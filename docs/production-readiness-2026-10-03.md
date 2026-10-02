# 本番運用の読み取り確認（#28、2026-10-03 JST）

対象: main `532a1003e2295c544ced223550961f9123e9a628`の本番release。SSHとアプリの有効設定を読み取り確認した。DB復元、秘密情報の変更、scheduler手動実行、設定変更は行っていない。

証跡: [安全な設定・metadata JSON](evidence/production-readiness-2026-10-03.json)。APP_KEY、DB接続先・資格情報、利用者情報、Token、ログ本文、crontab本文を出力していない。

| 項目 | 実測 | 判定 |
| --- | --- | --- |
| PHP / MySQL | 8.3.32 / 8.0.42 | 確認済み |
| APP_ENV / debug | production / false | 確認済み |
| APP_URL / secure cookie | https://share-toku.shikode.com / true | 設定確認済み |
| session | database | 設定確認済み、rotation実証は未完了 |
| shared/.env | permission 0600 | 確認済み |
| operator kill switch | OFF | 公開前の停止状態 |
| operator Workspace | 未設定 | 公開前に運営者が選定・設定する必要あり |
| cron | 本アプリのschedule:runが1件、毎分 | 登録確認済み |
| offers:expire | 毎時0分、UTC | 登録確認済み |
| analytics:aggregate | 01:00 UTC（10:00 JST） | 登録確認済み |
| analytics:prune | 02:00 UTC（11:00 JST） | 登録確認済み |
| raw analytics retention | 90日 | 設定確認済み、実行実績未確認 |
| Laravel logging | stack→single | ファイルrotation・保存期間の運用証跡が必要 |
| アプリbackupディレクトリ | shared/backupsにSQL 1件、6,898 bytes、0600 | 初期構築のfailed-schemaファイル。定期backupや復元可能性の証明には使えない |

cron登録は、cron daemonの実行成功・3commandの正常終了・失敗通知を保証しない。dailyチャンネルを使っていないため、その保存日数設定ではsingleログの保存期間を証明できない。ホスティング側のbackupやアクセスログ運用は、このアプリのディレクトリ確認から推測しない。

## 次に完了させる作業

1. 日次の整合性あるMySQL backup、暗号化、別保管先、保持期間、失敗通知の担当と方式を確定し、直近backupの成功・復号可能性を確認する。
2. 保護された隔離環境への本番backup復元訓練を実施する。外部送信・schedulerを止め、本番Tokenを公開検証環境へ流用しない。対象backup、commit、所要時間、migration / health / Workspace境界 / Offer / Site APIを記録する。前回の架空fixture復元成功を本番復旧証拠として扱わない。
3. cronの実行結果と失敗通知を確認する。expire / aggregate / pruneの各成功を匿名化した集計で記録する。
4. singleログ、Webアクセスログ、日次集計・監査・backupの保存期間を決め、#27のprivacy記載と一致させる。
5. 運営者Workspaceを設定し、公開承認まで運営者枠OFFを維持する。検証環境のsession/key rotation・rollbackと実WP再接続の証跡を揃える。

読み取り確認用スクリプト: `tests/manual/production-readiness.php`。release rootは`SHARETOKU_RELEASE_ROOT`で指定可能。SSH先でstdinからPHPを実行し、結果を保存する。Laravelをbootstrapし、そのプロセスのcacheのみarrayにして` schedule:list `でコマンド登録を初期化する。DB書き込みの定期コマンドを実行せず、秘密値とbackup内容を出力しない。

本番運用Gateは未完了。#28と#15をOPENに保つ。
