---
id: NNN
titulo: Título corto
version: 1.0
estado: borrador
tipo: modulo | tema
proyecto: nombre_maquina
---

# NNN · Título corto

## Contexto
¿Qué problema resuelve y para quién?

## Requisitos
Cada requisito lleva prioridad: **DEBE** (obligatorio, bloquea QA si no tiene
test), **DEBERÍA** (recomendable) o **PODRÍA** (opcional).

- **R1** *(DEBE)* —
- **R2** *(DEBERÍA)* —

## Criterios de aceptación (EARS)
Usa un patrón EARS por criterio (ver `specs/README.md`). Indica los requisitos
que cubre y el tipo de test: Unit, Kernel, Functional o calidad.

- **CA1** *(R1 · Kernel)* — **Cuando** …, **el sistema deberá** …
- **CA2** *(R1 · Functional)* — **Si** …, **entonces el sistema deberá** …

## Fuera de alcance
-

## Decisiones
1.

## Plan técnico
_(lo completa el agente tras aprobar la spec)_

## Tareas
- [ ] Validación (`asdd-validacion`) sin hallazgos BLOQUEANTES ni ALTOS
- [ ] Tests para CA1 (en rojo)
- [ ] Implementación CA1 (tests en verde)
- [ ] QA (`asdd-qa`) superado
- [ ] Seguridad (`asdd-seguridad`) superada

## Trazabilidad (requisito → criterio → test)
| Requisito | Criterio | Test |
|-----------|----------|------|
| R1 | CA1 | |

## Informes
- Validación: `specs/NNN-validacion.md`
- QA: `specs/NNN-qa.md`
- Seguridad: `specs/NNN-seguridad.md`
