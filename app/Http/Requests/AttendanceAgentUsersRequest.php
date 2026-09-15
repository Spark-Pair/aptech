<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceAgentUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('attendance_sync_agent');
    }

    public function rules(): array
    {
        return [
            // A device snapshot can legitimately contain no users (for example while the
            // reader is reconnecting). The field must still be present, but an empty array
            // must not terminate the continuously running Local Agent with HTTP 422.
            'device_identifier' => ['required', 'string', 'max:255'],
            'users' => ['present', 'array', 'max:1000'],
            'users.*.userid' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'users.*.name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
