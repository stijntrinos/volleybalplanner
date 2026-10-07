<div class="planner-error-details">
    <ul>
        @foreach ($details as $detail)
            <li>
                <x-filament::icon icon="heroicon-m-chevron-right" class="planner-error-details-icon" />
                <span>{{ $detail }}</span>
            </li>
        @endforeach
    </ul>
</div>
