<div class="d-flex justify-content-end gap-2 mt-3">
    <a class="btn btn-outline-secondary" href="{{ url()->current() }}">Limpiar</a>
    <button class="btn btn-primary" type="submit">
        <i class="ti ti-filter me-1"></i>
        Generar
    </button>
    <button class="btn btn-outline-primary" type="submit" formaction="{{ $pdfRoute }}" formtarget="_blank">
        <i class="ti ti-printer me-1"></i>
        Imprimir
    </button>
</div>
