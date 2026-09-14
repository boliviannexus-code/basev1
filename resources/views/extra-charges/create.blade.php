@extends('layouts.admin')
@section('page-title', 'Nuevo cargo extra')
@section('content')<x-ui.form-panel title="Datos del cargo">@include('extra-charges.partials.create-form')</x-ui.form-panel>@endsection
