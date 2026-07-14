# wp-plugin-middag-boilerplate

**MIDDAG's proposed architecture for a WordPress plugin.** It is an installable,
testable reference — not a prescriptive framework. It shows, end to end, how to
compose MIDDAG's OSS libraries (`framework` + `wordpress` + `ui` +
`@middag-io/react`) into a real plugin. You are free to follow all of it, take a
single layer, or ignore React entirely.

Example domain: **Widget** — a minimal aggregate persisted in its own table,
exposed over REST, listed on an Inertia/React page and configured through a
native WordPress settings page.

---

## Installation (fresh clone, no MIDDAG credentials)

The OSS libraries resolve from the **public Packagist** — no `auth.json`, no Satis.

```bash
composer install          # framework + wordpress (+ ui transitive) from Packagist
cd ui && npm install       # React 19 + Inertia v3 + @middag-io/react
npm run build:wp           # generates ../assets/dist/app.js + style.css (IIFE)
```

Activate the plugin in wp-admin: the `wp_mdgbp_widget` table is created on
activation, and the "MIDDAG Boilerplate" menu appears with the **Widgets**
(React) and **Settings** (native) pages.

Quality:

```bash
composer check   # php-cs-fixer + rector + phpstan (level 6, no baseline)
composer test    # phpunit — Unit + Integration
cd ui && npm run typecheck && npm run lint && npm run test
```

---

## The one knob: the slug + the namespace

Renaming the plugin = changing **two** identities. Everything else derives from them.

