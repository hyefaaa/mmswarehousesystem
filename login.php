<?php
// login.php
// Portal Log Masuk Premium (Glassmorphism & Secure authentication)

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah log masuk, terus lencongkan ke Dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config/db.php';

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        try {
            // Carian pengguna di dalam users
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user) {
                // Menyokong padanan teks biasa (legacy data) DAN password_verify (secure hash)
                if ($password === $user['password_hash'] || password_verify($password, $user['password_hash'])) {
                    // Set Sesi
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['role']      = $user['role']; // cth: 'admin', 'dealer', dll.
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['hd_id']     = $user['hd_id'] ?? null;

                    if (function_exists('log_system_activity')) {
                        log_system_activity("User Logged In", "users", $user['id'], "Pengguna '{$user['username']}' berjaya log masuk dari IP {$_SERVER['REMOTE_ADDR']}.");
                    }

                    header('Location: index.php');
                    exit;
                }
            }
            $error = 'Nama pengguna atau kata laluan salah!';

        } catch (Exception $e) {
            $error = 'Masalah pelayan: ' . $e->getMessage();
        }
    } else {
        $error = 'Sila isi semua ruangan!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
    (function() {
        const savedTheme = localStorage.getItem('mms_theme');
        const theme = savedTheme ? savedTheme : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-bs-theme', theme);
    })();
    const MMS_THEME = {
        get: function() {
            return document.documentElement.getAttribute('data-theme') || 'light';
        },
        set: function(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.setAttribute('data-bs-theme', theme);
            localStorage.setItem('mms_theme', theme);
            this.updateUI(theme);
        },
        toggle: function() {
            const current = this.get();
            const next = current === 'dark' ? 'light' : 'dark';
            this.set(next);
        },
        updateUI: function(theme) {
            const btn = document.getElementById('themeToggleBtn');
            const icon = document.getElementById('themeIcon');
            const text = document.getElementById('themeText');
            if (!icon) return;
            
            if (theme === 'dark') {
                icon.className = 'bi bi-sun-fill text-warning';
                if (text) text.textContent = 'Light';
                if (btn) btn.setAttribute('title', 'Tukar ke Tema Cerah (Light Mode)');
            } else {
                icon.className = 'bi bi-moon-stars-fill text-info';
                if (text) text.textContent = 'Dark';
                if (btn) btn.setAttribute('title', 'Tukar ke Tema Gelap (Dark Mode)');
            }
        }
    };
    document.addEventListener("DOMContentLoaded", function() {
        MMS_THEME.updateUI(MMS_THEME.get());
    });
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMS Login | Moo Moo Supplies</title>
    <!-- Favicon / Gambar Browser Logo -->
    <link rel="shortcut icon" href="img/logo.png" type="image/png">
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom Design & Responsive Stylesheet -->
    <link href="assets/css/style.css" rel="stylesheet">
    
    <style>
        :root {
            --mms-bg-grad: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }

        body {
            background: var(--mms-bg-grad);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            position: relative;
            padding: 20px;
        }

        /* Glassmorphism Card Upgraded */
        .login-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 3.5rem 2.5rem;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 10;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1.5px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
            border-radius: 12px;
            padding: 12px 18px;
            font-weight: 500;
            transition: var(--transition-smooth);
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.08) !important;
            border-color: var(--mms-cyan) !important;
            box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.2) !important;
        }

        .btn-mms-submit {
            background: var(--gradient-accent) !important;
            color: white;
            border: none;
            font-weight: 700;
            padding: 14px;
            border-radius: 12px;
            letter-spacing: 0.5px;
            transition: var(--transition-smooth);
        }

        .btn-mms-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(6, 182, 212, 0.35);
            color: white;
        }

        .form-label {
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.8px;
            color: #94a3b8;
            text-transform: uppercase;
        }

        /* Abstract glowing circles in background */
        .glow-circle {
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(6, 182, 212, 0.12);
            filter: blur(90px);
            z-index: 1;
            pointer-events: none;
        }

        .glow-1 { top: 10%; left: 20%; }
        .glow-2 { bottom: 10%; right: 20%; background: rgba(99, 102, 241, 0.1); }

        /* Mobile Viewport Sizing Adjustments */
        @media (max-width: 480px) {
            .login-card {
                padding: 2.2rem 1.5rem;
                border-radius: 18px;
            }
            
            .login-card img {
                width: 90px !important;
            }
            
            body {
                padding: 15px;
            }
        }

        #lang-btn-en.active, #lang-btn-ms.active {
            background: rgba(6,182,212,0.85) !important;
            color: white !important;
            box-shadow: 0 2px 8px rgba(6,182,212,0.4);
        }
        #lang-btn-en:hover:not(.active), #lang-btn-ms:hover:not(.active) {
            background: rgba(255,255,255,0.12) !important;
            color: white !important;
        }
    </style>
