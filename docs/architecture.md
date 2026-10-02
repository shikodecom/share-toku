# ShareToku architecture

The repository is a modular Laravel monolith with a WordPress plugin in `wordpress-plugin/sharetoku`. Laravel owns users, workspaces, master data, offers, review decisions, subscriptions, sites, distribution, and analytics. WordPress holds only a revocable Site Token and short lived resolve responses.

## Boundaries

- A user belongs to a Workspace through `workspace_members`. Every member resource query starts from the authorized Workspace; the client does not select the owning `workspace_id`.
- `users.is_system_admin` is separate from Workspace roles. A Workspace administrator cannot manage other Workspaces or system subscriptions.
- Public resource IDs are ULIDs. Internal bigint keys are never used by distribution clients.
- `Publishability` checks offer and program states and periods on every distribution query. The scheduler also marks expired offers, but distribution does not depend on the scheduler being on time.
- Public listing policy and external distribution policy are separate fields. The public portal (#14) remains unimplemented; see `docs/implementation-status.md` for the issue-to-source mapping.
- `EntitlementService` is the single source for plan limits and operator eligibility. The Subscription row belongs to the Workspace.
- Site Token is a 256 bit random bearer secret. Only its SHA-256 hash is stored in ShareToku. WordPress stores the raw token server side with autoload disabled.
- `sites.domain` stores the normalized ASCII host (including IDN punycode). Pending registrations may share a host; exchange locks matching Site rows and refuses a second active connection.
- The WordPress plugin caches resolve responses for no more than the API TTL. It does not use expired cache entries during an API outage.
- Analytics events carry a signed, short lived event token and never require the Site Token in the browser.

## Time and interfaces

Database timestamps are UTC. Human facing dates can be converted at display time. Site APIs use `/api/v1` and UTF-8 JSON. Error responses use `{"error":{"code":"...","message":"..."}}` for distribution and authorization failures.

## Operational dependencies

Production needs MySQL, HTTPS, a scheduler for `offers:expire`, `analytics:aggregate`, and `analytics:prune`, and ordinary Laravel session, cache, and queue stores. Set `APP_DEBUG=false` and a persistent `APP_KEY`.
