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

# ===========================================================================
# spec-009 · QA agnóstica de tecnología (perfiles de stack)
# ===========================================================================
INIT=scripts/asdd/init.sh
DRUPAL_STACK=asdd_tools/stacks/drupal.json
NODE_STACK=asdd_tools/stacks/node.json

# Sonda de capacidad: el runner genérico (R2) existe cuando check.sh lee
# ASDD_STACKS_DIR. Mientras no exista, no invocamos el check.sh real (sería
# lento y recursivo); cada caso de runner se marca en rojo.
RUNNER_READY=0; grep -q 'ASDD_STACKS_DIR' "$CHECK" && RUNNER_READY=1

# -- fixtures de perfiles y stubs ------------------------------------------
STKDIR="$TMP/stacks"; mkdir -p "$STKDIR"
CFG="$TMP/config009.json"
MARK="$TMP/mark"; TRACEDIR="$TMP/trace"; mkdir -p "$TRACEDIR"
STUB="$TMP/stub"; mkdir -p "$STUB"
HOMEDIR="$TMP/home"; mkdir -p "$HOMEDIR/.nvm"
# npm y herramientas node como no-ops para el paso a11y / node.json.
for b in npm npx node vitest eslint tsc; do
  printf '#!/usr/bin/env bash\nexit 0\n' > "$STUB/$b"; chmod +x "$STUB/$b"
done
printf 'nvm(){ :; }\n' > "$HOMEDIR/.nvm/nvm.sh"

set_stack() { # id
  jq -n --arg s "$1" \
    '{project_name:"fix",specs_dir:"../specs",output_json:"specs.json",stack:$s}' > "$CFG"
}
run_check() { # args… -> OUT, RC  (nunca toca los ficheros reales)
  OUT="$(ASDD_IN_TEST=1 ASDD_CONFIG="$CFG" ASDD_STACKS_DIR="$STKDIR" \
         HOME="$HOMEDIR" PATH="$STUB:$PATH" bash "$CHECK" "$@" 2>&1)"; RC=$?
}

jq -n '{label:"Pass",a11y:false,gates:[{id:"g1",label:"g1",cmd:["true"]},{id:"g2",label:"g2",cmd:["true"]}]}' > "$STKDIR/pass.json"
jq -n '{label:"Fail",a11y:false,gates:[{id:"g1",label:"g1",cmd:["true"]},{id:"g2",label:"g2",cmd:["false"]}]}' > "$STKDIR/fail.json"
jq -n --arg m "$MARK" '{label:"Group",a11y:false,gates:[{id:"t",label:"t",cmd:["bash","-c",("printf plain > "+$m)],cmd_group:["bash","-c",("printf group-{group} > "+$m)]}]}' > "$STKDIR/grp.json"
jq -n '{label:"A11y",a11y:true,gates:[{id:"g1",label:"g1",cmd:["true"]}]}' > "$STKDIR/a11yon.json"
jq -n --arg d "$TRACEDIR" '{label:"Trace",a11y:false,gates:[{id:"g1",label:"g1",cmd:["true"]}],trace:{pattern:"([A-Za-z0-9_]+)::([A-Za-z0-9_]+)",locate:("grep -q {2} "+$d+"/{1}.txt")}}' > "$STKDIR/trace.json"
jq -n --arg d "$TRACEDIR" '{label:"Trace2",a11y:false,gates:[{id:"g1",label:"g1",cmd:["true"]}],trace:{pattern:"([A-Za-z0-9_]+)::([A-Za-z0-9_.-]+)",locate:("grep -q {2} "+$d+"/{1}.txt")}}' > "$STKDIR/trace2.json"
jq -n '{label:"Guard",a11y:false,gates:[{id:"tool",label:"TOOLGATE",cmd:["scripts/asdd/test.sh"]},{id:"ok",label:"OKGATE",cmd:["true"]}]}' > "$STKDIR/guard.json"
echo bar   > "$TRACEDIR/Foo.txt"      # para Foo::bar (existe) y Foo::ba-r (sanear)
printf 'ref Foo::bar\n'  > "$TMP/spec-ok.md"
printf 'ref Baz::qux\n'  > "$TMP/spec-bad.md"
printf 'ref Foo::ba-r\n' > "$TMP/spec-inj.md"

rc_test() { # etiqueta  stack  esperado(0|ne)  [args…]
  local label="$1" stack="$2" exp="$3"; shift 3
  if [ "$RUNNER_READY" != 1 ]; then ko "$label" "runner no implementado (ASDD_STACKS_DIR)"; return; fi
  set_stack "$stack"; run_check "$@"
  if [ "$exp" = "0" ]; then
    [ "$RC" -eq 0 ] && ok "$label" || ko "$label" "RC=$RC; OUT=$OUT"
  else
    [ "$RC" -ne 0 ] && ok "$label" || ko "$label" "RC=0 (esperaba ≠0); OUT=$OUT"
  fi
}

