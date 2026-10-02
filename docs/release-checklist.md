# Release gate

Status: **not approved for public release**. This is a factual test record, not a claim of production readiness.

| Check | Status | Evidence / remaining action |
| --- | --- | --- |
| Threat model | Done | `docs/security-threat-model.md` |
| Laravel integration tests | In progress | Local PHPUnit integration suite; expand authorization and full scenario coverage |
| Static analysis / format / dependency audit | Automated checks passed | main `a8b4367`: remote CI passed on 2026-10-02 (run 36970161361); broader scenario coverage remains pending |
| Workspace crossing | Partial | Offer and distribution feature tests pass; audit every resource |
| Auth, CSRF, XSS | Partial | Framework and plugin controls implemented; browser tests pending |
| PKCE and Site Token | Partial | Automated exchange/replay/revoke tests pass; WordPress live callback pending |
| WordPress E2E | Pending | Needs a WordPress instance and representative domain |
| FREE match/no match/withdrawal, PRO | Partial | Service tests cover match and no consent; real plugin display pending |
| Emergency suspension and API outage | Partial | Query and cache rules implemented; live TTL test pending |
| Analytics privacy and aggregation | Partial | Automated ingest/dedupe and late event reaggregation tests pass; scheduler and retention need environment check |
| Operator global kill switch | Implemented | `SHARETOKU_OPERATOR_OFFERS_ENABLED` requires deployment verification |
| Public portal | Implemented; release verification pending | #14: SQLite / MySQL tests and local browser checks passed; clipboard contents and production/WP E2E remain in #26 |
| Terms/privacy/guidelines and reporting | Pending | Operator approved legal text and public publishing required |
| Backup/restore and secret rotation | Pending | Procedure in `docs/operations.md`; perform and record drills |
| Dependency findings | Partial | Local composer audit found 0 advisories; npm audit found 0 High/Critical after override and 6 Moderate findings for evaluation. Remote CI passed (run 36970161361); Moderate findings still require evaluation |

## E2E results

No live WordPress E2E run has been recorded. Scenarios A–F from Issue #15 are pending an environment with Laravel, MySQL, WordPress, HTTPS, and an operator Workspace. Do not mark this gate passed from unit tests alone.

## Unresolved risks

1. Issue #14 is implemented in this PR. Public release still depends on PR review, main integration and #26 E2E evidence.
2. Live WordPress setup, callback domain, caching, accessibility, and mobile layout have not been verified.
3. Legal copy, privacy disclosure, reporting route, backups, and restore drill require operator decisions and environment access.
4. Six Moderate npm findings in the WordPress build toolchain remain. The built plugin ships compiled assets and does not ship `node_modules`; evaluate each finding before release.
5. MySQL 8 migration, rollback, and re-migration passed in remote CI after PR #17. This PR adds application tests to the MySQL CI job. Local MySQL application tests pass; live WordPress E2E remains pending.

## Release preparation in progress (2026-10-02)

- PR #30 fixes #23–#25; PR #31 implements the opt-in public portal (#14). Both require review and main integration. The portal row above describes the current main baseline.
- [e2e-runbook.md](e2e-runbook.md): MySQL results and executable live WordPress scenarios for #26. Real WordPress and final clipboard paste remain pending.
- [publication-preparation.md](publication-preparation.md): #27 source-mapped disclosures and required operator/contact inputs. No provisional legal text or OAuth publication change.
- [operations-validation.md](operations-validation.md): #28 isolated MySQL restore with matching data, health and scoped API; expiration/retention/key tests. Production backup, cron, session rotation and reconnect remain pending.
- This PR: SQLite / MySQL 8.0.46 each 34 tests / 278 assertions passed. PR #30: each 52 / 382. PR #31: each 38 / 370. These are separate branches, not a merged test count.

Keep the public release gate unapproved until #26–#28 and the remaining acceptance checks are complete. Progress is tracked in #29.

## Deployment evidence (2026-10-02, JST)

- PR #16 (production CD) and PR #17 (MySQL index correction) are merged into main `a8b4367`.
- [CI run 36970161361](https://github.com/shikodecom/share-toku/actions/runs/36970161361) passed, including MySQL migration/rollback/re-migration, Laravel checks, WordPress build/tests, dependency audits, and secret scanning.
- [Production deploy run 36970237730](https://github.com/shikodecom/share-toku/actions/runs/36970237730) passed. This proves deployment workflow success, not completion of the public release gate.
- The source/Issue/PR mapping and remaining acceptance work are in [implementation-status.md](implementation-status.md).

## Google OAuth (#19)

自動検証と実Googleアカウントでのブラウザー確認は別に記録します。

- [x] 自動テスト: 初回作成・再ログイン・プロフィール同期・email競合・キャンセル・provider/state失敗・intended redirect
- [x] 自動テスト: login成功時のsession regenerate、logout時のsession invalidateとCSRF token regenerate
- [x] SQLite / MySQL 8 migration → rollback → re-migrate（password=NULLのGoogleユーザーを含む）
- [x] 本番: Google credentialsとcallback URI設定済み（shared/.env保存・config cache更新、Google側はテストモード）
- [x] 本番ブラウザー: Google OAuth live login、初回User / Personal Workspace / administrator作成
- [x] 本番ブラウザー: logout後に保護画面からloginへ戻る
- [ ] 追加の本番検証: state改ざんの拒否・session ID再生成を独立して確認（自動テストは成功済み）
- [x] 本番ブラウザー: 同一Googleアカウントで再ログインして重複がない

2026-10-02 ローカル検証: PHPUnit 35 tests / 348 assertions、Pint、PHPStan、composer auditが成功。MySQL 8.0.46でpassword=NULLのUserを保持したmigration → rollback → re-migrate成功。この時点ではCIと本番ブラウザー確認は未実施（後述の本番確認で通常フローを検証済み）。

PR用に認証変更のみを抽出した検証: PHPUnit 31 tests / 256 assertions、Pint、PHPStanが成功。上記35 testsの結果は公開ポータルの作業中変更を含む全体検証です。

2026-10-02 本番確認: PR #20をマージし、main CI 36980624335 / Deploy production 36980714305が成功。実Google初回ログイン → logout → 保護画面へのアクセス拒否 → 同一アカウント再ログインを確認。User / Personal Workspace / administratorメンバーは各0件から各1件となり、再ログイン後も各1件、User ID / Workspace IDも維持。state不一致とsession regenerateは自動テストで検証済み（本番ブラウザーでの独立したstate改ざん・session ID比較は未実施）。Google側はテストモード、一般公開は未承認。

公開準備（#15）: Googleブランディング設定・必要なポリシーURL等を確認し、一般公開の承認後にOAuthテストモードを解除する。password列・password_reset_tokensの物理削除は[cleanup #21](https://github.com/shikodecom/share-toku/issues/21)で実施する。
