@php
    $names = $products->take(3)->pluck('name')->join(', ', ' and ');
    $more = $products->count() - 3;
@endphp
<a class="deal" href="{{ route('home', ['filter' => 'offers']) }}#menu">
    🔥 Offers on now
    <span>{{ $names }}@if ($more > 0) and {{ $more }} more @endif</span>
</a>