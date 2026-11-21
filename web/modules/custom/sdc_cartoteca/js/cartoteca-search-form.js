/**
 * @file
 * Comportamiento del formulario de búsqueda de la Cartoteca IECA.
 *
 * Mejora progresiva: con valor, intercepta el envío (sin recarga) y despacha
 * cartoteca:load en cada componente; con el campo vacío, deja que el formulario
 * se envíe para que la validación de servidor (inline_form_errors) muestre el
 * error inline. La construcción de la URL vive en cartoteca-search-form.logic.js
 * (Drupal.sdcCartoteca.form).
 *
 * @param {Drupal} Drupal
 *   Drupal global object.
 * @param {once} once
 *   Drupal once utility.
 * @param {object} drupalSettings
 *   Drupal settings object.
 */
(function sdcCartotecaSearchFormInit(Drupal, once, drupalSettings) {
  const F = Drupal.sdcCartoteca.form;

  Drupal.behaviors.cartotecaSearchForm = {
    attach(context) {
      once(
        'cartoteca-search-form',
        '[data-cartoteca-instance]',
        context,
      ).forEach((form) => {
        const input = form.querySelector('.sdc-cartoteca__input');
        const limpiarBtn = form.querySelector('[data-cartoteca-limpiar]');

        /**
         * Despacha cartoteca:load en los componentes del formulario.
         *
         * @param {string} url
         *   URL de la API, o '' para restaurar el estado inicial.
         */
        function dispatch(url) {
          form
            .querySelectorAll('.sdc-cartoteca__slider, .sdc-cartoteca__info')
            .forEach((el) =>
              el.dispatchEvent(
                new CustomEvent('cartoteca:load', {
                  bubbles: false,
                  detail: { url },
                }),
              ),
            );
        }

        form.addEventListener('submit', (e) => {
          const val = (input?.value ?? '').trim();
          // Campo vacío: no interceptamos; valida el servidor (IFE).
          if (!val) return;
          e.preventDefault();
          const base = drupalSettings?.sdc_cartoteca?.apiBase ?? '';
          dispatch(F.buildApiUrl(base, val));
        });

        if (limpiarBtn) {
          limpiarBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (input) input.value = '';
            dispatch('');
          });
        }
      });
    },
  };
})(Drupal, once, drupalSettings);
