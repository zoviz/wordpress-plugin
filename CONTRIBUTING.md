# Contributing to Zoviz AI Studio

Thanks for your interest in improving the plugin. This project is developed
in the open, but releases are cut deliberately by the Zoviz team — see
[docs/release-process.md](docs/release-process.md).

## Conventional Commits are required

Every commit on `main` (in practice, every squash-merged PR) must follow
[Conventional Commits](https://www.conventionalcommits.org/):

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

Common types used in this repo: `feat`, `fix`, `docs`, `test`, `ci`, `chore`,
`refactor`. A breaking change is marked either with `!` after the type/scope
(`feat!: ...`) or a `BREAKING CHANGE:` footer.

This isn't a style preference — semantic-release parses commit history to
compute the next version number and to write the changelog automatically
when a release is dispatched. A PR whose title doesn't follow the
convention will fail the `pr-title` CI check (PRs are squash-merged, so the
PR title becomes the commit message).

| Commit prefix | Effect on next version |
|---|---|
| `fix:` | patch bump |
| `feat:` | minor bump |
| `feat!:` / `BREAKING CHANGE:` footer | major bump |
| `docs:`, `test:`, `ci:`, `chore:`, `refactor:` (no `!`) | no release by itself |

## Development setup

```bash
composer install     # PHP dev dependencies
npm ci                # JS dev dependencies
npm run env:start     # local WordPress via wp-env (Docker)
```

See the [README](README.md) for the full list of lint/test/build commands,
and [CLAUDE.md](CLAUDE.md) for the architecture rules every change must
follow (layering, naming, i18n, security invariants).

## Before opening a PR

- `composer lint && composer analyse && composer test:unit`
- `npm run lint:js && npm run test:js && npm run build`
- `npm run test:php:integration` (requires `npm run env:start` first)
- Add or update tests in the same PR as the behavior they cover — this is a
  hard requirement, not a nice-to-have (see `docs/architecture.md`).
- Every user-facing string goes through an i18n function with the literal
  text domain `'zoviz-ai-studio'` (see `docs/i18n.md`).

## Adding a Developer API service or a component

Walkthroughs: [docs/adding-a-service.md](docs/adding-a-service.md) and
[docs/adding-a-component.md](docs/adding-a-component.md). In short: a new
service is one class plus one registry line (or the `zoviz_register_services`
action for third parties); a new component follows `ComponentInterface` and
plugs into `zoviz_components`.

## Security issues

Please do not open a public issue for a security vulnerability — see
[SECURITY.md](SECURITY.md).
