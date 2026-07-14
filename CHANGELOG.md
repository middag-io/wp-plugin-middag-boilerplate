# Changelog

All notable changes are documented here. Versions follow [Semantic Versioning](https://semver.org);
this file is maintained by [release-please](https://github.com/googleapis/release-please) from
Conventional Commits.

## [0.1.1](https://github.com/middag-io/wp-plugin-middag-boilerplate/compare/middag-io/wp-boilerplate-v0.1.0...middag-io/wp-boilerplate-v0.1.1) (2026-07-14)


### Features

* **api:** Widget REST controller over AbstractWpRestController ([50b7eb9](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/50b7eb9e39c13975c6e7e9617a3f3ef6642f9847))
* **core:** composition root — AppBootstrap, AppServiceProvider, Plugin boot ([11e143a](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/11e143a249213921c669683824f092b830f12174))
* **cron:** scheduled widget sync via typed CronInterval + CronHandler ([04742d7](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/04742d7a3ffd05c341a9e39625ed182e7542a5a2))
* **domain:** Widget aggregate — pure POPO + schema descriptor + service ([805d722](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/805d7220ace9c1083a94d82dea4eee219c5d3b05))
* **settings:** native WordPress settings page (non-React) ([a265549](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/a265549018f9e7c58d274fe5b5b55bc1344220a4))
* **ui:** Inertia admin page via server-built PageContract + CSRF ([5a97336](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/5a9733649b7905ea2d38a26d68cfc47788deebcf))
* **ui:** React 19 + Inertia v3 IIFE build + selective registration ([7f8525a](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/7f8525a22624151d3a637198bcc6d8ff4302a1b6))
* **wordpress:** Widget persistence via framework SchemaBuilder + hooks + CPT ([376bb51](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/376bb51541567d4b0c4fd9580018aa25b958c130))

## 0.1.0

- Initial reference architecture: composition root over the OSS
  framework/wordpress/ui libraries, Widget domain persisted through the framework
  schema builder, REST API, typed cron, an Inertia/React admin page and a native
  WordPress settings page, with a green PHP + UI quality gate and CI.
