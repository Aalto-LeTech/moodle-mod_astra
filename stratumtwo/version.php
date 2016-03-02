<?php

/**
 * Defines the version and other meta-info about the plugin
 *
 * Setting the $plugin->version to 0 prevents the plugin from being installed.
 * See https://docs.moodle.org/dev/version.php for more info.
 *
 * @package    mod_stratumtwo
 * @copyright  2016 Aalto SCI CS dept.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_stratumtwo';
$plugin->version = 2016021900;
$plugin->release = 'v0.1';
$plugin->requires =  2015111600; // Moodle 3.0
$plugin->maturity = MATURITY_ALPHA;
//$plugin->cron = 0; // legacy cron API 
$plugin->dependencies = array();