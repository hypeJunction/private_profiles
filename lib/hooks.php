<?php

/**
 * Setup user hover menu / profile page title menu
 *
 * @param \Elgg\Event $event "register","menu:user_hover" or "register","menu:title" event
 *
 * @return \Elgg\Menu\MenuItems|null
 */
function setupUserHoverMenu(\Elgg\Event $event) {

	$user = $event->getEntityParam();
	if (!elgg_is_logged_in() || !$user instanceof ElggUser) {
		return;
	}

	if (elgg_get_logged_in_user_guid() === $user->guid) {
		return;
	}

	$menu = $event->getValue();
	if (!\Elgg\PrivateProfiles\Access::canSendPrivateMessage($user)) {
		// Remove send message item if viewer is not allowed
		// to send messages to the user
		$menu->remove('send');
	}

	return $menu;
}
