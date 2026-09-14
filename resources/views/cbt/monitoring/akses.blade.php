@extends('layouts.app')
@section('title', 'Setting Akses Monitoring Ujian')
@section('breadcrumb', 'Admin / Monitoring Ujian / Setting Akses')

@section('content')
<div class="max-w-3xl space-y-5" x-data="aksesPage({
        allRombels: @js($rombels->map(fn ($r) => ['id' => $r->id, 'nama' => $r->nama_rombel])->values()),
        aksesMap: @js($aksesPerGuru->map(fn ($rows) => $rows->pluck('rombongan_belajar_id')->values())),
        guruNames: @js($aksesPerGuru->mapWithKeys(fn ($rows) => [$rows->first()->guru_id => optional($rows->first()->guru)->nama_ptk ?? 'Petugas'])),
    })">

    {{-- Form tambah akses --}}
    <div class="card">
        <div class="card-header">
            <h2 class="font-bold text-ink-900">Setting Akses Monitoring Ujian</h2>
            <a href="{{ route('monitoring.index') }}" class="btn-ghost text-xs">&larr; Kembali ke Monitoring</a>
        </div>
        <form method="POST" action="{{ route('monitoring.akses.store') }}" class="card-pad space-y-4">
            @csrf
            <div class="rounded-xl border border-slate-200 p-4 space-y-4">
                <div>
                    <label class="label">Petugas Monitoring Ujian <span class="text-rose-500">*</span></label>
                    <select name="guru_id" class="select" required>
                        <option value="">Pilih Petugas</option>
                        @foreach($gurus as $g)
                            <option value="{{ $g->id }}" @selected(old('guru_id') == $g->id)>
                                {{ $g->nama_ptk }} — {{ $g->nip }}
                            </option>
                        @endforeach
                    </select>
                    @error('guru_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Pilih Kelas yang hendak di monitoring <span class="text-rose-500">*</span></label>
                    <div class="max-h-56 overflow-y-auto rounded-xl border border-slate-200 p-3 grid grid-cols-2 sm:grid-cols-3 gap-1">
                        @forelse($rombels as $r)
                            <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-sm hover:bg-brand-50 cursor-pointer">
                                <input type="checkbox" name="rombel_ids[]" value="{{ $r->id }}"
                                       @checked(in_array($r->id, (array) old('rombel_ids', [])))
                                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <span>{{ $r->nama_rombel }}</span>
                            </label>
                        @empty
                            <div class="col-span-full text-center text-xs text-ink-500 py-3">Belum ada rombel di tahun ajaran aktif.</div>
                        @endforelse
                    </div>
                    <p class="mt-1 text-xs text-ink-500">Bisa memilih lebih dari satu kelas. Guru yang ditunjuk akan bisa memonitoring semua ujian yang menarget kelas terpilih.</p>
                    @error('rombel_ids')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex justify-end">
                <button class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Tambah</button>
            </div>
        </form>
    </div>

    {{-- Daftar akses yang sudah diberikan --}}
    <div class="card">
        <div class="card-header">
            <h3 class="font-bold text-ink-900">Petugas Terdaftar</h3>
            <span class="badge-brand">{{ $aksesPerGuru->count() }} petugas</span>
        </div>
        <div class="table-wrap">
        <table class="table-modern">
            <thead><tr><th>Petugas</th><th>Kelas yang Dimonitoring</th><th class="w-px whitespace-nowrap">Aksi</th></tr></thead>
            <tbody>
            @forelse($aksesPerGuru as $guruId => $rows)
                <tr>
                    <td class="align-top">
                        <div class="font-semibold text-ink-900">{{ optional($rows->first()->guru)->nama_ptk ?? 'Guru tidak ditemukan' }}</div>
                        <div class="text-xs text-ink-500">{{ optional($rows->first()->guru)->nip }}</div>
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($rows as $a)
                                <span class="badge-muted inline-flex items-center gap-1">
                                    {{ optional($a->rombel)->nama_rombel ?? 'Rombel #'.$a->rombongan_belajar_id }}
                                    <form method="POST" action="{{ route('monitoring.akses.destroy', $a) }}" class="inline"
                                          onsubmit="return confirm('Hapus akses kelas ini?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-rose-500 hover:text-rose-700 font-bold leading-none" title="Hapus akses kelas ini">&times;</button>
                                    </form>
                                </span>
                            @endforeach
                        </div>
                    </td>
                    <td class="align-top whitespace-nowrap">
                        <div class="flex items-center gap-1">
                            <button type="button" @click="openEdit({{ $guruId }})" class="btn-ghost p-2" title="Edit akses kelas">
                                <x-icon name="edit"/>
                            </button>
                            <form method="POST" action="{{ route('monitoring.akses.destroy-all', $guruId) }}"
                                  onsubmit="return confirm('Hapus SEMUA akses monitoring {{ optional($rows->first()->guru)->nama_ptk ?? 'petugas ini' }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-ghost p-2 text-rose-600" title="Hapus semua akses petugas ini">
                                    <x-icon name="trash"/>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center py-8 text-ink-500">Belum ada petugas yang diberi akses monitoring.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{-- MODAL EDIT AKSES: checkbox kelas persis seperti form tambah di atas,
         tapi submit-nya MENYAMAKAN set kelas (tambah + hapus sekaligus) --
         beda dari form tambah yang cuma menambah. --}}
    <div x-show="modalOpen" x-cloak
         class="fixed inset-0 z-50 grid place-items-center bg-slate-900/60 p-4"
         @keydown.escape.window="modalOpen = false">
        <div @click.outside="modalOpen = false"
             class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between p-4 border-b border-slate-100">
                <h3 class="font-bold text-ink-900">Edit Akses — <span x-text="editingGuruName"></span></h3>
                <button type="button" @click="modalOpen = false" class="btn-ghost p-2 text-xl leading-none">&times;</button>
            </div>
            <form method="POST" :action="editUrl" class="flex flex-col overflow-hidden flex-1">
                @csrf @method('PUT')
                <div class="p-4 overflow-y-auto">
                    <p class="text-xs text-ink-500 mb-2">Centang kelas yang boleh dimonitoring, hilangkan centang untuk mencabut aksesnya.</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-1">
                        <template x-for="r in allRombels" :key="r.id">
                            <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-sm hover:bg-brand-50 cursor-pointer">
                                <input type="checkbox" name="rombel_ids[]" :value="r.id"
                                       :checked="editingRombelIds.includes(r.id)"
                                       @change="toggleEditRombel(r.id, $event.target.checked)"
                                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <span x-text="r.nama"></span>
                            </label>
                        </template>
                    </div>
                </div>
                <div class="flex justify-end gap-2 p-4 border-t border-slate-100">
                    <button type="button" @click="modalOpen = false" class="btn-secondary">Batal</button>
                    <button type="submit" class="btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function aksesPage({ allRombels, aksesMap, guruNames }) {
    return {
        allRombels, aksesMap, guruNames,
        modalOpen: false,
        editingGuruId: null,
        editingGuruName: '',
        editingRombelIds: [],
        editUrl: '',

        openEdit(guruId) {
            this.editingGuruId = guruId;
            this.editingGuruName = this.guruNames[guruId] || 'Petugas';
            this.editingRombelIds = [...(this.aksesMap[guruId] || [])];
            this.editUrl = `{{ url('monitoring/akses/petugas') }}/${guruId}`;
            this.modalOpen = true;
        },

        toggleEditRombel(id, checked) {
            if (checked) {
                if (! this.editingRombelIds.includes(id)) this.editingRombelIds.push(id);
            } else {
                this.editingRombelIds = this.editingRombelIds.filter(x => x !== id);
            }
        },
    };
}
window.aksesPage = aksesPage;
</script>
@endsection
