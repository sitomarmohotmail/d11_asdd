# Especificaciones (ASDD)

Cada funcionalidad nace aquí **antes** de escribir código.

El proceso adopta parcialmente el marco
[efactory-llc/asdd](https://github.com/efactory-llc/asdd) (licencia MIT):
requisitos en formato EARS y agentes de validación, QA, seguridad y
accesibilidad adaptados a
Drupal. Los agentes están en `.claude/agents/` y los umbrales en
`.claude/rules/quality-gates.md`.

## Ciclo de trabajo

```
borrador → [validación] → aprobada → tests en rojo → implementación
        → [QA] → [seguridad] → [accesibilidad] → implementada → commit spec-NNN
```

1. **Spec**: copiar `_plantilla.md` a `NNN-nombre-corto.md` y rellenar
   *Contexto*, *Requisitos* (con prioridad) y *Criterios de aceptación* (EARS).
2. **Validación**: el agente `asdd-validacion` revisa la spec y escribe
   `NNN-validacion.md`. Se corrige la spec hasta que no queden hallazgos
   BLOQUEANTES ni ALTOS.
3. **Aprobación**: la persona revisa y aprueba la spec (estado `aprobada`).
   Ningún agente puede aprobarla.
4. **Plan y tareas**: el agente añade el plan técnico y la lista de tareas.
5. **Deuda técnica**: las suites `custom-*` deben estar en verde antes de
   empezar.
6. **Tests primero**: cada criterio de aceptación se traduce en al menos un
   test (Unit / Kernel / Functional). Deben fallar al principio.
7. **Implementación** hasta que los tests pasen y `phpcs` esté limpio.
8. **QA**: el agente `asdd-qa` ejecuta tests, phpcs y phpstan, comprueba la
   cobertura de cada requisito y escribe `NNN-qa.md`.
9. **Seguridad**: el agente `asdd-seguridad` revisa el código de la spec y
   escribe `NNN-seguridad.md`.
10. **Accesibilidad** (si la spec pinta interfaz): el agente
    `asdd-accesibilidad` revisa el código, ejecuta axe-core y genera una lista
    de comprobación manual que confirma la persona. Escribe
    `NNN-accesibilidad.md`.
11. **Cierre**: estado `implementada`, commit con referencia a la spec
    (`spec-NNN: …`), incluidos los informes.

## Accesibilidad

Todo lo que añade o cambia HTML, Twig, CSS o JS debe cumplir **WCAG 2.2 AA**.
Esas specs llevan el requisito **RA** y criterios de accesibilidad concretos.
Lista de comprobación y reglas: `.claude/rules/accesibilidad.md`.

Revisión automática con axe-core (requiere Node, ver `.nvmrc`):

```bash
nvm use && npm ci                         # una vez
npx puppeteer browsers install chrome     # una vez (npm ya no lo hace solo)
npm run a11y -- http://d11assd.local/     # una o varias URL
npm run a11y -- --width 320 http://d11assd.local/   # en móvil
```

Los agentes se lanzan a mano en su paso; no hay encadenado automático.

### Buenas prácticas (lecciones del piloto, spec 001)
- **Registrar el rojo**: al escribir los tests de una spec, anota en *Notas de
  implementación* cuántos fallaron antes de implementar (p. ej. «rojo: 6
  fallos, CA7a–CA8»). QA lo comprueba.
- **Verificar los informes**: el agente principal comprueba en el código los
  hallazgos BLOQUEANTES y ALTOS antes de corregirlos. En el piloto, tres
  hallazgos de validación eran erróneos y uno de accesibilidad se detectó tarde.
- **No cambiar archivos mientras un agente ejecuta tests**: con QA en marcha,
  esperar a que termine antes de editar código o tests.
- **Spec abierta en el editor**: recarga la pestaña antes de editarla; una
  copia antigua guardada sobrescribe los cambios del agente.
- **Una pasada no basta**: cada corrección se vuelve a pasar por la puerta que
  la detectó, hasta que no queden BLOQUEANTES ni ALTOS.

## Estados

`borrador` → `aprobada` → `en-curso` → `implementada`

## Prioridades

| Prioridad | Significado | En QA |
|---|---|---|
| **DEBE** | Obligatorio | Sin test → BLOQUEANTE |
| **DEBERÍA** | Recomendable | Sin test → MEDIA |
| **PODRÍA** | Opcional | Sin test → informativo |

## Criterios de aceptación en EARS

EARS (*Easy Approach to Requirements Syntax*) obliga a escribir cada criterio
con una estructura fija, para que no haya ambigüedad. Un criterio = un
comportamiento verificable.

| Patrón | Plantilla | Ejemplo |
|---|---|---|
| Siempre | **El sistema deberá** ‹respuesta›. | El sistema deberá declarar `base theme: bootstrap5`. |
| Evento | **Cuando** ‹disparador›, **el sistema deberá** ‹respuesta›. | Cuando un visitante anónimo abra la portada, el sistema deberá responder 200. |
| Estado | **Mientras** ‹estado›, **el sistema deberá** ‹respuesta›. | Mientras el módulo esté desinstalado, el sistema deberá responder 404 en la ruta. |
| Error | **Si** ‹condición no deseada›, **entonces el sistema deberá** ‹respuesta›. | Si falta un valor obligatorio, entonces el sistema deberá mostrar un mensaje de error. |
| Opcional | **Donde** ‹característica presente›, **el sistema deberá** ‹respuesta›. | Donde el módulo Search esté activo, el sistema deberá mostrar el bloque de búsqueda. |

Se pueden combinar (*Mientras … cuando …, el sistema deberá …*). Cada criterio
indica entre paréntesis los requisitos que cubre y el tipo de test:
`**CA1** *(R1, R2 · Kernel)* — Cuando …`.

## Informes

Junto a cada spec quedan sus informes, que se versionan:

- `NNN-validacion.md` — validación de la spec.
- `NNN-qa.md` — puerta de QA.
- `NNN-seguridad.md` — puerta de seguridad.
- `NNN-accesibilidad.md` — puerta de accesibilidad.
