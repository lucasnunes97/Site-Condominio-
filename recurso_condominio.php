<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/lib/documento_error.php';
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/ftp_documents.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

function recurso_condominio_is_allowed_shared_doc_filename(string $file): bool
{
    $file = basename(str_replace('\\', '/', trim($file)));
    if ($file === '' || $file === '.' || $file === '..') {
        return false;
    }
    return (bool) preg_match('/^\d{4}-\d{2}-condominio-[a-z0-9-]+(?:-(?:cq|dc|dr|fc))?\.(?:htm|html)$/i', $file);
}

/**
 * Resolve um ficheiro partilhado por nome (sem NIF) no FTP.
 *
 * @return array{ok:true,found:array}|array{ok:false,reason:string}
 */
function ftp_resolve_shared_by_filename(array $ftpConfig, int $year, string $fileName): array
{
    $host = $ftpConfig['host'] ?? '';
    $user = $ftpConfig['username'] ?? '';
    $pass = $ftpConfig['password'] ?? '';
    $timeout = (int) ($ftpConfig['timeout'] ?? 30);

    if ($host === '' || $user === '' || $pass === '' || $pass === 'SUA_SENHA_FTP') {
        return ['ok' => false, 'reason' => 'config_error'];
    }

    $passiveConfigured = (bool) ($ftpConfig['passive'] ?? false);
    $yearDirs = ftp_year_directory_candidates($ftpConfig['base_path'] ?? 'condominios', $year);
    $flatBases = ftp_flat_base_directory_candidates($ftpConfig['base_path'] ?? 'condominios');

    $loginOk = false;

    foreach (ftp_passive_attempts($passiveConfigured) as $passive) {
        $conn = @ftp_connect($host, 21, $timeout);
        if ($conn === false) {
            continue;
        }
        if (!@ftp_login($conn, $user, $pass)) {
            ftp_safe_close($conn);
            continue;
        }
        $loginOk = true;
        ftp_pasv($conn, $passive);

        $found = null;
        foreach (array_merge($yearDirs, $flatBases) as $dir) {
            if (!@ftp_chdir($conn, $dir)) {
                continue;
            }
            $list = @ftp_nlist($conn, '.');
            if ($list === false) {
                continue;
            }
            $hit = ftp_find_named_file_in_list($list, $dir, $fileName, '');
            if ($hit !== null) {
                $found = $hit;
                break;
            }
        }

        ftp_safe_close($conn);

        if ($found !== null) {
            $found['search_year'] = $year;
            return ['ok' => true, 'found' => $found];
        }
    }

    if (!$loginOk) {
        return ['ok' => false, 'reason' => 'ftp_error'];
    }

    return ['ok' => false, 'reason' => 'file_not_found'];
}

/**
 * Resolve um ficheiro partilhado por nome (sem NIF) em disco, dentro de uma pasta base (sem subpastas por ano).
 *
 * @return array{ok:true,found:array}|array{ok:false,reason:string}
 */
function local_resolve_shared_by_filename(string $localBase, string $fileName, int $year): array
{
    $base = realpath($localBase);
    if ($base === false || !is_dir($base)) {
        return ['ok' => false, 'reason' => 'local_storage_missing'];
    }

    $candidate = $base . DIRECTORY_SEPARATOR . $fileName;
    $real = is_file($candidate) ? realpath($candidate) : false;
    if ($real === false || !is_readable($real) || !str_starts_with(str_replace('\\', '/', $real), str_replace('\\', '/', $base))) {
        return ['ok' => false, 'reason' => 'file_not_found'];
    }

    $ext = strtolower((string) pathinfo($real, PATHINFO_EXTENSION));
    return [
        'ok' => true,
        'found' => [
            'extension'   => $ext,
            'filename'    => basename($real),
            'local_path'  => $real,
            'nif_digits'  => '',
            'remote_dir'  => '',
            'remote_file' => '',
            'search_year' => $year,
        ],
    ];
}

$docRaw = isset($_GET['doc']) ? (string) $_GET['doc'] : '';
$fileRaw = isset($_GET['file']) ? (string) $_GET['file'] : '';

$doc = basename(str_replace('\\', '/', trim($docRaw)));
$file = basename(str_replace('\\', '/', trim($fileRaw)));

if (!recurso_condominio_is_allowed_shared_doc_filename($doc)) {
    documento_render_error('file_not_found', 404);
    exit;
}

if ($file === '' || $file === '.' || $file === '..') {
    documento_render_error('file_not_found', 404);
    exit;
}

$ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'css', 'js'];
if (!in_array($ext, $allowed, true)) {
    documento_render_error('file_not_found', 404);
    exit;
}

$year = 0;
if (preg_match('/^(\d{4})-\d{2}-condominio-/i', $doc, $m)) {
    $year = (int) $m[1];
}
if ($year < 2000 || $year > ((int) date('Y') + 1)) {
    $year = (int) date('Y');
}

$config = load_config();
$data = null;

// 1) Tentar disco: asset ao lado do documento shared.
$localBases = [
    __DIR__ . DIRECTORY_SEPARATOR . 'condominios',
    __DIR__ . DIRECTORY_SEPARATOR . 'Condominios',
    __DIR__ . DIRECTORY_SEPARATOR . 'CONDOMINIOS',
    __DIR__ . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'condominios',
    __DIR__ . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'Condominios',
    __DIR__ . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'CONDOMINIOS',
];
if (isset($config['local_condominios_path']) && is_string($config['local_condominios_path']) && $config['local_condominios_path'] !== '') {
    array_unshift($localBases, $config['local_condominios_path']);
}
$localBases = array_values(array_unique(array_filter($localBases)));

foreach ($localBases as $base) {
    if (!is_dir($base)) {
        continue;
    }
    $lr = local_resolve_shared_by_filename($base, $doc, $year);
    if (!($lr['ok'] ?? false) || empty($lr['found']['local_path'])) {
        continue;
    }
    $docPath = (string) $lr['found']['local_path'];
    $docReal = $docPath !== '' ? realpath($docPath) : false;
    if ($docReal === false) {
        continue;
    }
    $dir = dirname($docReal);
    $assetPath = $dir . DIRECTORY_SEPARATOR . $file;
    $assetReal = is_file($assetPath) ? realpath($assetPath) : false;
    if ($assetReal !== false && is_readable($assetReal) && str_starts_with(str_replace('\\', '/', $assetReal), str_replace('\\', '/', $dir))) {
        $data = file_get_contents($assetReal);
        if ($data === false) {
            $data = null;
        }
        if ($data !== null && $data !== '') {
            break;
        }
    }
}

// 2) Fallback FTP.
if ($data === null || $data === '') {
    $resolved = ftp_resolve_shared_by_filename($config['ftp'] ?? [], $year, $doc);
    if (!($resolved['ok'] ?? false)) {
        documento_render_error('file_not_found', 404);
        exit;
    }

    $found = $resolved['found'];
    $remoteDir = (string) ($found['remote_dir'] ?? '');
    if ($remoteDir === '') {
        documento_render_error('file_not_found', 404);
        exit;
    }

    $data = ftp_download_file_attempts($config['ftp'] ?? [], $remoteDir, $file);
}

if (!is_string($data) || $data === '') {
    documento_render_error('file_not_found', 404);
    exit;
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

