#!/usr/bin/env node
/**
 * Revisión automática de accesibilidad con axe-core (WCAG 2.2 AA).
 *
 * Uso:
 *   npm run a11y -- [--json salida.json] [--width 320] [--focus] [--menu] URL [URL…]
 *
 * Siempre comprueba también el reflujo (sin scroll horizontal, WCAG 1.4.10).
 * --focus recorre la página con Tab y mide el indicador de foco de cada
 * elemento (contorno o sombra, contraste ≥ 3:1 con el fondo; WCAG 2.4.7 y
 * 1.4.11).
 * --menu comprueba el menú plegable de Bootstrap5 (spec 001, R11–R13, v1.2):
 *   - CA17/CA17b: abrir con Intro → aria-expanded="true" y foco en el primer enlace
 *   - CA18/CA18b: cerrar con Espacio → aria-expanded="false", Tab no entra
 *   - CA19: a 1280 px el botón no se ve y el menú está visible
 *   - CA20: objetivos táctiles ≥ 24×24 px (axe target-size)
 *   - CA21/CA22: Esc cierra el menú y devuelve el foco (R13)
 *   - CA-A7: axe sobre el menú abierto
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
  const opts = { json: null, width: 1280, focus: false, menu: false, urls: [] };
  for (let i = 0; i < argv.length; i++) {
    if (argv[i] === '--json') opts.json = argv[++i];
    else if (argv[i] === '--focus') opts.focus = true;
    else if (argv[i] === '--menu') opts.menu = true;
    else if (argv[i] === '--width') opts.width = Number(argv[++i]);
    else opts.urls.push(argv[i]);
  }
  return opts;
}

// Chrome se abre con --no-sandbox, así que solo se permiten el sitio local y
// archivos locales (páginas de prueba), nunca webs externas.
const ALLOWED_HOSTS = new Set(['d11asdd.local', 'localhost', '127.0.0.1', '[::1]']);

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

/**
 * Comprueba el menú plegable de Bootstrap5 (spec 001, R11–R13, v1.2).
 * Solo se ejecuta con --menu. Devuelve { issues, axeWhileOpen }.
 *
 * CA19 (1280 px): botón no visible, menú visible.
 * CA17/CA17b (320 px): Intro abre el menú, aria-expanded="true", Tab llega
 *   al primer enlace de #block-d11asdd-theme-main-navigation.
 * CA18/CA18b (320 px): Espacio cierra el menú, aria-expanded="false", Tab
 *   no entra en el elemento controlado.
 * CA20: axe (target-size) sobre la página con el menú abierto.
 * CA21 (320 px): Esc cierra el menú y devuelve el foco al botón.
 * CA22 (320 px): Esc con el menú cerrado no hace nada.
 * CA-A7: 0 violaciones de axe con el menú abierto.
 */
