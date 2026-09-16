<?php
// Author: Zeday @join.co.id
require_once 'includes/config.php';
require_once 'includes/security.php';
require_once 'includes/db_connect.php';
require_once 'includes/usersession.php';

if ($role !== 'administrator') {
    header("Location: maindashboard.php");
    exit;
}
$csrfToken = generateCSRFToken();
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trash — Sistem Pajak Kendaraan</title>
  <script src="assets/js/tailwind.min.js"></script>
  <script src="assets/js/vue.global.prod.js"></script>
  <style>[v-cloak]{display:none}</style>
</head>
<body class="bg-gray-100 min-h-screen">

<div id="app" v-cloak>
<!-- Toast -->
<div class="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none">
  <div v-for="t in toasts" :key="t.id"
       :class="t.type==='success'?'bg-green-600':t.type==='warning'?'bg-amber-500':'bg-red-600'"
       class="pointer-events-auto flex items-start gap-2 text-white rounded-xl shadow-xl px-4 py-3">
    <span class="flex-shrink-0">{{ t.type==='success'?'✅':t.type==='warning'?'⚠️':'❌' }}</span>
    <p class="text-sm font-medium flex-1">{{ t.message }}</p>
    <button @click="removeToast(t.id)" class="opacity-70 hover:opacity-100 text-lg leading-none">&times;</button>
  </div>
</div>


<!-- Header -->
<header class="bg-gradient-to-r from-blue-800 to-blue-600 text-white shadow-lg sticky top-0 z-40">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <a href="maindashboard.php" class="flex items-center gap-1 text-blue-200 hover:text-white transition text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Dashboard
      </a>
      <span class="text-blue-300">/</span>
      <h1 class="font-bold text-base">Trash</h1>
    </div>
    <div class="flex items-center gap-2">
      <span class="text-sm text-blue-200 hidden sm:block">👤 <?php echo escapeHtml($username); ?></span>
      <a href="logout.php" class="flex items-center gap-1.5 bg-red-600 hover:bg-red-500 px-3 py-1.5 rounded-lg text-sm font-semibold transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        <span class="hidden sm:block">Logout</span>
      </a>
    </div>
  </div>
</header>

<main class="max-w-6xl mx-auto px-4 sm:px-6 py-6 space-y-6">

  <!-- Stats Cards -->
  <div class="grid grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl shadow p-4 text-center border-t-4 border-gray-400">
      <p class="text-2xl font-bold text-gray-800">{{ statistics.total_items }}</p>
      <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mt-1">Total di Trash</p>
    </div>
    <div class="bg-white rounded-2xl shadow p-4 text-center border-t-4 border-amber-400">
      <p class="text-2xl font-bold text-amber-600">{{ statistics.warning_items }}</p>
      <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mt-1">Hampir Expired</p>
    </div>
    <div class="bg-white rounded-2xl shadow p-4 text-center border-t-4 border-red-500">
      <p class="text-2xl font-bold text-red-600">{{ statistics.expired_items }}</p>
      <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mt-1">Expired (30+ hari)</p>
    </div>
  </div>

  <!-- Info Banner -->
  <div class="bg-blue-50 border border-blue-200 rounded-2xl px-5 py-4 flex items-start gap-3">
    <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
    <p class="text-sm text-blue-700">
      Data yang dihapus akan disimpan di sini selama <strong>{{ statistics.retention_days }}</strong> hari sebelum dihapus permanen.
      Anda dapat memulihkan data sebelum masa retensi habis.
    </p>
  </div>

  <!-- Table Card -->
  <div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
      <h3 class="font-bold text-gray-700 flex items-center gap-2">
        <span class="w-3 h-3 bg-red-400 rounded-full"></span>
        Item di Trash
        <span class="text-xs bg-red-50 text-red-600 px-2 py-0.5 rounded-full font-semibold ml-1">{{ data.length }}</span>
      </h3>
      <input v-model="search" type="text" placeholder="Cari nama, NIK, no polisi..."
             class="text-sm border border-gray-200 rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-blue-500 w-full sm:w-56">
    </div>

    <!-- Loading -->
    <div v-if="loading" class="py-16 text-center">
      <svg class="animate-spin w-8 h-8 text-blue-500 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
    </div>

    <!-- Empty -->
    <div v-else-if="data.length === 0" class="py-16 text-center">
      <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
        <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
      </div>
      <p class="text-gray-500 font-medium">Trash kosong</p>
      <p class="text-gray-400 text-sm mt-1">Tidak ada item yang dihapus</p>
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
          <tr>
            <th class="px-4 py-3 text-left font-semibold">Nama / NIK</th>
            <th class="px-4 py-3 text-left font-semibold hidden sm:table-cell">Kecamatan</th>
            <th class="px-4 py-3 text-left font-semibold">No Polisi</th>
            <th class="px-4 py-3 text-left font-semibold hidden md:table-cell">Dihapus</th>
            <th class="px-4 py-3 text-center font-semibold">Usia</th>
            <th class="px-4 py-3 text-center font-semibold">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="item in filteredData" :key="item.id"
              :class="item.trash_status === 'expired' ? 'bg-red-50' : item.trash_status === 'warning' ? 'bg-amber-50' : ''"
              class="hover:opacity-90 transition-opacity">
            <td class="px-4 py-3">
              <p class="font-semibold text-gray-800">{{ item.nama }}</p>
              <p class="text-xs text-gray-400 font-mono">{{ item.nik }}</p>
            </td>
            <td class="px-4 py-3 text-gray-600 hidden sm:table-cell">{{ item.kecamatan }}</td>
            <td class="px-4 py-3 font-mono font-bold text-gray-700 text-xs">{{ item.no_polisi }}</td>
            <td class="px-4 py-3 hidden md:table-cell">
              <p class="text-xs text-gray-600">{{ item.deleted_by_username || 'Unknown' }}</p>
              <p class="text-xs text-gray-400">{{ formatDate(item.deleted_at) }}</p>
            </td>
            <td class="px-4 py-3 text-center">
              <span :class="item.days_in_trash >= 30 ? 'bg-red-100 text-red-700' : item.days_in_trash >= 25 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600'"
                    class="px-2 py-0.5 rounded-full text-xs font-bold">
                {{ item.days_in_trash }}h
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <div class="flex justify-center gap-1.5">
                <button @click="restoreItem(item)"
                        class="flex items-center gap-1 px-2.5 py-1.5 bg-green-100 hover:bg-green-200 text-green-700 rounded-lg text-xs font-semibold transition">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                  Pulihkan
                </button>
                <button @click="confirmPermDelete(item)"
                        class="flex items-center gap-1 px-2.5 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg text-xs font-semibold transition">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  Hapus
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- Modal Konfirmasi Hapus Permanen -->
<div v-if="deleteConfirm" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
    <div class="p-6 text-center">
      <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      </div>
      <h3 class="font-bold text-gray-800 text-lg mb-1">Hapus Permanen?</h3>
      <p class="text-gray-500 text-sm mb-1"><strong>{{ deleteTarget?.nama }}</strong></p>
      <p class="text-red-600 text-xs mb-6">Data akan dihapus PERMANEN dan tidak bisa dipulihkan!</p>
      <div class="flex gap-2">
        <button @click="deleteConfirm = false" class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">Batal</button>
        <button @click="doPermDelete" :disabled="deleting" class="flex-1 flex items-center justify-center gap-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white rounded-xl text-sm font-semibold transition">
          <svg v-if="deleting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
          Hapus Permanen
        </button>
      </div>
    </div>
  </div>
