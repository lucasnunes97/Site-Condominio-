<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/lib/documento_error.php';
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/document_resolver.php';
require_once __DIR__ . '/lib/ftp_documents.php';
require_once __DIR__ . '/lib/shared_condominio_docs.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

$nifRaw = isset($_GET['nif']) ? (string) $_GET['nif'] : '';
$senhaRaw = isset($_GET['senha']) ? (string) $_GET['senha'] : '';
$docRaw = isset($_GET['doc']) ? (string) $_GET['doc'] : 'dc';
$fileRaw = isset($_GET['file']) ? (string) $_GET['file'] : '';

$nifDigits = preg_replace('/\D/', '', $nifRaw) ?? '';
$senhaTrim = trim($senhaRaw);
$senhaSafe = preg_replace('/[^a-zA-Z0-9]/', '', $senhaTrim) ?? '';
$docCode = strtolower(trim($docRaw));
if (!in_array($docCode, ['dc', 'cq', 'dr', 'fc'], true)) {
    $docCode = 'dc';
}

if ($nifDigits === '' || strlen($nifDigits) !== 9 || !ctype_digit($nifDigits)) {
    documento_render_error('nif_invalid', 400);
    exit;
}

if ($senhaSafe === '' || strlen($senhaSafe) < 1 || strlen($senhaSafe) > 64) {
    documento_render_error('senha_invalid', 400);
    exit;
}

$file = basename(str_replace('\\', '/', trim($fileRaw)));
if ($file === '' || $file === '.' || $file === '..') {
    documento_render_error('file_not_found', 404);
    exit;
}

// Limitar a ficheiros "estáticos" simples para evitar uso indevido.
$ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'css', 'js'];
if (!in_array($ext, $allowed, true)) {
    documento_render_error('file_not_found', 404);
    exit;
}

$year = (int) date('Y');
$config = load_config();
$resolved = document_resolve_document($config, $nifDigits, $year, $senhaSafe, $docCode);
if (!($resolved['ok'] ?? false)) {
    documento_render_error('file_not_found', 404);
    exit;
}

$found = $resolved['found'];
$source = $resolved['source'] ?? 'ftp';

$data = null;
if ($source === 'local') {
    $docPath = (string) ($found['local_path'] ?? '');
    $docReal = $docPath !== '' ? realpath($docPath) : false;
    if ($docReal === false) {
        documento_render_error('download_failed', 502);
        exit;
    }
    $dir = dirname($docReal);
    $assetPath = $dir . DIRECTORY_SEPARATOR . $file;
    $assetReal = is_file($assetPath) ? realpath($assetPath) : false;
    if ($assetReal !== false && is_readable($assetReal) && str_starts_with(str_replace('\\', '/', $assetReal), str_replace('\\', '/', $dir))) {
        $data = file_get_contents($assetReal);
        if ($data === false) {
            $data = null;
        }
    }

    // Fallback: alguns assets (ex.: logo.jpg) podem viver ao lado do documento partilhado do condomínio.
    if ($data === null) {
        $rootBase = dirname($dir); // .../condominios/{ANO}/...
        $yearStr = (string) $year;
        if (basename($dir) === 'Privados' || basename($dir) === 'privados') {
            $rootBase = dirname(dirname($dir));
        } elseif (basename($dir) === $yearStr) {
            $rootBase = dirname($dir);
        }
        $candidates = [];
        $candidates[] = $rootBase;
        // Também suportar instalações em www/condominios/{ANO}/
        $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'condominios';
        $candidates = array_values(array_unique(array_filter($candidates)));

        foreach ($candidates as $candRoot) {
            $rootReal = realpath($candRoot);
            if ($rootReal === false || !is_dir($rootReal)) {
                continue;
            }
            $shared = local_resolve_shared_condominio_document($rootReal, $found, 'dc', $year);
            if (!($shared['ok'] ?? false) || empty($shared['found']['local_path'])) {
                continue;
            }
            $sharedPath = (string) $shared['found']['local_path'];
            $sharedReal = $sharedPath !== '' ? realpath($sharedPath) : false;
            if ($sharedReal === false) {
                continue;
            }
            $sharedDir = dirname($sharedReal);
            $sharedAsset = $sharedDir . DIRECTORY_SEPARATOR . $file;
            $sharedAssetReal = is_file($sharedAsset) ? realpath($sharedAsset) : false;
            if ($sharedAssetReal !== false && is_readable($sharedAssetReal)
                && str_starts_with(str_replace('\\', '/', $sharedAssetReal), str_replace('\\', '/', $sharedDir))) {
                $data = file_get_contents($sharedAssetReal);
                if ($data === false) {
                    $data = null;
                }
                if ($data !== null) {
                    break;
                }
            }
        }
    }

    if ($data === null) {
        documento_render_error('file_not_found', 404);
        exit;
    }
} else {
    $remoteDir = (string) ($found['remote_dir'] ?? '');
    if ($remoteDir === '') {
        documento_render_error('file_not_found', 404);
        exit;
    }
    $data = ftp_download_file_attempts($config['ftp'] ?? [], $remoteDir, $file);
    if ($data === null) {
        // Fallback: assets podem estar no diretório do documento partilhado do condomínio.
        $shared = ftp_resolve_shared_condominio_document($config['ftp'] ?? [], $found, $nifDigits, $senhaSafe, $year, 'dc');
        if (($shared['ok'] ?? false) && !empty($shared['found']['remote_dir'])) {
            $sharedDir = (string) $shared['found']['remote_dir'];
            $data = ftp_download_file_attempts($config['ftp'] ?? [], $sharedDir, $file);
        }
        if ($data === null) {
            documento_render_error('file_not_found', 404);
            exit;
        }
    }
}

$mime = match ($ext) {
    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
    'css' => 'text/css; charset=utf-8',
    'js' => 'application/javascript; charset=utf-8',
    default => 'application/octet-stream',
};

$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file) ?: 'recurso';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $safeName . '"');
header('Content-Length: ' . (string) strlen($data));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');

echo $data;

