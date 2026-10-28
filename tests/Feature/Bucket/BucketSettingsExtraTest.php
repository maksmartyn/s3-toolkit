<?php

use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageProvider;
use App\Models\AuditLogEntry;
use App\Models\Storage;
use Aws\Result;
use Inertia\Testing\AssertableInertia as Assert;

function extraUrl(string $name, Storage $storage): string
{
    return route('storages.buckets.'.$name, ['storage' => $storage, 'bucket' => 'site']);
}

describe('lifecycle', function () {
    it('saves rules, moving Prefix into Filter', function () {
        $storage = Storage::factory()->create();
        $s3 = fakeS3(awsError('NoSuchLifecycleConfiguration', 404), new Result([]));

        $this->actingAs(storageUser($storage, StoragePermission::ManageLifecycle))
            ->put(extraUrl('lifecycle', $storage), ['rules' => [[
                'ID' => 'logs', 'Status' => 'Enabled', 'Prefix' => 'logs/', 'Expiration' => ['Days' => 30],
            ]]])
            ->assertSessionHasNoErrors();

        expect($s3->handler->getLastCommand()['LifecycleConfiguration']['Rules'])->toBe([[
            'ID' => 'logs', 'Status' => 'Enabled', 'Expiration' => ['Days' => 30], 'Filter' => ['Prefix' => 'logs/'],
        ]])->and(AuditLogEntry::query()->firstOrFail()->action)->toBe(AuditAction::BucketLifecycleChanged);
    });

    it('requires an action in every rule', function () {
        $this->actingAs(superadmin())
            ->put(extraUrl('lifecycle', Storage::factory()->create()), ['rules' => [['Status' => 'Enabled']]])
            ->assertSessionHasErrors('rules.0');
    });

    it('removes the configuration with an empty list', function () {
        $storage = Storage::factory()->create();
        $s3 = fakeS3(new Result(['Rules' => [['Status' => 'Enabled', 'Expiration' => ['Days' => 1]]]]), new Result([]));

        $this->actingAs(superadmin())->put(extraUrl('lifecycle', $storage), ['rules' => []]);

        expect($s3->handler->getLastCommand()->getName())->toBe('DeleteBucketLifecycle');
    });
});

describe('acl', function () {
    it('detects the preset and applies a new one', function () {
        $storage = Storage::factory()->create(['provider' => StorageProvider::Yandex]);
        $grants = ['Owner' => ['ID' => 'owner'], 'Grants' => [
            ['Grantee' => ['Type' => 'CanonicalUser', 'ID' => 'owner'], 'Permission' => 'FULL_CONTROL'],
            ['Grantee' => ['Type' => 'Group', 'URI' => 'http://acs.amazonaws.com/groups/global/AllUsers'], 'Permission' => 'READ'],
        ]];
        $s3 = fakeS3(new Result($grants), new Result($grants), new Result([]));

        $this->actingAs(superadmin());

        $this->get(extraUrl('show', $storage))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps('acl', fn (Assert $reload) => $reload
                ->where('acl.data.canned', 'public-read')
                ->has('acl.data.grants', 2)));

        $this->put(extraUrl('acl', $storage), ['acl' => 'private'])->assertSessionHasNoErrors();

        expect($s3->handler->getLastCommand()['ACL'])->toBe('private')
            ->and(AuditLogEntry::query()->firstOrFail()->details['changes']['acl'])->toBe(['from' => 'Публичное чтение', 'to' => 'Приватный']);
    });

    it('requires ManageAcl and ACL support', function () {
        $yandex = Storage::factory()->create(['provider' => StorageProvider::Yandex]);

        $this->actingAs(storageUser($yandex, StoragePermission::ViewBuckets))
            ->put(extraUrl('acl', $yandex), ['acl' => 'private'])
            ->assertForbidden();

        $this->actingAs(superadmin())
            ->put(extraUrl('acl', Storage::factory()->create()), ['acl' => 'private'])
            ->assertForbidden();
    });
});

