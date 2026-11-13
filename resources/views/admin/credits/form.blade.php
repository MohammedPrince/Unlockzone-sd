@extends('layouts.panel')

@section('title', __('Recharge credit'))
@section('eyebrow', __('Credit management'))
@section('page-title', __('Recharge or adjust credit'))
@section('page-description', __('Add credit after a customer pays, or correct a balance. Each save writes one permanent ledger entry.'))

@section('page-actions')
    <a class="btn" href="{{ route('admin.credits.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Ledger') }}</a>
@endsection

@section('content')
    <div class="grid grid-side">
        <form method="post" action="{{ route('admin.credits.store') }}" class="card" data-once
              data-confirm="{{ __('Record this credit operation? Ledger entries cannot be edited or deleted afterwards.') }}">
            @csrf
            <div class="card-head"><h2>{{ __('Credit operation') }}</h2></div>
            <div class="card-body form-grid">
                <div class="full">
                    <label for="user_id">{{ __('Customer') }}</label>
                    <select id="user_id" name="user_id" required>
                        <option value="">{{ __('Select a customer') }}</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}" @selected((int) old('user_id', $selected) === $c->id)>{{ $c->name }} ({{ '@' . $c->username }})</option>
                        @endforeach
                    </select>
                    @error('user_id') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="credit-operation">{{ __('Operation') }}</label>
                    <select id="credit-operation" name="operation" required>
                        <option value="recharge" @selected(old('operation', 'recharge') === 'recharge')>{{ __('Recharge (add credit)') }}</option>
                        <option value="adjustment" @selected(old('operation') === 'adjustment')>{{ __('Adjustment (correct a balance)') }}</option>
                    </select>
                </div>
                <div>
                    <label for="credit-amount">{{ __('Amount') }} ($)</label>
                    <input type="number" id="credit-amount" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" max="1000000" required inputmode="decimal" placeholder="0">
                    <p class="help" data-op="recharge">{{ __('Must be greater than zero.') }}</p>
                    <p class="help" data-op="adjustment" hidden>{{ __('Use a negative number to take credit back, e.g. -5. The balance cannot go below zero.') }}</p>
                    @error('amount') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="full">
                    <label for="reference">{{ __('Payment reference') }} <span class="muted">{{ __('(optional)') }}</span></label>
                    <input type="text" id="reference" name="reference" value="{{ old('reference') }}" maxlength="60" placeholder="{{ __('Bank transfer no., receipt no...') }}">
                    <p class="help">{{ __('Left blank, a reference is generated for you.') }}</p>
                </div>
                <div class="full">
                    <label for="credit-note"><span data-op="recharge">{{ __('Note') }} <span class="muted">{{ __('(optional)') }}</span></span><span data-op="adjustment" hidden>{{ __('Reason (required)') }}</span></label>
                    <textarea id="credit-note" name="note" maxlength="255" placeholder="{{ __('Shown to the customer in their credit history') }}">{{ old('note') }}</textarea>
                    @error('note') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="full form-actions">
                    <a class="btn" href="{{ route('admin.credits.index') }}">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Record operation') }}</button>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-head"><h2>{{ __('Good to know') }}</h2></div>
            <div class="card-body stack muted">
                <p><b style="color: var(--text)">{{ __('Recharge') }}</b> {{ __('is for money the customer paid you. It always adds credit.') }}</p>
                <p><b style="color: var(--text)">{{ __('Adjustment') }}</b> {{ __('is for corrections, bonuses, or taking back credit added by mistake. A reason is required and is saved in the audit log with your name.') }}</p>
                <p>{{ __('Refunds for failed or cancelled requests happen automatically. You do not need to add them here.') }}</p>
            </div>
        </div>
    </div>
@endsection
