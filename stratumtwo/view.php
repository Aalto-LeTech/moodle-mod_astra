<?php

/**
 * Prints a particular instance of stratumtwo (exercise round).
 *
 * @package    mod_stratumtwo
 * @copyright  2016 Aalto SCI CS dept.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$n  = optional_param('s', 0, PARAM_INT);  // ... stratum instance ID

if ($id) {
    list($course, $cm) = get_course_and_cm_from_cmid($id, mod_stratumtwo_exercise_round::TABLE);
    $stratumtwo        = $DB->get_record(mod_stratumtwo_exercise_round::TABLE, array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $stratumtwo        = $DB->get_record(mod_stratumtwo_exercise_round::TABLE, array('id' => $n), '*', MUST_EXIST);
    list($course, $cm) = get_course_and_cm_from_instance($stratumtwo->id, mod_stratumtwo_exercise_round::TABLE);
} else {
    print_error('missingparam', '', '', 'id');
}

require_login($course, false, $cm);
$context = context_module::instance($cm->id);

$exround = new mod_stratumtwo_exercise_round($stratumtwo);

// this should prevent guest access
require_capability('mod/stratumtwo:view', $context);
if ((!$cm->visible || $exround->isHidden()) &&
        !has_capability('moodle/course:manageactivities', $context)) {
    // show hidden activity (exercise round page) only to teachers
    throw new required_capability_exception($context,
            'moodle/course:manageactivities', 'nopermissions', '');
}

// Event for logging (viewing the page)
$event = \mod_stratumtwo\event\course_module_viewed::create(array(
        'objectid' => $PAGE->cm->instance,
        'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $stratumtwo);
$event->trigger();

//TODO require Bootstrap CSS and jQuery
//$PAGE->requires->js(new moodle_url('https://code.jquery.com/jquery-1.12.0.js')); // Moodle has 1.11.3 bundled
$PAGE->requires->css(new moodle_url('/mod/'. mod_stratumtwo_exercise_round::TABLE .'/assets/bootstrap/css/bootstrap.min.css'));
$PAGE->requires->js(new moodle_url('/mod/'. mod_stratumtwo_exercise_round::TABLE .'/assets/bootstrap/js/bootstrap.min.js'));
$PAGE->requires->css(new moodle_url('/mod/'. mod_stratumtwo_exercise_round::TABLE .'/assets/css/main.css'));
// highlight.js for source code syntax highlighting
//$PAGE->requires->css(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/8.7/styles/github.min.css'));
//$PAGE->requires->js(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/8.6/highlight.min.js'));

$PAGE->set_url('/mod/'. mod_stratumtwo_exercise_round::TABLE .'/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($stratumtwo->name));
$PAGE->set_heading(format_string($course->fullname));

// render page content
$output = $PAGE->get_renderer(mod_stratumtwo_exercise_round::TABLE);

// Print the page header (Moodle navbar etc.).
echo $output->header();

$renderable = new \mod_stratumtwo\output\exercise_round_page($exround, $USER);
echo $output->render($renderable);

echo $output->footer();