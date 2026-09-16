<?php
// Author: Zeday @join.co.id
require_once 'includes/config.php';
require_once 'includes/security.php';
require_once 'includes/db_connect.php';
require_once 'includes/usersession.php';
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Sistem Pajak Kendaraan</title>
  <script src="assets/js/tailwind.min.js"></script>
  <script src="assets/js/vue.global.prod.js"></script>
  <script src="assets/js/chart.umd.min.js"></script>
  <style>
    [v-cloak] { display: none; }
    .toast-enter { animation: slideIn .3s ease; }
    .toast-leave { animation: slideOut .3s ease forwards; }
    @keyframes slideIn  { from { transform: translateX(110%); opacity:0 } to { transform: translateX(0); opacity:1 } }
    @keyframes slideOut { from { transform: translateX(0); opacity:1 } to { transform: translateX(110%); opacity:0 } }
    .modal-bg { backdrop-filter: blur(4px); }
    .table-row-hover:hover { background-color: #eff6ff; cursor: pointer; }
    .stat-card { transition: transform .15s, box-shadow .15s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,.12); }
  </style>
</head>
<body class="bg-gray-100 min-h-screen">

<div id="app">

<!-- ===== TOAST NOTIFICATIONS ===== -->
<div class="fixed top-5 right-5 z-[100] flex flex-col gap-2 max-w-sm w-full pointer-events-none">
  <transition-group name="toast">
    <div v-for="t in toasts" :key="t.id"
         :class="t.type === 'success' ? 'bg-green-600' : t.type === 'warning' ? 'bg-amber-500' : 'bg-red-600'"
         class="pointer-events-auto flex items-start gap-3 text-white rounded-xl shadow-xl px-4 py-3 toast-enter">
      <span class="text-lg flex-shrink-0">{{ t.type === 'success' ? '✅' : t.type === 'warning' ? '⚠️' : '❌' }}</span>
      <p class="text-sm font-medium leading-snug flex-1">{{ t.message }}</p>
      <button @click="removeToast(t.id)" class="opacity-70 hover:opacity-100 text-lg leading-none">&times;</button>
    </div>
  </transition-group>
</div>

<!-- ===== HEADER ===== -->
<header class="bg-gradient-to-r from-blue-800 to-blue-600 text-white shadow-lg sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex justify-between items-center gap-4">
    <!-- Logo + Title -->
    <div class="flex items-center gap-3 min-w-0">
      <div class="flex items-center gap-2 flex-shrink-0">
        <img src="assets/images/logo-kalteng.png" alt="Kalteng"
             class="h-10 w-10 object-contain bg-white/15 rounded-full p-0.5"
             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Crect fill=%22%231d4ed8%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%22 y=%2255%22 font-size=%2224%22 fill=%22white%22 text-anchor=%22middle%22%3EKT%3C/text%3E%3C/svg%3E'">
        <img src="assets/images/logo-barito-timur.png" alt="Bartim"
             class="h-10 w-10 object-contain bg-white/15 rounded-full p-0.5"
             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Crect fill=%22%2316a34a%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%22 y=%2255%22 font-size=%2220%22 fill=%22white%22 text-anchor=%22middle%22%3EBTM%3C/text%3E%3C/svg%3E'">
      </div>
      <div class="min-w-0">
        <h1 class="font-bold text-base sm:text-lg leading-tight truncate">Pendataan Wajib Pajak Kendaraan</h1>
        <p class="text-blue-200 text-xs hidden sm:block">Kabupaten Barito Timur</p>
      </div>
    </div>

    <!-- Nav Actions -->
    <div class="flex items-center gap-2 flex-shrink-0">
      <?php if ($role === 'administrator'): ?>
      <a href="form_wajib_pajak.php"
         class="hidden sm:flex items-center gap-1.5 bg-green-500 hover:bg-green-400 px-3 py-1.5 rounded-lg text-sm font-semibold transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah Data
      </a>
      <a href="admin_kecamatan.php"
         class="hidden md:flex items-center gap-1.5 bg-blue-500 hover:bg-blue-400 px-3 py-1.5 rounded-lg text-sm font-semibold transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
        Kecamatan
      </a>
      <a href="manage_users.php"
         class="hidden md:flex items-center gap-1.5 bg-purple-500 hover:bg-purple-400 px-3 py-1.5 rounded-lg text-sm font-semibold transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        Users
      </a>
      <a href="admin_trash.php"
         class="hidden md:flex items-center gap-1.5 bg-orange-500 hover:bg-orange-400 px-3 py-1.5 rounded-lg text-sm font-semibold transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        Trash
      </a>
      <?php endif; ?>

      <!-- User Menu -->
      <div class="relative" @mouseleave="userMenu = false">
        <button @click="userMenu = !userMenu"
                class="flex items-center gap-2 bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-lg text-sm font-semibold transition">
          <span class="hidden sm:block"><?php echo escapeHtml($username); ?></span>
          <svg class="w-5 h-5 rounded-full bg-white/20 p-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        </button>
        <div v-show="userMenu" class="absolute right-0 mt-2 w-44 bg-white rounded-xl shadow-xl border border-gray-100 py-1 text-gray-700 text-sm z-50">
          <div class="px-4 py-2 border-b border-gray-100">
            <p class="font-semibold text-gray-800"><?php echo escapeHtml($username); ?></p>
            <p class="text-xs text-gray-400 capitalize"><?php echo $role; ?></p>
          </div>
          <a href="change_password.php" class="flex items-center gap-2 px-4 py-2 hover:bg-gray-50">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            Ganti Password
          </a>
          <button @click="confirmLogout" class="w-full flex items-center gap-2 px-4 py-2 hover:bg-red-50 text-red-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Logout
          </button>
        </div>
      </div>

      <!-- Tombol Logout langsung (selalu terlihat) -->
      <button @click="confirmLogout"
              class="flex items-center gap-1.5 bg-red-600 hover:bg-red-500 px-3 py-1.5 rounded-lg text-sm font-semibold transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        <span class="hidden sm:block">Logout</span>
      </button>
    </div>
  </div>
