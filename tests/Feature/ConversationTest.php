<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    private function openConversation(User $customer, string $body = 'My printer does not work'): Conversation
    {
        Sanctum::actingAs($customer);
        $uuid = $this->postJson('/api/conversations', ['name' => 'Printer', 'body' => $body])
            ->assertCreated()
            ->json('data.uuid');

        return Conversation::where('uuid', $uuid)->firstOrFail();
    }

    public function test_a_customer_opens_a_conversation_with_a_first_message(): void
    {
        $customer = $this->customer();
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/conversations', ['name' => 'Printer', 'body' => 'My printer does not work'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Printer')
            ->assertJsonPath('data.user.id', $customer->id)
            ->assertJsonPath('data.latest_message.body', 'My printer does not work')
            ->assertJsonCount(1, 'data.participants');

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $response->json('data.uuid'));
        $this->assertDatabaseHas('messages', ['body' => 'My printer does not work', 'user_id' => $customer->id]);
    }

    public function test_a_conversation_needs_a_first_message(): void
    {
        Sanctum::actingAs($this->customer());

        $this->postJson('/api/conversations', ['name' => 'Empty'])->assertStatus(400);

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_customers_see_only_their_own_conversations_and_staff_see_all(): void
    {
        $first = $this->customer();
        $second = $this->customer();
        $mine = $this->openConversation($first);
        $this->openConversation($second);

        Sanctum::actingAs($first);
        $this->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $mine->uuid);

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/conversations')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_customer_cannot_read_or_write_in_someone_elses_conversation(): void
    {
        $conversation = $this->openConversation($this->customer());

        Sanctum::actingAs($this->customer());
        $this->getJson("/api/conversations/{$conversation->uuid}")->assertForbidden();
        $this->getJson("/api/conversations/{$conversation->uuid}/messages")->assertForbidden();
        $this->postJson("/api/conversations/{$conversation->uuid}/messages", ['body' => 'Hello'])->assertForbidden();
        $this->postJson("/api/conversations/{$conversation->uuid}/read")->assertForbidden();

        $this->assertDatabaseCount('messages', 1);
    }

    public function test_conversations_are_addressed_by_uuid_not_by_id(): void
    {
        $customer = $this->customer();
        $conversation = $this->openConversation($customer);

        $this->getJson("/api/conversations/{$conversation->id}")->assertNotFound();
        $this->getJson("/api/conversations/{$conversation->uuid}")->assertOk();
    }

    public function test_a_staff_reply_joins_the_conversation_and_shows_as_unread_to_the_customer(): void
    {
        $customer = $this->customer();
        $admin = $this->admin();
        $conversation = $this->openConversation($customer);

        $this->travel(1)->minutes();
        Sanctum::actingAs($admin);
        $this->postJson("/api/conversations/{$conversation->uuid}/messages", ['body' => 'Is it plugged in?'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Is it plugged in?')
            ->assertJsonPath('data.is_mine', true);

        $this->assertTrue($conversation->hasParticipant($admin));

        Sanctum::actingAs($customer);
        $this->getJson('/api/conversations')
            ->assertJsonPath('data.0.unread_count', 1)
            ->assertJsonPath('data.0.latest_message.body', 'Is it plugged in?');

        // Reading the messages clears the unread count
        $this->travel(1)->minutes();
        $this->getJson("/api/conversations/{$conversation->uuid}/messages")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.body', 'My printer does not work')
            ->assertJsonPath('data.0.is_mine', true)
            ->assertJsonPath('data.1.is_mine', false)
            ->assertJsonPath('data.1.user.name', $admin->name);

        $this->getJson('/api/conversations')->assertJsonPath('data.0.unread_count', 0);
    }

    public function test_a_new_conversation_is_unread_for_staff_until_they_mark_it_read(): void
    {
        $conversation = $this->openConversation($this->customer());

        $this->travel(1)->minutes();
        Sanctum::actingAs($this->admin());
        $this->getJson('/api/conversations')->assertJsonPath('data.0.unread_count', 1);

        $this->postJson("/api/conversations/{$conversation->uuid}/read")->assertOk();

        $this->getJson('/api/conversations')->assertJsonPath('data.0.unread_count', 0);
        $this->getJson("/api/conversations/{$conversation->uuid}")->assertJsonPath('data.unread_count', 0);
    }

    public function test_the_most_recently_active_conversation_is_listed_first(): void
    {
        $customer = $this->customer();
        $older = $this->openConversation($customer, 'First problem');
        $this->travel(5)->minutes();
        $newer = $this->openConversation($customer, 'Second problem');

        $this->getJson('/api/conversations')->assertJsonPath('data.0.uuid', $newer->uuid);

        $this->travel(5)->minutes();
        $this->postJson("/api/conversations/{$older->uuid}/messages", ['body' => 'Any news?'])->assertCreated();

        $this->getJson('/api/conversations')->assertJsonPath('data.0.uuid', $older->uuid);
    }

    public function test_a_message_needs_a_body(): void
    {
        $conversation = $this->openConversation($this->customer());

        $this->postJson("/api/conversations/{$conversation->uuid}/messages", ['body' => ''])->assertStatus(400);
    }

    public function test_only_staff_can_open_a_conversation_for_another_user(): void
    {
        $customer = $this->customer();
        $other = $this->customer();

        Sanctum::actingAs($other);
        $this->postJson('/api/conversations', ['body' => 'Hi', 'user_id' => $customer->id])->assertForbidden();

        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $this->postJson('/api/conversations', ['body' => 'We noticed a problem with your account', 'user_id' => $customer->id])
            ->assertCreated()
            ->assertJsonPath('data.user.id', $customer->id)
            ->assertJsonCount(2, 'data.participants');

        Sanctum::actingAs($customer);
        $this->getJson('/api/conversations')->assertJsonCount(1, 'data');
    }

    public function test_only_staff_can_delete_a_conversation(): void
    {
        $customer = $this->customer();
        $conversation = $this->openConversation($customer);

        $this->deleteJson("/api/conversations/{$conversation->uuid}")->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->deleteJson("/api/conversations/{$conversation->uuid}")->assertOk();

        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('conversation_user', 0);
    }
}
