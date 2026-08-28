<script setup>
import { computed } from 'vue';
import { examProtectionStore as store } from '../stores/examProtection';

/** Konsekuensi pelanggaran, disesuaikan dgn proteksi_mode ujian ini — supaya
 *  siswa tidak dijanjikan "diblokir" padahal mode-nya sebenarnya cuma
 *  memotong nilai / mencatat peringatan. */
const consequenceText = computed(() => {
    switch (store.proteksiMode) {
        case 'pengurangan_nilai':
            return `Tiap pelanggaran memotong ${store.nilaiPengurangan} poin dari nilai akhir`;
        case 'logout_otomatis':
            return `> ${store.maxViolations} pelanggaran = ujian otomatis disubmit & keluar`;
        case 'peringatan':
            return 'Pelanggaran dicatat sebagai peringatan';
        default:
            return `> ${store.maxViolations} pelanggaran = ujian diblokir`;
    }
});
</script>

<template>
    <div v-if="store.protectionEnabled" class="card card-pad text-xs text-ink-600 space-y-1.5">
        <div class="font-semibold text-ink-900 mb-2">🛡️ Aturan Ujian</div>
        <div v-show="!store.isMobile">• Tetap dalam mode fullscreen</div>
        <div v-show="store.isMobile">• Jangan keluar / pindah aplikasi lain</div>
        <div v-show="store.isMobile">• Jangan gunakan mode layar terbagi (split-screen)</div>
        <div>• Jangan pindah tab / window</div>
        <div>• Dilarang copy / paste / klik kanan</div>
        <div>• Dilarang membuka DevTools</div>
        <div class="pt-2 mt-2 border-t border-slate-100 text-rose-600 font-semibold">
            {{ consequenceText }}
        </div>
        <!-- Dipisah dari daftar larangan di atas: ini catatan, bukan pelanggaran. -->
        <div class="pt-2 mt-2 border-t border-slate-100 text-ink-500">
            🔍 Zoom dimatikan selama ujian — mencoba zoom <strong>tidak</strong> dihitung pelanggaran.
        </div>
    </div>
</template>
