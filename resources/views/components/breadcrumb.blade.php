@props(['items' => null])

@php
    $items = $items ?? app(\App\Support\Breadcrumbs::class)->forCurrentRoute(request());
@endphp

@if (count($items) > 1)
    <div class="eclise-breadcrumb-wrap">
        <div class="container">
            <nav class="breadcrumb-scroll" aria-label="breadcrumb">
                <ol class="breadcrumb eclise-breadcrumb mb-0">
                    @foreach ($items as $item)
                        <li class="breadcrumb-item {{ $item['active'] ? 'active' : '' }}" @if($item['active']) aria-current="page" @endif>
                            @if (! $item['active'] && filled($item['url']))
                                <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                            @else
                                <span>{{ $item['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        </div>
    </div>
@endif
