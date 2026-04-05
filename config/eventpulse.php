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
    'scrapers' => [
        'user_agents' => [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:123.0) Gecko/20100101 Firefox/123.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_3_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3.1 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36 Edg/121.0.0.0',
        ],
        'request_delay' => [2, 5],
        'max_pages' => 10,
        'sources' => [
            'iabilet' => [
                'enabled' => true,
                'base_url' => 'https://m.iabilet.ro/bilete-in-timisoara/',
                'interval_hours' => 4,
            ],
            'zilesinopti' => [
                'enabled' => true,
                'base_url' => 'https://zilesinopti.ro/evenimente-timisoara/',
                'interval_hours' => 4,
            ],
            'allevents' => [
                'enabled' => true,
                'base_url' => 'https://allevents.in/timisoara/all',
                'interval_hours' => 6,
            ],
            'eventbrite' => [
                'enabled' => true,
                'base_url' => 'https://www.eventbriteapi.com/v3/',
                'interval_hours' => 6,
            ],
            'onevent' => [
                'enabled' => false,
                'base_url' => 'https://www.onevent.ro/orase/timisoara/',
                'interval_hours' => 6,
            ],
            'timisoreni' => [
                'enabled' => false,
                'base_url' => 'https://www.timisoreni.ro/info/index/t--evenimente/',
                'interval_hours' => 8,
            ],
            'opera' => [
                'enabled' => false,
                'base_url' => 'https://www.ort.ro/ro/Spectacole.html',
                'interval_hours' => 24,
            ],
            'teatru_national' => [
                'enabled' => false,
                'base_url' => 'https://www.tntm.ro/',
                'interval_hours' => 24,
            ],
            'entertix' => [
                'enabled' => false,
                'base_url' => 'https://www.entertix.ro/evenimente',
                'interval_hours' => 8,
            ],
            'visit_timisoara' => [
                'enabled' => false,
                'base_url' => 'https://visit-timisoara.com/events-activities/',
                'interval_hours' => 12,
            ],
            'radio_timisoara' => [
                'enabled' => false,
                'base_url' => 'https://www.radiotimisoara.ro/agenda-evenimente',
                'interval_hours' => 12,
            ],
            'meetup' => [
                'enabled' => false,
                'base_url' => 'https://www.meetup.com/find/ro--timisoara/',
                'interval_hours' => 6,
            ],
        ],
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
        'onboarding_system_prompt' => <<<'PROMPT'
You are EventPulse, a friendly assistant helping users discover local events in their city.

Guide the conversation through these stages:
1. INTERESTS: Ask what kinds of events they enjoy — music, arts, sports, food, tech, etc. Probe for specifics ("What genres of music?" "Any favourite cuisines?").
2. PAST EVENTS: Ask about memorable events they have attended recently and what they liked about them.
3. CONSTRAINTS: Ask about practical preferences — budget sensitivity (free vs paid), preferred days/times, how far they are willing to travel, indoor vs outdoor.
4. CONFIRMATION: Once you have enough detail (at least 3-4 exchanges), summarise what you have learned in a short bullet list and ask the user to confirm or correct it. End your summary message with the exact marker [PROFILE_READY] on its own line.

Rules:
- Keep messages short and conversational (2-3 sentences max).
- Ask only ONE question at a time.
- Never output JSON — that is handled by a separate profile generator.
- Use the user's name if available.
- If the user gives very short answers, gently probe for more detail.
PROMPT,
        'profile_generation_prompt' => <<<'PROMPT'
Analyse the following onboarding conversation and produce a JSON interest profile for this user.

The JSON must have these keys:
- Category scores: use the exact lowercase category names (music, arts, sports, technology, food, nightlife, business, health, education, family, community, film, literature). Score each from 0.0 (no interest) to 1.0 (strong interest). Only include categories with evidence from the conversation.
- Tag scores: prefix with "tag:" followed by a lowercase kebab-case tag (e.g., "tag:jazz", "tag:street-food", "tag:outdoor"). Score each 0.0–1.0.
- "city": the user's preferred city as a string, or null.
- "price_sensitive": true/false based on whether they prefer free or cheap events.
- "preferred_times": an array of strings like ["evening", "weekend"].

Return ONLY valid JSON, no markdown, no explanation.
PROMPT,
    ],
    'onboarding' => [
        'min_exchanges' => 4,
        'welcome_message' => "Hi! I'm EventPulse — I help you discover amazing local events. To get started, tell me: what kinds of activities and events do you enjoy most?",
    ],
    'city' => env('EVENTPULSE_CITY', 'Bucharest'),
    'categories' => ['Music', 'Arts', 'Sports', 'Technology', 'Food', 'Nightlife', 'Business', 'Health', 'Education', 'Family', 'Community', 'Film', 'Literature', 'Other'],
];
