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
    /** Bounds a runaway tool-call loop — each iteration is a full billed API call. */
    protected const MAX_TOOL_TURNS = 4;

    public function label(): string
    {
        return 'Claude ('.config('assistant.model').')';
    }

    public function complete(array $system, array $messages, array $tools = [], ?callable $toolExecutor = null): array
    {
        $blocks = [];

        foreach ($system as $block) {
            $blocks[] = array_filter([
                'type' => 'text',
                'text' => $block['text'],
                'cacheControl' => ($block['cache'] ?? false) ? ['type' => 'ephemeral'] : null,
            ], fn ($v) => $v !== null);
        }

        $claudeTools = array_map(fn ($t) => [
            'name' => $t['name'],
            'description' => $t['description'],
            'inputSchema' => $t['parameters'],
        ], $tools);

        $conversation = $messages;
        $inputTokens = 0;
        $outputTokens = 0;
        $text = '';

        for ($turn = 0; $turn < self::MAX_TOOL_TURNS; $turn++) {
            $message = $this->client()->messages->create(
                model: (string) config('assistant.model', 'claude-opus-5'),
                maxTokens: (int) config('assistant.max_tokens', 1200),
                system: $blocks,
                messages: $conversation,
                tools: $claudeTools ?: null,
            );

            $inputTokens += $message->usage->inputTokens ?? 0;
            $outputTokens += $message->usage->outputTokens ?? 0;

            $assistantBlocks = [];
            $toolUses = [];
            $text = '';

            foreach ($message->content as $content) {
                if ($content->type === 'text') {
                    $text .= $content->text;
                    $assistantBlocks[] = ['type' => 'text', 'text' => $content->text];
                } elseif ($content->type === 'tool_use') {
                    $toolUses[] = $content;
                    $assistantBlocks[] = ['type' => 'tool_use', 'id' => $content->id, 'name' => $content->name, 'input' => $content->input];
                }
            }

            if (empty($toolUses) || ! $toolExecutor) {
                break;
            }

            $conversation[] = ['role' => 'assistant', 'content' => $assistantBlocks];
            $conversation[] = ['role' => 'user', 'content' => array_map(
                fn ($tu) => ['type' => 'tool_result', 'tool_use_id' => $tu->id, 'content' => $toolExecutor($tu->name, $tu->input)],
                $toolUses
            )];
        }

        return [
            'text' => $text,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ];
    }

    protected function client(): Client
    {
        return new Client(apiKey: (string) config('assistant.api_key'));
    }
}
