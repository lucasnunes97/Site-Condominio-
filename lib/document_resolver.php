<?php

declare(strict_types=1);

require_once __DIR__ . '/ftp_documents.php';
require_once __DIR__ . '/shared_condominio_docs.php';

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
                // Novo padrão: sem pasta por ano; o ano pode ou não estar no nome.
                if (!str_contains($lower, strtolower($nifDigits))) {
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
function document_resolve_document(array $config, string $nifDigits, int $year, string $senha, string $docCode = 'dc'): array
{
    $mode = $config['document_storage'] ?? 'auto';
    if (!in_array($mode, ['auto', 'local', 'ftp'], true)) {
        $mode = 'auto';
    }

    // Preferir config.local, mas testar TODAS as pastas possíveis (não escolher só uma),
    // para não "prender" no legacy quando os ficheiros foram movidos para www/Condominio.
    $projectRoot = dirname(__DIR__);
    $localCandidates = [];
    if (isset($config['local_condominios_path']) && is_string($config['local_condominios_path']) && $config['local_condominios_path'] !== '') {
        $localCandidates[] = $config['local_condominios_path'];
    }
    // Em muitos alojamentos, a pasta "www" do FTP é o próprio webroot em disco.
    // Então devemos tentar com e sem o segmento "www/" e com variações de caixa.
    $localCandidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'Condominio';
    $localCandidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'condominio';
    $localCandidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'CONDOMINIO';
    $localCandidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'Condominio';
    $localCandidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'condominio';
    $localCandidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'CONDOMINIO';
    // Novo padrão do utilizador: todos os ficheiros (privados + partilhados) em www/condominios (sem subpastas por ano).
    $localCandidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'condominios';
    $localCandidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'Condominios';
    $localCandidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'CONDOMINIOS';
    // Legacy
    $localCandidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'condominios';

    $localCandidates = array_values(array_unique(array_filter($localCandidates)));
    // Mantido apenas para mensagens/fallback: primeiro candidato.
    $localBase = (string) ($localCandidates[0] ?? ($projectRoot . DIRECTORY_SEPARATOR . 'condominios'));

    /** Pasta «documento» ao lado do site (ex.: www/documento/123456789-2025.pdf) — tentar primeiro. */
    $localDocumento = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'documento';

    $docCode = strtolower(trim($docCode));
    if (!in_array($docCode, ['dc', 'cq', 'dr', 'fc'], true)) {
        $docCode = 'dc';
    }

    $tryFtpPrivate = static function () use ($config, $nifDigits, $year, $senha): array {
        if (!function_exists('ftp_connect')) {
            return ['ok' => false, 'reason' => 'ftp_error', 'source' => 'ftp'];
        }
        $ftp = $config['ftp'] ?? [];

        // Para CQ/DR/FC precisamos primeiro do extrato privado no FTP; permitir fallback «ficheiro com NIF»
        // quando o nome não segue exactamente ANO-NIFsenha (casos com prefixos/slug no servidor).
        return ftp_resolve_condominio_document($ftp, $nifDigits, $senha, $year, 'dc', true);
    };

    $tryFtpShared = static function (?array $privateFound) use ($config, $nifDigits, $year, $senha, $docCode): array {
        if (!function_exists('ftp_connect')) {
            return ['ok' => false, 'reason' => 'ftp_error', 'source' => 'ftp'];
        }
        $ftp = $config['ftp'] ?? [];

        return ftp_resolve_shared_condominio_document($ftp, $privateFound, $nifDigits, $senha, $year, $docCode);
    };

    /** Extrato privado encontrado em disco mas sem ficheiro partilhado na mesma árvore — ainda pode ir buscar o partilhado ao FTP. */
    $localPrivateFoundForShared = null;

    if ($mode === 'local' || $mode === 'auto') {
        $localRoots = [$localDocumento];
        foreach ($localCandidates as $cand) {
            if (is_dir($cand)) {
                $localRoots[] = $cand;
            }
        }
        // Se nenhuma existir ainda, manter o fallback para não devolver "local_storage_missing" indevidamente.
        if (count($localRoots) === 1) {
            $localRoots[] = $localBase;
        }
        $localRoots = array_values(array_unique($localRoots));
        $lastLocal = null;
        $anyRoot = false;

        foreach ($localRoots as $root) {
            if (!is_dir($root)) {
                continue;
            }
            $anyRoot = true;
            $localPrivate = local_resolve_condominio_document_exact($root, $nifDigits, $senha, $year, 'dc');
            $lastLocal = $localPrivate;

            if ($docCode === 'dc') {
                if ($localPrivate['ok']) {
                    return ['ok' => true, 'found' => $localPrivate['found'], 'source' => 'local'];
                }
            } else {
                if ($localPrivate['ok']) {
                    $shared = local_resolve_shared_condominio_document($root, $localPrivate['found'], $docCode, $year);
                    if ($shared['ok']) {
                        return ['ok' => true, 'found' => $shared['found'], 'source' => 'local'];
                    }
                    $localPrivateFoundForShared = $localPrivate['found'];
                }
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
        $ftpPrivate = $tryFtpPrivate();
        if ($docCode === 'dc') {
            if (($ftpPrivate['ok'] ?? false)) {
                return ['ok' => true, 'found' => $ftpPrivate['found'], 'source' => 'ftp'];
            }

            return array_merge($ftpPrivate, ['source' => 'ftp']);
        }

        if (($ftpPrivate['ok'] ?? false)) {
            $shared = $tryFtpShared($ftpPrivate['found']);
            if (($shared['ok'] ?? false)) {
                return ['ok' => true, 'found' => $shared['found'], 'source' => 'ftp'];
            }
            if ($localPrivateFoundForShared !== null) {
                $sharedLocal = ftp_resolve_shared_condominio_document(
                    $config['ftp'] ?? [],
                    $localPrivateFoundForShared,
                    $nifDigits,
                    $senha,
                    $year,
                    $docCode
                );
                if (($sharedLocal['ok'] ?? false)) {
                    return ['ok' => true, 'found' => $sharedLocal['found'], 'source' => 'ftp'];
                }
            }

            return array_merge($shared, ['source' => 'ftp']);
        }

        if ($localPrivateFoundForShared !== null) {
            $sharedOnlyLocal = ftp_resolve_shared_condominio_document(
                $config['ftp'] ?? [],
                $localPrivateFoundForShared,
                $nifDigits,
                $senha,
                $year,
                $docCode
            );
            if (($sharedOnlyLocal['ok'] ?? false)) {
                return ['ok' => true, 'found' => $sharedOnlyLocal['found'], 'source' => 'ftp'];
            }

            return array_merge($sharedOnlyLocal, ['source' => 'ftp']);
        }

        return array_merge($ftpPrivate, ['source' => 'ftp']);
    }

    return ['ok' => false, 'reason' => 'nif_not_found', 'source' => 'local'];
}

/**
 * Resolve documentos partilhados (CQ/DR/FC + DC “geral”) a partir do stem do gCondominio.
 *
 * @param array{filename: string, local_path?: string, extension: string} $privateFound
 * @return array{ok: true, found: array}|array{ok: false, reason: string}
 */
function local_resolve_shared_condominio_document(string $rootBase, array $privateFound, string $docCode, int $year): array
{
    $docCode = strtolower(trim($docCode));
    if (!in_array($docCode, ['cq', 'dr', 'fc', 'dc'], true)) {
        return ['ok' => false, 'reason' => 'file_not_found'];
    }

    $baseName = (string) ($privateFound['filename'] ?? '');
    if ($baseName === '') {
        return ['ok' => false, 'reason' => 'file_not_found'];
    }

    $stemBase = pathinfo($baseName, PATHINFO_FILENAME);
    $stem = shared_extract_condominio_stem($stemBase, $year);

    if (($stem === null || $stem === '') && !empty($privateFound['local_path'])) {
        $html = @file_get_contents((string) $privateFound['local_path']) ?: '';
        if ($html !== '') {
            $infer = shared_infer_condominio_stem_from_private($html, $year);
            if ($infer !== null) {
                $stem = $infer['stem'];
            }
        }
    }

    if ($stem === null || $stem === '') {
        return ['ok' => false, 'reason' => 'file_not_found'];
    }

    if (!shared_verify_stem_in_private_html($privateFound, $stem)) {
        return ['ok' => false, 'reason' => 'file_not_found'];
    }

    $yearStr = (string) $year;
    $suffixes = $docCode === 'dc' ? ['', '-dc'] : ['-' . $docCode];
    $targets = [];
    foreach ($suffixes as $suffix) {
        // Padrão antigo: com ano
        $targets[] = $yearStr . '-' . $stem . $suffix . '.htm';
        $targets[] = $yearStr . '-' . $stem . $suffix . '.html';
        // Padrão novo: sem ano (ficheiros todos em www/condominios/)
        $targets[] = $stem . $suffix . '.htm';
        $targets[] = $stem . $suffix . '.html';
    }

    $searchDirs = [];
    $rootReal = realpath($rootBase);
    if ($rootReal !== false && is_dir($rootReal)) {
        $searchDirs[] = $rootReal;
    }
    // Padrão antigo ainda suportado: root/{ANO}/
    $yearDir = $rootBase . DIRECTORY_SEPARATOR . $yearStr;
    if (is_dir($yearDir)) {
        $yr = realpath($yearDir);
        if ($yr !== false && ($rootReal === false || str_starts_with(str_replace('\\', '/', $yr), str_replace('\\', '/', $rootReal)))) {
            $searchDirs[] = $yr;
        }
    }

    foreach ($searchDirs as $dir) {
        foreach ($targets as $fileName) {
            $full = $dir . DIRECTORY_SEPARATOR . $fileName;
            $realFile = is_file($full) ? realpath($full) : false;
            if ($realFile === false || !is_readable($realFile)) {
                continue;
            }
            $ext = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));

            return [
                'ok' => true,
                'found' => [
                    'extension'   => $ext,
                    'filename'    => $fileName,
                    'local_path'  => $realFile,
                    'nif_digits'  => '',
                    'remote_dir'  => '',
                    'remote_file' => '',
                    'search_year' => $year,
                ],
            ];
        }
    }

    return ['ok' => false, 'reason' => 'file_not_found'];
}

/**
 * Pasta local: procura primeiro o ficheiro exato ANO-NIF+SENHA.htm (ou .html / .pdf) em {ANO}/ e na raiz.
 *
 * @return array{ok: true, found: array}|array{ok: false, reason: 'nif_not_found'|'file_not_found'}
 */
function local_resolve_condominio_document_exact(string $localBase, string $nifDigits, string $senha, int $year, string $docCode = 'dc'): array
{
    $localBase = realpath($localBase);
    if ($localBase === false || !is_dir($localBase)) {
        return ['ok' => false, 'reason' => 'nif_not_found'];
    }

    $yearStr = (string) $year;
    $targetStem = $yearStr . '-' . $nifDigits . $senha;
    /** Alguns exports usam o ano colado ao NIF+senha sem hífen intermédio: 202611111111122233.htm */
    $targetStemJoined = $yearStr . $nifDigits . $senha;
    $docCode = strtolower(trim($docCode));
    if (!in_array($docCode, ['dc', 'cq', 'dr', 'fc'], true)) {
        $docCode = 'dc';
    }

    $suffixes = $docCode === 'dc' ? ['', '-dc'] : ['-' . $docCode];
    $targets = [];
    foreach ($suffixes as $suffix) {
        $targets[] = $targetStem . $suffix . '.htm';
        $targets[] = $targetStem . $suffix . '.html';
        $targets[] = $targetStem . $suffix . '.pdf';
        $targets[] = $targetStemJoined . $suffix . '.htm';
        $targets[] = $targetStemJoined . $suffix . '.html';
        $targets[] = $targetStemJoined . $suffix . '.pdf';
    }

    $targets = array_values(array_unique(array_filter($targets)));

    $searchDirs = [];
    $yearDir = $localBase . DIRECTORY_SEPARATOR . $yearStr;
    if (is_dir($yearDir)) {
        $yearReal = realpath($yearDir);
        if ($yearReal !== false && str_starts_with(str_replace('\\', '/', $yearReal), str_replace('\\', '/', $localBase))) {
            $searchDirs[] = $yearReal;
            foreach (['Privados', 'privados'] as $priv) {
                $pd = $yearReal . DIRECTORY_SEPARATOR . $priv;
                if (is_dir($pd)) {
                    $prd = realpath($pd);
                    if ($prd !== false && str_starts_with(str_replace('\\', '/', $prd), str_replace('\\', '/', $yearReal))) {
                        $searchDirs[] = $prd;
                    }
                }
            }
        }
    }
    $searchDirs[] = $localBase;

    $searchDirs = array_values(array_unique(array_filter($searchDirs)));

    foreach ($searchDirs as $dir) {
        foreach ($targets as $fileName) {
            $candidates = [];
            $direct = $dir . DIRECTORY_SEPARATOR . $fileName;
            if (is_file($direct)) {
                $candidates[] = $direct;
            }
            $globbed = glob($dir . DIRECTORY_SEPARATOR . '*__' . $fileName, GLOB_NOSORT);
            if (is_array($globbed)) {
                foreach ($globbed as $g) {
                    $candidates[] = $g;
                }
            }

            foreach ($candidates as $full) {
                $realFile = is_file($full) ? realpath($full) : false;
                if ($realFile === false || !is_readable($realFile)) {
                    continue;
                }
                if (!str_starts_with(str_replace('\\', '/', $realFile), str_replace('\\', '/', $dir))) {
                    continue;
                }
                $baseName = basename($realFile);
                $ext = strtolower((string) pathinfo($baseName, PATHINFO_EXTENSION));
                $found = [
                    'extension'   => $ext,
                    'filename'    => $baseName,
                    'local_path'  => $realFile,
                    'nif_digits'  => $nifDigits,
                    'remote_dir'  => '',
                    'remote_file' => '',
                    'search_year' => $year,
                ];

                return ['ok' => true, 'found' => $found];
            }
        }
    }

    return ['ok' => false, 'reason' => 'file_not_found'];
}
