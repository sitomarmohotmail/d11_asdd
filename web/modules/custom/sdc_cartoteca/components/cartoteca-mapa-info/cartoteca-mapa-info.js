/**
 * @file
 * Panel de información de la Cartoteca IECA.
 *
 * sdcCartotecaInfoLoader — escucha cartoteca:load, hace fetch y renderiza
 * los campos del mapa como lista de definición.
 *
 * @param {Drupal} Drupal
 *   Drupal global object.
 * @param {once} once
 *   Drupal once utility.
 */
(function sdcCartotecaInfoInit(Drupal, once) {
  const SKIP_FIELDS = [
    'field_imagenes_media',
    'field_imagen_destacada_media',
    'field_imagenes_b_n',
  ];

  // ---------------------------------------------------------------------------
  // Utilidades
  // ---------------------------------------------------------------------------

  function esc(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function str(v) {
    return Array.isArray(v) ? '' : String(v ?? '').trim();
  }

  // ---------------------------------------------------------------------------
  // Parseo de campos (portado de CartotecaApiService.php)
  // ---------------------------------------------------------------------------

  function fieldLabel(key) {
    let name = key.slice(6); // quita 'field_'
    name = name.replace(/^\d+_/, '');
    return name.charAt(0).toUpperCase() + name.slice(1).replace(/_/g, ' ');
  }

  function extractLabels(records) {
    return records
      .map((r) => {
        if (typeof r === 'object' && r !== null) return str(r.label ?? '');
        return str(r);
      })
      .filter(Boolean);
  }

  function extractSubfields(records, prefix, only = []) {
    return records
      .filter((r) => typeof r === 'object' && r !== null)
      .map((record) => {
        const parts = [];
        Object.entries(record).forEach(([key, subfield]) => {
          if (
            key === 'title' ||
            key === 'metatag' ||
            key.endsWith('_indicador_1') ||
            key.endsWith('_indicador_2')
          ) {
            return;
          }
          if (only.length) {
            const suffix = key.slice(prefix.length + 1);
            if (!only.includes(suffix)) return;
          }
          const val = str(subfield?.[0]?.value ?? '');
          if (val) parts.push(val);
        });
        return parts.join(' ');
      })
      .filter(Boolean);
  }

  function detectAndExtract(key, records) {
    if (!Array.isArray(records) || !records.length) return null;
    const first = records[0];
    if (typeof first !== 'object' || first === null) {
      return str(String(first)) || null;
    }
    // Campo de imagen: saltar.
    if ('original-view' in first || 'big' in first) return null;
    // Campo de taxonomía / referencia: [{label: '...'}]
    if ('label' in first) {
      const vals = extractLabels(records);
      return vals.length ? vals.join('; ') : null;
    }
    // Campo de valor simple: [{value: '...'}]
    if ('value' in first && !Array.isArray(first.value)) {
      return str(first.value) || null;
    }
    // Subcampos MARC: {field_NNN_x: [{value: ...}], ...}
    const match = key.match(/^(field_\d+)_/);
    if (match) {
      const vals = extractSubfields(records, match[1]);
      return vals.length ? vals.join('; ') : null;
    }
    return null;
  }

  function normalizeFields(raw) {
    const fields = [];
    Object.entries(raw).forEach(([key, records]) => {
      if (!key.startsWith('field_') || SKIP_FIELDS.includes(key)) return;
      const value = detectAndExtract(key, records);
      if (value !== null && value !== '') {
        fields.push({ label: fieldLabel(key), value });
      }
    });
    return fields;
  }

  // ---------------------------------------------------------------------------
  // Constructores de HTML
  // ---------------------------------------------------------------------------

  function buildLoadingHTML() {
    return (
      `<div class="sdc-cartoteca__info-body card-body d-flex align-items-center justify-content-center">` +
      `<div class="spinner-border text-secondary" role="status">` +
      `<span class="visually-hidden">${Drupal.t('Cargando…')}</span>` +
      `</div></div>`
    );
  }

  function buildMessageHTML(msg) {
    return (
      `<div class="sdc-cartoteca__info-body card-body">` +
      `<p class="sdc-cartoteca__message text-center text-muted fst-italic mb-0">${esc(msg)}</p>` +
      `</div>`
    );
  }

  function buildFieldsHTML(fields) {
    const rows = fields
      .map(
        (f) =>
          `<tr>` +
          `<th scope="row" class="sdc-cartoteca__field-label">${esc(f.label)}</th>` +
          `<td class="sdc-cartoteca__field-value">${esc(f.value)}</td>` +
          `</tr>`,
      )
      .join('');
    // La región desplazable es focusable (tabindex=0) con role="region" y
    // nombre, para poder recorrerla con teclado (WCAG 2.1.1).
    return (
      `<div class="sdc-cartoteca__fields-scroll card-body p-0" tabindex="0"` +
      ` role="region" aria-label="${Drupal.t('Datos del mapa')}">` +
      `<table class="sdc-cartoteca__fields table table-sm table-striped mb-0"` +
      ` aria-label="${Drupal.t('Datos del mapa')}">` +
      `<tbody>${rows}</tbody></table>` +
      `</div>`
    );
  }

  // ---------------------------------------------------------------------------
  // Behavior: carga asíncrona
  // ---------------------------------------------------------------------------

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
            body.innerHTML = buildMessageHTML(Drupal.t('Esperando selección'));
            return;
          }

          body.innerHTML = buildLoadingHTML();
          Drupal.announce(Drupal.t('Cargando datos del mapa…'));

          try {
            const response = await fetch(url);
            if (reqId !== currentRequest) return;
            if (!response.ok) throw new Error(response.status);
            const data = await response.json();
            if (reqId !== currentRequest) return;

            if (!Array.isArray(data) || !data.length) {
              const msg = Drupal.t(
                'No se han encontrado datos para ese número de control.',
              );
              body.innerHTML = buildMessageHTML(msg);
              Drupal.announce(msg);
              return;
            }

            const fields = normalizeFields(data[0]);
            if (!fields.length) {
              const msg = Drupal.t(
                'No hay información disponible para este mapa.',
              );
              body.innerHTML = buildMessageHTML(msg);
              Drupal.announce(msg);
              return;
            }

            body.innerHTML = buildFieldsHTML(fields);
            Drupal.announce(Drupal.t('Datos del mapa cargados.'));
          } catch (_err) {
            if (reqId !== currentRequest) return;
            const msg = Drupal.t(
              'El servicio de la Cartoteca no está disponible en este momento.',
            );
            body.innerHTML = buildMessageHTML(msg);
            Drupal.announce(msg);
          }
        });
      });
    },
  };
})(Drupal, once);
