<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\Chat\Providers\ChatProvider;
use App\Services\Chat\Providers\ClaudeProvider;
use App\Services\Chat\Providers\OpenAiCompatibleProvider;
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
    public function __construct(protected SiteKnowledge $knowledge, protected ChatTools $tools) {}

    /** Chat is only offered when it's switched on *and* a key is stored. */
    public function isEnabled(): bool
    {
        if (! config('assistant.enabled')) {
            return false;
        }

        return config('assistant.provider') === 'openai'
            ? filled(config('assistant.openai_api_key')) && filled(config('assistant.openai_base_url'))
            : filled(config('assistant.api_key'));
    }

    /** The configured backend. Claude unless the owner picked another. */
    public function provider(): ChatProvider
    {
        return config('assistant.provider') === 'openai'
            ? new OpenAiCompatibleProvider
            : new ClaudeProvider;
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
            // A human agent's reply is the assistant's own prior turn as far as
            // the model is concerned — it's what the customer was answered with.
            ->map(fn (ChatMessage $m) => [
                'role' => in_array($m->role, ['assistant', 'agent'], true) ? 'assistant' : 'user',
                'content' => $m->content,
            ])
            ->all();

        $history = $this->normaliseTurns($history);

        // Anonymous visitors own no devices, so there's nothing for the tools
        // to act on — omitting them entirely keeps the request smaller and
        // stops a model from trying to call a tool that can only ever error.
        $user = $conversation->user;
        $tools = $user ? $this->tools->definitions() : [];
        $toolExecutor = $user ? fn (string $name, array $args) => $this->tools->execute($user, $name, $args) : null;

        try {
            $result = $this->provider()->complete(
                system: [
                    // Identical for every visitor, so it earns a cache breakpoint
                    // where the provider supports one — the catalogue runs to
                    // thousands of tokens and would otherwise be re-billed in
                    // full on every single message.
                    ['type' => 'text', 'text' => $this->knowledge->sitePrompt(), 'cache' => true],
                    ['type' => 'text', 'text' => $this->knowledge->visitorPrompt($conversation->user), 'cache' => false],
                ],
                messages: $history,
                tools: $tools,
                toolExecutor: $toolExecutor,
            );
        } catch (Throwable $e) {
            Log::error('Assistant reply failed', ['conversation' => $conversation->id, 'error' => $e->getMessage()]);

            $userMessage->update(['error' => mb_substr($e->getMessage(), 0, 255)]);

            throw new RuntimeException($this->friendlyError($e), previous: $e);
        }

        $text = trim($result['text']) ?: "Sorry, I didn't catch that — could you rephrase?";

        $reply = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $text,
            'input_tokens' => $result['input_tokens'],
            'output_tokens' => $result['output_tokens'],
        ]);

        $conversation->forceFill([
            'message_count' => $conversation->messages()->count(),
            'input_tokens' => $conversation->input_tokens + $result['input_tokens'],
            'output_tokens' => $conversation->output_tokens + $result['output_tokens'],
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
