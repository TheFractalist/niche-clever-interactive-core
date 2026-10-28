<?php

// Runs inside the WordPress Apache container. It proves the public HTTP route without relying on
// Docker Desktop's host-port forwarding, which is not part of the candidate itself.
$mode = $argv[1] ?? '';
$port = isset($argv[2]) && ctype_digit($argv[2]) ? (int) $argv[2] : 80;
if ($mode !== 'json' && $mode !== 'sse') {
    throw new RuntimeException('Use json or sse for the fixture HTTP probe.');
}

$accept = $mode === 'sse' ? 'text/event-stream' : 'application/json';
$context = stream_context_create(['http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/json\r\nAccept: {$accept}\r\nHost: 127.0.0.1:{$port}\r\n",
    'content' => '{"question":"What is this fixture?"}',
    'ignore_errors' => true,
    'timeout' => 20,
]]);
$body = file_get_contents('http://127.0.0.1/wp-json/ncli/v1/chat', false, $context);
if (!is_string($body)) {
    throw new RuntimeException('The internal fixture HTTP request did not return a body.');
}
$headers = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
$status = isset($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $matches) === 1 ? (int) $matches[1] : 0;
if ($mode === 'json') {
    $decoded = json_decode($body, true);
    if ($status !== 200 || !is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
        throw new RuntimeException('The JSON fallback did not return a validated answer.');
    }
} else {
    $delta = strpos($body, '"type":"delta"');
    $done = strpos($body, '"type":"done"');
    if ($status !== 200 || $delta === false || $done === false || $delta >= $done) {
        throw new RuntimeException('The SSE route did not emit ordered delta and done events.');
    }
}

echo json_encode(['mode' => $mode, 'status' => $status, 'bytes' => strlen($body)], JSON_UNESCAPED_SLASHES) . PHP_EOL;
