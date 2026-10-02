<p>最終確認日の新しい順（未確認は最後）、同日の場合は公開ID順で表示しています。</p>
@forelse($offers as $item)
<article><h2><a href="/offers/{{ $item->public_id }}">{{ $item->title ?: $item->program->service->name.'の紹介特典' }}</a></h2>
<p>{{ filled($item->invitee_benefit_override) ? $item->invitee_benefit_override : $item->program->invitee_benefit_text }}</p>
<p>紹介者: <a href="/u/{{ $item->workspace->publicProfile->public_slug }}">{{ $item->workspace->publicProfile->display_name }}</a></p>
<p>最終確認日: {{ $item->last_verified_at?->format('Y-m-d') ?? '未確認' }}</p></article>
@empty<p>現在公開中の紹介特典はありません</p>@endforelse
@if($offers instanceof \Illuminate\Contracts\Pagination\Paginator){{ $offers->links() }}@endif
