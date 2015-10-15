<?php

// The activity type that we use below
const ACTYPE_NAME_SMS_CONV = 'SMS Conversation';

const ACTIVITY_STATUS_SCHEDULED = 1;
const ACTIVITY_STATUS_COMPLETED = 2;

// Retrieve an activity type ID given the activity type name.
function smssurveystatsget_get_activity_type_id_by_name($name) {
  // TODO REFACTOR if CRM-14176 gets fixed (possibly 4.6
  // according to open ticket), use an ActivityType API call
  $result = civicrm_api('OptionGroup', 'getsingle', array(
    'version'    => 3,
    'sequential' => 1,
    'name'       => 'activity_type',
  ));
  if (civicrm_error($result)) {
    return NULL;
  }
  $ogid_actype = $result['id'];

  $result = civicrm_api('OptionValue', 'getsingle', array(
    'version'         => 3,
    'sequential'      => 1,
    'option_group_id' => $ogid_actype,
    'name'            => $name,
  ));
  if (civicrm_error($result)) {
    return NULL;
  }
  return $result['value'];
}

/**
 * SMSSurveyStats.Get API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_s_m_s_survey_stats_get_spec(&$spec) {
  // SMSSurveyStats.Get does not take any arguments
}

/**
 * SMSSurveyStats.Get API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_s_m_s_survey_stats_get($params) {
  $sqlGetSurveyStats = "
    SELECT civicrm_activity.subject AS subject,
           scheduled_date,
           status_id,
           count(civicrm_activity.id) AS count,
           cmr.recipients AS recipients
      FROM civicrm_activity

 LEFT JOIN (SELECT id, name, scheduled_date FROM civicrm_mailing WHERE 1) AS mailing
        ON mailing.name = civicrm_activity.subject 
 LEFT JOIN (SELECT mailing_id, COUNT(contact_id) AS recipients FROM civicrm_mailing_recipients GROUP BY mailing_id) AS cmr
        ON cmr.mailing_id = mailing.id

     WHERE activity_type_id=%0
     GROUP BY subject, status_id 
     ORDER BY scheduled_date DESC
  "; 

  $dao = CRM_Core_DAO::executeQuery($sqlGetSurveyStats, array(
    array(smssurveystatsget_get_activity_type_id_by_name(ACTYPE_NAME_SMS_CONV), 'Int'),
  ));
  
  $aResults = array();
  
  while ($dao->fetch()) {
    switch ($dao->status_id) {
      // This status is given to SMS Conversation activities that did not fully translate
      case ACTIVITY_STATUS_SCHEDULED:
        $status = 'transfailed';
        break;

      // This status is given to SMS Conversation activities that did fully translate
      case ACTIVITY_STATUS_COMPLETED:
        $status = 'transpassed';
        break;

      // Status is something we didn't recognise
      default:
        $status = $dao->status_id;
    }

  	$aResults[$dao->subject][$status]      = $dao->count;
    $aResults[$dao->subject]['date']       = $dao->scheduled_date;
    $aResults[$dao->subject]['subject']    = $dao->subject;
    $aResults[$dao->subject]['recipients'] = $dao->recipients;
  }

  return civicrm_api3_create_success($aResults, $params, 'SMSSurveyStats', 'Get');
}
