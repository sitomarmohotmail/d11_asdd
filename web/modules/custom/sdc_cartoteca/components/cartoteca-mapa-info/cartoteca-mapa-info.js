/**
 * @file
 * Comportamiento del panel de información de la Cartoteca IECA.
 *
 * sdcCartotecaInfoLoader — escucha cartoteca:load, hace fetch y renderiza los
 * campos del mapa como tabla accesible.
 *
 * La lógica pura y los constructores de HTML viven en
 * cartoteca-mapa-info.logic.js (Drupal.sdcCartoteca.info).
 *
 * @param {Drupal} Drupal
 *   Drupal global object.
 * @param {once} once
 *   Drupal once utility.
 */
(function sdcCartotecaInfoInit(Drupal, once) {
  const I = Drupal.sdcCartoteca.info;

  Drupal.behaviors.sdcCartotecaInfoLoader = {
    attach(context) {
      once(
        'sdc-cartoteca-info-loader',
        '.sdc-cartoteca__info',
        context,
      ).forEach((card) => {
        const body = card.querySelector('.sdc-cartoteca__info-body');
        let currentRequest = 0;

        card.addEventListener('cartoteca:load', async (e) => {
          currentRequest += 1;
          const reqId = currentRequest;
          const url = e.detail?.url ?? '';

          if (!url) {
            body.innerHTML = I.buildMessageHTML(
              Drupal.t('Esperando selección'),
            );
            return;
          }

          body.innerHTML = I.buildLoadingHTML();
          Drupal.announce(Drupal.t('Cargando datos del mapa…'));

          let ok = false;
          let data = null;
          try {
            const response = await fetch(url);
            ok = response.ok;
            if (ok) data = await response.json();
          } catch (_err) {
            ok = false;
          }
          if (reqId !== currentRequest) return;

          const { status, message, fields } = I.classifyResponse(ok, data);
          if (status === 'loaded') {
            body.innerHTML = I.buildFieldsHTML(fields);
            Drupal.announce(Drupal.t('Datos del mapa cargados.'));
          } else {
            body.innerHTML = I.buildMessageHTML(message);
            Drupal.announce(message);
          }
        });
      });
    },
  };
})(Drupal, once);
