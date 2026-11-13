{{--
    Daily bar chart drawn with plain CSS. Expects $daily: collection of
    ['label', 'sales', 'recharges', 'refunds', 'requests'] rows, one per day.
--}}
@php
    $max = max(1, $daily->max(fn ($d) => max($d['sales'], $d['recharges'], $d['refunds'])));
    $every = max(1, (int) ceil($daily->count() / 8));
@endphp

<div class="legend" style="margin-bottom: 10px">
    <span><i class="dot c-sales"></i> {{ __('Sales') }}</span>
    <span><i class="dot c-recharge"></i> {{ __('Recharges') }}</span>
    <span><i class="dot c-refund"></i> {{ __('Refunds') }}</span>
</div>

<div class="chart" role="img" aria-label="{{ __('Daily sales, recharges and refunds') }}">
    @foreach ($daily as $d)
        <div class="col">
            <span class="bar c-sales" style="height: {{ round($d['sales'] / $max * 100, 1) }}%"></span>
            <span class="bar c-recharge" style="height: {{ round($d['recharges'] / $max * 100, 1) }}%"></span>
            <span class="bar c-refund" style="height: {{ round($d['refunds'] / $max * 100, 1) }}%"></span>
            <div class="tip">
                <b>{{ $d['label'] }}</b><br>
                {{ __('Sales') }}: {{ money($d['sales'], false) }}<br>
                {{ __('Recharges') }}: {{ money($d['recharges'], false) }}<br>
                {{ __('Refunds') }}: {{ money($d['refunds'], false) }}<br>
                {{ __('Requests') }}: {{ $d['requests'] }}
            </div>
        </div>
    @endforeach
</div>

<div class="chart-x" aria-hidden="true">
    @foreach ($daily as $i => $d)
        <span>{{ $i % $every === 0 ? $d['label'] : '' }}</span>
    @endforeach
</div>
