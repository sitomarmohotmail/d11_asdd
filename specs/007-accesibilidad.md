# Accesibilidad · spec 007 — Módulo sdc_cartoteca

- Fecha: 2026-11-21
- Versión de la spec: 1.5 (estado `aprobada`)
- Norma: WCAG 2.2 AA
- Resultado: BLOQUEADA
- Hallazgos: 2 ALTOS · 4 MEDIOS · 4 BAJOS · 1 OBS

## Alcance
Revisión estática de `components/*/*.{twig,css,js}`, `js/cartoteca-search-form.js`, `css/`, `src/Form/CartotecaSearchForm.php` y tests Functional. Axe sobre `https://d11asdd.local/node/1` (1280 px): 0 violaciones, pero **sin el bloque** (no se renderiza), así que no vale como evidencia.

## Hallazgos
### A11Y-001 · ALTA · CA-A1 / revisión automática no ejecutada
- **Evidencia:** `drush config:get block.block.d11asdd_theme_cartotecaieca` → "does not exist" (existe en `config/sync`, no importado). En `/node/1` no hay clases `sdc-cartoteca`. Además `npm run a11y` sobre `http://d11asdd.local` falla con `ERR_CERT_AUTHORITY_INVALID` (redirige a https, el script no ignora el certificado).
- **Corrección:** `drush cim`, y ejecutar `check.sh` con la URL https (o añadir `ignoreHTTPSErrors`/`--ignore-certificate-errors` en `scripts/a11y/axe-check.mjs`). Axe debe repasar también los estados tras búsqueda, error y vacío.

### A11Y-002 · ALTA · 4.1.3 Mensajes de estado · `cartoteca-*.js`
- **Evidencia:** `buildLoadingHTML`, `buildMessageHTML` y `buildInitialHTML` hacen `body.innerHTML = …` sin `Drupal.announce()` ni `aria-live` persistente. El `role="status"` del spinner se inserta junto con su contenido (los lectores suelen no anunciarlo). Resultados, "No se han encontrado datos…", "servicio no disponible" y el regreso silencioso a la imagen por defecto en error (slider) no se anuncian.
- **Corrección:** `Drupal.announce(...)` ("Cargando…", "Datos del mapa cargados: N imágenes", mensajes de error/vacío) en ambos loaders, o `aria-live="polite"` en un contenedor que exista en el Twig. Mostrar también el error en el slider (hoy solo lo muestra info).

### A11Y-003 · MEDIA · 4.1.2 Nombre, función, valor · `cartoteca-image-slider.twig`, `.js`
- **Evidencia:** ni el Twig ni `buildLoadedHTML` incluyen `aria-roledescription="carousel"` (requisito RA de la spec), ni `role="group"`/`aria-label` en el contenedor, ni `aria-roledescription="slide"` en las diapositivas. El cambio de diapositiva (flechas/miniaturas) no se anuncia (el `alt` "Vista N del mapa" no indica posición "N de M").
- **Corrección:** `role="region"` + `aria-roledescription="carousel"` + `aria-label` en el wrapper; `role="group" aria-roledescription="slide" aria-label="N de M"` en cada slide; `aria-live="polite"` en el escenario o `Drupal.announce` al cambiar.

### A11Y-004 · MEDIA · 2.1.1 Teclado · `cartoteca-mapa-info.css:3-5`
- **Evidencia:** `.sdc-cartoteca__info {overflow-y:auto; max-height:70vh}` y la tabla en `div.overflow-auto`, sin `tabindex="0"`: una región desplazable sin foco no se puede recorrer con teclado en navegadores que no la hacen enfocable (axe `scrollable-region-focusable`).
- **Corrección:** quitar el `max-height`/overflow del card o dar al contenedor `tabindex="0" role="region" aria-label="Datos del mapa"`.

### A11Y-005 · MEDIA · 1.4.3 Contraste · `cartoteca-mapa-info.css:19`
- **Evidencia:** `th.sdc-cartoteca__field-label` usa `var(--bs-secondary,#6c757d)` a 0,8 rem sobre `table-striped`. #6c757d sobre blanco = 4,69:1, sobre fila rayada (~#f2f2f2) ≈ 4,2:1 (<4,5). Confirmar con valores reales del tema.
- **Corrección:** usar `var(--bs-body-color)` o `#495057` (≈7:1).

### A11Y-006 · MEDIA · Tests permanentes
- `tests/src/Functional/CartotecaFunctionalTest.php` no comprueba CA-A2 (`<h2>`), ni CA-A3 (`aria-label` + `th scope="row"` generados por JS: requiere test JS/WebDriver o mover la tabla a Twig), ni `aria-roledescription`, ni label del campo. Solo cubre `aria-invalid` del campo.

## Cumple (revisado)
Títulos `<h2>` en ambos Twig; label asociado vía Form API; error inline con `role="alert"`, `aria-invalid`, `aria-describedby` y foco al campo; flechas con `aria-label` y glifo `aria-hidden`; miniaturas `<button type=button>` con `aria-pressed` y `alt`; `<select>` con `aria-label`; `prefers-reduced-motion`; foco con outline 3 px primario en flechas; `th scope="row"` y `aria-label` en tabla (en JS).

## Observaciones
- OBS: el formulario no funciona sin JS (`novalidate` + `preventDefault`); aceptable, sin acción.

## BAJAS (a `specs/pendientes.md`)
- Miniatura activa y miniatura con foco usan el mismo outline (`.sdc-cartoteca__thumb--active` / `:focus-visible`); añadir borde/relleno distinto para el estado activo.
- Flechas con `opacity:.85` sobre imagen: comprobar contraste del glifo ≥ 3:1 (1.4.11).
- Enlace "Descargar" sin contexto; `Descargar imagen (<resolución>)`. El atributo `download` se ignora en cross-origin.
- Etiqueta del campo incluye el ejemplo "IECA1988118769" y además hay placeholder repetido; `aria-describedby` del JS sobrescribe descripciones previas.

## Comprobación manual (para la persona)
- [x] Teclado en el bloque: Tab recorre campo → Buscar → Limpiar → flechas → miniaturas → selector → Descargar; Intro/Espacio activan; sin trampas; Mayús+Tab inverso.
- [x] Enviar vacío: el foco vuelve al campo y Orca lee "Este campo es obligatorio".
- [x] Buscar `IECA1988118769`: Orca anuncia "Cargando" y el resultado; probar un número inexistente y con red cortada.
- [x] Cambiar de imagen con flechas/miniaturas: Orca anuncia la posición; el foco no se pierde.
- [x] Orca: tabla "Datos del mapa" lee cabecera de fila + valor; orden de lectura h2 → contenido.
- [x] Foco visible en todos los controles (≥ 3:1) y sin quedar tapado.
- [x] Zoom 200 % y 400 % / ancho 320 px: componentes apilados, sin scroll horizontal ni recortes; se puede desplazar la tabla con teclado.
- [x] `prefers-reduced-motion: reduce` activado: sin transiciones.
- [x] Enlace de salto al contenido sigue funcionando con el bloque en la página.
