# Security Policy

## Supported versions

Only the latest release of Zoviz AI Studio published on WordPress.org
receives security fixes. Please keep the plugin up to date.

## Reporting a vulnerability

If you believe you've found a security vulnerability in Zoviz AI Studio,
please report it privately rather than opening a public GitHub issue or
forum post.

**Email:** [security@zoviz.com](mailto:security@zoviz.com)

Please include:

- A description of the vulnerability and its potential impact.
- Steps to reproduce it (a minimal WordPress setup helps a lot).
- The plugin version and, if relevant, WordPress/PHP/WooCommerce versions.

We aim to acknowledge reports within 5 business days. We'll keep you
updated as we investigate and fix the issue, and we're happy to credit
reporters in the release notes (with your permission) once a fix ships.

## Scope

In scope: the `zoviz-ai-studio` plugin code in this repository. Out of
scope: the Zoviz Developer API / `developer.zoviz.com` and other Zoviz
services — please report those directly to security@zoviz.com as well, but
note it's a separate system from this repository's code.

## What this plugin does with your data

For context when evaluating a report: API keys are encrypted at rest and
never sent to the browser or logged (see
[docs/security.md](docs/security.md)); no request is made to Zoviz's
servers before a user configures an API key; there is no telemetry.
