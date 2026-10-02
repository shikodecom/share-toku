@extends('portal.layout')
@section('content')
<form action="/services" method="get"><label>サービス検索 <input name="q" value="{{ $search }}" placeholder="サービス・カテゴリ・紹介制度"></label>
<label>カテゴリ <select name="category"><option value="">すべて</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected($selectedCategory === $category->slug)>{{ $category->name }}</option>@endforeach</select></label><button>検索</button></form>
<nav>@foreach($categories as $category)<a href="/categories/{{ $category->slug }}">{{ $category->name }}</a>@endforeach</nav>
@forelse($services as $service)<article><h2><a href="/services/{{ $service->slug }}">{{ $service->name }}</a></h2><p>{{ $service->description }}</p><p>@foreach($service->categories->where('is_active', true) as $category){{ $category->name }} @endforeach · 公開特典 {{ $service->public_offers_count }}件</p></article>@empty<p>該当するサービスはありません。</p>@endforelse
{{ $services->links() }}
@if(request()->path() === '/')<h2>最近確認された公開特典</h2>@include('portal.cards')@endif
@endsection
