<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Services\Chat\ChatAssistant;
use App\Services\Chat\Providers\ClaudeProvider;
use App\Services\Chat\Providers\OpenAiCompatibleProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function useGroq(): void
    {
        config([
            'assistant.enabled' => true,
            'assistant.provider' => 'openai',
            'assistant.openai_base_url' => 'https://api.groq.com/openai/v1',
            'assistant.openai_api_key' => 'gsk-test',
            'assistant.openai_model' => 'llama-3.3-70b-versatile',
        ]);
    }

    #[Test]
    public function it_uses_claude_by_default(): void
    {
        config(['assistant.provider' => 'claude']);

        $this->assertInstanceOf(ClaudeProvider::class, app(ChatAssistant::class)->provider());
    }

    #[Test]
    public function it_switches_to_the_openai_compatible_provider(): void
    {
        $this->useGroq();

        $this->assertInstanceOf(OpenAiCompatibleProvider::class, app(ChatAssistant::class)->provider());
    }

    #[Test]
    public function chat_stays_off_until_the_chosen_provider_is_configured(): void
    {
        config(['assistant.enabled' => true, 'assistant.provider' => 'openai', 'assistant.openai_api_key' => null]);
        $this->assertFalse(app(ChatAssistant::class)->isEnabled());

        $this->useGroq();
        $this->assertTrue(app(ChatAssistant::class)->isEnabled());

        // A Claude key must not enable an OpenAI-compatible provider.
        config(['assistant.openai_api_key' => null, 'assistant.api_key' => 'sk-ant-test']);
        $this->assertFalse(app(ChatAssistant::class)->isEnabled());
    }

    #[Test]
    public function it_posts_an_openai_shaped_request_and_reads_the_reply(): void
    {
        $this->useGroq();

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Plans start at $4.99.']]],
                'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 20],
            ]),
        ]);

        $result = (new OpenAiCompatibleProvider)->complete(
            system: [
                ['type' => 'text', 'text' => 'SITE', 'cache' => true],
                ['type' => 'text', 'text' => 'VISITOR', 'cache' => false],
            ],
            messages: [['role' => 'user', 'content' => 'hi']],
        );

        $this->assertSame('Plans start at $4.99.', $result['text']);
        $this->assertSame(900, $result['input_tokens']);
        $this->assertSame(20, $result['output_tokens']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer gsk-test')
                && $body['model'] === 'llama-3.3-70b-versatile'
                // No prompt caching here, so both system blocks are merged into
                // a single system message.
                && $body['messages'][0]['role'] === 'system'
                && str_contains($body['messages'][0]['content'], 'SITE')
                && str_contains($body['messages'][0]['content'], 'VISITOR')
                && $body['messages'][1] === ['role' => 'user', 'content' => 'hi'];
        });
    }

    #[Test]
    public function a_provider_error_surfaces_its_message(): void
    {
        $this->useGroq();

        Http::fake([
            '*/chat/completions' => Http::response(['error' => ['message' => 'Rate limit reached']], 429),
        ]);

        $this->expectExceptionMessage('Rate limit reached');

        (new OpenAiCompatibleProvider)->complete(
            system: [['type' => 'text', 'text' => 'SITE', 'cache' => false]],
            messages: [['role' => 'user', 'content' => 'hi']],
        );
    }

    #[Test]
    public function a_customer_gets_a_reply_through_the_free_provider(): void
    {
        $this->useGroq();

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Yes, you can change the number.']]],
                'usage' => ['prompt_tokens' => 800, 'completion_tokens' => 12],
            ]),
        ]);

        $this->postJson(route('chat.store'), ['message' => 'Can I change the phone number?'])
            ->assertOk()
            ->assertJsonPath('reply', 'Yes, you can change the number.');

        $conversation = ChatConversation::sole();
        $this->assertSame(800, $conversation->input_tokens);
        $this->assertSame(12, $conversation->output_tokens);
    }
}
