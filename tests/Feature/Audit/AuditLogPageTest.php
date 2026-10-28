<?php

use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Permission\SystemPermission;
use App\Models\AuditLogEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function auditEntry(array $attributes = []): AuditLogEntry
{
    return AuditLogEntry::query()->forceCreate([
        'action' => AuditAction::UserBlocked,
        'subject_type' => 'user',
        'subject_id' => 1,
        'subject_label' => 'Target',
        'source' => 'web',
        ...$attributes,
    ]);
}

it('is available with ViewAuditLog permission or for superadmin only', function () {
    $this->actingAs(userWith(SystemPermission::ManageUsers, SystemPermission::GrantSystemPermissions))
        ->get(route('audit-log.index'))
        ->assertForbidden();

    $this->actingAs(userWith(SystemPermission::ViewAuditLog))
        ->get(route('audit-log.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audit-log/Index')
            ->where('auth.can.viewAuditLog', true)
            ->where('can.viewUsers', false));

    $this->actingAs(superadmin())->get(route('audit-log.index'))->assertOk();
});

it('shows readable details, newest first', function () {
    auditEntry(['action' => AuditAction::UserCreated, 'created_at' => now()->subHour()]);
    auditEntry([
        'action' => AuditAction::UserPermissionsChanged,
        'details' => ['permissions' => ['scope' => 'system', 'granted' => ['ManageUsers'], 'revoked' => ['Removed']]],
    ]);

    $this->actingAs(superadmin())
        ->get(route('audit-log.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 2)
            ->where('entries.data.0.actionLabel', 'Изменены базовые права')
            ->where('entries.data.0.lines', ['Выданы: Управлять пользователями', 'Сняты: Removed'])
            ->where('entries.data.1.action', 'user.created'));
});

it('filters by action, text, user and dates', function () {
    $admin = superadmin();
    $actor = User::factory()->create(['name' => 'Alice Actor']);

    auditEntry(['action' => AuditAction::UserCreated, 'subject_id' => 5, 'created_at' => '2026-01-10 12:00:00']);
    auditEntry(['actor_id' => $actor->id, 'actor_name' => 'Alice Actor', 'subject_id' => 6, 'created_at' => '2026-02-10 12:00:00']);
    auditEntry(['subject_label' => 'Special', 'subject_id' => 7, 'created_at' => '2026-03-10 12:00:00']);

    $ids = fn (array $query) => collect(
        $this->actingAs($admin)->get(route('audit-log.index', $query))->viewData('page')['props']['entries']['data'],
    )->pluck('subjectId')->all();

    expect($ids(['action' => 'user.created']))->toBe([5])
        ->and($ids(['search' => 'alice']))->toBe([6])
        ->and($ids(['search' => 'special']))->toBe([7])
        ->and($ids(['user_id' => 6]))->toBe([6])
        ->and($ids(['date_from' => '2026-02-01', 'date_to' => '2026-02-28']))->toBe([6]);
});

it('validates filters', function () {
    $this->actingAs(superadmin())
        ->get(route('audit-log.index', ['action' => 'nope', 'date_from' => '2026-02-10', 'date_to' => '2026-01-01']))
        ->assertSessionHasErrors(['action', 'date_to']);
});
