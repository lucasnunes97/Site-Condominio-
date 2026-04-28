<?php

declare(strict_types=1);

/**
 * Gera um slug seguro para nomes de pastas (ASCII minúsculas, hífens).
 */
function slugify(string $text): string
{
    $t = trim($text);
    if ($t === '') {
        return '';
    }

    if (class_exists(\Transliterator::class)) {
        $trans = \Transliterator::create('Any-Latin; Latin-ASCII');
        if ($trans !== null) {
            $conv = $trans->transliterate($t);
            if (is_string($conv) && $conv !== '') {
                $t = $conv;
            }
        }
    } elseif (function_exists('iconv')) {
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
        if (is_string($conv) && $conv !== '') {
            $t = $conv;
        }
    }

    $t = strtolower($t);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t) ?? '';
    $t = trim($t, '-');

    return $t;
}
