@extends('portal.layout')
@section('content')
<section><p>{{ $service->description }}</p>@if($url = \App\Services\PortalQuery::safeUrl($service->official_url))<a href="{{ $url }}" rel="external noopener noreferrer">公式サイト（外部リンク）</a>@endif</section>
@foreach($programs as $program)<section><h2>{{ $program->name }}</h2><p>{{ $program->description }}</p></section>@endforeach
@include('portal.cards')
@endsection
