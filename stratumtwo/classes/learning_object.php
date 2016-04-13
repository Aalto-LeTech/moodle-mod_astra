<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Base class for learning objects (exercises and chapters).
 * Each learning object belongs to one exercise round
 * and one category. A learning object has a service URL that is used to connect to
 * the exercise service.
 */
abstract class mod_stratumtwo_learning_object extends mod_stratumtwo_database_object {
    const TABLE = 'stratumtwo_lobjects'; // database table name
    const STATUS_READY       = 0;
    const STATUS_HIDDEN      = 1;
    const STATUS_MAINTENANCE = 2;
    const STATUS_UNLISTED    = 3;
    
    // SQL fragment for joining learning object base class table with a subtype table
    // self::TABLE constant cannot be used in this definition since old PHP versions
    // only support literal constants
    // Usage of this constant: sprintf(mod_stratumtwo_learning_object::SQL_SUBTYPE_JOIN, fields, SUBTYPE_TABLE)
    const SQL_SUBTYPE_JOIN = 'SELECT %s FROM {stratumtwo_lobjects} lob INNER JOIN {%s} ex ON lob.id = ex.lobjectid';
    // SQL fragment for selecting all fields in the subtype join query: this avoids the conflict of
    // id columns in both the base table and the subtype table. Id is taken from the subtype and
    // the subtype table should have a column lobjectid which is the id in the base table.
    const SQL_SELECT_ALL_FIELDS = 'ex.*,lob.status,lob.categoryid,lob.roundid,lob.parentid,lob.ordernum,lob.remotekey,lob.name,lob.serviceurl';
    
    // cache of references to other records, used in corresponding getter methods
    protected $category = null;
    protected $exerciseRound = null;
    protected $parentObject = null;
    
    public static function getSubtypeJoinSQL($subtype = mod_stratumtwo_exercise::TABLE, $fields = self::SQL_SELECT_ALL_FIELDS) {
        return sprintf(self::SQL_SUBTYPE_JOIN, $fields, $subtype);
    }
    
    /**
     * Create object of the corresponding class from an existing database ID.
     * @param int $id learning object ID (base table)
     * @return mod_stratumtwo_exercise|mod_stratumtwo_chapter
     */
    public static function createFromId($id) {
        global $DB;
        
        $where = ' WHERE lob.id = ?';
        $sql = self::getSubtypeJoinSQL(mod_stratumtwo_exercise::TABLE) . $where;
        $row = $DB->get_record_sql($sql, array($id), IGNORE_MISSING);
        if ($row !== false) {
            // this learning object is an exercise
            return new mod_stratumtwo_exercise($row);
        } else {
            // no exercise found, this learning object should be a chapter
            $sql = self::getSubtypeJoinSQL(mod_stratumtwo_chapter::TABLE) . $where;
            $row = $DB->get_record_sql($sql, array($id), MUST_EXIST);
            return new mod_stratumtwo_chapter($row);
        }
    }
    
    /**
     * Return the ID of this learning object (ID in the base table).
     * @see mod_stratumtwo_database_object::getId()
     */
    public function getId() {
        // assume that id field is from the subtype, see constant SQL_SELECT_ALL_FIELDS
        return $this->record->lobjectid; // id in the learning object base table
    }
    
    /**
     * Return ID of this learning object in its subtype table
     * (different to the ID in the base table).
     */
    public function getSubtypeId() {
        // assume that id field is from the subtype, see constant SQL_SELECT_ALL_FIELDS
        return $this->record->id; // id in the subtype (exercises/chapters) table
    }
    
    public function save() {
        global $DB;
        // Must save to both base table and subtype table.
        // subtype: $this->record->id should be the ID in the subtype table
        $DB->update_record(static::TABLE, $this->record);
        
        // must change the id value in the record for base table
        $record = clone $this->record;
        $record->id = $this->getId();
        return $DB->update_record(self::TABLE, $record);
    }
    
    public function getStatus($asString = false) {
        if ($asString) {
            switch ((int) $this->record->status) {
                case self::STATUS_READY:
                    return get_string('statusready', mod_stratumtwo_exercise_round::MODNAME);
                    break;
                case self::STATUS_MAINTENANCE:
                    return get_string('statusmaintenance', mod_stratumtwo_exercise_round::MODNAME);
                    break;
                case self::STATUS_UNLISTED:
                    return get_string('statusunlisted', mod_stratumtwo_exercise_round::MODNAME);
                    break;
                default:
                    return get_string('statushidden', mod_stratumtwo_exercise_round::MODNAME);
            }
        }
        return (int) $this->record->status;
    }
    
