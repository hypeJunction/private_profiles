# private_profiles — Architecture (Elgg 4.x)

## Summary

Restricts access to user profile pages and the ability to send private messages
between users. Site-wide defaults are configurable in plugin settings; each user
can optionally override the defaults for their own profile (toggleable in
plugin settings). Also exposes a user-level setting to hide a user's activity
and membership listing from logged-out visitors.

The plugin owns no entity types, subtypes, or relationships of its own — it
operates entirely via hook handlers on existing entity / access / menu APIs.

## Directory layout

```
private_profiles/
├── elgg-plugin.php              # routes, actions, default settings, Bootstrap binding
├── composer.json                # elgg/elgg ^4.0, php >=7.4, composer/installers ^2.0
├── classes/
│   ├── PrivateProfilesBootstrap.php           # boot() + init() hook registrations
│   └── Elgg/PrivateProfiles/
│       ├── Access.php           # access/permission predicates + hook handlers
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

## Registered hooks (all via Bootstrap)

| Hook | Type | Handler | Notes |
|------|------|---------|-------|
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

## Per-user settings (private settings)

| Name | Values | Notes |
|------|--------|-------|
| `user_access_setting` | `no` / `yes` / `members` / `friends` | Per-user profile access override |
| `user_messages_setting` | `no` / `yes` / `friends` | Per-user messages override |
| `user_activity_setting` | `yes` / `members` | Whether activity/member listing is hidden from logged-out visitors |

## Dependencies

- `elgg/elgg` `^4.0`
- `composer/installers` `^2.0`
- Suggests: `messages` (the messaging hooks are only meaningful when the messages plugin is active)

## Seeding

No seeder shipped — the plugin owns no entity types/subtypes/relationships
and persists only per-user `private_settings`. No seeder is required (no
visible gap in seeded fleets). Documented exception per the elgg-migrate
acceptance gate.

## Migration notes (3.x → 4.x)

- Removed `manifest.xml` (metadata moved to `composer.json`).
- Composer vendor switched to `hypejunction/private_profiles` (forked maintenance lineage).
- `forward()` replaced everywhere — `header() + exit` in route:rewrite hooks and
  resource views; `throw ValidationException` in `action:validate` hook.
- `ElggPlugin::setUserSetting()` → `ElggUser::setPluginSetting()`.
- Dropped `$plugin->getManifest()->getName()` (manifest removed); use
  `$plugin->getDisplayName()`.
- Added Elgg-standard PHPCS docblocks (`@param`/`@return`) to all public methods
  and global helpers.

## Known security debt to address in 4.x → 5.x

- `Access::applyActivityPrivacy()` builds a raw `NOT EXISTS` SQL fragment using
  `elgg_get_config('dbprefix')` string interpolation. Inputs are not directly
  attacker-controlled (hook params come from access framework), but should be
  refactored to Doctrine QueryBuilder in the next step to be consistent with
  5.x conventions.
