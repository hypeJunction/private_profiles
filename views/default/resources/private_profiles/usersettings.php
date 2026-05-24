<?php

elgg_gatekeeper();

$username = elgg_extract('username', $vars);
if ($username) {
	$user = elgg_get_user_by_username((string) $username);
} else {
	$user = elgg_get_logged_in_user_entity();
}

if (!$user || !$user->canEdit()) {
	header('Location: ' . elgg_normalize_url('settings/user'), true, 302);
	exit;
}

elgg_set_context('settings');

elgg_set_page_owner_guid($user->guid);

$title = elgg_echo('private_profiles:usersettings');

elgg_register_menu_item('breadcrumbs', [
	'name' => 'settings',
	'text' => elgg_echo('settings'),
	'href' => "settings/user/{$user->username}",
]);
elgg_register_menu_item('breadcrumbs', [
	'name' => 'private_profiles:usersettings',
	'text' => $title,
	'href' => false,
]);

$content = elgg_view_form('private_profiles/usersettings_save', [], [
	'user' => $user,
]);

$params = [
	'content' => $content,
	'title' => $title,
	'filter' => '',
];

$layout = elgg_view_layout('default', $params);

echo elgg_view_page($title, $layout);
