/**
 * @file
 * Tests JS-unit del slider de imágenes (CA4, CA4b, CA5, CA6, CA10a/b, CA-A4).
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { installDrupal, loadLogic } from './helpers.mjs';

const Drupal = installDrupal();
loadLogic(
  new URL(
    '../../components/cartoteca-image-slider/cartoteca-image-slider.logic.js',
    import.meta.url,
  ),
);
const S = Drupal.sdcCartoteca.slider;
const DEF = '/modules/custom/sdc_cartoteca/images/imagen-no-disponible.jpg';

const sizes = (tag) => ({
  'original-view': `http://x/${tag}-o.jpg`,
  big: `http://x/${tag}-b.jpg`,
  medium: `http://x/${tag}-m.jpg`,
  small: `http://x/${tag}-s.jpg`,
});

test('CA4 · normalizeImages concatena destacada, media y b/n en orden', () => {
  const raw = {
    field_imagen_destacada_media: [sizes('dest')],
    field_imagenes_media: [sizes('m1'), sizes('m2')],
    field_imagenes_b_n: [sizes('bn')],
  };
  const slides = S.normalizeImages(raw, DEF);
  assert.equal(slides.length, 4);
  assert.deepEqual(
    slides.map((s) => s.medium),
    ['http://x/dest-m.jpg', 'http://x/m1-m.jpg', 'http://x/m2-m.jpg', 'http://x/bn-m.jpg'],
  );
});

test('CA4b · sin imágenes → una diapositiva con la imagen por defecto', () => {
  const slides = S.normalizeImages({}, DEF);
  assert.deepEqual(slides, [{ default: DEF }]);
  const html = S.buildLoadedHTML(slides);
  assert.ok(!html.includes('sdc-cartoteca__controls'), 'sin controles');
  assert.ok(!html.includes('sdc-cartoteca__btn-next'), 'sin flechas');
  assert.ok(!html.includes('sdc-cartoteca__thumb'), 'sin miniaturas');
});

test('CA10a · array vacío → not_found con imagen por defecto', () => {
  const r = S.classifyResponse(true, [], DEF);
  assert.equal(r.status, 'not_found');
  assert.deepEqual(r.slides, [{ default: DEF }]);
});

test('CA10b · respuesta HTTP no correcta → error con imagen por defecto', () => {
  const r = S.classifyResponse(false, null, DEF);
  assert.equal(r.status, 'error');
  assert.deepEqual(r.slides, [{ default: DEF }]);
});

test('CA10 · respuesta correcta con datos → loaded', () => {
  const r = S.classifyResponse(true, [{ field_imagenes_media: [sizes('a')] }], DEF);
  assert.equal(r.status, 'loaded');
  assert.equal(r.slides.length, 1);
});

test('CA5 · con varias imágenes hay flechas y miniaturas como <button>', () => {
  const html = S.buildLoadedHTML([sizes('a'), sizes('b')]);
  assert.match(html, /sdc-cartoteca__btn-prev[^>]*aria-label=/);
  assert.match(html, /sdc-cartoteca__btn-next[^>]*aria-label=/);
  assert.match(html, /<button[^>]*sdc-cartoteca__thumb/);
  assert.match(html, /aria-roledescription="Carrusel"/);
  // A11Y-003: cada diapositiva es un grupo con rol de diapositiva.
  assert.match(html, /role="group" aria-roledescription="Diapositiva"/);
});

test('CA6 · el selector de resolución lista las claves disponibles', () => {
  const html = S.buildControls({ big: 'http://x/b.jpg', small: 'http://x/s.jpg' });
  const options = html.match(/<option /g) ?? [];
  assert.equal(options.length, 2);
  assert.match(html, /<a class="sdc-cartoteca__download[^"]*"[^>]*href="http:\/\/x\/b\.jpg"/);
});

test('CA-A4 · las diapositivas tienen alt y las miniaturas aria-pressed', () => {
  const html = S.buildLoadedHTML([sizes('a'), sizes('b')]);
  assert.match(html, /<img src="[^"]*"\s+alt="Vista 1 del mapa"/);
  assert.match(html, /aria-pressed="true"/);
  assert.match(html, /aria-pressed="false"/);
});

test('CA12 · el estado inicial (Limpiar/url="") muestra la imagen por defecto', () => {
  const html = S.buildInitialHTML(DEF);
  assert.ok(html.includes(DEF), 'contiene la imagen por defecto');
  assert.match(html, /alt="Imagen no disponible"/);
  assert.ok(!html.includes('sdc-cartoteca__controls'), 'sin controles');
  assert.ok(!html.includes('sdc-cartoteca__btn-next'), 'sin flechas');
});
