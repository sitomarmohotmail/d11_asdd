# Pendientes (hallazgos BAJOS y OBS)

Una línea por pendiente: origen · descripción. Se agrupan en specs futuras.

- 001 · PHPStan a nivel 8: ~10 errores de tipos nulos en los tests (`find()`, `FileStorage::read()`).
- 001 · OBS: textos en inglés sin `|t` en plantillas de Bootstrap5 («Toggle navigation», «Close», «breadcrumb»); comunicar a los mantenedores o sobrescribir plantillas en una spec propia.
- 001 · OBS-10: al cerrar un aviso, el foco vuelve a `<body>` (JS de Bootstrap).
- 001 · OBS: vista `frontpage` con textos en inglés («Welcome!») y RSS sin título de feed.
- 001 · Responsive (v1.2): menú plegable (`aria-expanded`, teclado) y objetivos táctiles ≥ 24×24 px. Reflujo a 320 px ya lo cubre `check.sh`. Solo cuando la persona lo pida.
