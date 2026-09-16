<?php
// Author: Zeday @join.co.id
require_once 'includes/config.php';
require_once 'includes/security.php';
require_once 'includes/db_connect.php';
require_once 'includes/usersession.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword  = $_POST['current_password'] ?? '';
    $newPassword      = $_POST['new_password'] ?? '';
    $confirmPassword  = $_POST['confirm_password'] ?? '';
    $csrfToken        = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($csrfToken)) {
        $message = 'Token keamanan tidak valid. Muat ulang halaman.';
        $messageType = 'error';
    } elseif (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = 'Semua field harus diisi.';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Password baru dan konfirmasi tidak cocok.';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 8) {
        $message = 'Password minimal 8 karakter.';
        $messageType = 'error';
    } elseif ($currentPassword === $newPassword) {
        $message = 'Password baru harus berbeda dari password lama.';
        $messageType = 'error';
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $message = 'User tidak ditemukan.';
            $messageType = 'error';
        } else {
            $user = $result->fetch_assoc();
            if (!password_verify($currentPassword, $user['password'])) {
                $message = 'Password lama tidak sesuai.';
                $messageType = 'error';
                logSecurityEvent('password_change_failed', ['user_id' => $userid, 'username' => $username, 'reason' => 'wrong_current_password']);
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->bind_param("si", $hashedPassword, $userid);

                if ($updateStmt->execute()) {
                    $message = 'Password berhasil diubah! Anda akan diarahkan ke halaman login.';
                    $messageType = 'success';
                    logSecurityEvent('password_changed', ['user_id' => $userid, 'username' => $username]);
                    session_unset();
                    session_destroy();
                    header("refresh:2;url=" . APP_URL . "/loginpage.php");
                } else {
                    $message = 'Gagal mengubah password. Silakan coba lagi.';
                    $messageType = 'error';
                }
                $updateStmt->close();
            }
        }
        $stmt->close();
    }
}

$csrfToken = generateCSRFToken();
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ubah Password — Sistem Pajak Kendaraan</title>
  <script src="assets/js/tailwind.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">

<!-- Header -->
<header class="bg-gradient-to-r from-blue-800 to-blue-600 text-white shadow-lg sticky top-0 z-40">
  <div class="max-w-xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <a href="maindashboard.php" class="flex items-center gap-1 text-blue-200 hover:text-white transition text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Dashboard
      </a>
      <span class="text-blue-300">/</span>
      <h1 class="font-bold text-base">Ubah Password</h1>
    </div>
    <div class="flex items-center gap-2">
      <span class="text-sm text-blue-200">👤 <?php echo escapeHtml($username); ?></span>
      <a href="logout.php" class="flex items-center gap-1.5 bg-red-600 hover:bg-red-500 px-3 py-1.5 rounded-lg text-sm font-semibold transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        <span class="hidden sm:block">Logout</span>
      </a>
    </div>
  </div>
</header>

<main class="max-w-xl mx-auto px-4 sm:px-6 py-8">
  <div class="bg-white rounded-2xl shadow-md overflow-hidden">

    <div class="px-6 py-5 border-b border-gray-100">
      <h2 class="font-bold text-gray-800 text-lg flex items-center gap-2">
        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
        Ganti Password Akun
      </h2>
      <p class="text-gray-400 text-sm mt-0.5">Setelah diubah, Anda akan logout otomatis</p>
    </div>

    <div class="p-6 space-y-5">

      <?php if (!empty($message)): ?>
      <div class="flex items-start gap-3 p-4 rounded-xl <?php echo $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'; ?>">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
          <?php if ($messageType === 'success'): ?>
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
          <?php else: ?>
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
          <?php endif; ?>
        </svg>
        <p class="text-sm font-medium"><?php echo escapeHtml($message); ?></p>
      </div>
      <?php endif; ?>

      <!-- Password strength info -->
      <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
        <p class="text-xs font-semibold text-blue-700 mb-2">Syarat Password:</p>
        <ul class="space-y-1 text-xs text-blue-600">
          <li class="flex items-center gap-1.5"><svg class="w-3.5 h-3.5 text-blue-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> Minimal 8 karakter</li>
          <li class="flex items-center gap-1.5"><svg class="w-3.5 h-3.5 text-blue-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> Berbeda dari password lama</li>
          <li class="flex items-center gap-1.5"><svg class="w-3.5 h-3.5 text-blue-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> <span class="text-blue-400">Disarankan: huruf besar, kecil, angka, simbol</span></li>
        </ul>
      </div>

      <form method="POST" class="space-y-4" id="pwForm">
        <input type="hidden" name="csrf_token" value="<?php echo escapeHtml($csrfToken); ?>">

        <div>
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Password Lama <span class="text-red-500">*</span></label>
          <input type="password" name="current_password" required
                 class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                 placeholder="Masukkan password lama">
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Password Baru <span class="text-red-500">*</span></label>
          <input type="password" name="new_password" id="newPass" required minlength="8"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                 placeholder="Min. 8 karakter">
          <div class="mt-2">
            <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
              <div id="strengthBar" class="h-full rounded-full transition-all bg-gray-300" style="width:0%"></div>
            </div>
            <p id="strengthLabel" class="text-xs mt-1 text-gray-400"></p>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Konfirmasi Password Baru <span class="text-red-500">*</span></label>
          <input type="password" name="confirm_password" id="confirmPass" required
                 class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                 placeholder="Ulangi password baru">
          <p id="matchMsg" class="text-xs mt-1 hidden"></p>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 rounded-xl transition flex items-center justify-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
          Ubah Password
        </button>
      </form>
    </div>
  </div>
</main>

<script>
const newPass     = document.getElementById('newPass');
const confirmPass = document.getElementById('confirmPass');
const strengthBar = document.getElementById('strengthBar');
const strengthLabel = document.getElementById('strengthLabel');
const matchMsg    = document.getElementById('matchMsg');

newPass.addEventListener('input', function() {
  const p = this.value;
  let s = 0;
  if (p.length >= 8) s++;
  if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++;
  if (/[0-9]/.test(p)) s++;
  if (/[^a-zA-Z0-9]/.test(p)) s++;
  const pct = s * 25;
  const colors = ['bg-gray-300','bg-red-500','bg-amber-500','bg-yellow-400','bg-green-500'];
  const labels = ['','Lemah','Cukup','Baik','Kuat'];
  const textColors = ['text-gray-400','text-red-500','text-amber-500','text-yellow-600','text-green-600'];
  strengthBar.style.width = pct + '%';
  strengthBar.className = 'h-full rounded-full transition-all ' + (colors[s] || 'bg-gray-300');
  strengthLabel.textContent = labels[s] || '';
  strengthLabel.className = 'text-xs mt-1 ' + (textColors[s] || 'text-gray-400');
});

confirmPass.addEventListener('input', function() {
  if (!this.value) { matchMsg.classList.add('hidden'); return; }
  matchMsg.classList.remove('hidden');
  if (newPass.value !== this.value) {
    matchMsg.textContent = 'Password tidak cocok';
    matchMsg.className = 'text-xs mt-1 text-red-500';
    this.setCustomValidity('Password tidak cocok');
  } else {
    matchMsg.textContent = 'Password cocok ✓';
    matchMsg.className = 'text-xs mt-1 text-green-600';
    this.setCustomValidity('');
  }
});
</script>
</body>
</html>
