<?php

namespace App\Http\Controllers\Modules\Identity;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DoctorSettingController extends Controller
{
    public function edit(Request $request): View
    {
        $doctor = $this->doctor($request);

        return view('doctors.settings', compact('doctor'));
    }

    public function update(Request $request): RedirectResponse
    {
        $doctor = $this->doctor($request);
        $data = $request->validate([
            'chart_language' => ['required', Rule::in(['fa', 'en'])],
            'chronic_summary_font_size' => ['required', 'integer', 'between:8,26'],
            'theme' => ['required', Rule::in(['light', 'dark'])],
        ]);
        $doctor->chart_language = $data['chart_language'];
        $doctor->chronic_summary_font_size = $data['chronic_summary_font_size'];
        $doctor->save();
        $request->user()->update(['theme' => $data['theme']]);

        return back()->with('status', __('ui.saved'));
    }

    private function doctor(Request $request): Doctor
    {
        abort_unless($request->user()->active && $request->user()->hasRole('doctor'), 403);

        return $request->user()->doctor()->where('active', true)->firstOrFail();
    }
}
