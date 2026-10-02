# 運用検証記録（#28）

2026-10-02、main `722241f`を基準に検証。本番データ・本番Token・本番credentialsは使用していない。一般公開判定は#15。

## 隔離MySQLの復元訓練

環境: PHP 8.5.9 / MySQL 8.0.46、Unix socketのみ、ネットワーク接続なし。専用のsource DBから別のrestore DBへ復元した。データは架空User、administratorメンバー、Workspace、Service、Program、Offer、Site、hash化したテストToken、Placement各1件と計測fixture。

1. migrationを実行し、終了済みpublished Offer、昨日のeventと91日前のeventを登録。
2. `offers:expire` / `analytics:aggregate` / `analytics:prune`を実行。Offerがexpired、監査1件、昨日の集計1件、古いraw event削除を確認。
3. `mysqldump --single-transaction --no-tablespaces --set-gtid-purged=OFF`でbackup作成。
4. 別DBへSQLを復元し、`php artisan migrate --force`がNothing to migrateであることを確認。
5. 復元先でLaravel HTTP kernelによる`/health`とscope `site:read`の`/api/v1/site/me`を確認。いずれも200。
6. 13テーブル（User / Workspace / member / Service / Program / Offer / Site / Token / Placement / raw event / daily metric / audit / migrations）の件数とデータSHA-256が復元前後で一致。Tokenの`last_used_at`を比較するため検証時計は固定した。

結果: dump 41,704 bytes、backup＋restore＋照合0.90秒、restore＋照合0.84秒。比較SHA-256: `2201de0cd0aa96738d465e3e22ec01647d0affe1603f8e541869a5ebf600fb99`。これは最小fixtureの所要時間であり、本番データ量のRTOではない。

この訓練では外部Googleログイン、Workspace越境、WordPress再接続、暗号化された定期backupの保管・通知は未検証。本番backupからの復元成功記録ではない。

## 定期処理と保存期間

`OperationsTest`で次をSQLiteとMySQLで確認した:

- 期限切れpublished Offerのみexpiredへ変更。未来のOfferとdraftは保持。再実行してもexpire監査は重複しない。
- 90日境界より古いraw eventだけを削除。境界のevent・新しいevent・daily metricは保持。再実行可能。
- APP_KEY変更で旧event tokenは無効。旧keyへ戻すと旧tokenが再び有効、新keyのtokenは無効。

`ConnectionAndAnalyticsTest`では集計の再実行と遅延clickの再集計を確認している。全34 tests / 278 assertions、Pint、PHPStan成功。

`CACHE_STORE=array php artisan schedule:list`で登録を確認:

| command | schedule |
| --- | --- |
| offers:expire | 毎時0分 |
| analytics:aggregate | 毎日01:00 |
| analytics:prune | 毎日02:00 |

実行時刻はアプリ設定のtimezoneによる。登録の確認は本番cronの稼働証明ではない。本番では毎分の`schedule:run`、各commandの終了status・最終成功時刻、失敗通知を確認する。raw analyticsの設定値、アクセスログ、daily metric、監査、backupの保存期間を#27の文書と一致させる。

## 本番で残る確認

| 項目 | 必要な確認 |
| --- | --- |
| 定期backup | 日次実行、暗号化、別保管先、保存期間、復元権限、失敗通知 |
| 復元 | 対象backup / commit / 開始終了時刻を記録。隔離先でmigration・health・認証設定・Workspace越境・sample Offer・Site API確認 |
| 緊急停止 | #26でOffer / Program / Site / global kill switchの実WP反映時間を測定 |
| Token | revoke→旧Token拒否→プラグイン解除→再接続を確認 |
| key変更 | 検証環境で新規keyを保管・反映・config cache更新。既存session cookie / event tokenへの影響、再ログイン・新規event発行を実ブラウザー確認 |
| key rollback | keyの変更前状態と復帰手順を記録。旧keyへ戻すと旧event署名が再び受理されるため、漏洩対応で安易に戻さない。Site TokenはAPP_KEYとは別のhash認証で、必要時に個別revoke |
| 本番設定 | HTTPS、APP_DEBUG=false、secure cookie、operator Workspaceとkill switch設定 |

本番DBへの復元や本番secretの変更はこの検証では行っていない。復元先でメール・scheduler・外部連携を停止し、検証アクセスを制限してから本番backupを扱う。本番Tokenを外部公開の検証環境へ流用しない。
