<?php

require_once 'CRM/Core/Page.php';

class CRM_Chainsms_Page_ChainSMSTranslate extends CRM_Core_Page {
  function getGroupsForMailing($iMailingId){
  	$sqlGetMailingGroups = "
  	  SELECT entity_id
  	  FROM civicrm_mailing_group
  	  WHERE mailing_id=".$iMailingId;

	$dao = CRM_Core_DAO::executeQuery($sqlGetMailingGroups);

	$aMailingGroups = array();

	while($dao->fetch()){
		$aMailingGroups[] = $dao->entity_id;
	}

	return implode(",", $aMailingGroups);
  }

  function run() {

// TODO REFACTOR on later versions of civicrm this shouldn't be needed, just use the api instead

    $sqlGetSMSMailings = "
      SELECT
        id,
        name,
        DATE(scheduled_date) AS send_date,
        DATE_ADD(DATE(scheduled_date), INTERVAL 2 DAY) AS limit_date
      FROM
        civicrm_mailing
      WHERE
        sms_provider_id = 1
    ";

    $dao = CRM_Core_DAO::executeQuery($sqlGetSMSMailings);

    $aSMSMailings = array();

	while($dao->fetch()){
      $aSMSMailings[]= array(
        'id' => $dao->id,
        'name' => $dao->name,
        'send_date' => $dao->send_date,
        'limit_date' => $dao->limit_date,
        'group_ids' => $this->getGroupsForMailing($dao->id),
	  );
	}

    $this->assign('aSMSMailings', $aSMSMailings);

    $aGroups = civicrm_api("Group", "get", array ('version' => '3','sequential' =>'1', 'rowCount' => 0));

    $aGroupsToPass =  array();
    foreach ($aGroups['values'] as $aEachGroup) {
      $aGroupToPass = array();
      $aGroupToPass['id'] = $aEachGroup['id'] ;
      $aGroupToPass['title'] = $aEachGroup['title'];
      $aGroupsToPass[] = $aGroupToPass;
    }
    
    $this->assign('aGroups', $aGroupsToPass);
    
    $this->assign('aTranslationOptions', CRM_Chainsms_Translator::getTranslatorClasses());
    
    parent::run();
  }
}
