# AGENTS.md

PHP library (`potibm/phluesky`) for posting to Bluesky via the AT Protocol. PHP `^8.2` (CI: 8.2/8.3/8.4/8.5).

## Commands

Run all three before finishing; CI enforces them.

- `composer test` — PHPUnit (`vendor/bin/phpunit tests`).
- `composer psalm` — static analysis (`psalm.xml`, errorLevel 2, `findUnusedCode=true`).
- `composer codestyle` — ECS check over `src/` and `tests/`; use `composer codestyle-fix` to fix.
- Single test: `vendor/bin/phpunit tests/BlueskyPostServiceTest.php --filter testAddImage --no-coverage`.

## Layout

- `src/` → namespace `potibm\Bluesky\`; `tests/` → `potibm\Bluesky\Test\` (PSR-4 in `composer.json`).
- `BlueskyApi` (`src/BlueskyApi.php`) implements `BlueskyApiInterface`; does XRPC calls and caches the session.
- `BlueskyPostService` builds posts, facets (`src/Richtext/`), and embeds (`src/Embed/`); `Feed/Post` is the record payload.
- Media uploads go through `src/Media/`: `addImage()`/`addWebsiteCard()` accept a `MediaSource` (`FileMediaSource`, `BlobMediaSource`). Passing a file path string still works but triggers `E_USER_DEPRECATED`; keep this migration path in mind when touching those methods.
- `HttpComponentsManager` resolves PSR-18/17 clients via `php-http/discovery`; the discovery Composer plugin is intentionally disabled (`allow-plugins`).

## Testing gotchas

- `phpunit.xml` sets `requireCoverageMetadata`/`beStrictAboutCoverageMetadata`, so every test class **must** declare `#[CoversClass(...)]` (and `#[UsesClass(...)]` for collaborators) or fail.
- No network in tests: mock `Psr\Http\Client\ClientInterface` and `ResponseInterface`, build bodies with `Http\Discovery\Psr17Factory` (see `tests/BlueskyApiTest.php:195`).
- `failOnRisky`/`failOnWarning` are enabled.
- `vfsStream` is used for filesystem edge cases (`tests/BlueskyPostServiceTest.php`).

## Notes

- `composer.lock` is gitignored (library convention), so CI resolves dependencies fresh from `composer.json`; bump constraints there, not in a lockfile.
- Exception hierarchy lives under `src/Exception/`; `Exception` is the base type. See README for the documented usage surface.
