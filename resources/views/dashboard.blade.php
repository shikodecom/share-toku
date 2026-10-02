<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}"><title>ダッシュボード | ShareToku</title>
<style>body{font:16px system-ui;max-width:76rem;margin:auto;padding:1.5rem;color:#17212b}header{display:flex;justify-content:space-between;align-items:center}section{border-top:1px solid #d8dfe7;padding:1.5rem 0}form{margin:.7rem 0}label{display:inline-grid;gap:.2rem;margin:.3rem}.row{display:flex;flex-wrap:wrap;align-items:end;gap:.4rem}input,select,textarea,button{font:inherit;padding:.45rem}textarea{min-width:22rem}button{cursor:pointer}table{border-collapse:collapse;width:100%}th,td{border-bottom:1px solid #e0e4e9;text-align:left;padding:.55rem}.muted{color:#526070}.error{color:#a32323}a{color:#17599a}</style></head><body>
<header><div><h1>ShareToku</h1><p>{{ $workspace->name }} · {{ $usage['plan'] }} · Site {{ $usage['sites'] }}/{{ $usage['max_sites'] }} · Offer {{ $usage['offers'] }}/{{ $usage['max_offers'] }}</p></div>
<form method="post" action="/logout">@csrf<button>ログアウト</button></form></header>
<div id="result" role="status" aria-live="polite"></div>

<section><h2>Workspace</h2><form data-api data-method="PATCH" method="post" action="/workspaces/{{ $workspace->public_id }}" class="row">
<label>名前<input name="name" required value="{{ $workspace->name }}"></label><button>名前を変更</button></form></section>

<section><h2>紹介特典</h2>
<form data-api method="post" action="/workspaces/{{ $workspace->public_id }}/offers" class="row">
<label>紹介プログラム<select name="referral_program_id" required><option value="">選択</option>@foreach($programs as $p)<option value="{{ $p->id }}">{{ $p->service->name }} / {{ $p->name }}</option>@endforeach</select></label>
<label>紹介コード<input name="referral_code" maxlength="255"></label><label>紹介URL<input type="url" name="referral_url" maxlength="2048"></label>
<label>期限<input type="datetime-local" name="ends_at"></label><button>下書きを作成</button></form>
<table><thead><tr><th>サービス</th><th>状態</th><th>コード / URL</th><th>期限</th><th>操作</th></tr></thead><tbody>
@forelse($offers as $offer)<tr><td>{{ ($offer->program?->service?->name ?? '削除済みサービス') }} / {{ ($offer->program?->name ?? '削除済みプログラム') }}</td><td>{{ $offer->status }}</td>
<td>{{ $offer->referral_code ? 'コードあり' : '' }} {{ $offer->referral_url ? 'URLあり' : '' }}</td><td>{{ $offer->ends_at?->format('Y-m-d') ?? 'なし' }}</td><td>
@if(in_array($offer->status,['draft','rejected']))<form data-api method="post" action="/workspaces/{{ $workspace->public_id }}/offers/{{ $offer->public_id }}/submit"><button>審査申請</button></form>@endif
@if(in_array($offer->status,['draft','rejected']))<details><summary>編集</summary><form data-api data-include-empty="1" data-method="PATCH" method="post" action="/workspaces/{{ $workspace->public_id }}/offers/{{ $offer->public_id }}" class="row"><input type="hidden" name="referral_program_id" value="{{ $offer->referral_program_id }}"><label>タイトル<input name="title" value="{{ $offer->title }}"></label><label>コード<input name="referral_code" value="{{ $offer->referral_code }}"></label><label>URL<input name="referral_url" value="{{ $offer->referral_url }}"></label><label>期限<input type="datetime-local" name="ends_at" value="{{ $offer->ends_at?->format('Y-m-d\TH:i') }}"></label><button>保存</button></form><form data-api data-method="DELETE" method="post" action="/workspaces/{{ $workspace->public_id }}/offers/{{ $offer->public_id }}"><button>削除</button></form></details>@endif
@if($offer->status==='suspended' && auth()->user()->is_system_admin)<form data-api method="post" action="/admin/offers/{{ $offer->public_id }}/reopen"><button>下書きへ戻す</button></form>@endif
@if($offer->status==='rejected')<small>却下理由: {{ \App\Models\ReviewRequest::where('referral_offer_id',$offer->id)->latest()->value('decision_reason') }}</small>@endif
</td></tr>@empty<tr><td colspan="5">まだ紹介特典はありません。</td></tr>@endforelse
</tbody></table></section>

<section><h2>WordPress Site</h2><p>FREE では同意済み Site に関連する運営者特典が別枠の PR として追加される場合があります。本人の紹介特典を差し替えません。同意はいつでも撤回できます。</p><form data-api method="post" action="/workspaces/{{ $workspace->public_id }}/sites" class="row">
<label>サイト名<input name="name" required></label><label>Domain<input name="domain" placeholder="example.com" required></label><button>Siteを登録</button></form>
<table><thead><tr><th>Site</th><th>状態</th><th>FREE 同意</th><th>操作</th></tr></thead><tbody>
@forelse($sites as $site)<tr><td>{{ $site->name }}<br><small>{{ $site->domain }}<br>Site public ID: {{ $site->public_id }}</small></td><td>{{ $site->status }}</td>
<td>{{ $site->consents->whereNull('withdrawn_at')->isNotEmpty() ? '同意済み' : '未同意' }}</td><td>
<form data-api method="post" action="/workspaces/{{ $workspace->public_id }}/sites/{{ $site->public_id }}/consent" class="row">
<input type="hidden" name="terms_version" value="{{ config('sharetoku.consent_version') }}"><label><input type="checkbox" name="accepted" value="1" required>FREE 掲載条件 v{{ config('sharetoku.consent_version') }} に同意</label><button>同意</button></form>
<form data-api method="post" action="/workspaces/{{ $workspace->public_id }}/sites/{{ $site->public_id }}/withdraw"><button>同意撤回</button></form>
<form data-api method="post" action="/workspaces/{{ $workspace->public_id }}/sites/{{ $site->public_id }}/revoke"><button>Token失効</button></form>
<form data-api data-method="PATCH" method="post" action="/workspaces/{{ $workspace->public_id }}/sites/{{ $site->public_id }}" class="row"><label>Domain変更<input name="domain" value="{{ $site->domain }}"></label><button>更新</button></form>
<form data-api data-method="PUT" data-empty-array="category_ids" method="post" action="/workspaces/{{ $workspace->public_id }}/sites/{{ $site->public_id }}/category-exclusions" class="row"><label>除外 Category<select multiple name="category_ids[]">@foreach($categories as $category)<option value="{{ $category->id }}" @selected($exclusions->get($site->id,collect())->pluck('category_id')->contains($category->id))>{{ $category->name }}</option>@endforeach</select></label><button>除外を保存</button></form>
</td></tr>@empty<tr><td colspan="4">Siteはありません。</td></tr>@endforelse</tbody></table></section>

<section><h2>分析（過去30日）</h2><table><thead><tr><th>Site</th><th>Offer</th><th>枠</th><th>表示</th><th>クリック</th><th>CTR</th></tr></thead><tbody>
@forelse($metrics as $metric)<tr><td>{{ $metric->site_name }}</td><td>{{ $metric->offer }}</td><td>{{ $metric->slot }}</td><td>{{ $metric->impressions }}</td><td>{{ $metric->clicks }}</td><td>{{ $metric->impressions ? number_format(100 * $metric->clicks / $metric->impressions, 1) : '0.0' }}%</td></tr>
@empty<tr><td colspan="6">まだ集計データはありません。</td></tr>@endforelse</tbody></table></section>

@if(auth()->user()->is_system_admin || auth()->user()->workspaces()->wherePivot('role','reviewer')->exists())
<section><h2>審査待ち</h2>@forelse($reviews as $review)<div><p>Offer {{ $review->referral_offer_id }} · 申請 {{ $review->submitted_at }}</p>
<form data-api method="post" action="/reviews/{{ $review->public_id }}/decision" class="row"><input type="hidden" name="decision" value="approve"><button>承認</button></form>
<form data-api method="post" action="/reviews/{{ $review->public_id }}/decision" class="row"><input type="hidden" name="decision" value="reject"><label>却下理由<input name="reason" required></label><button>却下</button></form></div>
@empty<p>審査待ちはありません。</p>@endforelse</section>@endif

@if(auth()->user()->is_system_admin)
<section><h2>マスタ管理</h2>
<h3>Workspace プラン</h3><form data-api data-workspace-action="/admin/workspaces/:workspace/subscription" method="post" action="/admin/workspaces/{{ $workspace->public_id }}/subscription" class="row"><label>Workspace<select name="_workspace">@foreach($adminWorkspaces as $item)<option value="{{ $item->public_id }}">{{ $item->name }}</option>@endforeach</select></label><label>Plan<select name="plan_code"><option value="free">FREE</option><option value="pro">PRO</option></select></label><input type="hidden" name="status" value="active"><input type="hidden" name="starts_at" value="{{ now()->toIso8601String() }}"><button>プランを変更</button></form>
<h3>緊急停止（直近50件）</h3><table><thead><tr><th>対象</th><th>Workspace</th><th>状態</th><th>操作</th></tr></thead><tbody>
@foreach($adminOffers as $item)<tr><td>Offer: {{ ($item->program?->service?->name ?? '削除済みサービス') }} / {{ $item->public_id }}</td><td>{{ $item->workspace->name }}</td><td>{{ $item->status }}</td><td>@if($item->status === 'published')<form data-api method="post" action="/admin/offers/{{ $item->public_id }}/suspend" class="row"><label>停止理由<input name="reason" required></label><button>停止</button></form>@else<form data-api method="post" action="/admin/offers/{{ $item->public_id }}/reopen"><button>下書きへ戻す</button></form>@endif</td></tr>@endforeach
@foreach($adminSites as $item)<tr><td>Site: {{ $item->name }} / {{ $item->domain }}</td><td>{{ $item->workspace->name }}</td><td>{{ $item->status }}</td><td>@if($item->status === 'active')<form data-api method="post" action="/admin/sites/{{ $item->public_id }}/suspend" class="row"><label>停止理由<input name="reason" required></label><button>停止</button></form>@endif</td></tr>@endforeach
</tbody></table>
<h3>Category</h3><form data-api method="post" action="/master/categories" class="row"><label>名前<input name="name" required></label><label>slug<input name="slug" required></label><button>追加</button></form>
@foreach($categories as $category)<details><summary>{{ $category->name }}（{{ $category->slug }}）</summary><form data-api data-method="PATCH" method="post" action="/master/categories/{{ $category->public_id }}" class="row"><label>名前<input name="name" required value="{{ $category->name }}"></label><label>slug<input name="slug" required value="{{ $category->slug }}"></label><button>更新</button></form><form data-api data-method="DELETE" method="post" action="/master/categories/{{ $category->public_id }}"><button>無効化</button></form></details>@endforeach
<h3>Service</h3><form data-api method="post" action="/master/services" class="row"><label>名前<input name="name" required></label><label>slug<input name="slug" required></label><label>公式URL<input type="url" name="official_url"></label><label>Category<select multiple name="category_ids[]">@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label><button>追加</button></form>
@foreach($services as $service)<details><summary>{{ $service->name }}（{{ $service->slug }}）</summary><form data-api data-include-empty="1" data-empty-array="category_ids" data-method="PATCH" method="post" action="/master/services/{{ $service->public_id }}" class="row"><label>名前<input name="name" required value="{{ $service->name }}"></label><label>slug<input name="slug" required value="{{ $service->slug }}"></label><label>公式URL<input type="url" name="official_url" value="{{ $service->official_url }}"></label><label>Category<select multiple name="category_ids[]">@foreach($categories as $category)<option value="{{ $category->id }}" @selected($service->categories->contains('id',$category->id))>{{ $category->name }}</option>@endforeach</select></label><button>更新</button></form><form data-api data-method="DELETE" method="post" action="/master/services/{{ $service->public_id }}"><button>削除</button></form></details>@endforeach
<h3>Referral Program</h3><form data-api method="post" action="/master/programs" class="row"><label>Service<select name="service_id" required>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</select></label><label>名前<input name="name" required></label><label>被紹介者特典<input name="invitee_benefit_text"></label><label>規約URL<input type="url" name="official_terms_url"></label><label>公開<select name="public_listing_policy"><option value="needs_review">審査待ち</option><option value="approved">承認</option><option value="prohibited">禁止</option></select></label><label>外部配信<select name="external_distribution_policy"><option value="needs_review">審査待ち</option><option value="approved">承認</option><option value="prohibited">禁止</option></select></label><button>追加</button></form>
@foreach($allPrograms as $program)<details><summary>{{ ($program->service?->name ?? '削除済みサービス') }} / {{ $program->name }}</summary><form data-api data-include-empty="1" data-method="PATCH" method="post" action="/master/programs/{{ $program->public_id }}" class="row"><input type="hidden" name="service_id" value="{{ $program->service_id }}"><label>名前<input name="name" required value="{{ $program->name }}"></label><label>被紹介者特典<input name="invitee_benefit_text" value="{{ $program->invitee_benefit_text }}"></label><label>規約URL<input type="url" name="official_terms_url" value="{{ $program->official_terms_url }}"></label><label>公開<select name="public_listing_policy">@foreach(['approved','needs_review','restricted','prohibited','suspended'] as $policy)<option value="{{ $policy }}" @selected($program->public_listing_policy===$policy)>{{ $policy }}</option>@endforeach</select></label><label>外部配信<select name="external_distribution_policy">@foreach(['approved','needs_review','restricted','prohibited','suspended'] as $policy)<option value="{{ $policy }}" @selected($program->external_distribution_policy===$policy)>{{ $policy }}</option>@endforeach</select></label><label>条件・備考<input name="policy_notes" value="{{ $program->policy_notes }}"></label><button>更新</button></form><form data-api data-method="DELETE" method="post" action="/master/programs/{{ $program->public_id }}"><button>削除</button></form></details>@endforeach
<h3>Category 関連性</h3><form data-api data-method="PUT" method="post" action="/admin/category-relations" class="row"><label>元<select name="source_category_id">@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label><label>関連先<select name="target_category_id">@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label><label>関連スコア<input type="number" name="relevance_score" min="0" max="100" value="50"></label><input type="hidden" name="is_active" value="1"><input type="hidden" name="is_bidirectional" value="0"><label><input type="checkbox" name="is_bidirectional" value="1">双方向</label><button>関連性を保存</button></form>
</section>@endif

<script>
document.querySelectorAll('form[data-api]').forEach(function(form){
  form.addEventListener('submit',async function(event){
    event.preventDefault();
    var result=document.getElementById('result');result.textContent='保存中…';result.className='';
    var body={};if(form.dataset.emptyArray)body[form.dataset.emptyArray]=[];
    new FormData(form).forEach(function(value,key){
      if(value==='' && !form.dataset.includeEmpty)return;
      if(key.endsWith('[]')){var name=key.slice(0,-2);if(!body[name])body[name]=[];body[name].push(value);}
      else body[key]=value;
    });
    try{
      var endpoint=form.dataset.workspaceAction?form.dataset.workspaceAction.replace(':workspace',encodeURIComponent(body._workspace)):form.action;
      delete body._workspace;
      var response=await fetch(endpoint,{method:form.dataset.method||form.method.toUpperCase(),headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(body),credentials:'same-origin'});
      var data=await response.json().catch(function(){return {};});
      if(!response.ok){result.className='error';result.textContent=Object.values(data.errors||{}).flat().join(' ')||data.message||'保存に失敗しました。';return;}
      location.reload();
    }catch(e){result.className='error';result.textContent='通信に失敗しました。';}
  });
});
</script></body></html>
