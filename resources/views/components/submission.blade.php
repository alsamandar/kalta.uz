{{-- Lets the server recognise a repeated submit of this very form (retry, double tap) --}}
<input type="hidden" name="_submission" value="{{ \Illuminate\Support\Str::uuid() }}">
