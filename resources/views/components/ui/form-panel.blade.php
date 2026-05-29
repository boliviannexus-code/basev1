@props([
    'action' => null,
    'method' => 'POST',
    'enctype' => null,
    'title' => null,
])

<div class="card form-panel">
    @if ($title)
        <div class="card-header">
            <h3 class="card-title">{{ $title }}</h3>
        </div>
    @endif

    <div class="card-body">
        @if ($action)
            <form method="POST" action="{{ $action }}" autocomplete="off" novalidate @if ($enctype) enctype="{{ $enctype }}" @endif>
                @csrf
                @if (! in_array(strtoupper($method), ['GET', 'POST'], true))
                    @method($method)
                @endif

                {{ $slot }}
            </form>
        @else
            {{ $slot }}
        @endif
    </div>

    @isset($footer)
        <div class="card-footer">
            {{ $footer }}
        </div>
    @endisset
</div>
