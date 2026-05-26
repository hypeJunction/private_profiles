<?php

namespace Elgg\PrivateProfiles;

use Elgg\Database\QueryBuilder;
use ElggUser;

/**
 * Access control helpers for Private Profiles plugin
 */
class Access {

	const ACCESS_PUBLIC = 'yes';
	const ACCESS_PRIVATE = 'no';
	const ACCESS_FRIENDS = 'friends';
	const ACCESS_LOGGED_IN = 'members';

	/**
	 * As elgg_check_access_overrides() was removed in Elgg 3
	 * we re-implement it here
	 *
	 * @param int $user_guid The user to check against.
	 * @return bool
	 */
	public static function privateprofiles_check_access_overrides($user_guid = 0) {
		if (!$user_guid || $user_guid <= 0) {
			$is_admin = false;
		} else {
			$user = get_user((int) $user_guid);
			$is_admin = $user ? $user->isAdmin() : false;
		}

		return ($is_admin || \elgg_get_ignore_access());
	}

	/**
	 * Check if the viewer has permissions to access user profile
	 *
	 * @param ElggUser      $user   Profile owner
	 * @param ElggUser|null $viewer Viewer (default to logged in user)
	 *
	 * @return bool
	 */
	public static function hasAccessToProfile(ElggUser $user, ?ElggUser $viewer = null) {
		if (!isset($viewer)) {
			$viewer = \elgg_get_logged_in_user_entity();
		}

		$viewer_guid = $viewer ? (int) $viewer->guid : 0;

		if (self::privateprofiles_check_access_overrides($viewer_guid)) {
			return true;
		}

		if ($viewer && $user->canEdit($viewer_guid)) {
			return true;
		}

		$access_setting = self::getAccessSetting($user);

		switch ($access_setting) {
			case self::ACCESS_PRIVATE:
			default:
				return $viewer && $user->guid == $viewer->guid;

			case self::ACCESS_PUBLIC:
				return true;

			case self::ACCESS_LOGGED_IN:
				return (bool) $viewer;

			case self::ACCESS_FRIENDS:
				return $viewer && $viewer->isFriendOf($user->guid);
		}
	}

	/**
	 * Get profile access setting for the user
	 *
	 * @param ElggUser $user Profile owner
	 * @return string
	 */
	public static function getAccessSetting(ElggUser $user) {

		$access_setting = \elgg_get_plugin_setting('default_access_setting', 'private_profiles', self::ACCESS_PRIVATE);

		$custom_access_setting = \elgg_get_plugin_setting('custom_access_setting', 'private_profiles', 'yes');
		if ($custom_access_setting != 'yes') {
			// Users are not allowed to customize their own settings
			return $access_setting;
		}

		$user_access_setting = \elgg_get_plugin_user_setting('user_access_setting', $user->guid, 'private_profiles');
		if ($user_access_setting) {
			$access_setting = $user_access_setting;
		}

		return $access_setting;
	}

	/**
	 * Check if the sender is allowed to send a private message to the recipient
	 *
	 * @param ElggUser      $recipient Recipient
	 * @param ElggUser|null $sender    Sender (default to logged in user)
	 *
	 * @return bool
	 */
	public static function canSendPrivateMessage(ElggUser $recipient, ?ElggUser $sender = null) {
		if (!isset($sender)) {
			$sender = \elgg_get_logged_in_user_entity();
		}

		if (!$sender) {
			// Non-logged in users can't send messages
			return false;
		}

		if (self::privateprofiles_check_access_overrides($sender->guid)) {
			return true;
		}

		if ($recipient->canEdit($sender->guid)) {
			return true;
		}

		$messages_setting = self::getMessagesSetting($recipient);

		switch ($messages_setting) {
			case self::ACCESS_PRIVATE:
			default:
				return $recipient->guid == $sender->guid;

			case self::ACCESS_PUBLIC:
			case self::ACCESS_LOGGED_IN:
				return ($sender);

			case self::ACCESS_FRIENDS:
				return $sender && $sender->isFriendOf($recipient->guid);
		}
	}

	/**
	 * Get message setting for the user
	 *
	 * @param ElggUser $user Profile owner
	 * @return string
	 */
	public static function getMessagesSetting(ElggUser $user) {

		$message_setting = \elgg_get_plugin_setting('default_messages_setting', 'private_profiles', self::ACCESS_PRIVATE);

		$custom_setting = \elgg_get_plugin_setting('custom_access_setting', 'private_profiles', 'yes');
		if ($custom_setting != 'yes') {
			// Users are not allowed to customize their own settings
			return $message_setting;
		}

		$user_message_setting = \elgg_get_plugin_user_setting('user_messages_setting', $user->guid, 'private_profiles');
		if ($user_message_setting) {
			$message_setting = $user_message_setting;
		}

		return $message_setting;
	}

