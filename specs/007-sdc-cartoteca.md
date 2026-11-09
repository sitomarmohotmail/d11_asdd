---
id: 007
titulo: Módulo sdc_cartoteca — bloque buscador de mapas con componentes SDC
version: 1.5
estado: aprobada
nivel: L
tipo: modulo
proyecto: sdc_cartoteca
---

# 007 · Módulo sdc_cartoteca — bloque buscador de mapas con componentes SDC

## Contexto

El proyecto necesita un módulo personalizado (`sdc_cartoteca`) que exponga un
bloque Drupal colocable en cualquier región. El bloque contiene un pequeño
formulario de búsqueda por número de control IECA; al enviarlo consulta la API
REST de la Cartoteca de la Junta de Andalucía y presenta los resultados en dos
componentes SDC del propio módulo: un slider de imágenes y una ficha de
metadatos del mapa.

La imagen por defecto (estado inicial, antes de cualquier búsqueda) es
`imagen-no-disponible.jpg`, descargada del tema Terra de la Cartoteca y
empaquetada en el módulo.

## Requisitos

- **R1** *(DEBE)* — El módulo deberá proporcionar un bloque Drupal
  (`plugin_id: sdc_cartoteca`) colocable en cualquier región.
- **R2** *(DEBE)* — El bloque deberá incluir un campo de texto obligatorio de
  máximo 15 caracteres y un botón "Buscar" para introducir el número de
  control; si se envía vacío, el sistema deberá mostrar el error de validación
  **inline**, en rojo y directamente debajo del campo, sin notificación en la
  barra superior de mensajes. Requiere el módulo core `inline_form_errors`.
- **R3** *(DEBE)* — Al enviar el formulario, el sistema deberá convertir el
  valor a mayúsculas, construir la URL
  `https://ws089.juntadeandalucia.es/institutodeestadisticaycartografia/cartoteca/api/v1/mapa-apitest?_format=json&numero_de_control={VALOR}`
  y pasarla a los componentes SDC mediante el evento personalizado
  `cartoteca:load`. La consulta a la API la realiza el JS de cada componente
  con `fetch`; la URL base se expone al cliente vía
  `drupalSettings.sdc_cartoteca.apiBase`.
- **R4** *(DEBE)* — El módulo deberá proporcionar el componente SDC
  `sdc_cartoteca:cartoteca-image-slider` que muestre: todas las imágenes
  disponibles en la suma de `field_imagen_destacada_media` (un único elemento
  con variantes de tamaño), `field_imagenes_media` (multielemento, cada uno
  con variantes de tamaño) y `field_imagenes_b_n` (multielemento B/N, ídem),
  con slider de flechas y miniaturas cuando haya más de una imagen,
  selector de resolución de descarga (original, grande, mediana, pequeña) y
  botón "Descargar imagen".
- **R5** *(DEBE)* — El módulo deberá proporcionar el componente SDC
  `sdc_cartoteca:cartoteca-mapa-info` que muestre los campos de metadatos
  del mapa (ver lista en *Plan técnico*) como pares etiqueta–valor; los campos
  cuyo valor venga vacío, `null` o array vacío en el JSON no se renderizarán
  (ni etiqueta ni valor).
- **R6** *(DEBE)* — En la carga inicial del bloque, el componente de imágenes
  deberá mostrar `imagen-no-disponible.jpg` y el componente de datos deberá
  mostrar el mensaje "Esperando selección".
- **R7** *(DEBE)* — Tras recibir el evento `cartoteca:load`, cada componente
  SDC deberá hacer `fetch` de la URL, parsear la respuesta y actualizar su
  contenido sin recargar la página.
- **R8** *(DEBE)* — Si la API devuelve un error HTTP o un array vacío `[]`
  (mapa no encontrado), el sistema deberá restaurar la imagen por defecto en
  el componente de imágenes y mostrar el mensaje "No se han encontrado datos
  para ese número de control o el mapa no existe" en el componente de datos.
- **R9** *(DEBE)* — Los componentes deberán usar las clases de utilidad del
  tema hijo `d11assd_theme` (Bootstrap 5) y ser responsivos a 320 px y 1280 px
  sin desbordamiento horizontal.
- **RA** *(DEBE)* — El sistema deberá cumplir WCAG 2.2 nivel AA en todo lo que
  añade o modifica esta spec: semántica HTML correcta (encabezados `<h*>`,
  roles ARIA, `scope` en tablas, `aria-label`/`aria-labelledby`), contraste
  suficiente, foco visible, navegación por teclado y reflujo a 320 px.

