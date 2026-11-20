@extends('layouts.panel')

@section('title', __('Administrators'))
@section('eyebrow', __('Platform'))
@section('page-title', __('Administrators'))
@section('page-description', __('People who can sign in to this control panel. Every administrator has full access.'))

@section('content')
    @php $me = auth()->id(); @endphp
    <div class="stack">
        <div class="card">
            <div class="card-head"><h2>{{ __('Administrators') }}</h2></div>
            <div class="table-wrap">
                <table class="cards">
                    <thead>
                        <tr><th>{{ __('Administrator') }}</th><th>{{ __('Contact') }}</th><th>{{ __('Status') }}</th><th>{{ __('Last sign-in') }}</th><th>{{ __('Added') }}</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($admins as $a)
                            <tr>
                                <td class="primary">
                                    {{ $a->name ?: $a->username }}
                                    @if ($a->id === $me) <span class="badge badge-active">{{ __('You') }}</span> @endif
                                    <span class="sub">{{ '@' . $a->username }}</span>
                                </td>
                                <td data-label="{{ __('Contact') }}">{{ $a->email }}<span class="sub">{{ $a->phone ?: '—' }}</span></td>
                                <td data-label="{{ __('Status') }}"><span class="badge badge-{{ $a->status }}">{{ __(ucfirst($a->status)) }}</span></td>
                                <td data-label="{{ __('Last sign-in') }}" class="nowrap">{{ $a->last_login_at ? $a->last_login_at->translatedFormat('M j, Y H:i') : '—' }}</td>
                                <td data-label="{{ __('Added') }}" class="nowrap">{{ $a->created_at?->translatedFormat('M j, Y') }}</td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn-sm" href="{{ route('admin.admins.edit', $a->id) }}">{{ __('Edit') }}</a>
                                    @if ($a->id !== $me)
                                            <form method="post" action="{{ route('admin.admins.status', $a->id) }}" @if ($a->isActive()) data-confirm="{{ __('Suspend :name? They will not be able to sign in.', ['name' => $a->username]) }}" @endif>
                                                @csrf
                                                <input type="hidden" name="active" value="{{ $a->isActive() ? 0 : 1 }}">
                                                <button class="btn btn-sm" type="submit">{{ $a->isActive() ? __('Suspend') : __('Activate') }}</button>
                                            </form>
                                            <form method="post" action="{{ route('admin.admins.destroy', $a->id) }}" data-confirm="{{ __('Delete administrator :name? This cannot be undone.', ['name' => $a->username]) }}">
                                                @csrf @method('delete')
                                                <button class="btn btn-sm btn-danger" type="submit">{{ __('Delete') }}</button>
                                            </form>
                                    @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <form method="post" action="{{ route('admin.admins.store') }}" class="card" data-once>
            @csrf
            <div class="card-head"><h2>{{ __('Add administrator') }}</h2></div>
            <div class="card-body form-grid">
                <div>
                    <label for="first_name">{{ __('First name') }}</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" placeholder="{{ __('e.g. Sara') }}" required maxlength="100">
                </div>
                <div>
                    <label for="last_name">{{ __('Last name') }} <span class="muted">({{ __('optional') }})</span></label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" placeholder="{{ __('e.g. Ahmed') }}" maxlength="100">
                </div>
                <div>
                    <label for="username">{{ __('Username') }}</label>
                    <input type="text" id="username" name="username" value="{{ old('username') }}" placeholder="{{ __('e.g. sara.admin') }}" required minlength="3" maxlength="60" autocomplete="off">
                </div>
                <div>
                    <label for="email">{{ __('Email') }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="name@example.com" required maxlength="191" autocomplete="off">
                </div>
                <div>
                    <label for="phone">{{ __('Mobile') }} <span class="muted">({{ __('optional') }})</span></label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="+249 9XX XXX XXX" maxlength="30">
                </div>
                <div></div>
                <div>
                    <label for="password">{{ __('Password') }}</label>
                    <input type="password" id="password" name="password" required placeholder="{{ __('At least 8 characters') }}" minlength="8" autocomplete="new-password">
                    <p class="help">{{ __('At least 8 characters. Share it with the new administrator securely.') }}</p>
                </div>
                <div>
                    <label for="password_confirmation">{{ __('Confirm password') }}</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required placeholder="{{ __('Type the password again') }}" minlength="8" autocomplete="new-password">
                </div>
            </div>
            <div class="card-body">
                <p class="help">{{ __('The new administrator signs in at the admin login with this username or email, and has full access to the control panel.') }}</p>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'plus']) {{ __('Add administrator') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection
