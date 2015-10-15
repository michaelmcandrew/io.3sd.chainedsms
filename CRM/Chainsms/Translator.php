<?php
class CRM_Chainsms_Translator {
  // record_type_id values in civicrm_activity_contact for a record corresponding to:
  const RECORD_TYPE_ID_SOURCE = 2; // source_contact_id
  const RECORD_TYPE_ID_TARGET = 3; // target_contact_id

  function __construct(){
    $this->OutboundSMSActivityTypeId = CRM_Core_OptionGroup::getValue('activity_type', 'SMS', 'name');
    $this->MassSMSActivityTypeId = CRM_Core_OptionGroup::getValue('activity_type', 'Mass SMS', 'name');
    $this->InboundSMSActivityTypeId = CRM_Core_OptionGroup::getValue('activity_type', 'Inbound SMS', 'name');
    // TODO REFACTOR is SMSDeliveryActivityTypeId used anywhere?
    $this->SMSDeliveryActivityTypeId = CRM_Core_OptionGroup::getValue('activity_type', 'SMS Delivery', 'name');
    $this->SMSConversationActivityTypeId = CRM_Core_OptionGroup::getValue('activity_type', 'SMS Conversation', 'name');

    $this->ChainedSMSTableName = civicrm_api("CustomGroup","getvalue", array ('version' => '3', 'name' =>'Chained_SMS', 'return' =>'table_name'));
    $this->ChainedSMSTableId = civicrm_api("CustomGroup","getvalue", array ('version' => '3', 'name' =>'Chained_SMS', 'return' =>'id'));
    $this->ChainedSMSColumnName = civicrm_api("CustomField","getvalue", array ('version' => '3', 'name' =>'message_template_id', 'custom_group_id' => $this->ChainedSMSTableId, 'return' =>'column_name'));
    $this->LeavingYearGroupTableId = civicrm_api("CustomGroup","getvalue", array ('version' => '3', 'name' =>'Contact_Reference', 'return' =>'id'));
    $this->LeavingYearGroupColumnId = civicrm_api("CustomField","getvalue", array ('version' => '3', 'name' =>'What_year_are_you_in_', 'custom_group_id' => $this->LeavingYearGroupTableId, 'return' =>'id'));
  }

  function setStartDate($startDate){
    $this->startDate = $startDate;
  }

  function setEndDate($endDate){
    $this->endDate = $endDate;
  }

  function setGroups($groups){
    //expects an array of group ids;
    $this->groups = $groups;
  }

  function setCampaign($campaign){
    // TODO - this should be added to SMS and also to the processor so that all SMS are
    // automatically tagged with a campaign.
    // We might also consider adding parent activity ids to all chain SMS
    $this->campaign = $campaign;
  }

  function getLeavingYearGroupById($cid) {
    $result = civicrm_api('Contact', 'getsingle', array(
      'version'    => 3,
      'sequential' => 1,
      'id'         => $cid,
      'return'     => 'custom_' . $this->LeavingYearGroupColumnId,
    ));
    if (civicrm_error($result)) {
      return NULL;
    }
    return $result['custom_' . $this->LeavingYearGroupColumnId];
  }

