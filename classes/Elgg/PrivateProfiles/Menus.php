<?php

namespace Elgg\PrivateProfiles;

use ElggMenuItem;

/**
 * Menu registrations for Private Profiles plugin
 */
class Menus {

	/**
	 * Setup page menu
	 *
	 * @param \Elgg\Event $event "register","menu:page" event
	 *
	 * @return \Elgg\Menu\MenuItems|array|null
	 */
	public static function setupPageMenu(\Elgg\Event $event) {
		if (!elgg_in_context('settings')) {
			return;
		}

		$user = elgg_get_page_owner_entity();
		if (!$user || !$user->canEdit()) {
			return;
		}

		$menu = $event->getValue();
		$menu[] = ElggMenuItem::factory([
			'name' => 'private_profiles_usersettings',
			'text' => elgg_echo('private_profiles:usersettings'),
			'href' => "settings/privacy/{$user->username}",
		]);

		return $menu;
	}
}
