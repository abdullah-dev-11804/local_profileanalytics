<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

@ini_set('log_errors', '1');
@ini_set('error_log', '/tmp/profileanalytics-debug.log');

/**
 * Add a personal analytics link to the user profile navigation.
 *
 * @param navigation_node $navigation
 * @param stdClass $user
 * @param context_user $usercontext
 * @param stdClass|null $course
 * @param context|null $coursecontext
 * @return void
 */
function local_profileanalytics_extend_navigation_user(
    navigation_node $navigation,
    stdClass $user,
    context_user $usercontext,
    ?stdClass $course,
    ?context $coursecontext
): void {
    global $USER;

    error_log('local_profileanalytics: extend_navigation_user called for targetuser=' . (int)$user->id .
        ' currentuser=' . ((int)($USER->id ?? 0)));

    if (!isloggedin() || isguestuser()) {
        error_log('local_profileanalytics: skipping because current session is not a logged in non-guest user');
        return;
    }

    if (!local_profileanalytics_can_view_user((int)$user->id)) {
        error_log('local_profileanalytics: skipping because local_profileanalytics_can_view_user returned false for targetuser=' . (int)$user->id);
        return;
    }

    $url = new moodle_url('/local/profileanalytics/view.php', ['id' => (int)$user->id]);
    $navigation->add(
        get_string('pluginname', 'local_profileanalytics'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_profileanalytics'
    );

    error_log('local_profileanalytics: navigation link added for targetuser=' . (int)$user->id .
        ' url=' . $url->out(false));
}

/**
 * Check whether the current user can view the requested user's analytics page.
 *
 * @param int $targetuserid
 * @return bool
 */
function local_profileanalytics_can_view_user(int $targetuserid): bool {
    global $USER;

    error_log('local_profileanalytics: can_view_user check targetuser=' . $targetuserid .
        ' currentuser=' . ((int)($USER->id ?? 0)));

    if (!isloggedin() || isguestuser()) {
        error_log('local_profileanalytics: can_view_user false because current session is not a logged in non-guest user');
        return false;
    }

    if ((int)$USER->id === $targetuserid || is_siteadmin()) {
        error_log('local_profileanalytics: can_view_user true because same user or site admin');
        return true;
    }

    $context = context_user::instance($targetuserid);
    $canviewdetails = has_capability('moodle/user:viewdetails', $context);
    $canviewalldetails = has_capability('moodle/user:viewalldetails', $context);
    error_log('local_profileanalytics: capability results viewdetails=' . ($canviewdetails ? '1' : '0') .
        ' viewalldetails=' . ($canviewalldetails ? '1' : '0'));
    return $canviewdetails || $canviewalldetails;
}
