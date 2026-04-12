<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/lib/documento_error.php';
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/document_resolver.php';

/** Anos aceites na consulta (alinhado com as mensagens de erro). */
const DOCUMENTO_ANO_MIN = 2000;
const DOCUMENTO_ANO_MAX = 2050;

while (ob_get_level() > 0) {
    ob_end_clean();
}

$nifRaw = isset($_GET['nif']) ? (string) $_GET['nif'] : '';
$anoRaw = isset($_GET['ano']) ? trim((string) $_GET['ano']) : '';

$nifDigits = preg_replace('/\D/', '', $nifRaw) ?? '';

if ($nifDigits === '' || strlen($nifDigits) !== 9 || !ctype_digit($nifDigits)) {
    documento_render_error('nif_invalid', 400);
    exit;
}

if ($anoRaw === '' || !preg_match('/^\d{4}$/', $anoRaw)) {
    documento_render_error('year_invalid', 400, ['year_min' => DOCUMENTO_ANO_MIN, 'year_max' => DOCUMENTO_ANO_MAX]);
    exit;
}

$year = (int) $anoRaw;
if ($year < DOCUMENTO_ANO_MIN || $year > DOCUMENTO_ANO_MAX) {
    documento_render_error('year_invalid', 400, ['year_min' => DOCUMENTO_ANO_MIN, 'year_max' => DOCUMENTO_ANO_MAX]);
    exit;
}

if ($year > (int) date('Y')) {
    documento_render_error('year_not_available', 404, ['year' => $year, 'current_year' => (int) date('Y')]);
    exit;
}

$config = load_config();
$resolved = document_resolve_document($config, $nifDigits, $year);

if (!($resolved['ok'] ?? false)) {
    $reason = $resolved['reason'] ?? 'ftp_error';
    $status = match ($reason) {
        'config_error' => 503,
        'local_storage_missing' => 503,
        'ftp_error' => 503,
        'nif_not_found' => 404,
        'file_not_found' => 404,
        default => 503,
    };
    documento_render_error($reason, $status);
    exit;
}

$found = $resolved['found'];
$source = $resolved['source'] ?? 'ftp';

if ($source === 'local') {
    $path = (string) ($found['local_path'] ?? '');
    $real = $path !== '' ? realpath($path) : false;
    if ($real === false || !is_readable($real)) {
        documento_render_error('download_failed', 502);
        exit;
    }
    $data = file_get_contents($real);
    if ($data === false) {
        documento_render_error('download_failed', 502);
        exit;
    }
} else {
    $data = ftp_download_file($config['ftp'] ?? [], $found);
    if ($data === null) {
        documento_render_error('download_failed', 502);
        exit;
    }
}

$ext = $found['extension'];
$mime = match ($ext) {
    'pdf' => 'application/pdf',
    'html', 'htm' => 'text/html; charset=utf-8',
    default => 'application/octet-stream',
};

$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $found['filename']) ?: 'documento';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $safeName . '"');
header('Content-Length: ' . (string) strlen($data));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');

echo $data;
