# X AI Assistant

A focused WordPress plugin that automatically posts to X when a standard WordPress post is published for the first time.

## Version 1.0

- Detects `draft`/`scheduled` → `publish` transitions
- Reads the post title and permalink
- Applies a configurable `{title}` / `{url}` template
- Posts through X API v2 `POST /2/tweets` with OAuth 1.0a user context
- Saves success and error logs
- Prevents duplicate and overlapping sends
- Encrypts credential settings at rest with Sodium or OpenSSL
- Includes a confirmed, real-post test action
- Includes Git Updater headers

## Install

Download the release ZIP and install it from **Plugins → Add Plugin → Upload Plugin**, or place this directory at `wp-content/plugins/x-ai-assistant`.

Configure it at **Settings → X AI Assistant**. The X developer App must have write permission and its access token must represent the account that will publish.

## Git Updater

The main plugin file declares:

```text
GitHub Plugin URI: healingyasu/x-ai-assistant
Primary Branch: main
Release Asset: true
```

Tagged GitHub releases are built as `x-ai-assistant.zip` by GitHub Actions.

## Security

- Credentials are never committed and password fields are never echoed back.
- Settings changes and tests require `manage_options` plus WordPress nonces.
- Output is escaped and inputs are sanitized.
- X requests use WordPress safe HTTP APIs with no redirects.
- Credentials use encryption derived from the site's `AUTH_KEY` where supported.

If WordPress salts change, re-enter the credentials. Sodium or OpenSSL is required; credentials are not saved when neither is available.

## Scope

AI text generation, hashtags, scheduling, search, likes, follows, and interaction support are intentionally deferred to later versions.
