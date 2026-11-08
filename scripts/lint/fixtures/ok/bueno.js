/**
 * @file
 * Ejemplo que cumple el estándar del core.
 */

((Drupal, drupalSettings, once) => {
  /**
   * Marca los elementos de ejemplo.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.ejemploBueno = {
    attach(context) {
      once('ejemplo-bueno', '.ejemplo', context).forEach((element) => {
        element.dataset.ruta = drupalSettings.path.baseUrl;
      });
    },
  };
})(Drupal, drupalSettings, once);
