<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $employee = $this->route('employee');
        return [
            'empid' => $employee
                ? ['prohibited']
                : ['required','integer','min:1','max:2147483647',Rule::unique('employees','empid')],
            'name'=>'required|string|max:255','email'=>'nullable|email|max:255',
            'username'=>['required','string','max:255',Rule::unique('employees','username')->ignore($employee?->getKey())],
            'password'=>[$employee ? 'nullable' : 'required','string','min:8','max:255'],
            'designation'=>'required|string|max:255','department'=>'required|string|max:255',
            'shift_id'=>'nullable|exists:shifts,id',
            'joining_date'=>'required|date|before_or_equal:today','salary'=>'required|numeric|min:0|max:99999999.99','is_active'=>'required|boolean',
        ];
    }
}
