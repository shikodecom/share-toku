<!doctype html>
<html lang="ja"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $mode === 'register' ? '登録' : 'ログイン' }} | ShareToku</title>
<style>body{font:16px system-ui;max-width:32rem;margin:4rem auto;padding:1rem}label{display:block;margin:1rem 0}input{display:block;width:100%;padding:.6rem;box-sizing:border-box}button{padding:.7rem 1.2rem}a{color:#17599a}</style>
<main><h1>ShareToku {{ $mode === 'register' ? '登録' : 'ログイン' }}</h1>
@if($errors->any())<div role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="post" action="/{{ $mode }}">@csrf
@if($mode === 'register')<label>名前<input name="name" required value="{{ old('name') }}"></label>@endif
<label>メールアドレス<input type="email" name="email" required value="{{ old('email') }}"></label>
<label>パスワード<input type="password" name="password" required></label>
@if($mode === 'register')<label>パスワード確認<input type="password" name="password_confirmation" required></label>@endif
<button type="submit">{{ $mode === 'register' ? '登録する' : 'ログイン' }}</button></form>
<p><a href="/{{ $mode === 'register' ? 'login' : 'register' }}">{{ $mode === 'register' ? 'ログインへ' : '新規登録へ' }}</a></p></main></html>
