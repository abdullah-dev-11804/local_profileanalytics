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

/**
 * Add a visible node to the rendered user profile tree.
 *
 * @param \core_user\output\myprofile\tree $tree
 * @param stdClass $user
 * @param bool $iscurrentuser
 * @param stdClass|null $course
 * @return void
 */
function local_profileanalytics_myprofile_navigation(
    \core_user\output\myprofile\tree $tree,
    stdClass $user,
    bool $iscurrentuser,
    ?stdClass $course
): void {
    if (!local_profileanalytics_can_view_user((int)$user->id)) {
        return;
    }

    $categoryname = 'profileanalytics';
    $url = new moodle_url('/local/profileanalytics/view.php', ['id' => (int)$user->id]);

    try {
        $tree->add_category(new \core_user\output\myprofile\category(
            $categoryname,
            get_string('pluginname', 'local_profileanalytics')
        ));
    } catch (\Throwable $e) {
    }

    try {
        $tree->add_node(new \core_user\output\myprofile\node(
            $categoryname,
            'profileanalyticslink',
            get_string('pluginname', 'local_profileanalytics'),
            null,
            $url
        ));
    } catch (\Throwable $e) {
    }
}