    public function getCategory() {
        if (is_null($this->category)) {
            $this->category = mod_stratumtwo_category::createFromId($this->record->categoryid);
        }
        return $this->category;
    }
    
    public function getCategoryId() {
        return $this->record->categoryid;
    }
    
    public function getExerciseRound() {
        if (is_null($this->exerciseRound)) {
            $this->exerciseRound = mod_stratumtwo_exercise_round::createFromId($this->record->roundid);
        }
        return $this->exerciseRound;
    }
    
    public function getParentObject() {
        if (empty($this->record->parentid)) {
            return null;
        }
        if (is_null($this->parentObject)) {
            $this->parentObject = self::createFromId($this->record->parentid);
        }
        return $this->parentObject;
    }
    
    public function getParentId() {
        if (empty($this->record->parentid)) {
            return null;
        }
        return (int) $this->record->parentid;
    }
    
    /**
     * Return an array of the learning objects that are direct children of
     * this learning object.
     * @param bool $includeHidden if true, hidden learning objects are included
     * @return mod_stratumtwo_learning_object[]
     */
    public function getChildren($includeHidden = false) {
        global $DB;
        
        $where = ' WHERE lob.parentid = ?';
        $orderBy = ' ORDER BY ordernum ASC';
        $params = array($this->getId());
        
        if ($includeHidden) {
            $where .= $orderBy;
        } else {
            $where .= ' AND lob.status != ?' . $orderBy;
            $params[] = self::STATUS_HIDDEN;
        }
        $ex_sql = self::getSubtypeJoinSQL(mod_stratumtwo_exercise::TABLE) . $where;
        $ch_sql = self::getSubtypeJoinSQL(mod_stratumtwo_chapter::TABLE) . $where;
        $exerciseRecords = $DB->get_records_sql($ex_sql, $params);
        $chapterRecords = $DB->get_records_sql($ch_sql, $params);
        
        // gather learning objects into one array
        $learningObjects = array();
        foreach ($exerciseRecords as $ex) {
            $learningObjects[] = new mod_stratumtwo_exercise($ex);
        }
        foreach ($chapterRecords as $ch) {
            $learningObjects[] = new mod_stratumtwo_chapter($ch);
        }
        // sort the combined array, compare ordernums since all objects have the same parent
        usort($learningObjects, function($obj1, $obj2) {
            $ord1 = $obj1->getOrder();
            $ord2 = $obj2->getOrder();
            if ($ord1 < $ord2) {
                return -1;
            } else if ($ord1 == $ord2) {
                return 0;
            } else {
                return 1;
            }
        });
        
        return $learningObjects;
    }
    
    public function getOrder() {
        return (int) $this->record->ordernum;
    }
    
    public function getRemoteKey() {
        return $this->record->remotekey;
    }
    
    public function getNumber() {
        $parent = $this->getParentObject();
        if ($parent !== null) {
            return $parent->getNumber() . ".{$this->record->ordernum}";
        }
        return ".{$this->record->ordernum}";
    }
    
    public function getName($includeOrder = true) {
        require_once(dirname(dirname(__FILE__)) .'/locallib.php');
        // number formatting based on A+ (a-plus/exercise/exercise_models.py)
        
        if ($includeOrder && $this->getOrder() >= 0) {
            $conf = mod_stratumtwo_course_config::getForCourseId($this->getExerciseRound()->getCourse()->courseid);
            if ($conf !== null) {
                $contentNumbering = $conf->getContentNumbering();
                $moduleNumbering = $conf->getModuleNumbering();
            } else {
                $contentNumbering = mod_stratumtwo_course_config::getDefaultContentNumbering();
                $moduleNumbering = mod_stratumtwo_course_config::getDefaultModuleNumbering();
            }
            
            if ($contentNumbering == mod_stratumtwo_course_config::CONTENT_NUMBERING_ARABIC) {
                $number = $this->getNumber();
                if ($moduleNumbering == mod_stratumtwo_course_config::MODULE_NUMBERING_ARABIC ||
                        $moduleNumbering == mod_stratumtwo_course_config::MODULE_NUMBERING_HIDDEN_ARABIC) {
                    return $this->getExerciseRound()->getOrder() . "$number {$this->record->name}";
                }
                // leave out the module number ($number starts with a dot)
                return substr($number, 1) .' '. $this->record->name;
            } else if ($contentNumbering == mod_stratumtwo_course_config::CONTENT_NUMBERING_ROMAN) {
                return stratumtwo_roman_numeral($this->getOrder()) .' '. $this->record->name;
            }
        }
        return $this->record->name;
    }
    
