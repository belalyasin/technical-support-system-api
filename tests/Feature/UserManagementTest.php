<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private array $newUser = [
        'name' => 'New Agent',
        'email' => 'agent@example.com',
        'phone' => '0591112223',
        'password' => 'secret123',
        'role' => 'admin',
    ];

    public function test_customers_cannot_manage_users(): void
    {
        Sanctum::actingAs($this->customer());

        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', $this->newUser)->assertForbidden();
    }

    public function test_staff_can_list_filter_and_search_users(): void
    {
        $this->customer(['name' => 'Layla Customer']);
        $this->customer(['name' => 'Omar Customer']);
        Sanctum::actingAs($this->admin(['name' => 'Agent Smith']));

        $this->getJson('/api/users')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/users?role=user')->assertJsonCount(2, 'data');
        $this->getJson('/api/users?search=layla')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Layla Customer')
            ->assertJsonPath('data.0.roles.0', 'user');
    }

    public function test_an_admin_can_create_staff_but_not_a_super_admin(): void
    {
        Role::findOrCreate('super admin', 'user');
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/users', $this->newUser)
            ->assertCreated()
            ->assertJsonPath('data.roles.0', 'admin');
        $this->assertTrue(User::where('email', 'agent@example.com')->firstOrFail()->isStaff());

        $this->postJson('/api/users', ['email' => 'boss@example.com', 'phone' => '0599998887', 'role' => 'super admin'] + $this->newUser)
            ->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'boss@example.com']);
    }

    public function test_a_super_admin_can_create_another_super_admin(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $this->postJson('/api/users', ['role' => 'super admin'] + $this->newUser)->assertCreated();

        $this->assertTrue(User::where('email', 'agent@example.com')->firstOrFail()->hasRole('super admin'));
    }

    public function test_staff_can_update_a_user_and_change_their_role(): void
    {
        $customer = $this->customer();
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/users/{$customer->id}", ['name' => 'Renamed User', 'role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed User')
            ->assertJsonPath('data.roles.0', 'admin');

        $this->assertTrue($customer->fresh()->isStaff());
    }

    public function test_an_admin_cannot_change_or_delete_a_super_admin(): void
    {
        Role::findOrCreate('user', 'user');
        $boss = $this->superAdmin();
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/users/{$boss->id}", ['role' => 'user'])->assertForbidden();
        $this->deleteJson("/api/users/{$boss->id}")->assertForbidden();

        $this->assertTrue($boss->fresh()->hasRole('super admin'));
    }

    public function test_updating_checks_for_duplicate_emails(): void
    {
        $first = $this->customer();
        $second = $this->customer();
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/users/{$second->id}", ['email' => $first->email])->assertStatus(400);
        $this->putJson("/api/users/{$second->id}", ['email' => $second->email])->assertOk();
    }

    public function test_deleting_a_customer_removes_their_conversations(): void
    {
        $customer = $this->customer();
        Sanctum::actingAs($customer);
        $this->postJson('/api/conversations', ['body' => 'Help'])->assertCreated();

        Sanctum::actingAs($this->admin());
        $this->deleteJson("/api/users/{$customer->id}")->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_a_user_who_replied_in_other_conversations_cannot_be_deleted(): void
    {
        $customer = $this->customer();
        $agent = $this->admin();
        Sanctum::actingAs($customer);
        $uuid = $this->postJson('/api/conversations', ['body' => 'Help'])->json('data.uuid');
        Sanctum::actingAs($agent);
        $this->postJson("/api/conversations/{$uuid}/messages", ['body' => 'On it'])->assertCreated();

        Sanctum::actingAs($this->superAdmin());
        $this->deleteJson("/api/users/{$agent->id}")->assertStatus(409);

        $this->assertDatabaseHas('users', ['id' => $agent->id]);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_staff_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/users/{$admin->id}")->assertStatus(400);
        $this->getJson('/api/users/999')->assertNotFound();
    }
}
