<?php
/*
 * Copyright 2005-2013 the original author or authors.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once('libs/init.php');
require_once('libs/invitation.php');
require_once('libs/operator.php');
require_once('libs/track.php');
require_once('libs/request.php');

$invited = FALSE;
$operator = array();
$response = array();
if (Settings::get('enabletracking') == '1') {

	$entry = isset($_GET['entry']) ? $_GET['entry'] : "";
	$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "";
	$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : false;

	// Check if session start
	if (isset($_SESSION['visitorid'])
			&& preg_match('/^[0-9]+$/', $_SESSION['visitorid'])) {
		// Session started. Track visitor
		$invited = invitation_check($_SESSION['visitorid']);
		$visitorid = track_visitor($_SESSION['visitorid'], $entry, $referer);
	} else {
		$visitor = track_get_visitor_by_user_id($user_id);
		if ($visitor !== false) {
			// Session not started but visitor exist in database.
			// Probably third-party cookies disabled by the browser.
			// Use tracking by local cookie at target site
			$invited = invitation_check($visitor['visitorid']);
			$visitorid = track_visitor($visitor['visitorid'], $entry, $referer);
		} else {
			// Start tracking session
			$visitorid = track_visitor_start($entry, $referer);
			$visitor = track_get_visitor_by_id($visitorid);
			$user_id = $visitor['userid'];
		}
	}

	if ($visitorid) {
		$_SESSION['visitorid'] = $visitorid;
	}

	if ($invited !== FALSE) {
		$operator = operator_by_id($invited);
	}

	if ($user_id !== false) {
		// Update local cookie value at target site
		$response['handlers'][] = 'mibewUpdateUserId';
		$response['dependences']['mibewUpdateUserId'] = array();
		$response['data']['user']['id'] = $user_id;
	}

}

if ($invited !== FALSE) {
    $response['load']['mibewInvitationScript'] = get_app_location(true, is_secure_request()) . '/js/compiled/invite.js';
    $response['handlers'][] = 'mibewInviteOnResponse';
    $response['dependences']['mibewInviteOnResponse'] = array('mibewInvitationScript');
    $locale = isset($_GET['lang']) ? $_GET['lang'] : '';
    $operatorName = ($locale == $home_locale) ? $operator['vclocalename'] : $operator['vccommonname'];
    $response['data']['invitation']['operator'] = htmlspecialchars($operatorName);
    $response['data']['invitation']['message'] = getlocal("invitation.message");
    $response['data']['invitation']['avatar'] = htmlspecialchars($operator['vcavatar']);
}

start_js_output();
echo build_js_response($response);

exit;
?>