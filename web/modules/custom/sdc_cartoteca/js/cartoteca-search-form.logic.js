/**
 * @file
 * Lógica pura del formulario de búsqueda de la Cartoteca IECA.
 *
 * Publica Drupal.sdcCartoteca.form.buildApiUrl para el behavior y para
 * `node --test` (ver tests/js/search-form.test.mjs).
 *
 * @param {Drupal} Drupal
 *   Drupal global object.
 */
(function sdcCartotecaFormLogic(Drupal) {
  /**
   * Construye la URL de la API para un número de control.
   *
   * Convierte el valor a mayúsculas y lo codifica sobre la URL base.
   *
   * @param {string} base
   *   URL base de la API (drupalSettings.sdc_cartoteca.apiBase).
   * @param {string} value
   *   Número de control introducido.
   *
   * @return {string}
   *   URL completa con el parámetro numero_de_control.
   */
  function buildApiUrl(base, value) {
    const control = String(value ?? '')
      .trim()
      .toUpperCase();
    return `${base}&numero_de_control=${encodeURIComponent(control)}`;
  }

  Drupal.sdcCartoteca = Drupal.sdcCartoteca || {};
  Drupal.sdcCartoteca.form = { buildApiUrl };
})(Drupal);
