# Release gate

Status: **not approved for public release**. This is a factual test record, not a claim of production readiness.

| Check | Status | Evidence / remaining action |
| --- | --- | --- |
| Threat model | Done | `docs/security-threat-model.md` |
| Laravel integration tests | Automated checks passed | main `532a1003`: SQLite / MySQL each 64 passed / 547 assertions, concurrent scenarios 4; broader live acceptance remains pending |
| Static analysis / format / dependency audit | Automated checks passed | main `532a1003`: remote CI 37068762985 passed; broader scenario coverage remains pending |
| Workspace crossing | Partial | Offer and distribution feature tests pass; audit every resource |
| Auth, CSRF, XSS | Partial | Framework and plugin controls implemented; browser tests pending |
| PKCE and Site Token | Partial | Real local WordPress callback / disconnect / reconnect passed; HTTPS and Google browser flow remain pending |
| WordPress E2E | Partial | Real WP 7.1.2 / MySQL results in wordpress-e2e-2026-10-03.md; representative HTTPS domain and full editor / analytics acceptance remain pending |
| FREE match/no match/withdrawal, PRO | Partial | Real plugin / API display checks passed with isolated fixture state changes; browser creation / review / consent flow pending |
| Emergency suspension and API outage | Partial | Real WP fresh render checks passed; kill-switch / API outage tested before and after 10-second TTL; production TTL and per-resource timing pending |
| Analytics privacy and aggregation | Partial | Automated checks pass; production retention 90 and minutely cron registered; scheduled success / failures and browser ingestion remain pending |
| Operator global kill switch | Partial | Production OFF confirmed, operator Workspace unset; isolated real WP ON/OFF verified |
| Public portal | Implemented; release verification pending | #14: SQLite / MySQL tests and local browser checks passed; clipboard contents and production/WP E2E remain in #26 |
| Terms/privacy/guidelines and reporting | Pending | Operator approved legal text and public publishing required |
| Backup/restore and secret rotation | Partial | Isolated fake-data restore / event key tests passed; production inspection does not establish daily encrypted backup / production restore / session rotation |
| Dependency findings | Partial | Local composer audit found 0 advisories; npm audit found 0 High/Critical after override and 6 Moderate findings for evaluation. Remote CI passed (run 36970161361); Moderate findings still require evaluation |

## E2E results

Real WordPress 7.1.2 / MySQL / Laravel results were recorded on 2026-10-03 against main `532a1003` in [wordpress-e2e-2026-10-03.md](wordpress-e2e-2026-10-03.md). The HTTP loopback fixture verifies callback, display, REST preview, TTL and reconnect. It does not complete the HTTPS / Google / editor / browser analytics acceptance gate.

## Unresolved risks

1. #14 is merged through PR #31. Public release still depends on #26–#28 and final acceptance evidence.
2. Local WordPress callback, rendering and caching passed; HTTPS domain, full Google / editor flow, analytics delivery, final clipboard and mobile checks remain pending.
3. Legal copy, privacy disclosure, reporting route, daily backups and production-data restore drill require operator decisions. SSH read-only inspection succeeded; production settings and cron registration are recorded separately.
4. Six Moderate npm findings in the WordPress build toolchain remain. The built plugin ships compiled assets and does not ship `node_modules`; evaluate each finding before release.
5. Main CI runs all application tests on MySQL after migration / rollback / re-migration and deterministic concurrency checks. Passing CI and production CD do not replace the remaining public release gate.

## Historical preparation record (2026-10-02, before merging #30–#32)

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
- [ ] 本番ブラウザー: Google OAuth live login、初回User / Personal Workspace / administrator作成
- [ ] 本番ブラウザー: Socialite state検証、session regenerate、logout
- [ ] 本番ブラウザー: 同一Googleアカウントで再ログインして重複がない

2026-10-02 ローカル検証: PHPUnit 35 tests / 348 assertions、Pint、PHPStan、composer auditが成功。MySQL 8.0.46でpassword=NULLのUserを保持したmigration → rollback → re-migrate成功。CIと本番ブラウザー確認は未実施。

PR用に認証変更のみを抽出した検証: PHPUnit 31 tests / 255 assertions、Pint、PHPStanが成功。上記35 testsの結果は公開ポータルの作業中変更を含む全体検証です。
