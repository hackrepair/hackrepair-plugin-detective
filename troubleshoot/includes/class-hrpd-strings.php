<?php
/**
 * Every word the troubleshooter shows, in one place.
 *
 * Voice: The Hack Repair Guy. Short, calm, plain English. Say what happened,
 * what it means, and what to do next. No blame.
 *
 * Placeholders: %1$s, %2$s etc. are filled in by the app.
 *
 * @package HackRepair_Plugin_Detective
 * @author  Jim Walker, The Hack Repair Guy
 */

defined( 'ABSPATH' ) || exit;

/**
 * Troubleshooter copy.
 */
final class HRPD_Strings {

	/**
	 * All strings, keyed for the app.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		return array(
			// General.
			'appName'           => __( 'Plugin Detective', 'hackrepair-plugin-detective' ),
			'byline'            => __( 'by Jim Walker, The Hack Repair Guy', 'hackrepair-plugin-detective' ),
			'backToWp'          => __( 'Back to WordPress', 'hackrepair-plugin-detective' ),
			'signOut'           => __( 'Sign out', 'hackrepair-plugin-detective' ),
			/* translators: %1$s: the signed-in user's display name. */
			'signedInAs'        => __( 'Signed in as %1$s', 'hackrepair-plugin-detective' ),
			'working'           => __( 'One moment…', 'hackrepair-plugin-detective' ),
			'errorGeneric'      => __( 'Something went sideways. Reload the page and try again.', 'hackrepair-plugin-detective' ),
			'helpTitle'         => __( 'Still stuck?', 'hackrepair-plugin-detective' ),
			'helpText'          => __( 'Some problems aren\'t plugin problems. If your site is acting strange and nothing here explains it, The Hack Repair Guy can take a look.', 'hackrepair-plugin-detective' ),
			'helpLink'          => __( 'Get help from The Hack Repair Guy', 'hackrepair-plugin-detective' ),
			'openNewTab'        => __( 'Open in a new tab', 'hackrepair-plugin-detective' ),
			'reload'            => __( 'Reload', 'hackrepair-plugin-detective' ),
			'viewDesktop'       => __( 'Desktop view', 'hackrepair-plugin-detective' ),
			'viewNarrow'        => __( 'Fit to panel', 'hackrepair-plugin-detective' ),
			'frameNote'         => __( 'Page blank or won\'t show here? Some sites block being shown inside another page. Use "Open in a new tab" instead. It works the same way.', 'hackrepair-plugin-detective' ),

			// Sign in.
			'loginTitle'        => __( 'Sign in', 'hackrepair-plugin-detective' ),
			'loginIntro'        => __( 'Use your WordPress username and password. You need to be an administrator who can manage plugins.', 'hackrepair-plugin-detective' ),
			'loginWhy'          => __( 'This screen works even when wp-admin doesn\'t, so you can fix a broken site.', 'hackrepair-plugin-detective' ),
			'username'          => __( 'Username or email', 'hackrepair-plugin-detective' ),
			'password'          => __( 'Password', 'hackrepair-plugin-detective' ),
			'signIn'            => __( 'Sign in', 'hackrepair-plugin-detective' ),
			'loginDisabled'     => __( 'Troubleshooter sign-in is turned off on this site. Log in to wp-admin first, then open Plugin Detective from the Tools menu.', 'hackrepair-plugin-detective' ),
			'goToLogin'         => __( 'Go to the WordPress login', 'hackrepair-plugin-detective' ),
			'codeTitle'         => __( 'Two-factor code', 'hackrepair-plugin-detective' ),
			'codeIntro'         => __( 'Your account has two-factor login turned on. Enter the 6-digit code from your authenticator app, or one of your recovery codes.', 'hackrepair-plugin-detective' ),
			'code'              => __( 'Code', 'hackrepair-plugin-detective' ),
			'verify'            => __( 'Verify', 'hackrepair-plugin-detective' ),
			'startOverLogin'    => __( 'Start over', 'hackrepair-plugin-detective' ),

