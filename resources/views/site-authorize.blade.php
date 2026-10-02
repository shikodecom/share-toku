<!doctype html><html lang="ja"><meta charset="utf-8"><title>ShareToku 接続確認</title>
<main><h1>ShareToku 接続確認</h1><p>「{{ $site->name }}」（{{ $site->domain }}）を接続します。</p><p>WordPress サイト: {{ $params['site_home_url'] }}</p>
<p>接続先: {{ $params['redirect_uri'] }}</p>
<form method="post" action="{{ route('site-connections.authorize') }}">@csrf
@foreach($params as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
<button type="submit">このサイトを接続する</button></form></main></html>
