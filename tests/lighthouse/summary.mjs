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

// What holds each median run back: the LCP element, the audits that would save the most LCP time,
// and the elements that shift the layout. Enough to open a task without downloading the reports.
const diagnostics = [];

for (const preset of presets) {
    const manifestPath = `lighthouse-results/${preset}/manifest.json`;
    if (!existsSync(manifestPath)) continue;

    for (const run of JSON.parse(readFileSync(manifestPath, 'utf8')).filter((entry) => entry.isRepresentativeRun)) {
        const report = JSON.parse(readFileSync(run.jsonPath, 'utf8'));
        const nodes = (audit) => {
            const found = [];
            const walk = (value) => {
                if (!value || typeof value !== 'object') return;
                if (value.type === 'node' && value.selector) found.push(value.snippet ?? value.selector);
                Object.values(value).forEach(walk);
            };
            walk(report.audits[audit]?.details);
            return found;
        };
        const savings = Object.values(report.audits)
            .filter((audit) => (audit.metricSavings?.LCP ?? 0) >= 100)
            .sort((a, b) => b.metricSavings.LCP - a.metricSavings.LCP)
            .slice(0, 4)
            .map((audit) => `${audit.title} (−${Math.round(audit.metricSavings.LCP)} ms)`);
        const shifts = report.audits['cumulative-layout-shift'].numericValue >= 0.01 ? nodes('layout-shifts').slice(0, 3) : [];

        diagnostics.push(`**${new URL(run.url).pathname} · ${preset}**`);
        diagnostics.push(`- LCP element: \`${(nodes('largest-contentful-paint-element')[0] ?? 'n/a').slice(0, 160)}\``);
        if (savings.length) diagnostics.push(`- LCP savings: ${savings.join('; ')}`);
        if (shifts.length) diagnostics.push(`- Layout shifts: ${shifts.map((node) => `\`${node.slice(0, 120)}\``).join(', ')}`);
        diagnostics.push('');
    }
}

console.log('\n### Diagnostics\n');
console.log(diagnostics.join('\n'));
