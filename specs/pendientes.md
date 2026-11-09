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
