# REST API — `zoviz/v1`

Everything here is **internal**: a thin proxy the plugin's own JS calls via
`wp.apiFetch` (standard WordPress REST nonce auth). The browser never sees
a Zoviz Developer API key — the plugin looks the key up server-side and
calls `developer.zoviz.com` itself. This isn't a public/stable API for
third parties; routes can change between versions like any other internal
implementation detail.

All permission callbacks require, at minimum, `upload_files`; key and
settings management require `manage_options`; job rows are additionally
checked for ownership (the creating user, or an admin with `scope=all`).

| Method | Path | Permission | Notes |
|---|---|---|---|
| `POST` | `/jobs` | `upload_files` | Body: `service` (must be a registered service id), `key_id` (optional — falls back to the default key), `attachment_id` **or** multipart file(s) (`image`/`mask`/`sketch`, whichever the service's `fields()` schema declares), plus that service's other scalar fields (`prompt`, `dimension`, `target_width`/`target_height`, ...). Args are validated against the service's declarative field schema — see [adding-a-service.md](adding-a-service.md). |
| `GET` | `/jobs` | `upload_files` (`scope=all` requires `manage_options`) | Filters: `status[]`, `service`, `context`, `page`/`per_page` (≤100). Non-admins only ever see their own jobs. |
| `GET` | `/jobs/{id}` | `upload_files` + ownership | `refresh` (default `true`) polls the remote API once if the job is still pending. |
| `POST` | `/jobs/{id}/save` | `upload_files` + ownership (+ `edit_post` on the assign target, if any) | Downloads the result into the Media Library (idempotent — safe to call again just to assign). Body: `title`, `alt`, `assign: { type: 'none'\|'featured'\|'product_image'\|'product_gallery', post_id }`. |
| `DELETE` | `/jobs/{id}` | owner or `manage_options` | Removes the job row only — any Media Library attachment it produced is untouched. |
| `GET` | `/credits` | `upload_files` | `key_id` (optional), `force` (bypass the 5-minute cache). |
| `GET`, `POST` | `/keys` | `manage_options` | Secrets are always masked in responses. `POST` validates the key live against the Developer API *before* persisting it — see `KeyManager::add_key()`. |
| `PUT`, `DELETE` | `/keys/{id}` | `manage_options` | `PUT` body: `label`, `is_default`. |
| `GET` | `/services` | `upload_files` | The service catalog (id, label, description, field schema, credit cost, capabilities, accepted mimes) — this is what `client/shared/hooks/use-services.js` renders every form from. |
| `GET`, `POST` | `/settings` | `manage_options` | Plugin-wide settings (`auto_download`, retention, ...). |
| `POST` | `/notices/{id}/dismiss` | logged in | Snoozes a persistent admin notice for 7 days (per-user meta). |

## Error shape

Every `ApiException` subclass maps to a `WP_Error` via `to_wp_error()`:

```json
{
  "code": "zoviz_insufficient_credits",
  "message": "Your Zoviz workspace does not have enough credits for this request.",
  "data": {
    "status": 402,
    "buy_url": "https://zoviz.com/app/pricing/credit?navigation_source=wordpress"
  }
}
```

`data.buy_url` is only present on a 402 and must always carry
`navigation_source=wordpress` — `client/shared/components/insufficient-credits-notice.js`
renders it as the "Buy more credits" link everywhere a job can be
submitted. `client/shared/api/client.js`'s `normalizeError()` is the single
place every apiFetch failure is normalized into `{ code, message, status,
buyUrl }` before a component ever sees it.

## Adding a route

New routes belong to a controller extending `WP_REST_Controller` with a
full args schema and `get_item_schema()` (see any existing controller for
the shape). Register it from `DeveloperApiComponent::boot()`'s
`rest_api_init` callback alongside the others. Every route ships with an
integration test in `tests/Integration/Rest/` exercising the permission
matrix (including the non-owner 403 case) via `WP_REST_Request` — see
`tests/Integration/Support/RestTestCase.php`.