	/**
	 * Intercept a message being sent to a user without sufficient permissions
	 *
	 * @param \Elgg\Event $event "action:validate","messages/send" event
	 *
	 * @return void
	 * @throws \Elgg\Exceptions\Http\ValidationException
	 */
	public static function interceptPrivateMessage(\Elgg\Event $event) {

		$recipients = get_input('recipients');
		$original_msg_guid = (int) get_input('original_guid');

		if ($original_msg_guid) {
			// Allow users to respond to messages they have received
			return;
		}

		if (!is_array($recipients)) {
			return;
		}

		$error = false;
		foreach ($recipients as $guid) {
			$recipient = get_user((int) $guid);
			if (!$recipient) {
				continue;
			}

			if (!self::canSendPrivateMessage($recipient)) {
				$error = true;
				break;
			}
		}

		if ($error) {
			throw new \Elgg\Exceptions\Http\ValidationException(\elgg_echo('private_profiles:sending_denied'));
		}

		return;
	}

	/**
	 * Hide user activity and membership listing according to settings
	 *
	 * Excludes entities whose owner (or, for user entities, the entity itself) has
	 * set the per-user activity setting to "members-only". The exclusion is
	 * expressed as a parameterized `NOT EXISTS` subquery built via Elgg's
	 * QueryBuilder rather than raw SQL — see ARCHITECTURE.md migration notes for
	 * the 5.x → 6.x rewrite.
	 *
	 * @param \Elgg\Event $event "get_sql","access" event
	 *
	 * @return array|null
	 */
	public static function applyActivityPrivacy(\Elgg\Event $event) {

		if (\elgg_in_context('action')) {
			// let actions such as /login run without hinderance
			return;
		}

		$user_guid = $event->getParam('user_guid');
		if ($user_guid) {
			// activity privacy setting only applies to logged out users
			return;
		}

		if ($event->getParam('ignore_access')) {
			return;
		}

		$qb = $event->getParam('query_builder');
		if (!$qb instanceof QueryBuilder) {
			// Defensive: pre-6.x event payloads did not include the QueryBuilder.
			// In that case there is no safe way to inject a parameterized clause —
			// skip rather than fall back to string interpolation.
			return;
		}

		$table_alias = $event->getParam('table_alias');
		$guid_column = $event->getParam('guid_column', 'guid');
		$owner_guid_column = $event->getParam('owner_guid_column', 'owner_guid');

		$qualified_guid = $table_alias ? "{$table_alias}.{$guid_column}" : $guid_column;
		$qualified_owner_guid = $table_alias ? "{$table_alias}.{$owner_guid_column}" : $owner_guid_column;

		$return = $event->getValue();
		$return['ands'][] = self::buildActivityPrivacyExclusion($qb, $qualified_guid, $qualified_owner_guid);

		return $return;
	}

	/**
	 * Namespaced metadata name under which `user_activity_setting` is stored on
	 * a user entity. Matches `ElggEntity::getNamespacedPluginSettingName('user',
	 * 'private_profiles', 'user_activity_setting')` (Elgg 4.x+).
	 */
	protected const ACTIVITY_SETTING_METADATA_NAME = 'plugin:user_setting:private_profiles:user_activity_setting';

	/**
	 * Build a parameterized NOT EXISTS clause that excludes entities whose owner
	 * (or the entity itself, when it is a user) has opted into the members-only
	 * activity setting.
	 *
	 * Returns a SQL fragment safe to append to the outer query's WHERE — all
	 * dynamic values are bound through the supplied QueryBuilder's parameter bag.
	 *
	 * @param QueryBuilder $qb                   Outer query builder (receives bound params)
	 * @param string       $qualified_guid       Outer-query column expression (e.g. e.guid)
	 * @param string       $qualified_owner_guid Outer-query column expression (e.g. e.owner_guid)
	 *
	 * @return string
	 */
	protected static function buildActivityPrivacyExclusion(QueryBuilder $qb, string $qualified_guid, string $qualified_owner_guid): string {
		$setting_name = self::ACTIVITY_SETTING_METADATA_NAME;

		// Bind the two literal values on the outer query builder so they
		// participate in the prepared-statement parameter bag when the access
		// framework executes the final SELECT.
		$name_param = $qb->param($setting_name, ELGG_VALUE_STRING);
		$value_param = $qb->param(self::ACCESS_LOGGED_IN, ELGG_VALUE_STRING);

		// Build the subquery purely for its qualified table identifier
		// (metadata + db prefix) and SELECT shape. The WHERE clause uses the
		// already-bound parameters from the outer query.
		$sub = $qb->subquery('metadata', 'pp_md');
		$sub->select('1');

		// entity_guid matches either the outer entity's guid OR its owner_guid.
		// Both are controlled column identifiers supplied by the access
		// framework (not user input).
		$entity_match = $sub->expr()->or(
			"pp_md.entity_guid = {$qualified_guid}",
			"pp_md.entity_guid = {$qualified_owner_guid}"
		);

		$sub->where($entity_match)
			->andWhere("pp_md.name = {$name_param}")
			->andWhere("pp_md.value = {$value_param}");

		return "NOT EXISTS ({$sub->getSQL()})";
	}
}