</div>

</div>

<script>
const { createApp } = Vue;
createApp({
  data() {
    return {
      data: [], statistics: { total_items:0, expired_items:0, warning_items:0, retention_days:30 },
      loading: true, search: '',
      toasts: [], toastId: 0,
      deleteConfirm: false, deleteTarget: null, deleting: false,
      csrfToken: '<?php echo $csrfToken; ?>',
    };
  },
  computed: {
    filteredData() {
      const s = this.search.toLowerCase();
      return s ? this.data.filter(d =>
        d.nama.toLowerCase().includes(s) || d.nik.toLowerCase().includes(s) ||
        d.no_polisi.toLowerCase().includes(s) || d.kecamatan.toLowerCase().includes(s)
      ) : this.data;
    }
  },
  methods: {
    toast(msg, type='success') {
      const id = ++this.toastId;
      this.toasts.push({ id, message: msg, type });
      setTimeout(() => this.removeToast(id), 4000);
    },
    removeToast(id) { this.toasts = this.toasts.filter(t => t.id !== id); },

    async loadTrash() {
      this.loading = true;
      try {
        const res  = await fetch('api/get_trash.php', { credentials: 'same-origin' });
        const json = await res.json();
        if (json.status === 'success') { this.data = json.data; this.statistics = json.statistics; }
        else this.toast(json.message || 'Gagal memuat trash', 'error');
      } catch(e) { this.toast('Gagal terhubung ke server', 'error'); }
      finally { this.loading = false; }
    },

    async restoreItem(item) {
      try {
        const res  = await fetch('api/restore.php', { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body: JSON.stringify({ id: item.id, csrf_token: this.csrfToken }) });
        const json = await res.json();
        if (json.status === 'success') { this.toast('Data berhasil dipulihkan!'); this.loadTrash(); }
        else this.toast(json.message || 'Gagal memulihkan data', 'error');
      } catch(e) { this.toast('Terjadi kesalahan', 'error'); }
    },

    confirmPermDelete(item) { this.deleteTarget = item; this.deleteConfirm = true; },

    async doPermDelete() {
      this.deleting = true;
      try {
        const res  = await fetch('api/permanent_delete.php', { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body: JSON.stringify({ id: this.deleteTarget.id, csrf_token: this.csrfToken }) });
        const json = await res.json();
        if (json.status === 'success') {
          this.toast('Data dihapus permanen.', 'warning');
          this.deleteConfirm = false; this.deleteTarget = null;
          this.loadTrash();
        } else this.toast(json.message || 'Gagal menghapus', 'error');
      } catch(e) { this.toast('Terjadi kesalahan', 'error'); }
      finally { this.deleting = false; }
    },

    formatDate(d) {
      if (!d) return '—';
      return new Date(d).toLocaleDateString('id-ID', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
    },
  },
  mounted() { this.loadTrash(); }
}).mount('#app');
</script>
</body>
</html>
