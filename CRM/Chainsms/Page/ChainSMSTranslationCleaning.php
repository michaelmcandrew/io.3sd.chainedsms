<?php

require_once 'CRM/Core/Page.php';

CONST ACTIVITY_STATUS_SCHEDULED_VALUE = 1; // TODO get rid of hardcoded status index

class CRM_Chainsms_Page_ChainSMSTranslationCleaning extends CRM_Core_Page {
  // record_type_id values in civicrm_activity_contact for a record corresponding to:
  const RECORD_TYPE_ID_SOURCE = 2; // source_contact_id
  const RECORD_TYPE_ID_TARGET = 3; // target_contact_id

  function init(){
  	$this->OutboundSMSActivityTypeId = CRM_Core_OptionGroup::getValue('activity_type', 'SMS', 'name');
    $this->MassSMSActivityTypeId = CRM_Core_OptionGroup::getValue('activity_type', 'Mass SMS', 'name');
    $this->InboundSMSActivityTypeId = CRM_Core_OptionGroup::getValue('activity_type', 'Inbound SMS', 'name');
    // TODO REFACTOR is SMSDeliveryActivityTypeId used anywhere?
    $this->SMSDeliveryActivityTypeId = CRM_Core_OptionGroup::getValue('activity_type', 'SMS Delivery', 'name');
    $this->SMSConversationActivityTypeId = CRM_Core_OptionGroup::getValue('activity_type', 'SMS Conversation', 'name');
  }	
	
  function getDistinctTranslationCampaigns(){
  	$sqlDistinctTranslationCampaigns = "
      SELECT 
        DISTINCT(subject) AS subject
      FROM 
        civicrm_activity 
      WHERE 
        is_deleted != '1' AND 
        status_id = '1' AND 
        activity_type_id = ".$this->SMSConversationActivityTypeId; 

    $dao = CRM_Core_DAO::executeQuery($sqlDistinctTranslationCampaigns);
	
	$aDistinctCampaigns = array();
	
	while ( $dao->fetch() ){
	  $aDistinctCampaigns[] = $dao->subject;
	}
	
	return $aDistinctCampaigns;
  }

  function getInvalidSMSConversationInCampaign($sCampaignName, $sFilter, $offset){
  	$sFilterString = "";
	if ($sFilter != ""){
	  $sFilterString = " details LIKE '%$sFilter%' AND ";
	}	
		
  	$sqlGetInvalidConversation = "
      SELECT 
        ca.id AS id, 
        cac.contact_id AS source_contact_id, 
        activity_date_time, 
        subject, 
        details  
      FROM 
        civicrm_activity AS ca
      JOIN
        civicrm_activity_contact AS cac
        ON  cac.activity_id    = ca.id
        AND cac.record_type_id = ".self::RECORD_TYPE_ID_SOURCE."
      WHERE 
        is_deleted != '1' AND 
        subject = '".$sCampaignName."' AND
        status_id = '".ACTIVITY_STATUS_SCHEDULED_VALUE."' AND ".$sFilterString."
        activity_type_id = ".$this->SMSConversationActivityTypeId." 
        LIMIT 1 OFFSET ".$offset;
	
	$dao = CRM_Core_DAO::executeQuery($sqlGetInvalidConversation);
	
	while ( $dao->fetch() ){
	  return array(
	    'id' => $dao->id, 
	    'source_contact_id' => $dao->source_contact_id, 
	    'subject' => $dao->subject, 
	    'activity_date_time' => $dao->activity_date_time,
	    'details' => $dao->details, 
	  );
	}
  }

  function getPreviousMassSMSActivity($iContactId, $sBeforeDate){
  	$sqlGetPreviousMassSMSActivity = "
      SELECT 
        ca.id AS id,
        subject, 
        activity_date_time, 
        details 
      FROM 
        civicrm_activity AS ca
      JOIN
        civicrm_activity_contact AS cac
        ON  cac.activity_id    = ca.id
        AND cac.record_type_id = ".self::RECORD_TYPE_ID_TARGET."
      WHERE 
        is_deleted != '1' AND 
        activity_type_id = ".$this->MassSMSActivityTypeId." AND 
        activity_date_time < '".$sBeforeDate."' AND 
        cac.contact_id = ".$iContactId." 
      ORDER BY 
        activity_date_time DESC 
      LIMIT 
        1";

	$dao = CRM_Core_DAO::executeQuery($sqlGetPreviousMassSMSActivity);
	
	while ( $dao->fetch() ){
	  return array(
	    'id' => $dao->id,
	    'subject' => $dao->subject,
	    'activity_date_time' => $dao->activity_date_time,
	    'details' => $dao->details,
	  );
	}
	
  }
  
