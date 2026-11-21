/**
 * @file
 * Lógica pura del panel de información de la Cartoteca IECA.
 *
 * Parseo de campos y constructores de HTML, sin acceso al DOM, publicados en
 * Drupal.sdcCartoteca.info para el behavior y para `node --test`
 * (ver tests/js/mapa-info.test.mjs).
 *
 * @param {Drupal} Drupal
 *   Drupal global object.
 */
(function sdcCartotecaInfoLogic(Drupal) {
  const SKIP_FIELDS = [
    'field_imagenes_media',
    'field_imagen_destacada_media',
    'field_imagenes_b_n',
  ];

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
   * Convierte un valor a cadena (array → vacío).
   *
   * @param {*} v
   *   Valor de entrada.
   *
   * @return {string}
   *   Cadena recortada, o '' si es un array.
   */
  function str(v) {
    return Array.isArray(v) ? '' : String(v ?? '').trim();
  }

  /**
   * Deriva una etiqueta legible a partir de la clave del campo.
   *
   * @param {string} key
   *   Clave del campo (field_...).
   *
   * @return {string}
   *   Etiqueta legible.
   */
  function fieldLabel(key) {
    let name = key.slice(6);
    name = name.replace(/^\d+_/, '');
    return name.charAt(0).toUpperCase() + name.slice(1).replace(/_/g, ' ');
  }

  /**
   * Extrae etiquetas de registros de taxonomía/referencia.
   *
   * @param {Array} records
   *   Registros del campo.
   *
   * @return {Array<string>}
   *   Lista de etiquetas no vacías.
   */
  function extractLabels(records) {
    return records
      .map((r) => {
        if (typeof r === 'object' && r !== null) return str(r.label ?? '');
        return str(r);
      })
      .filter(Boolean);
  }

  /**
   * Extrae subcampos MARC de registros compuestos.
   *
   * @param {Array} records
   *   Registros del campo.
   * @param {string} prefix
   *   Prefijo del campo (field_NNN).
   * @param {Array<string>} only
   *   Sufijos a incluir; vacío = todos.
   *
   * @return {Array<string>}
   *   Lista de valores concatenados no vacíos.
   */
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

  /**
   * Detecta el tipo de campo y extrae su valor mostrable.
   *
   * @param {string} key
   *   Clave del campo.
   * @param {Array} records
   *   Registros del campo.
   *
   * @return {?string}
   *   Valor mostrable, o null si no aplica o está vacío.
   */
  function detectAndExtract(key, records) {
    if (!Array.isArray(records) || !records.length) return null;
    const first = records[0];
    if (typeof first !== 'object' || first === null) {
      return str(String(first)) || null;
    }
    if ('original-view' in first || 'big' in first) return null;
    if ('label' in first) {
      const vals = extractLabels(records);
      return vals.length ? vals.join('; ') : null;
    }
    if ('value' in first && !Array.isArray(first.value)) {
      return str(first.value) || null;
    }
    const match = key.match(/^(field_\d+)_/);
    if (match) {
      const vals = extractSubfields(records, match[1]);
      return vals.length ? vals.join('; ') : null;
    }
    return null;
  }

  /**
   * Normaliza el registro de la API a pares etiqueta–valor no vacíos.
   *
   * @param {object} raw
   *   Registro de mapa de la API (data[0]).
   *
   * @return {Array<{label: string, value: string}>}
   *   Campos con valor, en el orden del JSON.
   */
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

  /**
   * Clasifica la respuesta de la API para el panel de datos.
   *
   * @param {boolean} ok
   *   Si la respuesta HTTP fue correcta.
   * @param {*} data
   *   Cuerpo JSON ya parseado.
   *
   * @return {{status: string, message: ?string, fields: ?Array}}
   *   status: 'loaded' | 'not_found' | 'no_fields' | 'error'.
   */
  function classifyResponse(ok, data) {
    if (!ok) {
      return {
        status: 'error',
        message: Drupal.t(
          'El servicio de la Cartoteca no está disponible en este momento.',
        ),
        fields: null,
      };
    }
    if (!Array.isArray(data) || !data.length) {
      return {
        status: 'not_found',
        message: Drupal.t(
          'No se han encontrado datos para ese número de control o el mapa no existe.',
        ),
        fields: null,
      };
    }
    const fields = normalizeFields(data[0]);
    if (!fields.length) {
      return {
        status: 'no_fields',
        message: Drupal.t('No hay información disponible para este mapa.'),
        fields: null,
      };
    }
    return { status: 'loaded', message: null, fields };
  }

  /**
   * Construye el HTML del estado de carga (spinner).
   *
   * @return {string}
   *   HTML del spinner.
   */
  function buildLoadingHTML() {
    return (
      `<div class="sdc-cartoteca__info-body card-body d-flex align-items-center justify-content-center">` +
      `<div class="spinner-border text-secondary" role="status">` +
      `<span class="visually-hidden">${Drupal.t('Cargando…')}</span>` +
      `</div></div>`
    );
  }

  /**
   * Construye el HTML de un mensaje (estado inicial, vacío o error).
   *
   * @param {string} msg
   *   Texto del mensaje.
   *
   * @return {string}
   *   HTML del mensaje.
   */
  function buildMessageHTML(msg) {
    return (
      `<div class="sdc-cartoteca__info-body card-body">` +
      `<p class="sdc-cartoteca__message text-center text-muted fst-italic mb-0">${esc(msg)}</p>` +
      `</div>`
    );
  }

  /**
   * Construye la tabla de metadatos del mapa.
   *
   * @param {Array<{label: string, value: string}>} fields
   *   Campos a mostrar.
   *
   * @return {string}
   *   HTML de la tabla accesible (aria-label + th scope="row").
   */
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
    return (
      `<div class="sdc-cartoteca__fields-scroll card-body p-0" tabindex="0"` +
      ` role="region" aria-label="${Drupal.t('Datos del mapa')}">` +
      `<table class="sdc-cartoteca__fields table table-sm table-striped mb-0"` +
      ` aria-label="${Drupal.t('Datos del mapa')}">` +
      `<tbody>${rows}</tbody></table>` +
      `</div>`
    );
  }

  Drupal.sdcCartoteca = Drupal.sdcCartoteca || {};
  Drupal.sdcCartoteca.info = {
    str,
    esc,
    fieldLabel,
    extractLabels,
    extractSubfields,
    detectAndExtract,
    normalizeFields,
    classifyResponse,
    buildLoadingHTML,
    buildMessageHTML,
    buildFieldsHTML,
  };
})(Drupal);
