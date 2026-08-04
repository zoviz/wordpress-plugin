# Adding a Developer API service

A "service" is one Zoviz Developer API endpoint (Background Remover, Image
Upscaler, ...). Adding one is normally exactly two changes: one class, one
registry line. No REST or JS changes are needed — both are driven by the
service's declarative field schema.

## 1. Create the class

```php
namespace Zoviz\DeveloperApi\Services;

final class MyNewService extends AbstractAsyncService {

	public function id() {
		return 'my-new-service';
	}

	public function label() {
		return __( 'My New Service', 'zoviz-ai-studio' );
	}

	public function description() {
		return __( 'What this service does, in one sentence.', 'zoviz-ai-studio' );
	}

	public function endpoint() {
		return 'my-new-service'; // Path after /api/v1/ — check the real API spec.
	}

	public function fields() {
		return array(
			'image'  => array(
				'type'     => 'file',
				'required' => true,
				'label'    => __( 'Image', 'zoviz-ai-studio' ),
			),
			'prompt' => array(
				'type'     => 'string',
				'required' => true,
				'label'    => __( 'Instruction', 'zoviz-ai-studio' ),
			),
		);
	}
}
```

`AbstractAsyncService` supplies sensible defaults: multipart request
format, 1 credit, JPEG/PNG/WebP accepted in, PNG out, `capabilities()` of
`['bulk' => false, 'mask' => false, 'source' => 'image']`, and a
schema-driven `prepare_request()` that validates every field in `fields()`
and throws `ValidationException` on bad input. Override only what differs
— see `ImageGenerator2Service` (JSON format, 2 credits, an exact dimension
enum) or `ImageUpscalerService` (a differently-named endpoint,
`image-upscaling`) for real examples of overriding one thing.

### Field schema reference

Each entry in `fields()` is keyed by the field name the API expects and can
have:

| Key | Meaning |
|---|---|
| `type` | `'file'` \| `'string'` \| `'enum'` \| `'integer'` |
| `required` | bool |
| `label` | translated label — used by the auto-generated JS form |
| `options` | allowed values, for `'enum'` |
| `default` | optional default value |
| `min` / `max` | bounds, for `'integer'` |

This same array drives both `AbstractAsyncService::prepare_request()`'s
validation **and** `client/shared/components/service-form.js`'s rendering
— add a field here and it appears in every surface (Workspace, editor
sidebar, WooCommerce panel) with no JS changes.

### Capabilities

`capabilities()` tells every surface how to present the service:

```php
public function capabilities() {
	return array_merge( parent::capabilities(), array(
		'bulk'   => false,        // suitable for unattended bulk processing (v1.1+)
		'mask'   => true,         // requires a painted mask — shows MaskCanvas
		'source' => 'image',      // 'image' | 'sketch' | 'none' (pure generation)
	) );
}
```

## 2. Register it

In `DeveloperApiComponent::register()`, inside the `ServiceRegistry`
factory:

```php
$registry->register( new MyNewService() );
```

That's it. `ServiceRegistry::register()` throws on a duplicate id, so a
typo'd id collision fails loudly in tests rather than silently shadowing
another service.

## 3. Third parties: don't edit core

After the built-in services are registered, `DeveloperApiComponent` fires:

```php
do_action( 'zoviz_register_services', $registry );
```

A third-party plugin (or a future first-party add-on) can hook this to add
its own `ServiceInterface` implementation without touching this repository.

## 4. Tests

Add a unit test alongside the other services in `tests/Unit/Services/`
covering: the field schema (required/optional, defaults, enum bounds),
`prepare_request()` for both valid and invalid input, and anything the
service overrides (a non-default endpoint path, request format, credit
cost, or capabilities). See `ServicesTest` for the pattern — one test class
covers all seven built-in services plus the registry itself.

If the service needs a special UI control beyond what `ServiceForm`
renders generically (rare — `MaskCanvas` is the one example so far), that
lives in `client/shared/components/`, gated on the capability the service
declares (e.g. `capabilities.mask`), not on the service id — so the same
component works for every mask-based service, present or future.
