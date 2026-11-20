#!/bin/bash
# Genera web/assd_tools/specs.json leyendo el frontmatter de specs/*.md
# Uso: scripts/gen-specs-json.sh

SPECS_DIR="/var/www/d11assd/specs"
OUTPUT="/var/www/d11assd/assd_tools/specs.json"

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

  item=$(jq -n \
    --arg id      "$id"      \
    --arg titulo  "$titulo"  \
    --arg nivel   "$nivel"   \
    --arg tipo    "$tipo"    \
    --arg estado  "$estado"  \
    --arg version "$version" \
    --arg file    "$name"    \
    '{id:$id,titulo:$titulo,nivel:$nivel,tipo:$tipo,estado:$estado,version:$version,file:$file}')

  items+=("$item")
done

printf '%s\n' "${items[@]}" | jq -s 'sort_by(.id)' > "$OUTPUT"
echo "Generado $OUTPUT — $(jq length "$OUTPUT") specs"
