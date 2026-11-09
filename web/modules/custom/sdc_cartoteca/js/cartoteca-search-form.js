/**
 * @file
 * Formulario de búsqueda de la Cartoteca IECA.
 *
 * Valida el campo inline, construye la URL y despacha el evento
 * cartoteca:load en cada componente del formulario.
 *
 * @param {Drupal} Drupal
 *   Drupal global object.
 * @param {once} once
 *   Drupal once utility.
 * @param {object} drupalSettings
 *   Drupal settings object.
 */
(function sdcCartotecaSearchFormInit(Drupal, once, drupalSettings) {
  Drupal.behaviors.cartotecaSearchForm = {
    attach(context) {
      once(
        'cartoteca-search-form',
        '[data-cartoteca-instance]',
        context,
      ).forEach((form) => {
        const input = form.querySelector('.sdc-cartoteca__input');
        const limpiarBtn = form.querySelector('[data-cartoteca-limpiar]');
        const errorId = `${form.id || 'cartoteca'}-input-error`;

        // --- Gestión de error inline ---

        function showError(msg) {
          let errorEl = form.querySelector(`#${CSS.escape(errorId)}`);
          if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.id = errorId;
            errorEl.className =
              'sdc-cartoteca__field-error text-danger small mt-1';
            errorEl.setAttribute('role', 'alert');
            input.after(errorEl);
          }
          errorEl.textContent = msg;
          input.setAttribute('aria-invalid', 'true');
          input.setAttribute('aria-describedby', errorId);
        }

        function clearError() {
          const errorEl = form.querySelector(`#${CSS.escape(errorId)}`);
          if (errorEl) errorEl.remove();
          input.removeAttribute('aria-invalid');
          input.removeAttribute('aria-describedby');
        }

        // --- Despacho del evento a los componentes ---

        function dispatch(url) {
          form.querySelectorAll('.sdc-cartoteca__slider').forEach((el) =>
            el.dispatchEvent(
              new CustomEvent('cartoteca:load', {
                bubbles: false,
                detail: { url },
              }),
            ),
          );
          form.querySelectorAll('.sdc-cartoteca__info').forEach((el) =>
            el.dispatchEvent(
              new CustomEvent('cartoteca:load', {
                bubbles: false,
                detail: { url },
              }),
            ),
          );
        }

        // --- Submit: validar y lanzar ---

        form.addEventListener('submit', (e) => {
          e.preventDefault();
          clearError();

          const val = (input?.value ?? '').trim();
          if (!val) {
            showError(Drupal.t('Este campo es obligatorio.'));
            input?.focus();
            return;
          }

          const base = drupalSettings?.sdc_cartoteca?.apiBase ?? '';
          const url = `${base}&numero_de_control=${encodeURIComponent(val.toUpperCase())}`;
          dispatch(url);
        });

        // --- Limpiar ---

        if (limpiarBtn) {
          limpiarBtn.addEventListener('click', (e) => {
            e.preventDefault();
            clearError();
            if (input) input.value = '';
            dispatch('');
          });
        }
      });
    },
  };
})(Drupal, once, drupalSettings);
