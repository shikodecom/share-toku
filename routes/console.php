<?php

use App\Models\ReferralOffer;
use App\Services\Audit;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('offers:expire', function (Audit $audit) {
    ReferralOffer::where('status', 'published')->whereNotNull('ends_at')->where('ends_at', '<', now())->chunkById(100, function ($offers) use ($audit) {
        foreach ($offers as $offer) {
            $offer->update(['status' => 'expired']);
            $audit->record(null, $offer->workspace_id, $offer, 'expire', ['status' => 'published'], ['status' => 'expired']);
        }
    });
})->purpose('Expire published offers past their end time');

Schedule::command('offers:expire')->hourly();

Artisan::command('analytics:aggregate', function () {
    foreach ([2, 1, 0] as $daysAgo) {
        $date = now()->subDays($daysAgo)->toDateString();
        $rows = DB::table('analytics_events')->whereDate('occurred_at', $date)
            ->selectRaw('site_id, placement_id, referral_offer_id, slot, SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) AS impressions, SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) AS clicks', ['impression', 'click'])
            ->groupBy('site_id', 'placement_id', 'referral_offer_id', 'slot')->get();
        foreach ($rows as $row) {
            DB::table('daily_metrics')->updateOrInsert(
                ['metric_date' => $date, 'site_id' => $row->site_id, 'placement_id' => $row->placement_id, 'referral_offer_id' => $row->referral_offer_id, 'slot' => $row->slot],
                ['impressions' => $row->impressions, 'clicks' => $row->clicks, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
})->purpose('Rebuild current and previous two days of analytics metrics');

Artisan::command('analytics:prune', function () {
    DB::table('analytics_events')->where('occurred_at', '<', now()->subDays(config('sharetoku.analytics_retention_days')))->delete();
})->purpose('Remove expired raw analytics events');

Schedule::command('analytics:aggregate')->dailyAt('01:00');
Schedule::command('analytics:prune')->dailyAt('02:00');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