# -- R1 · perfil drupal.json (CA1, CA2, CA2b) ------------------------------
if [ -f "$DRUPAL_STACK" ] && jq -e . "$DRUPAL_STACK" >/dev/null 2>&1; then
  keys="$(jq -r 'has("label") and has("a11y") and (.gates|type=="array" and length>0) and (.trace|has("pattern") and has("locate"))' "$DRUPAL_STACK")"
  [ "$keys" = "true" ] && ok "009-CA1 drupal.json válido con claves" || ko "009-CA1 drupal.json" "faltan claves"
else
  ko "009-CA1 drupal.json" "no existe o JSON inválido"
fi

if [ -f "$DRUPAL_STACK" ]; then
  ids="$(jq -r '[.gates[].id] | join(",")' "$DRUPAL_STACK" 2>/dev/null)"
  need=1
  for g in unit kernel functional phpcs phpstan stylelint eslint twig js-unit drush; do
    echo "$ids" | grep -q "$g" || need=0
  done
  a11y="$(jq -r '.a11y' "$DRUPAL_STACK" 2>/dev/null)"
  cg="$(jq -r '[.gates[]|select(.id=="functional")|has("cmd_group")]|any' "$DRUPAL_STACK" 2>/dev/null)"
  [ "$need" = 1 ] && [ "$a11y" = "true" ] && [ "$cg" = "true" ] \
    && ok "009-CA2 drupal.json: ids de gates + a11y + cmd_group" \
    || ko "009-CA2 drupal.json" "ids=$ids a11y=$a11y cmd_group=$cg"
else
  ko "009-CA2 drupal.json" "no existe"
fi

# CA2b · label+cmd literales e invariante vs check.sh:50-65
EXPECTED_GATES="$(jq -n '[
  {id:"unit",      label:"phpunit custom-unit",      cmd:["vendor/bin/phpunit","--testsuite","custom-unit"]},
  {id:"kernel",    label:"phpunit custom-kernel",    cmd:["vendor/bin/phpunit","--testsuite","custom-kernel"]},
  {id:"functional",label:"phpunit custom-functional",cmd:["vendor/bin/phpunit","--testsuite","custom-functional"],cmd_group:["vendor/bin/phpunit","--testsuite","custom-functional","--group","{group}"]},
  {id:"phpcs",     label:"phpcs",                    cmd:["vendor/bin/phpcs","-q"]},
  {id:"phpstan",   label:"phpstan",                  cmd:["vendor/bin/phpstan","analyse","--no-progress"]},
  {id:"stylelint", label:"stylelint",                cmd:["scripts/lint/lint.sh","css"]},
  {id:"eslint",    label:"eslint",                   cmd:["scripts/lint/lint.sh","js"]},
  {id:"twig",      label:"twig",                     cmd:["scripts/lint/lint.sh","twig"]},
  {id:"linters",   label:"linters (fixtures)",       cmd:["scripts/lint/test.sh"]},
  {id:"tooling",   label:"tooling (fixtures)",       cmd:["scripts/asdd/test.sh"]},
  {id:"js-unit",   label:"js-unit (node --test)",    cmd:["bash","-c",". \"$HOME/.nvm/nvm.sh\" >/dev/null 2>&1; nvm use >/dev/null 2>&1; npm run --silent test:js"]},
  {id:"drush",     label:"drush config:status",      cmd:["bash","-c","vendor/bin/drush config:status 2>&1 | grep -q \"No differences\""]}
]')"
if [ -f "$DRUPAL_STACK" ] && \
   jq --argjson exp "$EXPECTED_GATES" -e '.gates == $exp' "$DRUPAL_STACK" >/dev/null 2>&1; then
  ok "009-CA2b drupal.json: gates literales e invariante"
else
  ko "009-CA2b drupal.json" "los gates no coinciden literalmente con check.sh:50-65"
fi

# -- R2 · runner (CA3, CA3b, CA4, CA4b, CA5, CA5b, CA6, CA6b, CA-R7) --------
rc_test "009-CA3 runner: un gate falla → ≠0"     fail 1  --no-a11y
rc_test "009-CA3b runner: todos pasan → 0"       pass 0  --no-a11y

