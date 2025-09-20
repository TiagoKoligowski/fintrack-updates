<?php
$manifestPath = __DIR__ . '/manifest.json';
$manifest = [
  'latest' => '0.1.0',
  'download' => '',
  'notes' => ''
];
if (file_exists($manifestPath)) {
  $json = @file_get_contents($manifestPath);
  if ($json !== false) {
    $data = @json_decode($json, true);
    if (is_array($data)) { $manifest = array_merge($manifest, $data); }
  }
}
?>
<!doctype html>
<html lang="pt-br">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atualizações • Fintrack</title>
    <style>
      body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; background:#0b0f12; color:#e9eef2; margin:0; }
      .container { max-width: 800px; margin: 40px auto; padding: 0 16px; }
      .card { background:#121a22; border:1px solid #1f2a33; border-radius:12px; padding:20px; }
      .muted { color:#9fb0bf; }
      .btn { display:inline-block; padding:10px 14px; border-radius:8px; text-decoration:none; font-weight:600; }
      .btn-primary { background:#3b82f6; color:white; }
      pre { white-space: pre-wrap; }
    </style>
  </head>
  <body>
    <div class="container">
      <h1>Atualizações • Fintrack</h1>
      <div class="card">
        <div class="muted">Versão mais recente</div>
        <h2><?= htmlspecialchars($manifest['latest']) ?></h2>
        <?php if (!empty($manifest['download'])): ?>
          <p><a class="btn btn-primary" href="<?= htmlspecialchars($manifest['download']) ?>">Baixar instalador</a></p>
        <?php else: ?>
          <p class="muted">URL do instalador não configurada.</p>
        <?php endif; ?>
        <?php if (!empty($manifest['notes'])): ?>
          <h3>Notas</h3>
          <pre><?= htmlspecialchars($manifest['notes']) ?></pre>
        <?php endif; ?>
        <hr>
        <div class="muted">
          Manifesto JSON: <code><?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http'). '://'. $_SERVER['HTTP_HOST']. rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'). '/manifest.json') ?></code>
        </div>
      </div>
    </div>
  </body>
  </html>

