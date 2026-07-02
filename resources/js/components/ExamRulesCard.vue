<script setup>
import { examProtectionStore as store } from '../stores/examProtection';
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
            > {{ store.maxViolations }} pelanggaran = ujian diblokir
        </div>
    </div>
</template>