  function prepare(){
    //create an array that contains a stdClass object for each contact

    foreach($this->groups as $group_id){
      $groupContacts = civicrm_api('GroupContact', 'Get', array('version' => 3, 'rowCount' => '1000000', 'group_id' => $group_id));
      foreach ($groupContacts['values'] as $groupContact){
        $this->contacts[$groupContact['contact_id']] = new CRM_Chainsms_Contact($groupContact['contact_id']);
      }
    }

    //process inbound SMS
    foreach($this->contacts as $contact){

      //for each contact find inbound SMS (in the time period)
      $smsActivitiesQuery = "
        SELECT ca.id,
               ca.activity_date_time,
               ca.details
          FROM civicrm_activity         AS ca
          JOIN civicrm_activity_contact AS cac
            ON cac.activity_id          =  ca.id
           AND cac.record_type_id       =  ".self::RECORD_TYPE_ID_SOURCE."
         WHERE activity_type_id         =  %1
           AND cac.contact_id           =  %2
           AND activity_date_time       BETWEEN %3 AND %4
      ";

      $smsActivitiesParams = array(
        1 => array($this->InboundSMSActivityTypeId, 'Integer'),
        2 => array($contact->id, 'Integer'),
        3 => array($this->startDate, 'String'),
        4 => array($this->endDate, 'String'),
      );

      //remove any contacts without inbound SMS
      $results = CRM_Core_DAO::executeQuery($smsActivitiesQuery, $smsActivitiesParams);
      if(!$results->N){
        unset($this->contacts[$contact->id]);

        //add inbound SMS to the texts array
      }else{
        while($results->fetch()){
          $contact->addText($results->id, 'inbound', $results->activity_date_time, NULL, $results->details);
        }
      }
    }

    //process outbound SMS
    foreach($this->contacts as $contact){

      //foreach contact, get mass SMS that fall within the time period, and the templates these were based on
      $smsActivitiesQuery = "
        SELECT ca.id,
               details,
               ca.activity_date_time,
               cvcs.{$this->ChainedSMSColumnName} AS message_template_id
          FROM civicrm_activity                   AS ca
          JOIN civicrm_activity_contact           AS cac
            ON cac.activity_id                    =  ca.id
           AND cac.record_type_id                 =  ".self::RECORD_TYPE_ID_TARGET."
          JOIN {$this->ChainedSMSTableName}       AS cvcs
            ON cvcs.entity_id                     =  ca.id
         WHERE activity_type_id                   =  %1
           AND cac.contact_id                     =  %2
           AND activity_date_time                 BETWEEN %3 AND %4
      ";

      $smsActivitiesParams = array(
        1 => array($this->OutboundSMSActivityTypeId, 'Integer'),
        2 => array($contact->id, 'Integer'),
        3 => array($this->startDate, 'String'),
        4 => array($this->endDate, 'String'),
      );

      $results = CRM_Core_DAO::executeQuery($smsActivitiesQuery, $smsActivitiesParams);

      while($results->fetch()){
        $contact->addText($results->id, 'outbound', $results->activity_date_time, $results->message_template_id, $results->details);
      }
    }

    //process mass SMS
    foreach($this->contacts as $contact){

      //foreach contact, get mass SMS that fall within the time period, and the templates these were based on
      $smsActivitiesQuery = "
        SELECT ca.id,
               cmt.msg_text,
               ca.activity_date_time,
               cm.msg_template_id
          FROM civicrm_activity         AS ca
          JOIN civicrm_activity_contact AS cac
            ON cac.activity_id          =  ca.id
           AND cac.record_type_id       =  ".self::RECORD_TYPE_ID_TARGET."
          JOIN civicrm_mailing          AS cm
            ON ca.source_record_id      =  cm.id
          JOIN civicrm_msg_template     AS cmt
            ON cm.msg_template_id       =  cmt.id
         WHERE activity_type_id         =  %1
           AND cac.contact_id           =  %2
           AND activity_date_time       BETWEEN %3 AND %4
      ";

      $smsActivitiesParams = array(
        1 => array($this->MassSMSActivityTypeId, 'Integer'),
        2 => array($contact->id, 'Integer'),
        3 => array($this->startDate, 'String'),
        4 => array($this->endDate, 'String'),
      );

      $results = CRM_Core_DAO::executeQuery($smsActivitiesQuery, $smsActivitiesParams);
      while($results->fetch()){
        $contact->addText($results->id, 'outbound', $results->activity_date_time, $results->msg_template_id, $results->msg_text);
      }
    }

    foreach($this->contacts as $contact){
      ksort($contact->texts);
    }

    // ---***--- FF SPECIFIC CODE ---***---
    //
    // In this particular instance (and this is only relevant for FF) if there is no initial outbound SMS, then work out the message template and make one up


    $yearToMessageTemplateInfo = array(
      '' => 83,
      'eleven' => 75,
      'thirteen' => 83,
      'twelve' => 85,
      'Unknown' => 83,
      'unknown' => 83,
      'Year 11' => 75,
      'Year 13' => 83
    );

    foreach($yearToMessageTemplateInfo as $key => $template_id){
      $messageTemplateParams=array('id'=>$template_id);
      $messageTemplateDefaults=array();
      $messageTemplate = CRM_Core_BAO_MessageTemplate::retrieve($messageTemplateParams, $messageTemplateDefaults);
      $yearToMessageTemplateInfo[$key]=array('id' => $template_id, 'text' => $messageTemplate->msg_text);
    }

    $this->contactsWithMissingOutbound = array();
    foreach($this->contacts as $contact){
      $firstText = current($contact->texts);
      if($firstText['direction'] == 'inbound'){

        $this->contactsWithMissingOutbound[]= $contact->id;

        //find out what year they are in, and then find out what message template to use
        $leavingYearGroup = $this->getLeavingYearGroupById($contact->id);
        if ($leavingYearGroup) {
          $contact->addText(-1, 'outbound', -1, $yearToMessageTemplateInfo[$leavingYearGroup]['id'], $yearToMessageTemplateInfo[$leavingYearGroup]['text']);
        } else {
          $this->contactsWithMissingOutboundAndNoYearInfo[] = $contact->id;
        }
      }
    }

	// TODO REFACTOR - can we remove the below?
    //transfer that into the new data structure
    foreach($this->contactsWithMissingOutbound as $c){
      //print_r($this->contacts[$c]);
    }
    //print_r($this->contactsWithMissingOutboundAndNoYearInfo);

    // ---***--- END OF FF SPECIFIC CODE ---***---

    //once all data has been added, clean up the contacts

    foreach($this->contacts as $contact){
      ksort($contact->texts);
    }
  }

