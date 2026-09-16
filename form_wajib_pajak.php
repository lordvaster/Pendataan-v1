<?php
// Author: Zeday @join.co.id
require_once 'includes/config.php';
require_once 'includes/security.php';
require_once 'includes/db_connect.php';
startSecureSession();

// Fetch kecamatan list from DB
$kecamatanList = [];
$res = $conn->query("SELECT nama_kecamatan FROM kecamatan WHERE status = 'active' ORDER BY nama_kecamatan ASC");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) $kecamatanList[] = $row['nama_kecamatan'];
}
if (empty($kecamatanList)) {
    $kecamatanList = ["Dusun Timur","Dusun Tengah","Patangkep Tutui","Pematang Karau",
                      "Raren Batuah","Paku","Karusen Janang","Awang","Benua Lima","Paju Epat"];
}

$csrfToken = generateCSRFToken();
$username = $_SESSION['username'] ?? '';
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tambah Data — Sistem Pajak Kendaraan</title>
  <script src="assets/js/tailwind.min.js"></script>
  <script src="assets/js/vue.global.prod.js"></script>
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <style>
    [v-cloak]{display:none}
    .step-active   { background:#2563EB; color:#fff; }
    .step-done     { background:#10B981; color:#fff; }
    .step-inactive { background:#e5e7eb; color:#9ca3af; }
    .field-error input, .field-error select, .field-error textarea {
      border-color: #ef4444 !important;
      box-shadow: 0 0 0 2px rgba(239,68,68,.15);
    }
    .preview-img { transition: opacity .2s; }
    .preview-img:hover { opacity: .85; }
  </style>
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
  <div class="max-w-3xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <a href="maindashboard.php" class="flex items-center gap-1 text-blue-200 hover:text-white transition text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Dashboard
      </a>
      <span class="text-blue-300">/</span>
      <h1 class="font-bold text-base">Tambah Data Wajib Pajak</h1>
    </div>
    <span class="text-sm text-blue-200 hidden sm:block">👤 <?php echo escapeHtml($username); ?></span>
  </div>
</header>

<main class="max-w-3xl mx-auto px-4 sm:px-6 py-6">

  <!-- Progress Steps -->
  <div class="flex items-center justify-center mb-8">
    <template v-for="(s, i) in steps" :key="i">
      <div class="flex flex-col items-center">
        <div :class="step > i ? 'step-done' : step === i ? 'step-active' : 'step-inactive'"
             class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold transition-all">
          <svg v-if="step > i" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
          <span v-else>{{ i + 1 }}</span>
        </div>
        <p class="text-xs mt-1 font-medium" :class="step === i ? 'text-blue-600' : 'text-gray-400'">{{ s }}</p>
      </div>
      <div v-if="i < steps.length - 1" :class="step > i ? 'bg-green-400' : 'bg-gray-200'"
           class="flex-1 h-0.5 mx-2 mb-4 transition-all max-w-16"></div>
    </template>
  </div>

  <!-- Card -->
  <div class="bg-white rounded-2xl shadow-md overflow-hidden">

    <!-- Step 1: Data Pribadi -->
    <div v-show="step === 0" class="p-6 space-y-5">
      <div class="border-b pb-3 mb-4">
        <h2 class="font-bold text-gray-800 text-lg">Data Pribadi</h2>
        <p class="text-gray-400 text-sm mt-0.5">Informasi identitas wajib pajak</p>
      </div>

      <div :class="errors.nama ? 'field-error' : ''">
        <label class="label-style">Nama Lengkap <span class="text-red-500">*</span></label>
        <input v-model="form.nama" type="text" placeholder="Masukkan nama lengkap" class="input-style">
        <p v-if="errors.nama" class="text-red-500 text-xs mt-1">{{ errors.nama }}</p>
      </div>

      <div :class="errors.nik ? 'field-error' : ''">
        <label class="label-style">Nomor KTP (NIK) <span class="text-red-500">*</span></label>
        <input v-model="form.nik" type="text" maxlength="16" placeholder="16 digit nomor KTP" class="input-style font-mono"
               @input="form.nik = form.nik.replace(/\D/g,'')">
        <p class="text-xs text-gray-400 mt-1">{{ form.nik.length }}/16 digit</p>
        <p v-if="errors.nik" class="text-red-500 text-xs mt-1">{{ errors.nik }}</p>
      </div>

      <div :class="errors.no_hp ? 'field-error' : ''">
        <label class="label-style">Nomor HP <span class="text-red-500">*</span></label>
        <input v-model="form.no_hp" type="tel" placeholder="08xxxxxxxxxx" class="input-style">
        <p v-if="errors.no_hp" class="text-red-500 text-xs mt-1">{{ errors.no_hp }}</p>
      </div>

      <div :class="errors.alamat ? 'field-error' : ''">
        <label class="label-style">Alamat Lengkap <span class="text-red-500">*</span></label>
        <textarea v-model="form.alamat" rows="3" placeholder="Jalan, RT/RW, Kelurahan/Desa..." class="input-style resize-none"></textarea>
        <p v-if="errors.alamat" class="text-red-500 text-xs mt-1">{{ errors.alamat }}</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div :class="errors.kecamatan ? 'field-error' : ''">
          <label class="label-style">Kecamatan <span class="text-red-500">*</span></label>
          <select v-model="form.kecamatan" class="input-style">
            <option value="">-- Pilih Kecamatan --</option>
            <option v-for="k in kecamatanList" :key="k" :value="k">{{ k }}</option>
          </select>
          <p v-if="errors.kecamatan" class="text-red-500 text-xs mt-1">{{ errors.kecamatan }}</p>
        </div>
        <div :class="errors.desa ? 'field-error' : ''">
          <label class="label-style">Desa / Kelurahan <span class="text-red-500">*</span></label>
          <input v-model="form.desa" type="text" placeholder="Nama desa/kelurahan" class="input-style">
          <p v-if="errors.desa" class="text-red-500 text-xs mt-1">{{ errors.desa }}</p>
        </div>
      </div>
    </div>

    <!-- Step 2: Data Kendaraan -->
    <div v-show="step === 1" class="p-6 space-y-5">
      <div class="border-b pb-3 mb-4">
        <h2 class="font-bold text-gray-800 text-lg">Data Kendaraan</h2>
        <p class="text-gray-400 text-sm mt-0.5">Informasi kendaraan yang didaftarkan</p>
      </div>

      <div :class="errors.no_polisi ? 'field-error' : ''">
        <label class="label-style">Nomor Polisi <span class="text-red-500">*</span></label>
        <input v-model="form.no_polisi" type="text" placeholder="KH 1234 AB" class="input-style uppercase tracking-widest font-mono"
               @input="form.no_polisi = form.no_polisi.toUpperCase()">
        <p class="text-xs text-gray-400 mt-1">Contoh: KH 1234 AB</p>
        <p v-if="errors.no_polisi" class="text-red-500 text-xs mt-1">{{ errors.no_polisi }}</p>
      </div>

      <div :class="errors.jenis_kendaraan ? 'field-error' : ''">
        <label class="label-style">Jenis Kendaraan <span class="text-red-500">*</span></label>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <label v-for="j in jenisOptions" :key="j.value"
                 :class="form.jenis_kendaraan === j.value ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-200 hover:border-gray-300'"
                 class="flex flex-col items-center gap-1.5 p-3 border-2 rounded-xl cursor-pointer transition text-center">
            <input type="radio" v-model="form.jenis_kendaraan" :value="j.value" class="hidden">
            <span class="text-2xl">{{ j.icon }}</span>
            <span class="text-xs font-semibold leading-tight">{{ j.value }}</span>
          </label>
        </div>
        <p v-if="errors.jenis_kendaraan" class="text-red-500 text-xs mt-1">{{ errors.jenis_kendaraan }}</p>
      </div>

      <div :class="errors.kondisi ? 'field-error' : ''">
        <label class="label-style">Kondisi Kendaraan <span class="text-red-500">*</span></label>
        <div class="space-y-2">
          <label v-for="k in kondisiOptions" :key="k.value"
                 :class="form.kondisi === k.value ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                 class="flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer transition">
            <input type="radio" v-model="form.kondisi" :value="k.value" class="w-4 h-4 text-blue-600">
            <div>
              <p class="text-sm font-semibold text-gray-800">{{ k.value }}</p>
              <p class="text-xs text-gray-400">{{ k.desc }}</p>
            </div>
          </label>
        </div>
        <p v-if="errors.kondisi" class="text-red-500 text-xs mt-1">{{ errors.kondisi }}</p>
      </div>
    </div>

    <!-- Step 3: Upload Dokumen -->
    <div v-show="step === 2" class="p-6 space-y-5">
      <div class="border-b pb-3 mb-4">
        <h2 class="font-bold text-gray-800 text-lg">Unggah Dokumen</h2>
        <p class="text-gray-400 text-sm mt-0.5">KTP dan STNK wajib, foto kendaraan opsional</p>
      </div>

      <div v-for="doc in docFields" :key="doc.key" :class="errors[doc.key] ? 'field-error' : ''">
        <label class="label-style">{{ doc.label }} {{ doc.required ? '*' : '(Opsional)' }}</label>
        <div @click="$refs[doc.key + 'Input'].click()"
             :class="form[doc.key] ? 'border-green-400 bg-green-50' : 'border-dashed border-gray-300 hover:border-blue-400'"
             class="border-2 rounded-xl p-4 text-center cursor-pointer transition relative">
          <!-- Preview -->
          <img v-if="previews[doc.key] && !doc.isPdf"
               :src="previews[doc.key]" class="preview-img w-full max-h-40 object-contain rounded-lg mb-2">
          <div v-else-if="form[doc.key]" class="flex items-center justify-center gap-2 py-4">
            <svg class="w-10 h-10 text-red-400" fill="currentColor" viewBox="0 0 24 24"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20M8,11H16V13H8V11M8,15H16V17H8V15Z"/></svg>
            <span class="text-sm text-gray-600 font-medium">{{ form[doc.key].name }}</span>
          </div>
          <div v-else class="py-6">
            <svg class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            <p class="text-sm text-gray-500 font-medium">Klik untuk pilih file</p>
            <p class="text-xs text-gray-400 mt-0.5">JPG, PNG, atau PDF • Maks 5MB</p>
          </div>
          <input :ref="doc.key + 'Input'" type="file" class="hidden"
                 :accept="doc.accept" @change="e => handleFile(e, doc.key)">
        </div>
        <p v-if="errors[doc.key]" class="text-red-500 text-xs mt-1">{{ errors[doc.key] }}</p>
      </div>

      <!-- reCAPTCHA -->
      <div class="pt-2">
        <label class="label-style">Verifikasi <span class="text-red-500">*</span></label>
        <div class="flex justify-center">
          <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
        </div>
        <p v-if="errors.captcha" class="text-red-500 text-xs mt-1 text-center">{{ errors.captcha }}</p>
      </div>
    </div>

    <!-- Step 4: Review & Submit -->
    <div v-show="step === 3" class="p-6 space-y-5">
      <div class="border-b pb-3 mb-4">
        <h2 class="font-bold text-gray-800 text-lg">Konfirmasi Data</h2>
        <p class="text-gray-400 text-sm mt-0.5">Periksa kembali sebelum mengirim</p>
      </div>

      <div class="bg-gray-50 rounded-xl p-4 space-y-2 text-sm">
        <div class="flex justify-between py-1.5 border-b border-gray-100">
          <span class="text-gray-500 font-medium">Nama</span>
          <span class="text-gray-800 font-semibold text-right">{{ form.nama }}</span>
        </div>
        <div class="flex justify-between py-1.5 border-b border-gray-100">
          <span class="text-gray-500 font-medium">NIK</span>
          <span class="text-gray-800 font-mono">{{ form.nik }}</span>
        </div>
        <div class="flex justify-between py-1.5 border-b border-gray-100">
          <span class="text-gray-500 font-medium">No HP</span>
          <span class="text-gray-800">{{ form.no_hp }}</span>
        </div>
        <div class="flex justify-between py-1.5 border-b border-gray-100">
          <span class="text-gray-500 font-medium">Kecamatan</span>
          <span class="text-gray-800">{{ form.kecamatan }}</span>
        </div>
        <div class="flex justify-between py-1.5 border-b border-gray-100">
          <span class="text-gray-500 font-medium">Desa</span>
          <span class="text-gray-800">{{ form.desa }}</span>
        </div>
        <div class="flex justify-between py-1.5 border-b border-gray-100">
          <span class="text-gray-500 font-medium">No Polisi</span>
          <span class="text-gray-800 font-mono font-bold">{{ form.no_polisi }}</span>
        </div>
        <div class="flex justify-between py-1.5 border-b border-gray-100">
          <span class="text-gray-500 font-medium">Jenis</span>
          <span class="text-gray-800">{{ form.jenis_kendaraan }}</span>
        </div>
        <div class="flex justify-between py-1.5">
          <span class="text-gray-500 font-medium">Kondisi</span>
          <span class="text-gray-800">{{ form.kondisi }}</span>
        </div>
      </div>

      <!-- Files summary -->
      <div class="grid grid-cols-3 gap-3">
        <div v-for="doc in docFields" :key="doc.key" class="text-center">
          <div :class="form[doc.key] ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400'"
               class="rounded-xl p-3 mb-1 flex items-center justify-center h-16">
            <svg v-if="form[doc.key]" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <svg v-else class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </div>
          <p class="text-xs font-medium" :class="form[doc.key] ? 'text-green-700' : 'text-gray-400'">{{ doc.short }}</p>
        </div>
      </div>
    </div>

    <!-- Navigation Buttons -->
    <div class="px-6 py-4 border-t border-gray-100 flex justify-between gap-3">
      <button v-if="step > 0" @click="step--"
              class="flex items-center gap-1.5 px-4 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Sebelumnya
      </button>
      <div v-else></div>

      <button v-if="step < 3" @click="nextStep"
              class="flex items-center gap-1.5 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition">
        Selanjutnya
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </button>

      <button v-else @click="submit" :disabled="loading"
              class="flex items-center gap-1.5 px-5 py-2.5 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white rounded-xl text-sm font-bold transition">
        <svg v-if="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
        {{ loading ? 'Mengirim...' : 'Kirim & Simpan' }}
      </button>
    </div>

  </div>

</main>
</div>

<style>
.label-style { display: block; font-size: .75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .375rem; }
.input-style { width: 100%; border: 1px solid #e5e7eb; border-radius: .75rem; padding: .625rem .875rem; font-size: .875rem; transition: border-color .15s, box-shadow .15s; }
.input-style:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.12); }
</style>

<script>
const { createApp } = Vue;
createApp({
  data() {
    return {
      step: 0,
      steps: ['Data Pribadi', 'Kendaraan', 'Dokumen', 'Konfirmasi'],
      loading: false,
      form: {
        nama:'', nik:'', no_hp:'', alamat:'', kecamatan:'', desa:'',
        no_polisi:'', jenis_kendaraan:'', kondisi:'',
        ktp: null, stnk: null, foto_kendaraan: null
      },
      previews: { ktp: null, stnk: null, foto_kendaraan: null },
      errors: {},
      toasts: [], toastId: 0,
      kecamatanList: <?php echo json_encode($kecamatanList); ?>,
      csrfToken: '<?php echo $csrfToken; ?>',
      jenisOptions: [
        { value: 'Roda 2', icon: '🏍️' },
        { value: 'Roda 3', icon: '🛺' },
        { value: 'Roda 4', icon: '🚗' },
        { value: 'Roda 6 atau lebih', icon: '🚛' },
      ],
      kondisiOptions: [
        { value: 'Baik (Masih bisa digunakan)', desc: 'Kendaraan masih beroperasi normal' },
        { value: 'Rusak', desc: 'Kendaraan mengalami kerusakan ringan/sedang' },
        { value: 'Rusak Berat (Bangkai kendaraan)', desc: 'Tidak bisa dioperasikan' },
        { value: 'Kendaraan Hilang', desc: 'Kendaraan tidak diketahui keberadaannya' },
        { value: 'Kendaraan tanpa Surat', desc: 'Tidak memiliki surat-surat kendaraan' },
      ],
      docFields: [
        { key: 'ktp',            label: 'Foto KTP',       short: 'KTP',     required: true,  accept: '.jpg,.jpeg,.png,.pdf', isPdf: false },
        { key: 'stnk',           label: 'Foto STNK',      short: 'STNK',    required: true,  accept: '.jpg,.jpeg,.png,.pdf', isPdf: false },
        { key: 'foto_kendaraan', label: 'Foto Kendaraan', short: 'Foto',    required: false, accept: 'image/*',              isPdf: false },
      ],
    };
  },
  methods: {
    toast(msg, type='success') {
      const id = ++this.toastId;
      this.toasts.push({ id, message: msg, type });
      setTimeout(() => this.removeToast(id), 5000);
    },
    removeToast(id) { this.toasts = this.toasts.filter(t => t.id !== id); },

    handleFile(e, field) {
      const file = e.target.files[0];
      if (!file) return;
      this.form[field] = file;
      if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = ev => { this.previews[field] = ev.target.result; };
        reader.readAsDataURL(file);
      } else {
        this.previews[field] = null;
      }
    },

    validateStep() {
      const e = {};
      if (this.step === 0) {
        if (!this.form.nama.trim())  e.nama = 'Nama tidak boleh kosong';
        if (!this.form.nik)          e.nik  = 'NIK tidak boleh kosong';
        else if (!/^\d{16}$/.test(this.form.nik)) e.nik = 'NIK harus 16 digit angka';
        if (!this.form.no_hp)        e.no_hp = 'Nomor HP tidak boleh kosong';
        else if (!/^(0|62)[0-9]{9,12}$/.test(this.form.no_hp.replace(/[\s\-\+]/g,''))) e.no_hp = 'Format nomor HP tidak valid';
        if (!this.form.alamat.trim()) e.alamat = 'Alamat tidak boleh kosong';
        if (!this.form.kecamatan)    e.kecamatan = 'Kecamatan harus dipilih';
        if (!this.form.desa.trim())  e.desa = 'Desa tidak boleh kosong';
      }
      if (this.step === 1) {
        if (!this.form.no_polisi)    e.no_polisi = 'Nomor polisi tidak boleh kosong';
        else if (!/^[A-Z]{1,2}\s?\d{1,4}\s?[A-Z]{1,3}$/i.test(this.form.no_polisi)) e.no_polisi = 'Format nomor polisi tidak valid (contoh: KH 1234 AB)';
        if (!this.form.jenis_kendaraan) e.jenis_kendaraan = 'Jenis kendaraan harus dipilih';
        if (!this.form.kondisi)      e.kondisi = 'Kondisi kendaraan harus dipilih';
      }
      if (this.step === 2) {
        if (!this.form.ktp)   e.ktp  = 'Foto KTP wajib diunggah';
        if (!this.form.stnk)  e.stnk = 'Foto STNK wajib diunggah';
        const captcha = typeof grecaptcha !== 'undefined' ? grecaptcha.getResponse() : '';
        if (!captcha)         e.captcha = 'Silakan selesaikan verifikasi CAPTCHA';
      }
      this.errors = e;
      return Object.keys(e).length === 0;
    },

    nextStep() {
      if (this.validateStep()) this.step++;
    },

    async submit() {
      if (!this.validateStep()) return;
      this.loading = true;
      const fd = new FormData();
      fd.append('nama', this.form.nama);
      fd.append('nik', this.form.nik);
      fd.append('no_hp', this.form.no_hp);
      fd.append('alamat', this.form.alamat);
      fd.append('kecamatan', this.form.kecamatan);
      fd.append('desa', this.form.desa);
      fd.append('no_polisi', this.form.no_polisi);
      fd.append('jenis_kendaraan', this.form.jenis_kendaraan);
      fd.append('kondisi', this.form.kondisi);
      fd.append('csrf_token', this.csrfToken);
      fd.append('g-recaptcha-response', grecaptcha.getResponse());
      if (this.form.ktp) fd.append('ktp', this.form.ktp);
      if (this.form.stnk) fd.append('stnk', this.form.stnk);
      if (this.form.foto_kendaraan) fd.append('foto_kendaraan', this.form.foto_kendaraan);

      try {
        const res  = await fetch('api/insert_pajak_secure.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const json = await res.json();
        if (json.status === 'success') {
          this.toast('Data berhasil disimpan!');
          setTimeout(() => { window.location.href = '<?php echo APP_URL; ?>/maindashboard.php'; }, 1800);
        } else {
          const msg = json.errors ? json.errors.join(', ') : (json.message || 'Gagal menyimpan data');
          this.toast(msg, 'error');
          if (typeof grecaptcha !== 'undefined') grecaptcha.reset();
        }
      } catch (err) {
        this.toast('Gagal terhubung ke server. Silakan coba lagi.', 'error');
      } finally {
        this.loading = false;
      }
    }
  }
}).mount('#app');
</script>
</body>
</html>