</header>

<!-- ===== MAIN CONTENT ===== -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">

  <!-- ===== STATISTICS CARDS ===== -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="stat-card bg-white rounded-2xl shadow p-5 border-l-4 border-blue-500">
      <div class="flex justify-between items-start">
        <div>
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Total Kendaraan</p>
          <p class="text-3xl font-bold text-gray-800 mt-1">{{ allData.length }}</p>
        </div>
        <div class="bg-blue-100 p-2.5 rounded-xl"><svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h8l2-2zM13 6l2 5h5l-2 2h-5"/></svg></div>
      </div>
    </div>
    <div class="stat-card bg-white rounded-2xl shadow p-5 border-l-4 border-green-500">
      <div class="flex justify-between items-start">
        <div>
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Roda 2</p>
          <p class="text-3xl font-bold text-gray-800 mt-1">{{ statsRoda2 }}</p>
        </div>
        <div class="bg-green-100 p-2.5 rounded-xl"><svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="19" r="2"/><circle cx="19" cy="19" r="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19H3V7l5-3h8l4 4v11h-2M5 19h14"/></svg></div>
      </div>
    </div>
    <div class="stat-card bg-white rounded-2xl shadow p-5 border-l-4 border-purple-500">
      <div class="flex justify-between items-start">
        <div>
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Roda 4+</p>
          <p class="text-3xl font-bold text-gray-800 mt-1">{{ statsRoda4 }}</p>
        </div>
        <div class="bg-purple-100 p-2.5 rounded-xl"><svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h8l2-2zM13 6l2 4h5"/></svg></div>
      </div>
    </div>
    <div class="stat-card bg-white rounded-2xl shadow p-5 border-l-4 border-orange-500">
      <div class="flex justify-between items-start">
        <div>
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Kecamatan</p>
          <p class="text-3xl font-bold text-gray-800 mt-1">{{ statsKecamatan }}</p>
        </div>
        <div class="bg-orange-100 p-2.5 rounded-xl"><svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
      </div>
    </div>
  </div>

  <!-- ===== CHARTS ===== -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" v-if="allData.length > 0">
    <div class="bg-white rounded-2xl shadow p-5">
      <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
        <span class="w-3 h-3 bg-blue-500 rounded-full"></span> Jenis Kendaraan
      </h3>
      <canvas id="chartJenis" height="200"></canvas>
    </div>
    <div class="bg-white rounded-2xl shadow p-5">
      <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
        <span class="w-3 h-3 bg-orange-500 rounded-full"></span> Per Kecamatan
      </h3>
      <canvas id="chartKecamatan" height="200"></canvas>
    </div>
  </div>

  <!-- ===== KENDARAAN PER KECAMATAN TABLE ===== -->
  <div class="bg-white rounded-2xl shadow p-5" v-if="kendaraanPerKec.length > 0">
    <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
      <span class="w-3 h-3 bg-purple-500 rounded-full"></span> Rekapitulasi per Kecamatan
    </h3>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="bg-gray-50 text-gray-500 text-xs uppercase">
            <th class="px-4 py-3 text-left font-semibold">Kecamatan</th>
            <th class="px-4 py-3 text-center font-semibold">Roda 2</th>
            <th class="px-4 py-3 text-center font-semibold">Roda 4+</th>
            <th class="px-4 py-3 text-center font-semibold">Total</th>
            <th class="px-4 py-3 text-left font-semibold">Proporsi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="item in kendaraanPerKec" :key="item.kecamatan" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-800">{{ item.kecamatan }}</td>
            <td class="px-4 py-3 text-center">
              <span class="inline-block bg-green-100 text-green-700 px-2.5 py-0.5 rounded-full text-xs font-semibold">{{ item.roda2 }}</span>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="inline-block bg-purple-100 text-purple-700 px-2.5 py-0.5 rounded-full text-xs font-semibold">{{ item.roda4 }}</span>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="inline-block bg-blue-100 text-blue-700 px-2.5 py-0.5 rounded-full text-xs font-bold">{{ item.total }}</span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <div class="flex-1 bg-gray-200 rounded-full h-2 max-w-[120px]">
                  <div class="bg-blue-500 h-2 rounded-full" :style="{ width: ((item.total / allData.length) * 100).toFixed(0) + '%' }"></div>
                </div>
                <span class="text-xs text-gray-500">{{ ((item.total / allData.length) * 100).toFixed(1) }}%</span>
              </div>
            </td>
          </tr>
        </tbody>
        <tfoot class="bg-blue-50">
          <tr>
            <td class="px-4 py-3 font-bold text-gray-800">TOTAL</td>
            <td class="px-4 py-3 text-center font-bold text-green-700">{{ statsRoda2 }}</td>
            <td class="px-4 py-3 text-center font-bold text-purple-700">{{ statsRoda4 }}</td>
            <td class="px-4 py-3 text-center font-bold text-blue-700">{{ allData.length }}</td>
            <td class="px-4 py-3 text-xs text-gray-400">100%</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- ===== DATA TABLE ===== -->
  <div class="bg-white rounded-2xl shadow">
    <!-- Table Header / Toolbar -->
    <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
      <h3 class="font-bold text-gray-700 flex items-center gap-2">
        <span class="w-3 h-3 bg-blue-500 rounded-full"></span>
        Data Wajib Pajak
        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-semibold ml-1">{{ meta.total }}</span>
      </h3>
      <div class="flex gap-2 w-full sm:w-auto">
        <!-- Search -->
        <div class="relative flex-1 sm:w-64">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input v-model="search" @input="onSearch" type="text" placeholder="Cari nama, NIK, no polisi..."
                 class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
        </div>
        <?php if ($role === 'administrator'): ?>
        <a href="form_wajib_pajak.php"
           class="flex-shrink-0 flex items-center gap-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-xl text-sm font-semibold transition sm:hidden">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Tambah
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="py-16 text-center">
      <svg class="animate-spin w-10 h-10 text-blue-500 mx-auto" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
      </svg>
      <p class="text-gray-500 mt-3 text-sm">Memuat data...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="data.length === 0" class="py-16 text-center">
      <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
      <p class="text-gray-500 font-medium">Tidak ada data ditemukan</p>
      <p class="text-gray-400 text-sm mt-1">{{ search ? 'Coba ubah kata kunci pencarian' : 'Belum ada data wajib pajak' }}</p>
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
          <tr>
            <th class="px-4 py-3 text-left font-semibold w-10">#</th>
            <th class="px-4 py-3 text-left font-semibold">Nama</th>
            <th class="px-4 py-3 text-left font-semibold hidden md:table-cell">NIK</th>
            <th class="px-4 py-3 text-left font-semibold hidden sm:table-cell">No HP</th>
            <th class="px-4 py-3 text-left font-semibold hidden lg:table-cell">Kecamatan</th>
            <th class="px-4 py-3 text-left font-semibold">No Polisi</th>
            <th class="px-4 py-3 text-left font-semibold hidden md:table-cell">Jenis</th>
            <th class="px-4 py-3 text-center font-semibold">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="(item, idx) in data" :key="item.id"
              @click="openModal(item)"
              class="table-row-hover transition-colors">
            <td class="px-4 py-3 text-gray-400 text-xs">{{ (meta.page - 1) * meta.per_page + idx + 1 }}</td>
            <td class="px-4 py-3">
              <p class="font-semibold text-gray-800">{{ item.nama }}</p>
              <p class="text-xs text-gray-400 md:hidden">{{ item.nik }}</p>
            </td>
            <td class="px-4 py-3 text-gray-600 hidden md:table-cell font-mono text-xs">{{ item.nik }}</td>
            <td class="px-4 py-3 text-gray-600 hidden sm:table-cell">{{ item.no_hp }}</td>
            <td class="px-4 py-3 hidden lg:table-cell">
              <span class="bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full text-xs font-medium">{{ item.kecamatan }}</span>
            </td>
            <td class="px-4 py-3 font-mono font-semibold text-gray-800 text-xs">{{ item.no_polisi }}</td>
            <td class="px-4 py-3 hidden md:table-cell">
              <span :class="item.jenis_kendaraan === 'Roda 2' ? 'bg-green-50 text-green-700' : 'bg-purple-50 text-purple-700'"
                    class="px-2 py-0.5 rounded-full text-xs font-medium">{{ item.jenis_kendaraan }}</span>
            </td>
            <td class="px-4 py-3 text-center" @click.stop>
              <div class="flex justify-center items-center gap-1">
                <button @click="openModal(item)"
                        class="p-1.5 bg-gray-100 hover:bg-blue-100 text-gray-500 hover:text-blue-600 rounded-lg transition" title="Lihat Detail">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
                <?php if ($role === 'administrator'): ?>
                <button @click="editItem(item)"
                        class="p-1.5 bg-amber-50 hover:bg-amber-100 text-amber-500 hover:text-amber-700 rounded-lg transition" title="Edit">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button @click="deleteItem(item)"
                        class="p-1.5 bg-red-50 hover:bg-red-100 text-red-400 hover:text-red-600 rounded-lg transition" title="Hapus">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="meta.total_pages > 1" class="px-5 py-4 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-3">
      <p class="text-xs text-gray-400">
        Menampilkan {{ (meta.page - 1) * meta.per_page + 1 }}–{{ Math.min(meta.page * meta.per_page, meta.total) }} dari {{ meta.total }} data
      </p>
      <div class="flex items-center gap-1">
        <button @click="goPage(1)" :disabled="meta.page === 1"
                class="px-2.5 py-1.5 text-xs rounded-lg border disabled:opacity-40 hover:bg-gray-50 transition">«</button>
        <button @click="goPage(meta.page - 1)" :disabled="meta.page === 1"
                class="px-2.5 py-1.5 text-xs rounded-lg border disabled:opacity-40 hover:bg-gray-50 transition">‹</button>
        <template v-for="p in pageRange" :key="p">
          <button @click="goPage(p)"
                  :class="p === meta.page ? 'bg-blue-600 text-white border-blue-600' : 'hover:bg-gray-50'"
                  class="px-3 py-1.5 text-xs rounded-lg border transition">{{ p }}</button>
        </template>
        <button @click="goPage(meta.page + 1)" :disabled="meta.page === meta.total_pages"
                class="px-2.5 py-1.5 text-xs rounded-lg border disabled:opacity-40 hover:bg-gray-50 transition">›</button>
        <button @click="goPage(meta.total_pages)" :disabled="meta.page === meta.total_pages"
                class="px-2.5 py-1.5 text-xs rounded-lg border disabled:opacity-40 hover:bg-gray-50 transition">»</button>
      </div>
      <select v-model.number="perPage" @change="onPerPageChange" class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-blue-500">
        <option value="10">10 / hal</option>
        <option value="25">25 / hal</option>
        <option value="50">50 / hal</option>
        <option value="100">100 / hal</option>
      </select>
    </div>
  </div>

