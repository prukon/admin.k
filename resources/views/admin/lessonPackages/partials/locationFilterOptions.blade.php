{{-- Опции фильтра/выбора объекта: «Все», «Без объекта», список locations. --}}
@php
    $locationFilterSelected = $locationFilterSelected ?? '';
@endphp
<option value="" @selected($locationFilterSelected === '' || $locationFilterSelected === null)>Все</option>
<option value="none" @selected($locationFilterSelected === 'none')>Без объекта</option>
@foreach ($locations as $loc)
    <option value="{{ $loc->id }}" @selected((string) $locationFilterSelected === (string) $loc->id)>{{ $loc->name }}</option>
@endforeach
