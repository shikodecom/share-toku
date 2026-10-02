<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\MatchingSettingsController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SiteConnectionController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WorkspaceController;
use App\Models\Category;
use App\Models\ReferralOffer;
use App\Models\ReferralProgram;
use App\Models\ReviewRequest;
use App\Models\Service;
use App\Models\Site;
use App\Models\Workspace;
use App\Services\EntitlementService;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::get('/', fn () => view('home'));
Route::get('/register', [AuthController::class, 'registerForm']);
Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function (Request $request) {
        $workspace = app(WorkspaceAccess::class)->current($request->user());

        return view('dashboard', [
            'workspace' => $workspace,
            'usage' => app(EntitlementService::class)->usage($workspace),
            'offers' => $workspace->offers()->with('program.service')->latest()->get(),
            'sites' => $workspace->sites()->with('consents')->latest()->get(),
            'programs' => ReferralProgram::with('service')->where('is_active', true)->get(),
            'categories' => Category::orderBy('sort_order')->get(),
            'exclusions' => DB::table('site_category_exclusions')->whereIn('site_id', $workspace->sites()->select('id'))->get()->groupBy('site_id'),
            'metrics' => DB::table('daily_metrics')->join('sites', 'sites.id', '=', 'daily_metrics.site_id')
                ->join('referral_offers', 'referral_offers.id', '=', 'daily_metrics.referral_offer_id')
                ->where('sites.workspace_id', $workspace->id)->whereDate('metric_date', '>=', now()->subDays(30)->toDateString())
                ->select('sites.name as site_name', 'referral_offers.public_id as offer', 'slot')
                ->selectRaw('SUM(impressions) as impressions, SUM(clicks) as clicks')
                ->groupBy('sites.name', 'referral_offers.public_id', 'slot')->get(),
            'services' => Service::with('categories')->orderBy('name')->get(),
            'allPrograms' => ReferralProgram::with('service')->orderBy('name')->get(),
            'adminWorkspaces' => $request->user()->is_system_admin ? Workspace::orderBy('name')->get() : collect(),
            'adminOffers' => $request->user()->is_system_admin ? ReferralOffer::with('workspace', 'program.service')->whereIn('status', ['published', 'suspended'])->latest()->limit(50)->get() : collect(),
            'adminSites' => $request->user()->is_system_admin ? Site::with('workspace')->whereIn('status', ['active', 'suspended'])->latest()->limit(50)->get() : collect(),
            'reviews' => $request->user()->is_system_admin
                ? ReviewRequest::where('status', 'pending')->get()
                : ReviewRequest::where('status', 'pending')->whereIn('referral_offer_id', $workspace->offers()->select('id'))->get(),
        ]);
    });
    Route::get('/workspaces/{workspace}', [WorkspaceController::class, 'show']);
    Route::patch('/workspaces/{workspace}', [WorkspaceController::class, 'update']);
    Route::get('/workspaces/{workspace}/offers', [OfferController::class, 'index']);
    Route::post('/workspaces/{workspace}/offers', [OfferController::class, 'store']);
    Route::get('/workspaces/{workspace}/offers/{offer}', [OfferController::class, 'show']);
    Route::patch('/workspaces/{workspace}/offers/{offer}', [OfferController::class, 'update']);
    Route::delete('/workspaces/{workspace}/offers/{offer}', [OfferController::class, 'destroy']);
    Route::post('/workspaces/{workspace}/offers/{offer}/submit', [ReviewController::class, 'submit']);
    Route::get('/workspaces/{workspace}/sites', [SiteController::class, 'index']);
    Route::get('/workspaces/{workspace}/analytics', [AnalyticsController::class, 'summary']);
    Route::post('/workspaces/{workspace}/sites', [SiteController::class, 'store']);
    Route::patch('/workspaces/{workspace}/sites/{site}', [SiteController::class, 'update']);
    Route::get('/workspaces/{workspace}/sites/{site}/category-exclusions', [MatchingSettingsController::class, 'exclusions']);
    Route::put('/workspaces/{workspace}/sites/{site}/category-exclusions', [MatchingSettingsController::class, 'saveExclusions']);
    Route::post('/workspaces/{workspace}/sites/{site}/consent', [SiteController::class, 'consent']);
    Route::post('/workspaces/{workspace}/sites/{site}/withdraw', [SiteController::class, 'withdraw']);
    Route::post('/workspaces/{workspace}/sites/{site}/revoke', [SiteConnectionController::class, 'revoke']);
    Route::get('/master/{kind}', [MasterController::class, 'index']);
    Route::post('/master/{kind}', [MasterController::class, 'store']);
    Route::patch('/master/{kind}/{publicId}', [MasterController::class, 'update']);
    Route::delete('/master/{kind}/{publicId}', [MasterController::class, 'destroy']);
    Route::get('/reviews/pending', [ReviewController::class, 'pending']);
    Route::post('/reviews/{review}/decision', [ReviewController::class, 'decide']);
    Route::post('/admin/offers/{offer}/suspend', [ReviewController::class, 'suspend']);
    Route::post('/admin/offers/{offer}/reopen', [ReviewController::class, 'reopen']);
    Route::post('/admin/sites/{site}/suspend', [SiteController::class, 'suspend']);
    Route::post('/admin/workspaces/{workspace}/subscription', [SubscriptionController::class, 'update']);
    Route::get('/admin/category-relations', [MatchingSettingsController::class, 'relations']);
    Route::put('/admin/category-relations', [MatchingSettingsController::class, 'saveRelation']);
    Route::get('/site-connections/authorize', [SiteConnectionController::class, 'authorizeForm']);
    Route::post('/site-connections/authorize', [SiteConnectionController::class, 'authorizeSite'])->name('site-connections.authorize');
});
