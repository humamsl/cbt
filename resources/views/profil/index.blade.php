@extends('layouts.app')
@section('title', 'Profil Akun')

@section('content')
<x-page-header title="Profil Akun" subtitle="Informasi akun dan keamanan"/>

<div class="grid lg:grid-cols-3 gap-6">
    <div class="card card-pad text-center">
        <img src="{{ $user->profile_photo_url }}" class="w-28 h-28 rounded-full mx-auto mb-3 ring-4 ring-brand-50 object-cover" alt="">
        <div class="font-semibold text-ink-900 text-lg">{{ $user->name ?? ($user->nama_siswa ?? $user->nama_ptk ?? '-') }}</div>
        <div class="text-sm text-ink-500 capitalize">{{ $user->user_type }}</div>
        <div class="text-xs text-ink-500 mt-1 font-mono">{{ $user->user_name }}</div>
    </div>

    <form method="POST" action="{{ route('profil.password') }}" class="card card-pad space-y-4 lg:col-span-2">
        @csrf
        <h3 class="font-semibold text-ink-900">Ubah Password</h3>
        <x-field name="current_password" type="password" label="Password Saat Ini" required/>
        <x-field name="password" type="password" label="Password Baru" required/>
        <x-field name="password_confirmation" type="password" label="Konfirmasi Password" required/>
        <div class="flex justify-end pt-2 border-t border-slate-100">
            <button class="btn-primary">Simpan</button>
        </div>
    </form>
</div>
@endsection
