<?php

declare(strict_types=1);

require_once __DIR__ . '/slugify.php';

function portal_users_load(): array
{
    $local = __DIR__ . '/../portal_users.local.php';
    if (is_readable($local)) {
        $cfg = require $local;
        if (is_callable($cfg)) {
            $cfg = $cfg();
        }
        if (is_array($cfg)) {
            return $cfg;
        }
    }

    return [];
}

/**
 * @return array{nif: string, condo_slug: string}|null
 */
function portal_find_user(string $nifDigits, string $passwordPlain): ?array
{
    foreach (portal_users_load() as $row) {
        $n = isset($row['nif']) ? preg_replace('/\D/', '', (string) $row['nif']) : '';
        $p = isset($row['senha']) ? (string) $row['senha'] : '';
        $slug = isset($row['condo_slug']) ? trim((string) $row['condo_slug']) : '';
        if ($n === '' || $p === '' || $slug === '') {
            continue;
        }
        if ($n !== $nifDigits) {
            continue;
        }
        if (!hash_equals($p, $passwordPlain)) {
            continue;
        }
        $safeSlug = slugify($slug);
        if ($safeSlug === '' || $safeSlug !== $slug) {
            continue;
        }

        return ['nif' => $nifDigits, 'condo_slug' => $safeSlug];
    }

    return null;
}
