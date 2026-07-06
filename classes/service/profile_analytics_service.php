<?php
// This file is part of Moodle - http://moodle.org/

namespace local_profileanalytics\service;

use block_dashboardanalytics\repository\company_repository;
use block_dashboardanalytics\repository\completion_repository;
use block_dashboardanalytics\repository\course_analytics_repository;
use block_dashboardanalytics\repository\overview_repository;

defined('MOODLE_INTERNAL') || die();

class profile_analytics_service {
    public function build(int $userid): array {
        global $DB;

        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $filters = [
            'userids' => [$userid],
            'daterange' => 'last12months',
        ];

        $overview = new overview_repository();
        $completion = new completion_repository();
        $companyrepo = new company_repository();
        $analytics = new course_analytics_repository();

        $summary = $overview->overall_employee_compliance_summary($filters);
        $statuscounts = $overview->status_counts($filters);
        $rows = $overview->enrolment_status_snapshot_rows($filters);
        $completedcourses = $completion->count_completed_courses($filters);
        $companyline = $this->company_line($companyrepo, $userid, $user);
        $riskstatus = $this->status_from_percent((float)$summary['percent']);

        $recentcompletion = $DB->get_record_sql(
            "SELECT c.fullname, cc.timecompleted
               FROM {course_completions} cc
               JOIN {course} c ON c.id = cc.course
               {$analytics->eligibility_join_sql('c', 'cfprofilecompletion', 'cdprofilecompletion')}
              WHERE cc.userid = :userid
                AND cc.timecompleted IS NOT NULL
                AND " . $analytics->eligibility_where_sql('c', 'cfprofilecompletion', 'cdprofilecompletion') . "
           ORDER BY cc.timecompleted DESC",
            ['userid' => $userid],
            IGNORE_MULTIPLE
        );

        $lastaccess = (int)$DB->get_field_sql(
            "SELECT MAX(timeaccess)
               FROM {user_lastaccess}
              WHERE userid = :userid",
            ['userid' => $userid]
        );

        $courses = [];
        foreach ($rows as $row) {
            $courses[] = [
                'course' => $this->decoded((string)$row['course']),
                'status' => (string)$row['status'],
                'expiry' => !empty($row['expirytime']) ? userdate((int)$row['expirytime'], get_string('strftimedate')) : get_string('value:notavailable', 'local_profileanalytics'),
                'daysremaining' => $this->days_remaining_label((int)($row['expirytime'] ?? 0)),
                'sortexpiry' => (int)($row['expirytime'] ?? 0),
            ];
        }

        usort($courses, static function(array $a, array $b): int {
            $aempty = $a['sortexpiry'] <= 0 ? 1 : 0;
            $bempty = $b['sortexpiry'] <= 0 ? 1 : 0;
            if ($aempty !== $bempty) {
                return $aempty <=> $bempty;
            }
            return $a['sortexpiry'] <=> $b['sortexpiry'];
        });

        $actions = [];
        $upcoming = [];
        foreach ($courses as $course) {
            if ($course['status'] !== 'Active') {
                $actions[] = [
                    'course' => $course['course'],
                    'status' => $course['status'],
                    'statusclass' => $this->status_class_for_label($course['status']),
                    'meta' => $course['expiry'] . ' · ' . $course['daysremaining'],
                ];
            }
            if ($course['status'] === 'Expiring' || $course['status'] === 'Expired') {
                $upcoming[] = [
                    'course' => $course['course'],
                    'date' => $course['expiry'],
                    'meta' => $course['daysremaining'],
                ];
            }
        }

        return [
            'fullname' => fullname($user),
            'companyline' => $companyline,
            'risklabel' => $this->risk_label((float)$summary['percent'], count($rows)),
            'riskstatus' => $riskstatus,
            'cards' => [
                [
                    'label' => get_string('card:compliance', 'local_profileanalytics'),
                    'value' => count($rows) > 0 ? round((float)$summary['percent'], 1) . '%' : get_string('value:notavailable', 'local_profileanalytics'),
                    'meta' => get_string('meta:validcourses', 'local_profileanalytics', (object)[
                        'valid' => (int)$statuscounts['active'] + (int)$statuscounts['expiring'],
                        'total' => max(0, array_sum($statuscounts)),
                    ]),
                    'status' => $riskstatus,
                ],
                [
                    'label' => get_string('card:completedcourses', 'local_profileanalytics'),
                    'value' => (string)$completedcourses,
                    'meta' => get_string('meta:completedcourses', 'local_profileanalytics'),
                    'status' => 'info',
                ],
                [
                    'label' => get_string('card:certificatesavailable', 'local_profileanalytics'),
                    'value' => (string)((int)$statuscounts['active'] + (int)$statuscounts['expiring']),
                    'meta' => get_string('meta:currentdocuments', 'local_profileanalytics'),
                    'status' => ((int)$statuscounts['active'] + (int)$statuscounts['expiring']) > 0 ? 'ok' : 'muted',
                ],
                [
                    'label' => get_string('card:actionrequired', 'local_profileanalytics'),
                    'value' => (string)((int)$statuscounts['expired'] + (int)$statuscounts['nodocument']),
                    'meta' => get_string('meta:needsattention', 'local_profileanalytics'),
                    'status' => ((int)$statuscounts['expired'] + (int)$statuscounts['nodocument']) > 0 ? 'danger' : 'ok',
                ],
            ],
            'actions' => array_slice($actions, 0, 8),
            'upcoming' => array_slice($upcoming, 0, 8),
            'recentactivity' => [
                [
                    'label' => get_string('activity:lastaccess', 'local_profileanalytics'),
                    'value' => $lastaccess > 0 ? userdate($lastaccess) : get_string('value:notavailable', 'local_profileanalytics'),
                ],
                [
                    'label' => get_string('activity:lastcompletion', 'local_profileanalytics'),
                    'value' => !empty($recentcompletion->timecompleted)
                        ? $this->decoded(format_string((string)$recentcompletion->fullname)) . ' · ' . userdate((int)$recentcompletion->timecompleted)
                        : get_string('value:notavailable', 'local_profileanalytics'),
                ],
                [
                    'label' => get_string('activity:trackedcourses', 'local_profileanalytics'),
                    'value' => (string)count($courses),
                ],
            ],
            'documentsummary' => [
                ['label' => get_string('label:active', 'block_dashboardanalytics'), 'value' => (string)$statuscounts['active']],
                ['label' => get_string('label:expiring', 'block_dashboardanalytics'), 'value' => (string)$statuscounts['expiring']],
                ['label' => get_string('label:expired', 'block_dashboardanalytics'), 'value' => (string)$statuscounts['expired']],
                ['label' => get_string('label:nodocument', 'block_dashboardanalytics'), 'value' => (string)$statuscounts['nodocument']],
            ],
            'coursecolumns' => [
                get_string('label:course', 'block_dashboardanalytics'),
                get_string('label:status', 'block_dashboardanalytics'),
                get_string('label:expirydate', 'block_dashboardanalytics'),
                get_string('label:daysremaining', 'block_dashboardanalytics'),
            ],
            'courses' => $courses,
        ];
    }

