# Issue・PR・ソース対応表

確認日: 2026-10-02（JST）。公開ポータルの基準: main `722241f` と本PR。下記の過去のCI・PR記録は当時の記録として保持。

Issue の OPEN は「未着手」を意味しません。#1〜#13 は `93fff58` で主要実装が main に直接入りましたが、受け入れ条件・必須テスト全件の完了記録がありません。コードが存在することと、全条件を検証済みであることを区別します。今回の見直しでは未確認条件を一括でチェックしたり、Issue を一括で閉じたりしません。

| Issue | 実装の根拠 | 自動検証の根拠 | 残作業 / 状態 |
| --- | --- | --- | --- |
| [#1 基盤](https://github.com/shikodecom/share-toku/issues/1) | `composer.json`, `.github/workflows/ci.yml`, `/health`, `docs/architecture.md` | `ShareTokuFlowTest`, remote CI の MySQL migrate/rollback/re-migrate | 基盤実装・CI済み。新規 clone からの手順再現の受け入れ記録が必要 |
| [#2 認証・Workspace](https://github.com/shikodecom/share-toku/issues/2) | `AuthController`, `WorkspaceController`, `WorkspaceAccess`, `WorkspaceRole` | `GoogleAuthenticationTest` Google OAuth・失敗・ログアウト、`ShareTokuFlowTest` Workspace 越境 | #19でGoogle OAuth専用へ置換。本番Googleログイン・ロール別・削除 Workspace の検証は別途 |
| [#3 マスタ](https://github.com/shikodecom/share-toku/issues/3) | `MasterController`, `Category`, `Service`, `ReferralProgram`, `Publishability` | `DeletedMasterTest` 削除後の画面・審査・配信 | 実装済み。CRUD 全経路、slug 重複、権限・policy の専用テスト不足 |
| [#4 特典 CRUD](https://github.com/shikodecom/share-toku/issues/4) | `OfferController`, `ReferralOffer`, `OfferContent` | `ShareTokuFlowTest`, `ReviewFlowTest`, `DeletedMasterTest` | 実装済み。CRUD・期間・URL 入力の全条件を網羅する専用テスト不足 |
| [#5 審査・停止](https://github.com/shikodecom/share-toku/issues/5) | `ReviewController`, `ReviewRequest`, `Audit`, `offers:expire` | `ReviewFlowTest`, `DeletedMasterTest` | 実装済み。却下・不正遷移・ロール・policy 全ケースの検証不足 |
| [#6 Site・同意](https://github.com/shikodecom/share-toku/issues/6) | `SiteController`, `SiteConsent`, `SiteConnectionController` | `ShareTokuFlowTest`, `ConnectionAndAnalyticsTest` | 実装済み。consent API の版・権限・撤回と停止の専用テスト不足 |
| [#7 接続認証](https://github.com/shikodecom/share-toku/issues/7) | `SiteConnectionController`, `AuthenticateSiteToken`, plugin callback | `ConnectionAndAnalyticsTest` PKCE・再利用・revoke・domain競合 | 実装済み。期限切れ・scope・停止 Site の全ケースと実 WordPress callback 未検証 |
| [#8 マッチング](https://github.com/shikodecom/share-toku/issues/8) | `OperatorMatcher`, `MatchingSettingsController` | `ShareTokuFlowTest` 同一カテゴリ・no match・除外・kill switch | 実装済み。関連カテゴリ・同一サービス除外・tie 安定性の専用テスト不足 |
| [#9 配信 API](https://github.com/shikodecom/share-toku/issues/9) | `DistributionController`, `routes/api.php`, `EventToken` | `ShareTokuFlowTest`, `ConnectionAndAnalyticsTest` | 実装済み。全エラー・scope・policy・機密非露出ケースの検証不足 |
| [#10 WP plugin](https://github.com/shikodecom/share-toku/issues/10) | `sharetoku.php`, `includes/class-sharetoku-plugin.php`, `assets/` | `tests/render.php`, `tests/frontend.cjs`, remote CI | 実装済み。実機接続、shortcode、PC/スマホ、キャッシュ障害・切断/再接続 E2E 未完了 |
| [#11 Gutenberg](https://github.com/shikodecom/share-toku/issues/11) | `src/editor.js`, `src/block.json`, `build/`, plugin REST proxy | `tests/block.cjs`, remote CI build一致 | 実装済み。実 editor で検索→選択→保存→再編集、FREE/PRO preview 未検証 |
| [#12 分析](https://github.com/shikodecom/share-toku/issues/12) | `AnalyticsController`, `EventToken`, `routes/console.php`, frontend JS | `ConnectionAndAnalyticsTest`, `tests/frontend.cjs` | 実装済み。期限・batch・時刻制限等の専用テスト、実 scheduler/retention 確認不足 |
| [#13 プラン](https://github.com/shikodecom/share-toku/issues/13) | `EntitlementService`, `ManualSubscriptionManager`, `SubscriptionManager` | `EntitlementTest` FREE/PRO・Site上限・降格・audit | 実装済み。Offer 上限・期限切れ subscription の専用テスト不足 |
| [#14 公開ポータル](https://github.com/shikodecom/share-toku/issues/14) | `PortalController`, `PortalQuery`, `WorkspacePublicProfile`, 公開view、プロフィール設定 | `PortalTest` 検索・カテゴリ・policy分離・期間・privacy・XSS・URL・安定順・公開撤回・role | 本PRで実装。SQLite / MySQL各38 tests / 370 assertions、ブラウザー検索・表示・コピー成功表示・公開撤回を確認。実WP等の総合検証は#26 |
| [#15 公開判定](https://github.com/shikodecom/share-toku/issues/15) | `docs/security-threat-model.md`, `docs/operations.md`, `docs/release-checklist.md` | remote CI、部分的な統合テスト | 継続中。#14、WordPress E2E、規約・問い合わせ、backup/restore 等が未完了。一般公開未承認 |

## 公開ポータルの検証（2026-10-02）

本PRはローカル作業中だった#14の実装をmain `722241f`から独立させた。#19のGoogle認証は既にmain反映済みで、PR #22の本番検証記録変更は本PRに含めない。進行管理は#29。全体の一般公開承認は#15。

SQLite / MySQL 8.0.46でPHPUnit各38 tests / 370 assertions、Pint、PHPStanが成功。ブラウザーで検索＋カテゴリ、Service / Offer / public profile、公開撤回後404、390pxの表示を確認した。コピー操作は「コピーしました」の表示と例外がないことを確認したが、in-app browserの仮想clipboardでは内容の貼り付け確認ができないため、実ブラウザーでの最終確認を#26に残す。

## PR とコミットの関係

- `93fff58`: #1〜#13 の主要実装と #15 の一部資料を直接追加。対応する機能 PR はありません。
- [PR #16](https://github.com/shikodecom/share-toku/pull/16): 本番 CD と PHP 8.3 互換依存。2026-10-02 マージ済み。初回配信の MySQL インデックス名エラーは #17 で修正。
- [PR #17](https://github.com/shikodecom/share-toku/pull/17): MySQL の短い明示インデックス名と migrate/rollback/re-migrate CI。2026-10-02 マージ済み。
- [main CI](https://github.com/shikodecom/share-toku/actions/runs/36970161361) と [本番 CD](https://github.com/shikodecom/share-toku/actions/runs/36970237730) は成功。本番配信成功と #15 の一般公開承認は別の判定です。

## 今回修正した不整合

1. README の shortcode 例が Service slug だったため、実装が要求する Offer public ID に修正。配信先を「予定」から「配信済み・一般公開未承認」に更新。
2. release checklist の「初回 CI 待ち」「MySQL は SQLite のみで検証」という古い記録を、実際の CI/CD 結果へ更新。
3. 削除済み Service/Program に紐づく Offer で dashboard、申請、承認が例外になっていた経路を修正。dashboard は削除済み表示、新規候補は除外、作成・申請・承認は拒否、却下は可能、配信対象外。回帰テストを追加。
4. Issue・PR の説明と対応づけるため、確認時点の実装・検証・残作業を本表に整理。未確認の受け入れ条件は未完了として維持。今回のソース修正はドラフト PR の段階では main 未反映。

## 今回のローカル検証

- Laravel: 17 tests / 141 assertions 成功（SQLite）。
- Pint、PHPStan、WordPress PHP renderer / frontend JS / block JS checks 成功。
- ローカル sandbox では既存 storage への書き込みと PHPStan の並列通信が制限されるため、テストの compiled views を `/tmp`、ログを stderr、PHPStan を `--debug` で実行。
- Homebrew Node の共有ライブラリ欠落があるため、JS checks は bundled Node を使用。システム環境の変更は行っていません。
- MySQL と WP 実機の新しい E2E を行ったという記録ではありません。

Issue [#19](https://github.com/shikodecom/share-toku/issues/19): SocialiteによるGoogle OAuth、Google IDでの識別、プロフィール同期、Personal Workspace作成、state検証、旧認証route撤去を実装。password列はNULL許容で保持し、password_reset_tokensも後方互換のため残します。本番ブラウザー検証はrelease checklistで別途記録します。
