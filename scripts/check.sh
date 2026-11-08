#!/usr/bin/env bash
# Puerta de QA automática (sustituye al agente asdd-qa): tests, phpcs, phpstan,
# linters CSS/JS/Twig (scripts/lint/lint.sh), config:status, trazabilidad y axe.
#
# Uso:
#   scripts/check.sh [--spec specs/NNN-x.md] [--group GRUPO] [--no-a11y] [URL…]
#
#   --spec     comprueba que cada test citado en la trazabilidad existe.
#   --group    limita los tests Functional a un grupo (desarrollo). Sin él se
#              ejecuta la suite completa (cierre de spec).
#   --no-a11y  omite axe-core (specs sin interfaz).
#   URL…       páginas para axe (por defecto http://d11assd.local/).
#
# Sale con 1 si falla cualquier comprobación.
set -u
cd "$(dirname "$0")/.."

spec="" group="" a11y=1 urls=()
while [ $# -gt 0 ]; do
  case "$1" in
    --spec) spec="$2"; shift 2 ;;
    --group) group="$2"; shift 2 ;;
    --no-a11y) a11y=0; shift ;;
    *) urls+=("$1"); shift ;;
  esac
done
[ ${#urls[@]} -eq 0 ] && urls=("http://d11assd.local/")

failed=0
results=()
run() {
  local label="$1"; shift
  local out
  if out=$("$@" 2>&1); then
    results+=("OK    $label")
  else
    results+=("FALLO $label")
    failed=1
    printf '\n--- %s ---\n%s\n' "$label" "$(printf '%s\n' "$out" | tail -25)"
  fi
}

run "phpunit custom-unit" vendor/bin/phpunit --testsuite custom-unit
run "phpunit custom-kernel" vendor/bin/phpunit --testsuite custom-kernel
if [ -n "$group" ]; then
  run "phpunit custom-functional (grupo $group)" vendor/bin/phpunit --testsuite custom-functional --group "$group"
else
  run "phpunit custom-functional" vendor/bin/phpunit --testsuite custom-functional
fi
run "phpcs" vendor/bin/phpcs -q
run "phpstan" vendor/bin/phpstan analyse --no-progress
run "stylelint" scripts/lint/lint.sh css
run "eslint" scripts/lint/lint.sh js
run "twig" scripts/lint/lint.sh twig
run "linters (fixtures)" scripts/lint/test.sh
run "drush config:status" bash -c 'vendor/bin/drush config:status 2>&1 | grep -q "No differences"'

if [ -n "$spec" ]; then
  trace() {
    local missing=0
    while read -r ref; do
      local type="${ref%%\\*}" rest="${ref#*\\}"
      local class="${rest%%::*}" method="${rest#*::}"
      if ! grep -qs "function ${method}(" web/modules/custom/*/tests/src/"$type"/"$class".php web/themes/custom/*/tests/src/"$type"/"$class".php; then
        echo "No existe: $ref"; missing=1
      fi
    done < <(grep -oE '(Unit|Kernel|Functional)\\[A-Za-z0-9_]+::test[A-Za-z0-9_]+' "$spec" | sort -u)
    return $missing
  }
  run "trazabilidad ($spec)" trace
fi

if [ "$a11y" -eq 1 ]; then
  export NVM_DIR="$HOME/.nvm"
  # shellcheck disable=SC1091
  . "$NVM_DIR/nvm.sh" >/dev/null
  nvm use >/dev/null
  run "axe + foco 1280px" npm run --silent a11y -- --focus --menu "${urls[@]}"
  run "axe + foco 320px" npm run --silent a11y -- --focus --menu --width 320 "${urls[@]}"
fi

printf '\n'
printf '%s\n' "${results[@]}"
exit $failed
