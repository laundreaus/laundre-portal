<?php
// AI search config. Add AI_API_KEY to .env to switch it on. Provider defaults to Anthropic.
return [
    'key'      => env('AI_API_KEY'),
    'provider' => env('AI_PROVIDER', 'anthropic'),  // anthropic | openai
    'model'    => env('AI_MODEL', 'claude-3-5-haiku-latest'),
];