			// Home.
			'homeTitle'         => __( 'Let\'s find what broke your site', 'hackrepair-plugin-detective' ),
			'homeIntro'         => __( 'When a site breaks after an update or a new plugin, the usual advice is to switch every plugin off and back on, one at a time. That works, but it takes forever. Plugin Detective does it in halves, so even a site with 40 plugins usually takes six or seven checks.', 'hackrepair-plugin-detective' ),
			'homeHow'           => __( 'How it works: you show me where the problem is, I switch some plugins off, and you tell me if the problem is still there. Repeat a few times and we\'ve got our answer. When we\'re done, everything goes back the way it was, unless you decide to keep the problem plugin off or delete it.', 'hackrepair-plugin-detective' ),
			/* translators: number of active plugins. */
			'homePluginCount'   => __( 'Active plugins on this site: %1$s', 'hackrepair-plugin-detective' ),
			'startCase'         => __( 'Start troubleshooting', 'hackrepair-plugin-detective' ),
			'noPlugins'         => __( 'There are no other active plugins on this site, so there\'s nothing to test. If something is broken, it\'s coming from the theme, WordPress itself, or the server.', 'hackrepair-plugin-detective' ),
			'openCaseTitle'     => __( 'You have a troubleshooting session in progress', 'hackrepair-plugin-detective' ),
			'openCaseText'      => __( 'Some plugins may be switched off right now. Pick up where you left off, or put everything back and start fresh.', 'hackrepair-plugin-detective' ),
			'continueCase'      => __( 'Pick up where I left off', 'hackrepair-plugin-detective' ),
			'abandonCase'       => __( 'Put my plugins back', 'hackrepair-plugin-detective' ),
			/* translators: short result summary. */
			'lastResult'        => __( 'Last result: %1$s', 'hackrepair-plugin-detective' ),

			// Maintenance.
			'maintTitle'        => __( 'Your site is stuck in maintenance mode', 'hackrepair-plugin-detective' ),
			'maintText'         => __( 'WordPress leaves a .maintenance file behind when an update gets interrupted. Visitors see "Briefly unavailable for scheduled maintenance" until it\'s removed. Removing it is safe; if an update really was half-finished, run it again from Dashboard > Updates afterward.', 'hackrepair-plugin-detective' ),
			'maintButton'       => __( 'Remove the maintenance file', 'hackrepair-plugin-detective' ),
			'maintDone'         => __( 'Done. The maintenance file is gone and your site is back on.', 'hackrepair-plugin-detective' ),
			'maintNone'         => __( 'Good news: your site isn\'t stuck in maintenance mode.', 'hackrepair-plugin-detective' ),

			// Step 1: where.
			'whereTitle'        => __( 'Step 1: Show me the problem', 'hackrepair-plugin-detective' ),
			'whereText'         => __( 'Go to the page where you see the problem. Click around inside the preview, or paste the address below. When the problem is on screen, click "I can see the problem."', 'hackrepair-plugin-detective' ),
			'pageAddress'       => __( 'Page address', 'hackrepair-plugin-detective' ),
			'go'                => __( 'Go', 'hackrepair-plugin-detective' ),
			'seeProblem'        => __( 'I can see the problem', 'hackrepair-plugin-detective' ),
			'cancel'            => __( 'Cancel', 'hackrepair-plugin-detective' ),

			// Step 2: must stay on.
			'requiredTitle'     => __( 'Step 2: Anything that has to stay on?', 'hackrepair-plugin-detective' ),
			'requiredText'      => __( 'Tick only the plugins your site truly can\'t run without, like the page builder this page was built with, or WooCommerce on a store. Ticked plugins stay on the whole time, which also means Plugin Detective can\'t check them. If you\'re not sure, leave everything unticked.', 'hackrepair-plugin-detective' ),
			'requiredSecurity'  => __( 'Heads up: these changes are live for everyone visiting your site, not just you. Unticked plugins, including security and spam protection, stay off until you finish or stop. Most sessions take a few minutes, so keep it short.', 'hackrepair-plugin-detective' ),
			'startTesting'      => __( 'Start testing', 'hackrepair-plugin-detective' ),
			'cancelSetup'       => __( 'Cancel', 'hackrepair-plugin-detective' ),

