<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ReferralProgram;
use App\Models\Service;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MasterController extends Controller
{
    private function authorizeEditor(Request $request, WorkspaceAccess $access): void
    {
        if ($request->user()->is_system_admin) {
            return;
        }
        $operatorId = config('sharetoku.operator_workspace_public_id');
        $member = $operatorId && $request->user()->workspaces()->where('workspaces.public_id', $operatorId)->wherePivot('role', 'reviewer')->exists();
        if (! $member) {
            $access->requireSystemAdmin($request->user());
        }
    }

    public function index(string $kind)
    {
        $model = $this->model($kind);

        return response()->json($model::query()->latest('id')->paginate(30));
    }

    public function store(Request $request, string $kind, WorkspaceAccess $access)
    {
        $this->authorizeEditor($request, $access);
        $model = $this->model($kind);
        $data = $this->validated($request, $kind);
        $data['public_id'] = (string) Str::ulid();
        $record = $model::create($data);
        if ($kind === 'services') {
            $record->categories()->sync($request->input('category_ids', []));
        }

        return response()->json($record, 201);
    }

    public function update(Request $request, string $kind, string $publicId, WorkspaceAccess $access)
    {
        $this->authorizeEditor($request, $access);
        $model = $this->model($kind);
        $record = $model::where('public_id', $publicId)->firstOrFail();
        $record->update($this->validated($request, $kind, $record->id));
        if ($kind === 'services' && $request->exists('category_ids')) {
            $record->categories()->sync($request->input('category_ids', []));
        }

        return response()->json($record);
    }

    public function destroy(Request $request, string $kind, string $publicId, WorkspaceAccess $access)
    {
        $this->authorizeEditor($request, $access);
        $model = $this->model($kind);
        $record = $model::where('public_id', $publicId)->firstOrFail();
        if ($kind === 'categories') {
            $record->update(['is_active' => false]);
        } else {
            $record->delete();
        }

        return response()->noContent();
    }

    private function model(string $kind): string
    {
        return match ($kind) {
            'categories' => Category::class, 'services' => Service::class, 'programs' => ReferralProgram::class, default => abort(404)
        };
    }

    private function validated(Request $request, string $kind, ?int $id = null): array
    {
        $url = ['nullable', 'url:http,https', 'max:2048'];

        $data = match ($kind) {
            'categories' => $request->validate(['name' => 'required|string|max:255', 'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('categories', 'slug')->ignore($id)], 'description' => 'nullable|string', 'is_active' => 'boolean', 'sort_order' => 'integer']),
            'services' => $request->validate(['name' => 'required|string|max:255', 'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('services', 'slug')->ignore($id)], 'description' => 'nullable|string', 'official_url' => $url, 'logo_url' => $url, 'is_active' => 'boolean', 'category_ids' => 'array', 'category_ids.*' => 'exists:categories,id']),
            'programs' => $request->validate(['service_id' => 'required|exists:services,id', 'name' => 'required|string|max:255', 'description' => 'nullable|string', 'invitee_benefit_text' => 'nullable|string', 'inviter_benefit_text' => 'nullable|string', 'conditions_text' => 'nullable|string', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'official_terms_url' => $url, 'public_listing_policy' => ['required', Rule::in(['approved', 'needs_review', 'restricted', 'prohibited', 'suspended'])], 'external_distribution_policy' => ['required', Rule::in(['approved', 'needs_review', 'restricted', 'prohibited', 'suspended'])], 'policy_notes' => 'nullable|string', 'policy_checked_at' => 'nullable|date', 'is_active' => 'boolean']),
        };
        if ($kind === 'programs' && in_array('restricted', [$data['public_listing_policy'], $data['external_distribution_policy']], true)
            && blank($data['policy_notes'] ?? null)) {
            throw ValidationException::withMessages(['policy_notes' => 'Restricted policy requires conditions.']);
        }

        return $data;
    }
}
