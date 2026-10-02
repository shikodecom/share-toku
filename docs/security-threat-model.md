# Security threat model

## Assets and boundaries

| Asset | Primary protection |
| --- | --- |
| User account and Workspace data | Session authentication, CSRF, Workspace membership queries |
| Referral codes and URLs | Scoped access, publishability checks, output escaping |
| Site Token | One time issue, hash in SaaS DB, server side WordPress option, revoke |
| Authorization code | 256 bit random, five minute expiry, PKCE S256, one use transaction |
| Audit log and analytics | Role checked reads, signed event claims, no raw IP storage |
| System administration | Separate `is_system_admin` flag and server side checks |

Trust boundaries: browser ↔ Laravel session endpoints; WordPress browser ↔ WordPress server; WordPress server ↔ ShareToku API; public browser ↔ referral site. The public portal boundary remains unimplemented because #14 was omitted from the requested issue sequence.

## Threats and controls

- **IDOR / Workspace crossing:** all member Offer and Site lookups are rooted in the authorized Workspace. Site bearer auth resolves its Workspace server side. Cross Workspace tests are included.
- **XSS:** Blade escapes output; the plugin uses WordPress text, attribute, and URL escaping. Referral URLs are validated at write time and escaped again at render time.
- **CSRF and session fixation:** Laravel web middleware protects mutations; login and registration regenerate sessions; logout invalidates them. WordPress admin actions require capability and nonce.
- **SQL injection:** all dynamic values use Eloquent/query builder bindings. Dynamic master type uses a fixed mapping.
- **Credential stuffing and rate abuse:** auth, exchange, distribution and events endpoints have rate limits. Production rate settings should be tuned from traffic observations.
- **Authorization code theft/replay:** PKCE S256, fixed redirect URI, five minute expiry, hash storage, and row locked exchange. WordPress validates state before exchange.
- **Token leakage:** no Site Token in query parameters, rendered HTML, or JavaScript. Raw tokens and codes should be excluded from access logs at the reverse proxy as well.
- **Redirect abuse:** authorize requires the redirect URI host to match the registered Site domain, the WordPress `admin-post.php` callback path and action, and HTTPS except localhost development. WordPress pins its callback URI.
- **Malicious referral URL:** only parseable HTTP(S) URLs with a host and bounded length are accepted. The SaaS does not fetch referral URLs.
- **Cache poisoning/stale delivery:** plugin cache key includes Site, Offer, and Placement; response TTL is bounded by offer expiry and five minute configuration. A Site suspension or consent withdrawal can remain visible in already cached pages until TTL expiry.
- **Analytics spoofing:** HMAC signed claims, valid slot and offer mapping, batch limit, event ID dedupe, timestamp bounds, origin check, rate limit. These limit abuse but cannot prove a real human viewed a card.
- **Privilege escalation:** system admin is distinct from Workspace administrator; reviewer access is limited to member Workspaces.

## Residual risks

The plugin has not been exercised in a live WordPress instance in this workspace. The public portal (#14) and its XSS/SEO surface are absent. There is no external penetration test, no conversion verification, and no anti-bot guarantee. The release checklist must stay open until environment and legal reviews are completed.
