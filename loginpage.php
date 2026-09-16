<?php
// Author: Zeday @join.co.id
require_once 'includes/config.php';
require_once 'includes/security.php';

startSecureSession();

if (!empty($_SESSION['login_status']) && $_SESSION['login_status'] === true) {
    header("Location: " . APP_URL . "/maindashboard.php");
    exit();
}

$expired  = isset($_GET['expired']);
$security = isset($_GET['security']);
$logout   = isset($_GET['logout']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Sistem Pendataan Pajak Kendaraan</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      min-height: 100vh;
      background-color: #1e40af;
      background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }

    .wrapper { width: 100%; max-width: 420px; }

    .card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 25px 50px -12px rgba(0,0,0,.35);
      overflow: hidden;
    }

    /* Header banner */
    .card-header {
      background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
      padding: 28px 32px 24px;
      text-align: center;
      color: #fff;
    }
    .logo-row {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 24px;
      margin-bottom: 16px;
    }
    .logo-item { text-align: center; }
    .logo-item img {
      width: 64px;
      height: 64px;
      object-fit: contain;
      border-radius: 50%;
      background: rgba(255,255,255,.12);
      padding: 4px;
      display: block;
      margin: 0 auto 4px;
    }
    .logo-item span { font-size: 11px; opacity: .85; display: block; }
    .logo-divider { width: 1px; height: 48px; background: rgba(255,255,255,.25); }
    .card-header h1 { font-size: 17px; font-weight: 700; line-height: 1.3; }
    .card-header p  { font-size: 13px; opacity: .78; margin-top: 2px; }

    /* Form body */
    .card-body { padding: 28px 32px 32px; }

    .alert {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 12px 14px;
      border-radius: 10px;
      font-size: 13px;
      margin-bottom: 18px;
      border: 1px solid;
    }
    .alert-warning { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
    .alert-danger  { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
    .alert-success { background: #f0fdf4; border-color: #86efac; color: #166534; }
    .alert svg { flex-shrink: 0; width: 16px; height: 16px; }

    .form-title { font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 22px; }

    .field { margin-bottom: 16px; }
    .field:last-of-type { margin-bottom: 20px; }
    label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }

    .input-wrap { position: relative; }
    .input-icon {
      position: absolute;
      left: 11px;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
      display: flex;
      align-items: center;
      pointer-events: none;
    }
    .input-icon svg { width: 18px; height: 18px; }

    input[type=text], input[type=password] {
      width: 100%;
      padding: 10px 40px 10px 38px;
      border: 1.5px solid #d1d5db;
      border-radius: 10px;
      font-size: 14px;
      color: #111827;
      transition: border-color .15s, box-shadow .15s;
      background: #fff;
    }
    input[type=text]:focus, input[type=password]:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59,130,246,.15);
    }

    .toggle-pw {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: #9ca3af;
      padding: 4px;
      display: flex;
      align-items: center;
    }
    .toggle-pw:hover { color: #6b7280; }
    .toggle-pw svg { width: 18px; height: 18px; }

    .error-box {
      display: none;
      align-items: center;
      gap: 8px;
      background: #fef2f2;
      border: 1px solid #fca5a5;
      color: #991b1b;
      border-radius: 10px;
      padding: 11px 14px;
      font-size: 13px;
      margin-bottom: 16px;
    }
    .error-box svg { flex-shrink: 0; width: 15px; height: 15px; }
    .error-box.show { display: flex; }

    .btn-login {
      width: 100%;
      background: #2563eb;
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 11px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: background .15s, opacity .15s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .btn-login:hover:not(:disabled) { background: #1d4ed8; }
    .btn-login:disabled { opacity: .65; cursor: not-allowed; }

    .spinner {
      display: none;
      width: 16px; height: 16px;
      border: 2px solid rgba(255,255,255,.4);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .7s linear infinite;
    }
    .spinner.show { display: block; }
    @keyframes spin { to { transform: rotate(360deg); } }

    .footer-text {
      text-align: center;
      font-size: 11px;
      color: rgba(255,255,255,.55);
      margin-top: 18px;
    }
  </style>
</head>
<body>

<div class="wrapper">
  <div class="card">

    <!-- Header -->
    <div class="card-header">
      <div class="logo-row">
        <div class="logo-item">
          <img src="assets/images/logo-kalteng.png" alt="Kalteng"
               onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Crect fill=%22%231d4ed8%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%22 y=%2255%22 font-size=%2218%22 fill=%22white%22 text-anchor=%22middle%22%3EKT%3C/text%3E%3C/svg%3E'">
          <span>Prov. Kalteng</span>
        </div>
        <div class="logo-divider"></div>
        <div class="logo-item">
          <img src="assets/images/logo-barito-timur.png" alt="Bartim"
               onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Crect fill=%22%2316a34a%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%22 y=%2255%22 font-size=%2213%22 fill=%22white%22 text-anchor=%22middle%22%3EBTM%3C/text%3E%3C/svg%3E'">
          <span>Kab. Barito Timur</span>
        </div>
      </div>
      <h1>Sistem Pendataan</h1>
      <p>Wajib Pajak Kendaraan Bermotor</p>
    </div>

    <!-- Body -->
    <div class="card-body">

      <?php if ($expired): ?>
      <div class="alert alert-warning">
        <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        Sesi Anda telah berakhir. Silakan login kembali.
      </div>
      <?php endif; ?>

      <?php if ($security): ?>
      <div class="alert alert-danger">
        <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
        Terdeteksi aktivitas mencurigakan. Silakan login kembali.
      </div>
      <?php endif; ?>

      <?php if ($logout): ?>
      <div class="alert alert-success">
        <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        Anda berhasil logout. Sampai jumpa!
      </div>
      <?php endif; ?>

      <p class="form-title">Masuk ke Akun Anda</p>

      <!-- Username -->
      <div class="field">
        <label for="username">Username</label>
        <div class="input-wrap">
          <span class="input-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          </span>
          <input id="username" type="text" autocomplete="username" placeholder="Masukkan username">
        </div>
      </div>

      <!-- Password -->
      <div class="field">
        <label for="password">Password</label>
        <div class="input-wrap">
          <span class="input-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
          </span>
          <input id="password" type="password" autocomplete="current-password" placeholder="Masukkan password">
          <button type="button" class="toggle-pw" id="togglePw" aria-label="Tampilkan password">
            <svg id="eyeOn" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <svg id="eyeOff" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
          </button>
        </div>
      </div>

      <!-- Error -->
      <div class="error-box" id="errorBox">
        <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        <span id="errorMsg"></span>
      </div>

      <!-- Submit -->
      <button class="btn-login" id="btnLogin">
        <div class="spinner" id="spinner"></div>
        <span id="btnText">Masuk</span>
      </button>

    </div>
  </div>

  <p class="footer-text">&copy; <?php echo date('Y'); ?> Bapenda Barito Timur &mdash; Kalimantan Tengah</p>
</div>

<script>
(function() {
  var pwInput  = document.getElementById('password');
  var togglePw = document.getElementById('togglePw');
  var eyeOn    = document.getElementById('eyeOn');
  var eyeOff   = document.getElementById('eyeOff');
  var btnLogin = document.getElementById('btnLogin');
  var spinner  = document.getElementById('spinner');
  var btnText  = document.getElementById('btnText');
  var errorBox = document.getElementById('errorBox');
  var errorMsg = document.getElementById('errorMsg');

  function showError(msg) {
    errorMsg.textContent = msg;
    errorBox.classList.add('show');
  }
  function hideError() {
    errorBox.classList.remove('show');
  }
  function setLoading(on) {
    btnLogin.disabled = on;
    spinner.className = 'spinner' + (on ? ' show' : '');
    btnText.textContent = on ? 'Memproses...' : 'Masuk';
  }

  togglePw.addEventListener('click', function() {
    var isText = pwInput.type === 'text';
    pwInput.type = isText ? 'password' : 'text';
    eyeOn.style.display  = isText ? '' : 'none';
    eyeOff.style.display = isText ? 'none' : '';
  });

  function doLogin() {
    hideError();
    var username = document.getElementById('username').value.trim();
    var password = document.getElementById('password').value;
    if (!username || !password) {
      showError('Username dan password harus diisi.');
      return;
    }
    setLoading(true);
    fetch('login/login_process.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ username: username, password: password, 'g-recaptcha-response': '' })
    })
    .then(function(r) { return r.json(); })
    .then(function(json) {
      if (json.status === 'success') {
        window.location.href = '<?php echo APP_URL; ?>/maindashboard.php';
      } else {
        showError(json.message || 'Login gagal. Periksa kembali username dan password.');
        setLoading(false);
      }
    })
    .catch(function() {
      showError('Gagal terhubung ke server. Silakan coba lagi.');
      setLoading(false);
    });
  }

  btnLogin.addEventListener('click', doLogin);
  document.getElementById('username').addEventListener('keydown', function(e) { if (e.key === 'Enter') doLogin(); });
  document.getElementById('password').addEventListener('keydown', function(e) { if (e.key === 'Enter') doLogin(); });
  document.getElementById('username').focus();
})();
</script>
</body>
</html>
