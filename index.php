<?php
// Author: Zeday @join.co.id
require_once 'includes/config.php';
require_once 'includes/security.php';
startSecureSession();
if (!empty($_SESSION['login_status']) && $_SESSION['login_status'] === true) {
    header("Location: " . APP_URL . "/maindashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Selamat Datang | Sistem Pendataan Wajib Pajak Kendaraan</title>

  <!-- ✅ Tailwind CSS CDN -->
  <script src="assets/js/tailwind.min.js"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-blue-100 min-h-screen flex items-center justify-center">

  <div class="text-center bg-white shadow-lg rounded-2xl p-10 w-full max-w-lg border border-blue-200">
    
    <!-- Logo Header -->
    <div class="flex justify-center items-center gap-8 mb-6 pb-6 border-b border-gray-200">
      <div class="text-center">
        <img src="assets/images/logo-kalteng.png" 
             alt="Logo Provinsi Kalimantan Tengah" 
             class="h-24 w-24 object-contain mx-auto mb-2"
             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Crect fill=%22%234F46E5%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%22 y=%2250%22 font-size=%2214%22 fill=%22white%22 text-anchor=%22middle%22 dy=%22.3em%22%3EKALTENG%3C/text%3E%3C/svg%3E'">
        <p class="text-xs text-gray-700 font-semibold">Pemerintah Provinsi</p>
        <p class="text-xs text-gray-700 font-semibold">Kalimantan Tengah</p>
      </div>
      <div class="text-center">
        <img src="assets/images/logo-barito-timur.png" 
             alt="Logo Kabupaten Barito Timur" 
             class="h-24 w-24 object-contain mx-auto mb-2"
             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Crect fill=%22%2310B981%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%22 y=%2250%22 font-size=%2212%22 fill=%22white%22 text-anchor=%22middle%22 dy=%22.3em%22%3EBARITO TIMUR%3C/text%3E%3C/svg%3E'">
        <p class="text-xs text-gray-700 font-semibold">Pemerintah Kabupaten</p>
        <p class="text-xs text-gray-700 font-semibold">Barito Timur</p>
      </div>
    </div>

    <h1 class="text-3xl font-extrabold text-blue-700 mb-3">
      Sistem Pendataan Wajib Pajak Kendaraan Bermotor
    </h1>
    <p class="text-gray-600 mb-8">
      Silakan isi data wajib pajak kendaraan bermotor Anda dengan lengkap dan benar.
    </p>

    <a href="form_wajib_pajak.php"
       class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-xl shadow-md transition duration-300">
      Isi Form Wajib Pajak
    </a>

    <div class="mt-8 text-sm text-gray-500">
      © 2025 Badan Pendapatan Daerah
    </div>
  </div>

</body>
</html>
