# Adding a component

`DeveloperApi` is the first component; video or brand-kit features are
expected to arrive as new, sibling components rather than growing inside
`DeveloperApi`. A component may use an entirely different backend, auth
scheme, or storage shape — the kernel doesn't assume anything
Developer-API-specific.

## The contract

```php
namespace Zoviz\Kernel;

interface ComponentInterface {
	public function id();
	public function register( Container $container );
	public function boot( Container $container );
}
```

- `id()` — a short, unique string.
- `register( $container )` — declare container bindings only. **No hooks
  here.** This runs for every component before any component's `boot()`
  runs, so a binding can never accidentally depend on hook-registration
  order.
- `boot( $container )` — wire WordPress hooks (`add_action`, `add_filter`,
  `rest_api_init` route registration, ...) only. **No new bindings here.**

## Wiring it in

Components are discovered through the `zoviz_components` filter:

```php
add_filter( 'zoviz_components', function ( array $components ) {
	$components[] = new MyComponent();
	return $components;
} );
```

`ComponentRegistry::all()` applies this filter once. First-party
components are added inside `Plugin::setup_components()`; third-party
components (or, in principle, a future first-party component shipped as
its own mu-plugin or add-on) use the filter from outside this repository.

## Shape to copy

Use `DeveloperApiComponent` as the template:

1. A single composition-root class implementing `ComponentInterface` — the
   *only* class the kernel ever references directly.
2. Its own `Api/`, sub-namespace(s) for whatever backend it talks to, its
   own exception hierarchy extending a common base with `to_wp_error()`.
3. Its own `Rest/` controllers, registered inside `boot()`'s
   `rest_api_init` callback.
4. Its own `Admin/` thin hook-holder classes rendering a root div; its own
   `client/<surface>/` JS entries.
5. If it needs persistent storage beyond a WordPress option, its own table
   via `Infrastructure\Database\Schema`-style versioned `dbDelta` install,
   or (preferably, if the shape fits) extend `Schema` rather than
   duplicating the versioning dance.

## What stays shared

- `Infrastructure/` (`Crypto`, `Http`, `Database`) is generic and meant to
  be reused — don't fork `Encryptor` or `WpHttpTransport` for a new
  component's own secrets or requests unless its actual needs genuinely
  differ.
- `Kernel/Assets` — reuse it for registering a new component's JS/CSS
  bundles; don't hand-roll `wp_register_script()` calls elsewhere.
- The shared JS surface pattern (`client/shared/`) is specific to
  `DeveloperApi`'s Workspace/editor/WooCommerce apps today; a component
  with a very different UI shape (e.g. a full video timeline editor) should
  feel free to build its own shared layer under its own `client/` entries
  rather than force-fitting `DeveloperApi`'s components.

## Tests

`tests/Unit/Kernel/` already covers `Container` and `ComponentRegistry`
component-agnostically. A new component needs its own
`tests/Unit/<Component>/` and `tests/Integration/<Component>/` trees
following the same unit/integration split described in
[architecture.md](architecture.md).
