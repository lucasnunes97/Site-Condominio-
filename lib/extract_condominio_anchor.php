<?php

declare(strict_types=1);

require_once __DIR__ . '/slugify.php';

/**
 * Extrai texto relevante dos primeiros <font face="Arial" size="1">…</font> do HTML.
 */
function html_extract_arial_anchor_text(string $html): string
{
    if (class_exists('DOMDocument')) {
        $prev = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $loaded = @$doc->loadHTML('<?xml encoding="UTF-8"?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if ($loaded) {
            $xp = new DOMXPath($doc);
            $nodes = $xp->query("//font[@face='Arial' and @size='1']");
            if ($nodes !== false && $nodes->length > 0) {
                $chunks = [];
                $max = min(8, $nodes->length);
                for ($i = 0; $i < $max; $i++) {
                    $t = trim(preg_replace('/\s+/u', ' ', $nodes->item($i)->textContent ?? '') ?? '');
                    if ($t !== '') {
                        $chunks[] = $t;
                    }
                }

                $out = trim(implode(' ', $chunks));
                if ($out !== '') {
                    return $out;
                }
            }
        }
    }

    // Fallback sem extensão DOM (evita fatal error em alojamentos sem php-xml): regex tolerante a atributos.
    if (preg_match_all('/<font\\b[^>]*\\bface\\s*=\\s*([\\"\'])Arial\\1[^>]*\\bsize\\s*=\\s*([\\"\'])1\\2[^>]*>(.*?)<\\/font>/is', $html, $mm)) {
        $pieces = [];
        foreach ($mm[3] as $chunk) {
            $txt = trim(html_entity_decode(strip_tags((string) $chunk), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $txt = trim(preg_replace('/\s+/u', ' ', $txt) ?? '');
            if ($txt !== '') {
                $pieces[] = $txt;
            }
            if (count($pieces) >= 8) {
                break;
            }
        }

        return trim(implode(' ', $pieces));
    }

    return '';
}

/**
 * A partir do texto do cabeçalho, tenta identificar o nome do condomínio e gerar slug.
 */
function condominio_slug_from_anchor_text(string $anchorText): string
{
    $t = trim($anchorText);
    if ($t === '') {
        return '';
    }

    // Procura linhas tipo "Condominio …" / "Condomínio …"
    if (preg_match('/cond[oó]minio\b\s*[:\-]?\s*(.+)/iu', $t, $m)) {
        $candidate = trim($m[1]);
        return slugify($candidate);
    }

    if (preg_match('/cond[oó]minio\b\s+(.+)/iu', $t, $m)) {
        return slugify(trim($m[1]));
    }

    // Fallback: usar o primeiro fragmento com letras/dígitos suficientes.
    return slugify($t);
}

/**
 * Lê um .htm/.html e devolve o slug do condomínio ou '' se não conseguir.
 */
function condominio_slug_from_html_file(string $path): string
{
    $data = @file_get_contents($path);
    if ($data === false || $data === '') {
        return '';
    }

    $anchor = html_extract_arial_anchor_text($data);

    return condominio_slug_from_anchor_text($anchor);
}
