# Architecture

Read this first. `CLAUDE.md` at the repo root is the enforced contract; this
document explains the reasoning behind it.

## Layers (dependency direction is one-way)

```
src/Kernel/          bootstrap, Container, ComponentInterface, ComponentRegistry,
                      Activation, Assets — nothing API-specific.
src/Infrastructure/   Crypto, Http transport, Database schema — generic,
                      reusable by any component.
src/DeveloperApi/     the first component. Its composition root,
                      DeveloperApiComponent, is the only class the kernel
                      references.
```

A component:

- registers container bindings in `register()` — **no hooks**.
- wires WordPress hooks in `boot()` — **no bindings**.

This split exists so a component's dependency graph is fully declared
before any hook fires, which makes the container predictable to test and
means `register()` never has ordering surprises.

Future components (video, brand kit, ...) follow the same shape and are
free to use different auth/policies — nothing in the kernel assumes a
Developer-API-shaped backend. See
[adding-a-component.md](adding-a-component.md).

## The kernel

- `Plugin` — singleton. Boots on `plugins_loaded` priority 5 (or
  immediately if that's already fired, which happens on the activation
  request itself). Owns the `Container` and `ComponentRegistry`, exposes
  `file()`/`dir()`/`url()`, loads the text domain on `init`.
- `Container` — lazy, memoized service locator: `set(id, factory)`,
  `get(id)`, `has(id)`.
- `ComponentRegistry` — holds every registered `ComponentInterface`,
  filterable via `zoviz_components` so third parties (or future first-party
  components) can add their own.
- `Activation` — `Schema::install()` + cron scheduling on activation,
  `wp_clear_scheduled_hook()` for every `zoviz_*` event on deactivation.
- `Assets` — registers a built JS/CSS bundle from its `<entry>.asset.php`
  dependency manifest and wires `wp_set_script_translations()`.

## Infrastructure

- `Crypto\Encryptor` — API keys are encrypted at rest with
  `sodium_crypto_secretbox` (OpenSSL AES-256-GCM fallback), keyed from
  `wp_salt()`. See [security.md](security.md) for the full threat model.
- `Http\HttpTransport` (+`WpHttpTransport`) — every outbound request goes
  through `wp_remote_*`, never raw cURL, so it respects site HTTP filters,
  proxies, and SSL verification settings. `MultipartBuilder` builds
  multipart bodies for file uploads.
- `Database\Schema` — `dbDelta`-based install/upgrade for the one custom
  table (`{prefix}zoviz_jobs`), versioned via the `zoviz_schema_version`
  option.

## The DeveloperApi component

`DeveloperApiComponent` wires everything below and is the template every
future component follows.

- **Services** (`Services/`) — `ServiceInterface`, usually via
  `AbstractAsyncService`, declares one Developer API endpoint: id, label,
  endpoint path, request format, credit cost, accepted mimes, a field
  schema, and capabilities. The same field schema drives both REST
  argument validation and the JS form. See
  [adding-a-service.md](adding-a-service.md).
- **Api/** — `ApiClient` is the single place that talks to
  `developer.zoviz.com` and the single place HTTP failures become typed
  exceptions (`AuthException`, `InsufficientCreditsException`,
  `ValidationException`, `ApiServerException`, `NetworkException`), all
  extending `ApiException` with a REST-ready `to_wp_error()`.
- **Keys/** — `KeyRepository` (encrypted storage, single option, no table —
  cardinality is a handful of keys per site) and `KeyManager` (validates a
  key live before persisting it, and auto-promotes the newest key to
  default).
- **Jobs/** — `JobRepository` is the only place besides `Schema` allowed to
  touch `$wpdb` for the jobs table. `JobManager` is the single orchestrator
  used by both REST and the sweeper: submit, poll, and download-to-media.
  Browser polling is the primary finalizer for a job; `JobSweeper`'s sweep
  is the backstop for abandoned jobs, fired from `admin_init` (not real
  WP-Cron) so it only ever runs off admin traffic — see
  [release-process.md](release-process.md) for the trade-off that implies.
- **Media/** — `MediaImporter` sideloads a downloaded result as a **new**
  Media Library attachment (originals are never touched), tags it with
  provenance meta, and assigns it to a target (featured image, WooCommerce
  product image/gallery — feature-detected).
- **Admin/** — thin hook holders. Every admin surface renders a single
  `<div id="zoviz-*-root">` shell plus its script enqueue; all logic lives
  in the REST controllers and the React app. This keeps PHP admin classes
  trivially reviewable and keeps the actual behavior in one place (the
  service's field schema, the REST controller's validation) instead of
  duplicated between PHP and JS.
- **Rest/** — controllers extend `WP_REST_Controller` with full args
  schemas. See [rest-api.md](rest-api.md).

## Why the client never sees API keys

Every Developer API call goes through the plugin's own `zoviz/v1` REST
namespace, authenticated by the standard WordPress REST nonce
(`wp.apiFetch`). The browser asks the plugin's REST layer to run a job; the
plugin looks up the (decrypted, server-side only) API key and calls
`developer.zoviz.com` itself. This is what makes "API keys never sent to
the browser" an architectural guarantee rather than a discipline problem.

## Testing philosophy

- **Unit** (`tests/Unit`, Brain Monkey, no WordPress): pure logic — service
  field schemas, the API client's error mapping, crypto roundtrips, the
  container. No live HTTP, ever.
- **Integration** (`tests/Integration`, wp-env + wp-phpunit): anything that
  touches `$wpdb`, WordPress core APIs, or full REST request/response
  cycles. Remote HTTP is faked via `pre_http_request` fixtures.
- **JS** (`tests/js`, Jest + Testing Library): shared components and hooks.

Every new service, route, or admin surface ships with tests in the same PR
— this is enforced by review, not by tooling, so treat it as non-negotiable.
