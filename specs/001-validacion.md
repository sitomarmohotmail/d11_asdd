# Validación · spec 001 — Tema hijo de Bootstrap5

- Fecha: 2026-11-08
- Versión de la spec validada: 1.1 (tercera pasada)
- Resultado: SUPERADA CON AVISOS
- Hallazgos: 0 BLOQUEANTES · 0 ALTOS · 0 MEDIOS · 1 BAJO

## Resumen
La spec ya no tiene hallazgos BLOQUEANTES ni ALTOS. Los nueve hallazgos de la
segunda pasada están resueltos. AMB-007 lo está en lo esencial: se fijan las
regiones y solo queda un matiz de forma. DRU-002, el que bloqueaba, queda
cerrado. R10 y el plan técnico declaran ya `subtheme_folder`, `subtheme_name`
y `subtheme_machine_name` en el esquema. Lo he comprobado en el código:
`SettingsManager.php`, líneas 218–239, añade esos campos al formulario, y
`theme_settings_convert_to_config()` los guarda, como confirma el agente
principal. Además, todos los valores de la tabla de R10 (incluido `none`) son
opciones válidas de los `select` del formulario, así que CA7e («guardar sin
cambios») no fallará por una opción no permitida.

He vuelto a revisar la spec entera y solo he encontrado un defecto menor
nuevo, en CA-A4c. La validación sigue siendo en parte retrospectiva: R1–R9
están implementados en la v1.0 (`84bb516`), y lo que se valida es la v1.1, en
borrador.

## Hallazgos

### AMB-010 · BAJA · CA-A4c
- **Evidencia:** «**el sistema deberá** distinguir los enlaces del pie del texto que los rodea por algo más que el color (subrayado), aunque la regla `footer.footer-dark a` de Bootstrap5 lo quite (WCAG 1.4.1). Se corrige en `css/style.css`.»
- **Problema:** el paréntesis «(subrayado)» se puede leer de dos formas: como el único rasgo aceptado o como un ejemplo. Según cómo se lea, valdría o no otro rasgo (por ejemplo, negrita con un contraste de 3:1 respecto al texto). Además, la última frase es una instrucción de implementación metida en el criterio, no parte del comportamiento. No impide verificarlo, porque el plan técnico concreta el subrayado.
- **Corrección propuesta:** «**Mientras** `d11assd_theme.settings` tenga los valores de R10, **el sistema deberá** mostrar subrayados los enlaces de `<footer>` (`text-decoration: underline`), aunque la regla `footer.footer-dark a` de Bootstrap5 lo quite (WCAG 1.4.1).» Si se quiere que valga otro rasgo, escribirlo de forma explícita. La frase «Se corrige en `css/style.css`» ya está en el plan técnico y se puede quitar del criterio.

## Hallazgos previos

### Segunda pasada (BLOQUEADA)
| ID | Estado |
|---|---|
| DRU-002 (ALTA) | Resuelto: R10 y el plan técnico añaden al esquema `subtheme_folder`, `subtheme_name` y `subtheme_machine_name` (`string`) y explican por qué se guardan. No se incluyen en `config/install`. CA7e lo verifica en un test Functional con el esquema estricto. Comprobado en `SettingsManager.php` (líneas 218–239). |
| A11Y-001 (MEDIA) | Resuelto: hay un CA-A4c nuevo, y el plan técnico añade el subrayado de los enlaces del pie a `css/style.css`. Matiz de redacción en AMB-010. |
| EARS-004 (MEDIA) | Resuelto: CA7d (Kernel, instalación) y CA7e (Functional, guardado del formulario) están separados. La trazabilidad y el plan están actualizados. |
| ACT-002 (MEDIA) | Resuelto: CA13 fija el actor (visitante anónimo) y el disparador (credenciales incorrectas en `/user/login`). Sin `inline_form_errors` activo, el error se muestra con Messenger en el bloque de mensajes. |
| AMB-007 (BAJA) | Resuelto en lo esencial: se fijan las regiones de `d11assd_theme_help` (`content`) y `d11assd_theme_search_form` (`nav_additional`). La condición sigue escrita como «solo se crean si…» en lugar de *Donde*, pero no es ambigua. |
| AMB-008 (BAJA) | Resuelto: CA12 usa selectores concretos. La spec coloca bien el menú principal dentro de `<nav class="navbar">`; la corrección propuesta en el informe anterior lo ponía por error dentro de `<main>`. |
| AMB-009 (BAJA) | Resuelto: CA8 dice qué contenedores son (el `div` hijo directo de la barra, de `<main>` y de `<footer>`), igual que CA7a. Comprobado en `page.html.twig`. |
| CON-004 (BAJA) | Resuelto: RA dice que la persona decide si se comunican a los mantenedores o se abre una spec específica. Encaja con `.claude/rules/accesibilidad.md`. |
| VER-004 (BAJA) | Resuelto: se divide en CA9 (archivos prohibidos) y CA9b (solo `.css` en la librería), los dos Unit. La suite `custom-unit` incluye `web/themes/custom/*/tests/src/Unit`. |

### Primera pasada (BLOQUEADA)
| ID | Estado |
|---|---|
| TRZ-001 (BLOQUEANTE) | Resuelto: CA9 y CA9b cubren R5. |
| TRZ-002 (BLOQUEANTE) | Resuelto: CA11, CA12 y CA13 cubren R7. |
| CON-001 (ALTA) | Resuelto: `b5_body_schema` y `b5_body_bg_schema` pasan a `none`. |
| CON-002 (ALTA) | Resuelto: el pie usa `bg-dark` (decisión de la persona). |
| DRU-001 (ALTA) | Resuelto, junto con DRU-002. |
| VER-001 (ALTA) | Resuelto: CA-A1, CA-A1b y CA-A1c. |
| CON-003 (ALTA) | Resuelto: RA fija el tratamiento de lo heredado (decisión de la persona). |
| EARS-001 (ALTA) | Resuelto: CA5a y CA5b. |
| EARS-002 (ALTA) | Resuelto: CA7c. |
| AMB-001 (MEDIA) | Resuelto. |
| AMB-002 (MEDIA) | Resuelto. |
| AMB-003 (MEDIA) | Resuelto. |
| ACT-001 (MEDIA) | Resuelto. |
| VER-002 (MEDIA) | Resuelto. |
| TRZ-003 (MEDIA) | Resuelto: CA10. |
| TRZ-004 (MEDIA) | Resuelto: CA14 y CA15. |
| EARS-003 (MEDIA) | Resuelto: CA-A4 y CA-A4b. |
| VER-003 (MEDIA) | Resuelto: puerta 5 en `quality-gates.md` y `asdd-accesibilidad` en `CLAUDE.md`. |
| AMB-004 (MEDIA) | Resuelto. |
| TRZ-005 (BAJA) | Resuelto. |
| TRZ-006 (BAJA) | Resuelto. |
| AMB-005 (BAJA) | Resuelto. |
| AMB-006 (BAJA) | Resuelto. |

## Recomendación
La spec cumple la puerta de validación: no hay hallazgos BLOQUEANTES ni ALTOS,
y todos los requisitos DEBE tienen al menos un criterio EARS con su tipo de
test. La persona puede aprobarla tal como está. Si quiere, antes puede
concretar la redacción de CA-A4c (AMB-010, BAJA) o dejarlo para la próxima
revisión.

La aprobación la da la persona: este informe no cambia el `estado` de la spec.