## Criterios de aceptación (EARS)

- **CA1** *(R1 · Kernel)* — **Cuando** el módulo `sdc_cartoteca` está
  habilitado, **el sistema deberá** registrar un bloque con `plugin_id`
  `sdc_cartoteca` visible en `/admin/structure/block`.
- **CA2** *(R2 · Functional)* — **Cuando** el bloque está colocado y se
  renderiza, **el sistema deberá** mostrar un `<input type="text" required>`
  con `maxlength="15"` y un `<button type="submit">Buscar</button>`, ambos con
  etiquetas visibles y correctamente asociadas.
- **CA2b** *(R2 · Functional)* — **Si** el formulario se envía con el campo
  vacío, **entonces el sistema deberá** mostrar el mensaje de error de
  validación directamente debajo del campo (inline, no en la barra superior)
  sin llamar a la API, tanto en envío normal como en AJAX.
- **CA3** *(R3 · Kernel)* — **Cuando** se envía el formulario con el valor
  `ieca1988118769` (minúsculas), **el sistema deberá** convertirlo a
  `IECA1988118769` (mayúsculas) antes de llamar a la API y retornar un array
  con los campos parseados.
- **CA3b** *(R3 · Functional)* — **Cuando** la página carga, **el sistema
  deberá** incluir la URL base de la API en
  `drupalSettings.sdc_cartoteca.apiBase` (en el `<script>` de Drupal), no en
  atributos de valor de campos de formulario ni en el HTML renderizado por PHP.
- **CA4** *(R4 · Functional)* — **Cuando** la API devuelve datos, **el sistema
  deberá** construir la lista de diapositivas concatenando: primero
  `field_imagen_destacada_media`, luego cada elemento de `field_imagenes_media`
  y finalmente cada elemento de `field_imagenes_b_n`; la imagen activa inicial
  será la primera de esa lista en resolución `medium`.
- **CA4b** *(R4 · Unit)* — **Si** los tres campos de imagen vienen vacíos
  o ausentes en el JSON, **entonces el sistema deberá** mostrar
  `imagen-no-disponible.jpg` como única diapositiva, sin controles de slider.
- **CA5** *(R4 · Functional)* — **Cuando** la lista de diapositivas tiene más
  de una imagen, **el sistema deberá** mostrar controles de slider
  (flechas anterior/siguiente y miniaturas) operables con teclado.
- **CA6** *(R4 · Functional)* — **Cuando** el usuario selecciona una
  diapositiva en el slider, **el sistema deberá** mostrar un selector de
  resolución con las claves de tamaño disponibles en esa imagen activa
  (no siempre las de `field_imagen_destacada_media`) y un enlace de descarga
  que apunte a la URL de la resolución seleccionada.
- **CA7** *(R5 · Functional)* — **Cuando** la API devuelve datos, **el sistema
  deberá** renderizar el componente `cartoteca-mapa-info` mostrando solo los
  campos con valor no vacío; los campos vacíos, `null` o array vacío no
  generarán ningún elemento HTML (ni etiqueta ni valor).
- **CA7c** *(R5 · Visual)* — **Cuando** `cartoteca-mapa-info` muestra los
  metadatos, **el sistema deberá** presentarlos en una tabla con cabecera
  "Datos del mapa" y filas de fondo intercalado (Bootstrap `.table-striped`)
  para facilitar la lectura.
- **CA7d** *(R4 · Visual)* — **El sistema deberá** mostrar el título
  "Imágenes del mapa" en la cabecera del componente `cartoteca-image-slider`,
  visible en todo momento (estado inicial, cargando y cargado).
- **CA7b** *(R5 · Unit)* — **Cuando** `CartotecaApiService` normaliza la
  respuesta JSON, **el sistema deberá** eliminar del array resultante todas
  las claves cuyo valor sea `null`, cadena vacía `""` o array vacío `[]`.
- **CA8** *(R6 · Functional)* — **Cuando** el bloque se renderiza sin haber
  realizado ninguna búsqueda, **el sistema deberá** mostrar `imagen-no-disponible.jpg`
  en el componente de imágenes y el texto "Esperando selección" en el
  componente de datos.
- **CA9** *(R7 · Functional)* — **Cuando** el formulario se envía, **el sistema
  deberá** disparar el evento `cartoteca:load` en cada componente SDC; el
  componente deberá hacer `fetch` de la URL y actualizar su contenido sin
  recargar la página.
