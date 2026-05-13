<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregatePropertyStats extends Command
{
    protected $signature   = 'analytics:aggregate {--date= : Date to aggregate (Y-m-d, defaults to yesterday)}';
    protected $description = 'Aggregate property views and contacts into daily stats tables';

    public function handle(): int
    {
        $date = $this->option('date') ?? now()->subDay()->toDateString();

        $this->info("Aggregating stats for {$date}...");

        $this->aggregatePropertyStats($date);
        $this->aggregateCityStats($date);

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function aggregatePropertyStats(string $date): void
    {
        // Get all property_ids that had views on the given date
        $propertyIds = DB::table('property_views')
            ->whereDate('created_at', $date)
            ->distinct()
            ->pluck('property_id');

        if ($propertyIds->isEmpty()) {
            $this->line("  No views found for {$date}");
            return;
        }

        foreach ($propertyIds as $propertyId) {
            $viewsTotal  = DB::table('property_views')
                ->where('property_id', $propertyId)
                ->whereDate('created_at', $date)
                ->count();

            $viewsUnique = DB::table('property_views')
                ->where('property_id', $propertyId)
                ->whereDate('created_at', $date)
                ->distinct('visitor_key')
                ->count('visitor_key');

            $contacts = DB::table('contacts')
                ->where('property_id', $propertyId)
                ->whereDate('created_at', $date)
                ->count();

            $conversionRate = $viewsUnique > 0
                ? round($contacts / $viewsUnique * 100, 2)
                : 0.00;

            DB::table('property_stats_daily')->upsert(
                [
                    'property_id'     => $propertyId,
                    'date'            => $date,
                    'views_total'     => $viewsTotal,
                    'views_unique'    => $viewsUnique,
                    'contacts_count'  => $contacts,
                    'conversion_rate' => $conversionRate,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ],
                ['property_id', 'date'],
                ['views_total', 'views_unique', 'contacts_count', 'conversion_rate', 'updated_at']
            );
        }

        $this->line("  property_stats_daily: {$propertyIds->count()} properties processed");
    }

    private function aggregateCityStats(string $date): void
    {
        // Aggregate views grouped by location via property.location_id
        $cityRows = DB::table('property_views as pv')
            ->join('properties as p', 'pv.property_id', '=', 'p.id')
            ->whereDate('pv.created_at', $date)
            ->whereNotNull('p.location_id')
            ->select('p.location_id')
            ->selectRaw('COUNT(pv.id) as views_total')
            ->groupBy('p.location_id')
            ->get();

        foreach ($cityRows as $row) {
            $contacts = DB::table('contacts as c')
                ->join('properties as p', 'c.property_id', '=', 'p.id')
                ->where('p.location_id', $row->location_id)
                ->whereDate('c.created_at', $date)
                ->count();

            // Snapshot of published count at aggregation time
            $publishedCount = DB::table('properties')
                ->where('location_id', $row->location_id)
                ->where('status', 'published')
                ->count();

            $ratio = $publishedCount > 0
                ? round($row->views_total / $publishedCount, 2)
                : 0.00;

            DB::table('city_stats_daily')->upsert(
                [
                    'location_id'          => $row->location_id,
                    'date'                 => $date,
                    'views_total'          => $row->views_total,
                    'properties_published' => $publishedCount,
                    'contacts_count'       => $contacts,
                    'demand_supply_ratio'  => $ratio,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ],
                ['location_id', 'date'],
                ['views_total', 'properties_published', 'contacts_count', 'demand_supply_ratio', 'updated_at']
            );
        }

        $this->line("  city_stats_daily: {$cityRows->count()} cities processed");
    }
}
