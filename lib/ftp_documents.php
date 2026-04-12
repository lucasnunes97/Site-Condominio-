<?php

declare(strict_types=1);

/**
 * Fecha ligação FTP sem emitir avisos PHP se a ligação já estiver inválida.
 */
function ftp_safe_close(mixed $conn): void
{
    if ($conn === false || $conn === null) {
        return;
    }
    @ftp_close($conn);
}

/** Normaliza o segmento base do caminho FTP (remove prefixos XAMPP/www acidentais). */
function ftp_normalize_config_base(string $basePathConfig): string
{
    $raw = trim(str_replace('\\', '/', $basePathConfig), '/');
    if (preg_match('#(?:^|/)(?:xampp/)?htdocs/(.+)$#i', $raw, $m)) {
        $raw = $m[1];
    }
    if (preg_match('#(?:^|/)www/(.+)$#i', $raw, $m)) {
        $raw = $m[1];
    }
    if (preg_match('#(?:^|/)site condominio/(.+)$#i', $raw, $m)) {
        $raw = $m[1];
    }

    return $raw !== '' ? $raw : 'condominios';
}

/**
 * Pastas raiz no FTP: a definida em base_path + condominios + documento (muitos alojamentos usam www/documento/).
 *
 * @return list<string>
 */
function ftp_root_folder_seeds(?string $basePathConfig): array
{
    $raw = ftp_normalize_config_base((string) ($basePathConfig ?? 'condominios'));
    $extra = [
        'documento',
        'Documento',
        'DOCUMENTO',
        'condominios',
        'Condominios',
        'CONDOMINIOS',
    ];
    $seeds = array_merge([$raw], $extra);

    return array_values(array_unique(array_filter($seeds)));
}

/**
 * Pastas do tipo base/ANO/ no FTP (ex.: documento/2025, condominios/2025).
 *
 * @return list<string>
 */
function ftp_year_directory_candidates(?string $basePathConfig, int $year): array
{
    $yearStr = (string) $year;
    $seeds = ftp_root_folder_seeds($basePathConfig);

    $candidates = [];
    foreach ($seeds as $base) {
        $candidates[] = $base . '/' . $yearStr;
        $candidates[] = '/' . $base . '/' . $yearStr;
    }

    $out = [];
    foreach ($candidates as $p) {
        $p = preg_replace('#/+#', '/', str_replace('//', '/', $p)) ?? $p;
        if ($p !== '') {
            $out[] = ltrim($p, '/');
            $out[] = '/' . ltrim($p, '/');
        }
    }

    return array_values(array_unique($out));
}

/**
 * Pastas raiz (sem ano) para ficheiros tipo NIF-ANO.pdf diretamente na pasta.
 *
 * @return list<string>
 */
function ftp_flat_base_directory_candidates(?string $basePathConfig): array
{
    $seeds = ftp_root_folder_seeds($basePathConfig);
    $out = [];
    foreach ($seeds as $p) {
        $p = preg_replace('#/+#', '/', ltrim($p, '/')) ?? $p;
        if ($p !== '') {
            $out[] = $p;
            $out[] = '/' . $p;
        }
    }

    return array_values(array_unique($out));
}

/**
 * Ficheiros PDF/HTML cujo nome contém o NIF (procura na pasta do ano).
 *
 * @param list<string> $list
 * @return list<array{remote_dir: string, remote_file: string, filename: string, extension: string, nif_digits: string}>
 */
function ftp_filter_files_nif_in_name(array $list, string $remoteDir, string $nifDigits): array
{
    $candidates = [];
    foreach ($list as $name) {
        $baseName = basename(str_replace('\\', '/', $name));
        if ($baseName === '.' || $baseName === '..') {
            continue;
        }
        $lower = strtolower($baseName);
        if (!str_ends_with($lower, '.pdf') && !str_ends_with($lower, '.html') && !str_ends_with($lower, '.htm')) {
            continue;
        }
        if (!str_contains($lower, strtolower($nifDigits))) {
            continue;
        }
        $ext = strtolower((string) pathinfo($baseName, PATHINFO_EXTENSION));
        $candidates[] = [
            'remote_dir'  => $remoteDir,
            'remote_file' => $baseName,
            'filename'    => $baseName,
            'extension'   => $ext,
            'nif_digits'  => $nifDigits,
        ];
    }

    return $candidates;
}

