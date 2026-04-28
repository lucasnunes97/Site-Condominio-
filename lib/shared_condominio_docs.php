<?php

declare(strict_types=1);

require_once __DIR__ . '/extract_condominio_anchor.php';

function slug_token_alnum_lower(string $s): string
{
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/i', '', $s) ?? '';

    return $s;
}

/**
 * Extrai o segmento após o ano quando o ficheiro segue o padrão do gCondominio:
 * {ANO}-{MM}-condominio-....htm (com ou sem sufixo -cq/-dc/-dr/-fc).
 */
function shared_extract_condominio_stem(string $fileBaseName, int $year): ?string
{
    $y = (string) $year;
    if (!str_starts_with($fileBaseName, $y . '-')) {
        return null;
    }

    $rest = substr($fileBaseName, strlen($y) + 1);
    $rest = preg_replace('/-(cq|dc|dr|fc)$/i', '', $rest) ?? $rest;

    // Espera-se algo como 01-condominio-avenida-2427
    if (!preg_match('/^\d{2}-condominio-/i', $rest)) {
        return null;
    }

    return $rest;
}

/**
 * Quando o extrato privado vem como 2026-<NIF+SENHA>.htm, o stem tem de ser inferido do HTML.
 *
 * @return array{stem: string, html: string}|null
 */
function shared_infer_condominio_stem_from_private(string $html, int $year): ?array
{
    $anchor = html_extract_arial_anchor_text($html);
    if ($anchor === '') {
        return null;
    }

    $anchorSlug = condominio_slug_from_anchor_text($anchor);
    if ($anchorSlug === '') {
        return null;
    }

    $y = (string) $year;
    $reStem = '/' . preg_quote($y, '/') . '-(\\d{2}-condominio-[^\\.\\s\\/"\\\']+)/i';

    if (!preg_match_all($reStem, $html, $matches)) {
        return null;
    }

    $bestStem = null;
    $bestScore = -1;

    foreach ($matches[1] as $rawStem) {
        $stem = trim((string) $rawStem);
        if ($stem === '') {
            continue;
        }

        $stemSlug = slugify(str_replace('-', ' ', $stem));
        if ($stemSlug === '') {
            continue;
        }

        // Igualdade exacta (ideal).
        if ($stemSlug === $anchorSlug) {
            return ['stem' => $stem, 'html' => $html];
        }

        // Caso comum: o ficheiro usa um subconjunto do texto completo do topo (ex.: "...avenida-2427" vs "...avenida-da-republica-2427").
        $score = 0;
        if (str_contains($anchorSlug, $stemSlug)) {
            $score = 100 + strlen($stemSlug);
        } elseif (str_contains($stemSlug, $anchorSlug)) {
            $score = 90 + strlen($anchorSlug);
        } else {
            similar_text($anchorSlug, $stemSlug, $pct);
            $score = (int) round($pct);
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestStem = $stem;
        }
    }

    if ($bestStem === null || $bestScore < 70) {
        return null;
    }

    return ['stem' => $bestStem, 'html' => $html];
}

/**
 * @param array{filename: string, local_path?: string, extension: string}|array{filename: string, remote_dir: string, remote_file: string, extension: string} $privateFound
 */
function shared_verify_stem_in_private_html(array $privateFound, string $stem): bool
{
    return shared_verify_stem_in_html('', $stem, $privateFound);
}

function shared_verify_stem_in_html(string $html, string $stem, ?array $privateFound = null): bool
{
    if ($html === '' && $privateFound !== null && !empty($privateFound['local_path'])) {
        $p = (string) $privateFound['local_path'];
        $html = @file_get_contents($p) ?: '';
    }

    if ($html === '') {
        return false;
    }

    $needle = str_replace('-', ' ', strtolower($stem));
    $needle = preg_replace('/\s+/', ' ', trim($needle)) ?? $needle;
    $hay = strtolower($html);

    // Verificação simples: o stem deve aparecer de forma abrangente no HTML (slug vs texto).
    if (str_contains($hay, strtolower((string) $stem)) || ($needle !== '' && str_contains($hay, $needle))) {
        return true;
    }

    // Tentativa mais robusta: comparar tokens do stem com o texto da âncora Arial (mesmo conteúdo, formatos diferentes).
    $anchor = html_extract_arial_anchor_text($html);
    $anchorSlug = condominio_slug_from_anchor_text($anchor);
    $stemSlug = slugify(str_replace('-', ' ', $stem));

    if ($anchorSlug !== '' && $stemSlug !== '') {
        if ($anchorSlug === $stemSlug) {
            return true;
        }

        if (str_contains($anchorSlug, $stemSlug) || str_contains($stemSlug, $anchorSlug)) {
            return true;
        }

        similar_text($anchorSlug, $stemSlug, $pct);
        if ($pct >= 70.0) {
            return true;
        }
    }

    $stemTok = slug_token_alnum_lower(str_replace('-', '', $stem));
    $anchorTok = slug_token_alnum_lower($anchor);
    if ($stemTok !== '' && $anchorTok !== '' && str_contains($anchorTok, $stemTok)) {
        return true;
    }

    return false;
}
