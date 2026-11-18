@extends('layouts.panel')

@section('title', __('API providers'))
@section('eyebrow', __('Service management'))
@section('page-title', __('API providers'))
@section('page-description', __("Connect to other service providers' servers and forward orders to them automatically."))

@section('page-actions')
    @if ($providers->isNotEmpty())
        <form method="post" action="{{ route('admin.providers.sync-orders') }}" class="inline" data-once>
            @csrf
            <button class="btn" type="submit">{{ __('Check open orders now') }}</button>
        </form>
    @endif
    <a class="btn btn-primary" href="{{ route('admin.providers.create') }}">@include('partials.icon', ['name' => 'plus']) {{ __('Connect a provider') }}</a>
@endsection

@section('content')
    @include('partials.page-guide', [
        'key' => 'admin-providers',
        'title' => 'How provider connections work',
        'intro' => 'A provider is another GSM service server that offers an API (the DHRU Fusion API standard). Once connected, a customer order is sent to that server and the result comes back by itself.',
        'steps' => [
            '<b>Connect</b>: enter the server address, your username on that server and the API key from your account there.',
            '<b>Sync services</b>: downloads the provider\'s price list.',
            '<b>Import</b>: tick the services you want to sell and set your profit margin. They are created here, linked to the provider.',
            '<b>Orders</b>: when a customer orders a linked service, it is charged here and forwarded to the provider. Success completes the request with the result; a rejection refunds the customer.',
            '<b>API clients</b> (second section): the other way round. You are the provider: give another server an API key on one of your customer accounts, and share all your services or only some. You can suspend or remove a client at any time.',
        ],
        'note' => 'Order results are fetched every minute when the scheduler runs, and whenever a request page is opened.',
    ])

    <div class="card">
        @if ($providers->isEmpty())
            <div class="empty"><strong>{{ __('No providers connected yet') }}</strong>{{ __('Connect your first provider to start selling its services.') }} <a href="{{ route('admin.providers.create') }}">{{ __('Connect a provider') }}</a></div>
        @else
            <div class="table-wrap">
                <table class="cards">
                    <thead><tr><th>{{ __('Provider') }}</th><th class="right">{{ __('Balance') }}</th><th class="right">{{ __('Price list') }}</th><th class="right">{{ __('Linked services') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($providers as $provider)
                            <tr>
                                <td class="primary">
                                    <a href="{{ route('admin.providers.services', $provider->id) }}">{{ $provider->name }}</a>
                                    <span class="sub mono">{{ $provider->url }}</span>
                                    @if ($provider->last_error) <span class="sub neg">{{ $provider->last_error }}</span> @endif
                                </td>
                                <td data-label="{{ __('Balance') }}" class="right num">
                                    {{ $provider->balance !== null ? amount($provider->balance) . ' ' . $provider->currency : '—' }}
                                    @if ($provider->last_checked_at) <span class="sub">{{ $provider->last_checked_at->diffForHumans() }}</span> @endif
                                </td>
                                <td data-label="{{ __('Price list') }}" class="right num">
                                    <a href="{{ route('admin.providers.services', $provider->id) }}">{{ number_format($provider->remote_services_count) }}</a>
                                    <span class="sub">{{ $provider->services_synced_at ? $provider->services_synced_at->diffForHumans() : __('Not synced yet') }}</span>
                                </td>
                                <td data-label="{{ __('Linked services') }}" class="right num">{{ number_format($provider->services_count) }}</td>
                                <td data-label="{{ __('Status') }}"><span class="badge badge-{{ $provider->status }}">{{ __(ucfirst($provider->status)) }}</span></td>
                                <td>
                                    <div class="row-actions">
                                        <form method="post" action="{{ route('admin.providers.test', $provider->id) }}" data-once>
                                            @csrf
                                            <button class="btn btn-sm" type="submit">{{ __('Test') }}</button>
                                        </form>
                                        <form method="post" action="{{ route('admin.providers.sync', $provider->id) }}" data-once>
                                            @csrf
                                            <button class="btn btn-sm btn-success" type="submit">{{ __('Sync services') }}</button>
                                        </form>
                                        <a class="btn btn-sm" href="{{ route('admin.providers.edit', $provider->id) }}">{{ __('Edit') }}</a>
                                        <form method="post" action="{{ route('admin.providers.destroy', $provider->id) }}" data-confirm="{{ __('Delete the connection to :name? Its services stay but will be handled manually.', ['name' => $provider->name]) }}">
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

    {{-- ===== Second direction: we are the provider. Other servers connect to our API. ===== --}}
    <div class="card" id="api-clients" style="margin-top: 28px">
        <div class="card-head">
            <div>
                <h2>{{ __('API clients — servers using your services') }}</h2>
                <p>{{ __('Give another server its own API key so it can order your services automatically, paid from a customer account\'s credit.') }}</p>
            </div>
            <a class="btn btn-primary btn-sm" href="{{ route('admin.api-clients.create') }}">@include('partials.icon', ['name' => 'plus']) {{ __('Add API client') }}</a>
        </div>

        <div class="card-body">
            <p class="help" style="margin: 0">
                {{ __('They connect with the DHRU Fusion API. Give them:') }}
                <b>{{ __('Server address') }}</b> <code dir="ltr">{{ url('/') }}</code>
                <button type="button" class="btn-copy-mini" data-copy="{{ url('/') }}" data-copied="{{ __('Copied') }}" title="{{ __('Copy') }}">@include('partials.icon', ['name' => 'copy'])<span data-copy-label class="sr-only">{{ __('Copy') }}</span></button>
                · <b>{{ __('Username') }}</b> {{ __('the customer\'s username') }}
                · <b>{{ __('API key') }}</b> {{ __('shown once below when you create it') }}.
                <span class="muted">({{ __('Full API address:') }} <code dir="ltr">{{ route('dhru-api') }}</code>)</span>
            </p>
        </div>

        @if ($newKey = session('new_api_key'))
            <div class="card-body">
                <div class="alert alert-success" style="margin: 0">
                    <strong>{{ __('API key for :name — copy it now, it will not be shown again:', ['name' => $newKey['username']]) }}</strong>
                    <div style="display: flex; gap: 8px; align-items: center; margin-top: 8px; flex-wrap: wrap">
                        <code dir="ltr" style="font-size: 15px; word-break: break-all">{{ $newKey['key'] }}</code>
                        <button type="button" class="btn btn-sm btn-copy" data-copy="{{ $newKey['key'] }}" data-copied="{{ __('Copied') }}">@include('partials.icon', ['name' => 'copy'])<span data-copy-label>{{ __('Copy') }}</span></button>
                    </div>
                </div>
            </div>
        @endif

        @if ($clients->isEmpty())
            <div class="empty"><strong>{{ __('No API clients yet') }}</strong>{{ __('Add one to let another server order your services through your API.') }} <a href="{{ route('admin.api-clients.create') }}">{{ __('Add API client') }}</a></div>
        @else
            <div class="table-wrap">
                <table class="cards">
                    <thead><tr><th>{{ __('Client') }}</th><th>{{ __('Shared services and prices') }}</th><th>{{ __('API key') }}</th><th class="right">{{ __('Orders') }}</th><th>{{ __('Last used') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($clients as $client)
                            <tr>
                                <td class="primary">
                                    <a href="{{ route('admin.customers.show', $client->user_id) }}">{{ $client->user->name }}</a>
                                    <span class="sub mono">{{ $client->user->username }}</span>
                                    @if ($client->note) <span class="sub">{{ $client->note }}</span> @endif
                                    @if ($client->user->status !== 'active') <span class="sub neg">{{ __('Customer account is :status', ['status' => __($client->user->status)]) }}</span> @endif
                                </td>
                                <td data-label="{{ __('Shared services and prices') }}">
                                    @if ($client->sharesAll())
                                        <span class="tag">{{ __('All services') }}</span>
                                    @else
                                        <span class="tag">{{ trans_choice('{0} No services|{1} :count service|[2,*] :count services', $client->services_count, ['count' => $client->services_count]) }}</span>
                                    @endif
                                    @php $pct = (float) $client->price_adjust; @endphp
                                    <span class="sub">
                                        {{ $pct == 0 ? __('Site prices') : ($pct > 0 ? __(':pct% above site prices', ['pct' => amount($pct)]) : __(':pct% discount', ['pct' => amount(abs($pct))])) }}
                                        @if ($client->prices_count) · {{ trans_choice('{1} :count fixed price|[2,*] :count fixed prices', $client->prices_count, ['count' => $client->prices_count]) }} @endif
                                    </span>
                                    @if ($client->ipList()) <span class="sub">{{ __('IP allow-list: :count', ['count' => count($client->ipList())]) }}</span> @endif
                                </td>
                                <td data-label="{{ __('API key') }}"><span class="mono">•••{{ $client->api_key_hint }}</span></td>
                                <td data-label="{{ __('Orders') }}" class="right num">{{ number_format($client->requests_count) }}</td>
                                <td data-label="{{ __('Last used') }}">
                                    {{ $client->last_used_at ? $client->last_used_at->diffForHumans() : __('Never') }}
                                    @if ($client->last_ip) <span class="sub mono">{{ $client->last_ip }}</span> @endif
                                </td>
                                <td data-label="{{ __('Status') }}"><span class="badge badge-{{ $client->isActive() ? 'active' : 'suspended' }}">{{ __(ucfirst($client->status)) }}</span></td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn-sm" href="{{ route('admin.api-clients.edit', $client->id) }}">{{ __('Edit') }}</a>
                                        <form method="post" action="{{ route('admin.api-clients.status', $client->id) }}" data-once
                                              @if ($client->isActive()) data-confirm="{{ __('Suspend API access of :name? Their server can no longer order or check orders until you resume it.', ['name' => $client->user->username]) }}" @endif>
                                            @csrf
                                            <input type="hidden" name="status" value="{{ $client->isActive() ? 'suspended' : 'active' }}">
                                            <button class="btn btn-sm {{ $client->isActive() ? '' : 'btn-success' }}" type="submit">{{ $client->isActive() ? __('Suspend') : __('Resume') }}</button>
                                        </form>
                                        <form method="post" action="{{ route('admin.api-clients.key', $client->id) }}" data-confirm="{{ __('Make a new API key for :name? The current key stops working at once.', ['name' => $client->user->username]) }}">
                                            @csrf
                                            <button class="btn btn-sm" type="submit">{{ __('New key') }}</button>
                                        </form>
                                        <form method="post" action="{{ route('admin.api-clients.destroy', $client->id) }}" data-confirm="{{ __('Remove API access of :name? The key stops working. The customer account, its credit and its orders stay.', ['name' => $client->user->username]) }}">
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
