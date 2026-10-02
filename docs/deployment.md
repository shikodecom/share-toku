# 本番CD（SSH・さくらレンタルサーバー）

本番URLは `https://share-toku.shikode.com` です。`main` へのpushで既存のCIがすべて成功すると、`Deploy production` が同じコミットをSSHで配置します。PRや他ブランチのCIからは配信しません。新しいmainがある場合、古いコミットの配信はスキップします。

## GitHub設定

Settings → Environments に `production` を作り、Deployment branchesを `main` に制限します。次のEnvironment secretsを登録します。既存のさくら向けCDと同じ `PRODUCTION_*` の名前を使用していますが、設置先はShareToku専用にしてください。

| Secret | 内容 |
| --- | --- |
| `PRODUCTION_HOST` | SSHホスト名 |
| `PRODUCTION_PORT` | SSHポート（省略時22） |
| `PRODUCTION_USER` | SSHユーザー |
| `PRODUCTION_SSH_KEY` | SSH秘密鍵。公開鍵をサーバーに登録 |
| `PRODUCTION_SSH_KNOWN_HOSTS` | 確認済みSSHホスト鍵のknown_hosts行。非標準ポートは `[host]:port` 形式 |
| `PRODUCTION_DEPLOY_PATH` | 非公開領域の専用絶対パス。例 `/home/f-taniguchi/apps/share-toku` |

Environment variable `PRODUCTION_PHP_BIN` に、サーバーのPHP 8.3以上のCLI実行パスを指定できます。省略時は `php` です。パスは英数字、`_`、`.`、`/`、`-`のみで、空白や `..` を含めません。

ホスト鍵はサーバー管理画面などの信頼できる情報と照合して登録します。GitHub runnerで自動取得して無条件に信用する処理はありません。

## サーバー初回準備

PHP 8.3以上（CLIとWeb両方）、intl、mbstring、PDO MySQL、MySQL 8以上、bash、tar、curl（`--retry-all-errors`対応）、SSH/SCP、シンボリックリンクが必要です。ComposerはGitHub runnerで実行するためサーバーへのインストールは不要です。

設置先を以下の構成にします。リリース全体を公開領域へ置かず、サブドメインの公開先は **`current/public` のみ** に設定します。本番公開ディレクトリ `/home/f-taniguchi/www/shikode/share-toku` は `/home/f-taniguchi/apps/share-toku/current/public` へのシンボリックリンクにします。さくらの公開先指定がwww配下に限定される場合、専用公開ディレクトリから `current/public` へのシンボリックリンクを設定し、Apacheがそのリンクを辿れることを確認してください。既存サイトのディレクトリには配置しません。

```text
/home/f-taniguchi/apps/share-toku/
  shared/
    .env         # サーバーで用意する本番設定
    storage/     # CDが作成・引き継ぎ
  incoming/      # 一時アップロード
  releases/      # コミットごとのコードとvendor
  current -> releases/<release-id>
```

1. `shared/.env` を `.env.example` に基づいて作成し、`APP_ENV=production`、`APP_DEBUG=false`、`APP_URL=https://share-toku.shikode.com`、`SESSION_SECURE_COOKIE=true`、本番DB・メール設定を保存します。`APP_KEY` は初回に生成して以後保持します。`SHARETOKU_OPERATOR_OFFERS_ENABLED=false` は公開チェック完了まで維持します。
2. `.env` は所有者だけが読み書きできる権限にし、WebのPHP実行ユーザーが読み取れることを確認します。`shared/storage` と各リリースの `bootstrap/cache` はWebのPHP実行ユーザーにも書き込み権限が必要です。
3. 本番DBを作成し、初回以降は配信前にバックアップを取得します。CDは `php artisan migrate --force` で未実行マイグレーションを適用します。
4. DNS、さくらのサブドメイン設定、TLS証明書を設定します。Apacheの `.htaccess` とシンボリックリンクを有効にします。TLSや公開先の設定が未完了だとヘルスチェックが失敗します。
5. cronに、指定PHP CLIで `current/artisan schedule:run` を毎分実行する設定を追加します。キューワーカーを使用する場合は別途常駐設定が必要です。CDの `queue:restart` は既存ワーカーに再起動を通知します。
6. 設定後、mainへpushしてActionsの `CI` → `Deploy production` の成功と `/up`、`/health`、ログインを確認します。設定前に失敗したCDはGitHubのRe-run jobsから再実行できます（対象コミットが現在のmainの場合のみ）。

## 配信と障害時

runnerで本番用vendorを含むアーカイブを作成し、サーバーで `.env` とstorageを接続します。パッケージ検出、設定・ルート・ビューキャッシュ、storageリンク、DBマイグレーションを実行した後、`current` を原子的に切り替えます。WordPressプラグインは別サイトへのインストールなので、このCDには含めません。

公開TLS経由の `/up` が失敗した場合や切り替え後の処理が失敗した場合は、直前のコードへ戻してCDを失敗にします。初回配信の失敗時は `current` を取り除きます。DBマイグレーションは戻しません。旧コードも動く後方互換のDB変更を使い、破壊的変更は別途計画してください。`/up` はLaravelの起動確認なので、業務機能の動作確認は別途必要です。

古いリリースは手動ロールバック用に残します。ディスク使用量を確認し、現在と直前のリリースを残して不要なものを削除してください。強制終了で `.deploy.lock` ディレクトリが残った場合は、サーバーで実行中のデプロイがないことを確認してからその空ディレクトリを削除します。

PHPのOPcacheやrealpathキャッシュが旧コードを保持するサーバーでは、コントロールパネルのPHP再起動など、提供サービスの方法でキャッシュを更新してください。

このワークフローの追加だけではGitHub Secrets、DNS、TLS、サーバー設定は作成されません。一般公開の判定は [release checklist](release-checklist.md) に従います。
