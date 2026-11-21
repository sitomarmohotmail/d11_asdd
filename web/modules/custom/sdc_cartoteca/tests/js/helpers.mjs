/**
 * @file
 * Utilidades para los tests JS-unit (node --test) de sdc_cartoteca.
 *
 * Los `*.logic.js` son scripts clásicos que publican funciones puras en
 * Drupal.sdcCartoteca.*. Aquí instalamos un stub mínimo de Drupal y cargamos el
 * fichero con node:vm en el contexto global, sin necesidad de un navegador.
 */

import { readFileSync } from 'node:fs';
import { runInThisContext } from 'node:vm';

/**
 * Instala un stub mínimo de Drupal en el contexto global.
 *
 * @returns {object} El objeto Drupal simulado.
 */
export function installDrupal() {
  const t = (str, args = {}) =>
    String(str).replace(/@[\w-]+/g, (m) => (m in args ? String(args[m]) : m));
  const formatPlural = (n, singular, plural, args = {}) =>
    t(n === 1 ? singular : plural, { '@count': n, ...args });
  const Drupal = {
    t,
    formatPlural,
    announce() {},
    sdcCartoteca: {},
  };
  globalThis.Drupal = Drupal;
  return Drupal;
}

/**
 * Evalúa un fichero *.logic.js en el contexto global actual.
 *
 * @param {URL} url La URL del fichero a cargar.
 */
export function loadLogic(url) {
  runInThisContext(readFileSync(url, 'utf8'));
}
