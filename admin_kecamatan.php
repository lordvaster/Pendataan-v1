<?php
// Author: Zeday @join.co.id
require_once 'includes/config.php';
require_once 'includes/security.php';
require_once 'includes/db_connect.php';
require_once 'includes/usersession.php';

if ($role !== 'administrator') {
    header('Location: maindashboard.php');
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
  <title>Manajemen Kecamatan — Sistem Pajak Kendaraan</title>
  <script src="assets/js/tailwind.min.js"></script>
  <script src="assets/js/vue.global.prod.js"></script>
  <style>[v-cloak]{display:none}</style>
</head>
<body class="bg-gray-100 min-h-screen">

<div id="app" v-cloak>
<!-- Toast -->
<div class="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none">
  <div v-for="t in toasts" :key="t.id"
       :class="t.type==='success'?'bg-green-600':'bg-red-600'"
       class="pointer-events-auto flex items-start gap-2 text-white rounded-xl shadow-xl px-4 py-3">
    <span class="flex-shrink-0">{{ t.type==='success'?'✅':'❌' }}</span>
    <p class="text-sm font-medium flex-1">{{ t.message }}</p>
    <button @click="removeToast(t.id)" class="opacity-70 hover:opacity-100 text-lg leading-none">&times;</button>
  </div>
</div>


<!-- Header -->
<header class="bg-gradient-to-r from-blue-800 to-blue-600 text-white shadow-lg sticky top-0 z-40">
  <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <a href="maindashboard.php" class="flex items-center gap-1 text-blue-200 hover:text-white transition text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Dashboard
      </a>
      <span class="text-blue-300">/</span>
      <h1 class="font-bold text-base">Manajemen Kecamatan</h1>
    </div>
    <div class="flex items-center gap-3">
      <span class="text-sm text-blue-200 hidden sm:block">👤 <?php echo escapeHtml($username); ?></span>
      <button @click="openAdd"
              class="flex items-center gap-1.5 bg-green-500 hover:bg-green-400 px-3 py-1.5 rounded-lg text-sm font-bold transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah Kecamatan
      </button>
      <a href="logout.php" class="flex items-center gap-1.5 bg-red-600 hover:bg-red-500 px-3 py-1.5 rounded-lg text-sm font-semibold transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        <span class="hidden sm:block">Logout</span>
      </a>
    </div>
  </div>
</header>

<main class="max-w-5xl mx-auto px-4 sm:px-6 py-6 space-y-6">

  <!-- Table -->
  <div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
      <h3 class="font-bold text-gray-700 flex items-center gap-2">
        <span class="w-3 h-3 bg-orange-500 rounded-full"></span>
        Daftar Kecamatan
        <span class="text-xs bg-orange-50 text-orange-600 px-2 py-0.5 rounded-full font-semibold ml-1">{{ kecamatanList.length }}</span>
      </h3>
      <input v-model="search" type="text" placeholder="Cari nama kecamatan..."
             class="text-sm border border-gray-200 rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-blue-500 w-full sm:w-56">
    </div>

    <!-- Loading -->
    <div v-if="loading" class="py-16 text-center">
      <svg class="animate-spin w-8 h-8 text-blue-500 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="py-12 text-center">
      <p class="text-red-500 font-medium">{{ error }}</p>
    </div>

    <!-- Table Data -->
    <div v-else class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
          <tr>
            <th class="px-4 py-3 text-left font-semibold">#</th>
            <th class="px-4 py-3 text-left font-semibold">Nama Kecamatan</th>
            <th class="px-4 py-3 text-left font-semibold hidden md:table-cell">Kode</th>
            <th class="px-4 py-3 text-right font-semibold hidden lg:table-cell">Penduduk</th>
            <th class="px-4 py-3 text-right font-semibold hidden lg:table-cell">Luas (km²)</th>
            <th class="px-4 py-3 text-center font-semibold">Status</th>
            <th class="px-4 py-3 text-center font-semibold">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="(k, idx) in filteredList" :key="k.id" class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-gray-400 text-xs">{{ idx + 1 }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center flex-shrink-0">
                  <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                </div>
                <span class="font-semibold text-gray-800">{{ k.nama_kecamatan }}</span>
              </div>
            </td>
            <td class="px-4 py-3 text-gray-500 font-mono text-xs hidden md:table-cell">{{ k.kode_kecamatan || '—' }}</td>
            <td class="px-4 py-3 text-right text-gray-600 hidden lg:table-cell">{{ k.jumlah_penduduk ? k.jumlah_penduduk.toLocaleString('id-ID') : '—' }}</td>
            <td class="px-4 py-3 text-right text-gray-600 hidden lg:table-cell">{{ k.luas_wilayah || '—' }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="k.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                    class="px-2.5 py-0.5 rounded-full text-xs font-semibold">
                {{ k.status === 'active' ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <div class="flex justify-center gap-1.5">
                <button @click="editKecamatan(k)"
                        class="p-1.5 bg-amber-50 hover:bg-amber-100 text-amber-600 rounded-lg transition" title="Edit">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button @click="confirmDelete(k)"
                        class="p-1.5 bg-red-50 hover:bg-red-100 text-red-500 rounded-lg transition" title="Hapus">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="filteredList.length === 0">
            <td colspan="7" class="px-4 py-12 text-center text-gray-400">Tidak ada kecamatan ditemukan</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- Modal Tambah/Edit Kecamatan -->
<div v-if="showModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4" @click.self="closeModal">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
    <div class="flex items-center justify-between px-6 py-4 border-b">
      <h3 class="font-bold text-gray-800 text-lg">{{ isEdit ? 'Edit Kecamatan' : 'Tambah Kecamatan Baru' }}</h3>
      <button @click="closeModal" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 text-xl">&times;</button>
    </div>
    <form @submit.prevent="save" class="p-6 space-y-4">
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Nama Kecamatan <span class="text-red-500">*</span></label>
        <input v-model="form.nama_kecamatan" type="text" required placeholder="Masukkan nama kecamatan"
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Kode Kecamatan</label>
        <input v-model="form.kode_kecamatan" type="text" placeholder="Contoh: 6204"
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Jumlah Penduduk</label>
          <input v-model.number="form.jumlah_penduduk" type="number" min="0"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Luas (km²)</label>
          <input v-model.number="form.luas_wilayah" type="number" step="0.01" min="0"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Status</label>
        <div class="grid grid-cols-2 gap-3">
          <label :class="form.status === 'active' ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-gray-300'"
                 class="flex items-center gap-2 p-3 border-2 rounded-xl cursor-pointer transition">
            <input type="radio" v-model="form.status" value="active" class="hidden">
            <span class="w-3 h-3 rounded-full" :class="form.status === 'active' ? 'bg-green-500' : 'bg-gray-300'"></span>
            <span class="text-sm font-semibold text-gray-700">Aktif</span>
          </label>
          <label :class="form.status === 'inactive' ? 'border-gray-500 bg-gray-50' : 'border-gray-200 hover:border-gray-300'"
                 class="flex items-center gap-2 p-3 border-2 rounded-xl cursor-pointer transition">
            <input type="radio" v-model="form.status" value="inactive" class="hidden">
            <span class="w-3 h-3 rounded-full" :class="form.status === 'inactive' ? 'bg-gray-500' : 'bg-gray-300'"></span>
            <span class="text-sm font-semibold text-gray-700">Nonaktif</span>
          </label>
        </div>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" @click="closeModal" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">Batal</button>
        <button type="submit" :disabled="saving"
                class="flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-xl text-sm font-semibold transition">
          <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
          {{ saving ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Tambah Kecamatan') }}
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div v-if="deleteTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
    <div class="p-6 text-center">
      <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
      </div>
      <h3 class="font-bold text-gray-800 text-lg mb-1">Hapus Kecamatan?</h3>
      <p class="text-gray-500 text-sm mb-6"><strong>{{ deleteTarget.nama_kecamatan }}</strong> akan dihapus permanen.</p>
      <div class="flex gap-2">
        <button @click="deleteTarget = null" class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">Batal</button>
        <button @click="doDelete" :disabled="deleting" class="flex-1 flex items-center justify-center gap-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white rounded-xl text-sm font-semibold transition">
          <svg v-if="deleting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
          Hapus
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
      kecamatanList: [], loading: true, error: null, search: '',
      toasts: [], toastId: 0,
      showModal: false, isEdit: false, saving: false,
      deleteTarget: null, deleting: false,
      csrfToken: '<?php echo $csrfToken; ?>',
      form: { id: null, nama_kecamatan: '', kode_kecamatan: '', jumlah_penduduk: 0, luas_wilayah: null, status: 'active' },
    };
  },
  computed: {
    filteredList() {
      const s = this.search.toLowerCase();
      return s ? this.kecamatanList.filter(k => k.nama_kecamatan.toLowerCase().includes(s)) : this.kecamatanList;
    }
  },
  methods: {
    toast(msg, type='success') {
      const id = ++this.toastId;
      this.toasts.push({ id, message: msg, type });
      setTimeout(() => this.removeToast(id), 4000);
    },
    removeToast(id) { this.toasts = this.toasts.filter(t => t.id !== id); },

    async loadData() {
      this.loading = true; this.error = null;
      try {
        const res  = await fetch('api/get_kecamatan.php', { credentials: 'same-origin' });
        const json = await res.json();
        if (json.status === 'success') this.kecamatanList = json.data;
        else this.error = json.message;
      } catch(e) { this.error = 'Gagal memuat data kecamatan'; }
      finally { this.loading = false; }
    },

    openAdd() {
      this.form = { id: null, nama_kecamatan:'', kode_kecamatan:'', jumlah_penduduk:0, luas_wilayah:null, status:'active' };
      this.isEdit = false; this.showModal = true;
    },

    editKecamatan(k) {
      this.form = { id: k.id, nama_kecamatan: k.nama_kecamatan, kode_kecamatan: k.kode_kecamatan || '', jumlah_penduduk: k.jumlah_penduduk || 0, luas_wilayah: k.luas_wilayah || null, status: k.status };
      this.isEdit = true; this.showModal = true;
    },

    closeModal() { this.showModal = false; },

    async save() {
      this.saving = true;
      const url = this.isEdit ? 'api/update_kecamatan.php' : 'api/add_kecamatan.php';
      try {
        const res  = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body: JSON.stringify({ ...this.form, csrf_token: this.csrfToken }) });
        const json = await res.json();
        if (json.status === 'success') {
          this.toast(json.message || (this.isEdit ? 'Kecamatan diupdate!' : 'Kecamatan ditambahkan!'));
          this.closeModal();
          this.loadData();
        } else { this.toast(json.message || 'Gagal menyimpan', 'error'); }
      } catch(e) { this.toast('Terjadi kesalahan', 'error'); }
      finally { this.saving = false; }
    },

    confirmDelete(k) { this.deleteTarget = k; },

    async doDelete() {
      this.deleting = true;
      try {
        const res  = await fetch('api/delete_kecamatan.php', { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body: JSON.stringify({ id: this.deleteTarget.id, csrf_token: this.csrfToken }) });
        const json = await res.json();
        if (json.status === 'success') {
          this.toast('Kecamatan berhasil dihapus');
          this.deleteTarget = null;
          this.loadData();
        } else { this.toast(json.message || 'Gagal menghapus', 'error'); }
      } catch(e) { this.toast('Terjadi kesalahan', 'error'); }
      finally { this.deleting = false; }
    },
  },
  mounted() { this.loadData(); }
}).mount('#app');
</script>
</body>
</html>
