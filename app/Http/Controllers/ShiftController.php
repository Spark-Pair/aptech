<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index()
    {
        return view('shifts.index', ['shifts' => Shift::withCount('employees')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        Shift::create($request->validate([
            'name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
        ]) + ['is_active' => true]);
        return back()->with('success', 'Shift created.');
    }

    public function update(Request $request, Shift $shift)
    {
        $shift->update($request->validate([
            'name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'is_active' => 'required|boolean',
        ]));
        return back()->with('success', 'Shift updated.');
    }
}
