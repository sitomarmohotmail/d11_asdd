/**
 * @file
 * Comportamiento JS del slider de imágenes de la Cartoteca.
 *
 * Gestiona:
 *  - Cambio de diapositiva (flechas y miniaturas)
 *  - Selector de resolución y enlace de descarga según la imagen activa
 */
(function (Drupal, once) {
  'use strict';

  /**
   * Resolucion labels para el selector de descarga.
   * @type {Object.<string,string>}
   */
  const SIZE_LABELS = {
    'original-view': Drupal.t('Vista original'),
    big: Drupal.t('Grande'),
    medium: Drupal.t('Mediana'),
    small: Drupal.t('Pequeña'),
  };

  /**
   * Muestra la diapositiva indicada y actualiza el selector de resolución.
   *
   * @param {HTMLElement} wrapper - Elemento raíz del slider.
   * @param {number} targetIndex - Índice de la diapositiva a mostrar.
   */
  function goToSlide(wrapper, targetIndex) {
    const slides = wrapper.querySelectorAll('.sdc-cartoteca__slide');
    const thumbs = wrapper.querySelectorAll('.sdc-cartoteca__thumb');
    const controls = wrapper.querySelector('.sdc-cartoteca__controls');

    slides.forEach((slide, i) => {
      const active = i === targetIndex;
      slide.classList.toggle('d-none', !active);
      if (slide.hasAttribute('aria-hidden') || i > 0) {
        slide.setAttribute('aria-hidden', active ? 'false' : 'true');
      }
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
        const sizes = JSON.parse(activeSlide.dataset.sizes || '{}');
        updateControls(controls, sizes);
      }
    }
  }

  /**
   * Actualiza el selector de resolución y el enlace de descarga.
   *
   * @param {HTMLElement} controls - Contenedor de controles.
   * @param {Object} sizes - Mapa size → URL de la imagen activa.
   */
  function updateControls(controls, sizes) {
    const select = controls.querySelector('.sdc-cartoteca__resolution');
    const downloadLink = controls.querySelector('.sdc-cartoteca__download');

    if (select) {
      select.innerHTML = '';
      Object.entries(SIZE_LABELS).forEach(([key, label]) => {
        if (sizes[key]) {
          const opt = document.createElement('option');
          opt.value = sizes[key];
          opt.textContent = label;
          select.appendChild(opt);
        }
      });
      if (downloadLink && select.value) {
        downloadLink.href = select.value;
      }
    }
  }

  Drupal.behaviors.sdcCartotecaSlider = {
    attach(context) {
      once('sdc-cartoteca-slider', '.sdc-cartoteca__slider', context).forEach(
        (wrapper) => {
          // Flechas anterior / siguiente.
          wrapper.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-direction]');
            if (!btn) return;

            const slides = wrapper.querySelectorAll('.sdc-cartoteca__slide');
            const total = slides.length;
            if (total <= 1) return;

            const controls = wrapper.querySelector('.sdc-cartoteca__controls');
            const current = controls
              ? parseInt(controls.dataset.activeSlide ?? '0', 10)
              : 0;

            const next =
              btn.dataset.direction === 'next'
                ? (current + 1) % total
                : (current - 1 + total) % total;

            goToSlide(wrapper, next);
          });

          // Miniaturas.
          wrapper.addEventListener('click', (e) => {
            const thumb = e.target.closest('.sdc-cartoteca__thumb');
            if (!thumb) return;
            goToSlide(wrapper, parseInt(thumb.dataset.slideIndex, 10));
          });

          // Selector de resolución → actualizar enlace de descarga.
          const select = wrapper.querySelector('.sdc-cartoteca__resolution');
          const downloadLink = wrapper.querySelector('.sdc-cartoteca__download');
          if (select && downloadLink) {
            select.addEventListener('change', () => {
              downloadLink.href = select.value;
            });
          }
        },
      );
    },
  };
})(Drupal, once);
