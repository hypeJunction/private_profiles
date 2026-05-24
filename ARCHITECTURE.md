# private_profiles — Architecture (Elgg 5.x)

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
├── composer.json                # elgg/elgg ~5.1.0, php >=8.1, ext-intl, composer/installers ^2.0
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
└── languages/
    ├── en.php
    └── de.php
```

## Registered events (all via Bootstrap)

In Elgg 5.x hooks and events are unified — all callbacks below are registered
via `elgg_register_event_handler()` and receive `\Elgg\Event`.

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

Stored via `ElggUser::setPluginSetting()` (plugin user settings, distinct from
the entity-level private settings concept that was removed in 5.0).

## Dependencies

- `elgg/elgg` `~5.1.0`
- `php` `>=8.1`
- `ext-intl` (newly required by Elgg 5.x)
- `composer/installers` `^2.0`
- Suggests: `messages` (the messaging event handlers are only meaningful when the messages plugin is active)

## Seeding

No seeder shipped — the plugin owns no entity types/subtypes/relationships
and persists only per-user plugin settings. No seeder is required (no
visible gap in seeded fleets). Documented exception per the elgg-migrate
acceptance gate.

## Migration notes (4.x → 5.x)

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

## Known security debt (carried over from 4.x → 5.x)

- `Access::applyActivityPrivacy()` builds a raw `NOT EXISTS` SQL fragment using
  `elgg_get_config('dbprefix')` string interpolation. Inputs are not directly
  attacker-controlled (event params come from the access framework, the
  literal value is a class constant), but the construction should be refactored
  to a `Doctrine\DBAL\Query\QueryBuilder` in the 5.x → 6.x step to align with
  the convention used in core. Tracked under epic h2dnn residuals.
