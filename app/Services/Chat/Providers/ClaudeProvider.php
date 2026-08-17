<?php

namespace App\Services\Chat\Providers;

use Anthropic\Client;

/**
 * Anthropic's own API, via the official PHP SDK.
 *
 * The only provider here that supports prompt caching, which matters: the
 * catalogue half of the system prompt is identical on every message and would
 * otherwise be re-billed in full each time.
 */
class ClaudeProvider implements ChatProvider
{
    public function label(): string
    {
        return 'Claude ('.config('assistant.model').')';
    }

    public function complete(array $system, array $messages): array
    {
        $blocks = [];

        foreach ($system as $block) {
            $blocks[] = array_filter([
                'type' => 'text',
                'text' => $block['text'],
                'cacheControl' => ($block['cache'] ?? false) ? ['type' => 'ephemeral'] : null,
            ], fn ($v) => $v !== null);
        }

        $message = $this->client()->messages->create(
            model: (string) config('assistant.model', 'claude-opus-5'),
            maxTokens: (int) config('assistant.max_tokens', 1200),
            system: $blocks,
            messages: $messages,
        );

        $text = '';
        foreach ($message->content as $content) {
            if ($content->type === 'text') {
                $text .= $content->text;
            }
        }

        return [
            'text' => $text,
            'input_tokens' => $message->usage->inputTokens ?? 0,
            'output_tokens' => $message->usage->outputTokens ?? 0,
        ];
    }

    protected function client(): Client
    {
        return new Client(apiKey: (string) config('assistant.api_key'));
    }
}
