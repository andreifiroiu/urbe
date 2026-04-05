# EventPulse — Timișoara Event Sources Research

## Source Inventory

I've identified **18 actionable sources** for Timișoara events, grouped by type. Here's each source with its URL, what it covers, estimated event volume, and the recommended scraping approach.

---

### TIER 1 — High-Volume Aggregators (start here)

These cover the broadest range of events and should be your first scrapers. Together they'll capture 80%+ of what's happening in Timișoara.

#### 1. Zile și Nopți (zilesinopti.ro)
- **URL:** `https://zilesinopti.ro/evenimente-timisoara/`
- **Also:** `https://zilesinopti.ro/evenimente-timisoara-weekend/`
- **Coverage:** Full-spectrum — concerts, theater, festivals, parties, art exhibitions, workshops, food events. The most comprehensive Romanian event aggregator with good Timișoara coverage.
- **Volume:** ~50–100 events/week
- **Scraping approach:** HTML scraping. The site renders server-side. Events are listed with title, venue, date, time, and category tags. Paginated listing. Use CSS selectors on the event cards.
- **Data quality:** High — structured categories (Teatru, Concert, Petrecere, Expoziție, etc.), venue names, dates with times.
- **Priority:** ★★★ Must-have for MVP

#### 2. iaBilet.ro
- **URL:** `https://www.iabilet.ro/bilete-in-timisoara/`
- **Mobile:** `https://m.iabilet.ro/bilete-in-timisoara/`
- **Coverage:** Ticketed events — concerts, stand-up comedy, theater, festivals, conferences, parties, folk, classical music, sport events. Romania's largest ticketing platform.
- **Volume:** ~30–60 active events for Timișoara at any time
- **Scraping approach:** HTML scraping. Events are well-structured with category labels (e.g., "Stand-up Timisoara:", "Rock Timisoara:"), venue, date, and price. The mobile site (`m.iabilet.ro`) has a cleaner HTML structure and may be easier to parse. Paginated with `?page=N`.
- **Data quality:** Excellent — includes category, venue, date, price range, and "selling fast" indicators.
- **Bonus:** Price data is embedded in the listing (e.g., "de la 9545 lei" = from 95.45 RON, prices are in "lei vechi" format ÷ 100).
- **Priority:** ★★★ Must-have for MVP

#### 3. AllEvents.in
- **URL:** `https://allevents.in/timisoara`
- **Also:** `https://allevents.in/timisoara/all`
- **Coverage:** Mixed — concerts, meetups, parties, art shows, conferences, festivals, community events. International aggregator that pulls from Facebook Events and user submissions.
- **Volume:** ~30–50 events/week
- **Scraping approach:** HTML scraping or consider their unofficial API. Events render server-side with structured data (title, date, time, venue, address). Includes lat/lng in some listings. May have JSON-LD structured data in page source.
- **Data quality:** Good — includes venue addresses (useful for geocoding), categories, and sometimes ticket links.
- **Priority:** ★★★ Must-have for MVP

#### 4. Timisoreni.ro
- **URL:** `https://www.timisoreni.ro/info/index/t--evenimente/`
- **Also:** `https://www.timisoreni.ro/info/spectacole/` (theater/opera/dance)
- **Coverage:** Comprehensive local events — concerts, theater, opera, sports, expos, book launches, parties, community events. The OG Timișoara community site with 8,400+ event entries.
- **Volume:** ~20–40 events/week (some may be duplicates from venues)
- **Scraping approach:** HTML scraping. Server-rendered. Events are in a paginated list with sorting options. Each event has title, description snippet, date, venue, and sometimes photos.
- **Data quality:** Medium — descriptions can be long-form Romanian text, good for LLM classification. Categories are less structured than iaBilet but richer descriptions.
- **Priority:** ★★☆ Important secondary source

---

### TIER 2 — Curated Local Sources

These provide unique events not always found on the big aggregators — especially cultural, institutional, and community events.

