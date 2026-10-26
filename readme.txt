=== The Hack Repair Guy's Plugin Detective ===
Contributors: hackrepair, tvcnet
Donate link: https://hackrepair.com/donations/buy-jim-a-coffee
Tags: troubleshoot, plugin conflict, white screen, maintenance mode, debug
Requires at least: 6.9.4
Tested up to: 7.1.3
Requires PHP: 7.4
Stable tag: 3.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Support link: https://github.com/hackrepair/hackrepair-plugin-detective/discussions

Find the plugin that broke your site in a few quick checks, even when wp-admin is down. By Jim Walker, The Hack Repair Guy.

== Description ==

The native View Details window includes an Other THRG Plugins guide to five other tools from The Hack Repair Guy.

Something stopped working after an update or a new plugin. The usual advice is to switch every plugin off, then turn them back on one at a time, checking the site after each one. It works. It also takes forever.

**Plugin Detective does it in halves.** You show it where the problem is, it switches some plugins off, and you tell it whether the problem is still there. A site with 40 plugins usually takes six or seven checks.

When you're done, every plugin goes back the way it was, unless you decide to keep the problem plugin switched off or delete it.

= A background check on the plugin it finds =

Finding the plugin is half the story. Plugin Detective then looks it up for you:

* Is there a newer version? An update is often the fix.
* Has wordpress.org closed it? Then it won't get updates, and it's time to replace it.
* Has it gone more than two years without an update?
* Does the version you have carry **known security holes**? You'll see how many, how serious, and whether updating fixes them, with links to the details.

A plugin that breaks your site and has a known security hole is worth taking seriously.

= A case report you can send =

At the end, you get a plain-English report: what the problem was, which plugin caused it, the background check, what was left switched off or deleted, and the WordPress, PHP and theme details a developer will ask for. Copy it or download it, then send it to the plugin's developer, your web person, or The Hack Repair Guy. The last report also stays on the Tools page.

= Works when your site doesn't =