			// Step 3: testing.
			/* translators: round number. */
			'testTitle'         => __( 'Round %1$s: Is the problem still there?', 'hackrepair-plugin-detective' ),
			'testBaseline'      => __( 'I\'ve switched off every plugin except the ones you said must stay on. Look at the page. Is the problem still there?', 'hackrepair-plugin-detective' ),
			/* translators: 1: plugins switched on this round, 2: plugins still under suspicion. */
			'testSplit'         => __( 'I\'ve switched on %1$s of the %2$s plugins still under suspicion. Look at the page again.', 'hackrepair-plugin-detective' ),
			/* translators: plugin name. */
			'testConfirm'       => __( 'Down to one. I\'ve switched on just %1$s. One more look to be sure.', 'hackrepair-plugin-detective' ),
			'answerFixed'       => __( 'Problem\'s gone', 'hackrepair-plugin-detective' ),
			'answerBroken'      => __( 'Still broken', 'hackrepair-plugin-detective' ),
			'undo'              => __( 'Undo my last answer', 'hackrepair-plugin-detective' ),
			'stopTesting'       => __( 'Stop and put everything back', 'hackrepair-plugin-detective' ),
			/* translators: number of checks left. */
			'roundsLeft'        => __( 'About %1$s more checks to go.', 'hackrepair-plugin-detective' ),
			'roundsLeftOne'     => __( 'This should be the last check.', 'hackrepair-plugin-detective' ),
			'onNow'             => __( 'Switched on for this check', 'hackrepair-plugin-detective' ),
			'stayingOn'         => __( 'Staying on the whole time', 'hackrepair-plugin-detective' ),
			'noneOn'            => __( 'None', 'hackrepair-plugin-detective' ),
			'testTip'           => __( 'Tip: if the problem only shows when you\'re logged out, or on a phone, open the page in a new tab or a private window to check. Take your time; there\'s no timer.', 'hackrepair-plugin-detective' ),

			// Results.
			/* translators: plugin name. */
			'culpritTitle'      => __( 'Found it: %1$s', 'hackrepair-plugin-detective' ),
			/* translators: plugin name. */
			'culpritText'       => __( 'Your problem goes away when %1$s is off. I\'ve left it switched off and turned every other plugin back on. Check your site on the right to make sure everything looks right.', 'hackrepair-plugin-detective' ),
			'culpritNext'       => __( 'What next? Check for an update to that plugin, or contact its developer and tell them what broke. If it\'s a plugin you don\'t need, you can delete it.', 'hackrepair-plugin-detective' ),
			'keepOff'           => __( 'Keep it switched off', 'hackrepair-plugin-detective' ),
			'deleteIt'          => __( 'Delete this plugin', 'hackrepair-plugin-detective' ),
			'deleteAsk'         => __( 'Delete %s for good? Its files are removed from your site. Its saved settings stay in your database, so a reinstall picks up where it left off.', 'hackrepair-plugin-detective' ),
			'deleteYes'         => __( 'Yes, delete it', 'hackrepair-plugin-detective' ),
			'deleteNo'          => __( 'No, keep it', 'hackrepair-plugin-detective' ),
			'turnBackOn'        => __( 'Turn it back on', 'hackrepair-plugin-detective' ),
			'notPluginTitle'    => __( 'It\'s not one of your plugins', 'hackrepair-plugin-detective' ),
			'notPluginText'     => __( 'The problem was still there with the plugins switched off, so the cause is somewhere else. I\'ve put all your plugins back. The usual suspects:', 'hackrepair-plugin-detective' ),
			/* translators: theme name. */
			'suspectTheme'      => __( 'Your theme (%1$s). Try switching to a default WordPress theme for a moment.', 'hackrepair-plugin-detective' ),
			'suspectRequired'   => __( 'One of the plugins you said must stay on.', 'hackrepair-plugin-detective' ),
			/* translators: list of must-use plugins. */
			'suspectMu'         => __( 'Must-use plugins, which always run: %1$s', 'hackrepair-plugin-detective' ),
			/* translators: list of drop-in files. */
			'suspectDropins'    => __( 'Drop-ins (special files in wp-content): %1$s', 'hackrepair-plugin-detective' ),
			'suspectCache'      => __( 'Caching. Clear your cache plugin, your host\'s cache, and any CDN, then look again.', 'hackrepair-plugin-detective' ),
			'suspectHack'       => __( 'Something on the site that shouldn\'t be there. A hacked site can look a lot like a plugin conflict.', 'hackrepair-plugin-detective' ),
			'comboTitle'        => __( 'It takes more than one plugin to cause this', 'hackrepair-plugin-detective' ),
			'comboText'         => __( 'No single plugin breaks it on its own. Usually that means two plugins are fighting with each other. It can also mean the problem comes and goes, or an answer got mixed up. I\'ve put all your plugins back.', 'hackrepair-plugin-detective' ),
			'comboList'         => __( 'The problem showed up with this group switched on:', 'hackrepair-plugin-detective' ),
			'mixedTitle'        => __( 'The answers don\'t add up', 'hackrepair-plugin-detective' ),
			'mixedText'         => __( 'Every group I tested came back fixed, which can\'t all be true. The problem may come and go, or caching may be showing you an older copy of the page. I\'ve put all your plugins back. Clear your caches and try again.', 'hackrepair-plugin-detective' ),
			'finish'            => __( 'Finish', 'hackrepair-plugin-detective' ),
			'startAgain'        => __( 'Start a new session', 'hackrepair-plugin-detective' ),
			'doneTitle'         => __( 'All done', 'hackrepair-plugin-detective' ),
			'doneDeleted'       => __( 'The problem plugin is deleted. Everything else is back on.', 'hackrepair-plugin-detective' ),
			'doneKept'          => __( 'The problem plugin stays switched off. Everything else is back on.', 'hackrepair-plugin-detective' ),
			'doneRestored'      => __( 'Every plugin is back the way it was before we started.', 'hackrepair-plugin-detective' ),
			'doneCancelled'     => __( 'Cancelled. We hadn\'t switched anything off, so nothing was changed.', 'hackrepair-plugin-detective' ),
			'checkSite'         => __( 'Your site right now', 'hackrepair-plugin-detective' ),