if [ "$RUNNER_READY" = 1 ]; then
  set_stack grp; : > "$MARK"; run_check --group MIGRUPO --no-a11y
  [ "$(cat "$MARK" 2>/dev/null)" = "group-MIGRUPO" ] \
    && ok "009-CA4 --group usa cmd_group con {group}" || ko "009-CA4" "MARK=$(cat "$MARK" 2>/dev/null)"
  set_stack grp; : > "$MARK"; run_check --no-a11y
  [ "$(cat "$MARK" 2>/dev/null)" = "plain" ] \
    && ok "009-CA4b sin --group usa cmd" || ko "009-CA4b" "MARK=$(cat "$MARK" 2>/dev/null)"
else
  ko "009-CA4 --group"  "runner no implementado"
  ko "009-CA4b sin group" "runner no implementado"
fi

if [ "$RUNNER_READY" = 1 ]; then
  set_stack a11yon; run_check
  echo "$OUT" | grep -q 'axe' && ok "009-CA5b a11y:true ejecuta axe" || ko "009-CA5b a11y on" "sin paso axe"
  set_stack a11yon; run_check --no-a11y
  echo "$OUT" | grep -q 'axe' && ko "009-CA5 --no-a11y omite axe" "corrió axe" || ok "009-CA5 --no-a11y omite axe"
else
  ko "009-CA5 a11y off"  "runner no implementado"
  ko "009-CA5b a11y on"  "runner no implementado"
fi

rc_test "009-CA6 trazabilidad: test existe → 0"  trace  0  --spec "$TMP/spec-ok.md"  --no-a11y
rc_test "009-CA6 trazabilidad: test falta → ≠0"  trace  1  --spec "$TMP/spec-bad.md" --no-a11y
rc_test "009-CA6b saneo: valor con guion → ≠0"   trace2 1  --spec "$TMP/spec-inj.md" --no-a11y

if [ "$RUNNER_READY" = 1 ]; then
  set_stack guard; run_check --no-a11y
  echo "$OUT" | grep -q 'OKGATE' && ! echo "$OUT" | grep -q 'TOOLGATE' \
    && ok "009-CA-R7 guardia ASDD_IN_TEST omite gate de tooling" \
    || ko "009-CA-R7 guardia" "OUT=$OUT"
else
  ko "009-CA-R7 guardia" "runner no implementado"
fi

# -- R3 · validación del id de stack (CA7, CA7b) ---------------------------
for bad in "a/b" "../x" "ghost"; do
  rc_test "009-CA7 stack inválido ($bad) → ≠0" "$bad" 1 --no-a11y
done
if [ "$RUNNER_READY" = 1 ]; then
  jq -n '{project_name:"fix",specs_dir:"../specs",output_json:"specs.json"}' > "$CFG"  # sin stack
  run_check --no-a11y
  [ "$RC" -ne 0 ] && ok "009-CA7b sin stack → ≠0" || ko "009-CA7b sin stack" "RC=0"
else
  ko "009-CA7b sin stack" "runner no implementado"
fi
# init.sh también valida el id
if [ -x "$INIT" ]; then
  set_stack drupal
  if ASDD_CONFIG="$CFG" ASDD_STACKS_DIR="$STKDIR" bash "$INIT" --stack "../x" >/dev/null 2>&1; then
    ko "009-CA7 init.sh id inválido" "no abortó"
  else
    [ "$(jq -r '.stack' "$CFG")" = "drupal" ] && ok "009-CA7 init.sh rechaza id inválido sin tocar config" \
      || ko "009-CA7 init.sh" "modificó config.json"
  fi
else
  ko "009-CA7 init.sh id inválido" "scripts/asdd/init.sh no existe"
fi

# -- R4 · asistente init.sh (CA8, CA8b, CA8c) ------------------------------
if [ -x "$INIT" ]; then
  set_stack node
  ASDD_CONFIG="$CFG" ASDD_STACKS_DIR="$STKDIR" bash "$INIT" --stack pass >/dev/null 2>&1
  [ "$(jq -r '.stack' "$CFG")" = "pass" ] && [ "$(jq -r '.project_name' "$CFG")" = "fix" ] \
    && ok "009-CA8 init.sh fija .stack conservando campos" || ko "009-CA8 init.sh" "config=$(cat "$CFG")"
  out="$(ASDD_CONFIG="$CFG" ASDD_STACKS_DIR="$STKDIR" bash "$INIT" 2>&1)"
  echo "$out" | grep -q 'pass' && echo "$out" | grep -q 'Pass' \
    && ok "009-CA8b init.sh lista id + label" || ko "009-CA8b init.sh listar" "$out"
else
  ko "009-CA8 init.sh fija stack" "scripts/asdd/init.sh no existe"
  ko "009-CA8b init.sh lista"      "scripts/asdd/init.sh no existe"
