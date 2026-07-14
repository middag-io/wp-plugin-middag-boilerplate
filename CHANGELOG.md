# Changelog

All notable changes are documented here. Versions follow [Semantic Versioning](https://semver.org);
this file is maintained by [release-please](https://github.com/googleapis/release-please) from
Conventional Commits.

## [1.0.0](https://github.com/middag-io/wp-plugin-middag-boilerplate/compare/v0.1.0...v1.0.0) (2026-07-14)


### Features

* **api:** Widget REST controller over AbstractWpRestController ([50b7eb9](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/50b7eb9e39c13975c6e7e9617a3f3ef6642f9847))
* **core:** composition root — AppBootstrap, AppServiceProvider, Plugin boot ([11e143a](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/11e143a249213921c669683824f092b830f12174))
* **cron:** scheduled widget sync via typed CronInterval + CronHandler ([04742d7](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/04742d7a3ffd05c341a9e39625ed182e7542a5a2))
* **domain:** Widget aggregate — pure POPO + schema descriptor + service ([805d722](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/805d7220ace9c1083a94d82dea4eee219c5d3b05))
* **integration:** add Integration layer with hand-wired client and optional-vendor glue ([6bb3647](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/6bb3647a9c0a406fa7f86d64ca668ccbd48b32ee))
* **lifecycle:** grant a custom capability and add a preservation-first uninstall ([d2a53a1](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/d2a53a1b66da2a8d24b4448df9b1f9b19929e871))
* **scaffold:** composer create-project initializer + Packagist metadata ([bc2cdd9](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/bc2cdd9b7bab1f8a7efa889dbd2cae55876ab869))
* **settings:** native WordPress settings page (non-React) ([a265549](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/a265549018f9e7c58d274fe5b5b55bc1344220a4))
* **ui:** Inertia admin page via server-built PageContract + CSRF ([5a97336](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/5a9733649b7905ea2d38a26d68cfc47788deebcf))
* **ui:** React 19 + Inertia v3 IIFE build + selective registration ([7f8525a](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/7f8525a22624151d3a637198bcc6d8ff4302a1b6))
* **wordpress:** Widget persistence via framework SchemaBuilder + hooks + CPT ([376bb51](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/376bb51541567d4b0c4fd9580018aa25b958c130))


### Bug Fixes

* **ci:** green PHP + UI jobs on declared PHP 8.2 and public npm ([e60f86d](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/e60f86db89bb40224ab55dda1419979e382c885c))
* **ci:** keep the optional-vendor string guard from tripping rector ([1d1fa06](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/1d1fa066a61e85f1fff941b28ee86e7081b3b4b5))
* **release:** bump WordPress Version: header via block markers ([502f958](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/502f95829d43bcf12cadf51cd18961beea1e1146))


### Miscellaneous Chores

* release 1.0.0 ([79b005a](https://github.com/middag-io/wp-plugin-middag-boilerplate/commit/79b005aa9084ac41aee3689d5dcfba75ce610690))

## 0.1.0

- Initial reference architecture: composition root over the OSS
  framework/wordpress/ui libraries, Widget domain persisted through the framework
  schema builder, REST API, typed cron, an Inertia/React admin page and a native
  WordPress settings page, with a green PHP + UI quality gate and CI.
