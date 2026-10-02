<!doctype html><html lang="ja"><meta charset="utf-8"><title>公開プロフィール | ShareToku</title><main><h1>公開プロフィール</h1><p>ここで入力した表示名・自己紹介・Webサイトのみ公開されます。公開を許可すると、掲載可能な特典がポータルに表示されます。</p>
@if(session('status'))<p>{{ session('status') }}</p>@endif
@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
<form method="post">@csrf @method('PUT')
<p><label>公開URLの名前 <input name="public_slug" required value="{{ old('public_slug', $profile?->public_slug) }}" pattern="[a-z0-9]+(-[a-z0-9]+)*"></label></p>
<p><label>表示名 <input name="display_name" required value="{{ old('display_name', $profile?->display_name) }}"></label></p>
<p><label>自己紹介 <textarea name="bio">{{ old('bio', $profile?->bio) }}</textarea></label></p>
<p><label>Webサイト <input name="website_url" type="url" value="{{ old('website_url', $profile?->website_url) }}"></label></p>
<input type="hidden" name="is_public" value="0"><p><label><input type="checkbox" name="is_public" value="1" @checked(old('is_public', $profile?->is_public))>公開を許可する</label></p><button>保存</button></form><a href="/dashboard">管理画面に戻る</a></main></html>
