<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/lib/portal_auth.php';

$nifRaw = isset($_POST['nif']) ? (string) $_POST['nif'] : '';
$passRaw = isset($_POST['senha']) ? (string) $_POST['senha'] : '';

$nifDigits = preg_replace('/\D/', '', $nifRaw) ?? '';

if ($nifDigits === '' || strlen($nifDigits) !== 9 || !ctype_digit($nifDigits)) {
    $_SESSION['portal_error'] = 'NIF inválido.';
    header('Location: portal.php');
    exit;
}

$user = portal_find_user($nifDigits, $passRaw);
if ($user === null) {
    $_SESSION['portal_error'] = 'Credenciais inválidas.';
    header('Location: portal.php');
    exit;
}

$_SESSION['u_nif'] = $user['nif'];
$_SESSION['u_condo_slug'] = $user['condo_slug'];
unset($_SESSION['portal_error']);

header('Location: portal.php');
exit;
