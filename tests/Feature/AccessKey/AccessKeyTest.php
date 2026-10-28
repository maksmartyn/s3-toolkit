<?php

use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Permission\SystemPermission;
use App\Domain\Enum\Storage\StorageProvider;
use App\Models\AuditLogEntry;
use App\Models\Storage;
use App\Services\AccessKey\Support\IamClientFactory;
use App\Services\AccessKey\Support\RsaPss;
use Aws\MockHandler;
use Aws\Result;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;

function keysUrl(Storage $storage, string $name = 'index', array $query = []): string
{
    return route('storages.access-keys.'.$name, ['storage' => $storage, ...$query]);
}

function fakeIam(mixed ...$responses): MockHandler
{
    $handler = new MockHandler($responses);

    app()->instance(IamClientFactory::class, new class($handler) extends IamClientFactory
    {
        public function __construct(private MockHandler $handler) {}

        protected function config(string $accessKeyId, string $secretAccessKey): array
        {
            return [...parent::config($accessKeyId, $secretAccessKey), 'handler' => $this->handler];
        }
    });

    return $handler;
}

describe('access', function () {
    it('requires ManageAccessKeys to manage keys and ManageStorages to configure', function () {
        $storage = Storage::factory()->create(['provider' => StorageProvider::Aws, 'endpoint' => null]);

        $this->actingAs(storageUser($storage, StoragePermission::ViewBuckets))
            ->get(keysUrl($storage))
            ->assertForbidden();

        $this->actingAs(storageUser($storage, StoragePermission::ManageAccessKeys))
            ->put(keysUrl($storage, 'credentials'), ['credentials' => []])
            ->assertForbidden();

        // Управляющий хранилищами видит страницу (чтобы настроить), но не ключи
        $this->actingAs(userWith(SystemPermission::ManageStorages))
            ->get(keysUrl($storage))
            ->assertInertia(fn (Assert $page) => $page
                ->component('access-keys/Index')
                ->where('can', ['manage' => false, 'configure' => true])
                ->where('settings.supported', true)
                ->where('overview', null));
    });

    it('reports unsupported providers', function () {
        $storage = Storage::factory()->create(['provider' => StorageProvider::Generic]);

        $this->actingAs(superadmin())
            ->get(keysUrl($storage))
            ->assertInertia(fn (Assert $page) => $page->where('settings.supported', false)->where('overview', null));
    });
});

describe('credentials', function () {
    it('keeps stored secrets when the field is left empty and records changes', function () {
        $storage = Storage::factory()->create(['provider' => StorageProvider::CloudflareR2]);
        $this->actingAs(superadmin());

        $this->put(keysUrl($storage, 'credentials'), ['credentials' => ['account_id' => 'acc', 'api_token' => 'secret-token']])
            ->assertSessionHasNoErrors();
        $this->put(keysUrl($storage, 'credentials'), ['credentials' => ['account_id' => 'acc2', 'api_token' => '']]);

        expect($storage->fresh()->admin_credentials)->toBe(['account_id' => 'acc2', 'api_token' => 'secret-token'])
            ->and(AuditLogEntry::query()->where('action', AuditAction::KeyManagementConfigured)->count())->toBe(2);

        $this->get(keysUrl($storage))->assertInertia(fn (Assert $page) => $page
            ->where('settings.configured', true)
            ->where('settings.fields.0.value', 'acc2')
            ->where('settings.fields.1.value', null)
            ->where('settings.fields.1.isSet', true));
    });

    it('validates the Yandex authorized key', function () {
        $storage = Storage::factory()->create(['provider' => StorageProvider::Yandex]);

        $this->actingAs(superadmin())
            ->put(keysUrl($storage, 'credentials'), ['credentials' => ['authorized_key' => '{"id": 1}']])
            ->assertInertiaFlash('toast.type', 'error');

        expect($storage->fresh()->admin_credentials)->toBeNull();
    });
});

