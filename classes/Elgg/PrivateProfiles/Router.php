<?php

namespace Elgg\PrivateProfiles;

/**
 * Routing hooks for Private Profiles plugin
 */
class Router {

	/**
	 * Route /profile pages
	 *
	 * @param \Elgg\Event $event "route:rewrite","profile" event
	 *
	 * @return void
	 */
	public static function routeProfile(\Elgg\Event $event) {
		$return = $event->getValue();
		if (!is_array($return)) {
			return;
		}

		$segments = (array) \elgg_extract('segments', $return, []);

		$username = array_shift($segments);
		$user = \elgg_get_user_by_username((string) $username);

		if (!$user) {
			elgg_register_error_message(\elgg_echo('private_profiles:invalid_username'));
			header('Location: ' . \elgg_normalize_url(REFERRER), true, 302);
			exit;
		}

		if (!Access::hasAccessToProfile($user)) {
			elgg_register_error_message(\elgg_echo('private_profiles:access_denied'));
			header('Location: ' . \elgg_normalize_url(REFERRER), true, 302);
			exit;
		}
	}

	/**
	 * Route /settings/privacy pages
	 *
	 * @param \Elgg\Event $event "route:rewrite","settings" event
	 *
	 * @return array|null
	 */
	public static function rewriteSettingsRoute(\Elgg\Event $event) {
		$return = $event->getValue();
		if (!is_array($return)) {
			return;
		}

		$identifier = \elgg_extract('identifier', $return);
		$segments = (array) \elgg_extract('segments', $return, []);

		$page = array_shift($segments);
		$username = array_shift($segments);

		if ($page == 'privacy') {
			return [
				'identifier' => 'private_profiles',
				'segments' => [
					'usersettings',
					$username,
				],
			];
		}
	}

	/**
	 * Handle /private_profiles page
	 *
	 * @param array $segments URL segments
	 * @return bool
	 */
	public function handlePrivateProfiles($segments) {

		$page = array_shift($segments);
		$username = array_shift($segments);

		echo \elgg_view_resource("private_profiles/$page", [
			'username' => $username,
		]);
		
		return true;
	}
}
