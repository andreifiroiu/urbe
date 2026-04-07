#!/usr/bin/env node
/**
 * Facebook Event Scraper Bridge
 *
 * Called from PHP via Symfony\Component\Process\Process.
 * Accepts a JSON payload as the first CLI argument:
 *   { "pages": ["https://www.facebook.com/PageName/events/", ...] }
 *
 * Outputs a JSON array of event objects to stdout.
 * All errors go to stderr — stdout must remain valid JSON.
 *
 * Required npm package: facebook-event-scraper (optionalDependencies)
 * Install: npm install facebook-event-scraper
 */

'use strict';

const PAGE_TIMEOUT_MS = 30_000;

async function main() {
    let input;
    try {
        input = JSON.parse(process.argv[2] ?? '{}');
    } catch (err) {
        process.stderr.write(`[facebook-scraper] Failed to parse input JSON: ${err.message}\n`);
        console.log('[]');
        process.exit(0);
    }

    const pages = Array.isArray(input.pages) ? input.pages : [];

    if (pages.length === 0) {
        console.log('[]');
        process.exit(0);
    }

    let scraper;
    try {
        // Dynamically require so PHP can detect if the package is missing
        scraper = require('facebook-event-scraper');
    } catch (err) {
        process.stderr.write(`[facebook-scraper] facebook-event-scraper not installed: ${err.message}\n`);
        console.log('[]');
        process.exit(0);
    }

    const { scrapeFbEventFromUrl, scrapeFbEventList } = scraper;
    const results = [];

    for (const pageUrl of pages) {
        try {
            const pageEvents = await withTimeout(
                scrapeFbEventList(pageUrl, { type: 'upcoming' }),
                PAGE_TIMEOUT_MS,
                `scrapeFbEventList(${pageUrl})`,
            );

            // Enrich each listing event with full detail if possible
            for (const event of pageEvents) {
                try {
                    const eventUrl = event.url ?? (event.id ? `https://www.facebook.com/events/${event.id}` : null);
                    if (eventUrl) {
                        const details = await withTimeout(
                            scrapeFbEventFromUrl(eventUrl),
                            PAGE_TIMEOUT_MS,
                            `scrapeFbEventFromUrl(${eventUrl})`,
                        );
                        results.push({ ...event, ...details });
                    } else {
                        results.push(event);
                    }
                } catch (err) {
                    // Partial data is acceptable — push what we have
                    process.stderr.write(`[facebook-scraper] Detail fetch failed: ${err.message}\n`);
                    results.push(event);
                }
            }
        } catch (err) {
            // One page failing must not kill the entire run
            process.stderr.write(`[facebook-scraper] Failed to scrape ${pageUrl}: ${err.message}\n`);
        }
    }

    console.log(JSON.stringify(results));
}

/**
 * Wrap a promise with a timeout that rejects after `ms` milliseconds.
 *
 * @param {Promise<any>} promise
 * @param {number} ms
 * @param {string} label  Used in the rejection message.
 * @returns {Promise<any>}
 */
function withTimeout(promise, ms, label) {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => {
            reject(new Error(`Timeout after ${ms}ms: ${label}`));
        }, ms);

        promise
            .then((result) => {
                clearTimeout(timer);
                resolve(result);
            })
            .catch((err) => {
                clearTimeout(timer);
                reject(err);
            });
    });
}

main().catch((err) => {
    process.stderr.write(`[facebook-scraper] Fatal error: ${err.message}\n`);
    console.log('[]');
    process.exit(0);
});
