# Release process

Releasing is a **deliberate, manually triggered** action — merging to
`main` never publishes anything. This is intentional: the owner wants
automation for the mechanics of a release, but a human decides *when* one
happens.

## How to cut a release

1. Make sure `main` is in the state you want released — every PR merged so
   far is included; nothing is released partially.
2. Go to **Actions → release.yml** on GitHub and click **Run workflow**
   (`workflow_dispatch`). There are no inputs — semantic-release computes
   everything from Conventional Commit history since the last tag.
3. Watch the run. If the `test` job fails, the release is aborted before
   anything is tagged or published — nothing partial ever reaches wp.org.

That's the entire manual process. Everything else below happens
automatically inside that one workflow run.

## What happens inside `release.yml`

1. **`test`** — re-runs the full `ci.yml` suite (PHPCS, PHPStan, PHP unit
   matrix, PHP integration matrix, JS lint/test/build, POT freshness,
   plugin-check) via `workflow_call`. A red test job stops the release
   here — no tag is ever created on a failing build.
2. **`release`** (needs `test`) — [semantic-release](https://semantic-release.gitbook.io/)
   runs with:
   - `commit-analyzer` — computes the next semver from commits since the
     last tag (see the bump table in [CONTRIBUTING.md](../CONTRIBUTING.md)).
   - `release-notes-generator` + `changelog` — writes `CHANGELOG.md`.
   - `exec` — runs `bin/bump-version.sh <version>`, which rewrites the
     `Version:` plugin header, `Stable tag:` in `readme.txt`,
     `Plugin::VERSION`, `package.json`, and `composer.json` so all five
     always agree.
   - `git` — commits the version bump + changelog back to `main` and tags
     that commit `vX.Y.Z` (never a hand-created tag).
   - `github` — creates the GitHub Release from the generated notes.

   If there are no releasable commits (only `docs:`/`chore:`/etc. since the
   last tag), semantic-release exits cleanly without releasing anything.
3. **`deploy`** (needs `release`, gated on its `new_release_published`
   output) — checks out the new tag, builds the release zip exactly like
   `bin/build-zip.sh` does locally (`composer install --no-dev
   --classmap-authoritative` + `npm run build`), and pushes it to the
   WordPress.org SVN repository via `10up/action-wordpress-plugin-deploy`
   with `SLUG: zoviz-ai-studio` set explicitly (the GitHub repo is named
   `wordpress-plugin`, which does **not** match the wp.org slug — this
   must stay explicit). The built zip is also attached to the GitHub
   Release.

## Why deploy is chained in the same workflow

A `GITHUB_TOKEN`-authored release does not trigger a separate `on: release`
workflow (GitHub suppresses that to prevent accidental infinite loops), so
`deploy` runs as a job in the *same* workflow, gated on `release`'s output,
rather than as its own workflow listening for a release event.

## Required secrets

- A fine-grained PAT or GitHub App token capable of pushing the version-bump
  commit to protected `main` (the default `GITHUB_TOKEN` cannot push to a
  branch with required-review protection).
- `SVN_USERNAME` / `SVN_PASSWORD` — WordPress.org SVN credentials for
  `10up/action-wordpress-plugin-deploy`.

## Local parity

`bin/build-zip.sh` builds the exact same release zip locally (useful for a
pre-flight check, or for `WordPress/plugin-check-action` in CI) without
touching git tags, GitHub, or SVN.

## WordPress.org listing assets

`assets.yml` syncs `.wordpress-org/**` to the plugin's SVN `assets/` folder.
Like `release.yml`, it is **manual dispatch only** — merging changes to
`.wordpress-org/**` or `readme.txt` on `main` never pushes anything by
itself; go to **Actions → assets.yml** and click **Run workflow** when
you're ready to publish the updated listing assets. This is independent of
a release — plugin listing assets can update without shipping a new plugin
version. real banner/icon/screenshot artwork is needed before the plugin is
submittable. When it's ready, add (all PNG unless noted):

| File | Size | Notes |
|---|---|---|
| `icon-128x128.png` | 128×128 | Plugin icon, square. |
| `icon-256x256.png` | 256×256 | Same icon, retina. |
| `icon.svg` | vector | Optional; wp.org prefers this over the PNG icons when present. |
| `banner-772x250.png` | 772×250 | Plugin page banner, low-DPI. |
| `banner-1544x500.png` | 1544×500 | Same banner, retina. |
| `screenshot-1.png` … `screenshot-4.png` | any, 16:9 recommended | Matched 1:1 by index to the `== Screenshots ==` list in `readme.txt` — keep them in sync. |

Don't add a stray README or any non-asset file inside `.wordpress-org/` —
`assets.yml` mirrors the directory's contents straight to the public SVN
`assets/` folder.

## A note on the jobs sweeper

The jobs sweeper (`JobSweeper`) is a backstop for abandoned jobs (browser
tab closed mid-job) — browser polling is the primary way a job's status is
finalized. `JobSweeper::maybe_run()` is hooked to `admin_init` only, not to
real WP-Cron, so it deliberately never runs off public front-end traffic;
a pair of transients throttle it to once per interval. The trade-off is the
mirror image of the usual WP-Cron caveat: a site nobody visits in
`wp-admin` for a while won't have abandoned jobs swept until someone does.
A real system cron hitting `wp-cron.php` does **not** help here — there's
no WP-Cron event to trigger — so there's nothing to configure for this on
low-traffic sites.
