@extends('portal.layout')
@section('content')
@php($program = $offer->program)
<article><h2>{{ $offer->title ?: $program->name }}</h2><dl>
<dt>サービス</dt><dd><a href="/services/{{ $program->service->slug }}">{{ $program->service->name }}</a></dd>
<dt>紹介制度</dt><dd>{{ $program->name }}</dd>
<dt>紹介される方の特典</dt><dd>{{ filled($offer->invitee_benefit_override) ? $offer->invitee_benefit_override : $program->invitee_benefit_text }}</dd>
<dt>条件</dt><dd>{{ filled($offer->conditions_override) ? $offer->conditions_override : $program->conditions_text }}</dd>
<dt>期限</dt><dd>特典: {{ $offer->ends_at?->format('Y-m-d H:i') ?? '期限の設定なし' }} / 紹介制度: {{ $program->ends_at?->format('Y-m-d H:i') ?? '期限の設定なし' }}</dd>
<dt>最終確認日</dt><dd>{{ $offer->last_verified_at?->format('Y-m-d') ?? '未確認' }}</dd>
<dt>紹介者</dt><dd><a href="/u/{{ $offer->workspace->publicProfile->public_slug }}">{{ $offer->workspace->publicProfile->display_name }}</a></dd>
@if(filled($offer->referral_code))<dt>紹介コード</dt><dd><code id="referral-code">{{ $offer->referral_code }}</code> <button type="button" id="copy-code">コピー</button><span id="copy-status" role="status"></span></dd>@endif
</dl><p>紹介者にも特典が発生する場合があります。</p>
@if($url = \App\Services\PortalQuery::safeUrl($offer->referral_url))<a href="{{ $url }}" rel="sponsored external noopener noreferrer">紹介URLを開く（外部リンク）</a>@endif
@if($url = \App\Services\PortalQuery::safeUrl($program->official_terms_url))<p><a href="{{ $url }}" rel="external noopener noreferrer">公式の利用条件（外部リンク）</a></p>@endif</article>
<script>document.getElementById('copy-code')?.addEventListener('click',async()=>{const status=document.getElementById('copy-status');try{await navigator.clipboard.writeText(document.getElementById('referral-code').textContent);status.textContent='コピーしました';}catch{status.textContent='コピーできませんでした。コードを選択してコピーしてください。';}});</script>
@endsection
