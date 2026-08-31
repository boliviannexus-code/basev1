@extends('layouts.admin')
@section('title', 'Editar derecho de cancha | '.config('app.name'))
@section('page-title', 'Editar derecho de cancha')
@section('page-subtitle', $courtFee->name)
@section('content')
<x-ui.form-panel title="Datos generales">@include('court-fees.partials.edit-form')</x-ui.form-panel>
@endsection
