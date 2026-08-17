<?php

namespace App\Services\Chat\Providers;

/**
 * One LLM backend for the live chat.
 *
 * Kept deliberately narrow — the assistant only ever needs a system prompt,
 * a transcript, and a reply — so swapping providers is a settings change
 * rather than a rewrite.
 */
interface ChatProvider
{
    /**
     * @param  list<array{type: string, text: string, cache: bool}>  $system
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<array{name: string, description: string, parameters: array}>  $tools  Provider-neutral tool
     *                                                                                    definitions. When the model wants to call one, the provider runs the whole
     *                                                                                    tool-use loop itself (call model → run $toolExecutor → feed result back →
     *                                                                                    call model again) and returns only the final text, so callers never see
     *                                                                                    the intermediate tool-call turns.
     * @param  (callable(string $name, array $arguments): string)|null  $toolExecutor
     * @return array{text: string, input_tokens: int, output_tokens: int}
     */
    public function complete(array $system, array $messages, array $tools = [], ?callable $toolExecutor = null): array;

    /** Human-readable name for admin screens and error messages. */
    public function label(): string;
}
