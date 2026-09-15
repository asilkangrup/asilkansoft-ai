<?php

return [
    'enabled' => env('MATBAA_AI_ENABLED', false),
    'api_key' => env('OPENAI_MATBAA_API_KEY'),
    'model' => env('OPENAI_MATBAA_MODEL', 'gpt-5.6'),
    'image_model' => env('OPENAI_MATBAA_IMAGE_MODEL', 'gpt-image-2.5-sunburst'),
    'image_quality' => env('MATBAA_AI_IMAGE_QUALITY', 'medium'),
    'creative_backgrounds' => env('MATBAA_AI_CREATIVE_BACKGROUNDS', true),
    'memory_messages' => (int) env('MATBAA_AI_MEMORY_MESSAGES', 50),
    'debounce_seconds' => (int) env('MATBAA_AI_DEBOUNCE_SECONDS', 10),
    'max_questions_per_turn' => (int) env('MATBAA_AI_MAX_QUESTIONS', 2),
    'temperature' => (float) env('MATBAA_AI_TEMPERATURE', 0.35),
    'handoff_when_ready' => env('MATBAA_AI_HANDOFF_WHEN_READY', true),
];
