<?php

use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Operation\OperationType;
use App\Domain\Enum\Permission\StoragePermission;
use App\Jobs\Bucket\FolderOperationJob;
use App\Models\AuditLogEntry;
use App\Models\BackgroundOperation;
use App\Models\Storage;
use Aws\Result;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function objectsUrl(string $name, Storage $storage, array $query = []): string
{
    return route('storages.buckets.objects.'.$name, ['storage' => $storage, 'bucket' => 'files', ...$query]);
}

describe('browsing', function () {
    it('shows the folder contents', function () {
        $storage = Storage::factory()->create();
        $s3 = fakeS3(new Result([
            'CommonPrefixes' => [['Prefix' => 'docs/a/']],
            'Contents' => [
                ['Key' => 'docs/', 'Size' => 0],
                ['Key' => 'docs/readme.txt', 'Size' => 12, 'ETag' => '"abc"', 'LastModified' => new DateTimeImmutable('2026-01-01')],
            ],
            'IsTruncated' => true,
            'NextContinuationToken' => 'next-page',
        ]));

        $this->actingAs(storageUser($storage, StoragePermission::ViewObjects))
            ->get(objectsUrl('index', $storage, ['prefix' => 'docs']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('objects/Index')
                ->where('prefix', 'docs/')
                ->where('listing.folders', ['docs/a/'])
                ->has('listing.objects', 1)
                ->where('listing.objects.0.name', 'readme.txt')
                ->where('listing.objects.0.etag', 'abc')
                ->where('listing.next', 'next-page')
                ->where('can.put', false));

        expect($s3->handler->getLastCommand()['Prefix'])->toBe('docs/')
            ->and($s3->handler->getLastCommand()['Delimiter'])->toBe('/');
    });

    it('loads the next page and searches by name start', function () {
        $storage = Storage::factory()->create();
        $s3 = fakeS3(new Result(['Contents' => [['Key' => 'rep-2.txt', 'Size' => 1]]]));

        $this->actingAs(storageUser($storage, StoragePermission::ViewObjects))
            ->getJson(objectsUrl('list', $storage, ['search' => 'rep', 'token' => 'tok']))
            ->assertOk()
            ->assertJsonPath('objects.0.key', 'rep-2.txt')
            ->assertJsonPath('next', null);

        expect($s3->handler->getLastCommand()['Prefix'])->toBe('rep')
            ->and($s3->handler->getLastCommand()['ContinuationToken'])->toBe('tok');
    });

    it('reports storage errors in the listing', function () {
        $storage = Storage::factory()->create();
        fakeS3(awsError('NoSuchBucket', 404));

        $this->actingAs(superadmin())
            ->getJson(objectsUrl('list', $storage))
            ->assertOk()
            ->assertJsonPath('objects', [])
            ->assertJson(fn ($json) => $json->whereType('error', 'string')->etc());
    });

    it('requires ViewObjects', function () {
        $storage = Storage::factory()->create();

        $this->actingAs(storageUser($storage, StoragePermission::ViewBuckets))
            ->get(objectsUrl('index', $storage))
            ->assertForbidden();
    });
});

describe('download and links', function () {
    it('redirects to a presigned URL on the public endpoint', function () {
        $storage = Storage::factory()->create(['endpoint' => 'http://minio:9000', 'public_endpoint' => 'https://files.example.com']);
        fakeS3();

        $response = $this->actingAs(storageUser($storage, StoragePermission::ViewObjects))
            ->get(objectsUrl('download', $storage, ['key' => 'docs/отчёт.pdf']));

        $location = (string) $response->headers->get('Location');

        expect($response->status())->toBe(302)
            ->and($location)->toStartWith('https://files.example.com/files/docs/')
            ->and($location)->toContain('X-Amz-Signature=')
            ->and($location)->toContain('response-content-disposition=')
            ->and(AuditLogEntry::query()->count())->toBe(0);
    });

    it('creates a shareable link and records it', function () {
        $storage = Storage::factory()->create();
        fakeS3();

        $this->actingAs(storageUser($storage, StoragePermission::SharePresignedLinks))
            ->postJson(objectsUrl('share', $storage), ['key' => 'a.txt', 'expires' => 3600])
            ->assertOk()
            ->assertJson(fn ($json) => $json->whereType('url', 'string')->whereType('expiresAt', 'string'));

        $entry = AuditLogEntry::query()->firstOrFail();

        expect($entry->action)->toBe(AuditAction::ObjectLinkCreated)
            ->and($entry->details['objects'])->toBe(['keys' => ['a.txt'], 'count' => 1]);
    });

    it('limits links to 7 days and requires the permission', function () {
        $storage = Storage::factory()->create();

        $this->actingAs(superadmin())
            ->postJson(objectsUrl('share', $storage), ['key' => 'a.txt', 'expires' => 604801])
            ->assertJsonValidationErrors('expires');

        $this->actingAs(storageUser($storage, StoragePermission::ViewObjects))
            ->postJson(objectsUrl('share', $storage), ['key' => 'a.txt', 'expires' => 60])
            ->assertForbidden();
    });
});

describe('changes', function () {
    it('deletes objects and versions and records them', function () {
        $storage = Storage::factory()->create();
        $s3 = fakeS3(new Result(['Deleted' => [['Key' => 'a.txt'], ['Key' => 'b.txt']]]));

        $this->actingAs(storageUser($storage, StoragePermission::DeleteObjects))
            ->deleteJson(objectsUrl('destroy', $storage), ['objects' => [['key' => 'a.txt'], ['key' => 'b.txt', 'version' => 'v1']]])
            ->assertOk()
            ->assertJsonPath('message', 'Удалено объектов: 2.');

        expect($s3->handler->getLastCommand()['Delete']['Objects'])->toBe([['Key' => 'a.txt'], ['Key' => 'b.txt', 'VersionId' => 'v1']])
            ->and(AuditLogEntry::query()->firstOrFail()->details['objects']['keys'])->toBe(['a.txt', 'b.txt (v1)']);
    });

    it('reports objects the storage refused to delete', function () {
        $storage = Storage::factory()->create();
        fakeS3(new Result(['Errors' => [['Key' => 'locked.txt', 'Code' => 'AccessDenied', 'Message' => 'Object is WORM protected']]]));

        $this->actingAs(superadmin())
            ->deleteJson(objectsUrl('destroy', $storage), ['objects' => [['key' => 'locked.txt']]])
            ->assertStatus(422)
            ->assertJson(fn ($json) => $json->where('message', fn (string $message) => str_contains($message, 'locked.txt'))->etc());

        expect(AuditLogEntry::query()->count())->toBe(0);
    });

    it('moving needs both put and delete permissions', function () {
        $storage = Storage::factory()->create();
        $payload = ['keys' => ['a.txt'], 'target_bucket' => 'other', 'target_prefix' => 'in', 'move' => true];
        $s3 = fakeS3(new Result(['ContentLength' => 1]), new Result([]), new Result(['Deleted' => [['Key' => 'a.txt']]]));

        $this->actingAs(storageUser($storage, StoragePermission::PutObjects))
            ->postJson(objectsUrl('transfer', $storage), $payload)
            ->assertForbidden();

        $this->actingAs(storageUser($storage, StoragePermission::PutObjects, StoragePermission::DeleteObjects))
            ->postJson(objectsUrl('transfer', $storage), $payload)
            ->assertOk();

        expect($s3->handler->getLastCommand()->getName())->toBe('DeleteObjects')->and(AuditLogEntry::query()->firstOrFail()->details['objects']['keys'])->toBe(['a.txt → other/in/a.txt']);
    });

    it('starts a folder operation in the background', function () {
        Queue::fake();
        $storage = Storage::factory()->create();

        $this->actingAs(storageUser($storage, StoragePermission::DeleteObjects))
            ->postJson(objectsUrl('folder-operation', $storage), ['type' => 'delete', 'prefix' => 'logs'])
            ->assertOk();

        $operation = BackgroundOperation::query()->firstOrFail();

        expect($operation->type)->toBe(OperationType::FolderDelete)
            ->and($operation->state['params'])->toBe(['prefix' => 'logs/'])
            ->and(AuditLogEntry::query()->firstOrFail()->action)->toBe(AuditAction::FolderDeletionStarted);
        Queue::assertPushed(FolderOperationJob::class);

        // Та же папка второй раз — нельзя, другая — можно
        $this->actingAs(superadmin())
            ->postJson(objectsUrl('folder-operation', $storage), ['type' => 'delete', 'prefix' => 'logs/'])
            ->assertStatus(422);
        $this->postJson(objectsUrl('folder-operation', $storage), ['type' => 'copy', 'prefix' => 'other/', 'target_bucket' => 'files', 'target_prefix' => 'copies'])
            ->assertOk();
    });

    it('refuses to copy a folder into itself', function () {
        Queue::fake();

        $this->actingAs(superadmin())
            ->postJson(objectsUrl('folder-operation', Storage::factory()->create()), [
                'type' => 'move', 'prefix' => 'a/', 'target_bucket' => 'files', 'target_prefix' => 'a/b',
            ])
            ->assertStatus(422);
    });

    it('validates metadata keys', function () {
        $this->actingAs(superadmin())
            ->putJson(objectsUrl('metadata', Storage::factory()->create()), ['key' => 'a', 'metadata' => [['key' => 'bad key', 'value' => '1']]])
            ->assertJsonValidationErrors('metadata.0.key');
    });

    it('uploads a small file', function () {
        $storage = Storage::factory()->create();
        $s3 = fakeS3(new Result([]));

        $this->actingAs(storageUser($storage, StoragePermission::PutObjects))
            ->post(objectsUrl('upload.store', $storage), [
                'key' => 'docs/hello.txt',
                'file' => UploadedFile::fake()->createWithContent('hello.txt', 'hello'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('key', 'docs/hello.txt');

        expect($s3->handler->getLastCommand()->getName())->toBe('PutObject')
            ->and($s3->handler->getLastCommand()['Key'])->toBe('docs/hello.txt')
            ->and(AuditLogEntry::query()->count())->toBe(0);
    });
});
