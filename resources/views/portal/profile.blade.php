@extends('portal.layout')
@section('content')
<section><h2>{{ $profile->display_name }}</h2><p>{{ $profile->bio }}</p>@if($url = \App\Services\PortalQuery::safeUrl($profile->website_url))<a href="{{ $url }}" rel="external noopener noreferrer">Webサイト（外部リンク）</a>@endif</section>
@include('portal.cards')
@endsection
