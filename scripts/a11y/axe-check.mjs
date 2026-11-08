#!/usr/bin/env node
/**
 * Revisión automática de accesibilidad con axe-core (WCAG 2.2 AA).
 *
 * Uso:
 *   npm run a11y -- [--json salida.json] [--width 320] [--focus] URL [URL…]
 *
 * Siempre comprueba también el reflujo (sin scroll horizontal, WCAG 1.4.10).
 * --focus recorre la página con Tab y mide el indicador de foco de cada
 * elemento (contorno o sombra, contraste ≥ 3:1 con el fondo; WCAG 2.4.7 y
 * 1.4.11).
 *
 * Códigos de salida: 0 sin problemas, 1 con problemas, 2 error.
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
  const opts = { json: null, width: 1280, focus: false, urls: [] };
  for (let i = 0; i < argv.length; i++) {
    if (argv[i] === '--json') opts.json = argv[++i];
    else if (argv[i] === '--focus') opts.focus = true;
    else if (argv[i] === '--width') opts.width = Number(argv[++i]);
    else opts.urls.push(argv[i]);
  }
  return opts;
}

// Chrome se abre con --no-sandbox, así que solo se permiten el sitio local y
// archivos locales (páginas de prueba), nunca webs externas.
const ALLOWED_HOSTS = new Set(['d11assd.local', 'localhost', '127.0.0.1', '[::1]']);

function isAllowed(url) {
  let parsed;
  try {
    parsed = new URL(url);
  }
  catch {
    return false;
  }
  if (parsed.protocol === 'file:') return true;
  return ['http:', 'https:'].includes(parsed.protocol) && ALLOWED_HOSTS.has(parsed.hostname);
}

// Colores y contraste (WCAG 2.x).
function parseColor(value) {
  const m = /rgba?\(([^)]+)\)/.exec(value || '');
  if (!m) return null;
  const [r, g, b, a = '1'] = m[1].split(/[ ,/]+/).filter(Boolean);
  return { r: +r, g: +g, b: +b, a: parseFloat(a) };
}
function blend(fg, bg) {
  return { r: fg.r * fg.a + bg.r * (1 - fg.a), g: fg.g * fg.a + bg.g * (1 - fg.a), b: fg.b * fg.a + bg.b * (1 - fg.a), a: 1 };
}
function luminance({ r, g, b }) {
  const lin = (c) => { c /= 255; return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; };
  return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
}
function contrast(a, b) {
  const [l1, l2] = [luminance(a), luminance(b)].sort((x, y) => y - x);
  return (l1 + 0.05) / (l2 + 0.05);
}

// Recorre la página con Tab y devuelve los elementos sin foco visible.
async function checkFocus(page) {
  const issues = [];
  const seen = new Set();
  // Empieza siempre desde el principio de la página (aunque haya autofocus).
  await page.evaluate(() => {
    const start = document.createElement('span');
    start.tabIndex = -1;
    document.body.prepend(start);
    start.focus();
    start.remove();
  });
  for (let i = 0; i < 200; i++) {
    await page.keyboard.press('Tab');
    await new Promise((r) => setTimeout(r, 250));
    const info = await page.evaluate(() => {
      const el = document.activeElement;
      if (!el || el === document.body) return null;
      const cs = getComputedStyle(el);
      let bg = null;
      for (let n = el.parentElement; n; n = n.parentElement) {
        const c = getComputedStyle(n).backgroundColor;
        if (c && !/rgba\(0, 0, 0, 0\)|transparent/.test(c)) { bg = c; break; }
      }
      const path = [];
      for (let n = el; n && n !== document.body; n = n.parentElement) {
        path.unshift(n.tagName.toLowerCase() + (n.id ? `#${n.id}` : '') + [...n.classList].slice(0, 2).map((c) => `.${c}`).join(''));
      }
      return {
        key: path.join('>') + '|' + (el.getAttribute('href') || el.name || el.textContent.trim().slice(0, 20)),
        label: `${path.slice(-2).join(' > ')} «${(el.textContent || el.value || el.getAttribute('aria-label') || '').trim().slice(0, 30)}»`,
        outlineWidth: parseFloat(cs.outlineWidth) || 0,
        outlineStyle: cs.outlineStyle,
        outlineColor: cs.outlineColor,
        boxShadow: cs.boxShadow,
        bg: bg || 'rgb(255, 255, 255)',
      };
    });
    if (!info || seen.has(info.key)) break;
    seen.add(info.key);
    const bg = parseColor(info.bg);
    let indicator = null;
    if (info.outlineStyle !== 'none' && info.outlineWidth >= 1) indicator = parseColor(info.outlineColor);
    else if (info.boxShadow && info.boxShadow !== 'none') indicator = parseColor(info.boxShadow);
    if (!indicator) {
      issues.push(`${info.label}: sin indicador de foco`);
      continue;
    }
    const ratio = contrast(blend(indicator, bg), bg);
    if (ratio < 3) issues.push(`${info.label}: indicador de foco ${ratio.toFixed(2)}:1 (< 3:1)`);
  }
  return { checked: seen.size, issues };
}

const opts = parseArgs(process.argv.slice(2));
if (opts.urls.length === 0) {
  console.error('Uso: npm run a11y -- [--json salida.json] [--width 320] [--focus] URL [URL…]');
  process.exit(2);
}
const rejected = opts.urls.filter((url) => !isAllowed(url));
if (rejected.length > 0) {
  console.error(`URL no permitidas (solo ${[...ALLOWED_HOSTS].join(', ')} o file://): ${rejected.join(' ')}`);
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
    const reflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    const focus = opts.focus ? await checkFocus(page) : null;
    await page.close();

    const violations = results.violations.map((v) => ({
      id: v.id,
      impact: v.impact,
      wcag: v.tags.filter((t) => /^wcag\d{3,4}$/.test(t)),
      help: v.help,
      helpUrl: v.helpUrl,
      nodes: v.nodes.map((n) => ({ target: n.target, html: n.html, summary: n.failureSummary })),
    }));
    total += violations.length + (reflow ? 1 : 0) + (focus ? focus.issues.length : 0);
    report.push({
      url,
      status: response?.status() ?? null,
      width: opts.width,
      violations,
      incomplete: results.incomplete.map((v) => ({ id: v.id, help: v.help, nodes: v.nodes.length })),
      passes: results.passes.length,
      reflow,
      focus,
    });

    console.log(`\n${url} (HTTP ${response?.status()}, ${opts.width}px): ${violations.length} violaciones, ${results.incomplete.length} a revisar a mano, ${results.passes.length} reglas superadas`);
    for (const v of violations) {
      console.log(`  - [${v.impact}] ${v.id} (${v.wcag.join(', ') || 'sin criterio'}): ${v.help}`);
      for (const n of v.nodes) console.log(`      ${n.target.join(' ')}`);
    }
    console.log(reflow ? '  - Reflujo: hay scroll horizontal (WCAG 1.4.10)' : '  Reflujo: sin scroll horizontal');
    if (focus) {
      console.log(`  Foco: ${focus.checked} elementos recorridos con Tab, ${focus.issues.length} sin foco visible suficiente`);
      for (const issue of focus.issues) console.log(`  - ${issue}`);
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
