#!/bin/bash
# Genera assd_tools/specs.json leyendo el frontmatter de specs/*.md
# Uso: scripts/gen-specs-json.sh
# Las rutas se leen de assd_tools/config.json (relativas a ese directorio).
# Se pueden sobreescribir con variables de entorno: SPECS_DIR, OUTPUT.

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
TOOLS_DIR="$SCRIPT_DIR/../assd_tools"
CONFIG="$TOOLS_DIR/config.json"

if [ -f "$CONFIG" ] && command -v jq &>/dev/null; then
  SPECS_REL=$(jq -r '.specs_dir  // "../specs"'   "$CONFIG")
  OUT_FILE=$( jq -r '.output_json // "specs.json"' "$CONFIG")
  SPECS_DIR="${SPECS_DIR:-$(cd "$TOOLS_DIR/$SPECS_REL" && pwd)}"
  OUTPUT="${OUTPUT:-$TOOLS_DIR/$OUT_FILE}"
else
  SPECS_DIR="${SPECS_DIR:-$SCRIPT_DIR/../specs}"
  OUTPUT="${OUTPUT:-$TOOLS_DIR/specs.json}"
fi

items=()

for f in "$SPECS_DIR"/[0-9][0-9][0-9]-*.md; do
  name=$(basename "$f")
  # Ignorar informes de agentes y ficheros especiales
  case "$name" in *-qa.md|*-validacion.md|*-seguridad.md|*-accesibilidad.md) continue ;; esac

  # Leer solo el bloque frontmatter (entre los dos ---)
  fm=$(awk '/^---/{f=!f; next} f' "$f" | head -20)

  id=$(     echo "$fm" | grep -m1 '^id:'      | sed 's/^id: *//; s/"//g; s/^ *//; s/ *$//')
  titulo=$( echo "$fm" | grep -m1 '^titulo:'  | sed 's/^titulo: *//; s/"//g; s/^ *//; s/ *$//')
  nivel=$(  echo "$fm" | grep -m1 '^nivel:'   | sed 's/^nivel: *//; s/"//g; s/^ *//; s/ *$//')
  tipo=$(   echo "$fm" | grep -m1 '^tipo:'    | sed 's/^tipo: *//; s/"//g; s/^ *//; s/ *$//')
  estado=$( echo "$fm" | grep -m1 '^estado:'  | sed 's/^estado: *//; s/"//g; s/^ *//; s/ *$//')
  version=$(echo "$fm" | grep -m1 '^version:' | sed 's/^version: *//; s/"//g; s/^ *//; s/ *$//')

  [ -z "$id" ] && continue

  # Detectar puertas de calidad superadas (existen informes NNN-*.md)
  num="${name%%[^0-9]*}"   # primeros 3 dígitos
  gates_arr=()
  [ -f "$SPECS_DIR/${num}-validacion.md" ]    && gates_arr+=("val")
  [ -f "$SPECS_DIR/${num}-qa.md" ]            && gates_arr+=("qa")
  [ -f "$SPECS_DIR/${num}-seguridad.md" ]     && gates_arr+=("seg")
  [ -f "$SPECS_DIR/${num}-accesibilidad.md" ] && gates_arr+=("a11y")
  if [ ${#gates_arr[@]} -gt 0 ]; then
    gates_json=$(printf '%s\n' "${gates_arr[@]}" | jq -R . | jq -s .)
  else
    gates_json='[]'
  fi

  item=$(jq -n \
    --arg id      "$id"      \
    --arg titulo  "$titulo"  \
    --arg nivel   "$nivel"   \
    --arg tipo    "$tipo"    \
    --arg estado  "$estado"  \
    --arg version "$version" \
    --arg file    "$name"    \
    --argjson gates "$gates_json" \
    '{id:$id,titulo:$titulo,nivel:$nivel,tipo:$tipo,estado:$estado,version:$version,file:$file,gates:$gates}')

  items+=("$item")
done

printf '%s\n' "${items[@]}" | jq -s 'sort_by(.id)' > "$OUTPUT"
echo "Generado $OUTPUT — $(jq length "$OUTPUT") specs"