</head>
<body>

    <div class="glow-circle glow-1"></div>
    <div class="glow-circle glow-2"></div>

    <!-- Floating Theme & Language Selector at the top right -->
    <div style="position: absolute; top: 20px; right: 20px; z-index: 100;" class="d-flex align-items-center gap-2">
        <button id="themeToggleBtn" type="button" class="theme-toggle-btn" onclick="MMS_THEME.toggle()" title="Tukar Tema (Light/Dark)">
            <i class="bi bi-moon-stars-fill text-info" id="themeIcon"></i>
            <span id="themeText" class="d-none d-sm-inline">Dark</span>
        </button>
        <div class="d-flex align-items-center" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 20px; padding: 3px 4px; gap: 2px;">
            <button id="lang-btn-en"
                    onclick="MMS_LANG.set('en')"
                    title="Switch to English"
                    style="background:transparent; border:none; border-radius:16px; padding:3px 9px; font-size:0.75rem; font-weight:700; color:rgba(255,255,255,0.6); cursor:pointer; transition:all 0.2s; letter-spacing:0.3px;">
                🇬🇧 EN
            </button>
            <button id="lang-btn-ms"
                    onclick="MMS_LANG.set('ms')"
                    title="Tukar ke Bahasa Melayu"
                    style="background:transparent; border:none; border-radius:16px; padding:3px 9px; font-size:0.75rem; font-weight:700; color:rgba(255,255,255,0.6); cursor:pointer; transition:all 0.2s; letter-spacing:0.3px;">
                🇲🇾 BM
            </button>
        </div>
    </div>

    <div class="login-card text-center">
        
        <div class="mb-4">
            <img src="img/logo.png" alt="MMS Logo" style="width: 110px; height: auto; border-radius: 16px; box-shadow: 0 10px 30px rgba(2, 132, 199, 0.25); border: 2px solid rgba(255, 255, 255, 0.1);">
        </div>
        
        <h3 class="fw-800 text-white mb-1" style="letter-spacing: -0.5px;">MOO MOO SUPPLIES</h3>
        <p class="small mb-4" style="color: #cbd5e1;" data-lang="login_title">Warehouse & Logistics Management</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger text-start small p-3 rounded-3" role="alert">
                <i class="bi bi-exclamation-octagon me-2"></i> <?= htmlspecialchars($error ?? '') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="text-start" id="loginForm">
            <div class="mb-3">
                <label class="form-label" data-lang="login_username">Username</label>
                <input type="text" name="username" class="form-control" placeholder="Type username" data-lang-placeholder="login_username" required autocomplete="username">
            </div>
            
            <div class="mb-4">
                <label class="form-label" data-lang="login_password">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Type password" data-lang-placeholder="login_password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-mms-submit w-100 py-3 mt-2 shadow-lg">
                <i class="bi bi-shield-lock-fill me-2"></i> <span data-lang="login_btn">LOG IN TO PORTAL</span>
            </button>
        </form>
        
        <div class="mt-4 pt-2 border-top border-secondary border-opacity-10">
            <p class="small mb-0" style="color: #cbd5e1;"><i class="bi bi-lock me-1"></i> <span data-lang="login_intranet">Intranet Access Only</span></p>
        </div>

    </div>

    <!-- MMS Dual Language Engine -->
    <script src="lang/translations.js?v=<?= time() ?>"></script>
</body>
</html>
