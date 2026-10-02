<!doctype html>
<html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }} | ShareToku</title><meta name="description" content="{{ $title }}。紹介特典の条件、期限、最終確認日を確認できます。">
<link rel="canonical" href="{{ url(request()->path()) }}">
<style>body{font:16px system-ui;max-width:960px;margin:auto;padding:24px;color:#183229;background:#f5f8f6;line-height:1.8}a{color:#08664d}nav{display:flex;gap:20px;flex-wrap:wrap}article,section{background:white;padding:24px;margin:20px 0;border:1px solid #dbe6df;border-radius:12px}input,select,textarea,button{font:inherit;padding:8px;max-width:100%;box-sizing:border-box}button{cursor:pointer}code{overflow-wrap:anywhere}dt{font-weight:bold}dd{margin:0 0 16px;white-space:pre-line}footer{margin-top:32px}</style></head>
<body><header><nav><a href="/">ShareToku</a><a href="/services">サービス一覧</a><a href="/dashboard">管理画面</a><a href="/login">ログイン</a></nav></header>
<main><h1>{{ $title }}</h1>@yield('content')</main><footer>紹介者にも特典が発生する場合があります。利用前に公式の条件をご確認ください。</footer></body></html>
