#!/usr/bin/env bash
# Tests de los linters de CSS, JS y Twig (spec-002).
# Uso: scripts/lint/test.sh   — sale con 1 si falla algún caso.
set -u
cd "$(dirname "$0")/../.."
F=scripts/lint/fixtures
L=scripts/lint/lint.sh
fallos=0
if ! command -v npm >/dev/null 2>&1 && [ -s "$HOME/.nvm/nvm.sh" ]; then
  # shellcheck disable=SC1091
  . "$HOME/.nvm/nvm.sh" >/dev/null && nvm use >/dev/null
fi

# caso NOMBRE ESPERADO(0|1) PATRÓN_SALIDA -- comando…
caso() {
  local nombre="$1" esperado="$2" patron="$3"; shift 4
  local salida estado
  salida=$("$@" 2>&1); estado=$?
  [ "$estado" -ne 0 ] && estado=1
  if [ "$estado" -ne "$esperado" ]; then
    echo "FALLO $nombre: salida $estado, se esperaba $esperado"
    printf '%s\n' "$salida" | tail -5; fallos=$((fallos + 1)); return
  fi
  if [ -n "$patron" ] && ! printf '%s\n' "$salida" | grep -qE "$patron"; then
    echo "FALLO $nombre: la salida no contiene /$patron/"
    printf '%s\n' "$salida" | tail -5; fallos=$((fallos + 1)); return
  fi
  echo "OK    $nombre"
}

# CA1 · R1
caso css-ko 1 'malo\.css:[0-9]+' -- $L css $F/ko
caso css-ok 0 '' -- $L css $F/ok
# CA2, CA3 · R2
caso js-ko 1 'malo\.js:[0-9]+' -- $L js $F/ko
caso js-ok 0 '' -- $L js $F/ok
# CA4 · R3
caso twig-ko 1 'malo\.html\.twig:[0-9]+' -- $L twig $F/ko
caso twig-ok 0 '' -- $L twig $F/ok
# CA6 · R5
caso excluidos 0 '' -- $L all $F/excluidos
# CA7 · R6
caso vacio 0 '' -- $L all $F/vacio
# CA8 · R7: npm run lint da el mismo resultado que lint.sh all.
npm_igual() {
  local a b
  $L all >/dev/null 2>&1; a=$?
  npm run --silent lint >/dev/null 2>&1; b=$?
  [ "$a" -eq "$b" ] && grep -q '"lint": "scripts/lint/lint.sh all"' package.json
}
caso npm 0 '' -- npm_igual

[ "$fallos" -eq 0 ] || { echo "$fallos caso(s) fallidos"; exit 1; }
