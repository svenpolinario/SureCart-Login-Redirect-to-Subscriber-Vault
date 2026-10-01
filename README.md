# SureCart Login Redirect to Subscriber Vault

## Purpose

This plugin redirects a customer to:

`/digital-subscriber-pass-download-vault/`

only after a successful login that was initiated from:

`/customer-dashboard/`

A customer who is already logged in and manually visits `/customer-dashboard/` will remain there.

## Installation

1. In WordPress, go to Plugins > Add New > Upload Plugin.
2. Upload `surecart-login-redirect-to-vault.zip`.
3. Activate the plugin.
4. Test in a private/incognito browser window.

## Test

1. Log out.
2. Open `/customer-dashboard/`.
3. Log in with a valid SureCart customer account.
4. The user should be redirected to `/digital-subscriber-pass-download-vault/`.
5. Then manually visit `/customer-dashboard/`.
6. The user should remain on `/customer-dashboard/`.

The plugin uses a short-lived browser flag and consumes it after the redirect, so it does not globally redirect the Customer Dashboard.

## Important

This plugin is intentionally site-specific. If either URL changes, edit the two path constants at the top of the PHP file:

DASHBOARD_PATH
TARGET_PATH
