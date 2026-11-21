/**
 * @file
 * Lógica pura del slider de imágenes de la Cartoteca IECA.
 *
 * Funciones de parseo y constructores de HTML, sin acceso al DOM ni a eventos,
 * publicadas en Drupal.sdcCartoteca.slider para que las consuma el behavior y
 * para poder probarlas con `node --test` (ver tests/js/image-slider.test.mjs).
 *
 * @param {Drupal} Drupal
 *   Drupal global object.
 */
(function sdcCartotecaSliderLogic(Drupal) {
  const SIZE_LABELS = {
    'original-view': Drupal.t('Vista original'),
    big: Drupal.t('Grande'),
    medium: Drupal.t('Mediana'),
    small: Drupal.t('Pequeña'),
  };
  const IMAGE_SIZES = ['original-view', 'big', 'medium', 'small'];

  /**
   * Escapa texto para insertarlo en HTML.
   *
   * @param {*} s
   *   Valor a escapar.
   *
   * @return {string}
   *   Texto escapado.
   */
  function esc(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  /**
   * Devuelve la URL solo si usa un esquema seguro (http, https o relativa).
   *
   * @param {*} u
   *   Valor de URL procedente del JSON de la API.
   *
   * @return {string}
   *   La URL saneada, o cadena vacía si el esquema no es seguro.
   */
  function safeUrl(u) {
    const s = String(u ?? '').trim();
    if (/^(?:javascript|data|vbscript):/i.test(s)) return '';
    return s;
  }

  /**
   * Indica si una imagen trae al menos una variante de tamaño.
   *
   * @param {object} img
   *   Objeto de imagen de la API.
   *
   * @return {boolean}
   *   Cierto si hay alguna clave de tamaño con valor.
   */
  function hasSizes(img) {
    return IMAGE_SIZES.some((s) => img[s]);
  }

  /**
   * Extrae las variantes de tamaño presentes en una imagen.
   *
   * @param {object} img
   *   Objeto de imagen de la API.
   *
   * @return {?object}
   *   Mapa tamaño→URL, o null si no hay ninguno.
   */
  function extractSizes(img) {
    const sizes = {};
    IMAGE_SIZES.forEach((s) => {
      if (img[s]) sizes[s] = img[s];
    });
    return Object.keys(sizes).length ? sizes : null;
  }

  /**
   * Construye la lista de diapositivas a partir del registro de la API.
   *
   * Orden: field_imagen_destacada_media, field_imagenes_media,
   * field_imagenes_b_n. Si no hay ninguna, una diapositiva con la imagen
   * por defecto.
   *
   * @param {object} raw
   *   Registro de mapa de la API (data[0]).
   * @param {string} defaultImage
   *   URL de la imagen por defecto.
   *
   * @return {Array<object>}
   *   Lista de diapositivas (mapas de tamaños), o [{default}].
   */
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

  /**
   * Clasifica la respuesta de la API para el slider.
   *
   * @param {boolean} ok
   *   Si la respuesta HTTP fue correcta.
   * @param {*} data
   *   Cuerpo JSON ya parseado.
   * @param {string} defaultImage
   *   URL de la imagen por defecto.
   *
   * @return {{status: string, slides: Array<object>}}
   *   status: 'loaded' | 'not_found' | 'error'; slides a renderizar.
   */
  function classifyResponse(ok, data, defaultImage) {
    if (!ok) return { status: 'error', slides: [{ default: defaultImage }] };
    if (!Array.isArray(data) || !data.length) {
      return { status: 'not_found', slides: [{ default: defaultImage }] };
    }
    return { status: 'loaded', slides: normalizeImages(data[0], defaultImage) };
  }

  /**
   * Construye el HTML de una diapositiva.
   *
   * @param {object} sizes
   *   Mapa de tamaños (o {default}).
   * @param {number} index
   *   Índice de la diapositiva.
   * @param {number} total
   *   Número total de diapositivas.
   *
   * @return {string}
   *   HTML de la diapositiva.
   */
  function buildSlide(sizes, index, total) {
    const src = safeUrl(
      sizes['original-view'] ||
        sizes.big ||
        sizes.medium ||
        sizes.small ||
        sizes.default ||
        '',
    );
    const hidden = index > 0 ? ' d-none' : '';
    const ariaAttr = index > 0 ? ' aria-hidden="true"' : '';
    const slideAria =
      total > 1
        ? ` role="group" aria-roledescription="${Drupal.t('Diapositiva')}"` +
          ` aria-label="${Drupal.t('@n de @t', { '@n': index + 1, '@t': total })}"`
        : '';
    const sizesJson = esc(JSON.stringify(sizes));
    return (
      `<div class="sdc-cartoteca__slide${hidden}" data-slide-index="${index}"` +
      ` data-sizes="${sizesJson}"${slideAria}${ariaAttr}>` +
      `<img src="${esc(src)}"` +
      ` alt="${Drupal.t('Vista @n del mapa', { '@n': index + 1 })}"` +
      ` class="sdc-cartoteca__image card-img-top img-fluid" loading="lazy" />` +
      `</div>`
    );
  }

  /**
   * Construye el pie de controles (selector de resolución y descarga).
   *
   * @param {object} firstSizes
   *   Mapa de tamaños de la imagen activa.
   *
   * @return {string}
   *   HTML del pie de controles.
   */
  function buildControls(firstSizes) {
    const options = IMAGE_SIZES.filter((s) => firstSizes[s])
      .map(
        (s) =>
          `<option value="${esc(safeUrl(firstSizes[s]))}">${SIZE_LABELS[s]}</option>`,
      )
      .join('');
    const firstKey = IMAGE_SIZES.find((s) => firstSizes[s]);
    const href = firstKey ? esc(safeUrl(firstSizes[firstKey])) : '#';
    return (
      `<div class="sdc-cartoteca__controls card-footer d-flex align-items-center gap-2" data-active-slide="0">` +
      `<select class="sdc-cartoteca__resolution form-select form-select-sm"` +
      ` aria-label="${Drupal.t('Resolución de imagen')}">${options}</select>` +
      `<a class="sdc-cartoteca__download btn btn-sm btn-outline-primary" href="${href}" download>` +
      `${Drupal.t('Descargar')}</a></div>`
    );
  }

  /**
   * Construye la tira de miniaturas.
   *
   * @param {Array<object>} slides
   *   Lista de diapositivas.
   *
   * @return {string}
   *   HTML de las miniaturas, o cadena vacía si hay una sola.
   */
  function buildThumbnails(slides) {
    if (slides.length <= 1) return '';
    const thumbs = slides
      .map((sizes, i) => {
        const src = safeUrl(
          sizes['original-view'] ||
            sizes.big ||
            sizes.medium ||
            sizes.small ||
            sizes.default ||
            '',
        );
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

  /**
   * Construye las flechas de navegación.
   *
   * @param {number} total
   *   Número total de diapositivas.
   *
   * @return {string}
   *   HTML de las flechas, o cadena vacía si hay una sola.
   */
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

  /**
   * Construye el HTML del slider con resultados.
   *
   * @param {Array<object>} slides
   *   Lista de diapositivas.
   *
   * @return {string}
   *   HTML completo del slider cargado.
   */
  function buildLoadedHTML(slides) {
    const total = slides.length;
    const slidesHTML = slides.map((s, i) => buildSlide(s, i, total)).join('');
    const arrows = buildArrows(total);
    const thumbs = buildThumbnails(slides);
    const controls = slides[0].default ? '' : buildControls(slides[0]);
    const stageAria =
      total > 1
        ? ` role="group" aria-roledescription="${Drupal.t('Carrusel')}"` +
          ` aria-label="${Drupal.t('Imágenes del mapa')}"`
        : '';
    return (
      `<div class="sdc-cartoteca__stage position-relative"${stageAria}>${slidesHTML}${arrows}</div>` +
      `${thumbs}${controls}`
    );
  }

  /**
   * Construye el HTML del estado inicial (imagen por defecto).
   *
   * @param {string} defaultImage
   *   URL de la imagen por defecto.
   *
   * @return {string}
   *   HTML del estado inicial.
   */
  function buildInitialHTML(defaultImage) {
    return (
      `<div class="sdc-cartoteca__stage">` +
      `<div class="sdc-cartoteca__slide" data-slide-index="0" data-sizes="{}">` +
      `<img src="${esc(safeUrl(defaultImage))}" alt="${Drupal.t('Imagen no disponible')}"` +
      ` class="sdc-cartoteca__image card-img-top img-fluid" loading="lazy" /></div></div>`
    );
  }

  /**
   * Construye el HTML del estado de carga (spinner).
   *
   * @return {string}
   *   HTML del spinner.
   */
  function buildLoadingHTML() {
    return (
      `<div class="sdc-cartoteca__stage">` +
      `<div class="spinner-border text-secondary" role="status">` +
      `<span class="visually-hidden">${Drupal.t('Cargando…')}</span></div></div>`
    );
  }

  Drupal.sdcCartoteca = Drupal.sdcCartoteca || {};
  Drupal.sdcCartoteca.slider = {
    SIZE_LABELS,
    IMAGE_SIZES,
    esc,
    safeUrl,
    normalizeImages,
    classifyResponse,
    buildSlide,
    buildControls,
    buildThumbnails,
    buildArrows,
    buildLoadedHTML,
    buildInitialHTML,
    buildLoadingHTML,
  };
})(Drupal);
