<?php

declare(strict_types=1);

/**
 * Padroniza nomes na pasta condominios/{ANO}/:
 * - Lê cada .htm e extrai o nome do condomínio via <font face="Arial" size="1">…</font>
 * - Gera slug e renomeia para: {slug}__{nome_original}.htm
 *
 * Uso: php padronizar_condominios_htm.php [ANO] [--dry-run]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'CLI apenas.';
    exit(1);
}

require_once __DIR__ . '/lib/extract_condominio_anchor.php';

$yearArg = $argv[1] ?? date('Y');
$dryRun = in_array('--dry-run', $argv, true);

if (!preg_match('/^\d{4}$/', $yearArg)) {
    fwrite(STDERR, "Ano inválido.\n");
    exit(1);
}

$base = realpath(__DIR__ . '/condominios');
if ($base === false) {
    fwrite(STDERR, "Pasta condominios/ não existe.\n");
    exit(1);
}

$yearDir = $base . DIRECTORY_SEPARATOR . $yearArg;
if (!is_dir($yearDir)) {
    fwrite(STDERR, "Pasta do ano não existe: condominios/{$yearArg}/\n");
    exit(1);
}

$yearReal = realpath($yearDir);
if ($yearReal === false || !str_starts_with(str_replace('\\', '/', $yearReal), str_replace('\\', '/', $base))) {
    fwrite(STDERR, "Caminho inválido.\n");
    exit(1);
}

$scanDirs = [$yearReal];
foreach (['Privados', 'privados'] as $sub) {
    $p = $yearReal . DIRECTORY_SEPARATOR . $sub;
    if (is_dir($p)) {
        $pr = realpath($p);
        if ($pr !== false && str_starts_with(str_replace('\\', '/', $pr), str_replace('\\', '/', $yearReal))) {
            $scanDirs[] = $pr;
        }
    }
}

$moved = 0;
$skipped = 0;

foreach ($scanDirs as $dir) {
    $list = @scandir($dir);
    if ($list === false) {
        continue;
    }
    foreach ($list as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $full = $dir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($full)) {
            continue;
        }
        $lower = strtolower($name);
        if (!str_ends_with($lower, '.htm')) {
            $skipped++;
            continue;
        }

        if (str_contains($name, '__')) {
            $skipped++;
            continue;
        }

        $slug = condominio_slug_from_html_file($full);
        if ($slug === '') {
            fwrite(STDERR, "Sem slug (HTML sem âncora?): {$name}\n");
            $skipped++;
            continue;
        }

        $newName = $slug . '__' . $name;
        $dest = $dir . DIRECTORY_SEPARATOR . $newName;
        if (is_file($dest)) {
            fwrite(STDERR, "Destino já existe, a saltar: {$newName}\n");
            $skipped++;
            continue;
        }

        if ($dryRun) {
            echo "[dry-run] {$name} -> {$newName}\n";
            $moved++;
            continue;
        }

        if (@rename($full, $dest)) {
            echo "{$name} -> {$newName}\n";
            $moved++;
        } else {
            fwrite(STDERR, "Falha ao renomear: {$name}\n");
            $skipped++;
        }
    }
}

echo "Ano: {$yearArg}\n";
echo ($dryRun ? "Previstos: {$moved}\n" : "Renomeados: {$moved}\n");
echo "Ignorados: {$skipped}\n";
