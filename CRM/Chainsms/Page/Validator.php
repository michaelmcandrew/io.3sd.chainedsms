<?php

require_once 'CRM/Core/Page.php';

class CRM_Chainsms_Page_Validator extends CRM_Core_Page {
  const ACTIVITY_TYPE_OUTBOUND_SMS = 4;
  const ACTIVITY_TYPE_MASS_SMS = 45;
  const ACTIVITY_TYPE_INBOUND_SMS = 46;
  const ACTIVITY_TYPE_SMS_DELIVERY = 47;
  const ACTIVITY_TYPE_SMS_CONVERSATION = 48;
  const RECORD_TYPE_TARGET = 3;

  static $smsActivityTypes = array(
    self::ACTIVITY_TYPE_OUTBOUND_SMS,
    self::ACTIVITY_TYPE_MASS_SMS,
    self::ACTIVITY_TYPE_INBOUND_SMS,
    self::ACTIVITY_TYPE_SMS_DELIVERY,
    self::ACTIVITY_TYPE_SMS_CONVERSATION,
  );

  /**
   * Gets the contact IDs of all contacts who appear as targets of a Mass SMS
   * activity regarding a specific Mailing.
   *
   * @param int $iMailingId Mailing ID
   * @return array Array of contact IDs
   */
  static function getMassSMSRecipients($iMailingId) {
    $massRecipientsDao = CRM_Core_DAO::executeQuery("
      SELECT `cat`.`contact_id`         AS `cid`
        FROM `civicrm_activity`         AS `ca`
  INNER JOIN `civicrm_activity_contact` AS `cat`
          ON `cat`.`activity_id`         = `ca`.`id`
         AND `cat`.`record_type_id`      = %0
       WHERE `ca`.`source_record_id`     = %1
         AND `ca`.`activity_type_id`     = %2
    ", array(
      array(self::RECORD_TYPE_TARGET,     'Int'),
      array($iMailingId,                  'Int'),
      array(self::ACTIVITY_TYPE_MASS_SMS, 'Int'),
    ));

