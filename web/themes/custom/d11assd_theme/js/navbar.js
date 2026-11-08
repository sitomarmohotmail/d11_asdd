/**
 * @file
 * Cierra el menú plegable de Bootstrap5 con Esc y devuelve el foco al botón
 * del menú (spec 001, R13, CA21, CA22, decisión 8).
 *
 * No duplica la lógica de apertura ni toca aria-expanded a mano:
 * usa bootstrap.Collapse (provisto por bootstrap5/bootstrap5-js-latest).
 *
 * @param {Drupal} Drupal - Espacio de nombres de Drupal.
 * @param {once} once - Utilidad once() del núcleo de Drupal.
 */
(function d11assdNavbar(Drupal, once) {
  Drupal.behaviors.d11assdNavbar = {
    attach(context) {
      once('d11assd-navbar', 'nav.navbar', context).forEach((navbar) => {
        const toggler = navbar.querySelector('.navbar-toggler');
        if (!toggler) {
          return;
        }

        const targetId = toggler.getAttribute('aria-controls');
        const collapseEl = targetId ? document.getElementById(targetId) : null;
        if (!collapseEl) {
          return;
        }

        /**
         * Cierra el menú y devuelve el foco al botón si está abierto (CA21).
         *
         * @param {KeyboardEvent} event - Evento de teclado.
         */
        function handleEsc(event) {
          if (event.key !== 'Escape') {
            return;
          }
          if (toggler.getAttribute('aria-expanded') !== 'true') {
            return;
          }
          // bootstrap.Collapse actualiza aria-expanded y la visibilidad.
          // eslint-disable-next-line no-undef
          bootstrap.Collapse.getOrCreateInstance(collapseEl).hide();
          toggler.focus();
          event.stopPropagation();
        }

        // CA21: Esc con el foco en el botón (antes de entrar en el menú).
        toggler.addEventListener('keydown', handleEsc);

        // CA21: Esc con el foco en un enlace del menú (CA17b → Tab al interior).
        collapseEl.addEventListener('keydown', handleEsc);
      });
    },
  };
})(Drupal, once);
