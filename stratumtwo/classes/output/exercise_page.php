<?php
namespace mod_stratumtwo\output;

defined('MOODLE_INTERNAL') || die;

class exercise_page implements \renderable, \templatable {
    
    protected $exround;
    protected $exercise;
    protected $exerciseSummary;
    protected $user;
    protected $errorMsg;
    
    public function __construct(\mod_stratumtwo_exercise_round $exround,
            \mod_stratumtwo_exercise $exercise,
            \stdClass $user, $errorMsg = null) {
        $this->exround = $exround;
        $this->exercise = $exercise;
        $this->user = $user;
        $this->exerciseSummary = new \mod_stratumtwo\summary\user_exercise_summary($exercise, $user);
        $this->errorMsg = $errorMsg;
    }
    
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @return stdClass
     */
    public function export_for_template(\renderer_base $output) {
        $data = new \stdClass();
        $ctx = \context_module::instance($this->exround->getCourseModule()->id);
        $data->is_course_staff = \has_capability('mod/stratumtwo:viewallsubmissions', $ctx);
        $data->is_editing_teacher = \has_capability('mod/stratumtwo:addinstance', $ctx);
        $data->is_manual_grader = \has_capability('mod/stratumtwo:grademanually', $ctx);
        
        $data->status_maintenance = ($this->exround->isUnderMaintenance() || $this->exercise->isUnderMaintenance());
        $data->not_started = !$this->exround->hasStarted();

        if (!($data->status_maintenance || $data->not_started) || $data->is_course_staff) {
            try {
                $data->page = $this->exercise->loadPage($this->user->id);
            } catch (\mod_stratumtwo\protocol\remote_page_exception $e) {
                $data->error = \get_string('serviceconnectionfailed', \mod_stratumtwo_exercise_round::MODNAME);
                $page = new \stdClass();
                $page->content = '';
                $data->page = $page;
            }
        }
        
        if (!is_null($this->errorMsg)) {
            if (isset($data->error)) {
                $data->error .= '<br>'. $this->errorMsg;
            } else {
                $data->error = $this->errorMsg;
            }
        }
        
        $data->exercise = $this->exercise->getTemplateContext();
        $data->submissions = $this->exercise->getSubmissionsTemplateContext($this->user->id);
        $data->submission = false;
        
        $data->summary = $this->exerciseSummary->getTemplateContext();
        
        $data->toDateStr = new \mod_stratumtwo\output\date_to_string();
        
        return $data;
        // It should return an stdClass with properties that are only made of simple types:
        // int, string, bool, float, stdClass or arrays of these types
    }
}