async function checkMenu(page, width) {
  const issues = [];

  // Localizar botón y elemento controlado.
  const { togglerSel, controlsId, menuSel, firstLinkSel } = await page.evaluate(() => {
    const toggler = document.querySelector('nav.navbar button.navbar-toggler');
    if (!toggler) return {};
    const id = toggler.getAttribute('aria-controls');
    const collapseEl = id ? document.getElementById(id) : null;
    const firstLink = collapseEl ? collapseEl.querySelector('a[href]') : null;
    return {
      togglerSel: 'nav.navbar button.navbar-toggler',
      controlsId: id || null,
      menuSel: id ? `#${id}` : null,
      firstLinkSel: firstLink ? '#' + (firstLink.id || null) : null,
    };
  });

  if (!togglerSel || !controlsId || !menuSel) {
    issues.push('Botón .navbar-toggler o aria-controls no encontrados.');
    return { issues, axeWhileOpen: null };
  }

  if (width >= 992) {
    // CA19: a 1280 px el botón no debe ser visible y el menú sí.
    const { togglerVisible, menuVisible } = await page.evaluate((tSel, mSel) => {
      const tEl = document.querySelector(tSel);
      const mEl = document.querySelector(mSel);
      function isVisible(el) {
        if (!el) return false;
        const cs = getComputedStyle(el);
        return cs.display !== 'none' && cs.visibility !== 'hidden' && parseFloat(cs.opacity) > 0;
      }
      return { togglerVisible: isVisible(tEl), menuVisible: isVisible(mEl) };
    }, togglerSel, menuSel);

    if (togglerVisible) issues.push('CA19: el botón del menú es visible a ' + width + ' px (debe estar oculto).');
    if (!menuVisible) issues.push('CA19: el menú no está visible a ' + width + ' px (debe estar visible).');
    return { issues, axeWhileOpen: null };
  }

  // A 320 px: comprobar apertura con Intro (CA17, CA17b).
  await page.focus(togglerSel);
  await page.keyboard.press('Enter');
  await new Promise((r) => setTimeout(r, 400));

  const afterOpen = await page.evaluate((tSel, mSel) => {
    const tEl = document.querySelector(tSel);
    const mEl = document.querySelector(mSel);
    return {
      ariaExpanded: tEl ? tEl.getAttribute('aria-expanded') : null,
      menuVisible: mEl ? getComputedStyle(mEl).display !== 'none' : false,
    };
  }, togglerSel, menuSel);

  if (afterOpen.ariaExpanded !== 'true') {
    issues.push('CA17: tras Intro, aria-expanded no es "true" (es "' + afterOpen.ariaExpanded + '").');
  }
  if (!afterOpen.menuVisible) {
    issues.push('CA17: tras Intro, el menú controlado no es visible.');
  }

  // CA17b: Tab desde el botón llega al primer enlace del menú principal.
  await page.focus(togglerSel);
  await page.keyboard.press('Tab');
  await new Promise((r) => setTimeout(r, 300));
  const focusAfterTab = await page.evaluate((mSel) => {
    const el = document.activeElement;
    const collapseEl = document.querySelector(mSel);
    return {
      inMenu: collapseEl ? collapseEl.contains(el) : false,
      tag: el ? el.tagName.toLowerCase() : null,
      href: el ? el.getAttribute('href') : null,
    };
  }, menuSel);
  if (!focusAfterTab.inMenu || focusAfterTab.tag !== 'a') {
    issues.push('CA17b: tras Intro + Tab, el foco no está en el primer enlace del menú (está en <' + focusAfterTab.tag + '>).');
  }

  // CA21: Esc cierra el menú y devuelve el foco al botón.
  // (el foco está en un enlace del menú)
  await page.keyboard.press('Escape');
  await new Promise((r) => setTimeout(r, 400));

  const afterEsc = await page.evaluate((tSel) => {
    const tEl = document.querySelector(tSel);
    const focused = document.activeElement;
    return {
      ariaExpanded: tEl ? tEl.getAttribute('aria-expanded') : null,
      focusOnToggler: focused === tEl,
    };
  }, togglerSel);

  if (afterEsc.ariaExpanded !== 'false') {
    issues.push('CA21: tras Esc, aria-expanded no es "false" (es "' + afterEsc.ariaExpanded + '").');
  }
  if (!afterEsc.focusOnToggler) {
    issues.push('CA21: tras Esc, el foco no está en el botón del menú.');
  }

  // CA22: Esc con el menú cerrado no cambia el foco.
  const focusBeforeEsc = await page.evaluate(() => {
    const el = document.activeElement;
    return el ? (el.id || el.className) : null;
  });
  await page.keyboard.press('Escape');
  await new Promise((r) => setTimeout(r, 300));
  const focusAfterEsc = await page.evaluate(() => {
    const el = document.activeElement;
    return el ? (el.id || el.className) : null;
  });
  if (focusBeforeEsc !== focusAfterEsc) {
    issues.push('CA22: Esc con el menú cerrado cambió el foco (de "' + focusBeforeEsc + '" a "' + focusAfterEsc + '").');
  }

  // Volver a abrir para CA18 y para axe con el menú abierto (CA-A7, CA20).
  await page.focus(togglerSel);
  await page.keyboard.press('Enter');
  await new Promise((r) => setTimeout(r, 400));

  // CA-A7: axe con el menú abierto (incluye target-size para CA20).
  const axeResults = await new AxePuppeteer(page).configure({ locale: LOCALE }).withTags(TAGS).analyze();
  const axeViolations = axeResults.violations.map((v) => ({
    id: v.id,
    impact: v.impact,
    wcag: v.tags.filter((t) => /^wcag\d{3,4}$/.test(t)),
    help: v.help,
    nodes: v.nodes.map((n) => ({ target: n.target, html: n.html })),
  }));

  // CA18/CA18b: Espacio cierra el menú; Tab no entra en el elemento controlado.
  await page.focus(togglerSel);
  await page.keyboard.press('Space');
  await new Promise((r) => setTimeout(r, 400));

  const afterClose = await page.evaluate((tSel, mSel) => {
    const tEl = document.querySelector(tSel);
    const mEl = document.querySelector(mSel);
    return {
      ariaExpanded: tEl ? tEl.getAttribute('aria-expanded') : null,
      menuHidden: mEl ? getComputedStyle(mEl).display === 'none' : true,
    };
  }, togglerSel, menuSel);

  if (afterClose.ariaExpanded !== 'false') {
    issues.push('CA18: tras Espacio, aria-expanded no es "false" (es "' + afterClose.ariaExpanded + '").');
  }
  if (!afterClose.menuHidden) {
    issues.push('CA18: tras Espacio, el elemento controlado sigue visible.');
  }

  // CA18b: Tab desde el botón no debe entrar en el menú cerrado.
  await page.focus(togglerSel);
  await page.keyboard.press('Tab');
  await new Promise((r) => setTimeout(r, 300));
  const focusAfterTabClosed = await page.evaluate((mSel) => {
    const el = document.activeElement;
    const collapseEl = document.querySelector(mSel);
    return { inMenu: collapseEl ? collapseEl.contains(el) : false };
  }, menuSel);
  if (focusAfterTabClosed.inMenu) {
    issues.push('CA18b: con el menú cerrado, Tab desde el botón entró en el elemento controlado.');
  }

  return { issues, axeWhileOpen: axeViolations };
}

