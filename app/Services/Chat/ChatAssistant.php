<?php

namespace App\Services\Chat;

use Anthropic\Client;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Talks to Claude on behalf of the live-chat widget.
 *
 * Every request carries a system prompt generated from this store's live data
 * (see SiteKnowledge), which is how the assistant "learns" the site without
 * any training step: prices, stock and the customer's own devices are read
 * fresh on each message.
 */
class ChatAssistant
{
    public function __construct(protected SiteKnowledge $knowledge) {}

    /** Chat is only offered when it's switched on *and* an API key is stored. */
    public function isEnabled(): bool
    {
        return (bool) config('assistant.enabled') && filled(config('assistant.api_key'));
    }

    /**
     * Sends the conversation to Claude and stores the reply.
     *
     * @throws RuntimeException when the assistant is off or the API call fails
     */
    public function reply(ChatConversation $conversation, ChatMessage $userMessage): ChatMessage
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('The assistant is not configured.');
        }

        $history = $conversation->messages()
            ->whereNull('error')
            ->orderByDesc('id')
            ->limit((int) config('assistant.history_limit', 20))
            ->get()
            ->reverse()
            ->values()
            ->map(fn (ChatMessage $m) => [
                'role' => $m->role === 'assistant' ? 'assistant' : 'user',
                'content' => $m->content,
            ])
            ->all();

        $history = $this->normaliseTurns($history);

        try {
            $message = $this->client()->messages->create(
                model: (string) config('assistant.model', 'claude-opus-5'),
                maxTokens: (int) config('assistant.max_tokens', 1200),
                system: [
                    // Identical for every visitor, so it earns a cache breakpoint —
                    // the catalogue can run to thousands of tokens and would
                    // otherwise be re-billed in full on every single message.
                    [
                        'type' => 'text',
                        'text' => $this->knowledge->sitePrompt(),
                        'cacheControl' => ['type' => 'ephemeral'],
                    ],
                    [
                        'type' => 'text',
                        'text' => $this->knowledge->visitorPrompt($conversation->user),
                    ],
                ],
                messages: $history,
            );
        } catch (Throwable $e) {
            Log::error('Assistant reply failed', ['conversation' => $conversation->id, 'error' => $e->getMessage()]);

            $userMessage->update(['error' => mb_substr($e->getMessage(), 0, 255)]);

            throw new RuntimeException($this->friendlyError($e), previous: $e);
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $text = trim($text) ?: "Sorry, I didn't catch that — could you rephrase?";

        $reply = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $text,
            'input_tokens' => $message->usage->inputTokens ?? 0,
            'output_tokens' => $message->usage->outputTokens ?? 0,
        ]);

        $conversation->forceFill([
            'message_count' => $conversation->messages()->count(),
            'input_tokens' => $conversation->input_tokens + ($message->usage->inputTokens ?? 0),
            'output_tokens' => $conversation->output_tokens + ($message->usage->outputTokens ?? 0),
            'last_message_at' => now(),
        ])->save();

        return $reply;
    }

    /**
     * The API wants turns that start with the user and alternate. Trimming
     * history to the last N messages can leave a dangling assistant reply at
     * the front, and a failed send can leave two user turns in a row.
     *
     * @param  list<array{role: string, content: string}>  $turns
     * @return list<array{role: string, content: string}>
     */
    protected function normaliseTurns(array $turns): array
    {
        while ($turns && $turns[0]['role'] !== 'user') {
            array_shift($turns);
        }

        $merged = [];
        foreach ($turns as $turn) {
            $last = array_key_last($merged);

            if ($last !== null && $merged[$last]['role'] === $turn['role']) {
                $merged[$last]['content'] .= "\n\n".$turn['content'];

                continue;
            }

            $merged[] = $turn;
        }

        return $merged;
    }

    protected function client(): Client
    {
        return new Client(apiKey: (string) config('assistant.api_key'));
    }

    /** Visitors shouldn't see raw API errors; the detail goes to the log instead. */
    protected function friendlyError(Throwable $e): string
    {
        $message = $e->getMessage();

        return match (true) {
            str_contains($message, 'authentication') || str_contains($message, '401') => "The chat assistant isn't set up correctly yet. Please contact us directly.",
            str_contains($message, 'rate_limit') || str_contains($message, '429') => "We're getting a lot of questions right now — try again in a moment.",
            str_contains($message, 'credit') || str_contains($message, 'billing') => 'The chat assistant is temporarily unavailable. Please contact us directly.',
            default => "Sorry, I couldn't answer that just now. Please try again, or contact our support team.",
        };
    }
}
