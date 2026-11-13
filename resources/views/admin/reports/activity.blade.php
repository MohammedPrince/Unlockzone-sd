@extends('layouts.panel')

@section('title', __('Activity log'))
@section('eyebrow', __('Insights'))
@section('page-title', __('Activity log'))
@section('page-description', __('An audit trail of sign-ins, credit operations and every change made in the control panel.'))

@section('content')
    <div class="card">
        <form class="filters" method="get" action="{{ route('admin.activity') }}">
            <div class="field grow">
                <label for="q">{{ __('Search') }}</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Text in the description') }}">
            </div>
            <div class="field">
                <label for="action">{{ __('Action') }}</label>
                <select id="action" name="action">
                    <option value="">{{ __('All actions') }}</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ __(ucfirst(str_replace('_', ' ', $action))) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field"><label for="from">{{ __('From') }}</label><input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="field"><label for="to">{{ __('To') }}</label><input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}"></div>
            <div class="btns">
                <button class="btn btn-primary" type="submit">{{ __('Filter') }}</button>
                <a class="btn" href="{{ route('admin.activity') }}">{{ __('Reset') }}</a>
            </div>
        </form>

        @if ($logs->isEmpty())
            <div class="empty"><strong>{{ __('No activity found') }}</strong>{{ __('Nothing matches these filters.') }}</div>
        @else
            <div class="table-wrap">
                <table class="cards">
                    <thead><tr><th>{{ __('When') }}</th><th>{{ __('Who') }}</th><th>{{ __('Action') }}</th><th>{{ __('Description') }}</th><th>{{ __('IP address') }}</th></tr></thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td data-label="{{ __('When') }}" class="nowrap">{{ $log->created_at->translatedFormat('M j, Y H:i') }}<span class="sub">{{ $log->created_at->diffForHumans() }}</span></td>
                                <td data-label="{{ __('Who') }}">{{ $log->user->username ?? __('Guest') }}<span class="sub">{{ $log->user ? __(ucfirst($log->user->role)) : '' }}</span></td>
                                <td data-label="{{ __('Action') }}"><span class="tag">{{ str_replace('_', ' ', $log->action) }}</span></td>
                                <td class="primary" style="font-weight: 400">{{ $log->description }}</td>
                                <td data-label="{{ __('IP address') }}" class="mono">{{ $log->ip_address ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $logs->links() }}
        @endif
    </div>
@endsection