#### 5. Visit Timișoara
- **URL:** `https://visit-timisoara.com/events-activities/`
- **Coverage:** Cultural events, festivals, exhibitions, guided tours, seasonal activities. The official tourism/cultural portal. Curated and high-quality.
- **Volume:** ~10–20 events/week
- **Scraping approach:** The site appears to lazy-load events (JavaScript rendered — "Searching for more events.."). May need Browsershot/headless browser, or check for an underlying API (XHR requests in browser dev tools). Possibly a WordPress/REST backend.
- **Data quality:** High — English + Romanian, includes venue, time, and editorial descriptions.
- **Bonus:** They have a weekly newsletter with 4,000+ subscribers — validates there's demand for this kind of curation.
- **Priority:** ★★☆ Good cultural coverage

#### 6. OnEvent.ro
- **URL:** `https://www.onevent.ro/orase/timisoara/`
- **Coverage:** Concerts, theater, stand-up, conferences, business events, sport, workshops, wellness, family events. Good Romanian aggregator with category filters.
- **Volume:** ~20–40 events/week
- **Scraping approach:** HTML scraping. Events are structured with date ranges, venue, category tags (Atelier, Dans, Familie & Copii, Concert, Business, Conferințe, Networking, etc.). Server-rendered.
- **Data quality:** Good — event types are well-tagged, includes start/end times.
- **Priority:** ★★☆ Good variety

#### 7. Radio România Timișoara — Agendă Evenimente
- **URL:** `https://www.radiotimisoara.ro/agenda-evenimente`
- **Coverage:** Cultural events — opera, ballet, theater, exhibitions, film festivals, lectures, art events. Editorially curated by journalists.
- **Data quality:** Excellent editorial descriptions but less structured for scraping. Events are blog-post-style with embedded dates and venues.
- **Volume:** ~10–15 events/week
- **Scraping approach:** HTML scraping. Blog-style layout. Each event is an article with tags. LLM extraction would work well here to pull structured data from the editorial content.
- **Priority:** ★★☆ Unique cultural events not on ticketing sites

#### 8. Centrul de Proiecte Timișoara
- **URL:** `https://centruldeproiecte.ro/calendar/`
- **Coverage:** Publicly funded cultural projects — exhibitions, performances, community events, art installations. Events happening in municipal cultural spaces.
- **Volume:** ~5–15 events/week
- **Scraping approach:** HTML scraping. Calendar format. Updated regularly (last update noted March 26, 2026).
- **Data quality:** Good — municipal source, reliable dates and venues.
- **Priority:** ★☆☆ Niche but unique

---

### TIER 3 — Ticketing Platforms (national, filter to Timișoara)

These have structured data and cover paid/ticketed events.

#### 9. Entertix.ro
- **URL:** `https://www.entertix.ro/evenimente` (filter by city)
- **Coverage:** Festivals, concerts, cultural events, sport. Strong on Filarmonica Banatul and classical/symphonic events in Timișoara.
- **Volume:** ~10–20 Timișoara events at any time
- **Scraping approach:** HTML scraping. Events page with city filtering. Well-structured event cards.
- **Data quality:** Good — includes venue, date, price.
- **Priority:** ★★☆ Especially good for classical/philharmonic events

#### 10. Eventim.ro
- **URL:** `https://www.eventim.ro/ro/venues/timisoara/city.html`
- **Coverage:** Major ticketed events — concerts, theater, sports. International ticketing platform (Eventim network).
- **Volume:** ~10–15 events
- **Scraping approach:** HTML scraping. The site has a city page. May require cookie handling.
- **Data quality:** Good — structured ticketing data.
- **Priority:** ★☆☆ Overlap with iaBilet

#### 11. TicketStore.ro
- **URL:** `https://ticketstore.ro/ro/oras/Timisoara`
- **Coverage:** Theater, concerts, festivals, opera. Romanian ticketing platform.
- **Volume:** ~5–15 events
- **Scraping approach:** HTML scraping. City-filtered listing.
- **Priority:** ★☆☆ Overlap with iaBilet

#### 12. Bilete.ro
- **URL:** `https://www.bilete.ro/` (filter Timișoara)
- **Coverage:** Concerts, theater, festivals, sport, live shows. Another Romanian ticketing site.
- **Volume:** ~5–15 events
- **Scraping approach:** HTML scraping.
- **Priority:** ★☆☆ Overlap

