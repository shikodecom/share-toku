# Release gate

Status: **not approved for public release**. This is a factual test record, not a claim of production readiness.

| Check | Status | Evidence / remaining action |
| --- | --- | --- |
| Threat model | Done | `docs/security-threat-model.md` |
| Laravel integration tests | In progress | Local PHPUnit integration suite; expand authorization and full scenario coverage |
| Static analysis / format / dependency audit | In progress | CI workflow added; first remote CI run pending |
| Workspace crossing | Partial | Offer and distribution feature tests pass; audit every resource |
| Auth, CSRF, XSS | Partial | Framework and plugin controls implemented; browser tests pending |
| PKCE and Site Token | Partial | Automated exchange/replay/revoke tests pass; WordPress live callback pending |
| WordPress E2E | Pending | Needs a WordPress instance and representative domain |
| FREE match/no match/withdrawal, PRO | Partial | Service tests cover match and no consent; real plugin display pending |
| Emergency suspension and API outage | Partial | Query and cache rules implemented; live TTL test pending |
| Analytics privacy and aggregation | Partial | Automated ingest/dedupe and late event reaggregation tests pass; scheduler and retention need environment check |
| Operator global kill switch | Implemented | `SHARETOKU_OPERATOR_OFFERS_ENABLED` requires deployment verification |
| Public portal | Outside requested sequence | Issue #14 is not implemented; #15 depends on it |
| Terms/privacy/guidelines and reporting | Pending | Operator approved legal text and public publishing required |
| Backup/restore and secret rotation | Pending | Procedure in `docs/operations.md`; perform and record drills |
| Dependency findings | Partial | Local composer audit found 0 advisories; npm audit found 0 High/Critical after override and 6 Moderate findings for evaluation. Remote CI pending |

## E2E results

No live WordPress E2E run has been recorded. Scenarios A–F from Issue #15 are pending an environment with Laravel, MySQL, WordPress, HTTPS, and an operator Workspace. Do not mark this gate passed from unit tests alone.

## Unresolved risks

1. Issue #14 is outside this requested sequence but required by Issue #15's public release gate.
2. Live WordPress setup, callback domain, caching, accessibility, and mobile layout have not been verified.
3. Legal copy, privacy disclosure, reporting route, backups, and restore drill require operator decisions and environment access.
4. Six Moderate npm findings in the WordPress build toolchain remain. The built plugin ships compiled assets and does not ship `node_modules`; evaluate each finding before release.
5. MySQL is not running in the local workspace, so the migrations have only been exercised with the test suite's in-memory SQLite database.
