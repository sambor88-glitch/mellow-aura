// Performance budget in CI (MA-267): Lighthouse on the key pages, phone and desktop.
// tests/lighthouse/run.sh starts the shop and runs this config once per preset (LIGHTHOUSE_PRESET;
// not LHCI_*, which lhci reads as its own options).
const port = process.env.LIGHTHOUSE_PORT ?? '8124';
const base = `http://127.0.0.1:${port}`;
const preset = process.env.LIGHTHOUSE_PRESET === 'desktop' ? 'desktop' : 'mobile';

// The median of the runs decides, so one slow run on a busy runner does not fail the job.
const median = (assertion) => [assertion[0], { ...assertion[1], aggregationMethod: 'median-run' }];

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
        assert: {
            assertions: {
                'categories:performance': median(['error', { minScore: 0.9 }]),
                'categories:accessibility': median(['error', { minScore: 0.95 }]),
                'categories:seo': median(['error', { minScore: 1 }]),
                'largest-contentful-paint': median(['error', { maxNumericValue: 2500 }]),
                'cumulative-layout-shift': median(['error', { maxNumericValue: 0.05 }]),
                'total-blocking-time': median(['error', { maxNumericValue: 200 }]),
            },
        },
        upload: {
            target: 'filesystem',
            outputDir: `lighthouse-results/${preset}`,
        },
    },
};
