<?php
/**
 * Plugin Detective Fix page API.
 *
 * Runs outside the normal WordPress request (no plugins, no theme) so it
 * keeps working when a plugin has taken the site down.
 *
 * @package HackRepair_Plugin_Detective
 * @author  Jim Walker, The Hack Repair Guy
 */

define( 'HRPD_STANDALONE', true );

require __DIR__ . '/includes/bootstrap.php';

HRPD_App::handle_api();