describe('aws', function () {
    it('lists, creates, disables and deletes IAM keys', function () {
        $storage = Storage::factory()->create(['provider' => StorageProvider::Aws, 'endpoint' => null]);
        $iam = fakeIam(
            new Result(['Users' => [['UserName' => 'backup']], 'IsTruncated' => false]),
            new Result(['AccessKeyMetadata' => [
                ['AccessKeyId' => 'AKIA1EXAMPLEKEY00', 'UserName' => 'backup', 'Status' => 'Inactive', 'CreateDate' => new DateTimeImmutable('2026-01-01')],
            ], 'IsTruncated' => false]),
            new Result(['AccessKey' => ['AccessKeyId' => 'AKIA2EXAMPLEKEY00', 'SecretAccessKey' => 'shh', 'UserName' => 'backup']]),
            new Result([]),
            new Result([]),
        );

        $this->actingAs(storageUser($storage, StoragePermission::ManageAccessKeys));

        $this->get(keysUrl($storage, query: ['user' => 'backup']))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('overview.users', ['backup'])
                ->where('overview.keys.0.accessKeyId', 'AKIA1EXAMPLEKEY00')
                ->where('overview.keys.0.status', 'inactive')));

        $this->postJson(keysUrl($storage, 'store'), ['user' => 'backup'])
            ->assertOk()
            ->assertJson(['accessKeyId' => 'AKIA2EXAMPLEKEY00', 'secretAccessKey' => 'shh']);
        expect($iam->getLastCommand()['UserName'])->toBe('backup');

        $this->putJson(keysUrl($storage, 'status'), ['id' => 'AKIA2EXAMPLEKEY00', 'user' => 'backup', 'active' => false])->assertOk();
        expect($iam->getLastCommand()['Status'])->toBe('Inactive');

        $this->deleteJson(keysUrl($storage, 'destroy'), ['id' => 'AKIA2EXAMPLEKEY00', 'user' => 'backup'])->assertOk();
        expect($iam->getLastCommand()->getName())->toBe('DeleteAccessKey');

        $actions = AuditLogEntry::query()->orderBy('id')->pluck('action')->all();
        expect($actions)->toBe([AuditAction::AccessKeyCreated, AuditAction::AccessKeyStatusChanged, AuditAction::AccessKeyDeleted])
            ->and(json_encode(AuditLogEntry::query()->pluck('details')))->not->toContain('shh');
    });

    it('turns provider errors into messages', function () {
        $storage = Storage::factory()->create(['provider' => StorageProvider::Aws, 'endpoint' => null]);
        fakeIam(awsError('AccessDenied', 403));

        $this->actingAs(superadmin())
            ->postJson(keysUrl($storage, 'store'), [])
            ->assertStatus(422)
            ->assertJson(fn ($json) => $json->where('message', fn (string $message) => str_contains($message, 'Доступ запрещён'))->etc());

        expect(AuditLogEntry::query()->count())->toBe(0);
    });
});

describe('http providers', function () {
    it('manages Yandex Cloud static keys with an IAM token from a signed JWT', function () {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $storage = Storage::factory()->create(['provider' => StorageProvider::Yandex, 'admin_credentials' => [
            'authorized_key' => json_encode(['id' => 'kid1', 'service_account_id' => 'sa1', 'private_key' => $pem]),
        ]]);

        Http::fake([
            'iam.api.cloud.yandex.net/iam/v1/tokens' => Http::response(['iamToken' => 't0ken']),
            'iam.api.cloud.yandex.net/iam/aws-compatibility/v1/accessKeys?*' => Http::response(['accessKeys' => [
                ['id' => 'aje1', 'keyId' => 'YCAJ1', 'serviceAccountId' => 'sa1', 'description' => 'backup', 'createdAt' => '2026-01-01T00:00:00Z'],
            ]]),
            'iam.api.cloud.yandex.net/iam/aws-compatibility/v1/accessKeys' => Http::response(['accessKey' => ['id' => 'aje2', 'keyId' => 'YCAJ2'], 'secret' => 'ys']),
            'iam.api.cloud.yandex.net/iam/aws-compatibility/v1/accessKeys/aje2' => Http::response([]),
        ]);

        $this->actingAs(superadmin());

        $this->get(keysUrl($storage))->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('overview.keys.0.id', 'aje1')
            ->where('overview.keys.0.accessKeyId', 'YCAJ1')));

        $this->postJson(keysUrl($storage, 'store'), ['description' => 'new'])->assertJson(['accessKeyId' => 'YCAJ2', 'secretAccessKey' => 'ys']);
        $this->deleteJson(keysUrl($storage, 'destroy'), ['id' => 'aje2'])->assertOk();

        Http::assertSent(function (Request $request) {
            if (! str_ends_with($request->url(), '/iam/v1/tokens')) {
                return false;
            }

            [$header] = explode('.', $request['jwt']);

            return json_decode(base64_decode(strtr($header, '-_', '+/')), true) === ['typ' => 'JWT', 'alg' => 'PS256', 'kid' => 'kid1'];
        });
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer t0ken')
            && $request->method() === 'POST' && $request['serviceAccountId'] === 'sa1');
    });

    it('manages EC2 credentials in OpenStack Keystone', function () {
        $storage = Storage::factory()->create(['provider' => StorageProvider::Selectel, 'admin_credentials' => [
            'auth_url' => 'https://cloud.api.selcloud.ru/identity/v3', 'username' => 'svc', 'password' => 'pw', 'domain' => '123456', 'project_id' => 'p1',
        ]]);

        Http::fake([
            '*/auth/tokens' => Http::response(['token' => ['user' => ['id' => 'u1']]], 201, ['X-Subject-Token' => 'kt']),
            '*/users/u1/credentials/OS-EC2' => Http::sequence()
                ->push(['credentials' => [['access' => 'a1', 'secret' => 's1', 'tenant_id' => 'p1'], ['access' => 'a9', 'tenant_id' => 'other']]])
                ->push(['credential' => ['access' => 'a2', 'secret' => 's2']], 201),
        ]);

        $this->actingAs(superadmin());

        $this->get(keysUrl($storage))->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('overview.keys', 1)
            ->where('overview.keys.0.accessKeyId', 'a1')));

        $this->postJson(keysUrl($storage, 'store'))->assertJson(['accessKeyId' => 'a2', 'secretAccessKey' => 's2']);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/auth/tokens')
            && $request['auth']['identity']['password']['user']['domain']['name'] === '123456');
        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Auth-Token', 'kt') && ($request->data()['tenant_id'] ?? null) === 'p1');
    });

    it('manages Cloudflare R2 tokens, hashing the token value into the secret', function () {
        $storage = Storage::factory()->create(['provider' => StorageProvider::CloudflareR2, 'admin_credentials' => ['account_id' => 'acc', 'api_token' => 'cf']]);

        Http::fake([
            'api.cloudflare.com/client/v4/accounts/acc/tokens/permission_groups' => Http::response(['result' => [
                ['id' => 'g1', 'name' => 'Workers R2 Storage Bucket Item Write'],
            ]]),
            'api.cloudflare.com/client/v4/accounts/acc/tokens?*' => Http::response(['result' => [
                ['id' => 't1', 'name' => 'r2', 'status' => 'disabled', 'policies' => [['permission_groups' => [['name' => 'Workers R2 Storage Bucket Item Read']]]]],
                ['id' => 't2', 'name' => 'dns', 'status' => 'active', 'policies' => [['permission_groups' => [['name' => 'DNS Write']]]]],
            ]]),
            'api.cloudflare.com/client/v4/accounts/acc/tokens' => Http::response(['result' => ['id' => 't3', 'value' => 'token-value']]),
        ]);

        $this->actingAs(superadmin());

        $this->get(keysUrl($storage))->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('overview.keys', 1)
            ->where('overview.keys.0.status', 'inactive')));

        $this->postJson(keysUrl($storage, 'store'), ['name' => 'backup'])
            ->assertJson(['accessKeyId' => 't3', 'secretAccessKey' => hash('sha256', 'token-value')]);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request['policies'][0]['permission_groups'] === [['id' => 'g1']]
            && $request->hasHeader('Authorization', 'Bearer cf'));
    });
});

