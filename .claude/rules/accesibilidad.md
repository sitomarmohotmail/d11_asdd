# Accesibilidad: WCAG 2.2 nivel AA

Se aplica a **toda spec que añada o cambie HTML, Twig, CSS o JS**: el tema
`d11asdd_theme`, módulos, componentes SDC, bloques, formularios y
configuración que altere la salida (menús, bloques, formatos de texto).

El objetivo es cumplir **WCAG 2.2 AA** (incluye 2.1 AA, la que exige
EN 301 549 / RD 1112/2018). Lo revisa el agente `asdd-accesibilidad`.

## Requisito obligatorio en la spec
Toda spec con interfaz incluye en *Requisitos*:

> **RA** *(DEBE)* — El sistema deberá cumplir WCAG 2.2 nivel AA en todo lo que
> añade o modifica esta spec.

y, en *Criterios de aceptación*, los criterios EARS concretos que apliquen
(ver lista de abajo). Si una spec con interfaz no tiene RA, `asdd-validacion`
lo marca como ALTA.

## Lista de comprobación (Drupal + Bootstrap 5)

### Estructura y semántica
- `<html lang>` correcto (3.1.1). Partes en otro idioma con `lang` (3.1.2).
- Regiones: `header`, `nav` (con nombre si hay varias), `main` único, `footer` (1.3.1).
- Un `<h1>` por página; encabezados sin saltos de nivel (1.3.1, 2.4.6).
- `<title>` de página descriptivo (2.4.2).
- Listas, tablas y botones con el elemento HTML correcto; nada de `div`/`span`
  clicables (4.1.2).
- En Twig: imprimir siempre `{{ attributes }}` y usar `{{ attributes.addClass() }}`
  en lugar de reescribir el elemento; no eliminar los atributos ARIA de core.

### Teclado y foco
- Todo es operable con teclado, sin trampas de foco (2.1.1, 2.1.2).
- Enlace "Saltar al contenido principal" funcional (2.4.1). Bootstrap5 lo trae
  en `html.html.twig`; no romperlo.
- Orden de foco lógico (2.4.3). Nunca `tabindex` > 0.
- Foco visible siempre (2.4.7): prohibido `outline: none` / `outline: 0` sin
  un indicador alternativo con contraste ≥ 3:1.
- El elemento con foco no queda tapado por cabeceras fijas o banners (2.4.11).

### Color y contraste
- Texto normal ≥ 4,5:1; texto grande (≥ 24 px o ≥ 18,66 px negrita) ≥ 3:1 (1.4.3).
- Bordes de campos, iconos con significado e indicadores de foco ≥ 3:1 (1.4.11).
- El color no es el único medio de transmitir información: los enlaces dentro
  de un texto se distinguen también por subrayado u otro rasgo (1.4.1).
- Al cambiar variables o colores de Bootstrap en `style.css`, comprobar el
  contraste de cada combinación texto/fondo resultante.

### Contenido no textual
- Imágenes informativas con `alt` descriptivo; decorativas con `alt=""` (1.1.1).
- El logo enlazado tiene un nombre accesible (p. ej. "Inicio – D11asdd").
- Iconos solo visuales con `aria-hidden="true"`; botones de solo icono con
  texto en `visually-hidden` o `aria-label`.

### Formularios
- Cada campo tiene `<label>` asociado (1.3.1, 3.3.2). Usar la Form API de
  Drupal, que lo hace por defecto; no ocultar etiquetas sin `visually-hidden`.
- Errores identificados en texto y asociados al campo (3.3.1, 3.3.3).
- `autocomplete` en campos de datos personales (1.3.5).
- Autenticación sin pruebas cognitivas (3.3.8).

### Adaptación y movimiento
- Reflujo a 320 px CSS sin scroll horizontal (1.4.10).
- Zoom de texto al 200 % sin pérdida de contenido (1.4.4).
- Espaciado de texto ajustable sin cortes (1.4.12): no fijar alturas a bloques de texto.
- Animaciones respetan `prefers-reduced-motion` (2.3.3 es AAA, pero se aplica
  como buena práctica); nada parpadea más de 3 veces por segundo (2.3.1).
- Objetivos táctiles ≥ 24 × 24 px o con separación suficiente (2.5.8).

### JavaScript y contenido dinámico
- Componentes interactivos con rol, nombre y estado (`aria-expanded`,
  `aria-controls`, `aria-current`…) actualizados por JS (4.1.2).
- Mensajes de estado anunciados con `Drupal.announce()` o regiones `aria-live`
  (4.1.3).
- Contenido que aparece al pasar el ratón o al enfocar: se puede cerrar con
  Esc, se puede pasar el ratón por encima y no desaparece solo (1.4.13).
- Usar `once()` y `Drupal.behaviors`; no robar el foco al cargar la página.

## Cómo se verifica
| Nivel | Herramienta | Quién |
|---|---|---|
| Estático | Lectura de Twig, CSS, JS y config | `asdd-accesibilidad` |
| Automático | `npm run a11y -- URL…` (axe-core, etiquetas WCAG 2.0/2.1/2.2 A y AA) | `asdd-accesibilidad` |
| Tests permanentes | PHPUnit Functional: `lang`, enlace de salto, regiones, `h1` único, `alt`, etiquetas | Tests de la spec |
| Manual | Teclado, lector de pantalla (Orca), zoom 200 %, 320 px | La persona, con la lista que genera el agente |

Las herramientas automáticas detectan solo una parte de los problemas: la
revisión manual es obligatoria antes de marcar la spec como `implementada`.

## Origen de los problemas
- En código propio (`web/themes/custom`, `web/modules/custom`): se corrigen en la spec.
- En core o contrib (p. ej. Bootstrap5): se documentan como observación (OBS);
  si afectan a AA, se corrigen sobrescribiendo la plantilla o el CSS en el
  tema hijo, o se abre una spec específica.
