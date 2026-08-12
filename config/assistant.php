<?php

return [
    /*
    | The AI live-chat assistant. Every value here is also editable from
    | Admin → Settings → Assistant, which overlays this config at runtime.
    */

    'enabled' => env('ASSISTANT_ENABLED', false),

    'api_key' => env('ANTHROPIC_API_KEY'),

    'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),

    'max_tokens' => (int) env('ASSISTANT_MAX_TOKENS', 1200),

    'greeting' => env('ASSISTANT_GREETING', "Hi! I'm the support assistant. Ask me anything about our cloud phones — plans, payment, or how to set your device up."),

    /** Messages one visitor may send per hour. Each one costs you money at Anthropic. */
    'rate_limit_per_hour' => (int) env('ASSISTANT_RATE_LIMIT', 25),

    /** How many past turns to replay to the model. Older turns are dropped. */
    'history_limit' => (int) env('ASSISTANT_HISTORY_LIMIT', 20),

    /** Extra business knowledge written by the site owner in the admin panel. */
    'knowledge' => null,
];
