<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregateMarketInsights extends Command
{
    protected $signature   = 'analytics:aggregate-market {--date= : Target date (YYYY-MM-DD), defaults to yesterday}';
    protected $description = 'Aggregate searches and market signals into market_insights_daily';

    public function handle(): int
    {
        $date = $this->option('date') ?? now()->subDay()->toDateString();

        $this->info("Aggregating market insights for {$date}...");

        // Collect all location_ids active on this date from searches or property views
        $locationsFromSearches = DB::table('searches')
            ->whereDate('created_at', $date)
            ->whereNotNull('location_id')
            ->distinct()
            ->pluck('location_id');

        $locationsFromViews = DB::table('property_views as pv')
            ->join('properties as p', 'pv.property_id', '=', 'p.id')
            ->whereDate('pv.created_at', $date)
            ->whereNotNull('p.location_id')
            ->distinct()
            ->pluck('p.location_id');

        $locationIds = $locationsFromSearches->merge($locationsFromViews)->unique()->values();

        if ($locationIds->isEmpty()) {
            $this->line("  No activity found for {$date}");
            return self::SUCCESS;
        }

        foreach ($locationIds as $locationId) {
            // --- Search signals ---
            $searchesCount = DB::table('searches')
                ->whereDate('created_at', $date)
                ->where('location_id', $locationId)
                ->count();

            $searchesZeroResults = DB::table('searches')
                ->whereDate('created_at', $date)
                ->where('location_id', $locationId)
                ->where('results_count', 0)
                ->count();

            // --- Supply signals (snapshot at aggregation time) ---
            $propertiesPublished = DB::table('properties')
                ->where('location_id', $locationId)
                ->where('status', 'published')
                ->count();

            $avgPrice = DB::table('properties')
                ->where('location_id', $locationId)
                ->where('status', 'published')
                ->avg('price');

            // --- Demand signals from city_stats_daily (already computed) ---
            $cityStats = DB::table('city_stats_daily')
                ->where('location_id', $locationId)
                ->where('date', $date)
                ->first();

            $viewsTotal    = $cityStats?->views_total    ?? 0;
            $contactsCount = $cityStats?->contacts_count ?? 0;

            // --- avg_time_to_contact_hours ---
            // For each property in this location that has both a view and a contact on $date,
            // compute hours between earliest view and earliest contact, then average.
            $propertyIds = DB::table('properties')
                ->where('location_id', $locationId)
                ->where('status', 'published')
                ->pluck('id');

            $timeToContactSamples = [];

            foreach ($propertyIds as $propertyId) {
                $firstView = DB::table('property_views')
                    ->where('property_id', $propertyId)
                    ->whereDate('created_at', $date)
                    ->min('created_at');

                $firstContact = DB::table('contacts')
                    ->where('property_id', $propertyId)
                    ->whereDate('created_at', $date)
                    ->min('created_at');

                if ($firstView !== null && $firstContact !== null) {
                    $diffHours = abs(
                        strtotime($firstContact) - strtotime($firstView)
                    ) / 3600;

                    $timeToContactSamples[] = $diffHours;
                }
            }

            $avgTimeToContactHours = count($timeToContactSamples) > 0
                ? array_sum($timeToContactSamples) / count($timeToContactSamples)
                : null;

            // --- Computed indices ---
            $demandIndex = $propertiesPublished > 0
                ? round($searchesCount / $propertiesPublished, 4)
                : 0.0;

            $attractivenessScore = ($avgPrice !== null && $avgPrice > 0)
                ? min(round($viewsTotal / $avgPrice, 4), 5.0)
                : 0.0;

            $liquidityIndex = ($avgTimeToContactHours !== null && $avgTimeToContactHours > 0)
                ? min(round(1.0 / $avgTimeToContactHours, 4), 10.0)
                : 0.0;

            $searchGapIndex = $searchesCount > 0
                ? round($searchesZeroResults / $searchesCount, 4)
                : 0.0;

            // --- UPSERT ---
            DB::table('market_insights_daily')->upsert(
                [
                    'location_id'               => $locationId,
                    'date'                      => $date,
                    'searches_count'            => $searchesCount,
                    'searches_zero_results'     => $searchesZeroResults,
                    'views_total'               => $viewsTotal,
                    'properties_published'      => $propertiesPublished,
                    'avg_price'                 => $avgPrice,
                    'contacts_count'            => $contactsCount,
                    'avg_time_to_contact_hours' => $avgTimeToContactHours,
                    'demand_index'              => $demandIndex,
                    'attractiveness_score'      => $attractivenessScore,
                    'liquidity_index'           => $liquidityIndex,
                    'search_gap_index'          => $searchGapIndex,
                    'created_at'                => now(),
                    'updated_at'                => now(),
                ],
                ['location_id', 'date'],
                [
                    'searches_count',
                    'searches_zero_results',
                    'views_total',
                    'properties_published',
                    'avg_price',
                    'contacts_count',
                    'avg_time_to_contact_hours',
                    'demand_index',
                    'attractiveness_score',
                    'liquidity_index',
                    'search_gap_index',
                    'updated_at',
                ]
            );

            $this->info("  Processed location {$locationId}");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
