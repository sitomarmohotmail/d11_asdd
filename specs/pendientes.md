# Pendientes (hallazgos BAJOS y OBS)

Una línea por pendiente: origen · descripción. Se agrupan en specs futuras.

- 001 · PHPStan a nivel 8: ~10 errores de tipos nulos en los tests (`find()`, `FileStorage::read()`).
- 001 · OBS: textos en inglés sin `|t` en plantillas de Bootstrap5 («Toggle navigation», «Close», «breadcrumb»); comunicar a los mantenedores o sobrescribir plantillas en una spec propia.
- 001 · OBS-10: al cerrar un aviso, el foco vuelve a `<body>` (JS de Bootstrap).
- 001 · OBS: vista `frontpage` con textos en inglés («Welcome!») y RSS sin título de feed.
- 001 v1.2 · CA22 usa el patrón «Si… entonces» para un caso normal (Esc con el menú cerrado).
- 001 v1.2 · CA-A7 se solapa con CA-A3 y CA20.
- 001 v1.2 · CA6 y las tareas no citan ESLint sobre `js/navbar.js` (lo cubre `check.sh` por spec-002).
- 007 · DRU-001: en Drupal 11.4 el módulo `sdc` es obsoleto (integrado en core); la dependencia se eliminó del info.yml — resuelto.
- 007 · DRU-002: eliminar `CartotecaAjaxController.php` del plan técnico (ya resuelto en v1.0); el AJAX va por Form API.
- 007 v1.6 · AMB: R4 «variantes de tamaño» y R5 «Período/Escala» con alternativas `/` en la tabla del plan sin regla de prioridad.
- 007 v1.6 · DUP: R6/CA8 repiten lo mismo que CA4b (imagen por defecto).
- 007 v1.6 · AMB: R7/CA9 «disparar el evento en cada componente» no define el selector (el plan sí: `.sdc-cartoteca__slider`/`__info`).
- tooling · `asdd_tools/gen-specs-json.sh` pinta las puertas val/qa en verde por la mera existencia del fichero `NNN-validacion.md`/`NNN-qa.md`, sin leer el veredicto (`Resultado:`); engaña si el informe está BLOQUEADO. Mejora: leer la primera línea de resultado.
- 007 v1.6 · CA2b: `CartotecaFormErrorTest` usa `user/login` y no comprueba que el error esté ausente de la barra de mensajes (solo que esté inline con `aria-invalid`). Mejora: aserción negativa sobre `[data-drupal-messages]`.