/**
 * Fallback: mesma pasta raiz, nome do ficheiro contém NIF e ano (ex.: rel_2025_335301053.pdf).
 *
 * @param list<string> $list
 */
function ftp_filter_files_nif_and_year_in_name(array $list, string $remoteDir, string $nifDigits, string $yearStr): array
{
    $candidates = [];
    foreach ($list as $name) {
        $baseName = basename(str_replace('\\', '/', $name));
        if ($baseName === '.' || $baseName === '..') {
            continue;
        }
        $lower = strtolower($baseName);
        if (!str_ends_with($lower, '.pdf') && !str_ends_with($lower, '.html') && !str_ends_with($lower, '.htm')) {
            continue;
        }
        if (!str_contains($lower, strtolower($nifDigits)) || !str_contains($lower, $yearStr)) {
            continue;
        }
        $ext = strtolower((string) pathinfo($baseName, PATHINFO_EXTENSION));
        $candidates[] = [
            'remote_dir'  => $remoteDir,
            'remote_file' => $baseName,
            'filename'    => $baseName,
            'extension'   => $ext,
            'nif_digits'  => $nifDigits,
        ];
    }

    return $candidates;
}

/**
 * @return list<bool>
 */
function ftp_passive_attempts(bool $configured): array
{
    $a = [$configured, !$configured];
    return array_values(array_unique($a, SORT_REGULAR));
}

/**
 * FTP: pasta ANO (sob base_path) → ficheiro PDF/HTML cujo nome contém o NIF.
 * Fallback: pasta base sem ano → ficheiro com NIF e ano no nome.
 *
 * @return array{ok: true, found: array}|array{ok: false, reason: 'config_error'|'ftp_error'|'nif_not_found'|'file_not_found'}
 */
