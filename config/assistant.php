<?php

return [
    /*
    | The AI live-chat assistant. Every value here is also editable from
    | Admin → Settings → Assistant, which overlays this config at runtime.
    */

    'enabled' => env('ASSISTANT_ENABLED', false),

    /*
    | Which backend answers. 'claude' uses Anthropic's own API; 'openai' talks
    | to any OpenAI-compatible /chat/completions endpoint, which covers Groq,
    | Google Gemini, OpenRouter, DeepSeek, Together and self-hosted runtimes —
    | several of which have a free tier.
    */
    'provider' => env('ASSISTANT_PROVIDER', 'claude'),

    // --- Claude ---
    'api_key' => env('ANTHROPIC_API_KEY'),
    'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),

    // --- OpenAI-compatible ---
    'openai_preset' => env('ASSISTANT_OPENAI_PRESET', 'groq'),
    'openai_base_url' => env('ASSISTANT_OPENAI_BASE_URL', 'https://api.groq.com/openai/v1'),
    'openai_api_key' => env('ASSISTANT_OPENAI_API_KEY'),
    'openai_model' => env('ASSISTANT_OPENAI_MODEL', 'llama-3.3-70b-versatile'),

    'max_tokens' => (int) env('ASSISTANT_MAX_TOKENS', 1200),

    'greeting' => env('ASSISTANT_GREETING', "Hi! I'm the support assistant. Ask me anything about our cloud phones — plans, payment, or how to set your device up."),

    /** Messages one visitor may send per hour. Each one costs you money at Anthropic. */
    'rate_limit_per_hour' => (int) env('ASSISTANT_RATE_LIMIT', 25),

    /** How many past turns to replay to the model. Older turns are dropped. */
    'history_limit' => (int) env('ASSISTANT_HISTORY_LIMIT', 20),

    /** Extra business knowledge written by the site owner in the admin panel. */
    'knowledge' => null,
];
