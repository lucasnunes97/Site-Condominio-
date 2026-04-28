<?php

declare(strict_types=1);

/**
 * Organiza ficheiros na pasta condominios/{ANO}/ conforme regras:
 * - Nomes só numéricos (NIF): move para condominios/{ANO}/privados/
 * - Demais: extrai nome legível, slugify e move para condominios/{ANO}/{slug}/
 *
 * Uso (CLI): php organizar_condominios.php [ANO]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'CLI apenas.';
    exit(1);
}

require_once __DIR__ . '/lib/slugify.php';

$yearArg = $argv[1] ?? date('Y');
if (!preg_match('/^\d{4}$/', $yearArg)) {
    fwrite(STDERR, "Ano inválido.\n");
    exit(1);
}

$base = realpath(__DIR__ . '/condominios');
if ($base === false) {
    fwrite(STDERR, "Pasta condominios/ não existe. Crie-a antes.\n");
    exit(1);
}

$yearDir = $base . DIRECTORY_SEPARATOR . $yearArg;
if (!is_dir($yearDir)) {
    if (!@mkdir($yearDir, 0755, true)) {
        fwrite(STDERR, "Não foi possível criar {$yearDir}\n");
        exit(1);
    }
}

$yearReal = realpath($yearDir);
if ($yearReal === false || !str_starts_with(str_replace('\\', '/', $yearReal), str_replace('\\', '/', $base))) {
    fwrite(STDERR, "Caminho inválido.\n");
    exit(1);
}

$privDir = $yearReal . DIRECTORY_SEPARATOR . 'privados';
if (!is_dir($privDir) && !@mkdir($privDir, 0755, true)) {
    fwrite(STDERR, "Não foi possível criar pasta privados.\n");
    exit(1);
}

$list = @scandir($yearReal);
if ($list === false) {
    fwrite(STDERR, "Não foi possível ler a pasta do ano.\n");
    exit(1);
}

$moved = 0;
$skipped = 0;

foreach ($list as $name) {
    if ($name === '.' || $name === '..') {
        continue;
    }
    $full = $yearReal . DIRECTORY_SEPARATOR . $name;
    $rp = realpath($full);
    if ($rp === false || !is_file($rp)) {
        continue;
    }
    if (!str_starts_with(str_replace('\\', '/', $rp), str_replace('\\', '/', $yearReal))) {
        continue;
    }

    // Ignorar já organizados (subpastas são tratadas noutra execução se necessário).
    if (str_contains($rp, DIRECTORY_SEPARATOR . 'privados' . DIRECTORY_SEPARATOR)) {
        continue;
    }

    $lower = strtolower($name);
    if (!preg_match('/\.(htm|html|pdf)$/i', $lower)) {
        $skipped++;
        continue;
    }

    $baseName = pathinfo($name, PATHINFO_FILENAME);
    if ($baseName === '') {
        $skipped++;
        continue;
    }

    // Só números => privado (NIF).
    if (preg_match('/^\d+$/', $baseName) === 1) {
        $dest = $privDir . DIRECTORY_SEPARATOR . $name;
        if (realpath($dest) !== false) {
            fwrite(STDERR, "Destino já existe, a saltar: {$name}\n");
            $skipped++;
            continue;
        }
        if (@rename($rp, $dest)) {
            $moved++;
        }
        continue;
    }

    // Contém letras ou espaços => documento geral do condomínio.
    if (!preg_match('/[a-zA-ZÀ-ÿ]/u', $baseName)) {
        $skipped++;
        continue;
    }

    $slug = slugify($baseName);
    if ($slug === '') {
        $skipped++;
        continue;
    }

    $targetDir = $yearReal . DIRECTORY_SEPARATOR . $slug;
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
        fwrite(STDERR, "Não foi possível criar pasta {$slug}\n");
        $skipped++;
        continue;
    }

    $dest = $targetDir . DIRECTORY_SEPARATOR . $name;
    if (realpath($dest) !== false) {
        fwrite(STDERR, "Destino já existe, a saltar: {$slug}/{$name}\n");
        $skipped++;
        continue;
    }

    if (@rename($rp, $dest)) {
        $moved++;
    }
}

echo "Ano: {$yearArg}\n";
echo "Movidos: {$moved}\n";
echo "Ignorados: {$skipped}\n";
