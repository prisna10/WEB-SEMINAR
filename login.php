<?php
require 'config.php';

session_set_cookie_params(SESSION_TIMEOUT_SECONDS, "/");
session_start();

if (
    isset($_SESSION['admin_logged_in'])
    && $_SESSION['admin_logged_in'] === true
    && (!isset($_SESSION['session_expires_at']) || time() >= $_SESSION['session_expires_at'])
) {
    session_unset();
    session_destroy();
    header("Location: login.php?expired=1");
    exit();
}

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: data_pendaftar.php");
    exit();
}

$error = "";
$timeout_message = "";
if (isset($_GET['expired']) && $_GET['expired'] == 1) {
    $timeout_message = "Sesi Anda telah berakhir. Silakan login kembali.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['session_expires_at'] = time() + SESSION_TIMEOUT_SECONDS;
        header("Location: data_pendaftar.php");
        exit();
    } else {
        $error = "Username atau Password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Seminar UHW</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #002244, #00509e); }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center vh-100">
    <div class="card shadow-lg border-0" style="width: 100%; max-width: 400px; border-radius: 15px;">
        <div class="card-header bg-white text-primary text-center py-4 border-0" style="border-radius: 15px 15px 0 0;">
            <i class="fas fa-user-shield fa-3x mb-3"></i>
            <h4 class="mb-0 fw-bold">Login Admin</h4>
        </div>
        <div class="card-body p-4 bg-light">
            <div class="alert alert-info py-2">
                <div class="fw-bold mb-1">Kredensial Admin</div>
                <div>Username: <code><?php echo htmlspecialchars(ADMIN_USERNAME, ENT_QUOTES, 'UTF-8'); ?></code></div>
                <div>Password: <code><?php echo htmlspecialchars(ADMIN_PASSWORD, ENT_QUOTES, 'UTF-8'); ?></code></div>
            </div>
            <?php if($timeout_message): ?>
                <div class="alert alert-warning text-center py-2 fw-bold"><i class="fas fa-clock me-1"></i> <?php echo $timeout_message; ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-danger text-center py-2 fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark">Username</label>
                    <input type="text" name="username" class="form-control form-control-lg" placeholder="Masukkan username" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark">Password</label>
                    <input type="password" name="password" class="form-control form-control-lg" placeholder="Masukkan password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 btn-lg rounded-pill fw-bold shadow-sm">Masuk Sistem</button>
            </form>
        </div>
        <div class="card-footer text-center bg-white border-0 py-3" style="border-radius: 0 0 15px 15px;">
            <a href="index.html" class="text-decoration-none text-muted"><i class="fas fa-arrow-left me-1"></i>Kembali ke Web Utama</a>
        </div>
    </div>
</body>
</html>