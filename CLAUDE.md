# Zoviz AI Studio — Development Conventions

Official Zoviz plugin for WordPress (WooCommerce optional). Integrates the Zoviz
Developer API (`https://developer.zoviz.com/api/v1/...`): seven async AI image
services plus jobs/credits endpoints. Published on WordPress.org as
`zoviz-ai-studio`; developed at `github.com/zoviz/wordpress-plugin`.

Full architecture and per-topic guides live in `docs/` — read
`docs/architecture.md` first when it exists. This file is the contract every
change must follow.

## Identity (single rename points)

- Display name **Zoviz AI Studio** · slug/text domain **`zoviz-ai-studio`** ·
  namespace **`Zoviz\`** · prefix **`zoviz_`** (options, hooks, table,
  transients, REST namespace `zoviz/v1`).
- Slug constant: `Zoviz\Kernel\Plugin::SLUG`. Version: `Plugin::VERSION` +
  plugin header + readme.txt `Stable tag` — all bumped ONLY by release
  automation (`bin/bump-version.sh` via semantic-release), never by hand.

## Compatibility floors

- PHP ≥ 7.4 (no `match`, enums, readonly, constructor promotion, named args).
  Arrow functions and typed properties are fine. CI enforces via
  PHPCompatibilityWP and a PHP 7.4 unit matrix cell.
- WordPress ≥ 6.4. WooCommerce is OPTIONAL: every Woo touchpoint behind
  `class_exists( 'WooCommerce' )`; the plugin must be fully functional without it.

## Architecture layers (dependency direction is one-way)

1. `src/Kernel/` — bootstrap, `Container`, `ComponentInterface`,
   `ComponentRegistry`, `Activation`, `Assets`. NOTHING API-specific here.
2. `src/Infrastructure/` — Crypto, Http transport, Database schema. Generic,
   reusable by any component.
3. `src/DeveloperApi/` — the first component (`DeveloperApiComponent` is its
   composition root and the only class the kernel references). Future
   components (video, brand kit) follow the same shape and may use different
   auth/policies.

Rules: components register container bindings in `register()` (no hooks) and
wire hooks in `boot()` (no bindings). Admin PHP classes are thin hook holders
rendering `<div id="zoviz-*-root">` shells — logic lives in REST controllers
and React apps.

## Adding a Developer API service

One class implementing `ServiceInterface` (usually extending
`AbstractAsyncService`) declaring id/label/endpoint/format/credit
cost/fields schema/capabilities + one `register()` call in
`DeveloperApiComponent`. The REST validation and the JS form render from the
same `fields()` schema — no per-service UI code unless the service needs a
special control. Third parties: `zoviz_register_services` action.

## Code style

- PHPCS: WordPress-Extra + WordPress-Docs + PHPCompatibilityWP
  (`phpcs.xml.dist`). Tabs, Yoda conditions, snake_case methods, full
  docblocks on every class/method/hook. PSR-4 file names (FileName sniff
  disabled).
- PHPStan level 6 (`phpstan.neon.dist`).
- JS: `@wordpress/scripts` defaults (ESLint WP preset). React via
  `@wordpress/element` + `@wordpress/components`; all `@wordpress/*` imports
  are externalized to core-bundled handles — NEVER bundle a library WordPress
  ships (directory guideline 13), no third-party runtime JS deps.
- i18n: EVERY user-facing string through `__()`/`_x()`/`esc_html__()` etc.
  with LITERAL text domain `'zoviz-ai-studio'` (tools can't extract
  constants). JS strings via `@wordpress/i18n`. Exception/log messages for
  developers are not translated.

## Security invariants

- API keys: encrypted at rest (`Infrastructure\Crypto\Encryptor`), never sent
  to the browser, never logged; REST returns masked values only.
- Every REST route: full args schema + `permission_callback`
  (`upload_files` for image ops, `manage_options` for keys/settings) +
  ownership checks on job rows. Browser calls use `wp.apiFetch` (nonce).
- Sanitize early, escape late; `$wpdb` only inside repositories
  (`JobRepository`, `Schema`); no `$_POST`/`$_GET` outside REST/args layer.
- No outbound HTTP before a user saves an API key. No telemetry, ever.
- Results are ALWAYS saved as new attachments; originals are never deleted.

## Error handling contract

`ApiClient` maps HTTP failures to typed exceptions (`AuthException` 401,
`InsufficientCreditsException` 402, `ValidationException` 400,
`ApiServerException` 5xx, `NetworkException`). All extend `ApiException` with
`to_wp_error()`. A 402 must always surface the buy-credits deep link
`https://zoviz.com/app/pricing/credit?navigation_source=wordpress`.

## Testing

- Unit: `composer test:unit` — Brain Monkey, no WordPress, tests extend
  `Zoviz\Tests\Unit\TestCase`. NO live HTTP ever; fixtures in `tests/fixtures/`.
- Integration: `npm run test:php:integration` inside wp-env (wp-phpunit;
  remote HTTP faked via `pre_http_request`).
- JS: `npm run test:js` (Jest + @testing-library/react).
- Every new service/route/surface ships with tests in the same PR.

## Commands

```
composer install            # PHP deps (dev)
composer lint / lint:fix    # PHPCS / PHPCBF
composer analyse            # PHPStan
composer test:unit          # unit suite
npm ci && npm run build     # JS build → build/
npm run lint:js / test:js
npm run env:start           # wp-env (Docker) with plugin mapped + activated
npm run test:php:integration
```

## Git & releases

- Conventional Commits are MANDATORY (`feat:`, `fix:`, `feat!:`, `chore:`,
  `docs:`, `test:`, `ci:`) — they drive semantic-release version calculation
  and the public changelog. PR titles are linted (squash merges).
- Merges to `main` NEVER release. Releasing = manually dispatching
  `release.yml` (tests → semantic-release computes version/tag/changelog →
  deploy to wp.org SVN). Never create tags or bump versions by hand.
- The wp.org package excludes dev files via `.distignore` (10up deploy) and
  `.gitattributes` (`git archive`). Keep both in sync when adding root files.

## WordPress.org directory guardrails (never violate)

No remote executable code; no bundled core-duplicated libraries; no
undismissable or off-scope admin notices; SaaS usage disclosed in readme.txt;
readme tags ≤ 5; `Stable tag` must equal the plugin header `Version`.
