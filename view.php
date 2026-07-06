<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

require_login();

$user = core_user::get_user($id, '*', MUST_EXIST);
$usercontext = context_user::instance($user->id);

if (!local_profileanalytics_can_view_user((int)$user->id)) {
    throw new required_capability_exception($usercontext, 'moodle/user:viewdetails', 'nopermissions', '');
}

$PAGE->set_context($usercontext);
$PAGE->set_url(new moodle_url('/local/profileanalytics/view.php', ['id' => $user->id]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('page:title', 'local_profileanalytics', fullname($user)));
$PAGE->set_heading(fullname($user));
$PAGE->set_secondary_navigation(false);
$PAGE->requires->css(new moodle_url('/local/profileanalytics/styles.css'));

$service = new \local_profileanalytics\service\profile_analytics_service();
$data = $service->build((int)$user->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('heading:personalanalytics', 'local_profileanalytics'));

echo html_writer::start_div('local-profileanalytics');

echo html_writer::start_div('lpa-hero');
echo html_writer::start_div('lpa-hero-copy');
echo html_writer::tag('h3', s($data['fullname']), ['class' => 'lpa-hero-name']);
echo html_writer::tag('p', s($data['companyline']), ['class' => 'lpa-hero-meta']);
echo html_writer::end_div();
echo html_writer::tag('span', s($data['risklabel']), ['class' => 'lpa-risk-badge lpa-risk-' . $data['riskstatus']]);
echo html_writer::end_div();

echo html_writer::start_div('lpa-card-grid');
foreach ($data['cards'] as $card) {
    echo html_writer::start_div('lpa-card lpa-card-' . $card['status']);
    echo html_writer::tag('div', s($card['label']), ['class' => 'lpa-card-label']);
    echo html_writer::tag('div', s($card['value']), ['class' => 'lpa-card-value']);
    echo html_writer::tag('div', s($card['meta']), ['class' => 'lpa-card-meta']);
    echo html_writer::end_div();
}
echo html_writer::end_div();

echo html_writer::start_div('lpa-layout');

echo html_writer::start_div('lpa-panel');
echo html_writer::tag('h4', get_string('panel:requiredactions', 'local_profileanalytics'));
if (empty($data['actions'])) {
    echo html_writer::tag('p', get_string('empty:actions', 'local_profileanalytics'), ['class' => 'lpa-empty']);
} else {
    echo html_writer::start_tag('ul', ['class' => 'lpa-action-list']);
    foreach ($data['actions'] as $action) {
        echo html_writer::start_tag('li', ['class' => 'lpa-action-item']);
        echo html_writer::tag('span', s($action['course']), ['class' => 'lpa-action-course']);
        echo html_writer::tag('span', s($action['status']), ['class' => 'lpa-pill lpa-pill-' . $action['statusclass']]);
        echo html_writer::tag('span', s($action['meta']), ['class' => 'lpa-action-meta']);
        echo html_writer::end_tag('li');
    }
    echo html_writer::end_tag('ul');
}
echo html_writer::end_div();

echo html_writer::start_div('lpa-panel');
echo html_writer::tag('h4', get_string('panel:upcomingexpiry', 'local_profileanalytics'));
if (empty($data['upcoming'])) {
    echo html_writer::tag('p', get_string('empty:upcoming', 'local_profileanalytics'), ['class' => 'lpa-empty']);
} else {
    echo html_writer::start_tag('ul', ['class' => 'lpa-timeline']);
    foreach ($data['upcoming'] as $item) {
        echo html_writer::start_tag('li', ['class' => 'lpa-timeline-item']);
        echo html_writer::tag('span', s($item['course']), ['class' => 'lpa-timeline-course']);
        echo html_writer::tag('span', s($item['date']), ['class' => 'lpa-timeline-date']);
        echo html_writer::tag('span', s($item['meta']), ['class' => 'lpa-timeline-meta']);
        echo html_writer::end_tag('li');
    }
    echo html_writer::end_tag('ul');
}
echo html_writer::end_div();

echo html_writer::start_div('lpa-panel');
echo html_writer::tag('h4', get_string('panel:recentactivity', 'local_profileanalytics'));
echo html_writer::start_tag('ul', ['class' => 'lpa-stat-list']);
foreach ($data['recentactivity'] as $row) {
    echo html_writer::start_tag('li', ['class' => 'lpa-stat-item']);
    echo html_writer::tag('span', s($row['label']), ['class' => 'lpa-stat-label']);
    echo html_writer::tag('span', s($row['value']), ['class' => 'lpa-stat-value']);
    echo html_writer::end_tag('li');
}
echo html_writer::end_tag('ul');
echo html_writer::end_div();

echo html_writer::start_div('lpa-panel');
echo html_writer::tag('h4', get_string('panel:documentsummary', 'local_profileanalytics'));
echo html_writer::start_tag('ul', ['class' => 'lpa-stat-list']);
foreach ($data['documentsummary'] as $row) {
    echo html_writer::start_tag('li', ['class' => 'lpa-stat-item']);
    echo html_writer::tag('span', s($row['label']), ['class' => 'lpa-stat-label']);
    echo html_writer::tag('span', s($row['value']), ['class' => 'lpa-stat-value']);
    echo html_writer::end_tag('li');
}
echo html_writer::end_tag('ul');
echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::start_div('lpa-panel lpa-panel-table');
echo html_writer::tag('h4', get_string('panel:coursestatus', 'local_profileanalytics'));
if (empty($data['courses'])) {
    echo html_writer::tag('p', get_string('empty:courses', 'local_profileanalytics'), ['class' => 'lpa-empty']);
} else {
    echo html_writer::start_tag('table', ['class' => 'generaltable lpa-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    foreach ($data['coursecolumns'] as $column) {
        echo html_writer::tag('th', s($column));
    }
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    foreach ($data['courses'] as $course) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($course['course']));
        echo html_writer::tag('td', s($course['status']), ['class' => 'lpa-status-cell']);
        echo html_writer::tag('td', s($course['expiry']));
        echo html_writer::tag('td', s($course['daysremaining']));
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}
echo html_writer::end_div();

echo html_writer::end_div();

echo $OUTPUT->footer();
