<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/lib/documento_error.php';
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/document_resolver.php';
require_once __DIR__ . '/lib/extract_condominio_anchor.php';
require_once __DIR__ . '/lib/shared_condominio_docs.php';
require_once __DIR__ . '/lib/ftp_documents.php';

/** Caminho absoluto web até à raiz deste script (suporta site num subdiretório). */
$docScriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/documento.php');
$docRootPrefix = preg_replace('#/[^/]+$#', '/', $docScriptPath) ?: '/';

/** Path-only para reescrita (http(s), //host ou relativo). */
function documento_url_path_only(string $val): string
{
    $val = trim($val);
    if ($val === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $val)) {
        return (string) (parse_url($val, PHP_URL_PATH) ?? '');
    }
    if (str_starts_with($val, '//')) {
        return (string) (parse_url('http:' . $val, PHP_URL_PATH) ?? '');
    }

    // Relativo: parse_url não separa bien ?query — tirar query aqui.
    return (string) preg_replace('/\?.*$/', '', $val);
}

function documento_request_host_normalized(): string
{
    $h = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $h = strtolower(preg_replace('/:\d+$/', '', $h) ?? '');

    return $h;
}

/** URL absoluta para o mesmo host do pedido actual → path (/…); caso contrário null. */
function documento_same_origin_path(string $val): ?string
{
    $val = trim($val);
    if ($val === '') {
        return null;
    }
    $want = documento_request_host_normalized();
    if ($want === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $val)) {
        $h = strtolower((string) (parse_url($val, PHP_URL_HOST) ?? ''));
        if ($h !== $want) {
            return null;
        }
        $p = (string) (parse_url($val, PHP_URL_PATH) ?? '');

        return $p !== '' ? $p : '/';
    }

    if (str_starts_with($val, '//')) {
        $h = strtolower((string) (parse_url('http:' . $val, PHP_URL_HOST) ?? ''));
        if ($h !== $want) {
            return null;
        }
        $p = (string) (parse_url('http:' . $val, PHP_URL_PATH) ?? '');

        return $p !== '' ? $p : '/';
    }

    return null;
}

function documento_path_strip_query(string $path): string
{
    $path = preg_replace('/\?.*$/', '', $path) ?? '';
    $path = str_replace('\\', '/', trim($path));

    return $path;
}

/**
 * Ligações para documentos PARTILHADOS gCondominio (iguais para todos; nome sem NIF — só slug).
 * Ex.: 2026-01-condominio-avenida-2427-dr.htm ou 01-condominio-....htm (relativo à pasta do ano).
 *
 * @return 'cq'|'dc'|'dr'|'fc'|null
 */
function documento_shared_doc_code_from_condominio_filename(string $pathOnly): ?string
{
    $pathOnly = documento_path_strip_query($pathOnly);
    if ($pathOnly === '') {
        return null;
    }

    $base = basename($pathOnly);
    // Export às vezes vem como slug__2026-01-condominio-....htm
    if (str_contains($base, '__')) {
        $parts = explode('__', $base);
        $tail = (string) array_pop($parts);
        if ($tail !== '' && preg_match('/^(?:\d{4}-\d{2}|\d{2})-condominio-/i', $tail)) {
            $base = $tail;
        }
    }

    // Separadores de «aba»: ...-(cq|dc|dr|fc).htm
    if (preg_match('/^(\d{4}-\d{2}-condominio-.+)-(cq|dc|dr|fc)\.(?:htm|html)$/i', $base, $m)) {
        return strtolower($m[2]);
    }
    if (preg_match('/^(\d{2}-condominio-.+)-(cq|dc|dr|fc)\.(?:htm|html)$/i', $base, $m)) {
        return strtolower($m[2]);
    }

    // Ficheiro base (sem -cq/-dc/…) → mesmo tratamento que doc=dc no resolver (tenta '' e -dc).
    if (preg_match('/^\d{4}-\d{2}-condominio-.+\.(?:htm|html)$/i', $base)) {
        return 'dc';
    }
    if (preg_match('/^\d{2}-condominio-.+\.(?:htm|html)$/i', $base)) {
        return 'dc';
    }

    return null;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

$nifRaw = isset($_GET['nif']) ? (string) $_GET['nif'] : '';
$senhaRaw = isset($_GET['senha']) ? (string) $_GET['senha'] : '';
$docRaw = isset($_GET['doc']) ? (string) $_GET['doc'] : 'dc';

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

$year = (int) date('Y');

$config = load_config();
$resolved = document_resolve_document($config, $nifDigits, $year, $senhaSafe, $docCode);

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
    header('X-Documento-Reason: ' . $reason);
    documento_render_error($reason, $status);
    exit;
}

$found = $resolved['found'];
$source = $resolved['source'] ?? 'ftp';