  function getSMSMessagesBetween($iContactId, $sFromDate, $sToDate){
  	$sqlGetSMSMessagesBetween = "
      SELECT 
        ca.id AS id,
        subject, 
        activity_date_time, 
        details, 
        activity_type_id 
      FROM 
        civicrm_activity AS ca
      JOIN
        civicrm_activity_contact AS cac
        ON  cac.activity_id    = ca.id
        AND cac.record_type_id = ".self::RECORD_TYPE_ID_TARGET."
      WHERE 
        is_deleted != '1' AND 
        (activity_type_id = ".$this->OutboundSMSActivityTypeId." OR
        activity_type_id = ".$this->InboundSMSActivityTypeId.") AND
        activity_date_time >= '".$sFromDate."' AND 
        activity_date_time <= DATE_ADD('".$sToDate."', INTERVAL 1 WEEK) AND 
        cac.contact_id = ".$iContactId;
        
	$dao = CRM_Core_DAO::executeQuery($sqlGetSMSMessagesBetween);
	
	$aResults = array();
	
	while ( $dao->fetch() ){
	  
	  $sDirection = "";	
		
	  if($dao->activity_type_id == $this->OutboundSMSActivityTypeId){
	  	$sDirection = 'Outbound';
	  } else if ($dao->activity_type_id == $this->InboundSMSActivityTypeId) {
	  	$sDirection = 'Inbound';
	  }	
		
	  $aResults[]= array(
	    'id' => $dao->id,
	    'direction' => $sDirection, 
	    'subject' => $dao->subject, 
	    'activity_date_time' => $dao->activity_date_time, 
	    'details' => $dao->details
	  );
	}
		
	return array_reverse($aResults);
  }

  function countInvalidSMSConversations($sCampaignName, $sFilter){
  	
  	$sFilterString = "";
	if ($sFilter != ""){
	  $sFilterString = " details LIKE '%$sFilter%' AND ";
	}			
  		
  	$sqlGetSMSConversationCount = "
  	  SELECT 
  	    COUNT(id) as count
	  FROM
	    civicrm_activity
	  WHERE 
	    is_deleted = 0 AND 
	    subject = '".$sCampaignName."' AND ".$sFilterString."
		status_id=" . ACTIVITY_STATUS_SCHEDULED_VALUE;
	
	$dao = CRM_Core_DAO::executeQuery($sqlGetSMSConversationCount);
	
	while($dao->fetch()){
	  return $dao->count; 
	}
  }
  
  function run() {
  	$this->init();
	
  	// initialise parameters
	$sCleanedCampaignName = mysql_real_escape_string($_GET['campaign']);
	$sFilter = mysql_real_escape_string($_GET['filter']);
	
	if ($sFilter == "unset"){
	  $sFilter = "";
	}
	
	if (isset($_GET['offset'])){
      $iOffset = intval($_GET['offset']);
	  // TODO check that offset is not higher than the number of incorrect ones in this campaign, and > 0
	} else {
	  $iOffset = 0;	
	}
	
	$this->assign('sPageNum', $iOffset+1);
	
	// get a list of distinct campaigns
    $aDistinctCampaigns = $this->getDistinctTranslationCampaigns();
    $this->assign('aDistinctCampaigns', $aDistinctCampaigns);
    
	// if a campaign has been selected, produce the interface to fix one broken conversation
	if ($sCleanedCampaignName){
      $bError = FALSE;	
		
	  $this->assign('sCampaignName', $sCleanedCampaignName);
	  $this->assign('sFilter', $sFilter);
	  $this->assign('offset', $iOffset);
	  	
	  $iInvalidConversationCount = $this->countInvalidSMSConversations($sCleanedCampaignName, $sFilter);
	  
      // tell the user how many there are
	  $this->assign("iCountInvalidSMSConversations", $iInvalidConversationCount);
	  
	  if($iOffset >= $iInvalidConversationCount){
	  	$bError = TRUE;
        $this->assign('sErrorMsg', "There are no more invalid SMS Conversations in this campaign with that filter.");	
	  }
	  
	  if (!$bError){
	    $aSMSConversationActivity = $this->getInvalidSMSConversationInCampaign($sCleanedCampaignName, $sFilter, $iOffset);
	  
	    $this->assign('aSMSConversationActivity', $aSMSConversationActivity);
	  
	    $aMassSMSActivity = $this->getPreviousMassSMSActivity($aSMSConversationActivity['source_contact_id'], $aSMSConversationActivity['activity_date_time']);
        $this->assign('aMassSMSActivity', $aMassSMSActivity);
	  
	    $aSMSMessages = $this->getSMSMessagesBetween(
	      $aSMSConversationActivity['source_contact_id'], 
	      $aMassSMSActivity['activity_date_time'], 
	      $aSMSConversationActivity['activity_date_time']
	    );
		
        $aAllGroupMembership = civicrm_api("GroupContact","get", array ('version' => '3','sequential' =>'1', 'status' =>'added', 'return' =>'group_id', 'contact_id' => $aSMSConversationActivity['source_contact_id'], 'rowCount' => 0));
		
		$aAllGroupNames = array();
		
		foreach($aAllGroupMembership['values'] as $aEachGroupMembership){
			$aGroupMembership = civicrm_api("Group","get", array ('version' => '3','sequential' =>'1', 'status' =>'added', 'id' => $aEachGroupMembership['group_id'], 'return' => 'title'));
		 	$aAllGroupNames[] = $aGroupMembership['values'][0]['title']; 
		}
		
		$this->assign("sGroupMembership", implode("<br/>" , $aAllGroupNames));
		
        $this->assign('aSMSMessages', $aSMSMessages);
	  }
    }
   
   
    parent::run();
  }
}
