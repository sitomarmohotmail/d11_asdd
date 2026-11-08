---
id: 001
titulo: Tema hijo de Bootstrap5
estado: implementada
tipo: tema
proyecto: d11assd_theme
---

# 001 · Tema hijo de Bootstrap5

## Contexto
El sitio usa ahora Olivero como tema público. Queremos un tema propio que
herede de Bootstrap5 (contrib). Así podremos personalizar el aspecto y las
plantillas sin modificar el tema contribuido, que se actualiza con Composer. El
tema servirá también de base para mostrar los componentes SDC del módulo
"hola mundo" (spec posterior).

## Requisitos
- R1: Existe el tema `d11assd_theme` en `web/themes/custom/d11assd_theme`, con
  nombre visible "D11assd" y `base theme: bootstrap5`.
- R2: El tema es compatible con Drupal `^11` y declara su dependencia del tema
  base para que Drupal lo exija al instalarlo.
- R3: Hereda las regiones de Bootstrap5 (header, nav_branding, nav_main,
  nav_additional, breadcrumb, content, sidebar_first, sidebar_second, footer)
  y las declara en su `.info.yml`.
- R4: Añade una librería propia `d11assd_theme/global-styling` con
  `css/style.css`. Se carga en todas las páginas del front, además de las
  librerías de Bootstrap5.
- R5: Solo CSS: sin SCSS ni Node.js en esta spec.
- R6: Es el tema por defecto del sitio. Claro sigue siendo el tema de
  administración.
- R7: Los bloques se colocan en regiones equivalentes: las de Olivero se
  sustituyen por las de Bootstrap5, para que la página de inicio muestre la
  marca del sitio, el menú principal, los mensajes y el contenido.
- R8: Incluye `logo.svg` y `screenshot.png` propios.
- R9: La configuración resultante (tema activo y bloques) se exporta a
  `config/sync`.

## Criterios de aceptación
- **CA1** — Dado el código del tema, cuando Drupal lista los temas
  disponibles, entonces aparece `d11assd_theme` con `bootstrap5` como tema base.
  *(Kernel)*
- **CA2** — Dado un sitio sin el tema, cuando se instala `d11assd_theme`,
  entonces queda instalado también `bootstrap5`, y el tema hijo tiene las nueve
  regiones de R3. *(Kernel)*
- **CA3** — Dado el tema instalado, cuando se consultan sus librerías, entonces
  existe `d11assd_theme/global-styling` con `css/style.css`. *(Kernel)*
- **CA4** — Dado `d11assd_theme` como tema por defecto, cuando un visitante
  anónimo abre la portada, entonces la respuesta es 200, el HTML carga la hoja
  de estilos de Bootstrap5 y `d11assd_theme/css/style.css`, y se ve el nombre
  del sitio. *(Functional)*
- **CA5** — Dado un usuario administrador, cuando abre `/admin/content`,
  entonces la página se muestra con Claro y no con `d11assd_theme`.
  *(Functional)*
- **CA6** — Dado el código del tema, cuando se ejecuta `phpcs`, entonces no hay
  errores. *(calidad)*

## Fuera de alcance
- Compilación de SCSS y personalización de variables de Bootstrap (spec futura).
- Plantillas Twig propias y componentes SDC (spec del módulo "hola mundo").
- Ajustes visuales del tema (`theme-settings.php`), colores y tipografías.
- Despliegue en Docker.

## Decisiones
1. Nombre de máquina: `d11assd_theme`.
2. Solo CSS, sin SCSS (R5).
3. El tema se escribe a mano, sin el generador de Bootstrap5.

## Plan técnico
Archivos en `web/themes/custom/d11assd_theme/`:

| Archivo | Para qué |
|---------|----------|
| `d11assd_theme.info.yml` | Declara el tema: `base theme: bootstrap5`, `dependencies`, regiones (R1–R3) y librería (R4). |
| `d11assd_theme.libraries.yml` | Librería `global-styling` con `css/style.css` (R4). |
| `css/style.css` | Estilos propios. De momento, unos ajustes mínimos (R5). |
| `config/optional/block.block.d11assd_theme_*.yml` | Bloques del tema en las regiones de Bootstrap5, copiados de los de Bootstrap5. Se crean solos al instalar el tema (R7). |
| `logo.svg`, `screenshot.png` | Identidad propia (R8). |
| `tests/src/Kernel/ThemeTest.php` | CA1, CA2, CA3. |
| `tests/src/Kernel/ConfigSyncTest.php` | CA5 (parte de configuración): `config/sync` deja `d11assd_theme` como tema por defecto y Claro como tema de administración (R6, R9). |
| `tests/src/Functional/FrontendTest.php` | CA4 y CA5. |

Activación: `drush theme:install d11assd_theme`, se define como tema por
defecto en `system.theme` y después `drush cex`.

## Tareas
- [x] Tests para CA1–CA3 (Kernel)
- [x] Tests para CA4–CA5 (Functional)
- [x] Implementación del tema
- [x] Activación, bloques y `drush cex`
- [x] `phpcs` limpio (CA6)

## Trazabilidad (criterio → test)
| Criterio | Test |
|----------|------|
| CA1 | `Kernel\ThemeTest::testThemeIsDiscovered` |
| CA2 | `Kernel\ThemeTest::testInstallRequiresBaseThemeAndHasRegions` |
| CA3 | `Kernel\ThemeTest::testGlobalStylingLibrary` |
| CA4 | `Functional\FrontendTest::testFrontPage` |
| CA5 | `Functional\FrontendTest::testAdminUsesClaro`, `Kernel\ConfigSyncTest::testExportedThemeConfiguration` |
| CA6 | `vendor/bin/phpcs` |

## Notas de implementación
- El bloque de búsqueda (`search_form_block`) está en `config/optional` y solo
  se crea si se activa el módulo Search, que ahora no está activo.