Plugin Detective has its own Fix page that runs without loading any plugins or your theme. If a plugin takes the whole site down (white screen, "critical error," or wp-admin won't load), the Fix page still opens:

`https://yoursite.com/wp-content/plugins/hackrepair-plugin-detective/fix/`

Bookmark it. You'll find the exact address under Tools > Plugin Detective.

= Stuck in maintenance mode? =

If an update got interrupted and visitors see "Briefly unavailable for scheduled maintenance," open the Fix page with `?restore=1` on the end of the address and click the button to remove the leftover file.

= Built with security in mind =

* If you're already logged in to wp-admin, the Fix page recognizes you. No second login.
* The Fix page's own login uses WordPress's normal password check, gives one generic error for every failure, and locks an address out for 15 minutes after 5 failed tries.
* Two-factor login is enforced. If your account uses HackGuard Security or the Two Factor plugin, you'll be asked for your authenticator or recovery code. If it uses a 2FA plugin the Fix page can't check, you'll be sent to wp-admin instead, so 2FA is never skipped.
* Every change requires an administrator who can manage plugins, plus a per-session security token.
* No outside fonts, scripts, or tracking. The only outside lookups are the background check and the update check, both described under "External services" below.
* An activity log on the Tools page shows every session, plugin change, and Fix page sign-in.
* Works on hardened sites that block PHP inside the plugins folder: a small `.htaccess` in the Fix page folder re-allows only its two entry points (both require an administrator login). Everything else stays blocked.

= Safety net =

While a session is open, a notice in wp-admin tells you how many plugins are switched off, with a one-click "Put my plugins back" button. Deleting the plugin mid-session puts your plugins back first.

= Credit where it's due =

The idea for The Hack Repair Guy's Plugin Detective came from [Plugin Detective – Troubleshooting Conflicts](https://wordpress.org/plugins/plugin-detective/) by NSquared. Thank you to Natalie MacLees and team for showing how handy a guided, halving search can be.

This is an independent rebuild with new code, design, and copy, and it isn't affiliated with or endorsed by NSquared. What it adds:

* Two-factor enforcement on the Fix page's own login, plus rate limiting and an activity log.
* A wp-admin safety notice with a one-click "Put my plugins back."
* A plain warning that plugins switched off during a session are off for everyone visiting your site.
* Works on hardened hosts that block PHP inside the plugins folder.
* The problem plugin stays off and everything else comes back on, so you can check your site before deciding. Then keep it off, turn it back on, or delete it.
* A background check on the plugin it finds: newer version, closed on wordpress.org, and known security holes.
* A case report you can copy or download and send to a developer.
* No outside fonts, scripts, or tracking. Updates come from this plugin's own GitHub releases.

== Installation ==

1. Upload the `hackrepair-plugin-detective` folder to `/wp-content/plugins/`, or upload the ZIP under Plugins > Add New > Upload Plugin.
2. Activate it.
3. Go to Tools > Plugin Detective and bookmark the emergency address.

If your site is already down, upload the folder with your host's file manager or FTP. You don't need to activate it. Go straight to the Fix page address above and sign in.

= Settings for wp-config.php (optional) =

* `define( 'HRPD_REQUIRE_WP_LOGIN', true );` turns off the Fix page's own login. Only people already logged in to wp-admin can use it.
* `define( 'HRPD_NO_BACKGROUND_CHECK', true );` turns off the background check, so the plugin never contacts wordpress.org or WPVulnerability.
* `define( 'HRPD_EMERGENCY_PASSWORD_LOGIN', true );` lets the Fix page accept a password without a two-factor code. Only for a real emergency when your 2FA plugin is what's broken. Remove it as soon as you're back in.

== External services ==

When the Fix page finds a problem plugin, it runs a background check on that one plugin. It sends only the plugin's folder name (for example `svg-support`). No site address, user, or other data is sent, and answers are kept for 12 hours so the same plugin isn't looked up again and again.

* **wordpress.org Plugin Directory API** (`api.wordpress.org`): the newest version, when it was last updated, and whether it has been closed. The same service WordPress itself uses to check for plugin updates. [Terms](https://wordpress.org/about/privacy/)
* **WPVulnerability** (`www.wpvulnerability.net`): known security problems, gathered from CVE, Wordfence, Patchstack, WPScan and others. Free, no account, data released under CC0. [Terms and license](https://www.wpvulnerability.com/license/)

Separately, WordPress checks this plugin's own GitHub releases for updates (`api.github.com`), the same way it checks wordpress.org for other plugins. Nothing about your site is sent.

Turn the background check off with `define( 'HRPD_NO_BACKGROUND_CHECK', true );` in wp-config.php.

== Support ==

For questions, ideas and community support, visit GitHub Discussions:
https://github.com/hackrepair/hackrepair-plugin-detective/discussions

Report reproducible bugs through Issues:
https://github.com/hackrepair/hackrepair-plugin-detective/issues

Report security concerns privately to Jim Walker at jim@hackrepair.com. Do not include credentials or sensitive site information in public posts.

== Frequently Asked Questions ==

= Where does the security information come from? =

From WPVulnerability, a free public database that pulls together reports from CVE, Wordfence, Patchstack, WPScan and others. It lists problems by plugin and version, so Plugin Detective only shows the ones that affect the version you have. If a premium or custom plugin happens to share a folder name with a different plugin, the results may not match; the links let you check.

= What's in the case report? =

The problem page, the result, how many checks it took, what was left switched off, the background check, your other active plugins and their versions, and your WordPress, PHP and theme versions. It doesn't include passwords or anything from your database. It does include your site address and plugin list, so only send it to people you trust.

= What does "Delete this plugin" do? =

It removes the problem plugin's files from your site, after asking you first, and turns every other plugin back on. It doesn't run the plugin's own uninstall routine, so the plugin's saved settings and data stay in your database, and a reinstall picks up where it left off. It never touches Plugin Detective itself or anything outside the plugins folder. If the files can't be removed from here, the plugin stays switched off and you can delete it from the Plugins page.

= How can I try it out safely? =

Download the free test plugin, [Vanishing Act (Test Plugin)](https://github.com/hackrepair/hackrepair-plugin-detective/raw/main/test-plugin/vanishing-act.zip), and install it from Plugins > Add New > Upload Plugin. Switch it on and your home page shows "Page not found." Your dashboard keeps working. Then run Plugin Detective and watch it track the culprit down. Delete Vanishing Act when you're done. It's a harmless test plugin, so don't leave it on a live site. A copy also ships inside this plugin's folder, in `test-plugin/vanishing-act.zip`.

= How do I use it? =

Go to the page where you see the problem and click "Fix" in the admin bar. Or go to Tools > Plugin Detective and click "Open Fix." Then follow the steps.

= What does "must stay on" mean? =

Some sites can't work at all without a certain plugin, like the page builder a page is built with, or WooCommerce on a store. Tick those, and they stay on the whole time. If you're not sure, leave everything unticked.

= Is it safe to switch my plugins off? =

Plugin Detective only changes which plugins are switched on. It doesn't run their deactivation routines, so their settings and data aren't touched. Every way out of a session puts your plugin list back.

While you test, unticked plugins, including security plugins, are off for everyone visiting your site until you finish or stop. Keep sessions short.

= It says "It's not one of your plugins." Now what? =

The problem was still there with the plugins off. The usual suspects are your theme, your PHP version (did your host just change it?), a plugin you marked "must stay on," must-use plugins, caching, or something on the site that shouldn't be there. If your host just changed the PHP version, an older theme or must-use plugin may not support the new one. That breaks a site just like a plugin conflict. Switch PHP back in your hosting control panel and look again. A [hacked site](https://hackrepair.com/) can look a lot like a plugin conflict. If you're stuck, [The Hack Repair Guy can help](https://hackrepair.com/).

= It says it takes more than one plugin. What does that mean? =

No single plugin breaks it on its own, so two plugins are probably fighting each other. It can also mean the problem comes and goes, or an answer got mixed up. Clear your caches and try again.

= The Fix page shows "403 Forbidden." =

Your site or host blocks PHP files inside `wp-content/plugins/`. Plugin Detective ships a small `.htaccess` that re-allows just its own two entry points on Apache and LiteSpeed. On Nginx, ask your host to allow `fix/index.php` and `fix/api.php` inside the `hackrepair-plugin-detective` folder.

= The preview is blank. =

Some sites block being shown inside another page. Use "Open in a new tab." It works the same way.

= Does it work on multisite? =

Not yet. On a multisite network it stays hidden and changes nothing.

= What happens when I delete it? =

If a session is open, your plugins are put back first. Then Plugin Detective removes its own settings and activity log.

= How do updates work? =

This plugin is not distributed on the WordPress.org repository, so it checks its own GitHub releases instead. When a new version is published there, the Plugins screen shows an "update available" notice and offers a one-click update, the same as any WordPress.org plugin. The only outbound request this adds is the periodic check against GitHub for a newer release; nothing about your site's content, users, or configuration is sent.

== Screenshots ==

1. Tools > Plugin Detective, with the emergency bookmark, last session and activity log.
2. Step 1: show the Fix page where the problem is. Your site stays in view on the right.
3. Step 2: tick any plugins that must stay on.
4. Each round: is the problem still there? Here the test plugin's "Page not found" shows on the right.
5. Found it. The problem plugin stays off, with its background check and a case report, while you check your site.

== Changelog ==

= 3.5.1 =
* Expanded Other THRG Plugins in View Details to show five companion tools, excluding the current plugin.
* Plugin Archiver links to its official WordPress.org page.


= 3.5.0 =
* Added a Support container at the bottom of the Tools > Plugin Detective screen: a link to buy Jim a cup of coffee and a link to GitHub Discussions for questions, ideas and help.

= 3.4.5 =
* Normalize all packaged file permissions to 0644 so assets remain readable on hosting with separate PHP and web-server users.
* Add a package-permissions regression check, independent of source file permissions.


= 3.4.4 =
* Updated 2026 THRG heart branding with a gentle two-cycle animation and matching native View Details banner.
* Respects reduced motion and printing; local PNG/CSS assets require no animation library.


= 3.4.3 =
* Explain temporary update-check failures clearly and move diagnostic errors into Technical details.
* Show a retry time for confirmed GitHub rate limits.

= 3.4.2 =
* Match the compact companion-plugin cards and sidebar layout used by Disable Other Plugin Notices.
* Display the installed version in View Details.

= 3.4.1 =
* Restore bundled updater parser dependencies required by View Details.

= 3.4.0 =
* Added an Other THRG Plugins tab to View Details with companion tools and project links.

= 3.3.3 - 2026-10-16 =
* Tweak: Tools > Plugin Detective is now quiet. Notices from other plugins and themes no longer show on our page, so you can read it without the noise. They still show everywhere else in wp-admin.
* Tweak: A Copy button now sits next to the emergency address under "Bookmark this for emergencies".

= 3.3.2 - 2026-10-16 =
* Fix: Release packaging. The download now uses normal file permissions (folders 755, files 644), so images and scripts no longer return a 403 error on hosts that keep a ZIP's permissions when they unpack it.
* Fix: The 3.3.1 release was tagged before its code reached the main branch, so sites never saw it as an update. 3.3.2 brings 3.3.1 and this release together, so WordPress offers the update the normal way.

= 3.3.1 - 2026-10-16 =
* Tweak: "It's not one of your plugins" now lists your PHP version among the usual suspects and shows the version your site is running. If your host just changed PHP, an older theme or must-use plugin that can't handle it breaks a site just like a plugin conflict. Switch PHP back in your hosting control panel and look again.
* Tweak: On that same screen, "hacked site" now links to hackrepair.com, with a "Contact Jim Now?" tooltip.
* Tweak: Every link now has a tooltip that says what a click does, on the Fix page and in wp-admin.

= 3.3.0 - 2026-10-15 =
* New: Simpler names. "Troubleshoot" is now "Fix" everywhere you read it, and the emergency address is shorter: `/wp-content/plugins/hackrepair-plugin-detective/fix/`. If you bookmarked the old address, replace it with the new one from Tools > Plugin Detective.

= 3.2.1 - 2026-10-14 =
* Fix: The "Delete this plugin" button now shows on the result screen, next to "Keep it switched off" and "Turn it back on," and it works.
* Fix: The last result on the Tools page now says "found X and deleted it" after you delete the problem plugin, instead of "then turned it back on."
* Tweak: The plugin's one-line description on the Plugins screen now matches this readme.
* Tweak: Screenshots retaken in the new full-screen layout.
* Tweak: Readme, FAQ and screens now say you can delete the problem plugin, mention the GitHub update check under outside services, and no longer promise sessions last "a few minutes."

= 3.2.0 - 2026-10-14 =
* New: Vanishing Act (Test Plugin), a harmless plugin that makes your home page show "Page not found," so you can try Plugin Detective safely. Download it from this window's FAQ tab. A copy also ships in `test-plugin/vanishing-act.zip`.

= 3.1.1 - 2026-10-14 =
* Fix: The background check no longer says a plugin "gets its updates from false". When a plugin's author has switched off update checks (Update URI: false), it now says so in plain English.

= 3.1.0 - 2026-10-14 =
* New: "Delete this plugin" button next to "Keep it switched off" and "Turn it back on" on the result screen. It asks first, removes the problem plugin's files, and puts everything else back. Its saved settings stay in your database so a reinstall picks up where it left off. If the files can't be removed from here, the plugin stays switched off and you're told how to delete it from the Plugins page.

= 3.0.0 - 2026-10-14 =
* New: Background check on the plugin it finds. Shows whether a newer version is out, whether wordpress.org has closed it, whether it's gone two years without an update, and any known security holes in the version you have, with how serious they are and whether updating fixes them.
* New: Case report. A plain-English summary of the session, the background check, and your site details, ready to copy or download and send to a developer. The last report also stays on the Tools page.
* New: `HRPD_NO_BACKGROUND_CHECK` setting for wp-config.php to turn the background check off.
* New: Full-screen Fix page. What to do next fills the left two-thirds of the window and your live site fills the right third, on every screen from the first one, so you can see the site while you answer. The left side scrolls on its own and the site stays put. The site is shown at a normal desktop width, shrunk to fit; "Fit to panel" switches that off. On a narrow window the two stack.
* Tweak: The "View details" window now has a Screenshots tab.

= 2.2.1 - 2026-10-14 =
* Tweak: Renamed to The Hack Repair Guy's Plugin Detective. Same plugin, same folder, same settings.
* Tweak: Cleaner banner in the "View details" window, and compatibility now lists WordPress 7.1.3.

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
* New: Tools > Plugin Detective screen with open/continue fixing, the emergency bookmark address, maintenance-mode status, last result, and an activity log.
* New: Safety notice in wp-admin while plugins are switched off, with one-click "Put my plugins back."
* New: "Undo my last answer" during testing.
* New: When a problem plugin is found, it stays off and everything else comes back on, so you can check your site before deciding.
* New: Activity log (last 50 entries) of sessions, plugin changes, Fix page sign-ins, failed logins, and lockouts.
* New: Two-factor login on the Fix page for HackGuard Security (authenticator and recovery codes) and the Two Factor plugin (authenticator codes). Accounts using WP 2FA or Wordfence Login Security are sent to wp-admin, so 2FA is never skipped.
* New: `HRPD_REQUIRE_WP_LOGIN` and `HRPD_EMERGENCY_PASSWORD_LOGIN` settings for wp-config.php.
* New: HackRepair heart-and-wrench mascot in the plugin screens and as the Fix page's browser-tab icon.
* Tweak: Fix page screens rebuilt in plain JavaScript, with no outside fonts, scripts, or tracking.
* Tweak: Faster, predictable search: switch everything off once, then test in halves, then confirm the last suspect on its own.
* Tweak: Stuck maintenance mode fix now asks before removing the file.
* Tweak: Works on hardened sites that block PHP inside the plugins folder (Apache and LiteSpeed). Only the Fix page's two entry points are allowed through.
* Tweak: Requires PHP 7.4 and WordPress 6.9.4. Tested up to WordPress 7.1.
* Security: Fix page starts with WordPress's own lightweight startup and never loads plugins, must-use plugins, or the theme.
* Security: Login uses WordPress's normal password check, gives one generic error, and locks an address out for 15 minutes after 5 failed tries.
* Security: Signed, short-lived, HttpOnly Fix page session. Nothing secret appears in page addresses.
* Security: Every change requires an administrator who can manage plugins, plus a per-session token. Only installed plugins can be switched on.
* Security: Problem-page addresses are limited to your own site.
* Fix: Uninstall puts plugins back if a session is open, then removes all plugin data.
