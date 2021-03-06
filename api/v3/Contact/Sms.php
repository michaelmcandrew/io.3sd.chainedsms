<?php

/**
 * Send a single SMS to a contact.
 *
 * @param  array   $params   input parameters
 *
 * Allowed @params array keys are:
 * {int     id     			id of the contact that you want to SMS}
 * {int     text	    	the text of the SMS that you want to send}
 *
 * @return array  API Result Array
 *
 * @static void
 * @access public
 */

function civicrm_api3_contact_sms($params) {

	//This API makes everything nice for / wraps around
	//CRM_Activity_BAO_Activity::sendSMS()

	//it would be nice to be able to chain this by sending the results of a Contact.get

	//get the list of contacts that you want to send the SMS to

    //for some reason, CRM_Activity_BAO_Activity::sendSMS wants $contactDetails AND $contactIds. I'm pretty sure it could work out the contact IDs from $contactDetails, but lets not worry about that.
    if(isset($params['contact_id'])){
        $contactsResult = civicrm_api('Contact', 'get', array('version'=>3, 'id' => $params['contact_id']));

        if(!$contactsResult['count']){
            return civicrm_api3_create_error('Please specify at least one contact.');
        }
        $contactDetails = $contactsResult['values'];
        //idea is that this contact will take a contact ID and a text message and then send an SMS

        foreach($contactDetails as $contact){
            $contactIds[]=$contact['contact_id'];
        }
	}elseif(isset($params['group_id'])){
        $groupContactsResult = civicrm_api('GroupContact', 'get', array('version'=>3, 'group_id' => $params['group_id'], 'option.limit' => 1000000)); // This will break if you try and SMS more than one million people :)
        $contactDetails = $groupContactsResult['values'];
        //idea is that this contact will take a contact ID and a text message and then send an SMS

        foreach($contactDetails as $key => $contact){
            $contactDetails[$key] = civicrm_api('Contact', 'getsingle', array('version'=>3, 'id' => $contact['contact_id']));
            $contactIds[]=$contact['contact_id'];
        }
    }else{
        return civicrm_api3_create_error('You should include either a contact_id or group_id in your params');
    }
    // use the default SMS provider
	$providers=CRM_SMS_BAO_Provider::getProviders(NULL, array('is_default' => 1));
	$provider = $providers[0];
	$provider['provider_id'] = $provider['id'];

	//this should be set somehow when not set (or maybe we need to change the underlying BAO to not require it?)
	$userID=1;
	if(isset($params['msg_template_id'])){
        $messageTemplateParams=array('id'=>$params['msg_template_id']);
        $messageTemplateDefaults=array();
        $messageTemplate = CRM_Core_BAO_MessageTemplate::retrieve($messageTemplateParams, $messageTemplateDefaults);
        $activityParams['sms_text_message']=$messageTemplate->msg_text;
    }elseif(isset($params['text'])){
        $activityParams['sms_text_message']=$params['text'];
    }else{
        return civicrm_api3_create_error('You should include either text or a msg_template_id');
    }

	$sms = CRM_Activity_BAO_Activity::sendSMS($contactDetails, $activityParams, $provider, $contactIds, $userID);
    $created_activity = civicrm_api('Activity', 'get', array('version' => 3, 'id' => $sms[1], 'debug' => 1));
   if(!$created_activity['count']){
     return civicrm_api3_create_success();
   }
    //record the message template ID if this was sent using a message template
    if($params['msg_template_id']){

      //TODO record message template used
      $params[1] = array($created_activity['id'], 'Integer');
      $params[2] = array($params['msg_template_id'], 'Integer');
      $query = "INSERT INTO civicrm_chainedsms_outbound_template SET activity_id = %1, msg_template_id = %2";
      $result = CRM_Core_DAO::executeQuery($query, $params);
    }

    return civicrm_api3_create_success($created_activity['values'], $params, 'Contact', 'sms');
}
