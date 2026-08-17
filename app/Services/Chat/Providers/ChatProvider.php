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
     * @return array{text: string, input_tokens: int, output_tokens: int}
     */
    public function complete(array $system, array $messages): array;

    /** Human-readable name for admin screens and error messages. */
    public function label(): string;
}
