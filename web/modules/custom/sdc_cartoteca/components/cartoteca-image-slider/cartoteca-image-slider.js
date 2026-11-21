/**
 * @file
 * Comportamiento del slider de imágenes de la Cartoteca IECA.
 *
 * sdcCartotecaSlider        — navegación (flechas, miniaturas, selector).
 * sdcCartotecaSliderLoader  — escucha cartoteca:load, hace fetch y renderiza.
 *
 * La lógica pura y los constructores de HTML viven en
 * cartoteca-image-slider.logic.js (Drupal.sdcCartoteca.slider).
 *
 * @param {Drupal} Drupal
 *   Drupal global object.
 * @param {once} once
 *   Drupal once utility.
 */
(function sdcCartotecaSliderInit(Drupal, once) {
  const S = Drupal.sdcCartoteca.slider;
  const { IMAGE_SIZES, SIZE_LABELS, safeUrl } = S;

  // ---------------------------------------------------------------------------
  // Helpers de navegación (manipulan el DOM; interacción viva → verif. manual)
  // ---------------------------------------------------------------------------

  /**
   * Actualiza el selector de resolución y el enlace de descarga.
   *
   * @param {HTMLElement} controls
   *   Contenedor del pie de controles.
   * @param {object} sizes
   *   Mapa de tamaños de la imagen activa.
   */
  function updateControls(controls, sizes) {
    const select = controls.querySelector('.sdc-cartoteca__resolution');
    const link = controls.querySelector('.sdc-cartoteca__download');
    if (!select) return;
    select.innerHTML = '';
    IMAGE_SIZES.forEach((key) => {
      if (!sizes[key]) return;
      const opt = document.createElement('option');
      opt.value = safeUrl(sizes[key]);
      opt.textContent = SIZE_LABELS[key];
      select.appendChild(opt);
    });
    if (link && select.value) link.href = select.value;
  }

  /**
   * Muestra la diapositiva indicada y sincroniza miniaturas y controles.
   *
   * @param {HTMLElement} wrapper
   *   Contenedor del slider.
   * @param {number} targetIndex
   *   Índice de la diapositiva destino.
   */
  function goToSlide(wrapper, targetIndex) {
    const slides = wrapper.querySelectorAll('.sdc-cartoteca__slide');
    const thumbs = wrapper.querySelectorAll('.sdc-cartoteca__thumb');
    const controls = wrapper.querySelector('.sdc-cartoteca__controls');

    slides.forEach((slide, i) => {
      const active = i === targetIndex;
      slide.classList.toggle('d-none', !active);
      if (i > 0) slide.setAttribute('aria-hidden', active ? 'false' : 'true');
    });

    thumbs.forEach((thumb, i) => {
      const active = i === targetIndex;
      thumb.classList.toggle('sdc-cartoteca__thumb--active', active);
      thumb.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    if (controls) {
      controls.dataset.activeSlide = targetIndex;
      const activeSlide = slides[targetIndex];
      if (activeSlide) {
        updateControls(controls, JSON.parse(activeSlide.dataset.sizes || '{}'));
      }
    }
  }

  // ---------------------------------------------------------------------------
  // Behavior: navegación (event delegation — funciona con HTML dinámico)
  // ---------------------------------------------------------------------------

  Drupal.behaviors.sdcCartotecaSlider = {
    attach(context) {
      once('sdc-cartoteca-slider', '.sdc-cartoteca__slider', context).forEach(
        (wrapper) => {
          wrapper.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-direction]');
            if (!btn) return;
            const slides = wrapper.querySelectorAll('.sdc-cartoteca__slide');
            if (slides.length <= 1) return;
            const controls = wrapper.querySelector('.sdc-cartoteca__controls');
            const current = controls
              ? parseInt(controls.dataset.activeSlide ?? '0', 10)
              : 0;
            const next =
              btn.dataset.direction === 'next'
                ? (current + 1) % slides.length
                : (current - 1 + slides.length) % slides.length;
            goToSlide(wrapper, next);
          });

          wrapper.addEventListener('click', (e) => {
            const thumb = e.target.closest('.sdc-cartoteca__thumb');
            if (!thumb) return;
            goToSlide(wrapper, parseInt(thumb.dataset.slideIndex, 10));
          });

          wrapper.addEventListener('change', (e) => {
            if (!e.target.matches('.sdc-cartoteca__resolution')) return;
            const link = wrapper.querySelector('.sdc-cartoteca__download');
            if (link) link.href = e.target.value;
          });
        },
      );
    },
  };

  // ---------------------------------------------------------------------------
  // Behavior: carga asíncrona (escucha el evento cartoteca:load)
  // ---------------------------------------------------------------------------

  Drupal.behaviors.sdcCartotecaSliderLoader = {
    attach(context) {
      once(
        'sdc-cartoteca-slider-loader',
        '.sdc-cartoteca__slider',
        context,
      ).forEach((wrapper) => {
        const defaultImage = wrapper.dataset.cartotecaDefault || '';
        const body = wrapper.querySelector('.sdc-cartoteca__slider-body');
        let currentRequest = 0;

        wrapper.addEventListener('cartoteca:load', async (e) => {
          currentRequest += 1;
          const reqId = currentRequest;
          const url = e.detail?.url ?? '';

          if (!url) {
            body.innerHTML = S.buildInitialHTML(defaultImage);
            return;
          }

          body.innerHTML = S.buildLoadingHTML();
          Drupal.announce(Drupal.t('Cargando imágenes del mapa…'));

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

          const { status, slides } = S.classifyResponse(ok, data, defaultImage);
          body.innerHTML = S.buildLoadedHTML(slides);

          if (status === 'loaded' && !slides[0].default) {
            Drupal.announce(
              Drupal.formatPlural(
                slides.length,
                '1 imagen cargada.',
                '@count imágenes cargadas.',
              ),
            );
          } else if (status === 'error') {
            Drupal.announce(
              Drupal.t('No se han podido cargar las imágenes del mapa.'),
            );
          } else {
            Drupal.announce(Drupal.t('No se han encontrado imágenes.'));
          }
        });
      });
    },
  };
})(Drupal, once);
