@extends('layouts.panel')

@php $result = session('tool_result'); @endphp

@section('title', __('Server Tools'))
@section('eyebrow', __('System'))
@section('page-title', __('Server Tools'))
@section('page-description', __('Run the deploy commands on the hosting: backups, caches, migrations, waiting jobs, composer and public files.'))

@section('content')
    {{-- Which site this is --}}
    <div class="card tool-summary">
        <div>
            <small>{{ __('Site') }}</small>
            <span @class(['stage-badge', 'prod' => $summary['stage'] === 'PROD'])>{{ $summary['stage'] }}</span>
        </div>
        <div>
            <small>{{ __('Address') }}</small>
            <b dir="ltr">{{ $summary['address'] }}</b>
        </div>
        <div>
            <small>{{ __('Project folder') }}</small>
            <b dir="ltr">{{ $summary['folder'] }}</b>
        </div>
        <div>
            <small>{{ __('Server Tools') }}</small>
            <b @class(['big', 'pos' => $summary['enabled'], 'neg' => !$summary['enabled']])>{{ $summary['enabled'] ? __('On') : __('Off') }}</b>
        </div>
    </div>

    @include('partials.page-guide', [
        'key' => 'admin-server-tools',
        'title' => 'After uploading new code',
        'intro' => 'Run these in order after you upload a new version to the hosting.',
        'steps' => [
            '<b>Database backup</b>: create a backup first.',
            '<b>Composer install</b>: only when composer.json or composer.lock changed.',
            '<b>Clear caches</b>, then <b>Run migrations</b>, then <b>Optimize</b>.',
            'Changed only .env? <b>Clear config cache</b>, then <b>Cache config</b>.',
            '<b>Copy public folders</b> (only when the site is served from another folder): when CSS, JS or images changed.',
            'Something went wrong? Check <b>laravel.log</b> at the bottom of this page.',
            '<b>Run waiting jobs</b>: only if emails or orders are stuck in the queue.',
            '<b>Email settings</b>: enter the admin email and the hosting email account, save, then send a test email.',
        ],
        'note' => 'One tool runs at a time. Every run is saved in the Activity log with its result.',
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

    @unless ($enabled)
        <div class="alert alert-error">{{ __('Server Tools are switched off. Set SERVER_TOOLS_ENABLED=true in .env to use the buttons.') }}</div>
    @endunless

    <div class="card">
        <div class="card-head">
            <div>
                <h2>{{ __('Tools') }}</h2>
                <p>{{ __('Each button runs one command on the server and shows its output.') }}</p>
            </div>
        </div>
        <div class="card-body tool-grid">
            @foreach ($tools as $key => [$title, $command, $help, $danger, $off])
                <form method="post" action="{{ route('admin.tools.run', $key) }}" @class(['tool-card', 'is-off' => $off || !$enabled]) data-once data-tool
                      @if ($danger) data-confirm="{{ __('Run :tool now? This changes the live site.', ['tool' => __($title)]) }}" @endif>
                    @csrf
                    <h3>@if ($key === 'backup') @include('partials.icon', ['name' => 'database']) @endif {{ __($title) }}</h3>
                    <code dir="ltr">{{ $command }}</code>
                    <p>{{ __($help) }}</p>
                    @if ($off)
                        <small class="tool-off">{{ __($off) }}</small>
                    @endif
                    <button type="submit" @class(['btn', 'btn-sm', 'btn-primary' => !$danger, 'btn-run-danger' => $danger]) @disabled($off || !$enabled)>
                        <span class="when-idle">{{ __('Run') }}</span><span class="when-busy">{{ __('Running…') }}</span>
                    </button>
                </form>
            @endforeach
        </div>
    </div>

    {{-- Email: who gets the notices, and the hosting email account that sends them. Saved to .env. --}}
    @php $m = $mailSettings; @endphp
    <div class="card" id="mail">
        <div class="card-head">
            <div>
                <h2>{{ __('Email settings') }}</h2>
                <p>{{ __('The admin gets an email for every new customer and every new order. Customers get an email when their order is completed, failed or cancelled. Saved in .env (MAIL_* keys).') }}</p>
            </div>
            <span @class(['stage-badge', 'prod' => $m['mailer'] !== 'log'])>{{ $m['mailer'] === 'log' ? __('Off') : __('On') }}</span>
        </div>
        <form method="post" action="{{ route('admin.tools.mail.save') }}" data-once>
            @csrf @method('put')
            <div class="card-body form-grid">
                <div class="full">
                    <label for="admin_address">{{ __('Admin email') }}</label>
                    <input type="text" id="admin_address" name="admin_address" dir="ltr" value="{{ old('admin_address', $m['admin_address']) }}" maxlength="500" placeholder="admin@unlock-sd.com">
                    <p class="help">{{ __('Where new customer and new order notices are sent. Several addresses: separate them with commas. Empty = no admin emails.') }}</p>
                </div>
                <div>
                    <label for="mailer">{{ __('Send emails with') }}</label>
                    <select id="mailer" name="mailer" required>
                        @foreach ($mailers as $key => $label)
                            <option value="{{ $key }}" @selected(old('mailer', $m['mailer']) === $key)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="from_address">{{ __('Sender email') }}</label>
                    <input type="email" id="from_address" name="from_address" dir="ltr" value="{{ old('from_address', $m['from_address']) }}" required maxlength="191" placeholder="support@unlock-sd.com">
                    <p class="help">{{ __('The hosting email account the site sends from. Usually the same as the SMTP username.') }}</p>
                </div>
                <div>
                    <label for="from_name">{{ __('Sender name') }} <span class="muted">({{ __('optional') }})</span></label>
                    <input type="text" id="from_name" name="from_name" value="{{ old('from_name', $m['from_name']) }}" maxlength="100" placeholder="{{ setting('site_name') ?: config('app.name') }}">
                </div>
                <div>
                    <label for="host">{{ __('SMTP server') }}</label>
                    <input type="text" id="host" name="host" dir="ltr" value="{{ old('host', $m['host']) }}" maxlength="191" placeholder="mail.unlock-sd.com">
                    <p class="help">{{ __('From cPanel > Email Accounts > Connect Devices (Outgoing Server).') }}</p>
                </div>
                <div>
                    <label for="encryption">{{ __('Encryption') }}</label>
                    <select id="encryption" name="encryption" required>
                        @foreach ($encryptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('encryption', $m['encryption']) === $key)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="port">{{ __('Port') }}</label>
                    <input type="number" id="port" name="port" dir="ltr" value="{{ old('port', $m['port']) }}" min="1" max="65535" placeholder="465">
                </div>
                <div>
                    <label for="username">{{ __('SMTP username') }}</label>
                    <input type="text" id="username" name="username" dir="ltr" value="{{ old('username', $m['username']) }}" maxlength="191" autocomplete="off" placeholder="support@unlock-sd.com">
                </div>
                <div>
                    <label for="password">{{ __('Email password') }}</label>
                    <input type="password" id="password" name="password" dir="ltr" maxlength="191" autocomplete="new-password"
                           placeholder="{{ $m['has_password'] ? __('Saved. Leave empty to keep it') : __('The email account password') }}">
                    <p class="help">{{ __('Stored in .env only and never shown again.') }}</p>
                </div>
            </div>
            <div class="card-body">
                <button type="submit" class="btn btn-primary">{{ __('Save email settings') }}</button>
            </div>
        </form>
        <form method="post" action="{{ route('admin.tools.mail.test') }}" class="card-body mail-test" data-once>
            @csrf
            <label for="test_to">{{ __('Send a test email') }}</label>
            <div class="logo-row">
                <input type="email" id="test_to" name="to" dir="ltr" required maxlength="191" value="{{ old('to', explode(',', $m['admin_address'])[0] ?: auth()->user()->email) }}">
                <button type="submit" class="btn">{{ __('Send test') }}</button>
            </div>
            <p class="help">{{ __('Save first, then send a test. If it fails, the mail server\'s answer is shown here and in laravel.log.') }}</p>
        </form>
    </div>

    <div class="card tool-info">
        <div class="card-head">
            <div>
                <h2>{{ __('Server details') }}</h2>
                <p>{{ __('Change any of these in .env (SERVER_TOOLS_* keys) if the hosting paths are different.') }}</p>
            </div>
        </div>
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

            <dl class="kv server-paths">
                @foreach ($details as $label => $value)
                    <dt>{{ __($label) }}</dt>
                    <dd class="mono">{{ __($value) }}</dd>
                @endforeach
            </dl>
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

    {{-- laravel.log viewer --}}
    <div class="card" id="log">
        <div class="card-head">
            <div>
                <h2 dir="ltr">laravel.log</h2>
                <p>
                    <span dir="ltr">storage/logs/laravel.log</span>
                    @if ($logInfo)
                        · <span class="num">{{ $logInfo['size'] }}</span> · {{ __('updated :time', ['time' => $logInfo['updated']->diffForHumans()]) }}
                    @endif
                    · {{ __('last :count lines, newest first', ['count' => $logLines]) }}
                </p>
            </div>
            <div class="row-actions">
                <a class="btn btn-sm" href="{{ route('admin.tools.index') }}#log">↻ {{ __('Refresh') }}</a>
                @if ($logInfo)
                    <a class="btn btn-sm" href="{{ route('admin.tools.log.download') }}">@include('partials.icon', ['name' => 'download']) {{ __('Download') }}</a>
                    <form method="post" action="{{ route('admin.tools.log.clear') }}" data-confirm="{{ __('Empty laravel.log? Download it first if you need it.') }}">
                        @csrf @method('delete')
                        <button class="btn btn-sm btn-run-danger" type="submit">{{ __('Clear log') }}</button>
                    </form>
                @endif
            </div>
        </div>
        @if (empty($logEntries))
            <div class="empty"><strong>{{ __('The log is empty') }}</strong>{{ __('Errors and warnings from the site will appear here.') }}</div>
        @else
            <div class="card-body">
                <div class="log-view" dir="ltr">@foreach ($logEntries as $entry)<div class="log-{{ $entry['level'] }}">{{ $entry['text'] }}</div>@endforeach</div>
            </div>
        @endif
    </div>
@endsection
