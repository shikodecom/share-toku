<?php

namespace App\Http\Controllers;

use App\Contracts\SubscriptionManager;
use App\Models\Workspace;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function update(Request $request, string $workspace, WorkspaceAccess $access, SubscriptionManager $manager)
    {
        $access->requireSystemAdmin($request->user());
        $w = Workspace::where('public_id', $workspace)->firstOrFail();
        $data = $request->validate(['plan_code' => 'required|in:free,pro', 'status' => 'required|in:active,trialing,past_due,cancelled,expired',
            'starts_at' => 'required|date', 'ends_at' => 'nullable|date|after:starts_at']);

        return response()->json($manager->change($w, $data, $request->user()->id));
    }
}
