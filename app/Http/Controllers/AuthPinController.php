<?php

namespace App\Http\Controllers;

use App\Models\HotelSetting;
use App\Models\StaffUser;
use App\Services\PosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthPinController extends Controller
{
    public function root(Request $request): RedirectResponse
    {
        $staffId = $request->session()->get('pos_staff_id');
        $staff = $staffId ? StaffUser::query()->where('is_active', true)->find($staffId) : null;

        if ($staff) {
            return redirect($staff->homeRoute());
        }

        return redirect()->route('pos.login');
    }

    public function showLogin(Request $request): View|RedirectResponse
    {
        $staffId = $request->session()->get('pos_staff_id');
        $staff = $staffId ? StaffUser::query()->where('is_active', true)->find($staffId) : null;
        if ($staff) {
            return redirect($staff->homeRoute());
        }

        $settings = HotelSetting::allAsMap();
        $activeStaff = StaffUser::query()->where('is_active', true)->orderBy('id')->get();

        return view('auth.login', [
            'settings' => $settings,
            'activeStaff' => $activeStaff,
        ]);
    }

    public function login(Request $request, PosService $pos): RedirectResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string', 'max:10'],
        ]);

        $staff = StaffUser::query()
            ->where('pin_code', trim($validated['pin']))
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            return back()->withErrors(['pin' => 'Invalid Staff PIN. Please try again.']);
        }

        $request->session()->put('pos_staff_id', $staff->id);
        $request->session()->regenerate();

        $pos->logAudit($staff, 'staff_login', 'staff', $staff->id, ['role' => $staff->role]);

        return redirect($staff->homeRoute())->with('status', "Welcome, {$staff->name}!");
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('pos_staff_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('pos.login')->with('status', 'Logged out safely.');
    }
}