describe('minio', function () {
    it('passes generated keys and the connection to mc', function () {
        $storage = Storage::factory()->create(['endpoint' => 'http://minio:9000', 'admin_credentials' => ['user' => 'app']]);
        Process::fake(['*' => Process::result('{"status":"success","accessKey":"X"}')]);

        $secret = $this->actingAs(superadmin())
            ->postJson(keysUrl($storage, 'store'), ['name' => 'ci', 'expires_at' => now()->addDay()->toIso8601String()])
            ->assertOk()
            ->json();

        Process::assertRan(function ($process) use ($secret) {
            $command = implode(' ', (array) $process->command);

            return str_contains($command, 'admin user svcacct add s3t app')
                && str_contains($command, '--access-key '.$secret['accessKeyId'])
                && str_contains($command, '--name ci')
                && str_starts_with((string) $process->environment['MC_HOST_s3t'], 'http://');
        });
        expect($secret['secretAccessKey'])->toHaveLength(40);
    });

    it('shows mc errors', function () {
        $storage = Storage::factory()->create(['endpoint' => 'http://minio:9000', 'admin_credentials' => []]);
        Process::fake(['*' => Process::result('{"status":"error","error":{"message":"Unable to add","cause":{"message":"Access Denied."}}}', exitCode: 1)]);

        $this->actingAs(superadmin())
            ->deleteJson(keysUrl($storage, 'destroy'), ['id' => 'X'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Провайдер отклонил запрос: Unable to add Access Denied.');
    });
});

it('signs RSA-PSS so that openssl verifies it', function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    $dir = sys_get_temp_dir().'/pss-'.uniqid();
    mkdir($dir);
    file_put_contents("$dir/pub.pem", openssl_pkey_get_details($key)['key']);
    file_put_contents("$dir/data", 'header.payload');
    file_put_contents("$dir/sig", RsaPss::sign('header.payload', $pem));

    $result = Process::run(['openssl', 'dgst', '-sha256', '-sigopt', 'rsa_padding_mode:pss', '-sigopt', 'rsa_pss_saltlen:32',
        '-verify', "$dir/pub.pem", '-signature', "$dir/sig", "$dir/data"]);

    expect(trim($result->output()))->toBe('Verified OK');
});
