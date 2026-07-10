@if (session('success'))
    <div
        class="alert alert-success d-none"
        data-swal-success="{{ session('success') }}"
        @if (session('report_url')) data-report-url="{{ session('report_url') }}" @endif
    ></div>
@endif

@if (($errors ?? null)?->any())
    <div class="alert alert-danger d-none" data-swal-error="{{ $errors->first() ?: 'Revisa los datos ingresados.' }}"></div>
@endif
