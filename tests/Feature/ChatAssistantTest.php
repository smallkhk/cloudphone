<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\CloudInstance;
use App\Models\Setting;
use App\Models\Sku;
use App\Models\User;
use App\Services\Chat\ChatAssistant;
use App\Services\Chat\ChatTools;
use App\Services\Chat\SiteKnowledge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ChatAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function enableAssistant(): void
    {
        config(['assistant.enabled' => true, 'assistant.api_key' => 'sk-ant-test']);
    }

    /** Swaps in a stub so tests never make a real (billed) API call. */
    protected function fakeAssistant(string $reply = 'Our cheapest plan is $4.99.'): void
    {
        $this->instance(ChatAssistant::class, new class($reply) extends ChatAssistant
        {
            public function __construct(protected string $reply)
            {
                parent::__construct(app(SiteKnowledge::class), app(ChatTools::class));
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function reply(ChatConversation $conversation, ChatMessage $userMessage): ChatMessage
            {
                return $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $this->reply,
                ]);
            }
        });
    }

    #[Test]
    public function the_widget_is_hidden_until_the_owner_turns_it_on(): void
    {
        config(['assistant.enabled' => false, 'assistant.api_key' => null]);

        $this->get(route('home'))->assertOk()->assertDontSee('chatWidget()', false);

        $this->enableAssistant();

        $this->get(route('home'))->assertOk()->assertSee('chatWidget()', false);
    }

    #[Test]
    public function it_refuses_to_chat_when_no_api_key_is_configured(): void
    {
        config(['assistant.enabled' => true, 'assistant.api_key' => null]);

        $this->postJson(route('chat.store'), ['message' => 'Hello'])
            ->assertStatus(503)
            ->assertJsonStructure(['error']);

        $this->assertSame(0, ChatMessage::count());
    }

    #[Test]
    public function a_guest_can_hold_a_conversation_and_it_is_persisted(): void
    {
        $this->enableAssistant();
        $this->fakeAssistant('Plans start at $4.99.');

        $this->postJson(route('chat.store'), ['message' => 'What do you sell?'])
            ->assertOk()
            ->assertJson(['reply' => 'Plans start at $4.99.']);

        $conversation = ChatConversation::sole();
        $this->assertNull($conversation->user_id);
        $this->assertSame(2, $conversation->messages()->count());

        // The transcript survives a reload.
        $this->getJson(route('chat.history'))
            ->assertOk()
            ->assertJsonPath('messages.0.content', 'What do you sell?')
            ->assertJsonPath('messages.1.content', 'Plans start at $4.99.');
    }

    #[Test]
    public function a_signed_in_customers_conversation_is_attributed_to_them(): void
    {
        $this->enableAssistant();
        $this->fakeAssistant();

        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('chat.store'), ['message' => 'Where is my device?'])->assertOk();

        $this->assertSame($user->id, ChatConversation::sole()->user_id);
    }

    #[Test]
    public function it_caps_how_many_messages_one_visitor_can_send_per_hour(): void
    {
        $this->enableAssistant();
        $this->fakeAssistant();
        config(['assistant.rate_limit_per_hour' => 2]);

        $this->postJson(route('chat.store'), ['message' => 'one'])->assertOk();
        $this->postJson(route('chat.store'), ['message' => 'two'])->assertOk();
        $this->postJson(route('chat.store'), ['message' => 'three'])->assertStatus(429);
    }

    #[Test]
    public function an_api_failure_is_reported_without_leaking_the_raw_error(): void
    {
        $this->enableAssistant();

        $this->instance(ChatAssistant::class, new class(app(SiteKnowledge::class), app(ChatTools::class)) extends ChatAssistant
        {
            public function isEnabled(): bool
            {
                return true;
            }

            public function reply(ChatConversation $conversation, ChatMessage $userMessage): ChatMessage
            {
                throw new RuntimeException('Sorry, I could not answer that just now.');
            }
        });

        $this->postJson(route('chat.store'), ['message' => 'Hello'])
            ->assertStatus(502)
            ->assertJsonPath('error', 'Sorry, I could not answer that just now.');
    }

    #[Test]
    public function the_message_must_not_be_empty_or_enormous(): void
    {
        $this->enableAssistant();
        $this->fakeAssistant();

        $this->postJson(route('chat.store'), ['message' => ''])->assertStatus(422);
        $this->postJson(route('chat.store'), ['message' => str_repeat('a', 2001)])->assertStatus(422);
    }

    #[Test]
    public function the_system_prompt_describes_the_live_catalogue_and_payment_rules(): void
    {
        Sku::factory()->create(['name' => 'Galaxy S23', 'duration_label' => '30 days', 'price' => 14.99, 'active' => true, 'sell_out' => false]);
        Setting::set('site_name', 'CloudPhone Store');
        Setting::set('crypto_usdt_trc20_address', 'TXtest000000000000000000000000000');

        $prompt = app(SiteKnowledge::class)->sitePrompt();

        $this->assertStringContainsString('CloudPhone Store', $prompt);
        $this->assertStringContainsString('Galaxy S23', $prompt);
        $this->assertStringContainsString('30 days $14.99', $prompt);
        $this->assertStringContainsString('TRC20', $prompt);
        // It must never hand out the wallet address itself.
        $this->assertStringNotContainsString('TXtest000000000000000000000000000', $prompt);
    }

    #[Test]
    public function the_visitor_prompt_includes_the_customers_own_devices(): void
    {
        $user = User::factory()->create(['name' => 'Ada', 'email' => 'ada@example.com']);
        CloudInstance::factory()->create(['user_id' => $user->id, 'pad_code' => 'AC90001', 'nickname' => 'Main phone']);

        $prompt = app(SiteKnowledge::class)->visitorPrompt($user);

        $this->assertStringContainsString('ada@example.com', $prompt);
        $this->assertStringContainsString('AC90001', $prompt);
        $this->assertStringContainsString('Main phone', $prompt);
    }

    #[Test]
    public function a_guest_prompt_says_they_must_register_before_buying(): void
    {
        $prompt = app(SiteKnowledge::class)->visitorPrompt(null);

        $this->assertStringContainsString('Not signed in', $prompt);
    }

    #[Test]
    public function owner_written_knowledge_is_added_to_the_prompt(): void
    {
        Setting::set('assistant_knowledge', 'Refunds: none once a device is provisioned.');

        $this->assertStringContainsString(
            'Refunds: none once a device is provisioned.',
            app(SiteKnowledge::class)->sitePrompt()
        );
    }

    #[Test]
    public function an_admin_can_read_and_delete_transcripts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $conversation = ChatConversation::create(['visitor_token' => 'tok-1']);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Do you take PayPal?']);

        $this->actingAs($admin)->get(route('admin.chat.index'))->assertOk()->assertSee('Do you take PayPal?');
        $this->actingAs($admin)->get(route('admin.chat.show', $conversation))->assertOk()->assertSee('Do you take PayPal?');

        $this->actingAs($admin)->delete(route('admin.chat.destroy', $conversation))->assertRedirect();
        $this->assertSame(0, ChatConversation::count());
    }

    #[Test]
    public function customers_cannot_read_other_peoples_transcripts(): void
    {
        $conversation = ChatConversation::create(['visitor_token' => 'tok-1']);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.chat.show', $conversation))
            ->assertForbidden();
    }
}