- **CA10a** *(R8 · Unit)* — **Si** la API devuelve un array vacío `[]`,
  **entonces el sistema deberá** tratar la respuesta como "mapa no encontrado",
  devolver la imagen por defecto en el componente de imágenes y mostrar el
  mensaje "No se han encontrado datos para ese número de control o el mapa no
  existe" en el componente de datos.
- **CA10b** *(R8 · Unit)* — **Si** la API devuelve un código HTTP ≠ 200,
  **entonces el sistema deberá** devolver la imagen por defecto y mostrar el
  mensaje "El servicio de la Cartoteca no está disponible en este momento".
- **CA11** *(R9 · check.sh)* — **Cuando** el bloque se renderiza a 320 px de
  ancho, **el sistema deberá** mostrar los dos componentes apilados verticalmente
  sin desbordamiento horizontal.
- **CA-A1** *(RA · check.sh)* — **Cuando** se ejecute `scripts/check.sh` sobre
  la página que contiene el bloque, **el sistema deberá** dar 0 problemas de
  axe, reflujo y foco a 1280 y 320 px.
- **CA-A2** *(RA · Functional)* — **Cuando** los componentes se renderizan,
  **el sistema deberá** mostrar el título de cada componente dentro de un
  elemento `<h2>` (WCAG 1.3.1 / 2.4.6): "Imágenes del mapa" en
  `cartoteca-image-slider` y "Datos del mapa" en `cartoteca-mapa-info`.
- **CA-A3** *(RA · Functional)* — **Cuando** `cartoteca-mapa-info` renderiza
  la tabla de metadatos, **el sistema deberá** incluir `aria-label="Datos del
  mapa"` en el elemento `<table>` y usar `<th scope="row">` para cada etiqueta
  de campo (WCAG 1.3.1).

## Fuera de alcance

- Almacenamiento local de resultados (caché de búsquedas anteriores).
- Pestañas Marc21 / ISBD / Legal del detalle.
- Formulario de solicitud de mapa.
- Georreferenciación o integración con mapas interactivos.
- Integración con el sistema de autenticación de Drupal.

## Plan técnico

### Estructura de archivos

```
web/modules/custom/sdc_cartoteca/
  sdc_cartoteca.info.yml
  sdc_cartoteca.libraries.yml
  src/
    Plugin/Block/CartotecaBlock.php        ← BlockBase + FormInterface
    Form/CartotecaSearchForm.php           ← formulario; submit interceptado en JS
    Service/CartotecaApiService.php        ← Guzzle, parseo JSON → array (referencia)
  components/
    cartoteca-image-slider/
      cartoteca-image-slider.component.yml
      cartoteca-image-slider.twig
      cartoteca-image-slider.css
      cartoteca-image-slider.js            ← slider + loader (fetch + parseo)
    cartoteca-mapa-info/
      cartoteca-mapa-info.component.yml
      cartoteca-mapa-info.twig
      cartoteca-mapa-info.css
      cartoteca-mapa-info.js              ← loader (fetch + parseo de campos)
  images/
    imagen-no-disponible.jpg               ← imagen por defecto (Terra / CC)
  templates/
    block--sdc-cartoteca.html.twig
  tests/src/
    Unit/CartotecaApiServiceTest.php
    Kernel/CartotecaBlockTest.php
    Functional/CartotecaFunctionalTest.php
```

### Campos mapeados desde el JSON a `cartoteca-mapa-info`

| Etiqueta UI         | Campo JSON                                      |
|---------------------|-------------------------------------------------|
| Nº de control       | `field_001_numero_de_control`                   |
| Título              | `field_245_mencion_del_titulo.a` + `.b`         |
| Mención responsab.  | `field_245_mencion_del_titulo.c`                |
| Autor               | `field_autor`                                   |
| Período             | `field_906_fecha_completa.b` / `field_fecha_inicio`–`field_fecha_fin` |
| Siglo               | `field_siglo_de_inicio`–`field_siglo_de_fin`    |
| Lugar               | `field_lugar`                                   |
| Comunidad autónoma  | `field_comunidad_autonoma`                      |
| Provincia           | `field_provincia`                               |
| Municipio           | `field_municipio`                               |
| País                | `field_pais_web`                                |
| Escala              | `field_escala_numerica` / `field_255_datos_matematicos_cartograficos.a` |
| Descripción física  | `field_300_descripcion_fisica.a` + `.b` + `.c`  |
| Notas generales     | `field_500_notas_generales.a`                   |
| Idioma              | `field_idioma_web`                              |
| Categoría           | `field_categoria`                               |
| Fuente              | `field_fuente_de_adquisicion`                   |