function documento_is_shared_condominio_dr_filename(string $filename): bool
{
    $b = basename(str_replace('\\', '/', $filename));
    if (str_contains($b, '__')) {
        $parts = explode('__', $b);
        $tail = (string) array_pop($parts);
        if ($tail !== '') {
            $b = $tail;
        }
    }
    return (bool) preg_match('/^\d{4}-\d{2}-condominio-.+-dr\.(?:htm|html)$/i', $b);
}

function documento_html_anchor_slug(string $html): string
{
    $anchor = html_extract_arial_anchor_text($html);
    return condominio_slug_from_anchor_text($anchor);
}

/**
 * Dado um caminho local para um ficheiro (normalmente em condominios/{ANO}/...),
 * tenta inferir a pasta base (condominios/) para resolver documentos partilhados.
 */
function documento_infer_local_root_for_shared(string $localPath, int $year): ?string
{
    $real = $localPath !== '' ? realpath($localPath) : false;
    if ($real === false) {
        return null;
    }
    $dir = dirname($real);
    $yearStr = (string) $year;
    // .../condominios/{ANO}/Privados/file.htm
    if (basename($dir) === 'Privados' || basename($dir) === 'privados') {
        $dir = dirname($dir);
    }
    if (basename($dir) !== $yearStr) {
        return null;
    }
    $root = dirname($dir);
    $rootReal = realpath($root);
    return ($rootReal !== false && is_dir($rootReal)) ? $rootReal : null;
}

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

/**
 * Validação/redirecionamento:
 * Se o pedido for doc=dr e o ficheiro resolvido for na verdade um documento PARTILHADO do condomínio
 * (ex.: 2026-01-condominio-...-dr.htm), então este acesso só é válido se o NIF+senha (doc privado)
 * corresponder ao MESMO endereço/condomínio do documento partilhado.
 *
 * Regra:
 * - Extrair o "anchor" (Arial size=1) do HTML PRIVADO (doc=dc do NIF+senha) → slugPrivado
 * - Resolver o documento partilhado do condomínio "dc" (ex.: ...-dc.htm) → slugShared
 * - Se slugPrivado == slugShared → servir o shared -dc.htm
 * - Caso contrário → erro (não expor informação sobre outros condomínios)
 */
if (($ext === 'html' || $ext === 'htm') && $docCode === 'dr' && documento_is_shared_condominio_dr_filename((string) ($found['filename'] ?? ''))) {
    // 1) Obter slug do documento PRIVADO do NIF (doc=dc).
    $privResolved = document_resolve_document($config, $nifDigits, $year, $senhaSafe, 'dc');
    if (!($privResolved['ok'] ?? false)) {
        documento_render_error('file_not_found', 404);
        exit;
    }
    $privFound = $privResolved['found'];
    $privSource = $privResolved['source'] ?? 'ftp';
    $privHtml = '';
    if (($privFound['extension'] ?? '') === 'html' || ($privFound['extension'] ?? '') === 'htm') {
        if ($privSource === 'local') {
            $p = (string) ($privFound['local_path'] ?? '');
            $rp = $p !== '' ? realpath($p) : false;
            if ($rp !== false && is_readable($rp)) {
                $privHtml = (string) (file_get_contents($rp) ?: '');
            }
        } else {
            $privHtml = (string) (ftp_download_file($config['ftp'] ?? [], $privFound) ?? '');
        }
    }
    $privSlug = $privHtml !== '' ? documento_html_anchor_slug($privHtml) : '';
    if ($privSlug === '') {
        documento_render_error('file_not_found', 404);
        exit;
    }

    // 2) Resolver o doc PARTILHADO "dc" correspondente ao condomínio.
    $sharedResolved = null;
    if ($source === 'local') {
        $root = documento_infer_local_root_for_shared((string) ($found['local_path'] ?? ''), $year);
        if ($root !== null) {
            $r = local_resolve_shared_condominio_document($root, $found, 'dc', $year);
            if (($r['ok'] ?? false) && !empty($r['found'])) {
                $sharedResolved = ['source' => 'local', 'found' => $r['found']];
            }
        }
    } else {
        $r = ftp_resolve_shared_condominio_document($config['ftp'] ?? [], $found, $nifDigits, $senhaSafe, $year, 'dc');
        if (($r['ok'] ?? false) && !empty($r['found'])) {
            $sharedResolved = ['source' => 'ftp', 'found' => $r['found']];
        }
    }

    if ($sharedResolved === null) {
        documento_render_error('file_not_found', 404);
        exit;
    }

    $dcFound = $sharedResolved['found'];
    $dcData = null;
    if (($sharedResolved['source'] ?? 'ftp') === 'local') {
        $p = (string) ($dcFound['local_path'] ?? '');
        $rp = $p !== '' ? realpath($p) : false;
        if ($rp !== false && is_readable($rp)) {
            $dcData = file_get_contents($rp);
        }
    } else {
        $dcData = ftp_download_file($config['ftp'] ?? [], $dcFound);
    }

    if (!is_string($dcData) || $dcData === '') {
        documento_render_error('download_failed', 502);
        exit;
    }

    $sharedSlug = documento_html_anchor_slug($dcData);
    if ($sharedSlug === '' || $sharedSlug !== $privSlug) {
        documento_render_error('file_not_found', 404);
        exit;
    }

    // 3) OK → servir o shared -dc.htm (balanço do condomínio) no lugar do -dr.htm.
    $found = $dcFound;
    $data = $dcData;
    $ext = (string) ($found['extension'] ?? $ext);
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) ($found['filename'] ?? 'documento')) ?: 'documento';
    header('Content-Disposition: inline; filename="' . $safeName . '"');
    header('Content-Length: ' . (string) strlen($data));
}