| Knob | Value here | Derives |
|---|---|---|
| **slug** (`extra.installer-name` in `composer.json`) | `middag-boilerplate` | installed folder `wp-content/plugins/middag-boilerplate/`, text domain, React mount `id` (`middag-boilerplate-app`), REST namespace (`middag-boilerplate/v1`), asset names |
| **PSR-4 namespace** | `Middag\WpBoilerplate\` → `src/` | every class |

> The namespace is a **chosen PascalCase**, NOT derived from the kebab slug. `middag-boilerplate`
> (slug) and `Middag\WpBoilerplate` (namespace) are independent — change both.

The **repository** name (`wp-plugin-middag-boilerplate`) ≠ the **installed folder**
(`middag-boilerplate`): the `wp-plugin-` prefix disambiguates the repo within the
org, and `installer-name` governs the install path.

---

## The rule: `src/` (PHP) vs `ui/` (visual)

- **`src/`** — ALL the PHP, subdivided **by LAYER** (never `includes/admin/public`):
  - `Core/` — composition root. `AppBootstrap` **composes** the lib's `WpBootstrap`
    (it does not reimplement the platform), `AppServiceProvider` auto-discovers
    services, and `Plugin::boot` builds the container once and wires the hooks.
  - `Domain/` — pure DDD, **zero WordPress**. Entity + enum + DTO + service +
    schema descriptor (`#[Table]`/`#[Column]`).
  - `WordPress/` — the ONLY namespace that touches the WP API/`$wpdb`: repository
    (built on the framework's `AbstractRepository`), mapper, hooks, CPT, settings.
  - `Api/V1/` — REST controllers built on `AbstractWpRestController`.
  - `Integration/<Vendor>/` — outbound third-party integrations. A `*Client`
    (talks over the WordPress HTTP API, no SDK) is hand-wired in
    `AppServiceProvider` because `Client` is not a discovery suffix; a discovered
    `*Service` maps the result into the domain. The `OptionalVendor/` subtree
    shows the guarded pattern for glue that `extends` a base only present when a
    third-party plugin is active (see "Lifecycle & data").
  - `UI/` — Inertia page controllers (they build the `PageContract` in PHP).
- **`ui/`** — React + Inertia + `@middag-io/react` + Vite → IIFE in `assets/dist/`.

**The UI is optional.** A plugin WITHOUT a rich interface has neither `ui/` nor
`src/UI/` — delete both folders and the plugin stays valid (Domain + WordPress + Api).

---

## Two UIs, one menu (it is not 100% React)

The boilerplate shows both WordPress admin worlds under the same menu:

- **Widgets** — an Inertia/React SPA. The PHP (`WidgetPageController` + the `ui`
  lib's `PageBuilder`) builds the entire `PageContract`; the React `<ContractPage>`
  renders it. Single flow (no separate `contractKey`/`contractData`). The React
  side registers ONLY the shell + the blocks it uses (`registerBlock`), never
  `registerDefaults()` — forbidden in the IIFE build.
- **Settings** — a native WordPress page (Settings API), server-rendered, ZERO
  React, via `Middag\WordPress\Settings` (Tab → Section → Field).

---

## Where a proprietary product plugs in `core` + `licensing`

The boilerplate is **strict OSS (TIER A)**: it depends only on `framework` +
`wordpress` + `ui`. It **never** requires `middag-io/core` or
`middag-io/licensing` (proprietary) — otherwise it would stop being installable by
an external developer, and the CI gate (OSS boundary) fails the build.

A real MIDDAG product adds those tiers at two points, **without touching the rest
of the architecture**:

- **Auth/SSO** — the `WidgetController` injects a `RequestAuthenticatorInterface`.
  The boilerplate wires the `WpSessionAuthenticator` (cookie/session). A product
  wires ITS OWN composite (token → session) from `core`/`licensing` in
  `AppBootstrap`, and every controller then authenticates through it — no other
  change.
- **Composition root** — `AppBootstrap::configure()` is where a product registers
  its proprietary services and runs `bootModules([...])` for the `core` modules.

In other words: the OSS↔proprietary boundary is a **DI seam**, not a fork.

---

## Vendor isolation (a product concern, not demonstrated here)

This boilerplate **does not run Strauss**. Prefixing only Symfony DI would be
incoherent: `middag-io/framework` exposes Symfony DI types in its public API
(`BootstrapInterface::configure(ContainerBuilder)`) and is not prefixed along with
them, so an app that prefixed only the DI would break the interface implementation.

Isolating the vendor for real (to coexist with other plugins that bundle a
different Symfony) means scoping the **entire graph** — `framework` + Symfony +
PSR — with **PHP-Scoper** in the product's build. That is a product-build concern,
deliberately out of scope for this reference to keep it coherent and simple.

> **`Middag\Framework` collision across multiple plugins:** same case — resolved
> by PHP-Scoper in the product, documented here, not demonstrated.

---

## Demonstrates vs. Freedom

**The boilerplate DEMONSTRATES:** composition via `WpBootstrap` + `ContainerFactory`;
identity via `WpComponentContext`; one class per layer; auto-discovery by suffix
+ single-interface auto-alias; a table generated from `#[Table]` via
`SchemaBuilder`; REST with real `permission_callback`s + a `RestResponse` envelope;
cron with a typed `CronInterval`; an `InertiaAdapter` with a derived mount id;
SELECTIVE block registration; a native settings page; CSRF on mutations; an
`Integration/` layer (a hand-wired `*Client` + a guarded optional-vendor glue
class); custom capabilities via `CapabilityRegistrar`; a preservation-first
`uninstall.php`.

**You are FREE to:** choose `SCAN_DIRS`/namespace; which blocks/shells to register;
inject your own `RequestAuthenticatorInterface`; use the framework's async bus;
add CPTs/routes/pages; or ignore Inertia/React and use a single layer. The
libraries are a menu, not a mandate.

---

## Lifecycle & data: activation, capabilities, uninstall

The plugin uses plain static WordPress hooks for its lifecycle —
`register_activation_hook`/`register_deactivation_hook` → `Plugin::activate()` /
`Plugin::deactivate()`. It deliberately does NOT wrap this in a "PluginLifecycle"
abstraction: such a helper couples every plugin to subsystems it may not need
(it registers a `CronRegistrar` unconditionally), dead weight for a plugin
without cron. Static hooks keep activation explicit and cost-free — reach for a
heavier abstraction only when a plugin actually needs it.

- **Capabilities.** `activate()` grants the plugin's custom capabilities through
  the wp-adapter's `CapabilityRegistrar`, driven by a central
  `Plugin::CAPABILITY_MAP` (role → caps); `deactivate()` revokes them. One
  symmetric map beats scattered `add_cap`/`remove_cap` calls.
- **Uninstall.** `uninstall.php` (run only on DELETE) is PRESERVATION-FIRST: by
  default it removes only derived/scheduled state, and destroys production data
  (the widget table, CPT content, options, the custom cap) ONLY when the owner
  opts in with `define('MDGBP_UNINSTALL_PURGE', true)`. It runs without the
  Composer autoloader, so it uses core APIs + hardcoded literals, and loops over
  every site on multisite.

---

## i18n: English-first

Every user-facing string is wrapped in a WordPress i18n call
(`__()` / `esc_html__()`, text domain `middag-boilerplate` = the slug) and the
**msgid IS the English text** — never a pre-translated literal. Translations live
in `languages/` as `.po` / `.mo`; regenerate the `.pot` with `composer i18n:pot`.
English-first keeps msgids stable across locales and lets any translator start
from the source string.

---

## Release

`release-please` maintains `CHANGELOG.md` and the `MIDDAG_BOILERPLATE_VERSION`
constant (annotated with `x-release-please-version`) from Conventional Commits.
The `Version:` header in the main plugin file (read by WordPress) must be bumped
along with it in the release PR — WordPress parses the header literally, so it
does not carry the release-please annotation.

---

## License

GPL-3.0-or-later.