  function setTranslatorClass($translatorClass){
    $this->translatorClass = new $translatorClass;
  }

  function translate(){
    foreach ($this->contacts as $contact){
      $this->translatorClass->translate($contact);
    }
  }

  function update(){
    $activitiesCreatedOrUpdated = 0;

    foreach ($this->contacts as $contact){

      // Load the date of their most recent SMS Conversation activity,
      // as well as the date of their last inbound SMS
      $mostRecentActivity = $contact->getMostRecentActivityInfo($this->SMSConversationActivityTypeId, $this->campaign);
      $mostRecentTextDate = $contact->getDate();

      if(
        (strtotime($mostRecentTextDate) > strtotime($mostRecentActivity['date'])) ||
	    ($mostRecentActivity['status_id'] == 1)
	  ) {

      	// Remove previous activities
      	if($mostRecentActivity != NULL) {
      		$contact->deleteCampaignActivities($this->SMSConversationActivityTypeId, $this->campaign);
      	}

	      //params for the new activity
	      $params = array();
	      $params['activity_type_id'] = $this->SMSConversationActivityTypeId;
	      $params['version'] = 3;
	      $params['subject'] = $this->campaign;
	      $params['source_contact_id'] = $contact->id;
	      $params['target_contact_id'] = $contact->id;
	      $params['activity_date_time'] = $contact->getDate();
	      $params['details']  = "TEXTS:\n";

	      //display the texts
	      foreach($contact->texts as $text){
	        $params['details']  .= " -> {$text['direction']}: {$text['text']}\n";
	      }

	      //display the data
	      $params['details'] .= "\nDATA:\n".print_r($contact->data, TRUE);

	      $this->translatorClass->update($contact);

	      if($this->translatorClass->cleanupNecessary($contact)){
	        //If cleanup is necessary, then set the status to scheduled so that people can come in and clean it up,
	        //and also display the errors.
	        $params['status_id'] = 1; //scheduled
	        //display the errors
	        $params['details'] .= "\nERRORS:\n";
	        $params['details'] .= $contact->getErrors()."\n\n";

	      }else{
	        //if no cleanup is necessary, set the activity status to complete
	        $params['status_id'] = 2; //completed

	      }

	      $params['details'] = nl2br($params['details']);
	      $result = civicrm_api("Activity", "create", $params);
        if (civicrm_error($result)) {
          CRM_Core_Error::debug_log_message("ChainSMS Translator update(): could not create SMS Conversation activity, params: " . print_r($params, TRUE) . ", result: " . print_r($result, TRUE));
        }
        $activitiesCreatedOrUpdated++;
      }
    }

    return $activitiesCreatedOrUpdated;
  }

  public static function getTranslatorClasses(){
    $extensionsDirResult = civicrm_api3('Setting', 'get', array(
      'sequential' => 1,
      'return' => 'extensionsDir',
    ));

    $translatorsFilepaths = scandir($extensionsDirResult['values'][0]['extensionsDir']
        . "/org.thirdsectordesign.chainsms/CRM/Chainsms/Translator");

    $filesToRemove = array('AbstractTranslator.php', 'TranslatorInterface.php', '.', '..');

    foreach($filesToRemove as $fileToRemove){
      foreach(array_keys($translatorsFilepaths, $fileToRemove) as $key){
        unset($translatorsFilepaths[$key]);
      }
    }

    // Now we build $translators array in the form key => user friendly description
    // 'EmailAddressTranslator' => 'Turns inbound SMS messages into Email Addresses'
    $translators = array();

    foreach($translatorsFilepaths as $key => $translatorFilepath){
      // check the file ends in .php, or bail
      if (stripos(strrev($translatorFilepath), 'php.') !== 0){
        continue;
      }

      $classKey = substr($translatorFilepath, 0, -4); // remove .php
      $translatorClassName = 'CRM_Chainsms_Translator_' . $classKey;
      $translators[] = array(
        'value' => $translatorClassName,
        'name' => $translatorClassName::getName(),
        'description' => $translatorClassName::getDescription(),
      );
    }

    return $translators;
  }
}
