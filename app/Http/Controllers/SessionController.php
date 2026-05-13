<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'visitor_key' => ['required', 'uuid'],
            'entry_page'  => ['nullable', 'string', 'max:255'],
            'device'      => ['nullable', 'in:mobile,desktop,tablet,unknown'],
        ]);

        $visitorKey = $request->input('visitor_key');

        // Return existing active session (last seen within 30 minutes)
        $existing = DB::table('user_sessions')
            ->where('visitor_key', $visitorKey)
            ->whereNull('ended_at')
            ->where('last_seen_at', '>=', now()->subMinutes(30))
            ->orderByDesc('started_at')
            ->value('session_token');

        if ($existing) {
            return response()->json(['session_token' => $existing]);
        }

        $sessionToken = Str::uuid()->toString();

        DB::table('user_sessions')->insert([
            'session_token' => $sessionToken,
            'visitor_key'   => $visitorKey,
            'user_id'       => $request->user()?->id,
            'started_at'    => now(),
            'last_seen_at'  => now(),
            'entry_page'    => $request->input('entry_page'),
            'device'        => $request->input('device', 'unknown'),
        ]);

        return response()->json(['session_token' => $sessionToken], 201);
    }

    public function ping(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => ['required', 'uuid'],
        ]);

        DB::table('user_sessions')
            ->where('session_token', $request->input('session_token'))
            ->whereNull('ended_at')
            ->update([
                'last_seen_at' => now(),
                'page_count'   => DB::raw('page_count + 1'),
            ]);

        return response()->json(['ok' => true]);
    }

    public function end(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => ['required', 'uuid'],
        ]);

        $session = DB::table('user_sessions')
            ->where('session_token', $request->input('session_token'))
            ->whereNull('ended_at')
            ->first();

        if (!$session) {
            return response()->json(['ok' => true]); // Already ended or not found — idempotent
        }

        $startedAt = \Carbon\Carbon::parse($session->started_at);
        $duration  = max(0, $startedAt->diffInSeconds(now(), false));

        DB::table('user_sessions')
            ->where('session_token', $request->input('session_token'))
            ->update([
                'ended_at'         => now(),
                'duration_seconds' => $duration,
            ]);

        return response()->json(['ok' => true]);
    }
}
