// Performance budget in CI (MA-267): Lighthouse on the key pages, phone and desktop.
// tests/lighthouse/run.sh starts the shop and runs this config once per preset (LIGHTHOUSE_PRESET;
// not LHCI_*, which lhci reads as its own options).
const port = process.env.LIGHTHOUSE_PORT ?? '8124';
const base = `http://127.0.0.1:${port}`;
const preset = process.env.LIGHTHOUSE_PRESET === 'desktop' ? 'desktop' : 'mobile';

// The median of the runs decides, so one slow run on a busy runner does not fail the job.
const median = (level, options) => [level, { ...options, aggregationMethod: 'median-run' }];

// The targets from MA-267.
const target = {
    'categories:performance': median('error', { minScore: 0.9 }),
    'categories:accessibility': median('error', { minScore: 0.95 }),
    'categories:seo': median('error', { minScore: 1 }),
    'largest-contentful-paint': median('error', { maxNumericValue: 2500 }),
    'cumulative-layout-shift': median('error', { maxNumericValue: 0.05 }),
    'total-blocking-time': median('error', { maxNumericValue: 200 }),
};

// Where the shop misses a target today, the budget holds the level measured on 29.10 so it cannot get worse.
// Each one has a task to reach the target; tighten the number back to `target` when it is done.
const measured = preset === 'mobile'
    ? [
        // Phone after the fonts in MA-271 (31.10, measured locally): LCP 2.3–2.9 s, Performance 89–93; CI runs about 0.3 s slower,
        // with up to ±0.5 s between runs. The margin keeps that noise from failing the job. Without any web font the pages
        // reach 1.9–2.2 s, so what is left of the gap is the fonts being fetched before the photo at all.
        {
            matchingUrlPattern: '.*',
            assertions: {
                ...target,
                'categories:performance': median('error', { minScore: 0.85 }),
                'largest-contentful-paint': median('error', { maxNumericValue: 3500 }),
            },
        },
    ]
    : [{ matchingUrlPattern: '.*', assertions: target }];

module.exports = {
    ci: {
        collect: {
            url: [
                `${base}/`,
                `${base}/sklep`,
                // A product from the seeders with four of Kasia's photos, so LCP is a real image.
                `${base}/produkt/kubki-z-cytatem`,
                `${base}/kubek-z-napisem`,
                `${base}/warsztaty-ceramiczne-krakow`,
            ],
            numberOfRuns: 3,
            settings: {
                ...(preset === 'desktop' ? { preset: 'desktop' } : {}),
                chromeFlags: '--headless=new --no-sandbox',
            },
        },
        assert: { assertMatrix: measured },
        upload: {
            target: 'filesystem',
            outputDir: `lighthouse-results/${preset}`,
        },
    },
};
