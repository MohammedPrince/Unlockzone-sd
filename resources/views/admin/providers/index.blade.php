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
@endsection
