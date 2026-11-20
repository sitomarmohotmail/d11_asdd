<?php
// Endpoint SSE para el chat integrado en el dashboard ASSD.
// Lee la API key de: 1) variable de entorno ANTHROPIC_API_KEY
//                    2) fichero assd_tools/.api-key (una línea, no versionado)
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
if (ob_get_level()) ob_end_clean();

function sse(string $event, string $data): void {
    echo "event: {$event}\ndata: " . json_encode($data) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

$api_key = getenv('ANTHROPIC_API_KEY') ?: '';
if (!$api_key) {
    $key_file = __DIR__ . '/.api-key';
    if (file_exists($key_file)) $api_key = trim(file_get_contents($key_file));
}

if (!$api_key) {
    sse('error', 'API key no configurada. Crea el fichero assd_tools/.api-key con tu clave de Anthropic.');
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$messages = $body['messages'] ?? [];
if (empty($messages)) { sse('error', 'Sin mensajes.'); exit; }

// System prompt con contexto del proyecto
$claude_md = @file_get_contents(__DIR__ . '/../CLAUDE.md') ?: '';
$system = "Eres un asistente experto en este proyecto Drupal 11 con ASDD (Agentic Spec-Driven Development).\n\n"
        . "## CLAUDE.md del proyecto\n{$claude_md}\n\n"
        . "Responde en español. Sé conciso y directo.";

$payload = json_encode([
    'model'      => 'claude-sonnet-4-5',
    'max_tokens' => 8096,
    'system'     => $system,
    'messages'   => $messages,
    'stream'     => true,
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) {
        foreach (explode("\n", $chunk) as $line) {
            if (!str_starts_with($line, 'data: ')) continue;
            $data = json_decode(substr($line, 6), true);
            if (!$data) continue;
            if ($data['type'] === 'content_block_delta' && isset($data['delta']['text'])) {
                sse('delta', $data['delta']['text']);
            }
            if ($data['type'] === 'message_stop') {
                sse('done', '');
            }
        }
        return strlen($chunk);
    },
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_TIMEOUT        => 120,
]);
curl_exec($ch);
if (curl_errno($ch)) sse('error', curl_error($ch));
curl_close($ch);
