@props(['name', 'label', 'type' => 'text', 'value' => '', 'required' => false])
<div class="form-group {{ $errors->has($name) ? 'has-error' : '' }}">
    <label for="{{ $name }}">{{ $label }} @if($required)<span class="text-danger" aria-hidden="true">*</span>@endif</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}" {{ $attributes->class(['form-control']) }} @required($required) @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif>
    @error($name)<span id="{{ $name }}-error" class="help-block">{{ $message }}</span>@enderror
</div>
