<?php

use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageProvider;
use App\Models\AuditLogEntry;
use App\Models\Storage;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use GuzzleHttp\Psr7\Response;
use Inertia\Testing\AssertableInertia as Assert;

function settingsUrl(string $name, Storage $storage, string $bucket = 'data'): string
{
    return route('storages.buckets.'.$name, ['storage' => $storage, 'bucket' => $bucket]);
}

describe('page', function () {
    it('loads each section separately and hides unsupported ones', function () {
        $storage = Storage::factory()->create(['provider' => StorageProvider::Aws, 'endpoint' => null]);

        fakeS3(
            new Result(['Status' => 'Enabled']),
            new Result(['TagSet' => [['Key' => 'env', 'Value' => 'prod']]]),
            awsError('NoSuchBucketPolicy', 404),
            new Result(['CORSRules' => [['AllowedOrigins' => ['*'], 'AllowedMethods' => ['GET'], 'MaxAgeSeconds' => 60]]]),
        );

        $this->actingAs(storageUser($storage, StoragePermission::ViewBuckets, StoragePermission::ManageCors))
            ->get(settingsUrl('show', $storage))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('buckets/Show')
                ->where('bucket', 'data')
                ->where('can.manageCors', true)
                ->where('can.managePolicy', false)
                ->missing('versioning')
                ->loadDeferredProps(['versioning', 'tags', 'policy', 'cors'], fn (Assert $reload) => $reload
                    ->where('versioning.data', 'enabled')
                    ->where('tags.data.0.key', 'env')
                    ->where('policy', ['data' => null, 'error' => null])
                    ->where('cors.data.0.allowedOrigins', ['*'])
                    ->where('cors.data.0.maxAgeSeconds', 60)));
    });

    it('does not offer features the storage does not support', function () {
        $storage = Storage::factory()->create(['provider' => StorageProvider::CloudflareR2]);

        $this->actingAs(superadmin())
            ->get(settingsUrl('show', $storage))
            ->assertInertia(fn (Assert $page) => $page
                ->where('versioning', null)
                ->where('tags', null)
                ->where('policy', null));

        $this->put(settingsUrl('policy', $storage), ['policy' => null])->assertForbidden();
    });

    it('shows a section error without breaking other sections', function () {
        $storage = Storage::factory()->create();
        fakeS3(awsError('AccessDenied', 403));

        $this->actingAs(superadmin())
            ->get(settingsUrl('show', $storage))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps('versioning', fn (Assert $reload) => $reload
                ->where('versioning.data', null)
                ->where('versioning.error', 'Доступ запрещён. Проверьте права ключа. (AccessDenied)')));
    });

    it('requires ViewBuckets', function () {
        $storage = Storage::factory()->create();

        $this->actingAs(storageUser($storage, StoragePermission::ViewObjects))
            ->get(settingsUrl('show', $storage))
            ->assertForbidden();
    });
});

describe('versioning', function () {
    it('enables versioning and records the change', function () {
        $storage = Storage::factory()->create();
        $s3 = fakeS3(new Result([]), new Result([]));

        $this->actingAs(storageUser($storage, StoragePermission::ManageVersioning))
            ->put(settingsUrl('versioning', $storage), ['status' => 'enabled'])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'success');

        expect($s3->handler->getLastCommand()['VersioningConfiguration'])->toBe(['Status' => 'Enabled'])
            ->and(AuditLogEntry::query()->firstOrFail())
            ->action->toBe(AuditAction::BucketVersioningChanged)
            ->details->toMatchArray(['changes' => ['versioning' => ['from' => 'Не включено', 'to' => 'Включено']]]);
    });

    it('skips unchanged state and rejects turning versioning off', function () {
        $storage = Storage::factory()->create();
        $s3 = fakeS3(new Result(['Status' => 'Enabled']));

        $this->actingAs(superadmin());

        $this->put(settingsUrl('versioning', $storage), ['status' => 'enabled'])->assertSessionHasNoErrors();
        $this->put(settingsUrl('versioning', $storage), ['status' => 'unversioned'])->assertSessionHasErrors('status');

        expect($s3->handler->getLastCommand()->getName())->toBe('GetBucketVersioning')
            ->and(AuditLogEntry::query()->count())->toBe(0);
    });

    it('requires ManageVersioning', function () {
        $storage = Storage::factory()->create();

        $this->actingAs(storageUser($storage, StoragePermission::ViewBuckets))
            ->put(settingsUrl('versioning', $storage), ['status' => 'enabled'])
            ->assertForbidden();
    });
});

