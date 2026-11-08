# Especificaciones (ASDD)

Cada funcionalidad nace aquí **antes** de escribir código.

## Ciclo de trabajo

1. **Spec**: copiar `_plantilla.md` a `NNN-nombre-corto.md` y rellenar *Contexto*,
   *Requisitos* y *Criterios de aceptación* (en formato Dado / Cuando / Entonces).
2. **Revisión**: la persona revisa y aprueba la spec (estado `aprobada`).
3. **Plan y tareas**: el agente añade el plan técnico y la lista de tareas.
4. **Tests primero**: cada criterio de aceptación se traduce en al menos un test
   (Unit / Kernel / Functional). Deben fallar al principio.
5. **Implementación** hasta que los tests pasen y `phpcs` esté limpio.
6. **Cierre**: estado `implementada`, commit con referencia a la spec (`spec-NNN`).

## Estados

`borrador` → `aprobada` → `en-curso` → `implementada`
