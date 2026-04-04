<?php

declare(strict_types=1);

return [
    'recommendation' => [
        'weights' => [
            'category' => 0.30,
            'tags' => 0.20,
            'location' => 0.15,
            'time' => 0.10,
            'price' => 0.05,
            'freshness' => 0.10,
            'popularity' => 0.10,
        ],
    ],
    'feedback' => [
        'deltas' => [
            'interested' => 0.15,
            'not_interested' => -0.10,
            'saved' => 0.20,
            'hidden' => -0.15,
            'link_opened' => 0.05,
        ],
    ],
    'discovery' => [
        'default_openness' => 0.3,
        'exploration_budget' => 0.2,
        'min_surprise_score' => 0.3,
    ],
    'scraping' => [
        'interval_hours' => (int) env('EVENTPULSE_SCRAPE_INTERVAL_HOURS', 4),
        'max_consecutive_failures' => 3,
        'timeout_seconds' => 30,
    ],
    'notifications' => [
        'hour' => (int) env('EVENTPULSE_NOTIFICATION_HOUR', 8),
        'max_events_per_digest' => 10,
        'max_discovery_events' => 3,
    ],
    'profile' => [
        'decay_rate' => 0.05,
        'decay_interval_days' => 7,
        'min_score' => 0.0,
        'max_score' => 1.0,
    ],
    'llm' => [
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'max_tokens' => 1024,
        'classification_prompt' => 'You are an event classifier. Given the event title and description, classify it into exactly one category and extract relevant tags. Respond in JSON format with keys: "category" (one of: Music, Arts, Sports, Technology, Food, Nightlife, Business, Health, Education, Family, Community, Film, Literature, Other), "tags" (array of lowercase strings), "confidence" (float 0-1).',
        'onboarding_system_prompt' => 'You are EventPulse, a friendly assistant helping users discover local events. Ask about their interests, preferred event types, price sensitivity, and location preferences. Be conversational and extract structured preferences from natural language. After gathering enough information (3-5 exchanges), summarize their profile.',
    ],
    'city' => env('EVENTPULSE_CITY', 'Bucharest'),
    'categories' => ['Music', 'Arts', 'Sports', 'Technology', 'Food', 'Nightlife', 'Business', 'Health', 'Education', 'Family', 'Community', 'Film', 'Literature', 'Other'],
];
