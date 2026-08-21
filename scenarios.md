# Scenarios

This file lists the plugin scenarios that are implemented and currently expected to work based on the codebase review on `2026-08-21`.

## Reseller admin scenarios

1. Activate the plugin and let it create its custom tables, scheduled cron hooks, and the customer portal page automatically.
2. Open the welcome page after activation and either complete onboarding with Machine User credentials or skip it for demo mode.
3. Save reseller settings including organization name, API mode, markup, billing threshold, catalog sync interval, allowed regions, and S3 endpoint.
4. Test ArvanCloud connectivity from the admin panel and inspect sanitized diagnostics without exposing stored secrets.
5. Refresh API inventory manually and sync the storefront catalog from the official ArvanCloud public pages.
6. Run hourly billing manually from the admin panel for demo or live-managed resources.
7. Review dashboards for wallets, transactions, orders, resources, API inventory, audit logs, and usage logs.

## Customer authentication scenarios

1. Open the customer portal while logged out and request a phone login code when the mock SMS provider is enabled.
2. Receive a server-issued OTP `requestId` through AJAX and move to the verification step only after the request succeeds.
3. Verify the mock OTP with the same phone number and the active `requestId`.
4. Reuse an existing WordPress user if the phone number already belongs to one account.
5. Create a new subscriber automatically when registration is enabled and the phone number is not linked yet.
6. Reject expired, missing, mismatched, or rate-limited OTP requests.
7. Reject mock OTP login only when a different SMS provider is configured.

## Wallet and finance scenarios

1. View the current wallet balance and threshold in the customer portal.
2. Perform a mock wallet top-up through the front-end form.
3. Record a payment row, credit the wallet ledger, and add an audit entry for the top-up.
4. Show the customer only their own recent transactions.
5. Prevent service orders when the wallet balance is below the required minimum.

## Product and ordering scenarios

1. Render the public product catalog with CDN, cloud server, and object storage pricing blocks.
2. Create a CDN order from the customer portal after nonce validation and wallet balance checks.
3. Provision a CDN resource in demo mode instantly or through the live API in live mode.
4. Create a cloud server order from the customer portal with validated server name, region, image, flavor, zone, and root disk size.
5. Provision a cloud server in demo mode instantly or through the live API in live mode.
6. Persist each successful order in `acr_orders` and each provisioned service in `acr_resources`.

## Resource management scenarios

1. Let a customer view only their own services in the portal.
2. Manage cloud servers with power on, power off, reboot, reset password, rescue, unrescue, rename, resize, resize disk, and terminate actions.
3. Update the stored resource state and configuration after a successful server action.
4. Clear cached API snapshots after provisioning changes.

## Billing and restriction scenarios

1. Run hourly billing over active resources through `acr_hourly_billing`.
2. Prevent duplicate usage rows for the same resource and billing period.
3. Debit wallet balances atomically and write ledger rows plus usage logs.
4. Send low-balance email notifications with a transient lock to avoid spam.
5. Suspend resources when balance is insufficient or exhausted.
6. Run daily settlement aggregation through `acr_daily_settlement`.

## Catalog and content scenarios

1. Sync pricing and policy source pages from official ArvanCloud URLs with `wp_safe_remote_get`.
2. Keep fallback catalog values when the upstream pricing pages fail or change structure.
3. Expose a read-only catalog REST endpoint at `/wp-json/acr/v1/catalog`.
4. Render both Gutenberg blocks: `acr/product-catalog` and `acr/customer-profile`.

## Data protection scenarios

1. Store Machine User and S3 secrets encrypted with AES-256-GCM.
2. Avoid re-displaying saved secrets in admin forms.
3. Keep financial data on uninstall by default.
4. Remove plugin tables only when `ACR_REMOVE_DATA_ON_UNINSTALL` is explicitly set to `true`.
