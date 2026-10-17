@foreach($inputs as $input)
@php([$key, $field, $value] = [$input['key'], $input['field'], $input['value']])
@if($field['type'] === 'bool')
<label class="checkbox-label span-2"><input type="hidden" name="meta[{{ $key }}]" value="0"><input type="checkbox" name="meta[{{ $key }}]" value="1" @checked($value)><span>{{ $field['label'] }}</span></label>
@elseif($field['type'] === 'select')
<label>{{ $field['label'] }}<select name="meta[{{ $key }}]" required>@foreach($field['options'] as $optionKey => $optionLabel)<option value="{{ $optionKey }}" @selected($value === $optionKey)>{{ $optionLabel }}</option>@endforeach</select></label>
@elseif($field['type'] === 'date')
<label>{{ $field['label'] }}<input type="date" name="meta[{{ $key }}]" required value="{{ $value }}"></label>
@elseif($field['type'] === 'field')
<label>{{ $field['label'] }}<select name="meta[{{ $key }}]"><option value="">—</option>@foreach($master->fields($value) as $fieldLabel)<option @selected($value === $fieldLabel)>{{ $fieldLabel }}</option>@endforeach</select></label>
@else
<label>{{ $field['label'] }}<input type="number" name="meta[{{ $key }}]" min="{{ $field['min'] }}" max="{{ $field['max'] }}" value="{{ $value }}"></label>
@endif
@endforeach
