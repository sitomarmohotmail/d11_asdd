#!/usr/bin/env bash
# Tests del tooling asdd (spec-008): hook de procesos, veredicto de puertas,
# estado en-curso, orden descendente, resumen de documentos y purga del
# indicador de "tests en ejecución". Sale con 1 si falla algún caso.
#
# No toca los ficheros reales: el hook usa ASDD_RUNNING_JSON/ASDD_STACK y el
# generador usa SPECS_DIR/OUTPUT, ambos sobre un directorio temporal.
set -u
cd "$(dirname "$0")/../.."

HOOK=scripts/agent-hook.sh
GEN=scripts/gen-specs-json.sh
INDEX=asdd_tools/index.html
CHECK=scripts/check.sh
fallos=0

ok() { echo "OK    $1"; }
ko() { echo "FALLO $1: ${2:-}"; fallos=$((fallos + 1)); }

# Modo meta: forzar un fallo para comprobar que el arnés sale con código ≠ 0.
if [ -n "${ASDD_FORCE_FAIL:-}" ]; then
  echo "FALLO forzado (ASDD_FORCE_FAIL)"
  exit 1
fi

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

have() { grep -qE "$1" "$INDEX"; }

# ---------------------------------------------------------------------------
# R1 · hook: solo marca "tests" cuando se ejecuta scripts/check.sh
# ---------------------------------------------------------------------------
hook_entries() { # comando -> nº de entradas en running.json
  local rj="$TMP/running.json"
  printf '{}\n' > "$rj"; : > "$TMP/stack"
  jq -n --arg c "$1" '{tool_name:"Bash",tool_input:{command:$c}}' |
    ASDD_RUNNING_JSON="$rj" ASDD_STACK="$TMP/stack" bash "$HOOK" start >/dev/null 2>&1
  jq '[.[] | .[]] | length' "$rj" 2>/dev/null || echo ERR
}

while IFS= read -r cmd; do
  [ -z "$cmd" ] && continue
  n="$(hook_entries "$cmd")"
  [ "$n" = "0" ] && ok "CA1 hook ignora mención: $cmd" \
    || ko "CA1 hook: «$cmd»" "añadió $n (esperaba 0)"
done <<'EOF'
grep -E 'check.sh'
grep scripts/check.sh
ps aux | grep check.sh
git commit -m "fix: scripts/check.sh mención"
EOF

while IFS= read -r cmd; do
  [ -z "$cmd" ] && continue
  n="$(hook_entries "$cmd")"
  [ "$n" = "1" ] && ok "CA2 hook marca ejecución: $cmd" \
    || ko "CA2 hook: «$cmd»" "entradas=$n (esperaba 1)"
done <<'EOF'
scripts/check.sh --spec specs/007-x.md
cd /var/www/d11asdd && scripts/check.sh
bash scripts/check.sh
./scripts/check.sh
EOF

# ---------------------------------------------------------------------------
# R2 · veredicto de puertas  +  R6 · objeto reports
# ---------------------------------------------------------------------------
SPECS_FIX="$TMP/specs"; mkdir -p "$SPECS_FIX"
cat > "$SPECS_FIX/900-fixture.md" <<'EOF'
---
id: "900"
titulo: Fixture de prueba
version: 1.0
estado: en-curso
nivel: S
tipo: modulo
proyecto: fix
---
EOF
# qa BLOQUEADA, a11y BLOQUEADA -> se excluyen; val SUPERADA (etiqueta variante),
# seg sin línea Resultado -> se mantiene.
cat > "$SPECS_FIX/900-qa.md" <<'EOF'
- Fecha: 2026-01-03
- Versión de la spec: 1.0
- Resultado: BLOQUEADA
- Hallazgos: 1 BLOQUEANTE
EOF
printf -- '- Resultado: BLOQUEADA\n' > "$SPECS_FIX/900-accesibilidad.md"
cat > "$SPECS_FIX/900-validacion.md" <<'EOF'
- Fecha: 2026-01-02
- Versión de la spec validada: 1.2
- Resultado: SUPERADA CON AVISOS
- Hallazgos nuevos: 0 BLOQUEANTES
EOF
printf -- '- Fecha: 2026-01-01\n' > "$SPECS_FIX/900-seguridad.md"

