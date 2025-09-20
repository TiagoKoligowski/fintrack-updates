<?php
// Painel simples para subir nova atualização e atualizar o manifest.json
// Protegido por Basic Auth. Configure credenciais em config.php (copie do config.sample.php).

declare(strict_types=1);

// Carrega config
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
  header('Content-Type: text/plain; charset=utf-8');
  http_response_code(500);
  echo "Config ausente.\n\n";
  echo "Crie update-site/config.php a partir de update-site/config.sample.php e ajuste as credenciais.";
  exit;
}
require_once $configPath;

// Autenticação básica
function require_basic_auth(): void {
  $user = $_SERVER['PHP_AUTH_USER'] ?? null;
  $pass = $_SERVER['PHP_AUTH_PW'] ?? null;

  if ($user === null || $pass === null || $user !== ADMIN_USER || $pass !== ADMIN_PASS) {
    header('WWW-Authenticate: Basic realm="Fintrack Update Admin"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Acesso restrito.';
    exit;
  }
}
require_basic_auth();

// Sessão para CSRF token
@session_start();
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

// Helpers
function base_public_url(): string {
  if (!empty(BASE_PUBLIC_URL)) return rtrim(BASE_PUBLIC_URL, '/');
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
  $scheme = $isHttps ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
  return $scheme . '://' . $host . $dir;
}

function ensure_releases_dir(): string {
  $dir = __DIR__ . '/releases';
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
  return $dir;
}

function sanitize_filename(string $name): string {
  // Remove caracteres perigosos e espaços, mantendo . _ - letras e números
  $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
  return trim($name, '._-') ?: 'file';
}

function json_read(string $path): array {
  if (!file_exists($path)) return [];
  $raw = @file_get_contents($path);
  if ($raw === false) return [];
  $data = @json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function json_write(string $path, array $data): bool {
  $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
  return @file_put_contents($path, $json) !== false;
}

$errors = [];
$success = null;
$manifestPath = __DIR__ . '/manifest.json';
$currentManifest = json_read($manifestPath);
$currentLatest = $currentManifest['latest'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF
  if (($_POST['csrf'] ?? '') !== $csrf) {
    $errors[] = 'Token CSRF inválido. Recarregue a página.';
  }

  $version = trim((string)($_POST['version'] ?? ''));
  $notes = (string)($_POST['notes'] ?? '');
  if ($version === '') {
    $errors[] = 'Informe a versão.';
  }

  // Upload
  if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    $errors[] = 'Selecione o arquivo do instalador (.exe, .zip, .msi).';
  }

  if (!$errors) {
    $file = $_FILES['file'];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
      $errors[] = 'Falha no upload (código ' . ((int)$file['error']) . ').';
    } else {
      $sizeMax = (int)(MAX_UPLOAD_MB * 1024 * 1024);
      if ($file['size'] > $sizeMax) {
        $errors[] = 'Arquivo excede o limite de ' . MAX_UPLOAD_MB . ' MB.';
      }
      $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ALLOWED_EXTS, true)) {
        $errors[] = 'Extensão não permitida. Permitidas: ' . implode(', ', ALLOWED_EXTS) . '.';
      }
    }
  }

  if (!$errors) {
    $releasesDir = ensure_releases_dir();
    if (!is_dir($releasesDir) || !is_writable($releasesDir)) {
      $errors[] = 'Diretório releases/ indisponível ou sem permissão de escrita.';
    }
  }

  if (!$errors) {
    $ext = strtolower(pathinfo((string)$_FILES['file']['name'], PATHINFO_EXTENSION));
    $baseName = 'Fintrack_Update_' . $version . '.' . $ext;
    $safeName = sanitize_filename($baseName);
    $destPath = ensure_releases_dir() . '/' . $safeName;
    if (file_exists($destPath)) {
      $safeName = 'Fintrack_Update_' . $version . '_' . time() . '.' . $ext;
      $destPath = ensure_releases_dir() . '/' . $safeName;
    }

    if (!@move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
      $errors[] = 'Não foi possível mover o arquivo para releases/.';
    } else {
      @chmod($destPath, 0644);

      $downloadUrl = base_public_url() . '/releases/' . rawurlencode($safeName);

      // Atualiza manifest
      $manifest = json_read($manifestPath);
      $manifest['latest'] = $version;
      $manifest['download'] = $downloadUrl;
      $manifest['notes'] = $notes;
      if (!json_write($manifestPath, $manifest)) {
        $errors[] = 'Upload ok, mas falha ao salvar manifest.json.';
      } else {
        $success = [
          'version' => $version,
          'download' => $downloadUrl,
        ];
        $currentLatest = $version;
      }
    }
  }
}

?><!doctype html>
<html lang="pt-br">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upload de Atualização — Fintrack</title>
    <style>
      body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; background:#0b0f12; color:#e9eef2; margin:0; }
      .container { max-width: 800px; margin: 40px auto; padding: 0 16px; }
      .card { background:#121a22; border:1px solid #1f2a33; border-radius:12px; padding:20px; }
      .muted { color:#9fb0bf; }
      label { display:block; margin:12px 0 6px; }
      input[type=text], textarea { width:100%; padding:10px; border-radius:8px; border:1px solid #233140; background:#0e141b; color:#e9eef2; }
      input[type=file] { margin-top:8px; }
      button { margin-top:16px; padding:10px 14px; border-radius:8px; border:0; background:#3b82f6; color:#fff; font-weight:600; cursor:pointer; }
      .alert { padding:12px; border-radius:8px; margin-bottom:12px; }
      .alert-error { background:#3b1e24; border:1px solid #6b1a24; color:#fecaca; }
      .alert-success { background:#0f2a1c; border:1px solid #14532d; color:#bbf7d0; }
      code { background:#0e141b; padding:2px 6px; border-radius:6px; }
      a { color:#93c5fd; }
    </style>
  </head>
  <body>
    <div class="container">
      <h1>Upload de Atualização</h1>
      <div class="card">
        <p class="muted">Versão atual no manifesto: <strong><?= htmlspecialchars($currentLatest ?: '—') ?></strong></p>
        <p class="muted">Manifesto JSON: <code><?= htmlspecialchars(base_public_url() . '/manifest.php') ?></code></p>

        <?php if ($errors): ?>
          <div class="alert alert-error">
            <strong>Erros:</strong>
            <ul>
              <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="alert alert-success">
            <strong>Manifesto atualizado com sucesso!</strong><br>
            Versão: <?= htmlspecialchars($success['version']) ?><br>
            Download: <a href="<?= htmlspecialchars($success['download']) ?>" target="_blank" rel="noreferrer">abrir</a>
          </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

          <label for="version">Versão (ex.: 0.1.1)</label>
          <input type="text" id="version" name="version" required placeholder="0.1.1" value="<?= isset($_POST['version']) ? htmlspecialchars((string)$_POST['version']) : '' ?>">

          <label for="notes">Notas (opcional)</label>
          <textarea id="notes" name="notes" rows="5" placeholder="Correções e melhorias..."><?= isset($_POST['notes']) ? htmlspecialchars((string)$_POST['notes']) : '' ?></textarea>

          <label for="file">Arquivo do instalador (.exe, .zip, .msi) — até <?= (int)MAX_UPLOAD_MB ?> MB</label>
          <input type="file" id="file" name="file" accept=".exe,.zip,.msi" required>

          <button type="submit">Enviar atualização</button>
        </form>
      </div>
    </div>
  </body>
  </html>

