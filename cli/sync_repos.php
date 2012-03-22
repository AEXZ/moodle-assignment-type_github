<?php

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))).'/config.php');
require_once($CFG->dirroot.'/mod/assignment/type/github/assignment.class.php');

$CFG->debug = DEBUG_NORMAL;

$params = implode(' ', $argv);

// check params
preg_match('/--cm=(\d+)/', $params, $match);
if ($match) {
    $cmid = $match[1];
} else {
    echo 'Require course module ID. Usage: $ php script.php --cm=<id>' . PHP_EOL;
    die;
}

try {
    $task = new sync_git_repos($cmid);
    $task->sync();
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    die;
}

class sync_git_repos {

    private $_assignmentinstance;

    private $_groupmode;

    private $_git;

    private $_analyzer;

    private $_submissions;

    private $_members;

    public function __construct($cmid) {
        global $DB;

        if (! $cm = get_coursemodule_from_id('assignment', $cmid)) {
            throw new Exception(get_string('invalidcoursemodule', 'error'));
            return;
        }
        
        if (! $assignment = $DB->get_record("assignment", array("id"=>$cm->instance))) {
            throw new Exception(get_string('invalidid', 'assignment'));
            return;
        }
        
        if (! $course = $DB->get_record("course", array("id"=>$assignment->course))) {
            throw new Exception(get_string('coursemisconf', 'assignment'));
            return;
        }
        
        if ($assignment->assignmenttype != 'github') {
            throw new Exception(get_string('invalidtype', 'assignment'));
            return;
        }

        $this->_assignmentinstance = new assignment_github($cm->id, $assignment, $cm, $course);
        $this->_groupmode = groups_get_activity_groupmode($cm);
        $this->_git = new git($course->id, $assignment->id);
        $this->_analyzer = new git_analyzer();
    }

    public function sync() {

        $repos = $this->list_all_repos();
        foreach($repos as $id => $repo) {
            $work_tree = $this->generate_work_tree($id);
            $this->_analyzer->set_work_tree($work_tree);

            if ($this->_analyzer->has_work_tree()) {
                $this->_analyzer->pull();
            } else {

                // convert url to git:// first, in case user use other protocol
                $service =& $this->_git->get_api_service($repo->server);
                $git = $service->parse_git_url($repo->url);
                if ($git['type'] == 'git') {
                    $url = $repo->url;
                } else {
                    $url = $service->generate_git_url($git, 'git');
                }
                $this->_analyzer->pull($url);
            }

            $logs = $this->_analyzer->get_log();
            // todo: construct the table first, then fill logs in it
        }
    }

    private function generate_work_tree($id) {

        $prefix = "A{$this->_assignmentinstance->assignment->id}";
        if ($this->_groupmode) {
            $suffix = "G{$id}";
        } else {
            $suffix = "U{$id}";
        }
        return "{$prefix}-{$suffix}";
    }

    private function get_submissions() {

        $results = $this->_assignmentinstance->get_submissions();
        $submissions = array();
        foreach($results as $submission) {
            if (!empty($submission->userid)) {
                $submissions[$submission->userid] = $submission;
            }
        }
        return $submissions;
    }

    private function list_all_repos() {

        return $this->_assignmentinstance->list_all();
    }

    private function get_group_members($groupid) {

        $members = groups_get_members($groupid, 'u.*', 'lastname ASC');
        foreach($members as $id => $member) {
            $members[$id]->git_email = $this->get_user_email($id);
        }
    }

    private function get_user_email($userid) {

        if (empty($this->_submissions)) {
            $this->_submissions = $this->get_submissions();
        }

        if (empty($this->_submissions[$userid])) {
            return '';
        }

        return $this->_submissions[$userid]->data1;
    }

    private function store_logs() {
    }
}