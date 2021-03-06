<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Exercise category in a course. Each exercise (learning object) belongs to one category
 * and the category counts the total points in the category. A category can have
 * required points to pass that the student should earn in total from the
 * exercises in the category. Exercises in a category can be scattered across
 * multiple exercise rounds.
 * 
 * Each instance of this class should correspond to one record in the categories
 * database table.
 */
class mod_astra_category extends mod_astra_database_object {
    const TABLE = 'astra_categories'; // database table name
    const STATUS_READY  = 0;
    const STATUS_HIDDEN = 1;
    const STATUS_NOTOTAL = 2;
    
    public function getCourse() {
        // return course_modinfo object
        return get_fast_modinfo($this->record->course);
    }
    
    public function getStatus($asString = false) {
        if ($asString) {
            switch ((int) $this->record->status) {
                case self::STATUS_READY:
                    return get_string('statusready', mod_astra_exercise_round::MODNAME);
                    break;
                case self::STATUS_NOTOTAL:
                    return get_string('statusnototal', mod_astra_exercise_round::MODNAME);
                    break;
                //case self::STATUS_HIDDEN:
                default:
                    return get_string('statushidden', mod_astra_exercise_round::MODNAME);
            }
        }
        return (int) $this->record->status;
    }
    
    public function getName(string $lang = null) {
        require_once(dirname(dirname(__FILE__)) .'/locallib.php');
        
        return astra_parse_localization($this->record->name, $lang);
    }
    
    public function getPointsToPass() {
        return $this->record->pointstopass;
    }
    
    public function isHidden() {
        return $this->getStatus() === self::STATUS_HIDDEN;
    }
    
    public function setHidden() {
        $this->record->status = self::STATUS_HIDDEN;
    }
    
    private function getLearningObjects_sql($subtypeTable, $includeHidden = false, $fields = null) {
        if ($fields === null) {
            // use default fields (all)
            $sql = mod_astra_learning_object::getSubtypeJoinSQL($subtypeTable) . ' WHERE lob.categoryid = ?';
        } else {
            $sql = mod_astra_learning_object::getSubtypeJoinSQL($subtypeTable, $fields) . ' WHERE lob.categoryid = ?';
        }
        $params = array($this->getId());
        
        if (!$includeHidden) {
            $sql .= ' AND status != ?';
            $params[] = mod_astra_learning_object::STATUS_HIDDEN;
        }
        
        return array($sql, $params);
    }
    
    /**
     * Return all learning objects in this category.
     * @param bool $includeHidden if true, hidden learning objects are included
     * @return mod_astra_learning_object[], indexed by learning object IDs
     */
    public function getLearningObjects($includeHidden = false) {
        global $DB;
        
        list($chapters_sql, $ch_params) = $this->getLearningObjects_sql(mod_astra_chapter::TABLE, $includeHidden);
        $chapterRecords = $DB->get_records_sql($chapters_sql, $ch_params);
        
        $learningObjects = $this->getExercises($includeHidden);
        
        foreach ($chapterRecords as $rec) {
            $chapter = new mod_astra_chapter($rec);
            $learningObjects[$chapter->getId()] = $chapter;
        }
        
        return $learningObjects;
    }
    
    /**
     * Return all exercises in this category.
     * @param bool $includeHidden if true, hidden exercises are included
     * @return mod_astra_exercise[], indexed by exercise/learning object IDs
     */
    public function getExercises($includeHidden = false) {
        global $DB;
        
        list($sql, $params) = $this->getLearningObjects_sql(mod_astra_exercise::TABLE, $includeHidden);
        
        $exerciseRecords = $DB->get_records_sql($sql, $params);
        
        $exercises = array();
        
        foreach ($exerciseRecords as $rec) {
            $ex = new mod_astra_exercise($rec);
            $exercises[$ex->getId()] = $ex;
        }
        
        return $exercises;
    }
    
