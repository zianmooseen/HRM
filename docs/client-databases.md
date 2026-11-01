# Client databases and recovery

## Client creation flow

1. A platform administrator opens **Clients & companies → Add client**.
2. They enter a unique company reference code, company name, initial admin credentials and optionally a subscription plan.
3. The platform reserves its client record and company directory entry, with an optional trial subscription, in the central database.
4. The provisioner creates a database named `hrm_client_<generated UUID without hyphens>`. Request input cannot choose a database name or connection.
5. It writes an ownership marker, runs the HRM migrations, and seeds roles, legal reference rules, leave types and WPS provider reference data. Demo seeders are never run.
6. It creates the company admin, main branch, compliance settings, basic salary component and an empty default onboarding template. Customer-specific payroll/WPS setup still needs completion before use.
7. Only successful initialization marks the client `ready`. The encrypted bootstrap payload contains a password hash, never plaintext; it is discarded on success. No invitation email is sent by this flow; share the initial credentials securely.
8. Failures remain listed with a sanitized error and a provisioning run ID. Resolve the underlying configuration/error and click **Retry setup**. The same client, company and database are reused. Repeating a completed setup is a no-op.

Creation is synchronous. Configure request timeouts for schema installation. A shared cache with atomic locks (Redis in a multi-server deployment) must be available; the setup lock lasts 15 minutes. A crashed attempt can be retried after its lock expires. An existing database without the matching ownership marker is never adopted or dropped automatically. Inspect an interrupted creation before repair. MySQL non-transactional DDL can require operator repair of a partially applied migration before retrying.

## Data boundary

- **Central database:** platform users, client/database directory, subscriptions, invoices, provisioning runs, support access and maintenance audit records. `DB_CONNECTION` identifies this connection.
- **Client database:** its complete company HRM data, users/roles, HR audit history and reference-data snapshots. One client currently provisions one company; branches remain inside its database. Additional companies per client need a directory mapping extension.
- Central company rows are directory/billing identities. HR access requires a provisioned client; there is no central HR fallback.
- Platform migrations live in `backend/database/migrations/platform`; client migrations live in `backend/database/migrations/client`. Platform tables include identity/access, directory/provisioning, billing, audit history and the WPS provider catalog. Clients receive HR tables and local identity/access/reference snapshots, without billing or platform registry tables.
- Company directory names are the registration-time billing/directory identity; the client's operational company profile lives in its own database.
- Files are outside SQL backups. Current file paths include the centrally allocated company ID, which is globally unique. Keep private object/file backups and retention/versioning appropriate for the requested recovery window.

## Login and platform access

All users enter **username/email + password**. The server uses the central sign-in lookup to find candidate clients, verifies passwords in their databases and signs in automatically when exactly one account matches. If credentials match multiple companies (or both platform administration and a company), the form asks the user to choose a company after password verification. The selection is checked again against the submitted credentials. Company names are never listed for an incorrect password. The optional `client_code` API parameter remains supported for existing integrations; the UI has no client-code field. A successful cookie login stores the client identity and session version server-side. Database selection happens before authentication, authorization, validation and route binding. A query string or header cannot switch a client user's database. Client authentication requires a stateful Sanctum browser session; tenant API token authentication is not implemented.

User IDs are local to a database. Session guards and connection state are cleared when leaving the client context, including on exceptions. The frontend also separates saved company filters by client identity. A tenant-local `super_admin` role cannot access platform routes.

Platform administrators select a company to inspect or change its HR records. Every support request is audited centrally. Tenant changes use a dedicated local support actor linked to the platform admin, with no usable login, so audit foreign keys remain valid. Billing remains central, even while viewing a client's HR data. Platform company and branch choices and the WPS deadline monitor load each available client's data in an explicit connection context. Selected-company filters continue to apply to platform billing and WPS reports.

This implementation targets the existing PHP request/response deployment and sequential CLI tasks. Do not use concurrent requests sharing the same Laravel application container (for example coroutine multiplexing) without a concurrency-safe tenancy integration. Future queued HR jobs must carry a client ID and wrap their work in `TenantDatabaseManager::run()`; no tenant job should rely on a web request's connection state.

### Sign-in lookup maintenance

`client_login_identifiers` is central routing metadata: client ID, tenant-local user ID and keyed HMACs of usernames/emails. It contains no passwords, password hashes, plaintext identifiers, or HR profiles. User model create/update/delete events maintain these hints; authentication always rechecks the actual tenant user and database ownership. Paused, unready, deleted, inactive and support accounts are excluded. An unavailable candidate database fails discovery closed rather than silently selecting another account. Password verification work is bounded to 50 candidate accounts; use a more specific work email for heavily reused usernames. Discovery is limited to 20 requests per identifier/IP and 60 per IP per minute, before checking passwords.

After the initial migration, application-key rotation, a restore, or bulk SQL/imports that bypass model events, rebuild the lookup:

```sh
php artisan clients:sync-logins
# Or only one client:
php artisan clients:sync-logins --client=example-company
```

The deploy script performs this rebuild before bringing the application up. Reindex failures retain the previous index for the affected client. Because tenant and central writes use separate transactions, a rolled-back user edit can leave stale hints until reindexing; stale hints cannot authenticate users because credentials and identifiers are checked again in the client database. Session/database isolation remains unchanged after sign-in.

## Installation and database permissions

