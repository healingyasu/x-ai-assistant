=== X AI Assistant ===
Contributors: healingyasu
Tags: x, twitter, social media, automation
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publishes a templated X post when a WordPress post is first published.

== Description ==

X AI Assistant version 1.0 connects WordPress to the X API using OAuth 1.0a user credentials.

Features:

* Detects the first publication of standard WordPress posts.
* Supports `{title}` and `{url}` template placeholders.
* Prevents repeat posting and guards against concurrent requests.
* Stores a local success/error log.
* Includes a real-post test button with confirmation.
* Encrypts API credentials with Sodium or OpenSSL.
* Supports updates through Git Updater and GitHub releases.

== Installation ==

1. Install the release ZIP or clone the repository into `wp-content/plugins/x-ai-assistant`.
2. Activate the plugin.
3. Open Settings > X AI Assistant.
4. Enter the API Key, API Secret, Access Token, and Access Token Secret from an X developer App with write permission.
5. Save, then use Send test post.

The X API account and access tier must permit `POST /2/tweets`. A test sends a real public post.
The server must provide Sodium or OpenSSL so credentials can be stored securely.

== Changelog ==

= 1.0.0 =
* Initial release.
