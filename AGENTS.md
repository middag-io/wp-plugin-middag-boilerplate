# AGENTS.md — wp-plugin-middag-boilerplate

MIDDAG's proposed architecture for a WordPress plugin — an installable, testable REFERENCE (not a prescriptive framework) showing end to end how to compose the OSS libraries (`framework` + `wordpress` + `ui` + `@middag-io/react` via Inertia) into a real plugin. Example domain: **Widget** (own table, REST, Inertia/React page, native settings page).

**What this repo is NOT:**

- The libraries themselves live in `middag-php-framework` / `middag-php-wordpress` / `middag-php-ui` / `middag-react`.
- Production plugins live in their own repos (`wp-plugin-middag`, `wp-plugin-middag-account`, `wp-plugin-woocommerce-quotes`…) — this repo stays product-free.

## Git

- Conventional Commits; **never** `Co-Authored-By`.
- Base branch: `main` trunk-based; releases via release-please.

## Language

Docs and commits in EN (public repo).

## Quality gates

Green before delivering: `composer check` (style + rector + stan) and the test suite (`phpunit.xml`). Fixers: `composer fix`.

## Inherited rules (pointers, do not copy)

- Org doctrine via docs-MCP (alias `ops`) or `docs-middag-ops`; planning/backlog in `tool-middag-planning`.

## NOT in scope / do not do without permission

- Cutting releases (release-please owns versioning/changelog).
- Adding product-specific or proprietary references — this is the public, brand-free reference.

## Gotchas — READ before touching

1. `bin/init.php` (`composer init-plugin` / `post-create-project-cmd`) renames the plugin skeleton on `create-project` — keep placeholders it rewrites intact.
2. This repo is didactic surface: structure and naming ARE the product; prefer clarity over cleverness.