			// Last result labels.
			/* translators: plugin name. */
			'resultCulprit'     => __( 'found %1$s', 'hackrepair-plugin-detective' ),
			'resultNotPlugin'   => __( 'not a plugin', 'hackrepair-plugin-detective' ),
			'resultCombination' => __( 'more than one plugin involved', 'hackrepair-plugin-detective' ),
			'resultMixed'       => __( 'answers didn\'t add up', 'hackrepair-plugin-detective' ),
			'resultAbandoned'   => __( 'stopped early, plugins put back', 'hackrepair-plugin-detective' ),

			// Background check.
			/* translators: plugin name. */
			'bgTitle'           => __( 'Background check: %1$s', 'hackrepair-plugin-detective' ),
			'bgLoading'         => __( 'Checking this plugin\'s background with wordpress.org and a security vulnerability database…', 'hackrepair-plugin-detective' ),
			'bgFailed'          => __( 'I couldn\'t run the background check from this server. You can look the plugin up on wordpress.org yourself.', 'hackrepair-plugin-detective' ),
			'bgKnownHoles'      => __( 'Known security holes in your version:', 'hackrepair-plugin-detective' ),
			'bgPluginPage'      => __( 'See it on wordpress.org', 'hackrepair-plugin-detective' ),
			'bgBadNews'         => __( 'A plugin with known security holes is worth taking seriously. If you think your site may already have been hit, The Hack Repair Guy can check it.', 'hackrepair-plugin-detective' ),

			// Case report.
			'reportTitle'       => __( 'Your case report', 'hackrepair-plugin-detective' ),
			'reportIntro'       => __( 'A plain-English summary of what we found, with the site details a developer will ask for. Send it to the plugin\'s developer, your web person, or The Hack Repair Guy.', 'hackrepair-plugin-detective' ),
			'reportCopy'        => __( 'Copy report', 'hackrepair-plugin-detective' ),
			'reportCopied'      => __( 'Copied', 'hackrepair-plugin-detective' ),
			'reportDownload'    => __( 'Download (.txt)', 'hackrepair-plugin-detective' ),
			'reportShow'        => __( 'Show the report', 'hackrepair-plugin-detective' ),
			'reportPrivacy'     => __( 'It includes your site address and plugin list, so only send it to people you trust.', 'hackrepair-plugin-detective' ),

			// Confirmations.
			'confirmAbandon'    => __( 'Put every plugin back the way it was and end this session?', 'hackrepair-plugin-detective' ),
			'confirmYes'        => __( 'Yes, put them back', 'hackrepair-plugin-detective' ),
			'confirmNo'         => __( 'Keep going', 'hackrepair-plugin-detective' ),
		);
	}
}