    public function getServiceUrl() {
        return $this->record->serviceurl;
    }
    
    public function isEmpty() {
        return empty($this->record->serviceurl);
    }
    
    public function isHidden() {
        return $this->getStatus() === self::STATUS_HIDDEN;
    }
    
    public function isUnlisted() {
        return $this->getStatus() === self::STATUS_UNLISTED;
    }
    
    public function isUnderMaintenance() {
        return $this->getStatus() === self::STATUS_MAINTENANCE;
    }
    
    public function setStatus($status) {
        $this->record->status = $status;
    }
    
    public function setOrder($newOrder) {
        $this->record->ordernum = $newOrder;
    }
   
    public function isSubmittable() {
        return false;
    }
    
    /**
     * Delete this learning object from the database. Possible child learning
     * objects are also deleted.
     */
    public function deleteInstance() {
        global $DB;
        
        foreach ($this->getChildren(true) as $child) {
            $child->deleteInstance();
        }
        
        // delete this object, subtable and base table
        $DB->delete_records(static::TABLE, array('id' => $this->getSubtypeId()));
        return $DB->delete_records(self::TABLE, array('id' => $this->getId()));
    }
    
    public function getTemplateContext($includeCourseModule = true) {
        $ctx = new stdClass();
        $ctx->url = \mod_stratumtwo\urls\urls::exercise($this);
        $parent = $this->getParentObject();
        if ($parent === null) {
            $ctx->parenturl = null;
        } else {
            $ctx->parenturl = \mod_stratumtwo\urls\urls::exercise($parent);
        }
        $ctx->name = $this->getName();
        $ctx->editurl = \mod_stratumtwo\urls\urls::editExercise($this);
        $ctx->removeurl = \mod_stratumtwo\urls\urls::deleteExercise($this);
        
        if ($includeCourseModule) {
            $ctx->course_module = $this->getExerciseRound()->getTemplateContext();
        }
        $ctx->status_ready = ($this->getStatus() === self::STATUS_READY);
        $ctx->status_str = $this->getStatus(true);
        $ctx->status_unlisted = ($this->getStatus() === self::STATUS_UNLISTED);
        $ctx->is_submittable = $this->isSubmittable();
        
        return $ctx;
    }
    
    public function getLoadUrl($userid) {
        // this method can be overridden in child classes to change the URL in loadPage method
        return $this->getServiceUrl();
    }
    
    /**
     * Load the exercise page from the exercise service.
     * @param int $userid user ID
     * @throws mod_stratumtwo\protocol\remote_page_exception if there are errors
     * in connecting to the server
     * @return stdClass with field content
     */
    public function loadPage($userid) {
        $serviceUrl = $this->getLoadUrl($userid);
        try {
            $remotePage = new \mod_stratumtwo\protocol\remote_page($serviceUrl);
            return $remotePage->loadExercisePage($this);
        } catch (\mod_stratumtwo\protocol\stratum_connection_exception $e) {
            // error logging
            $event = \mod_stratumtwo\event\stratum_connection_failed::create(array(
                    'context' => context_module::instance($this->getExerciseRound()->getCourseModule()->id),
                    'other' => array(
                            'error' => $e->getMessage(),
                            'url' => $serviceUrl,
                            'objtable' => static::TABLE,
                            'objid' => $this->getSubtypeId(),
                    )
            ));
            $event->trigger();
            throw $e;
        } catch (\mod_stratumtwo\protocol\stratum_server_exception $e) {
            $event = \mod_stratumtwo\event\stratum_server_failed::create(array(
                    'context' => context_module::instance($this->getExerciseRound()->getCourseModule()->id),
                    'other' => array(
                            'error' => $e->getMessage(),
                            'url' => $serviceUrl,
                            'objtable' => static::TABLE,
                            'objid' => $this->getSubtypeId(),
                    )
            ));
            $event->trigger();
            throw $e;
        }
    }
    
}