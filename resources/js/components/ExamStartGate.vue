<script setup>
import { examProtectionStore as store } from '../stores/examProtection';
</script>

<template>
    <div v-show="store.needStart"
         class="fixed inset-0 z-50 bg-ink-900/95 backdrop-blur grid place-items-center p-6">
        <div class="card max-w-lg w-full p-8 text-center">
            <div class="mx-auto w-16 h-16 rounded-2xl bg-rose-50 text-rose-600 grid place-items-center mb-3 text-2xl">🛡️</div>
            <h2 class="text-xl font-bold text-ink-900">Ujian Berproteksi</h2>
            <p class="text-sm text-ink-600 mt-2">{{ store.quizName }}</p>

            <div class="mt-5 text-left text-sm text-ink-700 bg-amber-50 border border-amber-200 rounded-xl p-4 space-y-1.5">
                <div class="font-semibold text-amber-800 mb-1">⚠ Aturan ketat ujian:</div>
                <div :class="store.isMobile ? '' : 'hidden'">• Jangan keluar / pindah aplikasi lain</div>
                <div :class="store.isMobile ? '' : 'hidden'">• Jangan gunakan mode layar terbagi (split-screen)</div>
                <div>• Jangan pindah tab / window browser</div>
                <div>• Dilarang copy / paste / klik kanan</div>
                <div>• Dilarang membuka DevTools (F12)</div>
                <div class="font-semibold text-rose-700 pt-1.5 border-t border-amber-200">
                    Maks. <strong>{{ store.maxViolations }} pelanggaran</strong> — lebih dari itu, ujian <strong>OTOMATIS DIBLOKIR</strong>.
                </div>
            </div>

            <button type="button" @click="store.startExam()" class="btn-primary w-full mt-5 text-base py-3">
                Saya Mengerti, Mulai Ujian
            </button>
        </div>
    </div>
</template>
