<!doctype html>
<html lang="ja"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ログイン | ShareToku</title>
<style>body{font:16px system-ui;max-width:32rem;margin:4rem auto;padding:1rem;line-height:1.7}a{color:#17599a}.google-login{display:inline-block;padding:.7rem 1.2rem;border:1px solid #17599a;border-radius:.4rem}</style>
<main><h1>ShareToku ログイン</h1>
@if(session('auth_error'))<p role="alert">{{ session('auth_error') }}</p>@endif
<p>Googleアカウントでログインできます。初回ログイン時に個人Workspaceを作成します。</p>
<a class="google-login" href="{{ route('auth.google.redirect') }}">Googleでログイン</a>
<p><a href="/">トップへ</a></p></main></html>
