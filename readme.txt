=== ArvanCloud Partner Network ===
Contributors: arvancloud
Tags: arvancloud, reseller, partner, onboarding
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later

A secure, extensible WordPress foundation for the ArvanCloud reseller and commercial partner network.

== Description ==

This initial release provides the plugin bootstrap and responsive RTL onboarding experience. It deliberately excludes reseller business logic and external API integration so those modules can be implemented against a stable base.

Included:

* Activation defaults and safe one-time welcome redirect.
* Dedicated WordPress admin menu and responsive RTL welcome screen.
* Nonce-protected onboarding action and administrator capability checks.
* Escaped output, sanitized state and local dependency-free SVG icons.
* Extension hooks for later profile, opportunity, connection and API modules.
* Clean uninstall routine.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate ArvanCloud Partner Network.
3. WordPress redirects an administrator to the welcome screen.
4. Click Start setup to initialize the next onboarding step.

== Changelog ==

= 1.0.0 =
* Initial plugin foundation and welcome experience.

