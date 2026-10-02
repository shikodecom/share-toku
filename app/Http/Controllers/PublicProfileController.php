<?php

namespace App\Http\Controllers;

use App\Models\WorkspacePublicProfile;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicProfileController extends Controller
{
    public function edit(Request $request, string $workspace, WorkspaceAccess $access)
    {
        $w = $access->current($request->user(), $workspace);
        $access->requireRole($request->user(), $w, ['administrator']);

        return view('public-profile-form', ['workspace' => $w, 'profile' => $w->publicProfile]);
    }

    public function update(Request $request, string $workspace, WorkspaceAccess $access)
    {
        $w = $access->current($request->user(), $workspace);
        $access->requireRole($request->user(), $w, ['administrator']);
        $data = $request->validate([
            'public_slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('workspace_public_profiles')->ignore($w->publicProfile?->id)],
            'display_name' => 'required|string|max:100', 'bio' => 'nullable|string|max:2000',
            'website_url' => 'nullable|url:http,https|max:2048', 'is_public' => 'required|boolean',
        ]);
        WorkspacePublicProfile::updateOrCreate(['workspace_id' => $w->id], $data);

        return redirect('/workspaces/'.$w->public_id.'/public-profile')->with('status', '公開プロフィールを保存しました。');
    }
}
