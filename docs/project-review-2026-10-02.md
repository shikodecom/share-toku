# Issue・PR・ソースの現状確認

確認日: 2026-10-02（JST）。GitHub上のIssue全件、PR全件、最新mainのCI/CD、ローカルのソース・作業中差分を照合した記録。

本文は実装着手前の調査スナップショット。着手後の変更は末尾に追記する。過去の「未修正」「PR未作成」を現在の進捗として扱わない。

## 現在の基準

- GitHub main: `722241f42809177ce01ca6133814f92e31b4a736`（PR #20マージ）。
- ローカルHEAD: `6eeb6aa`、branch `codex/google-oauth-authentication`。この認証変更はPR #20でmain反映済み。
- 作業ツリー: 公開ポータル関連の未コミット変更とGoogle OAuth検証記録の変更がある。追跡済み7ファイルの変更に加えて、Controller・Model・Service・migration・view・testの新規ファイルが存在する。
- 調査前からある作業中変更を保持し、今回のソース側成果物は本記録のみ追加した。不具合の製品コード修正、コミット、PR作成・マージ、デプロイは行っていない。
- 整理後のIssueは23件（OPEN 22件、CLOSED 1件）。PRは5件（MERGED 4件、OPEN 1件）。一般公開は未承認。

## PR