### Imágenes en `cartoteca-image-slider`

Lista de diapositivas (en orden):
1. `field_imagen_destacada_media` — un único objeto con claves `original-view`, `big`, `medium`, `small`
2. Cada elemento de `field_imagenes_media` — mismo esquema de tamaños
3. Cada elemento de `field_imagenes_b_n` — mismo esquema de tamaños

- Imagen activa inicial: primera diapositiva en resolución `medium`
- Selector de resolución: claves `original-view`, `big`, `medium`, `small`
- Si hay más de una diapositiva → slider con `aria-roledescription="carousel"`,
  flechas con `aria-label` y miniaturas como lista de `<button>`

### Carga asíncrona (client-side fetch)

PHP renderiza la estructura inicial de los componentes con `url=''`.
El formulario lleva `novalidate`; la validación del campo la gestiona JS.

Al hacer submit, `cartoteca-search-form.js` construye la URL con
`drupalSettings.sdc_cartoteca.apiBase` y despacha
`CustomEvent('cartoteca:load', { detail: { url } })` en cada componente SDC
(`.sdc-cartoteca__slider` y `.sdc-cartoteca__info`).

Cada componente escucha su propio `cartoteca:load`, muestra un spinner,
hace `fetch(url)`, parsea el JSON y vuelca el HTML renderizado.
El botón "Limpiar" despacha `cartoteca:load` con `url=''`, restaurando el
estado inicial sin recarga.

El parseo del JSON (imágenes y campos de metadatos) reside en el JS de cada
componente; `CartotecaApiService.php` se mantiene para los tests unitarios
existentes pero ya no lo usa el formulario.

## Tareas

- [x] Tests (verde)
- [x] Imagen por defecto descargada en `images/`
- [x] `CartotecaApiService` (parseo + tests unitarios)
- [x] `CartotecaSearchForm` (Form API; submit interceptado en JS)
- [x] `CartotecaBlock` (bloque + wrappers)
- [x] SDC `cartoteca-image-slider` (Twig + CSS + JS; loader fetch)
- [x] SDC `cartoteca-mapa-info` (Twig + CSS + JS; loader fetch)
- [ ] `scripts/check.sh --spec specs/007-sdc-cartoteca.md http://d11assd.local/` en verde

## Trazabilidad

| Requisito | Criterio | Test |
|-----------|----------|------|
| R1 | CA1 | `Kernel\CartotecaBlockTest::testBlockExists` |
| R2 | CA2 | `Functional\CartotecaFunctionalTest::testFormRendered` |
| R2 | CA2b | `Functional\CartotecaFunctionalTest::testFormRequiredValidation` |
| R3 | CA3 | `Unit\CartotecaApiServiceTest::testApiCall` / `Kernel\CartotecaBlockTest::testApiParsing` |
| R3 | CA3b | `Functional\CartotecaFunctionalTest::testApiUrlInDrupalSettings` |
| R4 | CA4 | `Functional\CartotecaFunctionalTest::testSliderRendered` |
| R4 | CA4b | `Unit\CartotecaApiServiceTest::testNoImagesUsesDefault` |
| R4 | CA5 | `Functional\CartotecaFunctionalTest::testSliderControls` |
| R4 | CA6 | `Functional\CartotecaFunctionalTest::testResolutionSelector` |
| R5 | CA7 | `Functional\CartotecaFunctionalTest::testInfoFields` |
| R5 | CA7b | `Unit\CartotecaApiServiceTest::testEmptyFieldsAreStripped` |
| R6 | CA8 | `Functional\CartotecaFunctionalTest::testInitialState` |
| R7 | CA9 | `Functional\CartotecaFunctionalTest::testAjaxUpdate` |
| R8 | CA10a | `Unit\CartotecaApiServiceTest::testEmptyArrayIsNotFound` |
| R8 | CA10b | `Unit\CartotecaApiServiceTest::testHttpErrorHandling` |
| R9 | CA11 | `check.sh` (axe 320 px) |
| RA | CA-A1 | `check.sh` (axe + reflujo + foco) |

## Puertas

- Validación: 2026-11-09 · 1 BLOQUEANTE + 1 ALTO + 3 MEDIOS corregidos · 2 BAJOS → pendientes.md
- Seguridad: _(pendiente — `asdd-seguridad`)_
- Accesibilidad: _(pendiente — `asdd-accesibilidad`)_
- QA: _(pendiente — `scripts/check.sh`)_
