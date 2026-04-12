<?php

declare(strict_types=1);

require_once __DIR__ . '/ftp_documents.php';

/**
 * Pasta local: condominios/{ANO}/ → ficheiro cujo nome contém o NIF (igual ao FTP).
 *
 * @return array{ok: true, found: array}|array{ok: false, reason: 'nif_not_found'|'file_not_found'}
 */
function local_resolve_condominio_document(string $localBase, string $nifDigits, int $year): array
{
    $localBase = realpath($localBase);
    if ($localBase === false || !is_dir($localBase)) {
        return ['ok' => false, 'reason' => 'nif_not_found'];
    }

    $yearStr = (string) $year;
    $yearDir = $localBase . DIRECTORY_SEPARATOR . $yearStr;
    $candidates = [];

    if (is_dir($yearDir)) {
        $yearReal = realpath($yearDir);
        if ($yearReal !== false && str_starts_with(str_replace('\\', '/', $yearReal), str_replace('\\', '/', $localBase))) {
            $list = @scandir($yearReal);
            if ($list !== false) {
                foreach ($list as $name) {
                    if ($name === '.' || $name === '..') {
                        continue;
                    }
                    $lower = strtolower($name);
                    if (!str_ends_with($lower, '.pdf') && !str_ends_with($lower, '.html') && !str_ends_with($lower, '.htm')) {
                        continue;
                    }
                    if (!str_contains($lower, strtolower($nifDigits))) {
                        continue;
                    }
                    $full = $yearReal . DIRECTORY_SEPARATOR . $name;
                    $realFile = realpath($full);
                    if ($realFile === false || !str_starts_with(str_replace('\\', '/', $realFile), str_replace('\\', '/', $yearReal))) {
                        continue;
                    }
                    $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
                    $candidates[] = [
                        'extension'   => $ext,
                        'filename'    => $name,
                        'local_path'  => $realFile,
                        'nif_digits'  => $nifDigits,
                        'remote_dir'  => '',
                        'remote_file' => '',
                    ];
                }
            }
        }
    }

    if ($candidates === []) {
        $flatList = @scandir($localBase);
        if ($flatList !== false) {
            foreach ($flatList as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $lower = strtolower($name);
                if (!str_ends_with($lower, '.pdf') && !str_ends_with($lower, '.html') && !str_ends_with($lower, '.htm')) {
                    continue;
                }
                if (!str_contains($lower, strtolower($nifDigits)) || !str_contains($lower, $yearStr)) {
                    continue;
                }
                $full = $localBase . DIRECTORY_SEPARATOR . $name;
                $realFile = realpath($full);
                if ($realFile === false || !str_starts_with(str_replace('\\', '/', $realFile), str_replace('\\', '/', $localBase))) {
                    continue;
                }
                $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
                $candidates[] = [
                    'extension'   => $ext,
                    'filename'    => $name,
                    'local_path'  => $realFile,
                    'nif_digits'  => $nifDigits,
                    'remote_dir'  => '',
                    'remote_file' => '',
                ];
            }
        }
    }

    if ($candidates === []) {
        return ['ok' => false, 'reason' => 'file_not_found'];
    }

    usort($candidates, static function (array $a, array $b): int {
        $prio = static function (string $ext): int {
            return match ($ext) {
                'pdf' => 0,
                'html', 'htm' => 1,
                default => 2,
            };
        };
        return $prio($a['extension']) <=> $prio($b['extension']);
    });

    $found = $candidates[0];
    $found['search_year'] = $year;

    return ['ok' => true, 'found' => $found];
}

/**
 * @param 'auto'|'local'|'ftp' $mode
 * @return array{ok: true, found: array, source: 'local'|'ftp'}|array{ok: false, reason: string, source?: string}
 */
function document_resolve_document(array $config, string $nifDigits, int $year): array
{
    $mode = $config['document_storage'] ?? 'auto';
    if (!in_array($mode, ['auto', 'local', 'ftp'], true)) {
        $mode = 'auto';
    }

    $defaultLocal = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'condominios';
    $localBase = isset($config['local_condominios_path']) && is_string($config['local_condominios_path'])
        ? $config['local_condominios_path']
        : $defaultLocal;

    /** Pasta «documento» ao lado do site (ex.: www/documento/123456789-2025.pdf) — tentar primeiro. */
    $localDocumento = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'documento';

    $tryFtp = static function () use ($config, $nifDigits, $year): array {
        if (!function_exists('ftp_connect')) {
            return ['ok' => false, 'reason' => 'ftp_error', 'source' => 'ftp'];
        }
        $ftp = $config['ftp'] ?? [];
        $ftpRes = ftp_resolve_condominio_document($ftp, $nifDigits, $year);
        if ($ftpRes['ok']) {
            return ['ok' => true, 'found' => $ftpRes['found'], 'source' => 'ftp'];
        }

        return array_merge($ftpRes, ['source' => 'ftp']);
    };

    if ($mode === 'local' || $mode === 'auto') {
        $localRoots = array_values(array_unique([$localDocumento, $localBase]));
        $lastLocal = null;
        $anyRoot = false;

        foreach ($localRoots as $root) {
            if (!is_dir($root)) {
                continue;
            }
            $anyRoot = true;
            $local = local_resolve_condominio_document($root, $nifDigits, $year);
            $lastLocal = $local;
            if ($local['ok']) {
                return ['ok' => true, 'found' => $local['found'], 'source' => 'local'];
            }
        }

        if ($mode === 'local') {
            if (!$anyRoot) {
                return ['ok' => false, 'reason' => 'local_storage_missing', 'source' => 'local'];
            }

            return ['ok' => false, 'reason' => $lastLocal['reason'] ?? 'file_not_found', 'source' => 'local'];
        }
    }

    if ($mode === 'ftp' || $mode === 'auto') {
        return $tryFtp();
    }

    return ['ok' => false, 'reason' => 'nif_not_found', 'source' => 'local'];
}
