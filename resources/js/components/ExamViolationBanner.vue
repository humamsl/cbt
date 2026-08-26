<script setup>
import { examProtectionStore as store } from '../stores/examProtection';
</script>

<template>
    <Transition name="exam-banner-fade">
        <div v-if="store.protectionEnabled && store.showWarning"
             class="bg-rose-600 text-white px-4 py-2 text-sm text-center font-semibold">
            ⚠ Pelanggaran: {{ store.lastViolation }}
            ({{ store.violations }}/{{ store.maxViolations }}) — Ujian akan diblokir jika berlanjut!
        </div>
        <!-- Info zoom dikunci: BUKAN pelanggaran, jadi warnanya netral (slate,
             bukan merah) dan tidak menyebut hitungan pelanggaran sama sekali.
             Hanya tampil kalau tidak ada banner pelanggaran yang lebih penting. -->
        <div v-else-if="store.zoomHint"
             class="bg-slate-700 text-white px-4 py-2 text-xs text-center font-medium">
            🔍 Zoom dinonaktifkan selama ujian — ini <strong>tidak</strong> dihitung sebagai pelanggaran.
        </div>
    </Transition>
</template>

<style scoped>
.exam-banner-fade-enter-active,
.exam-banner-fade-leave-active {
    transition: opacity 0.2s ease;
}
.exam-banner-fade-enter-from,
.exam-banner-fade-leave-to {
    opacity: 0;
}
</style>
