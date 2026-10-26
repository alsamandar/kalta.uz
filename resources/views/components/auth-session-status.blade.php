@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'alert alert-success']) }} role="status"><x-i name="check" />{{ $status }}</div>
@endif
