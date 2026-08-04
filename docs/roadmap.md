# Roadmap

v1.0.0 ships a deliberately lean core. This is what's deferred, and why.

## v1.1+ candidates

- **Bulk processing.** A `QueueInterface` with a `WpCronQueue` /
  `ActionSchedulerQueue` factory (Action Scheduler isn't bundled with this
  plugin, but every WooCommerce site already ships it) plus batch progress
  UI. `Job.batch_id` and each service's `capabilities()['bulk']` flag are
  already in place for this.
- **Media Library bulk actions** — `bulk_actions-upload` /
  `handle_bulk_actions-upload` to run a service across a multi-selection.
- **WooCommerce product variations and the product list table** — per-
  variation image actions, and quick actions from **Products → All
  Products** without opening each product.
- **WooCommerce product bulk actions** — analogous to the Media Library
  bulk action, scoped to products.
- **Playwright end-to-end tests.** A skeleton + `wp-env` config for e2e is
  planned but deferred; today's coverage is unit + integration + Jest.
- **Deeper media-modal integration.** v1 deliberately limits itself to
  `attachment_fields_to_edit` inside the Backbone media modal (the one
  extension point considered stable) plus deep links to the Workspace page
  for everything else. A richer in-modal experience is possible but adds
  Backbone-view-override fragility that isn't worth it for v1.
- **Pre-publish checks** — e.g. surfacing "this post's featured image could
  use Background Remover" as a pre-publish panel suggestion.
- **Classic editor support** for the block-editor-only surfaces (sidebar,
  block toolbar). The classic editor is not a primary target for v1.

## Future components

The kernel/component architecture (`ComponentInterface`,
`zoviz_components`) exists specifically so these can be added without
touching `DeveloperApi`:

- **Video** — a Zoviz video-generation/editing component, likely with its
  own async job model.
- **Brand kit** — logo, color palette, and brand-asset management tied
  into the same credit/key UX patterns established by `DeveloperApi`.

See [adding-a-component.md](adding-a-component.md) for the shape a new
component should take.
