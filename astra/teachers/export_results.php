<?php
/** Page that lets the user export course results (points) to a JSON file.
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // defines MOODLE_INTERNAL for libraries
require_once($CFG->libdir .'/filelib.php');

$cid = required_param('course', PARAM_INT); // Course ID
$course = get_course($cid);

require_login($course, false);
$context = context_course::instance($cid);
require_capability('mod/astra:addinstance', $context); // editing teacher

$title = get_string('exportresults', mod_astra_exercise_round::MODNAME);

$PAGE->set_pagelayout('incourse');
$PAGE->set_url(\mod_astra\urls\urls::exportResults($cid, true));
$PAGE->set_title(format_string($title));
$PAGE->set_heading(format_string($course->fullname));

// navbar
$courseNav = $PAGE->navigation->find($cid, navigation_node::TYPE_COURSE);
$exportNav = $courseNav->add($title,
        \mod_astra\urls\urls::exportResults($cid, true),
        navigation_node::TYPE_CUSTOM, null, 'exportresults');
$exportNav->make_active();


// output starts
$form = new \mod_astra\form\export_results_form($cid,
        'exportresults', 'export_results.php?course='. $cid);
if ($form->is_cancelled()) {
    // Handle form cancel operation, if cancel button is present on form
    redirect(new moodle_url('/course/view.php', array('id' => $cid)));
    exit(0);
}


if ($fromform = $form->get_data()) {
    // form submitted, prepare parameters for the export function
    $exerciseIds = \mod_astra\form\export_results_form::parse_exercises($fromform);
    
    if (empty($fromform->selectstudents)) {
        $studentUserIds = null;
    } else {
        $studentUserIds = $fromform->selectstudents;
    }
    
    if (isset($fromform->submittedbefore)) {
        $submittedBefore = $fromform->submittedbefore;
    } else {
        $submittedBefore = 0;
    }
    
    $export = new \mod_astra\export\export_data($cid, $exerciseIds, $studentUserIds,
            $submittedBefore, $fromform->selectsubmissions);
    $json = $export->export_results();
    $json_str = json_encode($json);
    if ($json_str == false) {
        // JSON encoding error, probably a bug
        throw new coding_exception('JSON encoding error: '. \mod_astra\export\export_data::json_last_error_msg());
    } else {
        // force the user to download the file
        $date_now = date('d-m-Y\TH-i-s');
        $filename = "export_results_$date_now.json";
        send_temp_file($json_str, $filename, true);
    }
    
} else {
    // this branch is executed if the form is submitted but the data doesn't validate
    // and the form should be redisplayed, or on the first display of the form.
    
    $output = $PAGE->get_renderer(mod_astra_exercise_round::MODNAME);
    echo $output->header();
    echo $output->heading($title);
    echo '<p>'. get_string('exportpassedlist', mod_astra_exercise_round::MODNAME,
            \mod_astra\urls\urls::exportPassedList($cid)) .
    '</p>';
    echo '<hr>';
    echo '<p>'. get_string('exportdescription', mod_astra_exercise_round::MODNAME) .'</p>';
    
    $form->display();
    
    echo $output->footer();
}
