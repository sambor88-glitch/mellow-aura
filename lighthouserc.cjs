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

// Where the shop misses a target, the budget holds the level the shop reaches so it cannot get worse.
const measured = preset === 'mobile'
    ? [
        // Phone, decision of 31.10 (MA-271, option D): Performance holds the MA-267 target, LCP stays at 3 s. CI after the
        // fonts (#194): Performance 91–94, LCP 2.26–2.86 s. This is `artisan serve` on a runner, without Cloudflare, HTTP/2
        // or a warm cache, so 2.5 s waits for real data from Search Console after the launch (MA-119). The remaining gap is
        // the web fonts fetched before the first photo; loading them later was turned down because the swap would show.
        {
            matchingUrlPattern: '.*',
            assertions: {
                ...target,
                'largest-contentful-paint': median('error', { maxNumericValue: 3000 }),
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
