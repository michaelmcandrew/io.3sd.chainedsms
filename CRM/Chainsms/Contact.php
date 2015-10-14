<?php
class CRM_Chainsms_Contact{

  function __construct($id){
    $this->id = $id;
    $this->errors = array();
  }

  function addText($activity_id, $type, $date, $msg_template_id = NULL, $text = NULL){
    $this->texts[$activity_id] = array(
      'id' => $activity_id,
      'direction' => $type,
      'date' => $date,
      'msg_template_id' => $msg_template_id,
      'text' => $text
    );
  }
  
  function getErrors(){
    $errorText = '';
    foreach($this->errors as $error){
      $errorText .= "{$error['text']} ({$error['type']})\n";
    }
    return $errorText;
  }  

  function addError($text = NULL, $type = 'error'){
    $this->errors[] = array(
      'text' => $text,
      'type' => $type,
    );
  }

  function getDate(){
    $mostRecent= 0;
    
    foreach($this->texts as $text){
      if($text['direction'] == "inbound") {
        $curDate = strtotime($text['date']);
        if($curDate > $mostRecent) {
          $mostRecent = $curDate;
        }
      }
    }
    
    return date('Y-m-d H:i:s', $mostRecent);
  }
  
  function getMostRecentActivityInfo($activity_id, $campaign) {
  
  	$activities = $this->getExistingActivities($activity_id, $campaign);
  	
  	if(count($activities) == 0) {
  		return NULL;
  	}
  	
	$mostRecentActivity = array();
  	$mostRecentActivity['date'] = NULL;
	$mostRecentActivity['status_id'] = NULL;
	
  	foreach($activities["values"] as $value) {
  		if(
  			$mostRecentActivity['date'] == NULL ||
  			strtotime($value["activity_date_time"]) > strtotime($mostRecentActivity['date'])
  		) {
  			$mostRecentActivity['date'] = $value["activity_date_time"];
			$mostRecentActivity['status_id'] = $value['status_id'];
  		}
  	}
  
  	return $mostRecentActivity;
  }
  
  function getExistingActivities($activity_id, $campaign) {
  
  	//get any previous activities for this campaign
  	$params = array();
  	$params['activity_type_id'] = $activity_id;
  	$params['version'] = 3;
  	$params['subject'] = $campaign;
  	$params['source_contact_id'] = $this->id;
  	$params['target_contact_id'] = $this->id;
  	
  	$activities = civicrm_api("Activity", "get", $params);
  
  	return $activities;
  }
  
  function deleteCampaignActivities($activity_id, $campaign) {
  	 
  	$activities = $this->getExistingActivities($activity_id, $campaign);
  	 
  	foreach($activities["values"] as $value) {
  		$deleteParams = array(
  			'version' => 3,
  			'id' => $value["id"],
  		);
  		civicrm_api("Activity", "delete", $deleteParams);
  	}
  
  }
}
