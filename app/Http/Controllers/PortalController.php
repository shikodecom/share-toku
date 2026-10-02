<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Service;
use App\Models\WorkspacePublicProfile;
use App\Services\PortalQuery;
use App\Services\Publishability;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function index(Request $request, PortalQuery $query, ?string $categorySlug = null)
    {
        $filters = $request->validate(['q' => 'nullable|string|max:200', 'category' => 'nullable|string|max:255']);
        $category = $categorySlug ? Category::where('is_active', true)->where('slug', $categorySlug)->firstOrFail() : null;

        return view('portal.index', [
            'title' => $category ? $category->name.'の紹介特典' : '紹介特典を探す',
            'search' => $filters['q'] ?? '', 'selectedCategory' => $categorySlug ?? ($filters['category'] ?? ''),
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'services' => $query->services($filters['q'] ?? '', $categorySlug ?? ($filters['category'] ?? null))->paginate(20)->withQueryString(),
            'offers' => $request->path() === '/' ? $query->offers()->limit(10)->get() : collect(),
        ]);
    }

    public function service(string $serviceSlug, PortalQuery $query)
    {
        $service = Service::where('is_active', true)->where('slug', $serviceSlug)->with('categories')->firstOrFail();

        return view('portal.service', ['title' => $service->name.'の紹介特典', 'service' => $service,
            'programs' => $service->programs()->get()->filter(fn ($program) => app(Publishability::class)->programAllowed($program)),
            'offers' => $query->offers()->whereHas('program', fn ($q) => $q->where('service_id', $service->id))->paginate(20)]);
    }

    public function offer(string $offerPublicId, PortalQuery $query)
    {
        $offer = $query->offers()->where('public_id', $offerPublicId)->firstOrFail();

        return view('portal.offer', ['title' => $offer->program->service->name.'の紹介特典', 'offer' => $offer]);
    }

    public function profile(string $publicSlug, PortalQuery $query)
    {
        $profile = WorkspacePublicProfile::where('is_public', true)->where('public_slug', $publicSlug)->whereHas('workspace')->firstOrFail();

        return view('portal.profile', ['title' => $profile->display_name.'の紹介特典', 'profile' => $profile,
            'offers' => $query->offers()->where('workspace_id', $profile->workspace_id)->paginate(20)]);
    }
}