describe('tags', function () {
    it('saves tags and records added, changed and removed keys', function () {
        $storage = Storage::factory()->create();
        $s3 = fakeS3(new Result(['TagSet' => [['Key' => 'env', 'Value' => 'dev'], ['Key' => 'old', 'Value' => 'x']]]), new Result([]));

        $this->actingAs(superadmin())
            ->put(settingsUrl('tags', $storage), ['tags' => [['key' => 'env', 'value' => 'prod'], ['key' => 'team', 'value' => '']]])
            ->assertSessionHasNoErrors();

        expect($s3->handler->getLastCommand()['Tagging']['TagSet'])->toBe([
            ['Key' => 'env', 'Value' => 'prod'],
            ['Key' => 'team', 'Value' => ''],
        ])->and(AuditLogEntry::query()->firstOrFail()->details['changes'])->toBe([
            'tag:env' => ['from' => 'dev', 'to' => 'prod'],
            'tag:old' => ['from' => 'x', 'to' => null],
            'tag:team' => ['from' => null, 'to' => ''],
        ]);
    });

    it('deletes all tags with an empty list', function () {
        $storage = Storage::factory()->create();
        $s3 = fakeS3(new Result(['TagSet' => [['Key' => 'a', 'Value' => 'b']]]), new Result([]));

        $this->actingAs(superadmin())->put(settingsUrl('tags', $storage), ['tags' => []]);

        expect($s3->handler->getLastCommand()->getName())->toBe('DeleteBucketTagging');
    });

    it('validates tags', function (array $tags, string $error) {
        $this->actingAs(superadmin())
            ->put(settingsUrl('tags', Storage::factory()->create()), ['tags' => $tags])
            ->assertSessionHasErrors($error);
    })->with([
        'reserved prefix' => [[['key' => 'aws:x', 'value' => '1']], 'tags.0.key'],
        'duplicate keys' => [[['key' => 'a', 'value' => '1'], ['key' => 'a', 'value' => '2']], 'tags.0.key'],
        'too long value' => [[['key' => 'a', 'value' => str_repeat('v', 257)]], 'tags.0.value'],
        'too many' => [array_map(fn (int $i) => ['key' => "k{$i}", 'value' => ''], range(1, 51)), 'tags'],
    ]);
});

