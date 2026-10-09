<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Create a user with one of the application's roles: "user", "admin" or "super admin".
     */
    protected function userWithRole(string $role, array $attributes = []): User
    {
        Role::findOrCreate($role, 'user');

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    protected function customer(array $attributes = []): User
    {
        return $this->userWithRole('user', $attributes);
    }

    protected function admin(array $attributes = []): User
    {
        return $this->userWithRole('admin', $attributes);
    }

    protected function superAdmin(array $attributes = []): User
    {
        return $this->userWithRole('super admin', $attributes);
    }
}
