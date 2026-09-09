@extends('layouts.app')
@section('title', 'Registrasi Ujian')
@section('breadcrumb', 'CBT / Registrasi Ujian')

@section('content')
<div x-data="tesPage()">
<x-page-header title="Registrasi Ujian" subtitle="Konfigurasi paket ujian online">
    <x-slot:action>
        @if($isAdmin)
            <a href="{{ route('tes.activity-log') }}" class="btn-secondary"><x-icon name="document" class="w-4 h-4"/> Riwayat Aktivitas</a>
        @endif
        <a href="{{ route('tes.create') }}" class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Registrasi Ujian Baru</a>
    </x-slot:action>
</x-page-header>

<form class="card card-pad mb-4 max-w-md flex gap-2">
    <input name="q" value="{{ request('q') }}" class="input" placeholder="Cari nama tes...">
    <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
</form>

@if($items->count())
<div class="card card-pad mb-3 flex items-center justify-between gap-3 flex-wrap">
    <label class="flex items-center gap-2 text-sm text-ink-600 select-none">
        <input type="checkbox" class="rounded text-brand-600 focus:ring-brand-500" :checked="allOnPageSelected()" @change="toggleAllOnPage($event.target.checked)">
        Pilih semua di halaman ini
    </label>
    <div x-show="selected.length" x-cloak class="flex items-center gap-3">
        <span class="text-sm text-ink-500"><span x-text="selected.length"></span> tes dipilih</span>
        <button type="button" @click="openDeleteModal()" class="btn-secondary text-rose-600">
            <x-icon name="trash" class="w-4 h-4"/> Hapus Terpilih
        </button>
    </div>
</div>
@endif

<div class="grid gap-3">
    @forelse($items as $t)
        @php $isMine = (int) $t->created_by_guru_id === (int) auth()->id(); @endphp
        <div class="card card-pad">
            <div class="flex items-start justify-between gap-4">
                <input type="checkbox" class="rounded text-brand-600 focus:ring-brand-500 mt-1 shrink-0"
                       value="{{ $t->id }}" :checked="selected.includes({{ $t->id }})"
                       @change="toggleSelect({{ $t->id }}, $event.target.checked)"
                       @if(!$isAdmin && !$isMine) disabled title="Bukan registrasi ujian buatan Anda" @endif>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="{{ $t->status_badge }}">{{ ucfirst($t->status) }}</span>
                        <span class="badge-muted">{{ optional($t->mapel)->nama_mapel ?? '🌐 Ujian Umum' }}</span>
                        {{-- Daftar ini menampilkan tes SEMUA guru yang mengajar mapel+rombel
                             yang sama (bukan cuma milik sendiri) -- label pemilik ini penting
                             supaya tidak salah kira draft guru lain sebagai milik sendiri. --}}
                        <span class="badge-muted">👤 {{ $t->creator->nama_ptk ?? 'Admin' }}</span>
                        @if($t->target_mode === 'per_siswa')
                            <span class="badge-info">👤 {{ $t->siswa_targets_count ?? $t->siswaTargets()->count() }} siswa terpilih</span>
                        @elseif($t->target_mode === 'per_tingkat')
                            {{-- Target per tingkat: tampilkan tingkatnya supaya admin bisa
                                 langsung memverifikasi ujian ini menyasar tingkat mana saja --}}
                            @forelse((array) $t->target_tingkat as $tk)
                                <span class="badge-info">Tingkat {{ $tk }}</span>
                            @empty
                                <span class="badge-warning">⚠ Tingkat belum diatur</span>
                            @endforelse
                        @else
                            @foreach($t->rombelTargets as $rb)
                                <span class="badge-info">{{ $rb->nama_rombel }}</span>
                            @endforeach
                            @if($t->rombelTargets->isEmpty() && $t->rombel)
                                <span class="badge-info">{{ $t->rombel->nama_rombel }}</span>
                            @endif
                        @endif
                    </div>
                    <div class="text-lg font-bold text-ink-900">{{ $t->name }}</div>
                    <div class="text-sm text-ink-500 mt-1">{{ Str::limit($t->description, 140) }}</div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <a href="{{ route('tes.questions', $t) }}" class="btn-secondary text-xs">
                        <x-icon name="document" class="w-4 h-4"/> Soal
                    </a>
                    <a href="{{ route('tes.edit', $t) }}" class="btn-ghost p-2"><x-icon name="edit"/></a>
                    <form method="POST" action="{{ route('tes.duplicate', $t) }}" onsubmit="return confirm('Duplikat tes \'{{ $t->name }}\'? Salinannya dibuat sebagai Draft, soal &amp; target ikut disalin (jawaban siswa tidak).')">
                        @csrf<button type="submit" class="btn-ghost p-2 text-brand-600" title="Duplikat tes"><x-icon name="copy"/></button>
                    </form>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                <div><div class="text-xs text-ink-500">Durasi</div><div class="font-semibold">{{ $t->duration }} menit</div></div>
                <div><div class="text-xs text-ink-500">Soal</div><div class="font-semibold">{{ $t->questions_count }}</div></div>
                <div><div class="text-xs text-ink-500">Attempt</div><div class="font-semibold">{{ $t->attempts_count }}</div></div>
                <div><div class="text-xs text-ink-500">Periode</div>
                    <div class="text-xs">{{ optional($t->valid_from)->format('d/m H:i') }} — {{ optional($t->valid_upto)->format('d/m H:i') }}</div>
                </div>
            </div>
        </div>
    @empty
        <div class="card card-pad text-center text-ink-500">Belum ada tes.</div>
    @endforelse
