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
        $rows = DB::table('city_stats_daily as cs')
            ->join('locations as l', 'cs.location_id', '=', 'l.id')
            ->where('cs.date', '>=', now()->subDays(30)->toDateString())
            ->select('l.id', 'l.name', 'l.slug')
            ->selectRaw('SUM(cs.views_total) as views_total')
            ->selectRaw('SUM(cs.contacts_count) as contacts_count')
            ->selectRaw('MAX(cs.properties_published) as properties_published')
            ->selectRaw('CASE WHEN MAX(cs.properties_published) > 0
                              THEN ROUND(SUM(cs.views_total) / MAX(cs.properties_published), 2)
                              ELSE 0 END as demand_supply_ratio')
            ->groupBy('l.id', 'l.name', 'l.slug')
            ->orderByDesc('views_total')
            ->limit(20)
            ->get();

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
        $rows = DB::table('market_insights_daily as m')
            ->join('locations as l', 'm.location_id', '=', 'l.id')
            ->where('m.date', '>=', now()->subDays(30)->toDateString())
            ->select('l.id', 'l.name', 'l.slug')
            ->selectRaw('SUM(m.searches_count) as searches_count')
            ->selectRaw('SUM(m.searches_zero_results) as searches_zero_results')
            ->selectRaw('SUM(m.views_total) as views_total')
            ->selectRaw('MAX(m.properties_published) as properties_published')
            ->selectRaw('AVG(m.avg_price) as avg_price')
            ->selectRaw('AVG(m.demand_index) as demand_index')
            ->selectRaw('AVG(m.attractiveness_score) as attractiveness_score')
            ->selectRaw('AVG(m.liquidity_index) as liquidity_index')
            ->selectRaw('AVG(m.search_gap_index) as search_gap_index')
            ->groupBy('l.id', 'l.name', 'l.slug')
            ->orderByDesc('demand_index')
            ->limit(20)
            ->get();

        return response()->json($rows);
    }

    public function searchTrends(): JsonResponse
    {
        $since30 = now()->subDays(30)->startOfDay();

        // Top 10 most common filter combinations (by location + transaction_type + property_type)
        $topFilters = DB::table('searches')
            ->where('created_at', '>=', $since30)
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(filters, '$.transaction_type')) as transaction_type")
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(filters, '$.property_type')) as property_type")
            ->selectRaw('location_id')
            ->selectRaw('COUNT(*) as search_count')
            ->selectRaw('SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END) as zero_result_count')
            ->groupBy('transaction_type', 'property_type', 'location_id')
            ->orderByDesc('search_count')
            ->limit(10)
            ->get();

        // Zero-results searches by location (top 10 locations with most unmet demand)
        $zeroResultsByLocation = DB::table('searches as s')
            ->leftJoin('locations as l', 's.location_id', '=', 'l.id')
            ->where('s.created_at', '>=', $since30)
            ->where('s.results_count', 0)
            ->select('l.id', 'l.name')
            ->selectRaw('COUNT(*) as zero_result_searches')
            ->groupBy('l.id', 'l.name')
            ->orderByDesc('zero_result_searches')
            ->limit(10)
            ->get();

        return response()->json([
            'top_filters'             => $topFilters,
            'zero_results_by_location' => $zeroResultsByLocation,
            'period_days'             => 30,
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