From `backend`:

```sh
php artisan migrate --force
```

Normal `php artisan migrate` loads only the platform path. Default `db:seed` initializes platform roles, the WPS provider catalog and an administrator when credentials are configured; it does not seed HR records. Client provisioning and `clients:migrate` use only the client path. Existing platform credentials still work on the same username/password form.

The default client engine/server and runtime credentials come from `DB_CONNECTION`. PostgreSQL, MySQL and local SQLite are supported. For separate provisioning and runtime credentials, add named connections to `config/database.php`, then select them with `TENANT_PROVISIONING_CONNECTION` and `TENANT_TEMPLATE_CONNECTION`. Both must reach the same database server. URL/read/write overrides are resolved and removed before selecting a client database.

- PostgreSQL: the provisioner needs `CREATEDB` and the ability to assign the configured runtime role as database owner. Database creation runs outside transactions. The runtime role needs normal schema/data access.
- MySQL: grant creation rights for the managed database namespace to the provisioner and appropriate migration/data privileges in that namespace to the runtime role. The application does not create users or issue broad `GRANT` statements.
- SQLite: files go in `storage/app/tenants` by default, with a private directory and mode `0600`. Override with `TENANT_SQLITE_DIRECTORY`; do not expose this directory publicly.
- Preserve the application encryption key, central database and tenant files/backups. Database-backed sessions, cache and queue infrastructure are pinned to the central connection; rate limits include the client identity. Use persistent, shared session storage across app instances.

Database hosting products that disallow `CREATE DATABASE` require provider-specific provisioning; this implementation will record a failed setup instead of silently sharing the central database.

## Upgrade from the previous combined schema

Back up the central and all client databases. Stop integrations/workers and drain in-flight requests before migration. The platform cleanup refuses nonempty HR tables or central branch/department role assignments. Resolve any legacy central HR data explicitly before retrying; it is never migrated or discarded automatically. Global legal/leave reference defaults can be removed centrally because clients retain their own snapshots. The WPS provider administration catalog remains central.

```sh
php artisan down
php artisan migrate --force
php artisan clients:upgrade --force
php artisan clients:sync-logins
php artisan up
```

`clients:upgrade` pauses all ready clients, revokes sessions, applies client migrations, verifies identity and resumes only clients that were not already paused. Failed upgrades leave the application down and clients paused for inspection. Existing client billing tables are removed only if empty. HR rows and IDs remain untouched. The production deploy script uses this sequence; drain external jobs before starting deployment. If deployment fails, inspect the error before manually bringing the application up.

Migration filenames were preserved when moved, so historical migration entries must remain in place. Cleanup migrations are forward-only; reverse an upgrade with the matching database backups rather than `migrate:rollback`. Do not run platform paths against a client or client paths against the central database.

## Schema updates

Normal `php artisan migrate` updates the platform only. For existing client databases:

```sh
php artisan clients:maintenance example-company
php artisan clients:migrate --client=example-company --force
php artisan clients:maintenance example-company --resume
```

Drain in-flight requests and jobs after pausing. `clients:migrate` without `--client` targets all ready clients and requires all of them to be paused. Failed migrations keep clients paused. Initial provisioning runs the latest HRM migrations automatically; ordinary updates do not reseed or overwrite client data/reference rules. Reference-data updates need separately versioned migrations or an explicit maintenance process.

## Restore one client

1. Agree on the recovery timestamp and the changes that will be discarded. Confirm that database and file backups exist for that point.
2. Run `php artisan clients:databases` to identify the exact database. This operator command lists no credentials.
3. Pause that client with `php artisan clients:maintenance example-company`. This blocks new client access and revokes its sessions centrally. Drain requests/jobs already running and pause external integrations for this client.
4. Back up its current database and files before replacement.
5. Restore the server backup to an isolated temporary server. Never roll back the shared production server to recover one client. Disable outgoing integrations/jobs in the temporary environment.
6. Extract only the client's database and verify its `tenant_identity.uuid`, relationships, expected data/time and matching file versions. Daily snapshots restore to their backup time; exact timestamps require your database provider's point-in-time recovery/log retention.
7. Restore the verified client database into its original production database name. Do not replace the central directory, billing database or any other client's database. Restore relevant file versions as necessary.
8. If the snapshot has an older schema, run `clients:migrate --client=example-company --force` while paused. Run `clients:sync-logins --client=example-company` to refresh any restored usernames/emails. Verify the restored data and app compatibility.
9. Run `clients:maintenance example-company --resume`. The command checks database identity/initialization before enabling access. Users must sign in again; old sessions remain invalid.

A database restore cannot reverse bank transfers, emails or other external effects. Coordinate those separately. This change supplies database isolation, provisioning and per-client maintenance; it does **not** schedule server backups, purchase backup storage, replay transaction logs or implement an automatic restore UI. Configure and exercise your hosting backup/PITR process before promising a recovery time or recovery window.

## Verification

`php artisan test` covers provisioning, retries after a migration failure, no duplicate setup, overlapping client user IDs, session/login isolation, platform access boundaries, support-write audit identity, billing separation and exception cleanup. The recovery test snapshots one SQLite client database, changes two clients, restores only the first, and checks that the second's changes remain and old sessions are revoked. Production PostgreSQL/MySQL provisioning and restore permissions must also be exercised in a staging environment matching the hosting setup.
