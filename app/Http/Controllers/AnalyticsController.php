<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\GeoIpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnalyticsController extends Controller
{
    private const DEVICE_PATTERNS = [
        'mobile'  => '/(android|iphone|ipod|blackberry|windows phone|mobile)/i',
        'tablet'  => '/(ipad|android(?!.*mobile)|tablet)/i',
        'desktop' => '/(windows|macintosh|linux|x11)/i',
    ];

    // ── Public: track a property view ────────────────────────────────────────

    public function trackView(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => 'required|integer|exists:properties,id',
            'visitor_key' => 'sometimes|nullable|uuid',
            'source'      => 'required|in:direct,listing,map',
        ]);

        $visitorKey    = $data['visitor_key'] ?? null;
        $isNewVisitor  = empty($visitorKey);

        if ($isNewVisitor) {
            $visitorKey = (string) Str::uuid();
        }

        $ua            = $request->userAgent() ?? '';
        $device        = $this->deriveDevice($ua);
        $sessionBucket = now()->format('Y-m-d H:00');
        $uniqueKey     = hash('sha256', $data['property_id'] . $visitorKey . $sessionBucket);
        $ipHash        = hash('sha256', $request->ip() ?? '');
        $uaHash        = $ua ? hash('sha256', $ua) : null;
        $viewId        = Str::uuid()->toString();
        $geo           = GeoIpService::lookup($request->ip() ?? '');

        $inserted = DB::table('property_views')->insertOrIgnore([
            'property_id'      => $data['property_id'],
            'user_id'          => $request->user()?->id,
            'visitor_key'      => $visitorKey,
            'session_bucket'   => $sessionBucket,
            'unique_key'       => $uniqueKey,
            'source'           => $data['source'],
            'device'           => $device,
            'user_agent_hash'  => $uaHash,
            'ip_hash'          => $ipHash,
            'view_id'          => $viewId,
            'country'          => $geo['country'],
            'city_geo'         => $geo['city'],
            'created_at'       => now(),
        ]);

        if (! $inserted) {
            $existingViewId = DB::table('property_views')
                ->where('unique_key', $uniqueKey)
                ->value('view_id');

            $response = response()->json([
                'tracked'     => false,
                'visitor_key' => $visitorKey,
                'view_id'     => $existingViewId,
            ], 200);

            if ($isNewVisitor) {
                $response->cookie('visitor_key', $visitorKey, 525600, '/', null, false, false, false, 'lax');
            }

            return $response;
        }

        $response = response()->json([
            'tracked'     => true,
            'visitor_key' => $visitorKey,
            'view_id'     => $viewId,
        ], 201);

        if ($isNewVisitor) {
            $response->cookie('visitor_key', $visitorKey, 525600, '/', null, false, false, false, 'lax');
        }

        return $response;
    }

    // ── Authenticated: owner KPIs ─────────────────────────────────────────────

    public function propertyStats(Request $request, int $id): JsonResponse
    {
        $property = $request->user()->properties()->findOrFail($id);
        $since30  = now()->subDays(30)->startOfDay();

        // Try aggregated data first; fall back to live query if no rows yet
        $hasAggregated = DB::table('property_stats_daily')
            ->where('property_id', $id)
            ->exists();

        if ($hasAggregated) {
            $agg = DB::table('property_stats_daily')
                ->where('property_id', $id)
                ->selectRaw('
                    SUM(views_total)   AS total_views,
                    SUM(views_unique)  AS unique_views,
                    SUM(contacts_count) AS total_contacts,
                    CASE WHEN SUM(views_unique) > 0
                         THEN ROUND(SUM(contacts_count) / SUM(views_unique) * 100, 2)
                         ELSE 0 END AS conversion_rate
                ')
                ->first();

            $periodStats = DB::table('property_stats_daily')
                ->where('property_id', $id)
                ->where('date', '>=', now()->subDays(30)->toDateString())
                ->orderBy('date')
                ->get(['date', 'views_total as views', 'views_unique']);
        } else {
            $agg = DB::table('property_views')
                ->where('property_id', $id)
                ->selectRaw('
                    COUNT(*) AS total_views,
                    COUNT(DISTINCT visitor_key) AS unique_views
                ')
                ->first();

            $contacts = DB::table('contacts')
                ->where('property_id', $id)
                ->count();

            $agg->total_contacts  = $contacts;
            $agg->conversion_rate = $agg->unique_views > 0
                ? round($contacts / $agg->unique_views * 100, 2)
                : 0;

            $periodStats = collect();
        }

        $avgDuration = DB::table('property_views')
            ->where('property_id', $property->id)
            ->where('created_at', '>=', $since30)
            ->whereNotNull('duration_seconds')
            ->avg('duration_seconds');

        $topCountries = DB::table('property_views')
            ->where('property_id', $property->id)
            ->where('created_at', '>=', $since30)
            ->whereNotNull('country')
            ->select('country')
            ->selectRaw('COUNT(*) as views')
            ->groupBy('country')
            ->orderByDesc('views')
            ->limit(3)
            ->get();

        return response()->json([
            'total_views'          => (int) $agg->total_views,
            'unique_views'         => (int) $agg->unique_views,
            'total_contacts'       => (int) $agg->total_contacts,
            'conversion_rate'      => (float) $agg->conversion_rate,
            'period_stats'         => $periodStats,
            'avg_duration_seconds' => $avgDuration ? round($avgDuration, 1) : null,
            'top_countries'        => $topCountries,
        ]);
    }

    public function propertyTrend(Request $request, int $id): JsonResponse
    {
        $request->user()->properties()->findOrFail($id);

        $days    = (int) $request->query('days', 7);
        $days    = in_array($days, [7, 30]) ? $days : 7;
        $start   = now()->subDays($days - 1)->startOfDay();

        // Build a date series and left-join with aggregated stats
        $rows = DB::table('property_stats_daily')
            ->where('property_id', $id)
            ->where('date', '>=', $start->toDateString())
            ->orderBy('date')
            ->get(['date', 'views_total as views', 'views_unique as unique_views'])
            ->keyBy('date');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date        = $start->copy()->addDays($i)->toDateString();
            $row         = $rows->get($date);
            $series[]    = [
                'date'         => $date,
                'views'        => $row ? (int) $row->views : 0,
                'unique_views' => $row ? (int) $row->unique_views : 0,
            ];
        }

        return response()->json($series);
    }

    public function ownerSummary(Request $request): JsonResponse
    {
        $userId      = $request->user()->id;
        $since30     = now()->subDays(30)->startOfDay();
        $propertyIds = Property::where('user_id', $userId)->pluck('id');

        if ($propertyIds->isEmpty()) {
            return response()->json([
                'total_views'          => 0,
                'total_unique_views'   => 0,
                'total_contacts'       => 0,
                'avg_conversion_rate'  => 0.0,
                'top_property'         => null,
                'avg_duration_seconds' => null,
                'top_countries'        => [],
            ]);
        }

        $agg = DB::table('property_stats_daily')
            ->whereIn('property_id', $propertyIds)
            ->selectRaw('
                SUM(views_total)    AS total_views,
                SUM(views_unique)   AS total_unique_views,
                SUM(contacts_count) AS total_contacts,
                CASE WHEN SUM(views_unique) > 0
                     THEN ROUND(SUM(contacts_count) / SUM(views_unique) * 100, 2)
                     ELSE 0 END AS avg_conversion_rate
            ')
            ->first();

        // If no aggregated data, fall back to live counts
        if (! $agg || $agg->total_views === null) {
            $agg             = (object) [];
            $agg->total_views         = DB::table('property_views')->whereIn('property_id', $propertyIds)->count();
            $agg->total_unique_views  = DB::table('property_views')->whereIn('property_id', $propertyIds)->distinct('visitor_key')->count('visitor_key');
            $agg->total_contacts      = DB::table('contacts')->whereIn('property_id', $propertyIds)->count();
            $agg->avg_conversion_rate = $agg->total_unique_views > 0
                ? round($agg->total_contacts / $agg->total_unique_views * 100, 2)
                : 0;
        }

        $topRow = DB::table('property_stats_daily')
            ->whereIn('property_id', $propertyIds)
            ->select('property_id', DB::raw('SUM(views_total) as total'))
            ->groupBy('property_id')
            ->orderByDesc('total')
            ->first();

        $topProperty = null;
        if ($topRow) {
            $prop = Property::find($topRow->property_id, ['id', 'title']);
            if ($prop) {
                $topProperty = ['id' => $prop->id, 'title' => $prop->title, 'views' => (int) $topRow->total];
            }
        }

        $avgDuration = DB::table('property_views')
            ->whereIn('property_id', $propertyIds)
            ->where('created_at', '>=', $since30)
            ->whereNotNull('duration_seconds')
            ->avg('duration_seconds');

        $topCountries = DB::table('property_views')
            ->whereIn('property_id', $propertyIds)
            ->where('created_at', '>=', $since30)
            ->whereNotNull('country')
            ->select('country')
            ->selectRaw('COUNT(*) as views')
            ->groupBy('country')
            ->orderByDesc('views')
            ->limit(3)
            ->get();

        return response()->json([
            'total_views'          => (int) $agg->total_views,
            'total_unique_views'   => (int) $agg->total_unique_views,
            'total_contacts'       => (int) $agg->total_contacts,
            'avg_conversion_rate'  => (float) $agg->avg_conversion_rate,
            'top_property'         => $topProperty,
            'avg_duration_seconds' => $avgDuration ? round($avgDuration, 1) : null,
            'top_countries'        => $topCountries,
        ]);
    }

    // ── Public: update view duration (first-write-wins) ──────────────────────

    public function updateDuration(Request $request, string $view_id): JsonResponse
    {
        $request->validate([
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:86400'],
        ]);

        $updated = DB::table('property_views')
            ->where('view_id', $view_id)
            ->whereNull('duration_seconds')
            ->update(['duration_seconds' => $request->integer('duration_seconds')]);

        if ($updated === 0) {
            // Either view_id not found or already has a duration (first-write-wins)
            $exists = DB::table('property_views')->where('view_id', $view_id)->exists();
            if (!$exists) {
                return response()->json(['error' => 'View not found'], 404);
            }
        }

        return response()->json(['updated' => $updated > 0]);
    }

    // ── Public: track a search event ─────────────────────────────────────────

    public function trackSearch(Request $request): JsonResponse
    {
        $request->validate([
            'search_id'     => ['required', 'uuid'],
            'filters'       => ['present', 'array'],
            'results_count' => ['required', 'integer', 'min:0'],
            'visitor_key'   => ['nullable', 'uuid'],
        ]);

        $locationId = isset($request->filters['location_id']) && $request->filters['location_id']
            ? (int) $request->filters['location_id']
            : null;

        $userId = $request->user()?->id;

        DB::table('searches')->insertOrIgnore([
            'search_id'     => $request->input('search_id'),
            'visitor_key'   => $request->input('visitor_key'),
            'user_id'       => $userId,
            'session_token' => $request->input('session_token'),
            'filters'       => json_encode($request->input('filters')),
            'results_count' => $request->integer('results_count'),
            'location_id'   => $locationId,
            'created_at'    => now(),
        ]);

        return response()->json(['tracked' => true]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function deriveDevice(string $ua): string
    {
        if (preg_match(self::DEVICE_PATTERNS['mobile'], $ua)) {
            return 'mobile';
        }
        if (preg_match(self::DEVICE_PATTERNS['tablet'], $ua)) {
            return 'tablet';
        }
        if (preg_match(self::DEVICE_PATTERNS['desktop'], $ua)) {
            return 'desktop';
        }
        return 'unknown';
    }
}
