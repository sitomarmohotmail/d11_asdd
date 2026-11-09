/**
 * @file
 * Slider de imágenes de la Cartoteca IECA.
 *
 * sdcCartotecaSlider  — navegación (flechas, miniaturas, selector de tamaño).
 * sdcCartotecaSliderLoader — escucha cartoteca:load, hace fetch y renderiza.
 *
 * @param {Drupal} Drupal
 *   Drupal global object.
 * @param {once} once
 *   Drupal once utility.
 */
(function sdcCartotecaSliderInit(Drupal, once) {
  const SIZE_LABELS = {
    'original-view': Drupal.t('Vista original'),
    big: Drupal.t('Grande'),
    medium: Drupal.t('Mediana'),
    small: Drupal.t('Pequeña'),
  };
  const IMAGE_SIZES = ['original-view', 'big', 'medium', 'small'];

  // ---------------------------------------------------------------------------
  // Utilidades
  // ---------------------------------------------------------------------------

  function esc(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  // ---------------------------------------------------------------------------
  // Parseo de imágenes (portado de CartotecaApiService.php)
  // ---------------------------------------------------------------------------

  function hasSizes(img) {
    return IMAGE_SIZES.some((s) => img[s]);
  }

  function extractSizes(img) {
    const sizes = {};
    IMAGE_SIZES.forEach((s) => {
      if (img[s]) sizes[s] = img[s];
    });
    return Object.keys(sizes).length ? sizes : null;
  }

  function normalizeImages(raw, defaultImage) {
    const slides = [];
    const destacada = raw?.field_imagen_destacada_media?.[0];
    if (destacada && hasSizes(destacada)) {
      slides.push(extractSizes(destacada));
    }
    (raw?.field_imagenes_media ?? []).forEach((img) => {
      if (img && hasSizes(img)) slides.push(extractSizes(img));
    });
    (raw?.field_imagenes_b_n ?? []).forEach((img) => {
      if (img && hasSizes(img)) slides.push(extractSizes(img));
    });
    return slides.length ? slides : [{ default: defaultImage }];
  }

  // ---------------------------------------------------------------------------
  // Constructores de HTML
  // ---------------------------------------------------------------------------

  function buildSlide(sizes, index) {
    const src =
      sizes['original-view'] ||
      sizes.big ||
      sizes.medium ||
      sizes.small ||
      sizes.default ||
      '';
    const hidden = index > 0 ? ' d-none' : '';
    const ariaAttr = index > 0 ? ' aria-hidden="true"' : '';
    const sizesJson = JSON.stringify(sizes);
    return (
      `<div class="sdc-cartoteca__slide${hidden}" data-slide-index="${index}"` +
      ` data-sizes='${sizesJson}'${ariaAttr}>` +
      `<img src="${esc(src)}"` +
      ` alt="${Drupal.t('Vista @n del mapa', { '@n': index + 1 })}"` +
      ` class="sdc-cartoteca__image card-img-top img-fluid" loading="lazy" />` +
      `</div>`
    );
  }

  function buildControls(firstSizes) {
    const options = IMAGE_SIZES.filter((s) => firstSizes[s])
      .map(
        (s) =>
          `<option value="${esc(firstSizes[s])}">${SIZE_LABELS[s]}</option>`,
      )
      .join('');
    const firstKey = IMAGE_SIZES.find((s) => firstSizes[s]);
    const href = firstKey ? esc(firstSizes[firstKey]) : '#';
    return (
      `<div class="sdc-cartoteca__controls card-footer d-flex align-items-center gap-2" data-active-slide="0">` +
      `<select class="sdc-cartoteca__resolution form-select form-select-sm"` +
      ` aria-label="${Drupal.t('Resolución de imagen')}">${options}</select>` +
      `<a class="sdc-cartoteca__download btn btn-sm btn-outline-primary" href="${href}" download>` +
      `${Drupal.t('Descargar')}</a></div>`
    );
  }

  function buildThumbnails(slides) {
    if (slides.length <= 1) return '';
    const thumbs = slides
      .map((sizes, i) => {
        const src =
          sizes['original-view'] ||
          sizes.big ||
          sizes.medium ||
          sizes.small ||
          sizes.default ||
          '';
        const active = i === 0 ? ' sdc-cartoteca__thumb--active' : '';
        const pressed = i === 0 ? 'true' : 'false';
        return (
          `<button class="sdc-cartoteca__thumb${active} btn btn-link p-1"` +
          ` data-slide-index="${i}" aria-pressed="${pressed}" type="button">` +
          `<img src="${esc(src)}"` +
          ` alt="${Drupal.t('Miniatura @n', { '@n': i + 1 })}"` +
          ` class="sdc-cartoteca__thumb-img" loading="lazy" /></button>`
        );
      })
      .join('');
    return (
      `<div class="sdc-cartoteca__thumbnails d-flex flex-wrap gap-1 p-2 border-top overflow-auto">` +
      `${thumbs}</div>`
    );
  }

  function buildArrows(total) {
    if (total <= 1) return '';
    return (
      `<button class="sdc-cartoteca__btn-prev btn btn-light position-absolute top-50 start-0 translate-middle-y"` +
      ` data-direction="prev" type="button" aria-label="${Drupal.t('Imagen anterior')}">` +
      `<span aria-hidden="true">‹</span></button>` +
      `<button class="sdc-cartoteca__btn-next btn btn-light position-absolute top-50 end-0 translate-middle-y"` +
      ` data-direction="next" type="button" aria-label="${Drupal.t('Imagen siguiente')}">` +
      `<span aria-hidden="true">›</span></button>`
    );
  }

  function buildLoadedHTML(slides) {
    const slidesHTML = slides.map((s, i) => buildSlide(s, i)).join('');
    const arrows = buildArrows(slides.length);
    const thumbs = buildThumbnails(slides);
    const controls = slides[0].default ? '' : buildControls(slides[0]);
    return (
      `<div class="sdc-cartoteca__stage position-relative">${slidesHTML}${arrows}</div>` +
      `${thumbs}${controls}`
    );
  }

  function buildInitialHTML(defaultImage) {
    return (
      `<div class="sdc-cartoteca__stage">` +
      `<div class="sdc-cartoteca__slide" data-slide-index="0" data-sizes="{}">` +
      `<img src="${esc(defaultImage)}" alt="${Drupal.t('Imagen no disponible')}"` +
      ` class="sdc-cartoteca__image card-img-top img-fluid" loading="lazy" /></div></div>`
    );
  }

  function buildLoadingHTML() {
    return (
      `<div class="sdc-cartoteca__stage">` +
      `<div class="spinner-border text-secondary" role="status">` +
      `<span class="visually-hidden">${Drupal.t('Cargando…')}</span></div></div>`
    );
  }

  // ---------------------------------------------------------------------------
  // Helpers de navegación
  // ---------------------------------------------------------------------------

  function updateControls(controls, sizes) {
    const select = controls.querySelector('.sdc-cartoteca__resolution');
    const link = controls.querySelector('.sdc-cartoteca__download');
    if (!select) return;
    select.innerHTML = '';
    IMAGE_SIZES.forEach((key) => {
      if (!sizes[key]) return;
      const opt = document.createElement('option');
      opt.value = sizes[key];
      opt.textContent = SIZE_LABELS[key];
      select.appendChild(opt);
    });
    if (link && select.value) link.href = select.value;
  }

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
  // Behavior: navegación (todo event delegation — funciona con HTML dinámico)
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
            body.innerHTML = buildInitialHTML(defaultImage);
            return;
          }

          body.innerHTML = buildLoadingHTML();

          try {
            const response = await fetch(url);
            if (reqId !== currentRequest) return;
            if (!response.ok) throw new Error(response.status);
            const data = await response.json();
            if (reqId !== currentRequest) return;

            if (!Array.isArray(data) || !data.length) {
              body.innerHTML = buildInitialHTML(defaultImage);
              return;
            }

            const slides = normalizeImages(data[0], defaultImage);
            body.innerHTML = buildLoadedHTML(slides);
          } catch (_err) {
            if (reqId !== currentRequest) return;
            body.innerHTML = buildInitialHTML(defaultImage);
          }
        });
      });
    },
  };
})(Drupal, once);
