<div class="d-flex flex-wrap align-items-center gap-2">
    @foreach($attribute->values as $value)
        @if($attribute->swatche_type === 'color')
            @php $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value->swatche_value) ? $value->swatche_value : '#000000'; @endphp
            <span class="badge bg-transparent text-body border d-inline-flex align-items-center gap-2 p-2">
                <span class="rounded-circle border flex-shrink-0" style="width: 28px; height: 28px; background-color: {{ $color }}"></span>
                {{ $value->title }} <span class="text-secondary user-select-all">#{{ $value->id }}</span>
            </span>
        @elseif($attribute->swatche_type === 'image')
            <span class="badge bg-transparent text-body border d-inline-flex align-items-center gap-2 p-1 pe-2">
                <img src="{{ $value->swatche_value }}" class="rounded object-fit-cover flex-shrink-0"
                     style="width: 42px; height: 42px" alt="{{ $value->title }}">
                {{ $value->title }} <span class="text-secondary user-select-all">#{{ $value->id }}</span>
            </span>
        @else
            <span class="badge bg-secondary-lt">{{ $value->title }} <span class="user-select-all">#{{ $value->id }}</span></span>
        @endif
    @endforeach
    @if($attribute->values_count > $attribute->values->count())
        <span class="badge bg-secondary-lt">+{{ $attribute->values_count - $attribute->values->count() }}</span>
    @endif
    <span class="text-secondary small ms-1">{{ trans_choice('labels.attribute_value_count', $attribute->values_count, ['count' => $attribute->values_count]) }}</span>
</div>
