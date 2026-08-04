=== Zoviz AI Studio ===
Contributors: zoviz
Tags: ai, image editing, image generation, woocommerce, media library
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate, edit, and enhance images with Zoviz AI — background removal, generation, upscaling, object removal, product photography, and more.

== Description ==

Zoviz AI Studio brings the Zoviz Developer API's image tools to the places
you already work with images in WordPress: the Media Library, the block
editor, and (optionally) WooCommerce product pages.

**Included services**

* **Background Remover** — clean, precise cutouts with a transparent PNG result.
* **Image Editor** — paint a mask, describe the edit, and the AI transforms just that region.
* **Image Generator** — generate images from a text prompt in a curated set of cinematic aspect ratios.
* **Image Upscaler** — upscale to a target resolution while preserving detail.
* **Object Remover** — paint over an unwanted object or person and the area is filled in seamlessly.
* **Product Photography** — turn a simple product photo into a studio-quality scene.
* **Sketch to Image** — turn a hand-drawn sketch or wireframe into a polished image.

**Where you can use them**

* A dedicated **Workspace** page for any service, including mask painting.
* A **Jobs** history page to track and re-download past results.
* **Media Library** row actions and the "Attachment Details" panel.
* The **block editor**: a sidebar, toolbar actions on image blocks, and featured-image actions. Results are inserted as standard image blocks, so your content is unaffected if the plugin is ever deactivated.
* **WooCommerce product pages** (only shown when WooCommerce is active): process or generate a product image or gallery photo directly from the product editor.

= How it works =

Zoviz AI Studio is a client for the [Zoviz Developer API](https://developer.zoviz.com/). You'll need a free Zoviz account and an API key — nothing is sent anywhere until you add one under **Zoviz AI Studio → Settings**.

When you run a service, the image (or sketch, or mask) you provide and the text you type (prompts, editing instructions) are sent to Zoviz's servers at `developer.zoviz.com` for processing, and the result is downloaded back into your Media Library. No data is sent before you configure an API key, and the plugin does not collect or transmit any usage analytics or telemetry of its own.

By using this plugin you agree to Zoviz's [Terms of Service](https://zoviz.com/terms) and [Privacy Policy](https://zoviz.com/privacy).

= Credits and pricing =

Each service consumes credits from your Zoviz workspace. Credit balance is shown everywhere you can run a service, and the plugin links straight to the [Zoviz pricing page](https://zoviz.com/app/pricing/credit?navigation_source=wordpress) whenever you run low.

== Installation ==

1. Install and activate the plugin (via the WordPress.org directory, or by uploading the zip under **Plugins → Add New → Upload Plugin**).
2. Go to **Zoviz AI Studio → Settings** and add your Zoviz Developer API key. Don't have one? Create a free account at [developer.zoviz.com](https://developer.zoviz.com/).
3. Open **Zoviz AI Studio → Workspace**, pick a service, and run it — or use the actions in the Media Library, the block editor sidebar, or (with WooCommerce active) a product's edit screen.

== Frequently Asked Questions ==

= Do I need a Zoviz account? =

Yes. Every service is powered by the Zoviz Developer API, which requires a free account and an API key. Create one at [developer.zoviz.com](https://developer.zoviz.com/).

= Is WooCommerce required? =

No. The plugin is fully functional without WooCommerce. When WooCommerce is active, an extra panel appears on the product editor for processing product images and gallery photos.

= Where do my images go? =

Source images and results are only sent to and from `developer.zoviz.com` while a job is running. Results are always saved as **new** Media Library attachments — your original images are never modified or deleted.

= How long are results available for re-download? =

Results are eagerly downloaded into your Media Library in the background as soon as they're ready, so re-downloading from the Jobs page works from your own site even after the result expires on Zoviz's servers.

= What happens to images I've inserted into a post if I deactivate the plugin? =

Nothing. Results inserted via the block editor are plain WordPress image blocks pointing at your own Media Library attachments — deactivating or uninstalling the plugin does not remove them.

= Does uninstalling remove my images? =

No. Uninstalling removes the plugin's own settings, encrypted API keys, and job history table, but never touches Media Library attachments — including the results Zoviz AI Studio created.

== Screenshots ==

1. The Workspace: run any service, including mask painting for edits and object removal.
2. Media Library row actions and one-click shortcuts.
3. Block editor sidebar and image-block toolbar actions.
4. WooCommerce product editor panel.

== Changelog ==

See [CHANGELOG.md](https://github.com/zoviz/wordpress-plugin/blob/main/CHANGELOG.md) for the full, automatically generated history.

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
