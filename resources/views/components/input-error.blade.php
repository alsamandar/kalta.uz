@props(['messages'])

@if ($messages)
    @foreach ((array) $messages as $message)
        <p {{ $attributes->merge(['class' => 'field-error']) }} role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>
    @endforeach
@endif