if ($ext === 'html' || $ext === 'htm') {
    $baseParams = [
        'nif' => $nifDigits,
        'senha' => $senhaSafe,
    ];
    $rewrite = static function (string $html) use ($baseParams, $docRootPrefix): string {
        // Captura com ou sem aspas.
        $pattern = '#\b(href|src)\s*=\s*([\'"]?)([^\'"\s>]+)\2#i';

        return preg_replace_callback($pattern, static function (array $m) use ($baseParams, $docRootPrefix): string {
            $attr = $m[1];
            $quote = $m[2];
            $val = $m[3];

            $check = $val;
            $pathOnly = documento_url_path_only($check);
            $looksRemote = (bool) preg_match('#^(?:https?:)?//#i', $check);
            if ($looksRemote && $pathOnly === '') {
                return $attr . '=' . $quote . $val . $quote;
            }

            // Navegação entre separadores do pack PARTILHADO (nomes sem NIF; só slug condominio-*).
            if ($attr === 'href') {
                $pathClean = documento_path_strip_query($pathOnly);
                $sharedDoc = documento_shared_doc_code_from_condominio_filename($pathClean);
                if ($sharedDoc !== null) {
                    // Importante: documentos do CONDOMÍNIO não devem depender de NIF/senha (evita links com NIF "placeholder").
                    // Servir diretamente pelo nome do ficheiro partilhado no FTP.
                    $base = basename($pathClean);
                    $q = http_build_query(['file' => $base]);
                    return $attr . '=' . $quote . $docRootPrefix . 'condominio.php?' . $q . $quote;
                }
            }

            return $attr . '=' . $quote . $val . $quote;
        }, $html) ?? $html;
    };

    // Reescreve os links das "abas" internas do documento para chamar este endpoint.
    $data = $rewrite($data);

    // Reescreve recursos (imagens/css/js) relativos para serem servidos via recurso.php do mesmo diretório.
    $assetPattern = '#\b(src|href)\s*=\s*([\'"]?)([^\'"\s>]+)\2#i';
    $data = preg_replace_callback($assetPattern, static function (array $m) use ($baseParams, $docCode, $docRootPrefix): string {
        $attr = $m[1];
        $quote = $m[2];
        $val = $m[3];

        $sameOriginPath = documento_same_origin_path($val);
        if (str_starts_with(trim($val), '//') && $sameOriginPath === null) {
            return $attr . '=' . $quote . $val . $quote;
        }

        $work = $sameOriginPath !== null ? $sameOriginPath : $val;
        $lower = strtolower($work);

        if ($work === ''
            || ($sameOriginPath === null && (str_starts_with(strtolower($val), 'http://')
                || str_starts_with(strtolower($val), 'https://')))
            || str_starts_with($lower, 'data:')
            || str_starts_with($lower, 'javascript:')
            || str_starts_with($lower, '#')) {
            return $attr . '=' . $quote . $val . $quote;
        }

        $trim = preg_replace('#^[./]+#', '', (string) preg_replace('#\?.*$#', '', $work)) ?? '';

        // Se for caminho relativo simples para um recurso estático, proxy via recurso.php.
        if ($trim !== '' && preg_match('#^([^/?#]+\.(?:jpg|jpeg|png|gif|webp|svg|ico|css|js))$#i', $trim, $mm)) {
            $file = $mm[1];
            $q = http_build_query(array_merge($baseParams, ['doc' => $docCode, 'file' => $file]));
            return $attr . '=' . $quote . $docRootPrefix . 'recurso.php?' . $q . $quote;
        }

        return $attr . '=' . $quote . $val . $quote;
    }, $data) ?? $data;

    header('Content-Length: ' . (string) strlen($data));
}

echo $data;
