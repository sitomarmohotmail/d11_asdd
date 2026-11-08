#!/usr/bin/env bash
# Linters de CSS, JS y Twig con las reglas del core de Drupal (spec-002).
#
# Uso: scripts/lint/lint.sh css|js|twig|all [--fix] [RUTA…]
#   Sin RUTA revisa web/modules/custom y web/themes/custom.
#   Excluye node_modules, dist, vendor y *.min.js. Sin ficheros: OK.
#
# Configuración: .stylelintrc.json, .prettierrc.json y .eslintrc.json son
# copias de web/core (Drupal 11.4); al actualizar el core, compararlas.
set -u
cd "$(dirname "$0")/../.."

tipo="${1:-all}"; shift || true
fix=()
rutas=()
for a in "$@"; do
  if [ "$a" = "--fix" ]; then fix=(--fix); else rutas+=("$a"); fi
done
[ ${#rutas[@]} -eq 0 ] && rutas=(web/modules/custom web/themes/custom)

if ! command -v node >/dev/null 2>&1 && [ -s "$HOME/.nvm/nvm.sh" ]; then
  # shellcheck disable=SC1091
  . "$HOME/.nvm/nvm.sh" >/dev/null && nvm use >/dev/null
fi

ficheros() {
  find "${rutas[@]}" \( -name node_modules -o -name dist -o -name vendor \) -prune \
    -o -type f -name "$1" ! -name '*.min.js' -print 2>/dev/null | sort
}

lint() {
  local lista=()
  mapfile -t lista < <(ficheros "$2")
  [ ${#lista[@]} -eq 0 ] && return 0
  case "$1" in
    css) npx --no-install stylelint --formatter unix "${fix[@]}" "${lista[@]}" ;;
    js) npx --no-install eslint --no-eslintrc -c .eslintrc.json --format unix "${fix[@]}" "${lista[@]}" ;;
    twig)
      vendor/bin/twig-cs-fixer lint --no-cache --report github "${fix[@]}" "${lista[@]}" |
        sed -E 's/^::[a-z]+ file=([^,]+),line=([0-9]+),col=([0-9]+)::/\1:\2:\3: /'
      return "${PIPESTATUS[0]}"
      ;;
  esac
}

estado=0
case "$tipo" in
  css) lint css '*.css' || estado=1 ;;
  js) lint js '*.js' || estado=1 ;;
  twig) lint twig '*.twig' || estado=1 ;;
  all)
    lint css '*.css' || estado=1
    lint js '*.js' || estado=1
    lint twig '*.twig' || estado=1
    ;;
  *) echo "Tipo desconocido: $tipo (css|js|twig|all)" >&2; exit 2 ;;
esac
exit $estado