    $massRecipients = array();
    while ($massRecipientsDao->fetch()) {
      $massRecipients[] = $massRecipientsDao->cid;
    }
    return $massRecipients;
  }

  /**
   * Gets the time window during which messages were being sent out
   * for a specific Mailing.
   *
   * @param int $iMailingId Mailing ID
   * @return array|null Contains MySQL datetimes as start_date and end_date. NULL if unable to determine.
   */
  static function getMailingDeliveryWindow($iMailingId) {
    $timeDao = CRM_Core_DAO::executeQuery("
      SELECT MIN(`start_date`)     AS `start_date`,
             MAX(`end_date`)       AS `end_date`
        FROM `civicrm_mailing_job`
       WHERE `mailing_id`           = %0
    ", array(
      array($iMailingId, 'Int'),
    ));
    if (!$timeDao->fetch()) {
      return NULL;
    }
    return array(
      'start_date' => $timeDao->start_date,
      'end_date'   => $timeDao->end_date,
    );
  }

  /**
   * Gets the contact IDs of all contacts who were targets of an SMS Delivery
   * during the time delivery was running for a specific Mailing.
   *
   * @param int $iMailingId Mailing ID
   * @return array|null Array of contact IDs, or NULL on error
   */
  static function getFirstSMSDeliveryRecipients($iMailingId) {
    $mailingWindow = self::getMailingDeliveryWindow($iMailingId);
    if ($mailingWindow === NULL) {
      return NULL;
    }

    $delRecipientsDao = CRM_Core_DAO::executeQuery("
      SELECT `cat`.`contact_id`         AS `cid`
        FROM `civicrm_activity`         AS `ca`
  INNER JOIN `civicrm_activity_contact` AS `cat`
          ON `cat`.`activity_id`         = `ca`.`id`
         AND `cat`.`record_type_id`      = %0
       WHERE `ca`.`activity_type_id`     = %1
         AND `ca`.`activity_date_time`  BETWEEN %2 AND %3
    ", array(
      array(self::RECORD_TYPE_TARGET,         'Int'),
      array(self::ACTIVITY_TYPE_SMS_DELIVERY, 'Int'),
      array($mailingWindow['start_date'],     'String'),
      array($mailingWindow['end_date'],       'String'),
    ));

    $delRecipients = array();
    while ($delRecipientsDao->fetch()) {
      $delRecipients[] = $delRecipientsDao->cid;
    }
    return $delRecipients;
  }

  /**
   * Gets the first activity for a certain contact that meets certain conditions.
   *
   * @param int $cid Contact ID
   * @param array $whereClauses Array of strings containing conditions
   * @return array|null Activity details, NULL if no such activity
   */
  static function getFirstActivityFor($cid, $whereClauses = NULL) {
    $query = "
      SELECT `ca`.*
        FROM `civicrm_activity`         AS `ca`
  INNER JOIN `civicrm_activity_contact` AS `cat`
          ON `cat`.`activity_id`         = `ca`.`id`
         AND `cat`.`record_type_id`      = %0
         AND `cat`.`contact_id`          = %1
    ";
    if (!empty($whereClauses)) {
      if (!is_array($whereClauses)) {
        $whereClauses = array($whereClauses);
      }
      $query .= ' WHERE (' . implode(') AND (', $whereClauses) . ') ';
    }
    $query .= "
      ORDER BY `ca`.`activity_date_time` ASC
         LIMIT 1
    ";

    $dao = CRM_Core_DAO::executeQuery($query, array(
      array(self::RECORD_TYPE_TARGET, 'Int'),
      array($cid, 'Int'),
    ));
    if (!$dao->fetch()) {
      return NULL;
    }
    return get_object_vars($dao);
  }

  /**
   * getNoMassSMSActivity is to return the contacts who should have been sent 
   * the mailing but for whom we have no record of them being sent the message
   *
   * @param $iMailingId the id of the SMS mailing
   * @param &$aContactIds with no mass sms activity. Pass by reference for efficiency
   */
  function getNoMassSMSActivity($iMailingId, &$aContactIds, &$sStatsData) {
    $mailingRecipientsResult = civicrm_api('MailingRecipients', 'get', array(
      'version'    => 3,
      'sequential' => 1,
      'mailing_id' => $iMailingId,
      'rowCount'   => 0,
      'return'     => 'contact_id',
    ));
    if (civicrm_error($mailingRecipientsResult)) {
      $aContactIds = NULL;
      return;
    }

    $mailingRecipients = array();
    foreach ($mailingRecipientsResult['values'] as $recipient) {
      $mailingRecipients[] = $recipient['contact_id'];    
    }
    
    // Remove all deleted contacts.
    $activeMailingContactsQuery = CRM_Core_DAO::executeQuery("
      SELECT `cc`.`id`         AS `cid`
        FROM `civicrm_contact` AS `cc`
       WHERE `cc`.`id`         IN (%0)
         AND `cc`.`is_deleted` = 0
    ", array(
       array(implode(',', $mailingRecipients),'Text'),
    ));
    
    // Build list of active mailing recipients.
    $activeMailingContacts = array();
    while( $activeMailingContactsQuery->fetch()){
      $activeMailingContacts[] = $activeMailingContactsQuery->cid;
    }

    $massRecipients = self::getMassSMSRecipients($iMailingId);
    
    $aContactIds = array_diff($activeMailingContacts, $massRecipients);
    // Create title string with stats.
    $iTotalContacts = count($activeMailingContacts);
    $iFailedContacts = count($aContactIds);
    
    $sStatsData = array(
      'failedCount' => $iFailedContacts,
      'totalCount'  => $iTotalContacts,
    );
  }

  /**
   * getNoFirstSMSDeliveredActivity although the contact has a mass SMS activity,
   * there is no sms delivered activity for them 
   *
   * @param $iMailingId the id of the SMS mailing
   * @param &$aContactIds with no mass sms activity. Pass by reference for efficiency
   */
  function getNoFirstSMSDeliveredActivity($iMailingId, &$aContactIds, &$sStatsData) {
    $massRecipients = self::getMassSMSRecipients($iMailingId);
    $delRecipients = self::getFirstSMSDeliveryRecipients($iMailingId);
    if ($delRecipients === NULL) {
      $aContactIds = NULL;
      return;
    }
    $aContactIds = array_diff($massRecipients, $delRecipients);
    // Create title string with stats.
    $iTotalContacts = count($massRecipients);
    $iFailedContacts = count($aContactIds);
    $sStatsData = array(
      'failedCount' => $iFailedContacts,
      'totalCount'  => $iTotalContacts,
    );
  }

  /**
   * Body of the two functions below, which I understood to be similar in meaning.
   *
   * @see getNoSecondSMSActivity
   * @see getMissingDeliveredActivity
   */
  static function bodyNoSecondSMS($iMailingId, $activityTypeId, &$sStatsData) {
    $withCleanableResponse = array();
    $withChainOutbound = array();
    $delRecipients = self::getFirstSMSDeliveryRecipients($iMailingId);
    foreach ($delRecipients as $delRecipientId) {

      // Get the date/time of their first SMS delivery within the mailing job execution times
      // (This should be the first message in the chain)
      $mailingWindow = self::getMailingDeliveryWindow($iMailingId);
      if ($mailingWindow === NULL) {
        continue;
      }
      $mailingStart = CRM_Core_DAO::escapeString($mailingWindow['start_date']);
      $mailingEnd = CRM_Core_DAO::escapeString($mailingWindow['end_date']);
      $firstDelivery = self::getFirstActivityFor($delRecipientId, array(
        "`ca`.`activity_type_id` = " . self::ACTIVITY_TYPE_SMS_DELIVERY,
        "`ca`.`activity_date_time` BETWEEN '" . $mailingStart . "' AND '" . $mailingEnd . "'",
      ));
      if ($firstDelivery === NULL) {
        continue;
      }

      // Get the details of their next SMS-related activity after that
      // (This should be their inbound response to the first message in the chain)
      $nextSMSActivity = self::getFirstActivityFor($delRecipientId, array(
        "`ca`.`activity_type_id` IN (" . implode(',', self::$smsActivityTypes) . ")",
        "`ca`.`activity_date_time` > '" . CRM_Core_DAO::escapeString($firstDelivery['activity_date_time']) . "'",
      ));
      if ($nextSMSActivity === NULL) {
        continue;
      }

      // Check that it is an inbound, and is cleanable down to a single letter
      if ($nextSMSActivity['activity_type_id'] != self::ACTIVITY_TYPE_INBOUND_SMS) {
        continue;
      }
      $cleanedInbound = strtolower(trim(CRM_Chainsms_Processor::cleanInboundResponse($nextSMSActivity['details'])));
      if (!preg_match('/^[a-z]$/', $cleanedInbound)) {
        continue;
      }
      $withCleanableResponse[] = $delRecipientId;

      // Check for an outbound within 5 minutes of the inbound
      // (This should be the second message in the chain, depending on the first reply)
      $escDate = CRM_Core_DAO::escapeString($nextSMSActivity['activity_date_time']);
      $outboundAfterInbound = self::getFirstActivityFor($delRecipientId, array(
        "`ca`.`activity_type_id` = " . $activityTypeId,
        "`ca`.`activity_date_time` BETWEEN '" . $escDate . "' AND DATE_ADD('" . $escDate . "', INTERVAL 5 MINUTE)",
      ));
      if ($outboundAfterInbound !== NULL) {
        $withChainOutbound[] = $delRecipientId;
      }
    }
    
    $aContactIds = array_diff($withCleanableResponse, $withChainOutbound);
    // Create title substring with stats.
    $iTotalContacts = count($withCleanableResponse);
    $iFailedContacts = count($aContactIds);
    $sStatsData = array(
      'failedCount' => $iFailedContacts,
      'totalCount'  => $iTotalContacts,
    );
    
    return $aContactIds;
  }

  /**
   * getNoSecondSMSActivity although the contact has replied to the first SMS 
   * message with a response that can be cleaned into a single letter, they were 
   * not sent a follow up Outbound SMS
   *
   * @param $iMailingId the id of the SMS mailing
   * @param &$aContactIds with no mass sms activity. Pass by reference for efficiency
   */
  function getNoSecondSMSActivity($iMailingId, &$aContactIds, &$sStatsData) {    
    $aContactIds = self::bodyNoSecondSMS($iMailingId, self::ACTIVITY_TYPE_OUTBOUND_SMS, $sStatsData);
  }

  /**
   * getMissingDeliveredActivity although the contact has replied to the first SMS 
   * message with a response that can be cleaned into a single letter, they were 
   * not sent a follow up message
   *
   * @param $iMailingId the id of the SMS mailing
   * @param &$aContactIds with no mass sms activity. Pass by reference for efficiency
   */
  function getMissingDeliveredActivity($iMailingId, &$aContactIds, &$sStatsData) {
    $aContactIds = self::bodyNoSecondSMS($iMailingId, self::ACTIVITY_TYPE_SMS_DELIVERY, $sStatsData);
  }

  /*
   * assignIfNotEmpty
   * 
   * @param $varName the name of the variable that will be passed to smarty
   * @param $iMailingId the id of the mailing that was sent
   * @param $funcName the name of the function to populate those variables
   */
  function assignIfNotEmpty($varName, $iMailingId, $funcName){
    $contacts  = array();
    $statsData = '';
    $this->$funcName($iMailingId, $contacts, $statsData);
    if (!empty($contacts)){
      $this->assign($varName, $contacts);      
    } else {
      $this->assign($varName, 0);
    }
    $this->assign("{$varName}_title", $statsData);
  }
  
  function run() {

    $sqlGetSMSMailings = "
      SELECT 
        id, 
        name, 
        DATE(scheduled_date) AS send_date, 
        DATE_ADD(DATE(scheduled_date), INTERVAL 2 DAY) AS limit_date 
      FROM 
        civicrm_mailing 
      WHERE 
        sms_provider_id != ''
    ";

    $dao = CRM_Core_DAO::executeQuery($sqlGetSMSMailings);

    $aSMSMailings = array();
	
  	while($dao->fetch()){
      $aSMSMailings[]= array(
        'id' => $dao->id, 
        'name' => $dao->name, 
        'send_date' => $dao->send_date, 
        'limit_date' => $dao->limit_date, 
	    );
	  }

    $this->assign('aSMSMailings', $aSMSMailings);
    
    // get validator parameters
    if (isset($_GET['mailingId'])){
      $iMailingId = intval($_GET['mailingId']);
      // TODO check that offset is not higher than the number of incorrect ones in this campaign, and > 0
    } else {
      $iMailingId = 0;	
    }
    
    $this->assign('iMailingId', $iMailingId);
    
    // if no mailing has been selected then bail
    if ($iMailingId == 0){
      parent::run();
      return;
    }
    
    // collect all the data
    $this->assignIfNotEmpty('aNoMassSMS', $iMailingId, 'getNoMassSMSActivity');
    $this->assignIfNotEmpty('aNoFirstSMSDelivered', $iMailingId, 'getNoFirstSMSDeliveredActivity');
    $this->assignIfNotEmpty('aNoSecondSMS', $iMailingId, 'getNoSecondSMSActivity');
    $this->assignIfNotEmpty('aMissingDeliveredSMS', $iMailingId, 'getMissingDeliveredActivity');
    
    parent::run();
  }
  
}
