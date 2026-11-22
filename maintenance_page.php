<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Dalam Maintenance</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --blue: #1769ff; --dark: #071b3a; }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            overflow: hidden;
            font-family: Inter, system-ui, sans-serif;
            background: linear-gradient(135deg, #071b3a 0%, #1455ad 52%, #20a4f3 100%);
        }
        .orb {
            position: fixed;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            background: rgba(255,255,255,.1);
            filter: blur(2px);
            animation: drift 9s ease-in-out infinite alternate;
        }
        .orb-one { top: -100px; left: -70px; }
        .orb-two { right: -100px; bottom: -120px; width: 360px; height: 360px; animation-delay: -3s; }
        .maintenance-card {
            position: relative;
            max-width: 590px;
            border: 1px solid rgba(255,255,255,.55);
            border-radius: 28px;
            background: rgba(255,255,255,.94);
            box-shadow: 0 25px 70px rgba(0, 20, 70, .28);
            animation: card-in .8s cubic-bezier(.2,.8,.2,1) both;
        }
        .toolbox {
            display: inline-grid;
            place-items: center;
            width: 82px;
            height: 82px;
            border-radius: 24px;
            color: white;
            background: linear-gradient(135deg, var(--blue), #6f42c1);
            box-shadow: 0 12px 25px rgba(23,105,255,.3);
            animation: tool-float 3s ease-in-out infinite;
        }
        .toolbox i { animation: tool-spin 7s linear infinite; }
        .loading-dots span {
            display: inline-block;
            width: 8px;
            height: 8px;
            margin: 0 3px;
            border-radius: 50%;
            background: var(--blue);
            animation: dot 1.2s ease-in-out infinite;
        }
        .loading-dots span:nth-child(2) { animation-delay: .15s; }
        .loading-dots span:nth-child(3) { animation-delay: .3s; }
        @keyframes card-in { from { opacity: 0; transform: translateY(35px) scale(.96); } to { opacity: 1; transform: none; } }
        @keyframes tool-float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-9px); } }
        @keyframes tool-spin { to { transform: rotate(360deg); } }
        @keyframes dot { 0%,100% { opacity: .3; transform: translateY(0); } 50% { opacity: 1; transform: translateY(-5px); } }
        @keyframes drift { to { transform: translate(35px, 25px) scale(1.08); } }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; }
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">
    <div class="orb orb-one"></div>
    <div class="orb orb-two"></div>
    <main class="maintenance-card text-center p-4 p-md-5">
        <div class="toolbox mx-auto mb-4"><i class="fas fa-wrench fa-2x"></i></div>
        <div class="text-uppercase small fw-bold text-primary mb-2" style="letter-spacing: .16em;">Sebentar lagi kembali</div>
        <h1 class="fw-bold mb-3" style="color: var(--dark);">Website Sedang Maintenance</h1>
        <p class="text-secondary mb-4">Kami sedang melakukan perbaikan dan pembaruan agar pengalaman Anda menjadi lebih baik.</p>
        <div class="loading-dots mb-2" aria-label="Sedang memproses">
            <span></span><span></span><span></span>
        </div>
        <small class="text-muted">Silakan kembali beberapa saat lagi.</small>
    </main>
</body>
</html>
