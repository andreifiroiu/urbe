# CLAUDE.md — EventPulse

## Project Overview

EventPulse is a personalized local event discovery platform. It scrapes events from multiple sources, classifies them with AI, and delivers curated notifications to users based on their interest profiles. Users onboard via a chat interface and refine recommendations through feedback. A discovery engine surfaces novel events to expand user horizons.

## Tech Stack

- **Backend:** Laravel 12, PHP 8.3
- **Frontend:** React 18 + Inertia.js + shadcn/ui + Tailwind CSS v4
- **Database:** PostgreSQL 16
- **Cache / Queue broker:** Redis 7
- **Queue worker:** Laravel Horizon
- **Search:** Meilisearch (event full-text search via Laravel Scout)
- **AI/LLM:** Anthropic Claude API (event classification, onboarding chat, profile generation)
- **Email:** Laravel Mail + MJML-compiled HTML templates
- **Scraping:** Custom PHP scraper adapters + Browsershot for JS-rendered pages
- **Geocoding:** Nominatim (OSM) or Google Geocoding API
- **Testing:** Pest PHP
- **Static analysis:** PHPStan level 6, Laravel Pint for formatting
- **Deployment:** Laravel Forge on Ubuntu VPS

## Project Structure

```
eventpulse/
├── app/
│   ├── Console/Commands/           # Artisan commands (scrape, classify, notify, decay)
│   ├── Contracts/                  # Interfaces
│   │   └── ScraperAdapter.php
│   ├── DTOs/                       # Data Transfer Objects
│   │   └── RawEvent.php
│   ├── Enums/                      # PHP 8.3 enums
│   │   ├── EventCategory.php
│   │   ├── Reaction.php
│   │   ├── NotificationChannel.php
│   │   └── NotificationFrequency.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   ├── ChatController.php
│   │   │   ├── EventController.php
│   │   │   ├── RecommendationController.php
│   │   │   ├── FeedbackController.php
│   │   │   ├── ProfileController.php
│   │   │   ├── NotificationSettingsController.php
│   │   │   └── Admin/ScraperController.php
│   │   ├── Requests/               # Form requests with validation
│   │   └── Resources/              # API resources / Inertia props
│   ├── Jobs/
│   │   ├── RunScraperJob.php
│   │   ├── ProcessRawEventJob.php
│   │   ├── ClassifyEventJob.php
│   │   ├── GeocodeEventJob.php
│   │   ├── ComposeNotificationJob.php
│   │   ├── SendNotificationJob.php
│   │   ├── ProcessFeedbackJob.php
│   │   ├── DecayProfileScoresJob.php
│   │   └── CleanupExpiredEventsJob.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Event.php
│   │   ├── UserEventReaction.php
│   │   ├── Notification.php
│   │   ├── ChatMessage.php
│   │   ├── ScraperRun.php
│   │   └── DiscoveryLog.php
│   ├── Services/
│   │   ├── Scraping/
│   │   │   ├── ScraperOrchestrator.php
│   │   │   └── Adapters/
│   │   │       ├── EventbriteScraper.php
│   │   │       ├── MeetupScraper.php
│   │   │       ├── GenericHtmlScraper.php
│   │   │       └── RssFeedScraper.php
│   │   ├── Processing/
│   │   │   ├── EventDeduplicator.php
│   │   │   ├── EventClassifier.php
│   │   │   ├── EventEnricher.php
│   │   │   └── EventPipeline.php
│   │   ├── Recommendation/
│   │   │   ├── RecommendationEngine.php
│   │   │   ├── DiscoveryEngine.php
│   │   │   ├── DiversityFilter.php
│   │   │   └── FeedbackProcessor.php
│   │   ├── Chat/
│   │   │   ├── OnboardingAgent.php
│   │   │   ├── ProfileUpdateAgent.php
│   │   │   └── ProfileGenerator.php
│   │   ├── Notification/
│   │   │   ├── NotificationComposer.php
│   │   │   ├── EmailRenderer.php
│   │   │   └── NotificationDispatcher.php
│   │   └── InterestProfile/
│   │       ├── ProfileScorer.php
│   │       ├── ProfileUpdater.php
│   │       └── ProfileDecayer.php
│   └── Providers/
├── config/
│   └── eventpulse.php              # App-specific config (weights, thresholds, scraper schedules)
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── js/
│   │   ├── Pages/
│   │   │   ├── Auth/
│   │   │   ├── Onboarding/
│   │   │   │   └── Chat.jsx         # Chat-based onboarding UI
│   │   │   ├── Dashboard/
│   │   │   │   ├── Index.jsx         # Main dashboard
│   │   │   │   ├── SavedEvents.jsx
│   │   │   │   └── Profile.jsx
│   │   │   ├── Events/
│   │   │   │   ├── Index.jsx         # Browse/search events
│   │   │   │   └── Show.jsx          # Event detail
│   │   │   └── Settings/
│   │   │       └── Notifications.jsx
│   │   ├── Components/
│   │   │   ├── Chat/
│   │   │   │   ├── ChatWindow.jsx
│   │   │   │   ├── ChatBubble.jsx
│   │   │   │   └── TypingIndicator.jsx
│   │   │   ├── Events/
│   │   │   │   ├── EventCard.jsx
│   │   │   │   ├── EventList.jsx
│   │   │   │   ├── ReactionButtons.jsx
│   │   │   │   └── CategoryBadge.jsx
│   │   │   └── ui/                   # shadcn/ui components
│   │   └── Layouts/
│   │       └── AppLayout.jsx
│   └── views/
│       └── emails/
│           └── digest.blade.php      # Email template (compiled from MJML)
├── routes/
│   ├── web.php
│   └── api.php
└── tests/
    ├── Feature/
    │   ├── Scraping/
    │   ├── Processing/
    │   ├── Recommendation/
    │   ├── Chat/
    │   └── Notification/
    └── Unit/
        ├── Services/
        └── Models/
```

