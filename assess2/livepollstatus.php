<?php
/*
 * IMathAS: Assessment endpoint for teacher updates to livepoll status
 * (c) 2019 David Lippman
 *
 *
 * Method: POST
 * Query string parameters:
 *  aid   Assessment ID
 *  cid   Course ID
 *
 * POST
 *  curquestion   The question id
 *  curstate      The question state
 *  forceregen    (optional) set true to generate new seed
 *
 * Returns: partial assessInfo object, containing livepoll_status
 *  If selecting a new question, also returns HTML for that question
 */

$init_skip_csrfp = true; // TODO: get CSRFP to work
$no_session_handler = 'onNoSession';
require_once("../init.php");
require_once("./common_start.php");
require_once("./AssessInfo.php");
require_once("./AssessRecord.php");
require_once('./AssessUtils.php');

header('Content-Type: application/json; charset=utf-8');

check_for_required('GET', array('aid', 'cid'));
check_for_required('POST', array('newquestion', 'newstate'));
$cid = Sanitize::onlyInt($_GET['cid']);
$aid = Sanitize::onlyInt($_GET['aid']);
$uid = $userid;
$newQuestion = Sanitize::onlyInt($_POST['newquestion']);
$newState = Sanitize::onlyInt($_POST['newstate']);

// this page is only for teachers
if (!$isteacher) {
  echo '{"error": "teacher_only"}';
  exit;
}

$now = time();

// load settings
$assess_info = new AssessInfo($DBH, $aid, $cid, false);
$assess_info->loadException($uid, $isstudent, $studentinfo['latepasses'] , $latepasshrs, $courseenddate);

// load user's assessment record - always operating on scored attempt here
$assess_record = new AssessRecord($DBH, $assess_info, false);
$assess_record->loadRecord($uid);


// grab any assessment info fields that may have updated:
// has_active_attempt, timelimit_expires,
// prev_attempts (if we just closed out a version?)
// and those not yet loaded:
// help_features, intro, resources, video_id, category_urls
$include_from_assess_info = array(
  'available', 'startdate', 'enddate', 'original_enddate', 'submitby',
  'extended_with', 'allowed_attempts', 'latepasses_avail', 'latepass_extendto',
  'showscores', 'timelimit', 'points_possible'
);
$assessInfoOut = $assess_info->extractSettings($include_from_assess_info);

// get current livepoll status
$stm = $DBH->prepare("SELECT curquestion,curstate,seed,startt FROM imas_livepoll_status WHERE assessmentid=:assessmentid");
$stm->execute(array(':assessmentid'=>$aid));
$livepollStatus = $stm->fetch(PDO::FETCH_ASSOC);

$query = "UPDATE imas_livepoll_status SET ";
$query .= "curquestion=?,curstate=?,seed=?,startt=? ";
$query .= "WHERE assessmentid=?";
$livepollUpdate = $DBH->prepare($query);

// If new question, or if previous state was 0, then we're
// preloading a new question
// We'll set the state and load the question HTML
// No need to send anything out to livepoll server for this
if ($newQuestion !== $livepollStatus['curquestion'] ||
  $livepollStatus['curstate'] === 0
) {
  // force the newstate to be 1; don't want to skip any steps
  $newState = 1;

  // look up question HTML. Also grab seed
  // get current question version
  $qid = $assess_record->getQuestionId($qn);

  // do regen if requested
  if (!empty($_POST['forceregen'])) {
    $qid = $assess_record->buildNewQuestionVersion($qn, $qid);
  }

  // load question settings and code
  $assess_info->loadQuestionSettings(array($qid), true);

  // get question object. Not showing scores in this state.
  $assessInfoOut['questions'] = array(
    $qn => $assess_record->getQuestionObject($qn, false, true, true)
  );

  // extract seed
  $seed = $assessInfoOut['questions'][$qn]['seed'];

  //set status
  $livepollUpdate->execute(array($newQuestion, $newState, $seed, 0, $aid));

  //output
  $assessInfoOut['livepoll_status'] = array(
    'curquestion' => $newQuestion,
    'curstate' => $newState,
    'seed' => $seed,
    'startt' => 0
  );
}

// save record if needed
$assess_record->saveRecordIfNeeded();

//output JSON object
echo json_encode($assessInfoOut);