function ftp_resolve_condominio_document(array $ftpConfig, string $nifDigits, int $year): array
{
    $host = $ftpConfig['host'] ?? '';
    $user = $ftpConfig['username'] ?? '';
    $pass = $ftpConfig['password'] ?? '';
    $timeout = (int) ($ftpConfig['timeout'] ?? 30);

    if ($host === '' || $user === '' || $pass === '' || $pass === 'SUA_SENHA_FTP') {
        return ['ok' => false, 'reason' => 'config_error'];
    }

    $passiveConfigured = (bool) ($ftpConfig['passive'] ?? false);
    $yearStr = (string) $year;
    $yearDirs = ftp_year_directory_candidates($ftpConfig['base_path'] ?? 'condominios', $year);
    $flatBases = ftp_flat_base_directory_candidates($ftpConfig['base_path'] ?? 'condominios');

    $sortFn = static function (array $a, array $b): int {
        $prio = static function (string $ext): int {
            return match ($ext) {
                'pdf' => 0,
                'html', 'htm' => 1,
                default => 2,
            };
        };
        return $prio($a['extension']) <=> $prio($b['extension']);
    };

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
        foreach ($yearDirs as $ydir) {
            if (!@ftp_chdir($conn, $ydir)) {
                continue;
            }
            $list = @ftp_nlist($conn, '.');
            if ($list === false) {
                continue;
            }
            $matches = ftp_filter_files_nif_in_name($list, $ydir, $nifDigits);
            if ($matches !== []) {
                usort($matches, $sortFn);
                $found = $matches[0];
                break;
            }
        }

        if ($found === null) {
            ftp_safe_close($conn);
            $conn = @ftp_connect($host, 21, $timeout);
            if ($conn !== false && @ftp_login($conn, $user, $pass)) {
                ftp_pasv($conn, $passive);
                foreach ($flatBases as $baseDir) {
                    if (!@ftp_chdir($conn, $baseDir)) {
                        continue;
                    }
                    $list = @ftp_nlist($conn, '.');
                    if ($list === false) {
                        continue;
                    }
                    $matches = ftp_filter_files_nif_and_year_in_name($list, $baseDir, $nifDigits, $yearStr);
                    if ($matches !== []) {
                        usort($matches, $sortFn);
                        $found = $matches[0];
                        break;
                    }
                }
            }
        }

        if ($conn !== false) {
            ftp_safe_close($conn);
        }

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

function ftp_find_condominio_document(array $ftpConfig, string $nifDigits, int $year): ?array
{
    $r = ftp_resolve_condominio_document($ftpConfig, $nifDigits, $year);
    return ($r['ok'] ?? false) ? $r['found'] : null;
}

/**
 * Diretórios a tentar na descarga (caminho exato + variantes).
 *
 * @return list<string>
 */
function ftp_download_directory_order(array $ftpConfig, array $found, int $year): array
{
    $dirOrder = [];
    if (!empty($found['remote_dir'])) {
        $dirOrder[] = (string) $found['remote_dir'];
        $d = ltrim((string) $found['remote_dir'], '/');
        $dirOrder[] = '/' . $d;
        if ($d !== '') {
            $dirOrder[] = $d;
        }
    }
    foreach (ftp_year_directory_candidates($ftpConfig['base_path'] ?? 'condominios', $year) as $p) {
        $dirOrder[] = $p;
    }

    return array_values(array_unique(array_filter($dirOrder)));
}

function ftp_download_file(array $ftpConfig, array $found): ?string
{
    $host = $ftpConfig['host'] ?? '';
    $user = $ftpConfig['username'] ?? '';
    $pass = $ftpConfig['password'] ?? '';
    $timeout = (int) ($ftpConfig['timeout'] ?? 30);

    $remoteFile = $found['remote_file'] ?? '';
    $ext = $found['extension'] ?? '';
    $year = isset($found['search_year']) ? (int) $found['search_year'] : (int) (date('Y'));

    if ($host === '' || $user === '' || $pass === '' || $remoteFile === '') {
        return null;
    }

    $dirOrder = ftp_download_directory_order($ftpConfig, $found, $year);

    $passiveConfigured = (bool) ($ftpConfig['passive'] ?? false);

    foreach (ftp_passive_attempts($passiveConfigured) as $passive) {
        foreach ($dirOrder as $remoteDir) {
            $data = ftp_download_file_attempt($host, $user, $pass, $timeout, $passive, $remoteDir, $remoteFile);
            if ($data === null || $data === '') {
                continue;
            }
            if ($ext === 'pdf' && !str_starts_with($data, '%PDF')) {
                continue;
            }
            if (($ext === 'html' || $ext === 'htm') && strlen($data) < 10) {
                continue;
            }
            return $data;
        }
    }

    return null;
}

function ftp_download_file_attempt(
    string $host,
    string $user,
    string $pass,
    int $timeout,
    bool $passive,
    string $remoteDir,
    string $remoteFile
): ?string {
    $conn = @ftp_connect($host, 21, $timeout);
    if ($conn === false) {
        return null;
    }
    if (!@ftp_login($conn, $user, $pass)) {
        ftp_safe_close($conn);
        return null;
    }
    ftp_pasv($conn, $passive);

    if (!@ftp_chdir($conn, $remoteDir)) {
        ftp_safe_close($conn);
        return null;
    }

    $tmp = fopen('php://temp', 'r+b');
    if ($tmp === false) {
        ftp_safe_close($conn);
        return null;
    }

    $ok = @ftp_fget($conn, $tmp, $remoteFile, FTP_BINARY);
    ftp_safe_close($conn);

    if (!$ok) {
        fclose($tmp);
        return null;
    }

    rewind($tmp);
    $data = stream_get_contents($tmp);
    fclose($tmp);

    if ($data === false || $data === '') {
        return null;
    }

    return $data;
}
