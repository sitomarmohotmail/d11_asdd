#!/bin/bash
# Actualiza running.json cuando un agente ASDD o un comando de tests empieza o termina.
# Uso (vía hook de Claude Code): cat hook-input.json | scripts/agent-hook.sh start|stop
#
# Stdin: JSON de PreToolUse / PostToolUse con tool_name=Agent|Bash
# Arg 1: "start" (PreToolUse) | "stop" (PostToolUse)

RUNNING_JSON="/var/www/d11asdd/asdd_tools/running.json"

input=$(cat)
tool_name=$(echo "$input" | jq -r '.tool_name // ""')

if [ "$tool_name" = "Agent" ]; then
  subagent=$(echo "$input" | jq -r '.tool_input.subagent_type // ""')
  case "$subagent" in
    asdd-validacion|asdd-seguridad|asdd-accesibilidad) ;;
    *) exit 0 ;;
  esac
  prompt=$(echo "$input" | jq -r '.tool_input.prompt // ""')
  spec_id=$(echo "$prompt" | grep -oE 'specs/[0-9]{3}-' | grep -oE '[0-9]{3}' | head -1)
  [ -z "$spec_id" ] && exit 0

elif [ "$tool_name" = "Bash" ]; then
  command=$(echo "$input" | jq -r '.tool_input.command // ""')
  echo "$command" | grep -qE '(phpunit|check\.sh)' || exit 0
  subagent="tests"
  spec_id=$(echo "$command" | grep -oE 'specs/[0-9]{3}-' | grep -oE '[0-9]{3}' | head -1)
  [ -z "$spec_id" ] && spec_id="__global__"

else
  exit 0
fi

# Crear running.json si no existe o está vacío/corrupto
[ -f "$RUNNING_JSON" ] || echo '{}' > "$RUNNING_JSON"
jq empty "$RUNNING_JSON" 2>/dev/null || echo '{}' > "$RUNNING_JSON"

ACTION="${1:-start}"

if [ "$ACTION" = "start" ]; then
  jq --arg sid "$spec_id" --arg agent "$subagent" \
    '.[$sid] = ((.[$sid] // []) + [$agent] | unique)' \
    "$RUNNING_JSON" > "${RUNNING_JSON}.tmp" && mv "${RUNNING_JSON}.tmp" "$RUNNING_JSON"
else
  jq --arg sid "$spec_id" --arg agent "$subagent" \
    'if .[$sid] then
       .[$sid] = (.[$sid] | map(select(. != $agent)))
       | if .[$sid] | length == 0 then del(.[$sid]) else . end
     else . end' \
    "$RUNNING_JSON" > "${RUNNING_JSON}.tmp" && mv "${RUNNING_JSON}.tmp" "$RUNNING_JSON"
fi
