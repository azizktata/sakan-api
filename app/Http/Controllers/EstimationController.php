<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EstimationController extends Controller
{
    private const VALID_TRANSACTION_TYPES = ['vente', 'location'];
    private const VALID_PROPERTY_TYPES    = ['apartment', 'villa', 'house', 'land', 'commercial', 'office'];
    private const VALID_CONDITIONS        = ['neuf', 'bon_etat', 'a_renover'];

    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'city'             => 'required|string|max:64',
            'property_type'    => 'required|string|in:' . implode(',', self::VALID_PROPERTY_TYPES),
            'transaction_type' => 'required|string|in:' . implode(',', self::VALID_TRANSACTION_TYPES),
            'surface'          => 'required|numeric|min:20|max:50000',
            'bedrooms'         => 'sometimes|integer|min:0|max:20',
            'condition'        => 'sometimes|string|in:' . implode(',', self::VALID_CONDITIONS),
            'zone_score'       => 'sometimes|integer|min:1|max:5',
            'amenities_count'  => 'sometimes|integer|min:0|max:10',
            'neighborhood'     => 'sometimes|nullable|string|max:128',
            'governorate'      => 'sometimes|nullable|string|max:64',
            'garden_surface'   => 'sometimes|nullable|numeric|min:0|max:5000',
            'parking_spaces'   => 'sometimes|nullable|integer|min:0|max:20',
            'terrace_surface'  => 'sometimes|nullable|numeric|min:0|max:2000',
            'building_age'     => 'sometimes|nullable|integer|min:0|max:150',
        ]);

        $estimationId = (string) Str::uuid();
        $startMs      = (int) round(microtime(true) * 1000);

        $payload = [
            'city'             => $data['city'],
            'property_type'    => $data['property_type'],
            'transaction_type' => $data['transaction_type'],
            'surface'          => (float) $data['surface'],
            'bedrooms'         => (int) ($data['bedrooms'] ?? 2),
            'condition'        => $data['condition'] ?? 'bon_etat',
            'zone_score'       => (int) ($data['zone_score'] ?? 3),
            'amenities_count'  => (int) ($data['amenities_count'] ?? 0),
            'neighborhood'     => $data['neighborhood'] ?? null,
            'governorate'      => $data['governorate'] ?? null,
            'garden_surface'   => isset($data['garden_surface'])  ? (float) $data['garden_surface']  : null,
            'parking_spaces'   => isset($data['parking_spaces'])  ? (int)   $data['parking_spaces']  : null,
            'terrace_surface'  => isset($data['terrace_surface']) ? (float) $data['terrace_surface'] : null,
            'building_age'     => isset($data['building_age'])    ? (int)   $data['building_age']    : null,
        ];

        $mlUrl = rtrim(config('services.ml.url', 'http://localhost:8001'), '/');

        try {
            $response = Http::timeout(2)
                ->post("{$mlUrl}/predict", $payload);

            if ($response->successful()) {
                $result    = $response->json();
                $latencyMs = (int) round(microtime(true) * 1000) - $startMs;

                $this->log($request, $payload, $result, $result['model_version'] ?? null, $latencyMs, $estimationId);

                return response()->json(array_merge($result, ['estimation_id' => $estimationId]));
            }

            Log::warning('ML service returned non-200', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('ML service unreachable', ['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('ML service unexpected error', ['error' => $e->getMessage()]);
        }

        // Graceful fallback — frontend detects this and uses local heuristic engine
        $latencyMs = (int) round(microtime(true) * 1000) - $startMs;
        $this->log($request, $payload, null, null, $latencyMs, $estimationId);

        return response()->json([
            'fallback'      => true,
            'message'       => 'estimation_heuristic',
            'estimation_id' => $estimationId,
        ]);
    }

    public function feedback(Request $request, string $estimationId): JsonResponse
    {
        $data = $request->validate([
            'opinion' => 'required|in:too_high,correct,too_low',
        ]);

        $updated = \DB::table('estimation_logs')
            ->where('estimation_id', $estimationId)
            ->whereNull('user_opinion')
            ->update([
                'user_opinion' => $data['opinion'],
                'feedback_at'  => now(),
            ]);

        if (! $updated) {
            // Check if row exists at all
            $exists = \DB::table('estimation_logs')
                ->where('estimation_id', $estimationId)
                ->exists();

            if (! $exists) {
                return response()->json(['message' => 'Estimation not found'], 404);
            }
        }

        return response()->json(['success' => true]);
    }

    private function log(Request $request, array $input, ?array $result, ?string $modelVersion, int $latencyMs, string $estimationId): void
    {
        try {
            \DB::table('estimation_logs')->insert([
                'estimation_id' => $estimationId,
                'input'         => json_encode($input),
                'result'        => $result ? json_encode($result) : null,
                'model_version' => $modelVersion,
                'latency_ms'    => $latencyMs,
                'ip'            => $request->ip(),
                'created_at'    => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to write estimation log', ['error' => $e->getMessage()]);
        }
    }
}
