<?php

namespace App\Http\Middleware;

use App\Models\AttendanceSyncAgent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAttendanceAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! is_string($token) || strlen($token) < 32) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $agent = AttendanceSyncAgent::where('token_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->first();

        if (! $agent) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set('attendance_sync_agent', $agent);

        return $next($request);
    }
}
