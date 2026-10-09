<?php

namespace Tests\Feature;

use App\Models\DefaultMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DefaultMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_users_can_read_default_messages_but_only_staff_can_change_them(): void
    {
        $message = DefaultMessage::create(['body' => 'How can we help you?']);
        Sanctum::actingAs($this->customer());

        $this->getJson('/api/default-messages')->assertOk()->assertJsonPath('data.0.body', 'How can we help you?');
        $this->getJson("/api/default-messages/{$message->id}")->assertOk();

        $this->postJson('/api/default-messages', ['body' => 'Spam'])->assertForbidden();
        $this->putJson("/api/default-messages/{$message->id}", ['body' => 'Changed'])->assertForbidden();
        $this->deleteJson("/api/default-messages/{$message->id}")->assertForbidden();

        $this->assertDatabaseHas('default_messages', ['body' => 'How can we help you?']);
        $this->assertDatabaseCount('default_messages', 1);
    }

    public function test_staff_create_default_messages_under_a_parent(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $parentId = $this->postJson('/api/default-messages', ['body' => 'Billing'])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $admin->id)
            ->json('data.id');

        $this->postJson('/api/default-messages', ['body' => 'Where is my invoice?', 'parent_id' => $parentId])
            ->assertCreated()
            ->assertJsonPath('data.parent_name', 'Billing');

        $this->postJson('/api/default-messages', ['body' => 'Billing'])->assertStatus(422);
        $this->postJson('/api/default-messages', ['body' => 'Orphan', 'parent_id' => 999])->assertStatus(422);
    }

    public function test_staff_update_a_default_message(): void
    {
        $message = DefaultMessage::create(['body' => 'Old text']);
        DefaultMessage::create(['body' => 'Taken text']);
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/default-messages/{$message->id}", ['body' => 'New text'])
            ->assertOk()
            ->assertJsonPath('data.body', 'New text');
        // Saving again with its own text is not a duplicate
        $this->putJson("/api/default-messages/{$message->id}", ['body' => 'New text'])->assertOk();
        $this->putJson("/api/default-messages/{$message->id}", ['body' => 'Taken text'])->assertStatus(422);
        $this->putJson("/api/default-messages/{$message->id}", ['body' => 'New text', 'parent_id' => $message->id])->assertStatus(400);
        $this->putJson('/api/default-messages/999', ['body' => 'Missing one'])->assertNotFound();
    }

    public function test_staff_delete_a_default_message_unless_it_has_children(): void
    {
        $parent = DefaultMessage::create(['body' => 'Billing']);
        $child = DefaultMessage::create(['body' => 'Where is my invoice?', 'parent_id' => $parent->id]);
        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/default-messages/{$parent->id}")->assertStatus(409);
        $this->deleteJson("/api/default-messages/{$child->id}")->assertOk();
        $this->deleteJson("/api/default-messages/{$parent->id}")->assertOk();
        $this->deleteJson("/api/default-messages/{$parent->id}")->assertNotFound();
        $this->getJson("/api/default-messages/{$parent->id}")->assertNotFound();

        $this->assertDatabaseCount('default_messages', 0);
    }
}
