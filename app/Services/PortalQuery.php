<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;

class PortalQuery
{
    public function offers(): Builder
    {
        return app(Publishability::class)->offers(false)
            ->whereHas('workspace.publicProfile', fn ($q) => $q->where('is_public', true)->where('display_name', '<>', ''))
            ->with('workspace.publicProfile')
            ->orderByRaw('last_verified_at IS NULL')->orderByDesc('last_verified_at')->orderBy('public_id');
    }

    public function services(string $search = '', ?string $category = null): Builder
    {
        $query = Service::query()->where('is_active', true)->with('categories');
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('description', 'like', $term)
                ->orWhereHas('categories', fn ($c) => $c->where('is_active', true)->where('name', 'like', $term))
                ->orWhereHas('programs', fn ($p) => $p->where('is_active', true)->where('name', 'like', $term)));
        }
        if ($category !== null && $category !== '') {
            $query->whereHas('categories', fn ($q) => $q->where('is_active', true)->where('slug', $category));
        }

        return $query->withCount(['programs as public_offers_count' => fn ($q) => $q->join('referral_offers', 'referral_offers.referral_program_id', '=', 'referral_programs.id')
            ->whereIn('referral_offers.id', $this->offers()->reorder()->select('referral_offers.id'))])->orderBy('name')->orderBy('slug');
    }

    public static function safeUrl(?string $url): ?string
    {
        if (! $url || preg_match('/[\x00-\x1F\x7F]/', $url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $parts = parse_url($url);

        return is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)
            && ! isset($parts['user']) && ! isset($parts['pass']) ? $url : null;
    }
}
