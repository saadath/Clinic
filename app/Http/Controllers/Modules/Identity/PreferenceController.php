<?php

namespace App\Http\Controllers\Modules\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Identity\PreferenceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class PreferenceController extends Controller
{
    public function update(PreferenceRequest $request): RedirectResponse|JsonResponse
    {
        $preferences = $request->safe()->only(['locale', 'theme']);

        if ($request->user()) {
            $request->user()->update($preferences);
        }

        $request->session()->put($preferences);
        app()->setLocale($preferences['locale']);

        if ($request->expectsJson()) {
            return response()->json([
                ...$preferences,
                'messages' => trans('ui'),
                'staffMessages' => trans('staff'),
            ]);
        }

        return back()->with('status', __('ui.saved'));
    }
}
