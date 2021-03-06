<?php

class git_logger {

    private $assignment;

    private $_table = 'assignment_github_logs';

    function __construct($assignment) {
        $this->assignment = intval($assignment);
    }

    function get_by_commit($commit) {

        $conditions = array();
        $conditions['commit'] = $commit;
        return $this->get_records($conditions);
    }

    function get_by_group($id, $since = '', $until = '', $limitfrom = 0, $limitnum = 0) {

        return $this->get_by_group_user($id, 0, $since, $until, $limitfrom, $limitnum);
    }

    function get_by_user($id, $since = '', $until = '', $limitfrom = 0, $limitnum = 0) {

        $conditions = array();
        $conditions['userid'] = $id;
        $conditions['groupid'] = 0;
        return $this->get_records($conditions, $sort='date DESC', null, $limitfrom, $limitnum);
    }

    function get_by_group_user($groupid, $userid, $since = '', $until = '', $limitfrom = 0, $limitnum = 0) {

        $conditions = array();
        if ($userid) {
            $conditions['userid'] = $userid;
        }
        $conditions['groupid'] = $groupid;
        return $this->get_records($conditions, $sort='date DESC', null, $limitfrom, $limitnum);
    }

    function get_statistics_by_group($groupid) {
        global $DB, $CFG;

        $groupid = intval($groupid);
        if (!$groupid) {
            return null;
        }

        $sql = "SELECT
                  email, author, COUNT(*) AS commits, SUM(files) AS files,
                  SUM(insertions) AS insertions, SUM(deletions) AS deletions,
                  SUM(insertions)+SUM(deletions) AS total
                FROM `{$CFG->prefix}{$this->_table}`
                WHERE `assignment`={$this->assignment} AND `groupid`={$groupid}
                GROUP BY `email`
                UNION
                SELECT
                  'total' email, '' author, COUNT(*) AS commits, SUM(files) AS files,
                  SUM(insertions) AS insertions, SUM(deletions) AS deletions,
                  SUM(insertions)+SUM(deletions) AS total
                FROM `{$CFG->prefix}{$this->_table}`
                WHERE `assignment`={$this->assignment} AND `groupid`={$groupid}
                GROUP BY `assignment`";
        return $DB->get_records_sql($sql);
    }

    function get_statistics_by_user($userid) {
        global $DB, $CFG;

        $userid = intval($userid);
        if (!$userid) {
            return null;
        }

        $sql = "SELECT
                  userid, author, email, COUNT(*) AS commits, SUM(files) AS files,
                  SUM(insertions) AS insertions, SUM(deletions) AS deletions,
                  SUM(insertions)+SUM(deletions) AS total
                FROM `{$CFG->prefix}{$this->_table}`
                WHERE `assignment`={$this->assignment} AND `userid`={$userid}
                  AND `groupid`=0
                GROUP BY `userid`";
        return $DB->get_records_sql($sql);
    }

    function get_statistics_by_email($email) {
        global $DB, $CFG;

        if (!$email) {
            return null;
        }

        $sql = "SELECT
                  email, author, COUNT(*) AS commits, SUM(files) AS files,
                  SUM(insertions) AS insertions, SUM(deletions) AS deletions,
                  SUM(insertions)+SUM(deletions) AS total
                FROM `{$CFG->prefix}{$this->_table}`
                WHERE `assignment`={$this->assignment} AND `email`='{$email}'
                  AND `groupid`=0
                GROUP BY `email`";
        return $DB->get_records_sql($sql);
    }

    function get_statistics_by_group_email($groupid, $email) {
        global $DB, $CFG;

        $groupid = intval($groupid);
        if (!$groupid || !$email) {
            return null;
        }

        $sql = "SELECT
                  email, author, COUNT(*) AS commits, SUM(files) AS files,
                  SUM(insertions) AS insertions, SUM(deletions) AS deletions,
                  SUM(insertions)+SUM(deletions) AS total
                FROM `{$CFG->prefix}{$this->_table}`
                WHERE `assignment`={$this->assignment} AND `groupid`={$groupid}
                  AND `email`='{$email}'
                GROUP BY `email`";
        return $DB->get_records_sql($sql);
    }

    function get_user_last_commit($userid) {
        global $DB, $CFG;

        if (!$userid) {
            return null;
        }

        $sql = "SELECT *
                FROM `{$CFG->prefix}{$this->_table}`
                WHERE `assignment`= ? AND `userid`= ?
                ORDER BY `date` DESC LIMIT 0, 1";
        return $DB->get_record_sql($sql, array($this->assignment, $userid));
    }

    function list_all_latest_commits($groupmode) {
        global $DB, $CFG;

        $table = "{$CFG->prefix}{$this->_table}";
        if ($groupmode) {
            $key = 'groupid';
        } else {
            $key = 'userid';
        }

        $sql = "SELECT *
                  FROM (SELECT `{$key}`, MAX(`date`) AS `date`
                        FROM `{$table}`
                        WHERE `assignment`= ?
                        GROUP BY `{$key}`) AS `a`
             LEFT JOIN `{$table}` AS `b` USING(`{$key}`, `date`)";
        return $DB->get_records_sql($sql, array($this->assignment));
    }

    public function add_record($log) {
        global $DB;
        if ($log->assignment != $this->assignment) {
            return false;
        }
        return $DB->insert_record($this->_table, $log);
    }

    private function get_records($conditions, $sort='date DESC', $fields=array(), $limitfrom=0, $limitnum=0) {
        global $DB;

        if(!$fields) {
            $fields = '*';
        } else {
            $fields = implode($fileds, ',');
        }

        try {
            $logs = array();
            $result = $DB->get_records($this->_table, $conditions, $sort, $fields, $limitfrom, $limitnum);
            if ($result) {
                foreach($result as $k => $v) {
                    $logs[$v->commit] = $v;
                }
            }
            return $logs;
        } catch(Exception $e) {
            return false;
        }
    }

    function delete_by_group($id) {
        global $DB;

        $select = "assignment = ? AND groupid = ?";
        $params = array($this->assignment, $id);
        $DB->delete_records_select($this->_table, $select, $params);
    }

    function delete_by_user($id) {
        global $DB;

        $select = "assignment = ? AND userid = ?";
        $params = array($this->assignment, $id);
        $DB->delete_records_select($this->_table, $select, $params);
    }
}
