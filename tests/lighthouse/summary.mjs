// The median run of every page and preset as one Markdown table (job summary, PR description).
import { existsSync, readFileSync } from 'node:fs';

const presets = ['mobile', 'desktop'];
const rows = [];

for (const preset of presets) {
    const manifestPath = `lighthouse-results/${preset}/manifest.json`;
    if (!existsSync(manifestPath)) continue;

    for (const run of JSON.parse(readFileSync(manifestPath, 'utf8')).filter((entry) => entry.isRepresentativeRun)) {
        const report = JSON.parse(readFileSync(run.jsonPath, 'utf8'));
        const score = (category) => Math.round(report.categories[category].score * 100);
        const audit = (id) => report.audits[id].numericValue;

        rows.push([
            new URL(run.url).pathname,
            preset,
            score('performance'),
            score('accessibility'),
            score('seo'),
            `${(audit('largest-contentful-paint') / 1000).toFixed(2)} s`,
            audit('cumulative-layout-shift').toFixed(3),
            `${Math.round(audit('total-blocking-time'))} ms`,
        ]);
    }
}

const header = ['Page', 'Preset', 'Performance', 'Accessibility', 'SEO', 'LCP', 'CLS', 'TBT'];
console.log(`| ${header.join(' | ')} |`);
console.log(`| ${header.map(() => '---').join(' | ')} |`);
for (const row of rows) console.log(`| ${row.join(' | ')} |`);