#### 13. Eventbrite
- **URL:** `https://www.eventbrite.com/d/romania/timisoara/`
- **Coverage:** Business events, workshops, conferences, networking, some music events. Stronger on tech/business/professional events.
- **Volume:** ~5–15 events (smaller presence in Romania vs. Western Europe)
- **Scraping approach:** Eventbrite has a public API (https://www.eventbriteapi.com/v3/) — use the API for structured JSON data. Filter by `location.address=Timisoara` and `location.within=25km`. Rate limited but reliable.
- **Data quality:** Excellent — structured JSON with title, description, date, venue, category, price, images.
- **Priority:** ★★☆ Best for tech/business events, and has an actual API

---

### TIER 4 — Venue-Specific Sources

Individual venue websites for events not always listed on aggregators.

#### 14. Opera Națională Română Timișoara
- **URL:** `https://www.ort.ro/ro/Spectacole.html`
- **Coverage:** Opera, ballet, classical concerts at the Opera House.
- **Volume:** ~8–12 performances/month
- **Scraping approach:** HTML scraping. Program page with performance schedule.
- **Priority:** ★★☆ Canonical source for opera/ballet

#### 15. Teatrul Național Mihai Eminescu Timișoara
- **URL:** `https://www.tntm.ro/` (program section)
- **Coverage:** Theater performances at the National Theater.
- **Volume:** ~10–15 performances/month
- **Scraping approach:** HTML scraping. Monthly program page.
- **Priority:** ★★☆ Canonical source for theater

#### 16. Filarmonica Banatul Timișoara
- **URL:** Website TBD (search for current URL — often linked from iaBilet and Entertix)
- **Coverage:** Symphonic concerts, chamber music, recitals.
- **Volume:** ~4–8 concerts/month
- **Scraping approach:** HTML scraping.
- **Priority:** ★☆☆ Smaller volume but unique

---

### TIER 5 — Social & Community Sources

#### 17. Meetup.com
- **URL:** `https://www.meetup.com/find/ro--timisoara/`
- **Coverage:** Tech meetups, language exchanges, hiking groups, photography clubs, social gatherings, professional networking.
- **Volume:** ~5–15 events/week
- **Scraping approach:** Meetup has a GraphQL API. Use their API to query events by location (Timișoara). Authentication required (OAuth). Alternatively, scrape the public listing pages.
- **Data quality:** Excellent — structured data with RSVPs, group info, description, location.
- **Priority:** ★★☆ Best source for community/tech/hobby meetups

#### 18. Facebook Events
- **Coverage:** The single largest source of events in any city. Everything from house parties to major concerts. Many Timișoara events are ONLY posted on Facebook.
- **Volume:** ~50–100+ events/week
- **Scraping approach:** **Difficult.** Facebook aggressively blocks scraping. Options:
  - Use the **Apify Facebook Events Scraper** (paid, ~$10/1000 events)
  - Monitor specific Facebook pages/groups: "Evenimente Timisoara" (~12K followers), "Timisoara Events" group
  - Use the **Google Events API** (via SerpApi) which indexes many Facebook events
  - Accept that some Facebook-only events will be missed in MVP
- **Priority:** ★☆☆ for MVP (too complex), ★★★ for Phase 2

---

### BONUS — Meta-Sources (aggregate from multiple platforms)

#### Google Events (via SerpApi)
- **URL:** `https://serpapi.com/google-events-api`
- **Query:** `Events in Timisoara`
- **Coverage:** Google aggregates events from Eventbrite, Facebook, Meetup, ticketing sites, and venue websites into a unified index.
- **Cost:** SerpApi starts at $50/month for 5,000 searches.
- **Data quality:** Good — structured JSON with title, date, venue, address, ticket links, thumbnails.
- **Priority:** ★★☆ Great "catch-all" to find events missed by individual scrapers

#### dev.events
- **URL:** `https://dev.events/meetups/EU/RO/Timisoara/it`
- **Coverage:** IT/developer meetups and conferences specifically.
- **Priority:** ★☆☆ Niche tech source

---

## Recommended MVP Scraper Build Order

Start with 4 scrapers that together give you broad coverage with manageable complexity:

| Order | Source | Why | Scraper Type | Est. Effort |
|-------|--------|-----|--------------|-------------|
| 1 | **iaBilet.ro** | Best structured data, prices, categories. Covers 30-60 events. | HTML (CSS selectors) | 1 day |
| 2 | **Zile și Nopți** | Broadest local coverage, good categories | HTML (CSS selectors) | 1 day |
| 3 | **AllEvents.in** | International aggregator, catches Facebook events indirectly | HTML + JSON-LD | 1 day |
| 4 | **Eventbrite API** | Structured API, good for tech/business events | REST API (JSON) | 0.5 days |

**Phase 2 additions** (weeks 2–4):
| Order | Source | Why |
|-------|--------|-----|
| 5 | OnEvent.ro | Good category tagging, fills gaps |
| 6 | Timisoreni.ro | Deep local archive, community events |
| 7 | Opera + Teatru Național | Canonical venue sources for performing arts |
| 8 | Meetup API | Community/tech meetups |
| 9 | Visit Timișoara | Curated cultural events (needs headless browser) |

**Phase 3 additions:**
| Order | Source | Why |
|-------|--------|-----|
| 10 | Google Events API (SerpApi) | Catch-all for missed events |
| 11 | Facebook Events (via Apify) | Largest single source, hardest to scrape |
| 12 | Entertix, Eventim, TicketStore | Fill remaining ticketing gaps |
| 13 | Radio Timișoara agenda | Unique editorial/cultural events |

---

## Scraping Strategy Notes

### Deduplication Will Be Critical
The same event (e.g., "Concert Phoenix - Tamara" at Filarmonica Banatul) will appear on iaBilet, Zile și Nopți, AllEvents, OnEvent, Timisoreni, and Eventbrite simultaneously. The fingerprinting/dedup system needs to handle:
- Slight title variations ("Concert Phoenix" vs "Phoenix - Tamara - Timisoara")
- Different date formats
- Venue name variations ("Filarmonica Banatul Timisoara - Sala Capitol" vs "Sala Capitol")
- Romanian diacritics vs. ASCII (Timișoara vs Timisoara)

Recommendation: normalize text (lowercase, strip diacritics, remove common words like "timisoara", "concert", "bilete"), then hash(normalized_title + date + normalized_venue).

### Language Handling
All Romanian sources. LLM classification prompts should be bilingual (Romanian input, English-labeled categories). Claude handles Romanian well.

### Price Parsing
iaBilet uses "lei vechi" format: "de la 9545 lei" = 95.45 RON. Divide by 100. Other sites may show RON directly. Normalize to RON in the pipeline.

### Legal Considerations
- **iaBilet, Entertix, Eventim, Bilete.ro**: Commercial ticketing sites. Scrape public listing data only (title, date, venue, price). Link back to source for ticket purchases. Don't scrape full descriptions if they contain substantial original content.
- **Municipal sources** (Centrul de Proiecte, Visit Timișoara): Public information, likely fine.
- **Eventbrite**: Has a public API — use it. Their ToS allows API access.
- **AllEvents.in**: Public listing. Respect robots.txt.
- **Facebook**: Most restrictive. Use third-party tools (Apify, SerpApi) rather than direct scraping.

### Rate Limiting
- Scrape each source every 4–6 hours
- Respect robots.txt crawl-delay if specified
- Use random delays between requests (2–5 seconds)
- Rotate User-Agent strings

---

## Expected Event Volume for Timișoara

Based on the sources above, a realistic estimate:

| Metric | Estimate |
|--------|----------|
| Unique events per week | 80–150 |
| Unique events per month | 300–500 |
| After dedup (cross-source) | 60–100 per week |
| Categories breakdown | Music: 25%, Theater/Opera: 15%, Parties/Nightlife: 15%, Art/Culture: 10%, Community: 10%, Business/Tech: 8%, Sports: 7%, Family: 5%, Food: 3%, Other: 2% |

This is a healthy volume for a recommendation engine — enough to curate personalized daily digests of 5–8 events without running dry.