fi
# CA8c: init.sh regenera stack.json con active actualizado
if [ -x "$INIT" ] && grep -q 'STACK_OUT' "$GEN"; then
  set_stack pass; SOUT="$TMP/stack.json"
  ASDD_CONFIG="$CFG" ASDD_STACKS_DIR="$STKDIR" STACKS_DIR="$STKDIR" STACK_OUT="$SOUT" \
    bash "$INIT" --stack fail >/dev/null 2>&1
  [ "$(jq -r '.active' "$SOUT" 2>/dev/null)" = "fail" ] \
    && ok "009-CA8c init.sh regenera stack.json (active)" || ko "009-CA8c init.sh stack.json" "active=$(jq -r '.active' "$SOUT" 2>/dev/null)"
else
  ko "009-CA8c init.sh regenera stack.json" "init.sh o STACK_OUT no implementados"
fi

# -- R5 · perfil node.json (CA9, CA9b) -------------------------------------
if [ -f "$NODE_STACK" ] && jq -e '.gates|type=="array" and length>0' "$NODE_STACK" >/dev/null 2>&1; then
  ok "009-CA9 node.json válido con gates"
else
  ko "009-CA9 node.json" "no existe o sin gates"
fi
if [ "$RUNNER_READY" = 1 ] && [ -f "$NODE_STACK" ]; then
  cp "$NODE_STACK" "$STKDIR/node.json"; set_stack node; run_check --no-a11y
  [ "$RC" -eq 0 ] && ok "009-CA9b runner ejecuta node.json con stubs" || ko "009-CA9b node.json" "RC=$RC OUT=$OUT"
else
  ko "009-CA9b runner ejecuta node.json" "runner o node.json no implementados"
fi

# -- R6 · stack.json y panel (CA10, CA11) ----------------------------------
set_stack pass; SOUT="$TMP/stack.json"; rm -f "$SOUT"
SPECS_DIR="$SPECS_FIX" OUTPUT="$TMP/specs2.json" STACKS_DIR="$STKDIR" STACK_OUT="$SOUT" \
  ASDD_CONFIG="$CFG" bash "$GEN" >/dev/null 2>&1
if [ -f "$SOUT" ] && [ "$(jq -r '.active' "$SOUT" 2>/dev/null)" = "pass" ] \
   && [ "$(jq -r '[.available[]|select(.id=="pass")]|length' "$SOUT" 2>/dev/null)" = "1" ] \
   && [ "$(jq -r '.available[0]|has("id") and has("label") and has("a11y")' "$SOUT" 2>/dev/null)" = "true" ]; then
  ok "009-CA10 gen genera stack.json {active, available[{id,label,a11y}]}"
else
  ko "009-CA10 stack.json" "no generado o incompleto"
fi
have 'stack\.json' && have 'init\.sh --stack' \
  && ok "009-CA11 index.html lee stack.json y muestra el comando init.sh --stack" \
  || ko "009-CA11 panel" "index.html no referencia stack.json / init.sh --stack"

# -- RA · accesibilidad de la UI nueva (CA-A1, CA-A2, CA-A3) ----------------
sfg="$(grep -oE '\-\-stack-fg:[[:space:]]*#[0-9a-fA-F]{3,6}' "$INDEX" | grep -oE '#[0-9a-fA-F]{3,6}' | head -1)"
sbg="$(grep -oE '\-\-stack-bg:[[:space:]]*#[0-9a-fA-F]{3,6}' "$INDEX" | grep -oE '#[0-9a-fA-F]{3,6}' | head -1)"
if [ -n "$sfg" ] && [ -n "$sbg" ]; then
  cr="$(contrast "$sfg" "$sbg")"
  ge "$cr" 4.5 && ok "009-CA-A1 contraste distintivo $cr:1 ≥ 4,5" || ko "009-CA-A1 contraste" "$cr:1 < 4,5"
else
  ko "009-CA-A1 contraste" "faltan tokens --stack-fg/--stack-bg"
fi
have 'aria-current' && grep -qE '<(button|a)[^>]*stack' "$INDEX" \
  && ok "009-CA-A2 opciones como button/a + aria-current" \
  || ko "009-CA-A2 botones" "faltan button/a de stack o aria-current"
have 'aria-live' && grep -qiE 'clipboard|execCommand' "$INDEX" \
  && ok "009-CA-A3 aria-live + fallback de copia" \
  || ko "009-CA-A3 anuncio copia" "falta aria-live o fallback de clipboard"

# ---------------------------------------------------------------------------
[ "$fallos" -eq 0 ] && { echo "— tooling OK —"; exit 0; }
echo "$fallos caso(s) fallidos"; exit 1
