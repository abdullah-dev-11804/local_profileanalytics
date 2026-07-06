<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Add a personal analytics link to the user profile navigation.
 *
 * @param navigation_node $navigation
 * @param stdClass $user
 * @param context_user $usercontext
 * @param stdClass|null $course
 * @param context_course|null $coursecontext
 * @return void
 */
function local_profileanalytics_extend_navigation_user(
    navigation_node $navigation,
    stdClass $user,
    context_user $usercontext,
    ?stdClass $course,
    ?context_course $coursecontext
): void {
    if (!isloggedin() || isguestuser()) {
        return;
    }

    if (!local_profileanalytics_can_view_user((int)$user->id)) {
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
}

/**
 * Check whether the current user can view the requested user's analytics page.
 *
 * @param int $targetuserid
 * @return bool
 */
function local_profileanalytics_can_view_user(int $targetuserid): bool {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return false;
    }

    if ((int)$USER->id === $targetuserid || is_siteadmin()) {
        return true;
    }

    $context = context_user::instance($targetuserid);
    return has_capability('moodle/user:viewdetails', $context)
        || has_capability('moodle/user:viewalldetails', $context);
}
