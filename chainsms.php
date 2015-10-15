<?php

require_once 'chainsms.civix.php';

const SURVEY_SUBMENU_LABEL = 'SMS Survey';

/**
 * Implementation of hook_civicrm_config
 */
function chainsms_civicrm_config(&$config) {
  _chainsms_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function chainsms_civicrm_xmlMenu(&$files) {
  _chainsms_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function chainsms_civicrm_install() {
  // Insert blank arrays for users to fill in their own swear words.
  CRM_Core_BAO_Setting::setItem(array(), 'org.thirdsectordesign.chainsms', 'safe_list');
  CRM_Core_BAO_Setting::setItem(array(), 'org.thirdsectordesign.chainsms', 'swear_list');

  // TODO create permission to view swear filter page.

  $smsConversationActivityType = CRM_Core_OptionGroup::getValue('activity_type', 'SMS Conversation', 'name');

  if (empty($smsConversationActivityType)){
    $createSMSConversationActivityApiParams = array(
      'version' => 3,
      'sequential' => 1,
      'label' => 'SMS Conversation',
      'description' => 'Translated SMS Surveys. Automatically added by the SMS Survey Extension.',
      'weight' => 0,
      'name' => 'SMSConversation',
      'reserved' => 1,
    );
    civicrm_api('ActivityType', 'create', $createSMSConversationActivityApiParams);
  }

  return _chainsms_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function chainsms_civicrm_uninstall() {
  // Wipe the swear filters.
  $dao = CRM_Core_DAO::executeQuery("DELETE FROM civicrm_setting WHERE group_name = 'org.thirdsectordesign.chainsms'");
  return _chainsms_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function chainsms_civicrm_enable() {
  return _chainsms_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function chainsms_civicrm_disable() {
  return _chainsms_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function chainsms_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _chainsms_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function chainsms_civicrm_managed(&$entities) {
  return _chainsms_civix_civicrm_managed($entities);
}

/**
 * Respond to inbound SMS, if applicable, through the chain system.
 * Part of a hook_civicrm_post implementation.
 */
function chainsms_post_respond_to_inbound_sms($op, $objectName, $objectId, &$objectRef) {
  $activity = civicrm_api('Activity', 'getsingle', array('version' => '3', 'id' => $objectId));
  if (civicrm_error($activity)) {
    CRM_Core_Error::debug_log_message("In chainsms_post_respond_to_inbound_sms: getsingle Activity id '$objectId' failed");
    return;
  }

  $p = new CRM_Chainsms_Processor;
  $p->inbound($activity);
}

/**
 * Add body text to Mass SMS activities.
 * Part of a hook_civicrm_post implementation.
 */
function chainsms_post_add_details_to_mass_sms($op, $objectName, $objectId, &$objectRef) {
  $activity = civicrm_api('Activity', 'getsingle', array('version' => '3', 'id' => $objectId));
  if (civicrm_error($activity)) {
    CRM_Core_Error::debug_log_message("In chainsms_post_add_details_to_mass_sms: getsingle Activity id '$objectId' failed");
    return;
  }

  $mailing = civicrm_api('Mailing', 'getsingle', array(
    'version'    => 3,
    // Matching on both the mailing ID and subject just to make sure
    'id'   => $activity['source_record_id'],
    'name' => $activity['subject'],
  ));
  if (civicrm_error($mailing)) {
    CRM_Core_Error::debug_log_message("In chainsms_post_add_details_to_mass_sms: getsingle Mailing id '{$activity['source_record_id']}' name '{$activity['subject']}' failed");
    return;
  }

  $activityUpdateResult = civicrm_api('Activity', 'create', array(
    'version' => 3,
    // Update existing Activity, copying body text from the Mailing that sent this Mass SMS
    'id'      => $activity['id'],
    'details' => $mailing['body_text'],
  ));
  if (civicrm_error($activityUpdateResult)) {
    CRM_Core_Error::debug_log_message("In chainsms_post_add_details_to_mass_sms: update Activity id {$activity['id']} adding details from Mailing id {$mailing['id']}'s body_text failed");
    return;
  }
}

function chainsms_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  //try and return as quickly as possible
  if (
    $objectName == 'Activity' &&
    $objectRef->activity_type_id == CRM_Core_OptionGroup::getValue('activity_type', 'Inbound SMS', 'name')
  ) {
    chainsms_post_respond_to_inbound_sms($op, $objectName, $objectId, $objectRef);
  }
  elseif (
    $objectName == 'Activity' && $op == 'create' &&
    $objectRef->activity_type_id == CRM_Core_OptionGroup::getValue('activity_type', 'Mass SMS', 'name')
  ) {
    chainsms_post_add_details_to_mass_sms($op, $objectName, $objectId, $objectRef);
  }
}

/**
 * civicrm_civicrm_navigationMenu
 *
 * implementation of civicrm_civicrm_navigationMenu
 */
function chainsms_civicrm_navigationMenu( &$params ) {
  $sAdministerMenuId = CRM_Core_DAO::getFieldValue('CRM_Core_BAO_Navigation', 'Administer', 'id', 'name');
  $sSystemSettingsMenuId = CRM_Core_DAO::getFieldValue('CRM_Core_BAO_Navigation', 'System Settings', 'id', 'name');

  //  Get the maximum key of $params
  $insertKey = ( max( array_keys($params) ) ) +2;

  $params[$sAdministerMenuId]['child'][$sSystemSettingsMenuId]['child'][$insertKey] = array (
    'attributes' => array (
       'label'      => 'SMS Survey Swear Filter',
       'name'       => 'SMSSurveySwearFilter',
       'url'        => 'civicrm/chainsms/swearfilter',
       'permission' => null,
       'operator'   => null,
       'separator'  => null,
       'parentID'   => $sSystemSettingsMenuId,
       'navID'      => $insertKey,
       'active'     => 1
    )
  );

  // get the id of the Mailings Menu
  $mailingsMenuId = CRM_Core_DAO::getFieldValue('CRM_Core_BAO_Navigation', 'Mailings', 'id', 'name');

  // Create the SMS Survey submenu
  //  Get the maximum key of $params
  $insertKey = max( array_keys($params[$mailingsMenuId]['child'])) +1;

  $subMenuId = $insertKey;

  $params[$mailingsMenuId]['child'][$subMenuId] = array (
    'attributes' => array (
       'label'      => SURVEY_SUBMENU_LABEL,
       'name'       => SURVEY_SUBMENU_LABEL,
       'url'        => null,
       'permission' => null,
       'operator'   => null,
       'separator'  => null,
       'parentID'   => $mailingsMenuId,
       'navID'      => $subMenuId,
       'active'     => 1
    )
  );

  // Populate the submenu
  $chainSmsMessagesKey = max(array_keys($params[$mailingsMenuId]['child']))+1;

  $params[$mailingsMenuId]['child'][$subMenuId]['child'][$chainSmsMessagesKey] = array (
    'attributes' => array (
      'label' => 'Chain SMS Messages',
      'name' => 'SMSSurveyChains',
      'url' => 'civicrm/smssurvey/chains',
      'permission' => 'view all contacts',
      'operator' => NULL,
      'separator' => FALSE,
      'parentID' => $subMenuId,
      'navID' => $chainSmsMessagesKey,
      'active' => 1
    )
  );

  $smsSurveyTranslateKey = $chainSmsMessagesKey+1;

  $params[$mailingsMenuId]['child'][$subMenuId]['child'][$smsSurveyTranslateKey] = array (
    'attributes' => array (
      'label' => 'Translate Responses',
      'name' => 'Translator',
      'url' => 'civicrm/smssurvey/translate',
      'permission' => 'view all contacts',
      'operator' => NULL,
      'separator' => FALSE,
      'parentID' => $subMenuId,
      'navID' => $smsSurveyTranslateKey,
      'active' => 1
    )
  );

  $smsSurveyCleanKey = $smsSurveyTranslateKey+1;

  $params[$mailingsMenuId]['child'][$subMenuId]['child'][$smsSurveyCleanKey] = array (
    'attributes' => array (
      'label' => 'Clean Translated Responses',
      'name' => 'TranslationCleaner',
      'url' => 'civicrm/smssurvey/translationcleaning',
      'permission' => 'view all contacts',
      'operator' => NULL,
      'separator' => FALSE,
      'parentID' => $subMenuId,
      'navID' => $smsSurveyCleanKey,
      'active' => 1
    )
  );
}

/**
 * Implements hook_civicrm_permission().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_permission
 */
function chainsms_civicrm_permission(&$permissions) {
  $permissions['view sms survey swear filter'] = 'ChainSMS: ' . ts('view sms survey swear filter');
}
