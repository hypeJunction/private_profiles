Private Profiles for Elgg 1.9 - 1.12 and Elgg 2.X
=================================================

Latest Version: 1.9.7  
Released: 2015-09-23  
Contact: iionly@gmx.de  
License: GNU General Public License version 2  
Copyright: (C) iionly 2014-2015


Description
-----------

This plugin allows to restrict the access to profile pages and to restrict usage of private messages. There are side-wide plugin settings available and optionally users can configure the restrictions on their own (default is to allow users to configure their own preferences).

Access to profile pages can be limited to the user's own profile page (default), profile pages of users who made the user their friend, restrict accessing profile pages if the user is logged-in or the profile page access can be made unrestricted.

The sending of private messages to other users can be disabled, can be limited to users who made a user their friend (default) or there are no restrictions regarding who a user can send a private message to. If a user has not yet configured any personal preferences the side-wide settings are used instead.

Independent of the plugin settings an admin can still access all profile pages in any case and can send and receive private messages to and from all users.

Keep in mind that the limitation of access to profile pages and sending of private messages with the "Friends only" option selected does NOT mean that a user can bypass these limitations by simply friending another user. It's the OTHER user who has to friend the user first before the "Friends only" condition is fullfilled. Best would be to also use the "Friend request" plugin to have real two-way friend relationships where friend requests can be accepted or denied right away to make it less confusing for a user to understand who he is friends with.


Installation
------------

1. If you have a previous version of the Private Profiles plugin installed, disable the plugin, then remove the plugin's folder from the mod directory before installing the new version,
2. Copy the private_profiles folder into the mod folder of your site,
3. Enable the plugin in the admin section of your site,
4. Check the plugin settings page and change the settings if necessary.
