<?php

declare(strict_types=1);

session_start();

$nif = isset($_SESSION['u_nif']) ? preg_replace('/\D/', '', (string) $_SESSION['u_nif']) : '';
$slug = isset($_SESSION['u_condo_slug']) ? trim((string) $_SESSION['u_condo_slug']) : '';

$year = (string) date('Y');
$base = realpath(__DIR__ . '/condominios');
$yearDir = false;
if ($base !== false) {
    $yearDir = realpath($base . DIRECTORY_SEPARATOR . $year);
}

$buildingFiles = [];
$privateFiles = [];

if ($nif !== '' && strlen($nif) === 9 && $slug !== '' && $yearDir !== false && $base !== false
    && str_starts_with(str_replace('\\', '/', $yearDir), str_replace('\\', '/', $base))) {
    $prefix = $slug . '__';
    $globDirs = [$yearDir];
    foreach (['Privados', 'privados'] as $sub) {
        $pd = $yearDir . DIRECTORY_SEPARATOR . $sub;
        if (is_dir($pd)) {
            $globDirs[] = realpath($pd) ?: $pd;
        }
    }

    foreach ($globDirs as $scanDir) {
        if (!is_dir($scanDir)) {
            continue;
        }
        $scanReal = realpath((string) $scanDir);
        if ($scanReal === false || !str_starts_with(str_replace('\\', '/', $scanReal), str_replace('\\', '/', $yearDir))) {
            continue;
        }

        $pattern = rtrim($scanReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $prefix . '*';
        $matches = glob($pattern, GLOB_NOSORT);
        if ($matches === false) {
            continue;
        }

        foreach ($matches as $fullPath) {
            if (!is_file($fullPath)) {
                continue;
            }
            $rp = realpath($fullPath);
            if ($rp === false || !str_starts_with(str_replace('\\', '/', $rp), str_replace('\\', '/', $scanReal))) {
                continue;
            }

            $name = basename($rp);
            $lower = strtolower($name);
            if (!preg_match('/\.(htm|html|pdf)$/i', $lower)) {
                continue;
            }

            if (!str_starts_with($name, $prefix)) {
                continue;
            }

            $after = substr($name, strlen($prefix));
            $stem = pathinfo($after, PATHINFO_FILENAME);

            // Pessoal: após o prefixo, o nome base é só algarismos (ex.: NIF ou identificador numérico).
            if (preg_match('/^\d+$/', (string) $stem) === 1) {
                if ($stem === $nif) {
                    $privateFiles[] = $name;
                }
                continue;
            }

            $buildingFiles[] = $name;
        }
    }

    sort($buildingFiles);
    sort($privateFiles);
    $buildingFiles = array_values(array_unique($buildingFiles));
    $privateFiles = array_values(array_unique($privateFiles));
}

$err = isset($_SESSION['portal_error']) ? (string) $_SESSION['portal_error'] : '';
unset($_SESSION['portal_error']);

?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Área do condómino — documentos</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="doc-error-body">
  <div class="wrap wrap--section">
    <header class="section-head">
      <div>
        <h1 class="section-head__title">Documentos do condomínio</h1>
        <p class="section-head__lead">Acesso por sessão: após login, lista apenas ficheiros com o prefixo do seu condomínio dentro de <code>condominios/<?= htmlspecialchars((string) date('Y'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/</code><?php if ($slug !== ''): ?> (ex.: <code><?= htmlspecialchars($slug . '__', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></code>)<?php endif; ?>.</p>
      </div>
    </header>

    <?php if ($nif === '' || $slug === ''): ?>
      <?php if ($err !== ''): ?>
        <div class="msg err"><?= htmlspecialchars($err, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <?php endif; ?>
      <div class="card card--condo">
        <form method="post" action="portal_login.php" class="form-grid form-grid--condo">
          <label>
            NIF do condómino
            <input type="text" name="nif" inputmode="numeric" autocomplete="off" required maxlength="9" pattern="\d{9}">
          </label>
          <label>
            Senha
            <input type="password" name="senha" autocomplete="off" required maxlength="128">
          </label>
          <div class="form-actions">
            <button type="submit" class="btn btn--primary">Entrar</button>
          </div>
        </form>
      </div>
    <?php else: ?>
      <p class="intro-box">Condomínio: <strong><?= htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong> · NIF: <strong><?= htmlspecialchars($nif, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></p>
      <p><a href="portal_logout.php">Sair</a></p>

      <div class="seguros-layout">
        <div class="card card--form">
          <h2 class="side-card__title">Documentos do prédio</h2>
          <?php if ($buildingFiles === []): ?>
            <p>Não há documentos nesta pasta para o ano <?= htmlspecialchars($year, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>.</p>
          <?php else: ?>
            <ul class="check-list">
              <?php foreach ($buildingFiles as $f): ?>
                <?php
                  $q = http_build_query(['scope' => 'building', 'file' => $f]);
                ?>
                <li><a href="portal_download.php?<?= htmlspecialchars($q, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($f, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

        <div class="card card--form">
          <h2 class="side-card__title">Documentos pessoais</h2>
          <?php if ($privateFiles === []): ?>
            <p>Não há documentos privados com o seu NIF no nome.</p>
          <?php else: ?>
            <ul class="check-list">
              <?php foreach ($privateFiles as $f): ?>
                <?php
                  $q = http_build_query(['scope' => 'private', 'file' => $f]);
                ?>
                <li><a href="portal_download.php?<?= htmlspecialchars($q, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($f, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
