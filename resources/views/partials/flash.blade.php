@if (session('status'))
    <div class="container pt-3">
        <div class="alert alert-success mb-0" role="alert">{{ session('status') }}</div>
    </div>
@endif

@if ($errors->any())
    <div class="container pt-3">
        <div class="alert alert-danger mb-0" role="alert">
            <strong>Please check the highlighted fields.</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
