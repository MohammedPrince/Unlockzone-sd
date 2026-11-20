{{-- One input-field row in the category form. $i is the row index, $field holds its current values. --}}
<div class="field-row">
    <input type="hidden" name="fields[{{ $i }}][id]" value="{{ $field['id'] ?? '' }}">
    <div>
        <label>{{ __('Field label') }}</label>
        <input type="text" name="fields[{{ $i }}][label]" value="{{ $field['label'] ?? '' }}" required maxlength="100" placeholder="{{ __('e.g. IMEI') }}">
    </div>
    <div>
        <label>{{ __('Input type') }}</label>
        <select name="fields[{{ $i }}][input_type]" required>
            @foreach ($types as $key => $type)
                <option value="{{ $key }}" @selected(($field['input_type'] ?? 'imei') === $key)>{{ __($type['label']) }}</option>
            @endforeach
        </select>
    </div>
    <div class="wide">
        <label>{{ __('Placeholder') }} <span class="muted">({{ __('optional') }})</span></label>
        <input type="text" name="fields[{{ $i }}][placeholder]" value="{{ $field['placeholder'] ?? '' }}" maxlength="150" placeholder="{{ __('Hint shown inside the box') }}">
    </div>
    <label class="check" style="padding-bottom: 9px">
        <input type="hidden" name="fields[{{ $i }}][is_required]" value="0">
        <input type="checkbox" name="fields[{{ $i }}][is_required]" value="1" @checked($field['is_required'] ?? true)> {{ __('Required') }}
    </label>
    @php $used = (int) ($used ?? 0); @endphp
    <span class="field-used">
        <button type="button" class="btn btn-sm btn-danger" data-remove-field style="margin-bottom: 4px"
                @if ($used > 0) data-confirm-remove="{{ trans_choice('{1} Remove ":label"? 1 service uses it; it is removed from that service too when you save.|[2,*] Remove ":label"? :count services use it; it is removed from all of them too when you save.', $used, ['count' => $used, 'label' => $field['label'] ?? '']) }}" @endif>{{ __('Remove') }}</button>
        @if ($used > 0) <span class="muted">{{ trans_choice('{1} Used by 1 service|[2,*] Used by :count services', $used, ['count' => $used]) }}</span> @endif
    </span>
</div>
