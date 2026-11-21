/**
 * @file
 * Tests JS-unit del formulario de búsqueda (CA3).
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { installDrupal, loadLogic } from './helpers.mjs';

const Drupal = installDrupal();
loadLogic(new URL('../../js/cartoteca-search-form.logic.js', import.meta.url));
const { buildApiUrl } = Drupal.sdcCartoteca.form;

const BASE =
  'https://ws089.juntadeandalucia.es/.../mapa-apitest?_format=json';

test('CA3 · buildApiUrl convierte a mayúsculas y añade el parámetro', () => {
  const url = buildApiUrl(BASE, 'ieca1988118769');
  assert.ok(url.startsWith(BASE), 'parte de la URL base');
  assert.match(url, /&numero_de_control=IECA1988118769$/);
});

test('CA3 · buildApiUrl recorta espacios del valor', () => {
  const url = buildApiUrl(BASE, '  ieca123  ');
  assert.match(url, /&numero_de_control=IECA123$/);
});

test('CA3 · buildApiUrl codifica caracteres especiales', () => {
  const url = buildApiUrl(BASE, 'a b/c');
  assert.match(url, /&numero_de_control=A%20B%2FC$/);
});
