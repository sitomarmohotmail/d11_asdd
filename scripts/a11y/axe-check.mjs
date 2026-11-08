#!/usr/bin/env node
/**
 * Revisión automática de accesibilidad con axe-core (WCAG 2.2 AA).
 *
 * Uso:
 *   npm run a11y -- [--json salida.json] [--width 320] URL [URL…]
 *
 * Códigos de salida: 0 sin violaciones, 1 con violaciones, 2 error.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import puppeteer from 'puppeteer';
import { AxePuppeteer } from '@axe-core/puppeteer';

// Mensajes de axe en español.
const require = createRequire(import.meta.url);
const LOCALE = JSON.parse(readFileSync(require.resolve('axe-core/locales/es.json'), 'utf8'));

// Etiquetas de axe que componen WCAG 2.2 nivel A + AA.
const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

function parseArgs(argv) {
  const opts = { json: null, width: 1280, urls: [] };
  for (let i = 0; i < argv.length; i++) {
    if (argv[i] === '--json') opts.json = argv[++i];
    else if (argv[i] === '--width') opts.width = Number(argv[++i]);
    else opts.urls.push(argv[i]);
  }
  return opts;
}

const opts = parseArgs(process.argv.slice(2));
if (opts.urls.length === 0) {
  console.error('Uso: npm run a11y -- [--json salida.json] [--width 320] URL [URL…]');
  process.exit(2);
}

// --no-sandbox: Ubuntu restringe los user namespaces que usa el sandbox de
// Chrome. Solo se navega por el sitio local.
const browser = await puppeteer.launch({ args: ['--no-sandbox'] });
const report = [];
let total = 0;

try {
  for (const url of opts.urls) {
    const page = await browser.newPage();
    await page.setViewport({ width: opts.width, height: 900 });
    const response = await page.goto(url, { waitUntil: 'networkidle0' });
    const results = await new AxePuppeteer(page).configure({ locale: LOCALE }).withTags(TAGS).analyze();
    await page.close();

    const violations = results.violations.map((v) => ({
      id: v.id,
      impact: v.impact,
      wcag: v.tags.filter((t) => /^wcag\d{3,4}$/.test(t)),
      help: v.help,
      helpUrl: v.helpUrl,
      nodes: v.nodes.map((n) => ({ target: n.target, html: n.html, summary: n.failureSummary })),
    }));
    total += violations.length;
    report.push({
      url,
      status: response?.status() ?? null,
      width: opts.width,
      violations,
      incomplete: results.incomplete.map((v) => ({ id: v.id, help: v.help, nodes: v.nodes.length })),
      passes: results.passes.length,
    });

    console.log(`\n${url} (HTTP ${response?.status()}, ${opts.width}px): ${violations.length} violaciones, ${results.incomplete.length} a revisar a mano, ${results.passes.length} reglas superadas`);
    for (const v of violations) {
      console.log(`  - [${v.impact}] ${v.id} (${v.wcag.join(', ') || 'sin criterio'}): ${v.help}`);
      for (const n of v.nodes) console.log(`      ${n.target.join(' ')}`);
    }
  }
} catch (error) {
  console.error(`Error: ${error.message}`);
  await browser.close();
  process.exit(2);
}

await browser.close();
if (opts.json) writeFileSync(opts.json, JSON.stringify(report, null, 2));
process.exit(total > 0 ? 1 : 0);
