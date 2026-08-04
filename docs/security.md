# Security design

This document explains the *why* behind the security invariants listed in
`CLAUDE.md`. For reporting a vulnerability, see
[SECURITY.md](../SECURITY.md).

## API key encryption at rest

API keys are never stored in plaintext. `Infrastructure\Crypto\Encryptor`
encrypts every key before `KeyRepository` persists it in the
`zoviz_api_keys` option:

- **Primary backend:** `sodium_crypto_secretbox` (libsodium, bundled with
  PHP ≥ 7.2).
- **Fallback:** OpenSSL AES-256-GCM, for the rare host without libsodium.
- **Key derivation:** `sodium_crypto_generichash( wp_salt('auth') .
  wp_salt('secure_auth'), '', 32 )` — the encryption key is derived from
  the site's own authentication salts, never stored anywhere itself.
- **Versioned output:** ciphertext is prefixed `v1s:` (sodium) or `v1o:`
  (OpenSSL) followed by base64(nonce/iv + ciphertext), so a future format
  change can coexist with old ciphertexts during a migration.

### Salt rotation

Because the encryption key is derived from `wp_salt()`, rotating a site's
salts (e.g. after a suspected compromise, or via a security plugin's "force
logout everywhere" feature) makes every existing ciphertext
undecryptable. `Encryptor::decrypt()` returns `null` in that case — this is
treated as "the secret is gone", never a fatal error:

- `KeyManager` marks the affected key `is_valid = false` with an
  explanatory `last_error`.
- The admin notice (`Notices`) surfaces "re-enter your API key" using only
  cached state — no request is made to detect this on every page load.
- A `zoviz_crypto_canary` option (itself an encrypted known value) lets the
  plugin proactively detect a salt rotation on `admin_init` rather than
  waiting for the first failed decrypt.

## What never happens

- **The browser never receives an API key.** Every Developer API call is
  proxied through the plugin's own `zoviz/v1` REST routes; the browser
  authenticates to *those* with a standard WordPress REST nonce, and the
  plugin looks up the decrypted key server-side. See
  [rest-api.md](rest-api.md).
- **Keys are never logged.** No `error_log()`/debug output anywhere in the
  key lifecycle includes the secret value; REST responses always return a
  masked representation (see `KeyRepository`/`KeysController`).
- **No outbound request happens before a user configures an API key.** The
  plugin makes zero calls to `developer.zoviz.com` until a key is added —
  this is also the plugin's consent mechanism for the wp.org SaaS
  disclosure guideline.
- **No telemetry, ever.** The plugin does not phone home for usage
  analytics, error reporting, or update checks beyond what WordPress core
  itself does for the plugin listing.

## REST layer

- Every route declares a full `args` schema (types, sanitize/validate
  callbacks) — nothing reads `$_POST`/`$_GET` directly outside the REST/args
  layer.
- Every route has an explicit `permission_callback`: `upload_files` for
  image operations, `manage_options` for key/settings management.
- Job rows are checked for **ownership** in addition to capability — a
  non-admin can only see/act on jobs they created; `scope=all` on the jobs
  list requires `manage_options`.
- `$wpdb` is only ever touched inside `JobRepository` and `Schema` — every
  query is parameterized via `$wpdb->prepare()`.

## Capability map

| Action | Required capability |
|---|---|
| Run a service, view own jobs, view credits | `upload_files` |
| View all users' jobs | `manage_options` |
| Manage API keys, plugin settings | `manage_options` |
| Dismiss a notice | logged in |

## Uninstall

`uninstall.php` removes the plugin's own table, options, transients, and
per-user notice-dismissal meta. It deliberately **never** deletes Media
Library attachments — including the results Zoviz created — because those
are the user's content, not the plugin's internal state.
