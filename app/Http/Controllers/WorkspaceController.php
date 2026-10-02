<?php

namespace App\Http\Controllers;

use App\Services\EntitlementService;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function show(Request $request, string $workspace, WorkspaceAccess $access, EntitlementService $entitlements)
    {
        $w = $access->current($request->user(), $workspace);

        return response()->json(['workspace' => $w->only('public_id', 'name', 'type'), 'role' => $w->members()->where('user_id', $request->user()->id)->value('role'), 'usage' => $entitlements->usage($w)]);
    }

    public function update(Request $request, string $workspace, WorkspaceAccess $access)
    {
        $w = $access->current($request->user(), $workspace);
        $access->requireRole($request->user(), $w, ['administrator']);
        $w->update($request->validate(['name' => 'required|string|max:100']));

        return response()->json($w->only('public_id', 'name'));
    }
}