    private function company_line(company_repository $companyrepo, int $userid, \stdClass $user): string {
        global $DB;

        if ($companyrepo->has_iomad_tables()) {
            $sql = "SELECT c.name
                      FROM {company_users} cu
                      JOIN {company} c ON c.id = cu.companyid
                     WHERE cu.userid = :userid
                  ORDER BY c.name ASC";
            $names = $DB->get_fieldset_sql($sql, ['userid' => $userid]);
            if ($names) {
                return implode(' · ', array_map(static function(string $name): string {
                    return html_entity_decode(format_string($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }, $names));
            }
        }

        if (!empty($user->department)) {
            return (string)$user->department;
        }

        return get_string('value:notavailable', 'local_profileanalytics');
    }

    private function status_from_percent(float $percent): string {
        if ($percent >= 80.0) {
            return 'ok';
        }
        if ($percent >= 70.0) {
            return 'warning';
        }
        if ($percent > 0.0) {
            return 'danger';
        }
        return 'muted';
    }

    private function risk_label(float $percent, int $rowcount): string {
        if ($rowcount <= 0) {
            return get_string('risk:nodata', 'local_profileanalytics');
        }
        if ($percent >= 80.0) {
            return get_string('risk:healthy', 'local_profileanalytics');
        }
        if ($percent >= 70.0) {
            return get_string('risk:watch', 'local_profileanalytics');
        }
        return get_string('risk:critical', 'local_profileanalytics');
    }

    private function status_class_for_label(string $status): string {
        if ($status === 'Active') {
            return 'ok';
        }
        if ($status === 'Expiring') {
            return 'warning';
        }
        if ($status === 'Expired') {
            return 'danger';
        }
        return 'muted';
    }

    private function days_remaining_label(int $expirytime): string {
        if ($expirytime <= 0) {
            return get_string('value:notavailable', 'local_profileanalytics');
        }

        $days = (int)floor(($expirytime - time()) / DAYSECS);
        if ($days < 0) {
            return get_string('value:overdue', 'local_profileanalytics', abs($days));
        }
        if ($days === 0) {
            return get_string('value:today', 'local_profileanalytics');
        }
        return get_string('value:daysleft', 'local_profileanalytics', $days);
    }

    private function decoded(string $value): string {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
