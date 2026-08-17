<?php

namespace App\Services\Chat\Providers;

use App\Exceptions\AssistantException;
use Illuminate\Support\Facades\Http;

/**
 * Any endpoint that speaks the OpenAI /chat/completions shape.
 *
 * That covers most of the market — Groq, OpenRouter, Together, DeepSeek,
 * Mistral, Google's OpenAI-compatible Gemini endpoint, Ollama and other
 * self-hosted runtimes — so the owner can point the assistant at a free or
 * cheaper model without any code change.
 *
 * Raw HTTP is correct here: there is no single official SDK for "whatever
 * OpenAI-compatible host the owner typed into the admin panel".
 */
class OpenAiCompatibleProvider implements ChatProvider
{
    /**
     * Bounds a runaway tool-call loop — each iteration is a full billed API
     * call. Free-tier / small models are also not always reliable at function
     * calling: if one never stops calling tools, this is what cuts it off.
     */
    protected const MAX_TOOL_TURNS = 4;

    /** Ready-made endpoints, so nobody has to guess a base URL. */
    public const PRESETS = [
        'groq' => ['Groq (free tier)', 'https://api.groq.com/openai/v1', 'llama-3.3-70b-versatile', 'https://console.groq.com/keys'],
        'gemini' => ['Google Gemini (free tier)', 'https://generativelanguage.googleapis.com/v1beta/openai', 'gemini-3.6-flash', 'https://aistudio.google.com/apikey'],
        'openrouter' => ['OpenRouter', 'https://openrouter.ai/api/v1', 'meta-llama/llama-3.3-70b-instruct:free', 'https://openrouter.ai/keys'],
        'deepseek' => ['DeepSeek', 'https://api.deepseek.com/v1', 'deepseek-chat', 'https://platform.deepseek.com/api_keys'],
        'together' => ['Together AI', 'https://api.together.xyz/v1', 'meta-llama/Llama-3.3-70B-Instruct-Turbo', 'https://api.together.ai/settings/api-keys'],
        'custom' => ['Custom / self-hosted', '', '', ''],
    ];

    public function label(): string
    {
        $preset = self::PRESETS[config('assistant.openai_preset', 'custom')] ?? null;

        return ($preset[0] ?? 'OpenAI-compatible').' ('.config('assistant.openai_model').')';
    }

    public function complete(array $system, array $messages, array $tools = [], ?callable $toolExecutor = null): array
    {
        // No prompt caching here, so the system blocks are merged into one
        // system message rather than sent separately.
        $system = collect($system)->pluck('text')->filter()->implode("\n\n");

        $openAiTools = array_map(fn ($t) => [
            'type' => 'function',
            'function' => [
                'name' => $t['name'],
                'description' => $t['description'],
                'parameters' => $t['parameters'],
            ],
        ], $tools);

        $conversation = array_merge([['role' => 'system', 'content' => $system]], $messages);
        $inputTokens = 0;
        $outputTokens = 0;
        $text = '';

        for ($turn = 0; $turn < self::MAX_TOOL_TURNS; $turn++) {
            $response = Http::withToken((string) config('assistant.openai_api_key'))
                ->timeout(60)
                ->connectTimeout(15)
                ->acceptJson()
                ->post(rtrim((string) config('assistant.openai_base_url'), '/').'/chat/completions', array_filter([
                    'model' => (string) config('assistant.openai_model'),
                    'max_tokens' => (int) config('assistant.max_tokens', 1200),
                    'messages' => $conversation,
                    'tools' => $openAiTools ?: null,
                ], fn ($v) => $v !== null));

            if ($response->failed()) {
                // Providers disagree on error shape; try the common ones before
                // falling back to the raw body, which is better than nothing when
                // someone is debugging a new endpoint.
                $body = $response->json();
                $detail = $body['error']['message']
                    ?? $body['message']
                    ?? $body['detail']
                    ?? mb_substr($response->body(), 0, 300);

                throw new AssistantException("{$this->label()} returned HTTP {$response->status()}: {$detail}");
            }

            $body = $response->json();
            $message = $body['choices'][0]['message'] ?? [];
            $inputTokens += (int) ($body['usage']['prompt_tokens'] ?? 0);
            $outputTokens += (int) ($body['usage']['completion_tokens'] ?? 0);

            $text = $message['content'] ?? '';

            // Some providers return content as an array of parts.
            if (is_array($text)) {
                $text = collect($text)->pluck('text')->filter()->implode('');
            }
            $text = (string) $text;

            $toolCalls = $message['tool_calls'] ?? [];

            if (empty($toolCalls) || ! $toolExecutor) {
                break;
            }

            $conversation[] = $message;

            foreach ($toolCalls as $call) {
                $arguments = json_decode($call['function']['arguments'] ?? '{}', true);
                $result = $toolExecutor($call['function']['name'] ?? '', is_array($arguments) ? $arguments : []);

                $conversation[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'] ?? '',
                    'content' => $result,
                ];
            }
        }

        return [
            'text' => $text,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ];
    }
}
