<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceAgentSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('attendance_sync_agent');
    }

    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'string', 'max:100'],
            'device_identifier' => ['required', 'string', 'max:255'],
            'logs' => ['required', 'array', 'min:1', 'max:1000'],
            'logs.*.id' => ['required', 'integer'],
            'logs.*.timestamp' => ['required', 'date_format:Y-m-d H:i:s'],
            'logs.*.type' => ['required', 'integer', 'in:0,1,4,5'],
        ];
    }
}
