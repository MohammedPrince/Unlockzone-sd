@extends('layouts.panel')

@php $result = session('tool_result'); @endphp

@section('title', __('Server Tools'))
@section('eyebrow', __('Platform'))
@section('page-title', __('Server Tools'))
@section('page-description', __('Maintenance commands for the server, one button each. Use them after uploading a new version.'))

@section('content')
    @include('partials.page-guide', [
        'key' => 'admin-server-tools',
        'title' => 'After uploading new code',
        'intro' => 'Run these in order after you upload a new version to the hosting.',
        'steps' => [
            '<b>Database backup</b>: create a backup first.',
            '<b>Composer install</b>: only when composer.json or composer.lock changed.',
            '<b>Clear caches</b>, then <b>Run migrations</b>, then <b>Optimize</b>.',
            'Changed only .env? <b>Clear config cache</b>, then <b>Cache config</b>.',
            'Changed only .blade.php pages? <b>Clear page templates</b>.',
            'Orders or emails stuck in the queue? <b>Run waiting jobs</b>.',
        ],
        'note' => 'Every run is written to the Activity log with who ran it and how long it took.',
    ])

    @if ($result)
        <div class="card tool-result {{ $result['ok'] ? 'ok' : 'bad' }}" id="tool-result">
            <div class="card-head">
                <h2>{{ $result['ok'] ? '✓' : '✕' }} {{ __($result['title']) }}</h2>
                <span class="muted">{{ $result['ok'] ? __('Finished') : __('Failed') }} · {{ __(':s seconds', ['s' => $result['seconds']]) }}</span>
            </div>
            <pre class="tool-output" dir="ltr">{{ $result['output'] }}</pre>
        </div>
    @endif

    <div class="card tool-info">
        <div class="card-head"><h2>{{ __('Server') }}</h2></div>
        <div class="card-body">
            <dl class="info-grid">
                @foreach ($info as $label => $value)
                    <div>
                        <dt>{{ __($label) }}</dt>
                        <dd @class(['warn' => ($label === 'Debug mode' && $value === 'On' && app()->environment('production')) || ($label === 'Failed jobs' && $value > 0)])>{{ $value ?? '—' }}</dd>
                    </div>
                @endforeach
            </dl>
            @if (app()->environment('production') && config('app.debug'))
                <p class="error-text" style="margin-top: 10px">{{ __('Debug mode is on in production. Set APP_DEBUG=false in .env so error details are never shown to visitors.') }}</p>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <h2>{{ __('Tools') }}</h2>
                <p>{{ __('Each button runs one command on the server and shows its output.') }}</p>
            </div>
        </div>
        <div class="card-body tool-grid">
            @foreach ($tools as $key => [$title, $command, $help, $danger])
                <form method="post" action="{{ route('admin.tools.run', $key) }}" class="tool-card" data-once data-tool
                      @if ($danger) data-confirm="{{ __('Run :tool now? This changes the live site.', ['tool' => __($title)]) }}" @endif>
                    @csrf
                    <h3>@if ($key === 'backup') @include('partials.icon', ['name' => 'database']) @endif {{ __($title) }}</h3>
                    <code dir="ltr">{{ $command }}</code>
                    <p>{{ __($help) }}</p>
                    <button type="submit" @class(['btn', 'btn-sm', 'btn-primary' => !$danger, 'btn-run-danger' => $danger])>
                        <span class="when-idle">{{ __('Run') }}</span><span class="when-busy">{{ __('Running…') }}</span>
                    </button>
                </form>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <h2>{{ __('Database backups') }}</h2>
                <p>{{ __('Stored privately on the server (not reachable from the web). The newest 15 are kept.') }}</p>
            </div>
        </div>
        @if (empty($backups))
            <div class="empty"><strong>{{ __('No backups yet') }}</strong>{{ __('Use "Database backup" above to create one.') }}</div>
        @else
            <div class="table-wrap">
                <table class="cards">
                    <thead><tr><th>{{ __('File') }}</th><th>{{ __('Date') }}</th><th class="right">{{ __('Size') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($backups as $backup)
                            <tr>
                                <td class="primary mono">{{ $backup['name'] }}</td>
                                <td data-label="{{ __('Date') }}" class="nowrap">{{ $backup['date']->translatedFormat('M j, Y H:i') }}<span class="sub">{{ $backup['date']->diffForHumans() }}</span></td>
                                <td data-label="{{ __('Size') }}" class="right num">{{ $backup['size'] }}</td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn-sm" href="{{ route('admin.tools.backups.download', $backup['name']) }}">@include('partials.icon', ['name' => 'download']) {{ __('Download') }}</a>
                                        <form method="post" action="{{ route('admin.tools.backups.destroy', $backup['name']) }}" data-confirm="{{ __('Delete this backup? This cannot be undone.') }}">
                                            @csrf @method('delete')
                                            <button class="btn btn-sm btn-danger" type="submit">{{ __('Delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
