<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    public function overview(): JsonResponse
    {
        $since30 = now()->subDays(30)->startOfDay();

        $views = DB::table('property_views')
            ->where('created_at', '>=', $since30)
            ->count();

        $contacts = DB::table('contacts')
            ->where('created_at', '>=', $since30)
            ->count();

        $newUsers = DB::table('users')
            ->where('created_at', '>=', $since30)
            ->count();

        $publishedProperties = DB::table('properties')
            ->where('status', 'published')
            ->count();

        $conversionRate = $views > 0 ? round($contacts / $views * 100, 2) : 0;

        return response()->json([
            'total_views'          => $views,
            'total_contacts'       => $contacts,
            'new_users'            => $newUsers,
            'published_properties' => $publishedProperties,
            'conversion_rate'      => $conversionRate,
            'period_days'          => 30,
        ]);
    }

    public function topProperties(): JsonResponse
    {
        $since30 = now()->subDays(30)->startOfDay();

        $rows = DB::table('property_views as pv')
            ->join('properties as p', 'pv.property_id', '=', 'p.id')
            ->where('pv.created_at', '>=', $since30)
            ->select('p.id', 'p.title', 'p.price', 'p.transaction_type', 'p.property_type', 'p.status')
            ->selectRaw('COUNT(pv.id) as views_total')
            ->selectRaw('COUNT(DISTINCT pv.visitor_key) as views_unique')
            ->groupBy('p.id', 'p.title', 'p.price', 'p.transaction_type', 'p.property_type', 'p.status')
            ->orderByDesc('views_total')
            ->limit(10)
            ->get();

        return response()->json($rows);
    }

    public function topCities(): JsonResponse
    {
        $since30 = now()->subDays(30)->startOfDay();

        $rows = DB::table('property_views as pv')
            ->join('properties as p', 'pv.property_id', '=', 'p.id')
            ->join('locations as l', 'p.location_id', '=', 'l.id')
            ->where('pv.created_at', '>=', $since30)
            ->select('l.id', 'l.name', 'l.slug')
            ->selectRaw('COUNT(pv.id) as views_total')
            ->selectRaw('COUNT(DISTINCT pv.visitor_key) as views_unique')
            ->groupBy('l.id', 'l.name', 'l.slug')
            ->orderByDesc('views_total')
            ->limit(20)
            ->get();

        // Enrich with contacts + published count from live tables
        $locationIds = $rows->pluck('id');

        $contactCounts = DB::table('contacts as c')
            ->join('properties as p', 'c.property_id', '=', 'p.id')
            ->whereIn('p.location_id', $locationIds)
            ->where('c.created_at', '>=', $since30)
            ->select('p.location_id')
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('p.location_id')
            ->pluck('cnt', 'p.location_id');

        $publishedCounts = DB::table('properties')
            ->whereIn('location_id', $locationIds)
            ->where('status', 'published')
            ->select('location_id')
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('location_id')
            ->pluck('cnt', 'location_id');

        $rows = $rows->map(function ($row) use ($contactCounts, $publishedCounts) {
            $row->contacts_count       = (int) ($contactCounts[$row->id] ?? 0);
            $row->properties_published = (int) ($publishedCounts[$row->id] ?? 0);
            $row->demand_supply_ratio  = $row->properties_published > 0
                ? round($row->views_total / $row->properties_published, 2)
                : 0;
            return $row;
        });

        return response()->json($rows);
    }

    public function conversionFunnel(): JsonResponse
    {
        $since30 = now()->subDays(30)->startOfDay();

        $views = DB::table('property_views')
            ->where('created_at', '>=', $since30)
            ->count();

        $contacts = DB::table('contacts')
            ->where('created_at', '>=', $since30)
            ->count();

        $closed = DB::table('properties')
            ->whereIn('status', ['sold', 'rented'])
            ->where('updated_at', '>=', $since30)
            ->count();

        return response()->json([
            'views'            => $views,
            'contacts'         => $contacts,
            'closed'           => $closed,
            'view_to_contact'  => $views > 0 ? round($contacts / $views * 100, 2) : 0,
            'contact_to_close' => $contacts > 0 ? round($closed / $contacts * 100, 2) : 0,
            'period_days'      => 30,
        ]);
    }

    public function estimationDataset(Request $request): JsonResponse
    {
        $rows = DB::table('estimation_logs')
            ->whereNotNull('user_opinion')
            ->orderByDesc('created_at')
            ->paginate(50, [
                'estimation_id',
                'input',
                'result',
                'user_opinion',
                'model_version',
                'latency_ms',
                'created_at',
            ]);

        // Parse JSON columns so response is structured, not a string
        $rows->getCollection()->transform(function ($row) {
            $row->input  = json_decode($row->input, true);
            $row->result = $row->result ? json_decode($row->result, true) : null;
            return $row;
        });

        return response()->json($rows);
    }

    public function marketInsights(): JsonResponse
    {
        $since30 = now()->subDays(30)->startOfDay();

        // Build live market insights from raw tables — no cron dependency
        $viewsByLocation = DB::table('property_views as pv')
            ->join('properties as p', 'pv.property_id', '=', 'p.id')
            ->where('pv.created_at', '>=', $since30)
            ->whereNotNull('p.location_id')
            ->select('p.location_id')
            ->selectRaw('COUNT(pv.id) as views_total')
            ->groupBy('p.location_id')
            ->pluck('views_total', 'p.location_id');

        $searchesByLocation = DB::table('searches')
            ->where('created_at', '>=', $since30)
            ->whereNotNull('location_id')
            ->select('location_id')
            ->selectRaw('COUNT(*) as searches_count')
            ->selectRaw('SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END) as searches_zero_results')
            ->groupBy('location_id')
            ->get()
            ->keyBy('location_id');

        $publishedByLocation = DB::table('properties')
            ->where('status', 'published')
            ->whereNotNull('location_id')
            ->select('location_id')
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw('AVG(price) as avg_price')
            ->groupBy('location_id')
            ->get()
            ->keyBy('location_id');

        $locationIds = collect($viewsByLocation->keys())
            ->merge($searchesByLocation->keys())
            ->unique();

        $locations = DB::table('locations')
            ->whereIn('id', $locationIds)
            ->get(['id', 'name', 'slug'])
            ->keyBy('id');

        $rows = $locationIds->map(function ($locId) use ($viewsByLocation, $searchesByLocation, $publishedByLocation, $locations) {
            $loc      = $locations->get($locId);
            if (! $loc) return null;

            $views      = (int) ($viewsByLocation[$locId] ?? 0);
            $searches   = $searchesByLocation->get($locId);
            $published  = $publishedByLocation->get($locId);

            $searchCount      = $searches ? (int) $searches->searches_count : 0;
            $zeroResults      = $searches ? (int) $searches->searches_zero_results : 0;
            $publishedCount   = $published ? (int) $published->cnt : 0;
            $avgPrice         = $published ? round((float) $published->avg_price, 0) : null;

            $demandIndex          = min(100, round($searchCount * 0.6 + $views * 0.4, 1));
            $attractiveness       = $avgPrice && $publishedCount > 0 ? min(100, round($views / max($publishedCount, 1) * 10, 1)) : 0;
            $liquidity            = $publishedCount > 0 ? min(100, round($views / $publishedCount, 1)) : 0;
            $searchGapIndex       = $searchCount > 0 ? round($zeroResults / $searchCount * 100, 1) : 0;

            return (object) [
                'id'                     => $loc->id,
                'name'                   => $loc->name,
                'slug'                   => $loc->slug,
                'searches_count'         => $searchCount,
                'searches_zero_results'  => $zeroResults,
                'views_total'            => $views,
                'properties_published'   => $publishedCount,
                'avg_price'              => $avgPrice,
                'demand_index'           => $demandIndex,
                'attractiveness_score'   => $attractiveness,
                'liquidity_index'        => $liquidity,
                'search_gap_index'       => $searchGapIndex,
            ];
        })->filter()->sortByDesc('demand_index')->values()->take(20);

        return response()->json($rows);
    }

    public function searchTrends(): JsonResponse
    {
        $since30 = now()->subDays(30)->startOfDay();

        // Total searches in period
        $totalSearches = DB::table('searches')
            ->where('created_at', '>=', $since30)
            ->count();

        // Top 15 most common filter combinations (location + transaction_type + property_type)
        // Join locations to resolve name immediately
        $topFilters = DB::table('searches as s')
            ->leftJoin('locations as l', 's.location_id', '=', 'l.id')
            ->where('s.created_at', '>=', $since30)
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(s.filters, '$.transaction_type')) as transaction_type")
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(s.filters, '$.property_type')) as property_type")
            ->selectRaw('s.location_id')
            ->selectRaw('l.name as location_name')
            ->selectRaw('COUNT(*) as search_count')
            ->selectRaw('SUM(CASE WHEN s.results_count = 0 THEN 1 ELSE 0 END) as zero_result_count')
            ->groupBy('transaction_type', 'property_type', 's.location_id', 'l.name')
            ->orderByDesc('search_count')
            ->limit(15)
            ->get()
            ->map(function ($row) {
                $row->zero_result_pct = $row->search_count > 0
                    ? round($row->zero_result_count / $row->search_count * 100, 0)
                    : 0;
                return $row;
            });

        // Zero-results by location — only named locations (skip null = no city selected)
        // Also include total searches for that city to compute failure rate
        $zeroResultsByLocation = DB::table('searches as s')
            ->join('locations as l', 's.location_id', '=', 'l.id')
            ->where('s.created_at', '>=', $since30)
            ->where('s.results_count', 0)
            ->select('l.id', 'l.name')
            ->selectRaw('COUNT(*) as zero_result_searches')
            ->groupBy('l.id', 'l.name')
            ->orderByDesc('zero_result_searches')
            ->limit(10)
            ->get();

        // For each zero-result city, get total searches to compute failure rate
        $cityIds = $zeroResultsByLocation->pluck('id')->filter()->values();
        $totalByCity = DB::table('searches')
            ->where('created_at', '>=', $since30)
            ->whereIn('location_id', $cityIds)
            ->select('location_id')
            ->selectRaw('COUNT(*) as total_searches')
            ->groupBy('location_id')
            ->pluck('total_searches', 'location_id');

        $zeroResultsByLocation = $zeroResultsByLocation->map(function ($row) use ($totalByCity) {
            $total = $totalByCity[$row->id] ?? $row->zero_result_searches;
            $row->total_searches = (int) $total;
            $row->failure_rate   = $total > 0
                ? round($row->zero_result_searches / $total * 100, 0)
                : 100;
            return $row;
        });

        // Top property types searched (for summary insight)
        $topTypes = DB::table('searches')
            ->where('created_at', '>=', $since30)
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(filters, '$.property_type')) as property_type")
            ->selectRaw('COUNT(*) as search_count')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(filters, '$.property_type')) IS NOT NULL")
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(filters, '$.property_type')) != 'null'")
            ->groupBy('property_type')
            ->orderByDesc('search_count')
            ->limit(5)
            ->get();

        // Top cities searched (by search volume)
        $topCities = DB::table('searches as s')
            ->join('locations as l', 's.location_id', '=', 'l.id')
            ->where('s.created_at', '>=', $since30)
            ->select('l.id', 'l.name')
            ->selectRaw('COUNT(*) as search_count')
            ->selectRaw('SUM(CASE WHEN s.results_count = 0 THEN 1 ELSE 0 END) as zero_result_count')
            ->groupBy('l.id', 'l.name')
            ->orderByDesc('search_count')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $row->zero_result_pct = $row->search_count > 0
                    ? round($row->zero_result_count / $row->search_count * 100, 0)
                    : 0;
                return $row;
            });

        return response()->json([
            'top_filters'              => $topFilters,
            'zero_results_by_location' => $zeroResultsByLocation,
            'top_types'                => $topTypes,
            'top_cities'               => $topCities,
            'total_searches'           => $totalSearches,
            'period_days'              => 30,
        ]);
    }

    public function sessionStats(): JsonResponse
    {
        $since30 = now()->subDays(30)->startOfDay();

        $stats = DB::table('user_sessions')
            ->where('started_at', '>=', $since30)
            ->selectRaw('COUNT(*) as total_sessions')
            ->selectRaw('AVG(duration_seconds) as avg_duration_seconds')
            ->selectRaw('AVG(page_count) as avg_pages_per_session')
            ->selectRaw('SUM(CASE WHEN page_count <= 1 THEN 1 ELSE 0 END) as bounce_count')
            ->first();

        $bounceRate = $stats->total_sessions > 0
            ? round($stats->bounce_count / $stats->total_sessions * 100, 2)
            : 0;

        $deviceBreakdown = DB::table('user_sessions')
            ->where('started_at', '>=', $since30)
            ->select('device')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('device')
            ->get();

        return response()->json([
            'total_sessions'        => $stats->total_sessions,
            'avg_duration_seconds'  => round($stats->avg_duration_seconds ?? 0, 1),
            'avg_pages_per_session' => round($stats->avg_pages_per_session ?? 0, 1),
            'bounce_rate'           => $bounceRate,
            'device_breakdown'      => $deviceBreakdown,
            'period_days'           => 30,
        ]);
    }

    public function geoBreakdown(): JsonResponse
    {
        $since30 = now()->subDays(30)->startOfDay();

        $byCountry = DB::table('property_views')
            ->where('created_at', '>=', $since30)
            ->whereNotNull('country')
            ->select('country')
            ->selectRaw('COUNT(*) as views_total')
            ->selectRaw('COUNT(DISTINCT visitor_key) as unique_visitors')
            ->groupBy('country')
            ->orderByDesc('views_total')
            ->limit(20)
            ->get();

        $byCity = DB::table('property_views')
            ->where('created_at', '>=', $since30)
            ->whereNotNull('city_geo')
            ->select('country', 'city_geo')
            ->selectRaw('COUNT(*) as views_total')
            ->selectRaw('COUNT(DISTINCT visitor_key) as unique_visitors')
            ->groupBy('country', 'city_geo')
            ->orderByDesc('views_total')
            ->limit(20)
            ->get();

        return response()->json([
            'by_country'  => $byCountry,
            'by_city'     => $byCity,
            'period_days' => 30,
        ]);
    }
}
