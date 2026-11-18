{{-- Plain-text copy of the email, for mail apps that do not show HTML. Not HTML, so nothing is escaped. --}}
{!! $mailSubject !!}
{!! str_repeat('=', mb_strlen($mailSubject)) !!}

@foreach ($lines as $line)
{!! $line !!}

@endforeach
@if ($code !== null && $code !== '')
{!! $codeLabel ?: 'Result' !!}:
{!! $code !!}

@endif
@foreach ($details as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach

@if ($buttonText && $buttonUrl)
{!! $buttonText !!}: {!! $buttonUrl !!}
@endif

{!! setting('site_name') ?: config('app.name') !!} · {!! url('/') !!}
