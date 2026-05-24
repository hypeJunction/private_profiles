# private_profiles — Architecture (Elgg 7.x)

## Summary

Restricts access to user profile pages and the ability to send private messages
between users. Site-wide defaults are configurable in plugin settings; each user
can optionally override the defaults for their own profile (toggleable in
plugin settings). Also exposes a user-level setting to hide a user's activity
and membership listing from logged-out visitors.

The plugin owns no entity types, subtypes, or relationships of its own — it
operates entirely via event handlers on existing entity / access / menu APIs.

## Directory layout

```
private_profiles/
├── elgg-plugin.php              # routes, actions, default settings, Bootstrap binding
├── composer.json                # elgg/elgg ~7.0.0, php >=8.3, ext-intl, composer/installers ^2.0
├── docker/                      # elgg7 dev/CI stack (PHP 8.3, MySQL 8.0)
├── classes/
│   ├── PrivateProfilesBootstrap.php           # boot() + init() event registrations
│   └── Elgg/PrivateProfiles/
│       ├── Access.php           # access/permission predicates + event handlers
│       ├── Menus.php            # page-menu (settings/privacy) registration
│       └── Router.php           # /profile and /settings/privacy rewrites
├── actions/
│   └── private_profiles/
│       └── usersettings_save.php
├── lib/
│   └── hooks.php                # setupUserHoverMenu() global (loaded by elgg-plugin.php)
├── views/default/
│   ├── forms/private_profiles/usersettings_save.php
│   ├── plugins/private_profiles/settings.php   # admin plugin settings form
│   └── resources/private_profiles/usersettings.php
├── languages/
│   ├── en.php
│   └── de.php
└── tests/
    └── phpunit/integration/PrivateProfiles/AccessActivityPrivacyTest.php
```

## Registered events (all via Bootstrap)

In Elgg 7.x (same shape as 5.x/6.x) hooks and events are unified — all
callbacks below are registered via `elgg_register_event_handler()` and
receive `\Elgg\Event`.

| Event | Type | Handler | Notes |
|-------|------|---------|-------|
| `route:rewrite` | `settings` | `Router::rewriteSettingsRoute` | Rewrites `settings/privacy/<user>` to `private_profiles/usersettings/<user>` |
| `route:rewrite` | `profile` | `Router::routeProfile` (priority 100) | Gatekeeper for `/profile/<user>` access |
| `register` | `menu:page` | `Menus::setupPageMenu` | Adds "Privacy" item to settings menu |
| `register` | `menu:user_hover` | `setupUserHoverMenu` (priority 501) | Hides "Send message" when forbidden |
| `register` | `menu:title` | `setupUserHoverMenu` (priority 501) | Same on profile title menu |
| `action:validate` | `messages/send` | `Access::interceptPrivateMessage` | Throws ValidationException on disallowed send |
| `get_sql` | `access` | `Access::applyActivityPrivacy` | Hides activity from anonymous visitors per user setting |

## Routes

| Route | Path | Resource |
|-------|------|----------|
| `private_profiles:usersettings` | `/private_profiles/usersettings/{username?}` | `private_profiles/usersettings` |

## Actions

| Action | Access |
|--------|--------|
| `private_profiles/usersettings_save` | `logged_in` |

## Default plugin settings

| Setting | Default | Description |
|---------|---------|-------------|
| `default_access_setting` | `no` | Profile access default (no/yes/members/friends) |
| `default_messages_setting` | `friends` | Messages default (no/yes/friends) |
| `custom_access_setting` | `yes` | Whether users can override defaults |

## Per-user settings

| Name | Values | Notes |
|------|--------|-------|
| `user_access_setting` | `no` / `yes` / `members` / `friends` | Per-user profile access override |
| `user_messages_setting` | `no` / `yes` / `friends` | Per-user messages override |
| `user_activity_setting` | `yes` / `members` | Whether activity/member listing is hidden from logged-out visitors |

Stored via `ElggUser::setPluginSetting()`. From Elgg 5.x onward the
`private_settings` table is gone — these values live in the `metadata` table
under the namespaced name
`plugin:user_setting:private_profiles:<setting-name>` (see
`Access::ACTIVITY_SETTING_METADATA_NAME` for the activity-setting key).

## Dependencies

- `elgg/elgg` `~7.0.0`
- `php` `>=8.3`
- `ext-intl`
- `composer/installers` `^2.0`
- Composer stability for 7.x: `minimum-stability: dev`, `prefer-stable: true`, plus the `asset-packagist.org` composer repository (per rule 023-composer-stability)
- Suggests: `messages` (the messaging event handlers are only meaningful when the messages plugin is active)

## Seeding

No seeder shipped — the plugin owns no entity types/subtypes/relationships
and persists only per-user plugin settings. No seeder is required (no
visible gap in seeded fleets). Documented exception per the elgg-migrate
acceptance gate.

## Migration notes (6.x → 7.x)

- `composer.json`: `elgg/elgg` bumped to `~7.0.0`, `php` floor raised to
  `>=8.3`. Added `minimum-stability: dev`, `prefer-stable: true`, and the
  `https://asset-packagist.org` composer repository — these are mandatory
  on every Elgg 7.x plugin (rule 023-composer-stability) so dev-tagged
  Elgg dependencies (e.g. CKEditor frontend asset packages) resolve.
- `docker/`: regenerated from the canonical
  `skills/elgg-migrate/infra/elgg7` template — PHP 8.3, MySQL 8.0, Elgg
  7.x, PHPUnit `^10.5 || ^11.0`. Ports moved to 8700 / 13700 to avoid
  colliding with the 6.x stack on the same host.