</main>

<!-- ===== MODAL DETAIL ===== -->
<div v-if="showModal" class="fixed inset-0 modal-bg bg-black/50 flex items-center justify-center z-50 p-4" @click.self="showModal = false">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white rounded-t-2xl">
      <h2 class="font-bold text-gray-800 text-lg">Detail Wajib Pajak</h2>
      <button @click="showModal = false" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 text-xl transition">&times;</button>
    </div>
    <div class="p-6 space-y-5">
      <!-- Data Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div v-for="field in detailFields" :key="field.key">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">{{ field.label }}</p>
          <p class="text-gray-800 font-medium">{{ selected[field.key] || '—' }}</p>
        </div>
      </div>
      <!-- Dokumen -->
      <div>
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Dokumen & Foto</p>
        <div class="grid grid-cols-3 gap-3">
          <div v-if="selected.ktp_path" class="text-center">
            <img :src="selected.ktp_path" alt="KTP" class="w-full h-32 object-cover rounded-xl border border-gray-200 cursor-pointer hover:opacity-90 transition"
                 @click="lightbox = selected.ktp_path">
            <p class="text-xs text-gray-400 mt-1">KTP</p>
          </div>
          <div v-if="selected.stnk_path" class="text-center">
            <img :src="selected.stnk_path" alt="STNK" class="w-full h-32 object-cover rounded-xl border border-gray-200 cursor-pointer hover:opacity-90 transition"
                 @click="lightbox = selected.stnk_path">
            <p class="text-xs text-gray-400 mt-1">STNK</p>
          </div>
          <div v-if="selected.foto_kendaraan_path" class="text-center">
            <img :src="selected.foto_kendaraan_path" alt="Kendaraan" class="w-full h-32 object-cover rounded-xl border border-gray-200 cursor-pointer hover:opacity-90 transition"
                 @click="lightbox = selected.foto_kendaraan_path">
            <p class="text-xs text-gray-400 mt-1">Kendaraan</p>
          </div>
          <div v-if="!selected.ktp_path && !selected.stnk_path && !selected.foto_kendaraan_path"
               class="col-span-3 py-8 text-center text-gray-400 text-sm bg-gray-50 rounded-xl">
            Tidak ada dokumen yang diunggah
          </div>
        </div>
      </div>
    </div>
    <?php if ($role === 'administrator'): ?>
    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2">
      <button @click="editItem(selected); showModal = false"
              class="flex items-center gap-1.5 px-4 py-2 bg-amber-100 hover:bg-amber-200 text-amber-700 rounded-xl text-sm font-semibold transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        Edit
      </button>
      <button @click="deleteItem(selected); showModal = false"
              class="flex items-center gap-1.5 px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-xl text-sm font-semibold transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        Hapus
      </button>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ===== LIGHTBOX ===== -->
