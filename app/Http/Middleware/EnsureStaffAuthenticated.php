<?php

namespace App\Http\Middleware;

use App\Models\StaffUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $staffId = $request->session()->get('pos_staff_id');
        $staff = $staffId ? StaffUser::query()->where('is_active', true)->find($staffId) : null;

        if (! $staff) {
            $request->session()->forget('pos_staff_id');
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Staff PIN login required.'], 401);
            }

            return redirect()->route('pos.login');
        }

        if (! empty($roles) && ! $staff->canAccess(...$roles)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Access denied for role: '.$staff->role], 403);
            }

            return redirect($staff->homeRoute())->with('error', 'You do not have permission to open that screen.');
        }

        $request->attributes->set('staff', $staff);
        View::share('currentStaff', $staff);

        return $next($request);
    }
}