    /**
     * Return the count of exercises in this category.
     * @return int
     */
    public function countExercises($includeHidden = false) {
        global $DB;
        
        list($sql, $params) = $this->getLearningObjects_sql(mod_astra_exercise::TABLE,
                $includeHidden, 'COUNT(lob.id)');
        
        return $DB->count_records_sql($sql, $params);
    }
    
    /**
     * Return the count of learning objects in this category.
     * @return int
     */
    public function countLearningObjects($includeHidden = false) {
        global $DB;
    
        list($ch_sql, $ch_params) = $this->getLearningObjects_sql(mod_astra_chapter::TABLE,
                $includeHidden, 'COUNT(lob.id)');

        return $this->countExercises($includeHidden) + $DB->count_records_sql($ch_sql, $ch_params);
    }
    
    /**
     * Return all categories in a course.
     * @param int $courseid
     * @param bool $includeHidden if true, hidden categories are included
     * @return array of mod_astra_category objects, indexed by category IDs
     */
    public static function getCategoriesInCourse($courseid, $includeHidden = false) {
        global $DB;
        if ($includeHidden) {
            $records = $DB->get_records(self::TABLE, array('course' => $courseid));
        } else {
            $sql = 'SELECT * FROM {'. self::TABLE .'} WHERE course = ? AND status != ?';
            $params = array($courseid, self::STATUS_HIDDEN);
            $records = $DB->get_records_sql($sql, $params);
        }
        
        $categories = array();
        foreach ($records as $id => $record) {
            $categories[$id] = new self($record);
        }
        return $categories;
    }
    
    /**
     * Create a new category in the database.
     * @param stdClass $categoryRecord object with the fields required by the database table,
     * excluding id
     * @return int ID of the new database record, zero on failure
     */
    public static function createNew(stdClass $categoryRecord) {
        global $DB;
        return $DB->insert_record(self::TABLE, $categoryRecord);
    }
    
    /**
     * Update an existing category record or create a new one if it does not
     * yet exist (based on course and the name).
     * @param stdClass $newRecord must have at least course and name fields as
     * they are used to look up the record. Course and name are not modified in
     * an existing record.
     * @return int ID of the new/modified record
     */
    public static function updateOrCreate(stdClass $newRecord) {
        global $DB;
        
        $catRecord = $DB->get_record(self::TABLE, array(
                'course' => $newRecord->course,
                'name' => $newRecord->name,
        ), '*', IGNORE_MISSING);
        if ($catRecord === false) {
            // create new
            return $DB->insert_record(self::TABLE, $newRecord);
        } else {
            // update
            if (isset($newRecord->status))
                $catRecord->status = $newRecord->status;
            if (isset($newRecord->pointstopass))
                $catRecord->pointstopass = $newRecord->pointstopass;
            $DB->update_record(self::TABLE, $catRecord);
            return $catRecord->id;
        }
    }
    
    public function delete() {
        global $DB;
        
        // delete learning objects in this category
        foreach ($this->getLearningObjects(true) as $lobject) {
            $lobject->deleteInstance();
        }
        
        return $DB->delete_records(self::TABLE, array('id' => $this->getId()));
    }
    
    public function getTemplateContext($include_lobject_count = true) {
        $ctx = new stdClass();
        $ctx->name = $this->getName();
        $ctx->editurl = \mod_astra\urls\urls::editCategory($this);
        if ($include_lobject_count) {
            //$ctx->has_exercises = ($this->countExercises() > 0); // unneeded
            $ctx->has_learning_objects = ($this->countLearningObjects() > 0);
        }
        $ctx->removeurl = \mod_astra\urls\urls::deleteCategory($this);
        $ctx->status_ready = ($this->getStatus() === self::STATUS_READY);
        $ctx->status_str = $this->getStatus(true);
        $ctx->status_hidden = ($this->getStatus() === self::STATUS_HIDDEN);
        $ctx->status_nototal = ($this->getStatus() === self::STATUS_NOTOTAL);
        return $ctx;
    }
}