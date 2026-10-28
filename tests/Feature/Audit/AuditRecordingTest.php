<?php

use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Permission\SystemPermission;
use App\Models\AuditLogEntry;
use App\Models\PermissionTemplate;
use App\Models\User;

function lastEntry(): AuditLogEntry
{
    return AuditLogEntry::query()->latest('id')->firstOrFail();
}

it('records user creation with actor, ip and without the password', function () {
    $admin = superadmin();

    $this->actingAs($admin)
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.7'])
        ->withHeader('User-Agent', 'TestAgent/1.0')
        ->post(route('users.store'), ['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'secret-password-1']);

    $entry = lastEntry();

    expect($entry)
        ->action->toBe(AuditAction::UserCreated)
        ->actor_id->toBe($admin->id)
        ->actor_email->toBe($admin->email)
        ->subject_label->toBe('Ann')
        ->source->toBe('web')
        ->ip->toBe('10.0.0.7')
        ->user_agent->toBe('TestAgent/1.0')
        ->and($entry->details['changes']['email'])->toBe(['from' => null, 'to' => 'ann@example.com'])
        ->and(json_encode($entry->getAttributes()))->not->toContain('secret-password-1');
});

it('records user lifecycle actions', function () {
    $target = User::factory()->create();
    $this->actingAs(superadmin());

    $this->put(route('users.update', $target), ['name' => 'Renamed', 'email' => $target->email]);
    $this->post(route('users.block', $target));
    $this->post(route('users.unblock', $target));
    $this->post(route('users.reset-password', $target));
    $this->delete(route('users.destroy', $target));
    $this->post(route('users.restore', $target));

    expect(AuditLogEntry::query()->where('subject_id', $target->id)->orderBy('id')->pluck('action')->all())->toBe([
        AuditAction::UserUpdated,
        AuditAction::UserBlocked,
        AuditAction::UserUnblocked,
        AuditAction::UserPasswordReset,
        AuditAction::UserDeleted,
        AuditAction::UserRestored,
    ]);

    $update = AuditLogEntry::query()->where('action', AuditAction::UserUpdated)->firstOrFail();
    expect($update->details)->toBe(['changes' => ['name' => ['from' => $target->name, 'to' => 'Renamed']]]);
});

it('does not record updates without changes', function () {
    $target = User::factory()->create();

    $this->actingAs(superadmin())
        ->put(route('users.update', $target), ['name' => $target->name, 'email' => $target->email]);

    expect(AuditLogEntry::query()->count())->toBe(0);
});

it('records own profile changes, password change and account deletion', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->patch(route('profile.update'), ['name' => 'Me', 'email' => $user->email]);
    $this->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);
    $this->delete(route('profile.destroy'), ['password' => 'new-password-123']);

    $entries = AuditLogEntry::query()->orderBy('id')->get();

    expect($entries->pluck('action')->all())->toBe([
        AuditAction::UserUpdated,
        AuditAction::UserPasswordChanged,
        AuditAction::UserDeleted,
    ])->and($entries->pluck('actor_id')->unique()->all())->toBe([$user->id]);
});

it('records system permission changes', function () {
    $target = userWith(SystemPermission::ViewAuditLog);

    $this->actingAs(superadmin())
        ->put(route('users.permissions.update', $target), ['permissions' => [SystemPermission::ManageUsers->value]]);

    expect(lastEntry())
        ->action->toBe(AuditAction::UserPermissionsChanged)
        ->details->toBe(['permissions' => ['scope' => 'system', 'granted' => ['ManageUsers'], 'revoked' => ['ViewAuditLog']]]);
});

it('records permission template changes', function () {
    $this->actingAs(superadmin());

    $this->post(route('permission-templates.store'), ['scope' => 'storage', 'name' => 'Readers', 'permissions' => [0, 12]]);
    $template = PermissionTemplate::query()->firstOrFail();
    $this->put(route('permission-templates.update', $template), ['name' => 'Viewers', 'permissions' => [0]]);
    $this->delete(route('permission-templates.destroy', $template));

    $entries = AuditLogEntry::query()->orderBy('id')->get();

    expect($entries->pluck('action')->all())->toBe([
        AuditAction::PermissionTemplateCreated,
        AuditAction::PermissionTemplateUpdated,
        AuditAction::PermissionTemplateDeleted,
    ])
        ->and($entries[0]->details['permissions']['granted'])->toBe(['ViewBuckets', 'ViewObjects'])
        ->and($entries[1]->details)->toBe([
            'changes' => ['name' => ['from' => 'Readers', 'to' => 'Viewers']],
            'permissions' => ['scope' => 'storage', 'granted' => [], 'revoked' => ['ViewObjects']],
        ])
        ->and($entries[2]->subject_label)->toBe('Viewers');
});

it('records console actions as system', function () {
    $this->artisan('app:create-superadmin', ['--name' => 'Root', '--email' => 'root@example.com', '--generate-password' => true])
        ->assertSuccessful();

    expect(lastEntry())
        ->action->toBe(AuditAction::UserCreated)
        ->actor_id->toBeNull()
        ->source->toBe('system')
        ->ip->toBeNull()
        ->details->toMatchArray(['flags' => ['superadmin' => true]]);
});

it('writes nothing when the action fails', function () {
    $admin = superadmin();

    $this->actingAs($admin)->delete(route('profile.destroy'), ['password' => 'password']);

    expect($admin->refresh()->trashed())->toBeFalse()
        ->and(AuditLogEntry::query()->count())->toBe(0);
});

it('keeps entries immutable', function () {
    $this->actingAs(superadmin())->post(route('users.store'), ['name' => 'Ann', 'email' => 'ann@example.com']);

    $entry = lastEntry();

    expect(fn () => $entry->update(['subject_label' => 'X']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});
