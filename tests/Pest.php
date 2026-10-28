<?php

use App\Domain\Enum\Permission\SystemPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature', 'Integration');

function superadmin(): User
{
    return User::factory()->superadmin()->create();
}

function userWith(SystemPermission ...$permissions): User
{
    return User::factory()->withPermissions(...$permissions)->create();
}