## Coding Standards & Conventions

### PHP / Laravel

- **PHP 8.3 features**: Use enums, readonly properties, typed properties, named arguments, match expressions, first-class callables.
- **Strict types**: Every PHP file starts with `declare(strict_types=1);`
- **Return types**: Every method has an explicit return type.
- **Formatting**: Laravel Pint with default `laravel` preset. Run `./vendor/bin/pint` before commits.
- **Static analysis**: PHPStan level 6. Run `./vendor/bin/phpstan analyse` before commits.
- **DTOs over arrays**: Use typed DTOs (readonly classes) for data transfer between services. Never pass untyped arrays between service boundaries.
- **Service pattern**: Business logic lives in `App\Services\`, not in controllers or models. Controllers are thin (validate → delegate → respond).
- **Actions for single-purpose operations**: If a service method does one thing and is called from one place, consider an Action class instead.
- **Enums for constants**: Use PHP enums for all categorical constants (categories, reactions, notification channels, etc.). Never use string constants.
- **Config over hardcoding**: All tunable values (weights, thresholds, cadences, API keys) go in `config/eventpulse.php` and are read via `config('eventpulse.xxx')`.
- **Jobs for async work**: Any operation that takes > 200ms or calls an external API goes through the queue. Use specific queue names: `scraping`, `processing`, `ai`, `enrichment`, `notifications`, `default`.
- **Eloquent conventions**: Use UUIDs as primary keys. Use `$casts` for JSON columns, date columns, and enums. Define relationships explicitly.
- **Database**: PostgreSQL-specific features are welcome (JSONB operators, GIN indexes, `gen_random_uuid()`). All queries should use the query builder or Eloquent — no raw SQL except in migrations.
- **Error handling**: Wrap external API calls (Claude, geocoding, scrapers) in try/catch. Log failures and dispatch to a dead-letter queue for retry. Never let a scraper failure crash the pipeline.

### Frontend (React + Inertia)

- **Functional components only** with hooks.
- **shadcn/ui** for all base components. Never build custom buttons, inputs, modals, etc. from scratch.
- **Tailwind v4** utility classes. No custom CSS files unless absolutely necessary.
- **Inertia conventions**: Use `useForm()` for forms, `usePage()` for shared data, `router.visit()` for navigation.
- **TypeScript-like discipline**: Even though we use JSX, prop-type all components with JSDoc or default props.
- **File naming**: PascalCase for components (`EventCard.jsx`), camelCase for utilities (`formatDate.js`).
- **State management**: Local state via `useState`/`useReducer`. No global state library — Inertia's shared data and page props are the source of truth.

### Testing

- **Framework**: Pest PHP exclusively. No PHPUnit syntax.
- **Coverage targets**: 80%+ on services, 60%+ overall.
- **Naming**: `it('scores music events higher for users who like music')` — descriptive, behavior-focused.
- **Structure**: Mirror source directory in tests. `tests/Unit/Services/Recommendation/RecommendationEngineTest.php` for `App\Services\Recommendation\RecommendationEngine`.
- **Factories**: Use model factories for all test data. Never hardcode IDs.
- **External services**: Mock all external API calls (Claude, geocoding, scrapers). Use `Http::fake()` for HTTP calls, dedicated fakes for service classes.
- **Database**: Use `RefreshDatabase` trait. Tests run against a test PostgreSQL database.

### LLM Integration (Claude API)

- **Client**: Use the Anthropic PHP SDK or a simple HTTP wrapper service (`App\Services\Anthropic\ClaudeClient`).
- **Prompts**: Store all prompt templates in `config/eventpulse.php` under a `prompts` key, or in dedicated prompt classes.
- **Structured output**: Always instruct Claude to respond in JSON. Parse with `json_decode()` and validate against expected schema.
- **Rate limiting**: Queue all LLM calls on the `ai` queue. Use Laravel's rate limiter to stay within API limits.
- **Cost tracking**: Log token usage per classification/chat call. Store in a `llm_usage_log` table for monitoring.
- **Fallbacks**: If classification fails, store event with `category = "Other"` and flag for retry.

### Git Conventions

- **Branching**: `main` (production), `develop` (staging), `feature/*`, `fix/*`, `chore/*`.
- **Commits**: Conventional commits: `feat:`, `fix:`, `refactor:`, `test:`, `chore:`, `docs:`.
- **PRs**: Every feature branch gets a PR. CI must pass (Pint, PHPStan, Pest) before merge.

## Key Architecture Decisions

1. **PostgreSQL over MySQL**: JSONB support for flexible interest profiles, tags, and metadata. GIN indexes for tag queries. `gen_random_uuid()` for UUIDs.
1. **Separate queue names**: Prevents scraper backlog from blocking notification delivery. Horizon can allocate different worker counts per queue.
1. **LLM for classification over rule-based**: Event descriptions are too varied for regex/keyword matching. LLM classification handles edge cases and multi-language content gracefully.
1. **Chat-based onboarding over form-based**: Conversational UI captures nuance ("I like jazz but not smooth jazz") that checkboxes can't. The LLM extracts structured preferences from natural language.
1. **Email-first notifications**: Lowest friction for MVP. Push notifications come in Phase 2.
1. **Scraper adapter pattern**: Each source is a pluggable adapter implementing `ScraperAdapter`. Adding a new source means writing one class — no changes to the pipeline.
1. **Discovery as first-class feature**: The exploration budget is baked into `NotificationComposer` from day one, not bolted on later.

## Environment Variables

```
APP_NAME=EventPulse
APP_ENV=local
APP_URL=http://eventpulse.test

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=eventpulse
DB_USERNAME=eventpulse
DB_PASSWORD=

REDIS_HOST=127.0.0.1

QUEUE_CONNECTION=redis

ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-sonnet-4-20250514

MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=

GEOCODING_PROVIDER=nominatim  # or "google"
GOOGLE_GEOCODING_KEY=

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=events@eventpulse.app
MAIL_FROM_NAME="EventPulse"

EVENTPULSE_CITY=Bucharest
EVENTPULSE_SCRAPE_INTERVAL_HOURS=4
EVENTPULSE_NOTIFICATION_HOUR=8
```

## Common Commands

```bash
# Development
php artisan serve
npm run dev

# Queue workers
php artisan horizon

# Run scrapers manually
php artisan eventpulse:scrape                    # all sources
php artisan eventpulse:scrape --source=eventbrite # single source

# Process pending raw events
php artisan eventpulse:process-events

# Send notifications
php artisan eventpulse:send-notifications        # to all due users
php artisan eventpulse:send-notifications --user=UUID  # to one user

# Profile decay (normally scheduled, but can run manually)
php artisan eventpulse:decay-profiles

# Code quality
./vendor/bin/pint
./vendor/bin/phpstan analyse
./vendor/bin/pest
./vendor/bin/pest --coverage
```

## Implementation Priority

Build in this order:

1. **Database schema** — migrations for all tables
1. **Models + factories** — all Eloquent models with casts, relationships, factories
1. **Enums** — EventCategory, Reaction, NotificationChannel, NotificationFrequency
1. **Config** — `config/eventpulse.php` with all tunable values
1. **Scraper infrastructure** — ScraperAdapter interface, ScraperOrchestrator, one concrete adapter (GenericHtmlScraper)
1. **Event pipeline** — EventDeduplicator → EventClassifier → EventEnricher → EventPipeline
1. **Interest profile services** — ProfileScorer, ProfileUpdater, ProfileDecayer
1. **Recommendation engine** — RecommendationEngine, DiscoveryEngine, DiversityFilter
1. **Chat / onboarding** — OnboardingAgent, ProfileGenerator, ChatController
1. **Notifications** — NotificationComposer, EmailRenderer, NotificationDispatcher
1. **API + controllers** — all routes and controllers
1. **Frontend pages** — Onboarding chat → Dashboard → Event browse → Settings
1. **Artisan commands** — CLI wrappers for all scheduled operations
1. **Scheduled tasks** — `app/Console/Kernel.php` scheduling
1. **Tests** — unit tests for services, feature tests for API endpoints

## Things to Watch Out For

- **Scraper fragility**: HTML scrapers break when sites change. Log errors per source and alert on >3 consecutive failures. Build scrapers defensively with null-safe extraction.
- **LLM response validation**: Claude's JSON output can occasionally be malformed. Always validate parsed output against expected keys/types. Fall back gracefully.
- **Profile score boundaries**: Always clamp scores to [0.0, 1.0] after any update. Use `max(0.0, min(1.0, $score))` everywhere.
- **Email reaction tracking**: Reaction links in emails need signed tokens (Laravel's `URL::signedRoute()`) to prevent spoofing.
- **Event expiry**: Events in the past should be soft-excluded from recommendations but kept in DB for analytics. Add a scope: `Event::upcoming()`.
- **Rate limits**: Claude API, geocoding APIs, and scraped sites all have rate limits. Use Laravel's `RateLimiter` and queue throttling.
- **Timezone handling**: Store all times in UTC. Convert to user's local timezone only in presentation layer (email, dashboard).