OUTJSON="$TMP/specs.json"
SPECS_DIR="$SPECS_FIX" OUTPUT="$OUTJSON" bash "$GEN" >/dev/null 2>&1
S900() { jq -r ".[] | select(.id==\"900\") | $1" "$OUTJSON" 2>/dev/null; }

gates="$(S900 '.gates | sort | join(",")')"
[ "$gates" = "seg,val" ] && ok "CA3/CA4 gates = $gates (qa/a11y BLOQUEADA fuera; seg sin Resultado dentro)" \
  || ko "CA3/CA4 gates" "obtuvo «$gates» (esperaba «seg,val»)"

# CA10 · reports con los campos y la regex tolerante de etiquetas
[ "$(S900 '.reports.spec.version')" = "1.0" ] && [ "$(S900 '.reports.spec.estado')" = "en-curso" ] \
  && ok "CA10 reports.spec (version+estado)" || ko "CA10 reports.spec" "$(S900 '.reports.spec')"
[ "$(S900 '.reports.validacion.version')" = "1.2" ] \
  && ok "CA10 reports.validacion.version (etiqueta «…validada»)" \
  || ko "CA10 validacion.version" "$(S900 '.reports.validacion.version') (esperaba 1.2)"
[ "$(S900 '.reports.validacion.hallazgos')" = "0 BLOQUEANTES" ] \
  && ok "CA10 reports.validacion.hallazgos (etiqueta «Hallazgos nuevos»)" \
  || ko "CA10 validacion.hallazgos" "$(S900 '.reports.validacion.hallazgos')"
[ "$(S900 '.reports.qa.resultado')" = "BLOQUEADA" ] \
  && ok "CA10 reports.qa.resultado" || ko "CA10 qa.resultado" "$(S900 '.reports.qa.resultado')"
[ "$(S900 '.reports.seguridad.fecha')" = "2026-01-01" ] \
  && ok "CA10 reports.seguridad.fecha" || ko "CA10 seg.fecha" "$(S900 '.reports.seguridad.fecha')"

# ---------------------------------------------------------------------------
# R3 · arnés integrado en check.sh y sale ≠ 0 al fallar
# ---------------------------------------------------------------------------
grep -qE 'scripts/asdd/test\.sh' "$CHECK" \
  && ok "CA5 check.sh invoca scripts/asdd/test.sh" || ko "CA5 check.sh" "no invoca el arnés"
if ASDD_FORCE_FAIL=1 bash "$0" >/dev/null 2>&1; then
  ko "CA5 arnés exit" "no salió ≠ 0 con ASDD_FORCE_FAIL"
else
  ok "CA5 arnés sale ≠ 0 al fallar"
fi

# ---------------------------------------------------------------------------
# R4 · estado en-curso en todas las estructuras (CA6) y estilo propio (CA7)
# ---------------------------------------------------------------------------
estructura() { # regex nombre
  have "$1" && ok "CA6 $2" || ko "CA6 $2" "falta en index.html"
}
estructura "PIPELINE[^;]*id:'en-curso'|id:'en-curso'" "pipeline"
estructura "STATE_LABELS[[:space:]]*=[^;]*en-curso" "STATE_LABELS"
estructura "FILTER_ES[[:space:]]*=[^;]*en-curso" "FILTER_ES"
estructura "STEP_POS[[:space:]]*=[^;]*en-curso" "STEP_POS"
estructura "\[[^]]*'en-curso'[^]]*\]\.map" "leyenda (renderStats)"
estructura "cols[[:space:]]*=[[:space:]]*\[[^]]*'en-curso'" "cols (kanban)"
estructura "labels[[:space:]]*=[^;]*en-curso" "labels (kanban)"

for rule in '\-\-en-curso-fg' '\-\-en-curso-bg' '\-\-en-curso-bd' \
  '\.card\[data-estado="en-curso"\]' '\.dp-estado\.en-curso' \
  '\.stat\.en-curso' '\.pn-box\.en-curso' '\.kol-hd\.en-curso'; do
  have "$rule" && ok "CA7 regla/token $rule" || ko "CA7 $rule" "falta"
done

# ---------------------------------------------------------------------------
# R5 · orden descendente en todos los .sort(  (CA9)
# ---------------------------------------------------------------------------
if grep -qE 'a\.id\.localeCompare\(b\.id\)' "$INDEX"; then
  ko "CA9 orden" "queda un .sort ascendente (a.id.localeCompare(b.id))"
