<?php
// Simple proxy that serves manifest.json with proper headers and CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Origin');
header('Vary: Origin');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

$manifestPath = __DIR__ . '/manifest1.json';

if (!file_exists($manifestPath)) {
  http_response_code(404);
  echo json_encode([ 'error' => 'manifest1.json not found' ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

$json = @file_get_contents($manifestPath);
if ($json === false) {
  http_response_code(500);
  echo json_encode([ 'error' => 'failed to read manifest1.json' ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

// Validate JSON or fallback to default structure
$data = @json_decode($json, true);
if (!is_array($data)) {
  http_response_code(500);
  echo json_encode([ 'error' => 'invalid manifest1.json' ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
