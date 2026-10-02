# 公開前E2E手順（#26）

進行管理は#29、公開判定は#15。この手順の存在は実WordPressでの合格記録ではない。

## 対象と準備

- PR #30の不具合修正、PR #31の公開ポータルを含む対象commitを記録する。main反映後に最終確認する。
- Laravel / MySQL 8 / HTTPSの実WordPress、Googleテストユーザー、Workspace管理者と別reviewer、運営者Workspaceを用意する。
- WordPress・PHP・プラグインのversion、テーマ、ページキャッシュ/CDN、ブラウザー、時刻のtimezoneを記録する。
- 架空の紹介コードとURLだけを使う。Token・認可コード・Google credentials・実ユーザー情報は証跡に保存しない。
- 本人Offerと、関連する別Serviceの運営者Offerを審査・公開する。no-match用の関連なしServiceも用意する。
- テスト用Siteを登録し、FREE同意を確認してからWordPress管理画面の接続を実行する。Googleログイン→PKCE callback→接続成功まで確認する。

## MySQL自動検証

CIの`mysql-migrations` jobでmigration→rollback→re-migrate後にアプリの全テストを実行する。`DB_CONNECTION=mysql`等はjobの環境変数で指定し、`phpunit.xml`のSQLite初期値を上書きする。

ローカルでは専用の使い捨てDBを指定して`php artisan test --compact --do-not-cache-result`を実行する。RefreshDatabaseはDBを再構築するため、本番・共有DBを指定しない。config cacheを使わず、接続先を確認してから実行する。

## 実機シナリオ

| 項目 | 操作と期待値 | 保存する証跡 |
| --- | --- | --- |
| A: FREE match | Googleログイン、Offer作成、別reviewer承認、Site登録、同意、接続、Block/shortcode設置。本人と別Serviceの運営者特典、PR表示、impression/clickを確認 | 表示画面、匿名化した集計値、対象public ID |
| B: FREE no match | 運営者候補のない本人Offerを設置。本人だけ表示 | 候補設定と表示画面 |
| C: 同意撤回 | 同意を撤回し、次の有効resolveまたはcache期限後に運営者枠が消える | 撤回時刻、expires_at、消えた時刻 |
| D: PRO | PROへ変更。運営者枠がなく本人だけ表示 | planと表示画面 |
| E: 緊急停止 | Offer、Program、Siteを別々に停止し、global kill switchも確認。Site停止後のドメイン変更で停止が解除されない。運営者全体OFFでは本人枠を保持 | 各停止時刻と最終表示時刻、API status、TTL |
| F: API障害 | 検証環境のAPI通信だけを一時遮断。fresh cacheは表示、期限切れcacheは非表示、WordPress本文は正常 | 障害時刻、cache期限、HTTP status、画面 |

E/Fではプラグインのresolve cache上限300秒と応答`expires_at`を記録し、キャッシュを手動削除せず実際の失効を測る。ページ全体のキャッシュ/CDNがある場合はその残存時間も別に記録し、上限を超えたら公開ブロッカーとしてIssue化する。各シナリオ終了時に設定・通信を戻す。

## Editor・操作・公開ポータル

1. Gutenbergで検索→選択→preview→保存→再編集し、選択Offerが保持されることを確認する。FREE/PROのpreviewを比較し、editor previewで計測が増えないことも確認する。
2. shortcodeとblockの両方でPC/スマホ表示、コードのコピー→別の入力欄への貼り付け、外部リンクのhref・開き方・PR表示を確認する。
3. 接続解除→再接続を実施する。Token revoke後の401、Site停止後の403でもローカル解除できることを確認する。通信失敗時は再試行できることを確認する。
4. ポータルの検索、カテゴリ、Service/Offer/公開プロフィール、公開撤回後404を確認する。Googleの内部氏名・email・Workspace名が漏れていないことを確認する。
5. profile公開設定は管理者だけが変更できることを確認する。公開特典が外部配信policyだけで誤って公開・非公開にならないことを確認する。

## 記録様式と現状

各項目に「対象commit / 実施日時 / 環境 / 期待値 / 実測値 / 証跡 / 合否 / 失敗Issue」を記録する。未実施はPendingのまま保持する。

2026-10-02の自動検証結果:

| 対象 | SQLite | MySQL 8.0.46 |
| --- | --- | --- |
| PR #30 不具合修正 | 52 tests / 382 assertions成功 | 同52 tests / 382 assertions成功 |
| PR #31 公開ポータル | 38 tests / 370 assertions成功 | 同38 tests / 370 assertions成功 |
| 本PR 運用検証 | 34 tests / 278 assertions成功 | 同34 tests / 278 assertions成功 |

各PRは独立したmain `722241f`からの変更であり、統合後の合計テスト数ではない。PR #31ではローカルブラウザーの検索・表示・公開撤回404・390px表示・コピー成功表示まで確認した。in-app browserの仮想clipboardでコピー内容の貼り付けは確認できていない。実ブラウザーでの貼り付けと実WordPress全項目はPending。実機サイトのURL・利用可能な環境が必要。
