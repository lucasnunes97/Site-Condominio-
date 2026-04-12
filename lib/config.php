<?php

declare(strict_types=1);

function load_config(): array
{
    $local = __DIR__ . '/../config.local.php';
    if (is_readable($local)) {
        $cfg = require $local;
        if (is_array($cfg)) {
            return $cfg;
        }
    }
    $example = __DIR__ . '/../config.example.php';
    if (is_readable($example)) {
        return require $example;
    }
    return [];
}
