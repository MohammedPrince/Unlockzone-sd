{{--
    The customer's orders table, as on a Dhru "Server Orders" page: ID, status, service, credit, code, fields.
    It scrolls sideways on phones instead of squeezing the columns.
--}}
<div class="table-wrap orders-wrap">
    <table class="orders-table">
        <thead>
            <tr>
                <th>#{{ __('ID') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Service') }}</th>
                <th class="right">{{ __('Credit') }}</th>
                <th>{{ __('Code') }}</th>
                <th>{{ __('Fields') }}</th>
                <th>{{ __('Date') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($requests as $r)
                @php
                    $code = $r->status === 'completed' ? ($r->response_data['result'] ?? '') : ($r->status === 'failed' ? ($r->error_message ?? '') : '');
                @endphp
                <tr>
                    <td class="num"><a href="{{ route('customer.requests.show', $r->id) }}">{{ $r->id }}</a></td>
                    <td><span class="badge badge-{{ $r->status }}">{{ __(ucfirst($r->status)) }}</span></td>
                    <td class="o-service"><a href="{{ route('customer.requests.show', $r->id) }}">{{ $r->service->name ?? __('Service') }}</a></td>
                    <td class="right num">{{ amount($r->amount) }}@if ($r->refunded_at)<span class="sub">{{ __('refunded') }}</span>@endif</td>
                    <td class="o-code" dir="auto">{{ $code !== '' ? $code : '-' }}</td>
                    <td class="o-fields" dir="ltr">
                        @forelse ($r->request_data ?? [] as $item)
                            <div>{{ $item['label'] ?? __('Field') }}=&gt;{{ $item['value'] ?? '' }}</div>
                        @empty
                            -
                        @endforelse
                    </td>
                    <td class="nowrap muted">{{ $r->created_at->translatedFormat('Y-m-d H:i') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