</div>
<div class="mt-4">{{ $items->links() }}</div>

{{-- MODAL KONFIRMASI PASSWORD ADMIN --}}
<div x-show="modalOpen" x-cloak
     class="fixed inset-0 z-50 grid place-items-center bg-slate-900/60 p-4"
     @keydown.escape.window="modalOpen = false">
    <div @click.outside="modalOpen = false"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between p-4 border-b border-slate-100">
            <h3 class="font-bold text-ink-900">Konfirmasi Hapus Registrasi Ujian</h3>
            <button @click="modalOpen = false" class="btn-ghost p-2 text-xl leading-none">×</button>
        </div>
        <div class="p-5 space-y-3">
            <p class="text-sm text-ink-600">
                Akan menghapus <strong x-text="selected.length"></strong> registrasi ujian terpilih.
                Tindakan ini tidak bisa dibatalkan. Masukkan email &amp; password akun <strong>Admin</strong> untuk melanjutkan.
            </p>
            <div>
                <label class="label">Email Admin</label>
                <input type="email" class="input" x-model="adminEmail" placeholder="admin@sekolah.id" autocomplete="off">
            </div>
            <div>
                <label class="label">Password Admin</label>
                <input type="password" class="input" x-model="adminPassword" placeholder="••••••••" autocomplete="off" @keydown.enter="confirmDelete()">
            </div>
            <p x-show="errorMsg" x-cloak class="text-xs text-rose-600" x-text="errorMsg"></p>
        </div>
        <div class="flex justify-end gap-2 p-4 border-t border-slate-100">
            <button type="button" @click="modalOpen = false" class="btn-secondary">Batal</button>
            <button type="button" @click="confirmDelete()" :disabled="submitting"
                    class="btn-primary bg-rose-600 hover:bg-rose-700 border-rose-600 disabled:opacity-60">
                <span x-text="submitting ? 'Memproses...' : 'Hapus Sekarang'"></span>
            </button>
        </div>
    </div>
</div>

<script>
function tesPage() {
    return {
        selected: [],
        idsOnPage: [@foreach ($items as $t) @if($isAdmin || (int) $t->created_by_guru_id === (int) auth()->id()) {{ $t->id }}, @endif @endforeach],
        modalOpen: false,
        adminEmail: '',
        adminPassword: '',
        errorMsg: '',
        submitting: false,

        toggleSelect(id, checked) {
            if (checked) {
                if (! this.selected.includes(id)) this.selected.push(id);
            } else {
                this.selected = this.selected.filter(x => x !== id);
            }
        },

        allOnPageSelected() {
            return this.idsOnPage.length > 0 && this.idsOnPage.every(id => this.selected.includes(id));
        },

        toggleAllOnPage(checked) {
            if (checked) {
                this.idsOnPage.forEach(id => { if (! this.selected.includes(id)) this.selected.push(id); });
            } else {
                this.selected = this.selected.filter(id => ! this.idsOnPage.includes(id));
            }
        },

        openDeleteModal() {
            if (! this.selected.length) return;
            this.errorMsg = '';
            this.adminPassword = '';
            this.modalOpen = true;
        },

        // Fetch, bukan submit form biasa -- supaya kalau password admin salah,
        // pilihan checkbox & modal TETAP terbuka (tinggal ulangi passwordnya),
        // tidak perlu reload halaman & memilih ulang tes yang mau dihapus.
        async confirmDelete() {
            if (! this.adminEmail || ! this.adminPassword) {
                this.errorMsg = 'Email & password admin wajib diisi.';
                return;
            }
            this.submitting = true;
            this.errorMsg = '';
            try {
                const res = await fetch(`{{ route('tes.destroy-selected') }}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        ids: this.selected,
                        admin_email: this.adminEmail,
                        admin_password: this.adminPassword,
                    }),
                });
                if (res.ok) {
                    window.location.reload();
                    return;
                }
                const data = await res.json().catch(() => ({}));
                this.errorMsg = data.errors?.admin_password?.[0]
                    || data.errors?.ids?.[0]
                    || data.message
                    || `Gagal menghapus (status ${res.status}).`;
            } catch (e) {
                this.errorMsg = e.message || 'Network error saat menghapus.';
            } finally {
                this.submitting = false;
            }
        },
    };
}
window.tesPage = tesPage;
</script>
</div>
@endsection