<div v-if="lightbox" class="fixed inset-0 bg-black/90 flex items-center justify-center z-[200] p-4" @click="lightbox = null">
  <img :src="lightbox" alt="Preview" class="max-w-full max-h-full object-contain rounded-xl shadow-2xl">
</div>

<!-- ===== MODAL EDIT ===== -->
<div v-if="showEditModal" class="fixed inset-0 modal-bg bg-black/50 flex items-center justify-center z-50 p-4" @click.self="showEditModal = false">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white rounded-t-2xl">
      <h2 class="font-bold text-gray-800 text-lg">Edit Data Wajib Pajak</h2>
      <button @click="showEditModal = false" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 text-xl transition">&times;</button>
    </div>
    <div class="p-6 space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div v-for="f in editFields" :key="f.key" :class="f.full ? 'sm:col-span-2' : ''">
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">{{ f.label }}</label>
          <input v-model="editForm[f.key]" :type="f.type || 'text'" :placeholder="f.placeholder || ''"
                 :class="f.key === 'no_polisi' ? 'uppercase' : ''"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Kecamatan</label>
          <select v-model="editForm.kecamatan" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Pilih Kecamatan</option>
            <option v-for="k in kecamatanOptions" :key="k" :value="k">{{ k }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Jenis Kendaraan</label>
          <select v-model="editForm.jenis_kendaraan" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Pilih Jenis</option>
            <option>Roda 2</option>
            <option>Roda 3</option>
            <option>Roda 4</option>
            <option>Roda 6 atau lebih</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Kondisi</label>
          <select v-model="editForm.kondisi" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Pilih Kondisi</option>
            <option>Baik (Masih bisa digunakan)</option>
            <option>Rusak</option>
            <option>Rusak Berat (Bangkai kendaraan)</option>
            <option>Kendaraan Hilang</option>
            <option>Kendaraan tanpa Surat</option>
          </select>
        </div>
      </div>

      <!-- File Uploads -->
      <div>
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Ganti Dokumen (opsional)</p>
        <div class="grid grid-cols-3 gap-3">
          <div class="text-center">
            <label class="block cursor-pointer group">
              <img :src="editForm._ktp_preview || selected.ktp_path || ''"
                   class="w-full h-24 object-cover rounded-xl border-2 border-dashed border-gray-200 group-hover:border-blue-400 transition bg-gray-50"
                   onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Crect fill=%22%23f3f4f6%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%22 y=%2255%22 font-size=%2212%22 fill=%22%239ca3af%22 text-anchor=%22middle%22%3EKTP%3C/text%3E%3C/svg%3E'">
              <input type="file" class="hidden" accept=".jpg,.jpeg,.png,.pdf" @change="e => handleEditFile(e, 'ktp_file', '_ktp_preview')">
            </label>
            <p class="text-xs text-gray-400 mt-1">KTP</p>
          </div>
          <div class="text-center">
            <label class="block cursor-pointer group">
              <img :src="editForm._stnk_preview || selected.stnk_path || ''"
                   class="w-full h-24 object-cover rounded-xl border-2 border-dashed border-gray-200 group-hover:border-blue-400 transition bg-gray-50"
                   onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Crect fill=%22%23f3f4f6%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%22 y=%2255%22 font-size=%2212%22 fill=%22%239ca3af%22 text-anchor=%22middle%22%3ESTNK%3C/text%3E%3C/svg%3E'">
              <input type="file" class="hidden" accept=".jpg,.jpeg,.png,.pdf" @change="e => handleEditFile(e, 'stnk_file', '_stnk_preview')">
            </label>
            <p class="text-xs text-gray-400 mt-1">STNK</p>
          </div>
          <div class="text-center">
            <label class="block cursor-pointer group">
              <img :src="editForm._foto_preview || selected.foto_kendaraan_path || ''"
                   class="w-full h-24 object-cover rounded-xl border-2 border-dashed border-gray-200 group-hover:border-blue-400 transition bg-gray-50"
                   onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Crect fill=%22%23f3f4f6%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%22 y=%2255%22 font-size=%2212%22 fill=%22%239ca3af%22 text-anchor=%22middle%22%3EFoto%3C/text%3E%3C/svg%3E'">
              <input type="file" class="hidden" accept=".jpg,.jpeg,.png" @change="e => handleEditFile(e, 'foto_kendaraan_file', '_foto_preview')">
            </label>
            <p class="text-xs text-gray-400 mt-1">Kendaraan</p>
          </div>
        </div>
        <p class="text-xs text-gray-400 mt-2 text-center">Klik gambar untuk mengganti file</p>
      </div>
    </div>
    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2">
      <button @click="showEditModal = false"
              class="px-4 py-2 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">Batal</button>
      <button @click="saveEdit" :disabled="savingEdit"
              class="flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-xl text-sm font-semibold transition">
        <svg v-if="savingEdit" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
        {{ savingEdit ? 'Menyimpan...' : 'Simpan Perubahan' }}
      </button>
    </div>
  </div>
</div>

<!-- ===== CONFIRM LOGOUT MODAL ===== -->
<div v-if="confirmLogoutModal" class="fixed inset-0 modal-bg bg-black/50 flex items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
    <div class="p-6 text-center">
      <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      </div>
      <h3 class="font-bold text-gray-800 text-lg mb-1">Keluar dari Sistem?</h3>
      <p class="text-gray-400 text-sm mb-6">Sesi Anda akan diakhiri. Anda perlu login kembali untuk mengakses sistem.</p>
      <div class="flex gap-2">
        <button @click="confirmLogoutModal = false"
                class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">Batal</button>
        <a href="logout.php"
           class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-sm font-semibold transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          Ya, Keluar
        </a>
      </div>
    </div>
  </div>
</div>

<!-- ===== CONFIRM DELETE MODAL ===== -->
<div v-if="confirmDelete" class="fixed inset-0 modal-bg bg-black/50 flex items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
    <div class="p-6 text-center">
      <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
      </div>
      <h3 class="font-bold text-gray-800 text-lg mb-1">Hapus Data?</h3>
      <p class="text-gray-500 text-sm mb-1"><strong class="text-gray-700">{{ deleteTarget?.nama }}</strong></p>
      <p class="text-gray-400 text-xs mb-6">Data akan dipindahkan ke Trash dan bisa dipulihkan oleh administrator.</p>
      <div class="flex gap-2">
        <button @click="confirmDelete = false; deleteTarget = null"
                class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">Batal</button>
        <button @click="doDelete" :disabled="deleting"
                class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2.5 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white rounded-xl text-sm font-semibold transition">
          <svg v-if="deleting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
          {{ deleting ? 'Menghapus...' : 'Ya, Hapus' }}
        </button>
      </div>
    </div>
  </div>
</div>

</div><!-- #app -->

<script>
const { createApp } = Vue;
createApp({
  data() {
    return {
      // State
      data: [], allData: [], loading: true,
      meta: { total: 0, page: 1, per_page: 25, total_pages: 1, search: '' },
      perPage: 25, search: '',
      searchTimer: null,
      userMenu: false,
      // Charts
      chartJenis: null, chartKecamatan: null,
      // Modals
      showModal: false, selected: {},
      showEditModal: false, savingEdit: false,
      confirmDelete: false, deleteTarget: null, deleting: false,
      confirmLogoutModal: false,
      lightbox: null,
      // Toasts
      toasts: [], toastId: 0,
      csrfToken: '<?php echo $csrfToken; ?>',
      kecamatanOptions: [],
      // Edit form
      editForm: {},
      // Detail fields
      detailFields: [
        { key: 'nama',            label: 'Nama Lengkap' },
        { key: 'nik',             label: 'NIK' },
        { key: 'no_hp',           label: 'No HP' },
        { key: 'alamat',          label: 'Alamat' },
        { key: 'kecamatan',       label: 'Kecamatan' },
        { key: 'desa',            label: 'Desa' },
        { key: 'no_polisi',       label: 'No Polisi' },
        { key: 'jenis_kendaraan', label: 'Jenis Kendaraan' },
        { key: 'kondisi',         label: 'Kondisi' },
        { key: 'tanggal_input',   label: 'Tanggal Input' },
      ],
      editFields: [
        { key: 'nama',  label: 'Nama Lengkap', full: true },
        { key: 'nik',   label: 'NIK',          placeholder: '16 digit' },
        { key: 'no_hp', label: 'No HP' },
        { key: 'alamat', label: 'Alamat',      full: true },
        { key: 'desa',  label: 'Desa' },
        { key: 'no_polisi', label: 'No Polisi' },
      ],
    };
  },
  computed: {
    statsRoda2() { return this.allData.filter(d => d.jenis_kendaraan === 'Roda 2').length; },
    statsRoda4() { return this.allData.filter(d => !['Roda 2', 'Roda 3'].includes(d.jenis_kendaraan)).length; },
    statsKecamatan() { return new Set(this.allData.map(d => d.kecamatan)).size; },
    kendaraanPerKec() {
      const map = {};
      this.allData.forEach(d => {
        const k = d.kecamatan || 'Tidak Diketahui';
        if (!map[k]) map[k] = { kecamatan: k, roda2: 0, roda4: 0, total: 0 };
        if (d.jenis_kendaraan === 'Roda 2') map[k].roda2++;
        else map[k].roda4++;
        map[k].total++;
      });
      return Object.values(map).sort((a, b) => b.total - a.total);
    },
    pageRange() {
      const p = this.meta.page, t = this.meta.total_pages;
      const d = 2, start = Math.max(1, p - d), end = Math.min(t, p + d);
      return Array.from({ length: end - start + 1 }, (_, i) => start + i);
    },
  },
  methods: {
    // Toast
    toast(message, type = 'success') {
      const id = ++this.toastId;
      this.toasts.push({ id, message, type });
      setTimeout(() => this.removeToast(id), 4500);
    },
    removeToast(id) { this.toasts = this.toasts.filter(t => t.id !== id); },

    // Load paginated data
    async loadData(page = 1) {
      this.loading = true;
      const params = new URLSearchParams({ page, per_page: this.perPage, search: this.search });
      try {
        const res  = await fetch('api/tampil_data.php?' + params, { credentials: 'same-origin' });
        const json = await res.json();
        if (json.status === 'success') {
          this.data = json.data;
          this.meta = json.meta;
        } else {
          this.toast(json.message || 'Gagal memuat data', 'error');
        }
      } catch (e) { this.toast('Gagal terhubung ke server', 'error'); }
      finally    { this.loading = false; }
    },

    // Load all data (for charts/stats - no pagination)
    async loadAllData() {
      try {
        const res  = await fetch('api/tampil_data.php?page=1&per_page=9999', { credentials: 'same-origin' });
        const json = await res.json();
        if (json.status === 'success') {
          this.allData = json.data;
          this.$nextTick(() => this.createCharts());
        }
      } catch (e) { /* non-fatal */ }
    },

    onSearch() {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => this.loadData(1), 400);
    },

    goPage(p) {
      if (p < 1 || p > this.meta.total_pages) return;
      this.loadData(p);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    onPerPageChange() { this.loadData(1); },

    // Charts
    createCharts() {
      if (this.chartJenis) this.chartJenis.destroy();
      if (this.chartKecamatan) this.chartKecamatan.destroy();

      // Jenis
      const jenisMap = {};
      this.allData.forEach(d => { const k = d.jenis_kendaraan || 'Lainnya'; jenisMap[k] = (jenisMap[k] || 0) + 1; });
      const colors = ['#10B981','#8B5CF6','#F59E0B','#EF4444','#3B82F6','#EC4899'];
      this.chartJenis = new Chart(document.getElementById('chartJenis').getContext('2d'), {
        type: 'bar',
        data: {
          labels: Object.keys(jenisMap),
          datasets: [{ label: 'Kendaraan', data: Object.values(jenisMap), backgroundColor: colors, borderRadius: 8, borderSkipped: false }]
        },
        options: {
          indexAxis: 'y', responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: { x: { beginAtZero: true, grid: { color: '#f3f4f6' } }, y: { grid: { display: false } } }
        }
      });
      document.getElementById('chartJenis').parentElement.style.height = '180px';

      // Kecamatan
      const kecMap = {};
      this.allData.forEach(d => { const k = d.kecamatan || 'Lainnya'; kecMap[k] = (kecMap[k] || 0) + 1; });
      const sorted = Object.entries(kecMap).sort((a,b) => b[1]-a[1]);
      this.chartKecamatan = new Chart(document.getElementById('chartKecamatan').getContext('2d'), {
        type: 'bar',
        data: {
          labels: sorted.map(s => s[0]),
          datasets: [{ label: 'Kendaraan', data: sorted.map(s => s[1]), backgroundColor: '#3B82F6', borderRadius: 8, hoverBackgroundColor: '#2563EB' }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            y: { beginAtZero: true, grid: { color: '#f3f4f6' }, ticks: { stepSize: 1 } },
            x: { grid: { display: false }, ticks: { maxRotation: 45, minRotation: 30, font: { size: 10 } } }
          }
        }
      });
      document.getElementById('chartKecamatan').parentElement.style.height = '180px';
    },

    // Modal Detail
    openModal(item) { this.selected = item; this.showModal = true; },

    // Edit
    editItem(item) {
      this.editForm = {
        id: item.id, nama: item.nama, nik: item.nik, no_hp: item.no_hp,
        alamat: item.alamat, kecamatan: item.kecamatan, desa: item.desa,
        no_polisi: item.no_polisi, jenis_kendaraan: item.jenis_kendaraan, kondisi: item.kondisi,
        ktp_file: null, stnk_file: null, foto_kendaraan_file: null,
        _ktp_preview: null, _stnk_preview: null, _foto_preview: null
      };
      this.selected = item;
      this.showEditModal = true;
    },

    handleEditFile(e, field, previewField) {
      const file = e.target.files[0];
      if (!file) return;
      this.editForm[field] = file;
      if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = ev => { this.editForm[previewField] = ev.target.result; };
        reader.readAsDataURL(file);
      }
    },

    async saveEdit() {
      this.savingEdit = true;
      const fd = new FormData();
      fd.append('csrf_token', this.csrfToken);
      for (const key in this.editForm) {
        if (key.startsWith('_')) continue;
        if ((key === 'ktp_file' || key === 'stnk_file' || key === 'foto_kendaraan_file') && !this.editForm[key]) continue;
        fd.append(key, this.editForm[key]);
      }
      try {
        const res  = await fetch('api/update_data.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const json = await res.json();
        if (json.status === 'success') {
          this.toast('Data berhasil diupdate!');
          this.showEditModal = false;
          this.loadData(this.meta.page);
          this.loadAllData();
        } else {
          this.toast(json.message || 'Gagal update data', 'error');
        }
      } catch (e) { this.toast('Gagal terhubung ke server', 'error'); }
      finally    { this.savingEdit = false; }
    },

    // Delete
    deleteItem(item) { this.deleteTarget = item; this.confirmDelete = true; },

    async doDelete() {
      this.deleting = true;
      try {
        const res  = await fetch('api/soft_delete.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ id: this.deleteTarget.id, csrf_token: this.csrfToken })
        });
        const json = await res.json();
        if (json.status === 'success') {
          this.toast('Data dipindahkan ke Trash.', 'warning');
          this.confirmDelete = false; this.deleteTarget = null;
          this.loadData(this.meta.page);
          this.loadAllData();
        } else {
          this.toast(json.message || 'Gagal menghapus data', 'error');
        }
      } catch (e) { this.toast('Gagal terhubung ke server', 'error'); }
      finally    { this.deleting = false; }
    },

    confirmLogout() {
      this.userMenu = false;
      this.confirmLogoutModal = true;
    },
  },
  async mounted() {
    await this.loadData(1);
    this.loadAllData();
    try {
      const r = await fetch('api/get_kecamatan.php', { credentials: 'same-origin' });
      const j = await r.json();
      if (j.status === 'success') this.kecamatanOptions = j.data.map(k => k.nama_kecamatan);
    } catch(e) {}
  }
}).mount('#app');
</script>
</body>
</html>