describe('policy', function () {
    $policy = json_encode([
        'Version' => '2012-10-17',
        'Statement' => [[
            'Effect' => 'Allow', 'Principal' => '*', 'Action' => 's3:GetObject', 'Resource' => 'arn:aws:s3:::data/*',
        ]],
    ], JSON_UNESCAPED_SLASHES);

    it('saves a policy and records both versions', function () use ($policy) {
        $storage = Storage::factory()->create();
        $s3 = fakeS3(awsError('NoSuchBucketPolicy', 404), new Result([]));

        $this->actingAs(storageUser($storage, StoragePermission::ManageBucketPolicy))
            ->put(settingsUrl('policy', $storage), ['policy' => $policy])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Политика сохранена.');

        $document = AuditLogEntry::query()->firstOrFail()->details['documents']['policy'];

        expect($s3->handler->getLastCommand()['Policy'])->toBe($policy)
            ->and($document['from'])->toBeNull()
            ->and(json_decode($document['to'], true))->toBe(json_decode($policy, true));
    });

    it('removes the policy', function () use ($policy) {
        $storage = Storage::factory()->create();
        $s3 = fakeS3(new Result(['Policy' => $policy]), new Result([]));

        $this->actingAs(superadmin())
            ->put(settingsUrl('policy', $storage), ['policy' => ''])
            ->assertInertiaFlash('toast.message', 'Политика удалена.');

        expect($s3->handler->getLastCommand()->getName())->toBe('DeleteBucketPolicy')
            ->and(AuditLogEntry::query()->firstOrFail()->details['documents']['policy']['to'])->toBeNull();
    });

    it('validates the policy structure', function (string $policy, string $message) {
        $this->actingAs(superadmin())
            ->put(settingsUrl('policy', Storage::factory()->create()), ['policy' => $policy])
            ->assertSessionHasErrors(['policy' => $message]);
    })->with([
        ['{"Statement": []}', 'В политике должно быть "Version": "2012-10-17".'],
        ['{"Version": "2012-10-17", "Statement": []}', 'В политике должно быть хотя бы одно утверждение (Statement).'],
        ['{"Version": "2012-10-17", "Statement": [{"Effect": "Maybe", "Action": "*", "Resource": "*"}]}', 'Утверждение 1: Effect должен быть Allow или Deny.'],
        ['{"Version": "2012-10-17", "Statement": {"Effect": "Allow", "Resource": "*"}}', 'Утверждение 1: нужен Action.'],
    ]);

    it('shows the storage explanation when it rejects the policy', function () use ($policy) {
        $storage = Storage::factory()->create();
        fakeS3(awsError('NoSuchBucketPolicy', 404), fn ($command) => new S3Exception('x', $command, [
            'code' => 'MalformedPolicy', 'message' => 'Invalid principal in policy', 'response' => new Response(400),
        ]));

        $this->actingAs(superadmin())
            ->put(settingsUrl('policy', $storage), ['policy' => $policy])
            ->assertInertiaFlash('toast.message', 'Хранилище отклонило изменение: Политика некорректна. Invalid principal in policy (MalformedPolicy)');

        expect(AuditLogEntry::query()->count())->toBe(0);
    });
});

describe('cors', function () {
    it('saves rules in S3 format', function () {
        $storage = Storage::factory()->create(['provider' => StorageProvider::Yandex]);
        $s3 = fakeS3(awsError('NoSuchCORSConfiguration', 404), new Result([]));

        $this->actingAs(storageUser($storage, StoragePermission::ManageCors))
            ->put(settingsUrl('cors', $storage), ['rules' => [[
                'allowed_origins' => ['https://app.example.com', ' '],
                'allowed_methods' => ['GET', 'PUT'],
                'allowed_headers' => ['*'],
                'expose_headers' => [],
                'max_age_seconds' => 600,
            ]]])
            ->assertSessionHasNoErrors();

        expect($s3->handler->getLastCommand()['CORSConfiguration']['CORSRules'])->toBe([[
            'AllowedOrigins' => ['https://app.example.com'],
            'AllowedMethods' => ['GET', 'PUT'],
            'AllowedHeaders' => ['*'],
            'MaxAgeSeconds' => 600,
        ]])->and(AuditLogEntry::query()->firstOrFail()->action)->toBe(AuditAction::BucketCorsChanged);
    });

    it('removes CORS with an empty list', function () {
        $storage = Storage::factory()->create(['provider' => StorageProvider::Yandex]);
        $s3 = fakeS3(new Result(['CORSRules' => [['AllowedOrigins' => ['*'], 'AllowedMethods' => ['GET']]]]), new Result([]));

        $this->actingAs(superadmin())->put(settingsUrl('cors', $storage), ['rules' => []]);

        expect($s3->handler->getLastCommand()->getName())->toBe('DeleteBucketCors');
    });

    it('validates rules', function () {
        $this->actingAs(superadmin())
            ->put(settingsUrl('cors', Storage::factory()->create(['provider' => StorageProvider::Yandex])), ['rules' => [[
                'allowed_origins' => [],
                'allowed_methods' => ['PATCH'],
            ]]])
            ->assertSessionHasErrors(['rules.0.allowed_origins', 'rules.0.allowed_methods.0']);
    });

    it('is unavailable on storages without CORS support', function () {
        $this->actingAs(superadmin())
            ->put(settingsUrl('cors', Storage::factory()->create()), ['rules' => []])
            ->assertForbidden();
    });
});
