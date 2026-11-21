/**
 * @file
 * Tests JS-unit del panel de datos (CA7, CA7b, CA7c, CA-A3, CA10a/b).
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { installDrupal, loadLogic } from './helpers.mjs';

const Drupal = installDrupal();
loadLogic(
  new URL(
    '../../components/cartoteca-mapa-info/cartoteca-mapa-info.logic.js',
    import.meta.url,
  ),
);
const I = Drupal.sdcCartoteca.info;

test('CA7 · normalizeFields solo devuelve los campos con valor', () => {
  const raw = {
    field_autor: [{ value: 'Autor X' }],
    field_categoria: [{ label: 'Mapa' }],
    field_lugar: [{ value: '' }],
    field_provincia: [],
    field_comunidad_autonoma: null,
    field_imagenes_media: [{ medium: 'http://x/m.jpg' }],
    not_a_field: 'ignorar',
  };
  const fields = I.normalizeFields(raw);
  assert.deepEqual(fields, [
    { label: 'Autor', value: 'Autor X' },
    { label: 'Categoria', value: 'Mapa' },
  ]);
});

test('CA7b · detectAndExtract elimina null, cadena vacía y array vacío', () => {
  assert.equal(I.detectAndExtract('field_x', null), null);
  assert.equal(I.detectAndExtract('field_x', []), null);
  assert.equal(I.detectAndExtract('field_x', [{ value: '' }]), null);
  assert.equal(I.detectAndExtract('field_x', [{ value: 'ok' }]), 'ok');
});

test('CA7c / CA-A3 · la tabla usa table-striped, aria-label y th scope="row"', () => {
  const html = I.buildFieldsHTML([{ label: 'Autor', value: 'X' }]);
  assert.match(html, /<table[^>]*\btable-striped\b/);
  assert.match(html, /<table[^>]*aria-label="Datos del mapa"/);
  assert.match(html, /<th scope="row"[^>]*>Autor<\/th>/);
});

test('CA10a · array vacío → not_found con el mensaje de R8', () => {
  const r = I.classifyResponse(true, []);
  assert.equal(r.status, 'not_found');
  assert.equal(
    r.message,
    'No se han encontrado datos para ese número de control o el mapa no existe.',
  );
});

test('CA10b · error HTTP/red → mensaje de servicio no disponible', () => {
  const r = I.classifyResponse(false, null);
  assert.equal(r.status, 'error');
  assert.equal(
    r.message,
    'El servicio de la Cartoteca no está disponible en este momento.',
  );
});

test('CA7 · respuesta con campos → loaded con la lista', () => {
  const r = I.classifyResponse(true, [{ field_autor: [{ value: 'Autor X' }] }]);
  assert.equal(r.status, 'loaded');
  assert.deepEqual(r.fields, [{ label: 'Autor', value: 'Autor X' }]);
});

test('CA12 · el estado inicial (Limpiar/url="") muestra "Esperando selección"', () => {
  const html = I.buildMessageHTML('Esperando selección');
  assert.match(html, /sdc-cartoteca__message[^>]*>Esperando selección</);
});