| PR | 状態 | mainへの影響 / 次の作業 |
| --- | --- | --- |
| [#16 本番CD・PHP 8.3対応](https://github.com/shikodecom/share-toku/pull/16) | MERGED | 本番配信基盤 |
| [#17 MySQLインデックス名修正](https://github.com/shikodecom/share-toku/pull/17) | MERGED | MySQL migration / rollback / re-migrate CI |
| [#18 Issueと実装の照合・削除済みマスタ対応](https://github.com/shikodecom/share-toku/pull/18) | MERGED | 追跡資料と削除済みマスタの回帰修正 |
| [#20 Google OAuth](https://github.com/shikodecom/share-toku/pull/20) | MERGED | #19完了。password DB構造の削除は#21 |
| [#22 Google OAuth本番検証記録](https://github.com/shikodecom/share-toku/pull/22) | OPEN | CI全成功、MERGEABLE、レビュー提出なし。docsの2ファイルのみで、公開ポータルを含まない。レビュー後の反映候補 |

mainの[CI 36980624335](https://github.com/shikodecom/share-toku/actions/runs/36980624335)と[本番CD 36980714305](https://github.com/shikodecom/share-toku/actions/runs/36980714305)は成功。CI/CD成功は一般公開の承認を意味しない。

## 既存Issueとソース

| Issue | ソース / 状態 | 次の作業 |
| --- | --- | --- |
| [#1 基盤](https://github.com/shikodecom/share-toku/issues/1) | composer、CI、health、設計資料はmainに存在 | 新規cloneからの起動手順再現と受け入れ記録 |
| [#2 認証・Workspace](https://github.com/shikodecom/share-toku/issues/2) | AuthController、WorkspaceAccess、GoogleAuthenticationTest。認証は#19でGoogle専用へ置換 | 旧パスワード仕様を現行と区別。ロール別・削除Workspace・全リソース越境確認 |
| [#3 マスタ](https://github.com/shikodecom/share-toku/issues/3) | MasterController、Category、Service、ReferralProgram | CRUD・slug・権限・policy確認、期間更新修正#24 |
| [#4 特典CRUD](https://github.com/shikodecom/share-toku/issues/4) | OfferController、ReferralOffer、OfferContent | CRUD・URL・override確認、期間更新修正#24 |
| [#5 審査・停止](https://github.com/shikodecom/share-toku/issues/5) | ReviewController、ReviewFlowTest、offers:expire、Audit | 却下・不正遷移・ロール・policy全ケースの確認 |
| [#6 Site・同意](https://github.com/shikodecom/share-toku/issues/6) | SiteController、SiteConsent | 停止状態の維持#23、同意版・権限・撤回確認 |
| [#7 Site認証](https://github.com/shikodecom/share-toku/issues/7) | PKCE exchange、AuthenticateSiteToken、WP callback | #23・#25、期限・scope・停止・実callback確認 |
| [#8 マッチング](https://github.com/shikodecom/share-toku/issues/8) | OperatorMatcher、MatchingSettingsController | 関連カテゴリ、同一サービス除外、tieの安定性確認 |
| [#9 配信API](https://github.com/shikodecom/share-toku/issues/9) | DistributionController、EventToken、API routes | 全エラー・scope・policy・非公開情報の非露出確認 |
| [#10 WP plugin](https://github.com/shikodecom/share-toku/issues/10) | PHP plugin、frontend、renderer checks | 失効Token解除#25、実機E2E#26 |
| [#11 Gutenberg](https://github.com/shikodecom/share-toku/issues/11) | editor.js、block.json、build、REST proxy | 実editorの検索→選択→保存→再編集、FREE/PRO previewを#26で検証 |
| [#12 分析](https://github.com/shikodecom/share-toku/issues/12) | AnalyticsController、aggregate/prune、frontend計測 | 時刻・batch・期限確認、実scheduler/retentionを#28で確認 |
| [#13 プラン](https://github.com/shikodecom/share-toku/issues/13) | EntitlementService、ManualSubscriptionManager、EntitlementTest | Offer上限・期限切れsubscription等の受け入れ確認 |
| [#14 公開ポータル](https://github.com/shikodecom/share-toku/issues/14) | **作業ツリーのみ**。PortalController、PortalQuery、PublicProfileController、WorkspacePublicProfile、公開view、migration、PortalTest | 既存変更を専用PRへまとめ、MySQL・実ブラウザー検証後にmainへ反映 |
| [#15 公開判定](https://github.com/shikodecom/share-toku/issues/15) | OPEN。実機・公開文書・運用の未完了項目あり | #23〜#28、#14、既存受け入れ条件、npm Moderate findings評価を追跡 |
| [#19 Google OAuth](https://github.com/shikodecom/share-toku/issues/19) | CLOSED。PR #20・main反映済み。通常の本番ログイン結果は#22に記録 | 公開設定は#27、DB cleanupは#21 |
| [#21 password DB cleanup](https://github.com/shikodecom/share-toku/issues/21) | OPEN。意図的な後続作業 | Google認証安定稼働・復旧確認後。一般公開の通常ブロッカーではない |

#1〜#13の主要実装は`93fff58`で直接mainに追加された。OPENを未着手と見なすと重複実装になる。一方、コードの存在だけで受け入れ条件全件の完了にはしない。

## 再現した不具合と新規Issue

| 優先度 | Issue | 確認結果 |
| --- | --- | --- |
| P1 | [#23 Siteの緊急停止を維持](https://github.com/shikodecom/share-toku/issues/23) | system admin停止→所有者ドメイン更新でpending→authorize/exchangeでactiveへ戻ることをSQLite Feature probeで確認 |
| P2 | [#24 期間の部分更新検証](https://github.com/shikodecom/share-toku/issues/24) | starts_at=現在+2日を保存したOffer / Programへ、starts_at省略・ends_at=現在+1日でPATCHすると200、期間逆転が保存される |
| P2 | [#25 WPの失効Token解除](https://github.com/shikodecom/share-toku/issues/25) | disconnectの401/403 mock responseでwp_dieし、ローカルToken削除処理に到達しない |
| P1 | [#26 MySQL・実WordPress E2E](https://github.com/shikodecom/share-toku/issues/26) | #15のシナリオA〜F・実callback・editor・portal検証を具体化 |
| P1 | [#27 公開文書・問い合わせ・OAuth公開準備](https://github.com/shikodecom/share-toku/issues/27) | #15の公開導線とGoogleテストモード解除条件を具体化 |
| P1 | [#28 復元訓練・scheduler・失効検証](https://github.com/shikodecom/share-toku/issues/28) | #15の運用検証と記録を具体化 |

#23〜#25の対象製品コードは作業ツリーとmainで同一。ローカルの公開ポータル変更による新規不具合ではない。#26〜#28は#15の既存残作業を分割した子作業として、親Issue本文から追跡する。

既存#2・#3・#4・#6・#7・#10・#14・#15の本文に最新状態と関連リンクを追記した。既存受け入れ条件は保持し、未完了Issueを閉じていない。

## 今回の検証

- SQLite PHPUnit: **35 tests / 349 assertions成功**。未コミットの公開ポータル実装を含む作業ツリーが対象。これをmainのテスト件数として扱わない。
- Pint成功、PHPStan成功（errors 0）、WordPress PHP renderer / frontend JS / block JS checks成功。
- 調査用SQLite Feature probe: 2 tests / 11 assertions成功。#23の停止解除と#24のOffer / Program期間逆転という、現在の誤った動作が再現することを確認したテストであり、修正済みの証明ではない。
- WP disconnect: 401 / 403のmock PHP probeで確認。実WordPressを操作した結果ではない。
- テストはDB_DATABASE=:memory:、VIEW_COMPILED_PATHを一時ディレクトリ、LOG_CHANNEL=stderrにして実施。PHPStanはsandboxの並列通信制約を避けるため--debugを使用。JS checksはbundled Nodeを利用。
- ローカルPHPは8.5.9。PHP 8.3とMySQL migrationのCI証跡は上記main CIで確認。今回の調査では新しい実MySQLアプリテスト・WP実機E2E・本番復旧訓練は実施していない。

## 次に進める順序

1. #23を最優先で修正する。#24・#25も公開前E2Eに先立って修正する。
2. #22の検証記録をレビューする。#14の既存ポータル変更を専用PRとして整理する。
3. #27の公開文書・導線と#28の運用準備を進める。
4. 修正とポータル反映後、#26のMySQL・実WordPress・実ブラウザー検証を完走する。
5. #1〜#13と#15の受け入れ条件を証跡で確認し、一般公開を判断する。#21は安定稼働後に着手する。

既存のimplementation-status.md、architecture.md、operations.md、release-checklist.mdには過去の基準コミットや「未実装」「予定」等の記録がある。#22反映・#14のPR化時にmainの実状態へ更新する。READMEの作業中ポータル説明だけから、本番にポータルが反映済みとは判断しない。

## 着手後の進捗（2026-10-02）

親Issue [#29](https://github.com/shikodecom/share-toku/issues/29)で進行管理し、元の未コミット変更を保持したままmain `722241f`から独立したブランチを作成した。

| PR | 対象 | 検証と残作業 |
| --- | --- | --- |
| [#30](https://github.com/shikodecom/share-toku/pull/30) / `8f0cc27` | #23〜#25: Site停止維持、既存値を含む期間検証、失効TokenでもWPローカル解除 | SQLite / MySQL各52 tests / 382 assertions、WP解除8シナリオ、CI成功。レビュー・main反映待ち |
| [#31](https://github.com/shikodecom/share-toku/pull/31) / `c586f3c` | #14: 公開プロフィールを初期非公開にしたポータル | SQLite / MySQL各38 tests / 370 assertions、ローカルブラウザー確認、CI成功。実コピー貼り付け・WP総合検証は#26 |
| [#32](https://github.com/shikodecom/share-toku/pull/32) | #26〜#28: MySQLアプリCI、運用テスト、検証手順と証跡 | SQLite / MySQL各34 tests / 278 assertions、架空データの隔離復元成功。実WordPress・本番backup/cron・公開文書は未完了 |

Pint / PHPStan / diff checks成功。テスト数は独立した各PRの結果であり、統合後の合計ではない。公開準備と残作業の詳細は[e2e-runbook.md](e2e-runbook.md)、[operations-validation.md](operations-validation.md)、[publication-preparation.md](publication-preparation.md)を参照。

一般公開は未承認。実WordPressのURL・利用可能な環境、公開する運営者名・問い合わせ先が必要。PRを作成しただけでは#14/#23〜#28や親Issueを完了にしない。元の認証検証記録はPR #22に分離して維持し、マージ・本番デプロイは今回行っていない。
