<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class AuthController extends Controller
{
    private const FAILURE = 'Googleログインに失敗しました。もう一度お試しください。';

    private const CONFLICT = 'アカウント情報が競合しています。管理者へお問い合わせください。';

    public function loginForm()
    {
        return Auth::check() ? redirect('/dashboard') : view('login');
    }

    public function googleRedirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function googleCallback(Request $request)
    {
        if ($request->query->has('error')) {
            return redirect('/login')->with('auth_error', $request->query('error') === 'access_denied'
                ? 'Googleログインをキャンセルしました。' : self::FAILURE);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return redirect('/login')->with('auth_error', 'Googleログインの確認に失敗しました。もう一度お試しください。');
        } catch (Throwable $exception) {
            Log::warning('Google login provider failed.', ['exception_class' => $exception::class]);

            return redirect('/login')->with('auth_error', self::FAILURE);
        }

        $googleId = trim((string) $googleUser->getId());
        $email = mb_strtolower(trim((string) $googleUser->getEmail()));
        $name = trim((string) $googleUser->getName());
        if ($googleId === '' || $email === '') {
            return redirect('/login')->with('auth_error', self::FAILURE);
        }
        if ($name === '') {
            $name = Str::before($email, '@');
        }

        try {
            $user = DB::transaction(function () use ($googleId, $email, $name): ?User {
                $user = User::where('google_id', $googleId)->lockForUpdate()->first();
                $conflict = User::where('email', $email)
                    ->when($user !== null, fn ($query) => $query->where('id', '!=', $user->id))->exists();
                if ($conflict) {
                    return null;
                }
                if ($user !== null) {
                    $user->update(['name' => $name, 'email' => $email, 'email_verified_at' => $user->email_verified_at ?? now()]);

                    return $user;
                }

                $user = User::create(['name' => $name, 'email' => $email, 'google_id' => $googleId, 'email_verified_at' => now()]);
                $workspace = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => $user->name.' のWorkspace', 'type' => 'personal', 'owner_user_id' => $user->id]);
                WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'administrator']);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent callback may have claimed this Google ID or email.
            return redirect('/login')->with('auth_error', self::CONFLICT);
        }

        if ($user === null) {
            return redirect('/login')->with('auth_error', self::CONFLICT);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
