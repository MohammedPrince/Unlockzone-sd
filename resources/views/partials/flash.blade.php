@if (session('success'))
    <div class="alert alert-success" role="status">{{ __(session('success')) }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-error" role="alert">
        @if ($errors->count() === 1)
            {{ __($errors->first()) }}
        @else
            <ul>
                @foreach ($errors->all() as $message)
                    <li>{{ __($message) }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
