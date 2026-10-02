<?php

namespace App\Http\Controllers;

use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MatchingSettingsController extends Controller
{
    public function relations(Request $request, WorkspaceAccess $access)
    {
        $access->requireSystemAdmin($request->user());

        return response()->json(DB::table('category_relations')->orderBy('source_category_id')->get());
    }

    public function saveRelation(Request $request, WorkspaceAccess $access)
    {
        $access->requireSystemAdmin($request->user());
        $data = $request->validate(['source_category_id' => 'required|integer|exists:categories,id|different:target_category_id',
            'target_category_id' => 'required|integer|exists:categories,id', 'relevance_score' => 'required|integer|between:0,100',
            'is_bidirectional' => 'required|boolean', 'is_active' => 'required|boolean', 'notes' => 'nullable|string|max:2000']);
        DB::table('category_relations')->updateOrInsert(
            ['source_category_id' => $data['source_category_id'], 'target_category_id' => $data['target_category_id']],
            $data + ['created_at' => now(), 'updated_at' => now()]
        );

        return response()->json(['status' => 'saved']);
    }

    public function exclusions(Request $request, string $workspace, string $site, WorkspaceAccess $access)
    {
        $w = $access->current($request->user(), $workspace);
        $access->requireRole($request->user(), $w, ['administrator']);
        $s = $w->sites()->where('public_id', $site)->firstOrFail();

        return response()->json(DB::table('site_category_exclusions')->where('site_id', $s->id)->pluck('category_id'));
    }

    public function saveExclusions(Request $request, string $workspace, string $site, WorkspaceAccess $access)
    {
        $w = $access->current($request->user(), $workspace);
        $access->requireRole($request->user(), $w, ['administrator']);
        $s = $w->sites()->where('public_id', $site)->firstOrFail();
        $data = $request->validate(['category_ids' => 'required|array', 'category_ids.*' => 'integer|distinct|exists:categories,id']);
        DB::transaction(function () use ($s, $data) {
            DB::table('site_category_exclusions')->where('site_id', $s->id)->delete();
            foreach ($data['category_ids'] as $id) {
                DB::table('site_category_exclusions')->insert(['site_id' => $s->id, 'category_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return response()->json(['category_ids' => $data['category_ids']]);
    }
}
