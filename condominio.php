<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/lib/documento_error.php';
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/ftp_documents.php';
require_once __DIR__ . '/lib/extract_condominio_anchor.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

/** Caminho absoluto web até à raiz deste script (suporta site num subdiretório). */
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/condominio.php');
$docRootPrefix = preg_replace('#/[^/]+$#', '/', $scriptPath) ?: '/';

function condominio_path_strip_query(string $path): string
{
    $path = preg_replace('/\?.*$/', '', $path) ?? '';
    return str_replace('\\', '/', trim($path));
}

function condominio_is_allowed_shared_doc_filename(string $file): bool
{
    $file = basename(str_replace('\\', '/', trim($file)));
    if ($file === '' || $file === '.' || $file === '..') {
        return false;
    }

    return (bool) preg_match('/^\d{4}-\d{2}-condominio-[a-z0-9-]+(?:-(?:cq|dc|dr|fc))?\.(?:htm|html)$/i', $file);
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

$fileRaw = isset($_GET['file']) ? (string) $_GET['file'] : '';
$file = basename(str_replace('\\', '/', trim($fileRaw)));

if (!condominio_is_allowed_shared_doc_filename($file)) {
    documento_render_error('file_not_found', 404);
    exit;
}

// Ano vem no próprio nome do ficheiro.
$year = 0;
if (preg_match('/^(\d{4})-\d{2}-condominio-/i', $file, $m)) {
    $year = (int) $m[1];
}
if ($year < 2000 || $year > ((int) date('Y') + 1)) {
    $year = (int) date('Y');
}

$config = load_config();
$data = null;
$found = null;

// 1) Tentar disco primeiro (pasta única, sem ano): normalmente /condominios/ ou /www/condominios/ no filesystem.
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
    $lr = local_resolve_shared_by_filename($base, $file, $year);
    if (($lr['ok'] ?? false) && !empty($lr['found']['local_path'])) {
        $found = $lr['found'];
        $rp = realpath((string) $found['local_path']);
        if ($rp !== false && is_readable($rp)) {
            $data = file_get_contents($rp);
        }
        if (is_string($data) && $data !== '') {
            break;
        }
        $data = null;
        $found = null;
    }
}

// 2) Fallback FTP (se local não tiver).
if ($data === null || $data === '') {
    $resolved = ftp_resolve_shared_by_filename($config['ftp'] ?? [], $year, $file);
    if (!($resolved['ok'] ?? false)) {
        $reason = $resolved['reason'] ?? 'file_not_found';
        $status = ($reason === 'config_error' || $reason === 'ftp_error') ? 503 : 404;
        documento_render_error($reason, $status);
        exit;
    }

    $found = $resolved['found'];
    $data = ftp_download_file($config['ftp'] ?? [], $found);
}

if (!is_string($data) || $data === '') {
    documento_render_error('download_failed', 502);
    exit;
}

$ext = strtolower((string) ($found['extension'] ?? pathinfo($file, PATHINFO_EXTENSION)));
$mime = match ($ext) {
    'html', 'htm' => 'text/html; charset=utf-8',
    default => 'application/octet-stream',
};

$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) ($found['filename'] ?? $file)) ?: 'condominio';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $safeName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');

if ($ext === 'html' || $ext === 'htm') {
    $rewrite = static function (string $html) use ($docRootPrefix): string {
        $pattern = '#\b(href|src)\s*=\s*([\'"]?)([^\'"\s>]+)\2#i';

        return preg_replace_callback($pattern, static function (array $m) use ($docRootPrefix): string {
            $attr = $m[1];
            $quote = $m[2] !== '' ? $m[2] : '"';
            $val = $m[3];

            $pathOnly = condominio_path_strip_query($val);
            $base = basename($pathOnly);

            if ($attr === 'href' && condominio_is_allowed_shared_doc_filename($base)) {
                $q = http_build_query(['file' => $base]);
                return $attr . '=' . $quote . $docRootPrefix . 'condominio.php?' . $q . $quote;
            }

            $trim = preg_replace('#^[./]+#', '', $pathOnly) ?? '';
            if ($trim !== '' && preg_match('#^([^/?#]+\.(?:jpg|jpeg|png|gif|webp|svg|ico|css|js))$#i', $trim, $mm)) {
                $q = http_build_query(['doc' => '', 'file' => $mm[1]]);
                return $attr . '=' . $quote . $docRootPrefix . 'recurso_condominio.php?' . $q . $quote;
            }

            return $attr . '=' . $quote . $val . $quote;
        }, $html) ?? $html;
    };

    $data = $rewrite($data);
    $docParam = rawurlencode((string) ($found['filename'] ?? $file));
    $data = preg_replace('/recurso_condominio\.php\?doc=(?:[^&"\']*)(&file=)/i', 'recurso_condominio.php?doc=' . $docParam . '$1', $data) ?? $data;
    header('Content-Length: ' . (string) strlen($data));
} else {
    header('Content-Length: ' . (string) strlen($data));
}

echo $data;