- `Access::interceptPrivateMessage()` now reads the singular
  `recipient` action input first, falling back to the legacy
  `recipients` array if absent — rule 013-messages-parameter-rename.
  The Elgg 7.x core messages plugin renamed the `messages/send` form
  field; the validator coerces either shape into a list of integer
  guids before checking `canSendPrivateMessage()` for each, so no
  existing caller is broken.
- The 6.x → 7.x AST + LLM-guided manifest (26 rules) reported zero
  in-scope matches for everything else: no `ElggObject` direct
  instantiation, no CSS Crush, no Redis/Memcached, no Laminas mailer,
  no notification handler references, no renamed forms/routes/actions,
  no `elgg-button-special` / `elgg-button-action-done`, no
  `elgg_reset_system_cache()`, no `flush_cache`, no external-pages
  surface, no webservices, no river capability, no PHPUnit attribute
  migration needed (the existing integration test already uses class
  methods only). The migration is essentially the composer +
  messages-parameter delta.
- Test coverage retained: same 6 tests / 48 assertions as on 6.x; PHPUnit
  runs green inside the elgg7 docker stack.
- Gate results (verify-fleet --version=elgg7 --phpunit, project
  em-private_profiles-3f3af01): PHP syntax PASS · homepage 14296 bytes
  PASS · login 14394 bytes PASS · zero PHP Fatal/Error in error.log PASS
  · PHP_CodeSniffer (Elgg) PASS · PHPUnit 6 tests / 48 assertions PASS.

## Migration notes (5.x → 6.x)

- `composer.json`: `elgg/elgg` bumped to `~6.1.0`, `php` floor raised to `>=8.2`.
- No further deprecated-API usage to remove — the 5.x → 6.x AST + LLM-guided
  rules (AMD→ESM, annotation join alias, icon coords, removed hook functions,
  removed `EntityIcon` interface, etc.) all reported zero matches for this
  plugin. The migration is essentially a metadata bump plus the SQL refactor
  below.
- **SQL refactor in `Access::applyActivityPrivacy()`** (resolves the security
  debt flagged in the 4.x → 5.x ARCHITECTURE.md):
  - The handler now requires the `query_builder` event param (present from
    Elgg 6.x in `AccessWhereClause`) and uses
    `Elgg\Database\QueryBuilder::subquery()` + `ComparisonClause`-bound named
    parameters instead of `elgg_get_config('dbprefix')` string interpolation.
  - The produced clause shape is
    `NOT EXISTS (SELECT 1 FROM elgg_metadata pp_md WHERE … AND pp_md.name = :qbN AND pp_md.value = :qbM)`.
  - Targets the `metadata` table — `private_settings` was dropped in 5.x
    (`MergePrivateSettingsInMetadata` migration), which means the previous
    raw SQL was *already broken* (selecting from a non-existent table) and the
    privacy filter was silently a no-op on 5.x sites. This step restores the
    filter's intended behaviour as well as removing the raw-SQL pattern.
  - The namespaced metadata name is captured as the class constant
    `Access::ACTIVITY_SETTING_METADATA_NAME` so callers can verify it without
    bootstrapping a full `ElggUser`.
  - Defensive: when the event payload is missing a `query_builder` (no longer
    a real code path in 6.x, but kept as a safety net), the handler returns
    null rather than falling back to string interpolation.
- Test coverage: new
  `tests/phpunit/integration/PrivateProfiles/AccessActivityPrivacyTest.php`
  covers the parameterized clause shape, outer-query parameter binding, the
  no-`query_builder` early return, the action/logged-in/ignore-access early
  returns, and the no-table-alias case (6 tests, 48 assertions).

## Migration notes (4.x → 5.x — kept for history)

- `composer.json`: `elgg/elgg` bumped to `~5.1.0`, `php` floor raised to `>=8.1`,
  `ext-intl` added (new Elgg 5 dep).
- Hooks merged into events (Elgg 5.x): `elgg_register_plugin_hook_handler()` →
  `elgg_register_event_handler()`, callback signatures `\Elgg\Hook` →
  `\Elgg\Event` across `Bootstrap`, `Access`, `Menus`, `Router`, and
  `lib/hooks.php::setupUserHoverMenu()`. The plugin only used hooks via the
  registrar function, so the `'hooks'` key on `elgg-plugin.php` did not need
  removal (none existed).
- `REFERER` constant → `REFERRER` in `Router::routeProfile`.
- `get_user_by_username()` → `elgg_get_user_by_username()` in
  `Router::routeProfile` and `views/default/resources/private_profiles/usersettings.php`.
- `elgg_push_breadcrumb()` → `elgg_register_menu_item('breadcrumbs', ...)` in
  the user settings resource.
- PHP 8.1+ nullable type hints applied: `?ElggUser $viewer = null` and
  `?ElggUser $sender = null` on `Access::hasAccessToProfile()` and
  `Access::canSendPrivateMessage()`.
- Null safety hardening:
  - `get_user($user_guid)` no longer assumed to return a user — guarded against
    null before `isAdmin()` in `privateprofiles_check_access_overrides()`.
  - `hasAccessToProfile()`: viewer may be null (anonymous), so `$viewer->guid`
    is no longer dereferenced unconditionally; `LOGGED_IN` case now returns
    `(bool) $viewer` to satisfy the return type.
  - `interceptPrivateMessage()`: `is_array($recipients)` guard added before the
    loop (the recipients input is now coerced inside the action layer).
  - User settings action: explicit null check on `$current_user` to satisfy
    strict `isAdmin()` callsite.
