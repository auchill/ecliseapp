@php
    $sweetAlerts = $sweetAlerts ?? false;
    $flashMessages = collect([
        'success' => session('success'),
        'error' => session('error'),
        'warning' => session('warning'),
        'info' => session('info'),
        'status' => session('status'),
    ])->filter(fn ($message) => filled($message));
    $validationMessages = $errors->any() ? $errors->all() : [];
    $sweetAlertPayload = [
        'flash' => $flashMessages->all(),
        'validation' => $validationMessages,
    ];
@endphp

@if ($sweetAlerts)
    @if ($flashMessages->isNotEmpty() || count($validationMessages) > 0)
        <div
            data-eclise-flash
            data-messages="{{ json_encode($sweetAlertPayload) }}"
            hidden
        ></div>
        <noscript>
            @if ($flashMessages->isNotEmpty())
                <div class="container pt-3">
                    @foreach ($flashMessages as $type => $message)
                        <div class="alert alert-{{ $type === 'error' ? 'danger' : ($type === 'status' ? 'success' : $type) }} mb-3" role="alert">{{ $message }}</div>
                    @endforeach
                </div>
            @endif

            @if (count($validationMessages) > 0)
                <div class="container pt-3">
                    <div class="alert alert-danger mb-3" role="alert">
                        <strong>Please check the highlighted fields.</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($validationMessages as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </noscript>
    @endif
@else
    @if ($flashMessages->isNotEmpty())
        <div class="container pt-3">
            @foreach ($flashMessages as $type => $message)
                <div class="alert alert-{{ $type === 'error' ? 'danger' : ($type === 'status' ? 'success' : $type) }} mb-3" role="alert">{{ $message }}</div>
            @endforeach
        </div>
    @endif

    @if (count($validationMessages) > 0)
        <div class="container pt-3">
            <div class="alert alert-danger mb-3" role="alert">
                <strong>Please check the highlighted fields.</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($validationMessages as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
@endif
