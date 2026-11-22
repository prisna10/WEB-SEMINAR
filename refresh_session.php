<?php
require 'config.php';

session_set_cookie_params(SESSION_TIMEOUT_SECONDS, "/");
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'expired']);
    exit();
}

if (!isset($_SESSION['session_expires_at']) || time() >= $_SESSION['session_expires_at']) {
    session_unset();
    session_destroy();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'expired']);
    exit();
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'expires_at' => $_SESSION['session_expires_at']
]);
