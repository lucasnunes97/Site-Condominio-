<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION['csrf_seguros'])) {
    $_SESSION['csrf_seguros'] = bin2hex(random_bytes(32));
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['csrf' => $_SESSION['csrf_seguros']]);
