<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function registerForm()
    {
        return view('auth-form', ['mode' => 'register']);
    }

    public function loginForm()
    {
        return view('auth-form', ['mode' => 'login']);
    }

    public function register(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users,email', 'password' => 'required|string|min:12|confirmed']);
        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);
            $workspace = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => $user->name.' のWorkspace', 'type' => 'personal', 'owner_user_id' => $user->id]);
            WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'administrator']);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        if (! $request->expectsJson()) {
            return redirect('/dashboard');
        }

        return response()->json(['user' => $user->only('name', 'email'), 'workspace' => $user->workspaces()->first()->only('public_id', 'name')], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($credentials)) {
            return response()->json(['error' => ['code' => 'invalid_credentials', 'message' => 'Invalid credentials.']], 422);
        }
        $request->session()->regenerate();

        if (! $request->expectsJson()) {
            return redirect('/dashboard');
        }

        return response()->json(['user' => Auth::user()->only('name', 'email')]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (! $request->expectsJson()) {
            return redirect('/login');
        }

        return response()->noContent();
    }
}
