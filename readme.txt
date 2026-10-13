=== THRG Plugin Detective ===
Contributors: hackrepair, tvcnet
Donate link: https://hackrepair.com/donations/buy-jim-a-coffee
Tags: troubleshoot, plugin conflict, white screen, maintenance mode, debug
Requires at least: 6.9.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find the plugin that broke your site in a few quick checks, even when wp-admin is down. By Jim Walker, The Hack Repair Guy.

== Description ==

Something stopped working after an update or a new plugin. The usual advice is to switch every plugin off, then turn them back on one at a time, checking the site after each one. It works. It also takes forever.

**Plugin Detective does it in halves.** You show it where the problem is, it switches some plugins off, and you tell it whether the problem is still there. A site with 40 plugins usually takes six or seven checks.

When you're done, every plugin goes back the way it was, unless you decide to keep the problem plugin switched off.

= Works when your site doesn't =

Plugin Detective has its own troubleshooter page that runs without loading any plugins or your theme. If a plugin takes the whole site down (white screen, "critical error," or wp-admin won't load), the troubleshooter still opens:

`https://yoursite.com/wp-content/plugins/hackrepair-plugin-detective/troubleshoot/`

Bookmark it. You'll find the exact address under Tools > Plugin Detective.

= Stuck in maintenance mode? =

If an update got interrupted and visitors see "Briefly unavailable for scheduled maintenance," open the troubleshooter with `?restore=1` on the end of the address and click the button to remove the leftover file.

= Built with security in mind =

* If you're already logged in to wp-admin, the troubleshooter recognizes you. No second login.
* The troubleshooter's own login uses WordPress's normal password check, gives one generic error for every failure, and locks an address out for 15 minutes after 5 failed tries.
* Two-factor login is enforced. If your account uses HackGuard Security or the Two Factor plugin, you'll be asked for your authenticator or recovery code. If it uses a 2FA plugin the troubleshooter can't check, you'll be sent to wp-admin instead, so 2FA is never skipped.
* Every change requires an administrator who can manage plugins, plus a per-session security token.
* No outside fonts, scripts, or tracking. Nothing leaves your server.
* An activity log on the Tools page shows every session, plugin change, and troubleshooter sign-in.
* Works on hardened sites that block PHP inside the plugins folder: a small `.htaccess` in the troubleshooter folder re-allows only its two entry points (both require an administrator login). Everything else stays blocked.

= Safety net =

While a session is open, a notice in wp-admin tells you how many plugins are switched off, with a one-click "Put my plugins back" button. Deleting the plugin mid-session puts your plugins back first.

= Credit where it's due =

The idea for THRG Plugin Detective came from [Plugin Detective – Troubleshooting Conflicts](https://wordpress.org/plugins/plugin-detective/) by NSquared. Thank you to Natalie MacLees and team for showing how handy a guided, halving search can be.

This is an independent rebuild with new code, design, and copy, and it isn't affiliated with or endorsed by NSquared. What it adds:

* Two-factor enforcement on the troubleshooter's own login, plus rate limiting and an activity log.
* A wp-admin safety notice with a one-click "Put my plugins back."
* A plain warning that plugins switched off during a session are off for everyone visiting your site.
* Works on hardened hosts that block PHP inside the plugins folder.
* The problem plugin stays off and everything else comes back on, so you can check your site before deciding.
* No outside fonts, scripts, or tracking. Updates come from this plugin's own GitHub releases.

== Installation ==

1. Upload the `hackrepair-plugin-detective` folder to `/wp-content/plugins/`, or upload the ZIP under Plugins > Add New > Upload Plugin.
2. Activate it.
3. Go to Tools > Plugin Detective and bookmark the emergency address.

If your site is already down, upload the folder with your host's file manager or FTP. You don't need to activate it. Go straight to the troubleshooter address above and sign in.

= Settings for wp-config.php (optional) =

* `define( 'HRPD_REQUIRE_WP_LOGIN', true );` turns off the troubleshooter's own login. Only people already logged in to wp-admin can use it.
* `define( 'HRPD_EMERGENCY_PASSWORD_LOGIN', true );` lets the troubleshooter accept a password without a two-factor code. Only for a real emergency when your 2FA plugin is what's broken. Remove it as soon as you're back in.

== Frequently Asked Questions ==

= How do I use it? =

Go to the page where you see the problem and click "Troubleshoot" in the admin bar. Or go to Tools > Plugin Detective and click "Open the troubleshooter." Then follow the steps.

= What does "must stay on" mean? =

Some sites can't work at all without a certain plugin, like the page builder a page is built with, or WooCommerce on a store. Tick those, and they stay on the whole time. If you're not sure, leave everything unticked.

= Is it safe to switch my plugins off? =

Plugin Detective only changes which plugins are switched on. It doesn't run their deactivation routines, so their settings and data aren't touched. Every way out of a session puts your plugin list back.

While you test, unticked plugins, including security plugins, are off for a few minutes. Keep sessions short and finish them when you're done.

= It says "It's not one of your plugins." Now what? =

The problem was still there with the plugins off. The usual suspects are your theme, a plugin you marked "must stay on," must-use plugins, caching, or something on the site that shouldn't be there. A hacked site can look a lot like a plugin conflict. If you're stuck, [The Hack Repair Guy can help](https://hackrepair.com/).

= It says it takes more than one plugin. What does that mean? =

No single plugin breaks it on its own, so two plugins are probably fighting each other. It can also mean the problem comes and goes, or an answer got mixed up. Clear your caches and try again.

= The troubleshooter shows "403 Forbidden." =

Your site or host blocks PHP files inside `wp-content/plugins/`. Plugin Detective ships a small `.htaccess` that re-allows just its own two entry points on Apache and LiteSpeed. On Nginx, ask your host to allow `troubleshoot/index.php` and `troubleshoot/api.php` inside the `hackrepair-plugin-detective` folder.

= The preview is blank. =

Some sites block being shown inside another page. Use "Open in a new tab." It works the same way.

= Does it work on multisite? =

Not yet. On a multisite network it stays hidden and changes nothing.

= What happens when I delete it? =

If a session is open, your plugins are put back first. Then Plugin Detective removes its own settings and activity log.

= How do updates work? =

This plugin is not distributed on the WordPress.org repository, so it checks its own GitHub releases instead. When a new version is published there, the Plugins screen shows an "update available" notice and offers a one-click update, the same as any WordPress.org plugin. The only outbound request this adds is the periodic check against GitHub for a newer release; nothing about your site's content, users, or configuration is sent.

== Screenshots ==

1. Tools > Plugin Detective, with the emergency bookmark and activity log.
2. Step 1: show the troubleshooter where the problem is.
3. Step 2: tick any plugins that must stay on.
4. Each round: is the problem still there?
5. Found it. The problem plugin stays off while you check your site.

== Changelog ==

= 2.2.0 - 2026-10-13 =
* New: The "View details" window on the Plugins screen now has a banner, an icon, and the full description, installation steps, FAQ, and changelog. It works from the plugin's own readme, so it also works while the GitHub repository is private.

= 2.1.3 - 2026-10-13 =
* Tweak: Added a credit note for the plugin that inspired this one, and a short list of what this build adds.
* Tweak: Plugin description now reads "By THRG (The Hack Repair Guy)."

= 2.1.2 - 2026-10-12 =
* Tweak: Renamed to THRG Plugin Detective. Same plugin, same folder, same settings; only the name shown on the Plugins screen changes.

= 2.1.1 - 2026-10-10 =
* Tweak: Step 2 now says plainly that a ticked plugin stays on and can't be checked, so a plugin you tick can't be found as the cause.
* Tweak: Step 2 warning now says switched-off plugins are off for everyone visiting your site, security and spam protection included, until you finish or stop. It no longer promises "a few minutes."
* Tweak: Step 2 has a plain "Cancel" instead of "Stop and put everything back," since nothing has been switched off yet. Cancelling changes nothing and doesn't touch your plugin list.
* Tweak: Plugin list in Step 2 is now in A-to-Z order.
* Tweak: The name in the top corner now reads "Signed in as ..." so it isn't mistaken for a product name.
* Fix: Long plugin names in Step 2 no longer squash the checkbox or push the version number out of line.

= 2.1.0 - 2026-10-09 =
* New: Checks for updates against this plugin's GitHub releases, the same way WordPress checks wordpress.org for everything else. Works once the `hackrepair/hackrepair-plugin-detective` repository is public; while it's private, this quietly does nothing.

= 2.0.0 - 2026-10-08 =
* New: First release from Jim Walker, The Hack Repair Guy. Rebuilt from the ground up; no earlier code, artwork, or copy carried forward.
* New: Tools > Plugin Detective screen with open/continue troubleshooting, the emergency bookmark address, maintenance-mode status, last result, and an activity log.
* New: Safety notice in wp-admin while plugins are switched off, with one-click "Put my plugins back."
* New: "Undo my last answer" during testing.
* New: When a problem plugin is found, it stays off and everything else comes back on, so you can check your site before deciding.
* New: Activity log (last 50 entries) of sessions, plugin changes, troubleshooter sign-ins, failed logins, and lockouts.
* New: Two-factor login on the troubleshooter for HackGuard Security (authenticator and recovery codes) and the Two Factor plugin (authenticator codes). Accounts using WP 2FA or Wordfence Login Security are sent to wp-admin, so 2FA is never skipped.
* New: `HRPD_REQUIRE_WP_LOGIN` and `HRPD_EMERGENCY_PASSWORD_LOGIN` settings for wp-config.php.
* New: HackRepair heart-and-wrench mascot in the plugin screens and as the troubleshooter's browser-tab icon.
* Tweak: Troubleshooter screens rebuilt in plain JavaScript, with no outside fonts, scripts, or tracking.
* Tweak: Faster, predictable search: switch everything off once, then test in halves, then confirm the last suspect on its own.
* Tweak: Stuck maintenance mode fix now asks before removing the file.
* Tweak: Works on hardened sites that block PHP inside the plugins folder (Apache and LiteSpeed). Only the troubleshooter's two entry points are allowed through.
* Tweak: Requires PHP 7.4 and WordPress 6.9.4. Tested up to WordPress 7.1.
* Security: Troubleshooter starts with WordPress's own lightweight startup and never loads plugins, must-use plugins, or the theme.
* Security: Login uses WordPress's normal password check, gives one generic error, and locks an address out for 15 minutes after 5 failed tries.
* Security: Signed, short-lived, HttpOnly troubleshooter session. Nothing secret appears in page addresses.
* Security: Every change requires an administrator who can manage plugins, plus a per-session token. Only installed plugins can be switched on.
* Security: Problem-page addresses are limited to your own site.
* Fix: Uninstall puts plugins back if a session is open, then removes all plugin data.