const opts = parseArgs(process.argv.slice(2));
if (opts.urls.length === 0) {
  console.error('Uso: npm run a11y -- [--json salida.json] [--width 320] [--focus] [--menu] URL [URL…]');
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
    const menu = opts.menu ? await checkMenu(page, opts.width) : null;
    await page.close();

    const violations = results.violations.map((v) => ({
      id: v.id,
      impact: v.impact,
      wcag: v.tags.filter((t) => /^wcag\d{3,4}$/.test(t)),
      help: v.help,
      helpUrl: v.helpUrl,
      nodes: v.nodes.map((n) => ({ target: n.target, html: n.html, summary: n.failureSummary })),
    }));

    const menuIssueCount = menu ? menu.issues.length + (menu.axeWhileOpen ? menu.axeWhileOpen.length : 0) : 0;
    total += violations.length + (reflow ? 1 : 0) + (focus ? focus.issues.length : 0) + menuIssueCount;
    report.push({
      url,
      status: response?.status() ?? null,
      width: opts.width,
      violations,
      incomplete: results.incomplete.map((v) => ({ id: v.id, help: v.help, nodes: v.nodes.length })),
      passes: results.passes.length,
      reflow,
      focus,
      menu,
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
    if (menu) {
      const axeOpen = menu.axeWhileOpen ? menu.axeWhileOpen.length : 0;
      console.log(`  Menú (--menu): ${menu.issues.length} problemas de interacción, ${axeOpen} violaciones de axe con el menú abierto`);
      for (const issue of menu.issues) console.log(`  - ${issue}`);
      if (menu.axeWhileOpen) {
        for (const v of menu.axeWhileOpen) {
          console.log(`  - [axe-menu][${v.impact}] ${v.id}: ${v.help}`);
          for (const n of v.nodes) console.log(`      ${n.target.join(' ')}`);
        }
      }
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
