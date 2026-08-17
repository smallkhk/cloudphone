<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Chat\ChatAssistant;
use App\Services\Chat\ChatTools;
use App\Services\Chat\SiteKnowledge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatHumanTakeoverTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        config(['assistant.enabled' => true, 'assistant.api_key' => 'sk-ant-test', 'assistant.provider' => 'claude']);
    }

    /** Replies with a fixed string and records that it was called. */
    protected function fakeAssistant(): void
    {
        $this->instance(ChatAssistant::class, new class(app(SiteKnowledge::class), app(ChatTools::class)) extends ChatAssistant
        {
            public static int $calls = 0;

            public function isEnabled(): bool
            {
                return true;
            }

            public function reply(ChatConversation $conversation, ChatMessage $userMessage): ChatMessage
            {
                self::$calls++;

                return $conversation->messages()->create(['role' => 'assistant', 'content' => 'Bot reply']);
            }
        });
    }

    /** Starts a conversation as a visitor and returns it. */
    protected function startConversation(): ChatConversation
    {
        $this->fakeAssistant();
        $this->postJson(route('chat.store'), ['message' => 'Hello?'])->assertOk();

        return ChatConversation::sole();
    }

    #[Test]
    public function an_admin_reply_reaches_the_customer(): void
    {
        $conversation = $this->startConversation();

        $this->actingAs($this->admin)
            ->post(route('admin.chat.reply', $conversation), ['message' => 'Hi, this is Emmanuel — how can I help?'])
            ->assertRedirect();

        // The customer's widget polls for anything newer than what it has.
        $response = $this->getJson(route('chat.history'));

        $response->assertOk()->assertJsonPath('human', true);
        $contents = collect($response->json('messages'))->pluck('content');
        $this->assertContains('Hi, this is Emmanuel — how can I help?', $contents);
    }

    #[Test]
    public function replying_takes_the_conversation_off_the_assistant(): void
    {
        $conversation = $this->startConversation();

        $this->actingAs($this->admin)
            ->post(route('admin.chat.reply', $conversation), ['message' => 'Taking this one.']);

        $this->assertTrue($conversation->fresh()->human_handling);
        $this->assertSame($this->admin->id, $conversation->fresh()->handled_by);
    }

    #[Test]
    public function the_assistant_stays_quiet_while_a_person_is_handling_it(): void
    {
        $conversation = $this->startConversation();
        $before = $conversation->fresh()->messages()->where('role', 'assistant')->count();

        $this->actingAs($this->admin)
            ->post(route('admin.chat.handling', $conversation), ['human_handling' => 1]);

        // Back as the visitor, in the same session.
        $this->post(route('chat.store'), ['message' => 'Are you there?'])
            ->assertOk()
            ->assertJsonPath('human', true);

        $this->assertSame(
            $before,
            $conversation->fresh()->messages()->where('role', 'assistant')->count(),
            'The assistant must not answer over a human.'
        );
        $this->assertDatabaseHas('chat_messages', ['content' => 'Are you there?', 'role' => 'user']);
    }

    #[Test]
    public function handing_back_lets_the_assistant_answer_again(): void
    {
        $conversation = $this->startConversation();

        $this->actingAs($this->admin)->post(route('admin.chat.handling', $conversation), ['human_handling' => 1]);
        $this->actingAs($this->admin)->post(route('admin.chat.handling', $conversation), ['human_handling' => 0]);

        $this->assertFalse($conversation->fresh()->human_handling);

        $this->postJson(route('chat.store'), ['message' => 'Still there?'])
            ->assertOk()
            ->assertJsonPath('reply', 'Bot reply');
    }

    #[Test]
    public function history_can_fetch_only_messages_the_browser_has_not_seen(): void
    {
        $conversation = $this->startConversation();
        $lastId = $conversation->messages()->max('id');

        $this->actingAs($this->admin)
            ->post(route('admin.chat.reply', $conversation), ['message' => 'A new answer']);

        $messages = $this->getJson(route('chat.history', ['after' => $lastId]))->json('messages');

        $this->assertCount(1, $messages);
        $this->assertSame('A new answer', $messages[0]['content']);
        $this->assertSame('agent', $messages[0]['role']);
    }

    #[Test]
    public function unanswered_conversations_are_flagged_for_the_owner(): void
    {
        $conversation = $this->startConversation();

        $this->assertSame(1, ChatConversation::unread()->count());

        // Opening the transcript clears it.
        $this->actingAs($this->admin)->get(route('admin.chat.show', $conversation))->assertOk();
        $this->assertSame(0, ChatConversation::unread()->count());
    }

    #[Test]
    public function only_admins_can_reply_or_take_over(): void
    {
        $conversation = $this->startConversation();
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->post(route('admin.chat.reply', $conversation), ['message' => 'sneaky'])
            ->assertForbidden();

        $this->actingAs($customer)
            ->post(route('admin.chat.handling', $conversation), ['human_handling' => 1])
            ->assertForbidden();

        $this->assertDatabaseMissing('chat_messages', ['content' => 'sneaky']);
    }
}
