<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['month' => 'nullable|date_format:Y-m', 'search' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:255', 'empid' => 'nullable|integer|exists:employees,empid',
            'status' => 'nullable|in:Present,Absent,Off Day,Leave'];
    }

    public function month(): string
    {
        return $this->validated('month') ?: now()->format('Y-m');
    }
}
