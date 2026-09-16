<?php
// Author: Zeday @join.co.id
require_once 'includes/config.php';
require_once 'includes/security.php';
require_once 'includes/db_connect.php';
require_once 'includes/usersession.php';

if ($role !== 'administrator') {
    header("Location: maindashboard.php");
    exit();
}

$csrfToken = generateCSRFToken();
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola User — Sistem Pajak Kendaraan</title>
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
      <h1 class="font-bold text-base">Kelola User</h1>
    </div>
    <div class="flex items-center gap-3">
      <span class="text-sm text-blue-200 hidden sm:block">👤 <?php echo escapeHtml($username); ?></span>
      <button @click="showAddModal = true"
              class="flex items-center gap-1.5 bg-green-500 hover:bg-green-400 px-3 py-1.5 rounded-lg text-sm font-bold transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah User
      </button>
      <a href="logout.php" class="flex items-center gap-1.5 bg-red-600 hover:bg-red-500 px-3 py-1.5 rounded-lg text-sm font-semibold transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        <span class="hidden sm:block">Logout</span>
      </a>
    </div>
  </div>
</header>

<main class="max-w-5xl mx-auto px-4 sm:px-6 py-6">

  <!-- Stats -->
  <div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow p-4 text-center border-t-4 border-blue-500">
      <p class="text-2xl font-bold text-gray-800">{{ users.length }}</p>
      <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mt-1">Total User</p>
    </div>
    <div class="bg-white rounded-2xl shadow p-4 text-center border-t-4 border-purple-500">
      <p class="text-2xl font-bold text-gray-800">{{ users.filter(u => u.role === 'administrator').length }}</p>
      <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mt-1">Administrator</p>
    </div>
    <div class="bg-white rounded-2xl shadow p-4 text-center border-t-4 border-green-500">
      <p class="text-2xl font-bold text-gray-800">{{ users.filter(u => u.role === 'view_only').length }}</p>
      <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mt-1">View Only</p>
    </div>
  </div>

  <!-- Table -->
  <div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
      <h3 class="font-bold text-gray-700 flex items-center gap-2">
        <span class="w-3 h-3 bg-purple-500 rounded-full"></span> Daftar User
      </h3>
      <input v-model="search" type="text" placeholder="Cari username..."
             class="text-sm border border-gray-200 rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-blue-500 w-48">
    </div>

    <!-- Loading -->
    <div v-if="loading" class="py-16 text-center">
      <svg class="animate-spin w-8 h-8 text-blue-500 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
    </div>

    <div v-else class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
          <tr>
            <th class="px-4 py-3 text-left font-semibold">Username</th>
            <th class="px-4 py-3 text-left font-semibold">Role</th>
            <th class="px-4 py-3 text-left font-semibold hidden sm:table-cell">Dibuat</th>
            <th class="px-4 py-3 text-center font-semibold">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0"
                     :class="user.role === 'administrator' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'">
                  {{ user.username.charAt(0).toUpperCase() }}
                </div>
                <div>
                  <p class="font-semibold text-gray-800">{{ user.username }}</p>
                  <p class="text-xs text-gray-400">#{{ user.id }}</p>
                </div>
              </div>
            </td>
            <td class="px-4 py-3">
              <span :class="user.role === 'administrator' ? 'bg-purple-100 text-purple-700' : 'bg-green-100 text-green-700'"
                    class="px-2.5 py-0.5 rounded-full text-xs font-semibold">
                {{ user.role === 'administrator' ? '👑 Administrator' : '👁️ View Only' }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-500 text-xs hidden sm:table-cell">{{ formatDate(user.created_at) }}</td>
            <td class="px-4 py-3 text-center">
              <div class="flex justify-center gap-1.5">
                <button @click="editUser(user)"
                        class="p-1.5 bg-amber-50 hover:bg-amber-100 text-amber-600 rounded-lg transition" title="Edit">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button v-if="user.id !== currentUserId" @click="confirmDeleteUser(user)"
                        class="p-1.5 bg-red-50 hover:bg-red-100 text-red-500 rounded-lg transition" title="Hapus">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
                <span v-else class="px-2 py-1.5 text-xs text-gray-300 font-medium">(Anda)</span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- Modal Tambah User -->
<div v-if="showAddModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4" @click.self="showAddModal = false">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
    <div class="flex items-center justify-between px-6 py-4 border-b">
      <h3 class="font-bold text-gray-800 text-lg">Tambah User Baru</h3>
      <button @click="showAddModal = false" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 text-xl">&times;</button>
    </div>
    <div class="p-6 space-y-4">
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Username</label>
        <input v-model="newUser.username" type="text" placeholder="Masukkan username"
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Password</label>
        <input v-model="newUser.password" type="password" placeholder="Min. 8 karakter"
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
        <div class="mt-2 h-1.5 bg-gray-200 rounded-full overflow-hidden">
          <div :class="passStrengthColor" class="h-full rounded-full transition-all" :style="{ width: passStrengthPct + '%' }"></div>
        </div>
        <p class="text-xs mt-1" :class="passStrengthColor.replace('bg-', 'text-')">{{ passStrengthLabel }}</p>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Konfirmasi Password</label>
        <input v-model="newUser.password_confirm" type="password" placeholder="Ulangi password"
               :class="newUser.password_confirm && newUser.password !== newUser.password_confirm ? 'border-red-400' : 'border-gray-200'"
               class="w-full border rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
        <p v-if="newUser.password_confirm && newUser.password !== newUser.password_confirm" class="text-red-500 text-xs mt-1">Password tidak cocok</p>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Role</label>
        <div class="grid grid-cols-2 gap-3">
          <label :class="newUser.role === 'view_only' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                 class="flex flex-col items-center gap-2 p-3 border-2 rounded-xl cursor-pointer transition text-center">
            <input type="radio" v-model="newUser.role" value="view_only" class="hidden">
            <span class="text-2xl">👁️</span>
            <div><p class="text-xs font-bold text-gray-700">View Only</p><p class="text-xs text-gray-400">Hanya lihat data</p></div>
          </label>
          <label :class="newUser.role === 'administrator' ? 'border-purple-500 bg-purple-50' : 'border-gray-200 hover:border-gray-300'"
                 class="flex flex-col items-center gap-2 p-3 border-2 rounded-xl cursor-pointer transition text-center">
            <input type="radio" v-model="newUser.role" value="administrator" class="hidden">
            <span class="text-2xl">👑</span>
            <div><p class="text-xs font-bold text-gray-700">Administrator</p><p class="text-xs text-gray-400">Akses penuh</p></div>
          </label>
        </div>
      </div>
    </div>
    <div class="px-6 py-4 border-t flex justify-end gap-2">
      <button @click="showAddModal = false" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">Batal</button>
      <button @click="addUser" :disabled="savingAdd" class="flex items-center gap-1.5 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white rounded-xl text-sm font-semibold transition">
        <svg v-if="savingAdd" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
        Simpan User
      </button>
    </div>
  </div>
</div>

<!-- Modal Edit User -->
<div v-if="showEditModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4" @click.self="showEditModal = false">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
    <div class="flex items-center justify-between px-6 py-4 border-b">
      <h3 class="font-bold text-gray-800 text-lg">Edit User</h3>
      <button @click="showEditModal = false" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 text-xl">&times;</button>
    </div>
    <div class="p-6 space-y-4">
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Username</label>
        <input :value="editingUser.username" readonly class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-500 cursor-not-allowed">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Password Baru <span class="text-gray-400 font-normal">(kosongkan jika tidak diubah)</span></label>
        <input v-model="editingUser.password" type="password" placeholder="Isi jika ingin mengubah password"
               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Role</label>
        <div class="grid grid-cols-2 gap-3">
          <label :class="editingUser.role === 'view_only' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                 class="flex flex-col items-center gap-2 p-3 border-2 rounded-xl cursor-pointer transition text-center">
            <input type="radio" v-model="editingUser.role" value="view_only" class="hidden">
            <span class="text-2xl">👁️</span>
            <div><p class="text-xs font-bold text-gray-700">View Only</p></div>
          </label>
          <label :class="editingUser.role === 'administrator' ? 'border-purple-500 bg-purple-50' : 'border-gray-200 hover:border-gray-300'"
                 class="flex flex-col items-center gap-2 p-3 border-2 rounded-xl cursor-pointer transition text-center">
            <input type="radio" v-model="editingUser.role" value="administrator" class="hidden">
            <span class="text-2xl">👑</span>
            <div><p class="text-xs font-bold text-gray-700">Administrator</p></div>
          </label>
        </div>
      </div>
    </div>
    <div class="px-6 py-4 border-t flex justify-end gap-2">
      <button @click="showEditModal = false" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">Batal</button>
      <button @click="updateUser" :disabled="savingEdit" class="flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-xl text-sm font-semibold transition">
        <svg v-if="savingEdit" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
        Simpan Perubahan
      </button>
    </div>
  </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div v-if="confirmDelete" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
    <div class="p-6 text-center">
      <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
      </div>
      <h3 class="font-bold text-gray-800 text-lg mb-1">Hapus User?</h3>
      <p class="text-gray-500 text-sm mb-6"><strong>{{ deleteTarget?.username }}</strong> akan dihapus permanen.</p>
      <div class="flex gap-2">
        <button @click="confirmDelete = false" class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">Batal</button>
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
      users: [], loading: true, search: '',
      toasts: [], toastId: 0,
      showAddModal: false, savingAdd: false,
      showEditModal: false, savingEdit: false,
      confirmDelete: false, deleteTarget: null, deleting: false,
      currentUserId: <?php echo $userid; ?>,
      csrfToken: '<?php echo $csrfToken; ?>',
      newUser: { username: '', password: '', password_confirm: '', role: 'view_only' },
      editingUser: { id: null, username: '', password: '', role: '' },
    };
  },
  computed: {
    filteredUsers() {
      const s = this.search.toLowerCase();
      return s ? this.users.filter(u => u.username.toLowerCase().includes(s)) : this.users;
    },
    passStrength() {
      const p = this.newUser.password;
      let s = 0;
      if (p.length >= 8) s++;
      if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++;
      if (/[0-9]/.test(p)) s++;
      if (/[^a-zA-Z0-9]/.test(p)) s++;
      return s;
    },
    passStrengthPct() { return this.passStrength * 25; },
    passStrengthColor() {
      const c = ['bg-gray-300','bg-red-500','bg-amber-500','bg-yellow-400','bg-green-500'];
      return c[this.passStrength] || 'bg-gray-300';
    },
    passStrengthLabel() {
      const l = ['','Lemah','Cukup','Baik','Kuat'];
      return l[this.passStrength] || '';
    },
  },
  methods: {
    toast(msg, type='success') {
      const id = ++this.toastId;
      this.toasts.push({ id, message: msg, type });
      setTimeout(() => this.removeToast(id), 4000);
    },
    removeToast(id) { this.toasts = this.toasts.filter(t => t.id !== id); },

    async loadUsers() {
      this.loading = true;
      try {
        const res = await fetch('api/get_users.php', { credentials: 'same-origin' });
        const data = await res.json();
        if (data.status === 'success') this.users = data.data;
      } catch(e) { this.toast('Gagal memuat data user', 'error'); }
      finally { this.loading = false; }
    },

    async addUser() {
      if (!this.newUser.username || !this.newUser.password) { this.toast('Username dan password wajib diisi', 'error'); return; }
      if (this.newUser.password.length < 8) { this.toast('Password minimal 8 karakter', 'error'); return; }
      if (this.newUser.password !== this.newUser.password_confirm) { this.toast('Password tidak cocok', 'error'); return; }
      this.savingAdd = true;
      try {
        const res  = await fetch('api/add_user.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
          body: JSON.stringify({ username: this.newUser.username, password: this.newUser.password, role: this.newUser.role, csrf_token: this.csrfToken })
        });
        const data = await res.json();
        if (data.status === 'success') {
          this.toast('User berhasil ditambahkan!');
          this.showAddModal = false;
          this.newUser = { username:'', password:'', password_confirm:'', role:'view_only' };
          this.loadUsers();
        } else { this.toast(data.message || 'Gagal menambahkan user', 'error'); }
      } catch(e) { this.toast('Terjadi kesalahan', 'error'); }
      finally { this.savingAdd = false; }
    },

    editUser(user) {
      this.editingUser = { id: user.id, username: user.username, password: '', role: user.role };
      this.showEditModal = true;
    },

    async updateUser() {
      this.savingEdit = true;
      try {
        const res  = await fetch('api/update_user.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
          body: JSON.stringify({ id: this.editingUser.id, password: this.editingUser.password, role: this.editingUser.role, csrf_token: this.csrfToken })
        });
        const data = await res.json();
        if (data.status === 'success') {
          this.toast('User berhasil diupdate!');
          this.showEditModal = false;
          this.loadUsers();
        } else { this.toast(data.message || 'Gagal update user', 'error'); }
      } catch(e) { this.toast('Terjadi kesalahan', 'error'); }
      finally { this.savingEdit = false; }
    },

    confirmDeleteUser(user) { this.deleteTarget = user; this.confirmDelete = true; },

    async doDelete() {
      this.deleting = true;
      try {
        const res  = await fetch('api/delete_user.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
          body: JSON.stringify({ id: this.deleteTarget.id, csrf_token: this.csrfToken })
        });
        const data = await res.json();
        if (data.status === 'success') {
          this.toast('User berhasil dihapus');
          this.confirmDelete = false; this.deleteTarget = null;
          this.loadUsers();
        } else { this.toast(data.message || 'Gagal menghapus user', 'error'); }
      } catch(e) { this.toast('Terjadi kesalahan', 'error'); }
      finally { this.deleting = false; }
    },

    formatDate(d) {
      if (!d) return '—';
      return new Date(d).toLocaleDateString('id-ID', { year:'numeric', month:'short', day:'numeric' });
    }
  },
  mounted() { this.loadUsers(); }
}).mount('#app');
</script>
</body>
</html>
