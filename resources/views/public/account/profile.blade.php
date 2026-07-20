@extends('layouts.public')

@section('title', 'Perfil turista')

@section('content')
<section class="account-shell">
    <div class="container-xl">
        @include('public.account.partials.nav')
        <h1>Perfil del turista</h1>
        <div class="profile-panel">
            <div><span>Nombre</span><strong>{{ $user->name }}</strong></div>
            <div><span>Email</span><strong>{{ $user->email }}</strong></div>
            <div><span>Cuenta</span><strong>Turista</strong></div>
        </div>
    </div>
</section>
@endsection
