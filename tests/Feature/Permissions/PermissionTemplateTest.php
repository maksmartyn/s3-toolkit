<?php

use App\Domain\Enum\Permission\PermissionScope;
use App\Domain\Enum\Permission\SystemPermission;
use App\Models\PermissionTemplate;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('forbids templates without permission', function () {
    $template = PermissionTemplate::factory()->create();

    $this->actingAs(userWith(SystemPermission::ManageUsers, SystemPermission::GrantSystemPermissions));

    $this->get(route('permission-templates.index'))->assertForbidden();
    $this->get(route('permission-templates.create'))->assertForbidden();
    $this->post(route('permission-templates.store'), ['scope' => 'system', 'name' => 'X'])->assertForbidden();
    $this->get(route('permission-templates.edit', $template))->assertForbidden();
    $this->put(route('permission-templates.update', $template), ['name' => 'X'])->assertForbidden();
    $this->delete(route('permission-templates.destroy', $template))->assertForbidden();
});

it('lists templates of both scopes with permission options', function () {
    PermissionTemplate::factory()->create(['name' => 'Admins']);
    PermissionTemplate::factory()->storage()->create(['name' => 'Readers']);

    $this->actingAs(userWith(SystemPermission::ManagePermissionTemplates))
        ->get(route('permission-templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permission-templates/Index')
            ->has('templates', 2)
            ->has('scopes', 2)
            ->where('scopes.1.value', 'storage')
            ->has('scopes.1.options', 16)
            ->where('auth.can.viewPermissionTemplates', true));
});

it('creates system and storage templates', function () {
    $this->actingAs(userWith(SystemPermission::ManagePermissionTemplates));

    $this->post(route('permission-templates.store'), ['scope' => 'system', 'name' => 'Managers', 'permissions' => [3, 5]])
        ->assertRedirect(route('permission-templates.index'));
    $this->post(route('permission-templates.store'), ['scope' => 'storage', 'name' => 'Readers', 'permissions' => [0, 12, 15]])
        ->assertSessionHasNoErrors();

    expect(PermissionTemplate::query()->where('name', 'Managers')->firstOrFail())
        ->scope->toBe(PermissionScope::System)
        ->permissions->toBe((1 << 3) | (1 << 5))
        ->and(PermissionTemplate::query()->where('name', 'Readers')->firstOrFail()->permissions)->toBe(1 | (1 << 12) | (1 << 15));
});

it('validates permissions against the template scope', function () {
    $this->actingAs(superadmin())
        ->post(route('permission-templates.store'), ['scope' => 'system', 'name' => 'Bad', 'permissions' => [12]])
        ->assertSessionHasErrors('permissions.0');
});

it('requires unique names within a scope only', function () {
    PermissionTemplate::factory()->create(['name' => 'Base']);

    $this->actingAs(superadmin());

    $this->post(route('permission-templates.store'), ['scope' => 'system', 'name' => 'Base'])->assertSessionHasErrors('name');
    $this->post(route('permission-templates.store'), ['scope' => 'storage', 'name' => 'Base'])->assertSessionHasNoErrors();
});

it('updates a template without changing its scope', function () {
    $template = PermissionTemplate::factory()->storage()->create(['permissions' => 1]);

    $this->actingAs(superadmin())
        ->put(route('permission-templates.update', $template), ['name' => 'Renamed', 'permissions' => [13, 14], 'scope' => 'system'])
        ->assertSessionHasNoErrors();

    expect($template->refresh())
        ->name->toBe('Renamed')
        ->scope->toBe(PermissionScope::Storage)
        ->permissions->toBe((1 << 13) | (1 << 14));
});

it('opens the edit form with scope options', function () {
    $template = PermissionTemplate::factory()->create(['permissions' => 8]);

    $this->actingAs(superadmin())
        ->get(route('permission-templates.edit', $template))
        ->assertInertia(fn (Assert $page) => $page
            ->component('permission-templates/Form')
            ->where('template.permissions', [3])
            ->where('scope.value', 'system')
            ->has('scope.options', 6));
});

it('deletes a template without touching granted permissions', function () {
    $template = PermissionTemplate::factory()->create(['permissions' => 8]);
    $user = User::factory()->create(['permissions' => 8]);

    $this->actingAs(superadmin())
        ->delete(route('permission-templates.destroy', $template))
        ->assertRedirect(route('permission-templates.index'));

    expect(PermissionTemplate::query()->count())->toBe(0)
        ->and($user->refresh()->permissions->value)->toBe(8);
});

it('offers system templates on the user card only to granters', function () {
    PermissionTemplate::factory()->create(['name' => 'Sys']);
    PermissionTemplate::factory()->storage()->create(['name' => 'Store']);
    $target = User::factory()->create();

    $this->actingAs(userWith(SystemPermission::GrantSystemPermissions))
        ->get(route('users.edit', $target))
        ->assertInertia(fn (Assert $page) => $page->has('permissionTemplates', 1)->where('permissionTemplates.0.name', 'Sys'));

    $this->actingAs(userWith(SystemPermission::ManageUsers))
        ->get(route('users.edit', $target))
        ->assertInertia(fn (Assert $page) => $page->has('permissionTemplates', 0));
});