elif grep -qE 'b\.id\.localeCompare\(a\.id\)' "$INDEX"; then
  ok "CA9 orden descendente (b.id.localeCompare(a.id))"
else
  ko "CA9 orden" "no se encuentra el comparador por id"
fi

# ---------------------------------------------------------------------------
# R6 · render del desplegable desde reports (CA11)
# ---------------------------------------------------------------------------
if have '\.reports' && have 'resultado' && have 'hallazgos' && have 'fecha'; then
  ok "CA11 cardHTML referencia reports y sus campos"
else
  ko "CA11 render reports" "cardHTML no referencia .reports y los 4 campos"
fi

# ---------------------------------------------------------------------------
# R7 · purga en cliente por antigüedad (CA12)
# ---------------------------------------------------------------------------
if have 'started' && have '1800' && grep -qE 'Date\.now\(\)[[:space:]]*/[[:space:]]*1000|/[[:space:]]*1000' "$INDEX"; then
  ok "CA12 filtro por started/TTL en segundos"
else
  ko "CA12 filtro" "falta started, 1800 o la conversión a segundos"
fi

# ---------------------------------------------------------------------------
# RA · contraste (CA-A1, CA-A3) y etiqueta de texto (CA-A2)
# ---------------------------------------------------------------------------
tok() { grep -oE "\-\-en-curso-$1:[[:space:]]*#[0-9a-fA-F]{3,6}" "$INDEX" | grep -oE '#[0-9a-fA-F]{3,6}' | head -1; }
hex2rgb() {
  local h=${1#\#}
  [ ${#h} -eq 3 ] && h="${h:0:1}${h:0:1}${h:1:1}${h:1:1}${h:2:1}${h:2:1}"
  printf '%d %d %d' "0x${h:0:2}" "0x${h:2:2}" "0x${h:4:2}"
}
contrast() { # hex1 hex2
  local r1 g1 b1 r2 g2 b2
  read -r r1 g1 b1 < <(hex2rgb "$1"); read -r r2 g2 b2 < <(hex2rgb "$2")
  awk -v r1="$r1" -v g1="$g1" -v b1="$b1" -v r2="$r2" -v g2="$g2" -v b2="$b2" '
    function ch(c){c=c/255.0; return c<=0.03928?c/12.92:((c+0.055)/1.055)^2.4}
    function L(r,g,b){return 0.2126*ch(r)+0.7152*ch(g)+0.0722*ch(b)}
    BEGIN{la=L(r1,g1,b1); lb=L(r2,g2,b2); hi=(la>lb?la:lb); lo=(la>lb?lb:la);
          printf "%.3f",(hi+0.05)/(lo+0.05)}'
}
ge() { awk -v a="$1" -v b="$2" 'BEGIN{exit !(a+0>=b+0)}'; }

fg="$(tok fg)"; bg="$(tok bg)"; bd="$(tok bd)"
if [ -n "$fg" ] && [ -n "$bg" ]; then
  cr="$(contrast "$fg" "$bg")"
  ge "$cr" 4.5 && ok "CA-A1 contraste texto $cr:1 ≥ 4,5" || ko "CA-A1 contraste" "$cr:1 < 4,5 ($fg/$bg)"
else
  ko "CA-A1 contraste" "faltan tokens --en-curso-fg/bg"
fi
if [ -n "$bd" ] && [ -n "$bg" ]; then
  cr="$(contrast "$bd" "$bg")"
  ge "$cr" 3.0 && ok "CA-A3 contraste borde $cr:1 ≥ 3" || ko "CA-A3 borde" "$cr:1 < 3 ($bd/$bg)"
else
  ko "CA-A3 contraste borde" "faltan tokens --en-curso-bd/bg"
fi

grep -qF 'En curso' "$INDEX" && grep -qF 'en curso' "$INDEX" \
  && ok "CA-A2 etiquetas de texto «En curso»/«en curso»" \
  || ko "CA-A2 etiqueta" "faltan los textos del estado"

# ---------------------------------------------------------------------------
[ "$fallos" -eq 0 ] && { echo "— tooling OK —"; exit 0; }
echo "$fallos caso(s) fallidos"; exit 1
