<?php

declare(strict_types=1);

session_start();

$nif = isset($_SESSION['u_nif']) ? preg_replace('/\D/', '', (string) $_SESSION['u_nif']) : '';
$slug = isset($_SESSION['u_condo_slug']) ? trim((string) $_SESSION['u_condo_slug']) : '';

$fileRaw = isset($_GET['file']) ? (string) $_GET['file'] : '';
$scope = isset($_GET['scope']) ? strtolower(trim((string) $_GET['scope'])) : '';

if ($nif === '' || strlen($nif) !== 9 || $slug === '') {
    http_response_code(403);
    exit;
}

$file = basename(str_replace('\\', '/', $fileRaw));
if ($file === '' || $file === '.' || $file === '..') {
    http_response_code(400);
    exit;
}

$ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
if (!in_array($ext, ['htm', 'html', 'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'css', 'js'], true)) {
    http_response_code(400);
    exit;
}

$year = (string) date('Y');
$base = realpath(__DIR__ . '/condominios');
if ($base === false) {
    http_response_code(500);
    exit;
}

$yearDir = realpath($base . DIRECTORY_SEPARATOR . $year);
if ($yearDir === false || !str_starts_with(str_replace('\\', '/', $yearDir), str_replace('\\', '/', $base))) {
    http_response_code(404);
    exit;
}

$prefix = $slug . '__';
if (!str_starts_with($file, $prefix)) {
    http_response_code(403);
    exit;
}

$after = substr($file, strlen($prefix));
$stemAfter = pathinfo($after, PATHINFO_FILENAME);

$allowedRoots = [$yearDir];
foreach (['Privados', 'privados'] as $sub) {
    $pd = realpath($yearDir . DIRECTORY_SEPARATOR . $sub);
    if ($pd !== false && str_starts_with(str_replace('\\', '/', $pd), str_replace('\\', '/', $yearDir))) {
        $allowedRoots[] = $pd;
    }
}

$path = '';
foreach ($allowedRoots as $root) {
    $candidate = $root . DIRECTORY_SEPARATOR . $file;
    $rp = is_file($candidate) ? realpath($candidate) : false;
    if ($rp === false) {
        continue;
    }
    if (!str_starts_with(str_replace('\\', '/', $rp), str_replace('\\', '/', $root))) {
        continue;
    }
    $path = $rp;
    break;
}

if ($path === '' || $path === false) {
    http_response_code(404);
    exit;
}

$isPersonalCandidate = preg_match('/^\d+$/', (string) $stemAfter) === 1;

if ($scope === 'private') {
    if (!$isPersonalCandidate || (string) $stemAfter !== $nif) {
        http_response_code(403);
        exit;
    }
} elseif ($scope === 'building') {
    if ($isPersonalCandidate) {
        http_response_code(403);
        exit;
    }
} else {
    http_response_code(400);
    exit;
}

$data = file_get_contents($path);
if ($data === false) {
    http_response_code(502);
    exit;
}

$mime = match ($ext) {
    'pdf' => 'application/pdf',
    'htm', 'html' => 'text/html; charset=utf-8',
    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'css' => 'text/css; charset=utf-8',
    'js' => 'application/javascript; charset=utf-8',
    default => 'application/octet-stream',
};

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($path) . '"');
header('Content-Length: ' . (string) strlen($data));
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, max-age=0');

echo $data;
