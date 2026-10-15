// Runs the protocol's conformance kit against the app served by `vendor/bin/testbench serve`.
//
//   node tests/conformance/run.mjs <bundle of @panneau/protocol/conformance> [baseUrl]
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';

const [bundle, baseUrl = 'http://127.0.0.1:8000/panneau/api'] = process.argv.slice(2);
if (bundle === undefined) {
    console.error('Usage: node tests/conformance/run.mjs <conformance bundle> [baseUrl]');
    process.exit(2);
}

const { runConformance, formatReport } = await import(pathToFileURL(resolve(bundle)).href);

const report = await runConformance({
    baseUrl,
    init: { headers: { Authorization: 'Bearer conformance' } },
    resource: 'posts',
    writes: {
        create: { title: 'Conformance', subtitle: { fr: 'Conformité', en: 'Conformance' }, status: 'draft' },
        update: { title: 'Conformance 2' },
    },
});

console.log(formatReport(report));

// Every check applies to the fixture app: a skipped check means something is missing.
const skipped = report.checks.filter((check) => check.status === 'skipped');
if (skipped.length > 0) {
    console.error(`\n${skipped.length} check(s) were skipped.`);
}
process.exit(report.passed && skipped.length === 0 ? 0 : 1);
