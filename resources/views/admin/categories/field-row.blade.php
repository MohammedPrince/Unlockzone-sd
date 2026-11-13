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
    <button type="button" class="btn btn-sm btn-danger" data-remove-field style="margin-bottom: 4px">{{ __('Remove') }}</button>
</div>
