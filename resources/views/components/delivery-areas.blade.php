<div class="areas">
    @foreach ($areas as $area)
        <span>{{ $area->name }}</span>
    @endforeach
    <span>Your area is not listed? Message us</span>
</div>