# Zoviz AI Studio

Official [Zoviz](https://zoviz.com/) plugin for WordPress and WooCommerce:
generate, edit, and enhance images with Zoviz AI services — background
removal, image generation, upscaling, object removal, product photography,
sketch-to-image, and AI image editing — right where you work with images.

> Development happens here; releases are distributed through the
> [WordPress.org plugin directory](https://wordpress.org/plugins/zoviz-ai-studio/).

## Requirements

- WordPress 6.4+
- PHP 7.4+
- A [Zoviz Developer API key](https://developer.zoviz.com/)
- WooCommerce is optional — product-image tooling appears when it is active.

## Development

```bash
composer install     # PHP dev dependencies
npm ci               # JS dev dependencies
npm run build        # build admin apps into build/
npm run env:start    # local WordPress via wp-env (Docker)
```

Quality checks:

```bash
composer lint        # PHPCS (WordPress coding standards)
composer analyse     # PHPStan level 6
composer test:unit   # PHP unit tests
npm run lint:js
npm run test:js
npm run test:php:integration   # PHP integration tests inside wp-env
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for the workflow (Conventional Commits
required) and `docs/` for architecture and extension guides.

## License

[GPL-2.0-or-later](LICENSE).