describe('website', function () {
    it('enables hosting with index and error documents', function () {
        $storage = Storage::factory()->create(['provider' => StorageProvider::Yandex]);
        $s3 = fakeS3(awsError('NoSuchWebsiteConfiguration', 404), new Result([]));

        $this->actingAs(storageUser($storage, StoragePermission::ManageWebsite))
            ->put(extraUrl('website', $storage), [
                'enabled' => true, 'mode' => 'index', 'index_document' => 'index.html', 'error_document' => '404.html',
            ])
            ->assertSessionHasNoErrors();

        expect($s3->handler->getLastCommand()['WebsiteConfiguration'])->toBe([
            'IndexDocument' => ['Suffix' => 'index.html'],
            'ErrorDocument' => ['Key' => '404.html'],
        ]);
    });

    it('redirects all requests and disables hosting', function () {
        $storage = Storage::factory()->create(['provider' => StorageProvider::Aws, 'endpoint' => null, 'region' => 'eu-west-1']);
        $s3 = fakeS3(
            new Result(['IndexDocument' => ['Suffix' => 'index.html']]),
            new Result([]),
            new Result(['RedirectAllRequestsTo' => ['HostName' => 'example.com', 'Protocol' => 'https']]),
            new Result(['RedirectAllRequestsTo' => ['HostName' => 'example.com', 'Protocol' => 'https']]),
            new Result([]),
        );

        $this->actingAs(superadmin());

        $this->put(extraUrl('website', $storage), ['enabled' => true, 'mode' => 'redirect', 'redirect_host' => 'example.com', 'redirect_protocol' => 'https'])
            ->assertSessionHasNoErrors();
        expect($s3->handler->getLastCommand()['WebsiteConfiguration'])->toBe(['RedirectAllRequestsTo' => ['HostName' => 'example.com', 'Protocol' => 'https']]);

        $this->get(extraUrl('show', $storage))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps('website', fn (Assert $reload) => $reload
                ->where('website.data.redirectHost', 'example.com')
                ->where('website.data.url', 'http://site.s3-website-eu-west-1.amazonaws.com')));

        $this->put(extraUrl('website', $storage), ['enabled' => false])->assertInertiaFlash('toast.message', 'Хостинг сайта выключен.');
        expect($s3->handler->getLastCommand()->getName())->toBe('DeleteBucketWebsite');
    });

    it('validates the form', function () {
        $this->actingAs(superadmin())
            ->put(extraUrl('website', Storage::factory()->create(['provider' => StorageProvider::Yandex])), [
                'enabled' => true, 'mode' => 'index', 'index_document' => 'dir/index.html', 'routing_rules' => '{bad',
            ])
            ->assertSessionHasErrors(['index_document', 'routing_rules']);
    });
});

describe('object lock', function () {
    it('requires versioning before enabling', function () {
        $storage = Storage::factory()->create();
        fakeS3(awsError('ObjectLockConfigurationNotFoundError', 404), new Result(['Status' => 'Suspended']));

        $this->actingAs(superadmin())
            ->put(extraUrl('object-lock', $storage), ['mode' => null])
            ->assertInertiaFlash('toast.message', 'Сначала включите версионирование, затем Object Lock.');
    });

    it('sets the default retention', function () {
        $storage = Storage::factory()->create();
        $s3 = fakeS3(new Result(['ObjectLockConfiguration' => ['ObjectLockEnabled' => 'Enabled']]), new Result([]));

        $this->actingAs(storageUser($storage, StoragePermission::ManageObjectLock))
            ->put(extraUrl('object-lock', $storage), ['mode' => 'GOVERNANCE', 'unit' => 'days', 'period' => 7])
            ->assertSessionHasNoErrors();

        expect($s3->handler->getLastCommand()['ObjectLockConfiguration'])->toBe([
            'ObjectLockEnabled' => 'Enabled',
            'Rule' => ['DefaultRetention' => ['Mode' => 'GOVERNANCE', 'Days' => 7]],
        ])->and(AuditLogEntry::query()->firstOrFail()->details['changes']['object_lock'])->toBe([
            'from' => 'Включено, без срока по умолчанию',
            'to' => 'Governance (управляемый), 7 дней',
        ]);
    });
});
