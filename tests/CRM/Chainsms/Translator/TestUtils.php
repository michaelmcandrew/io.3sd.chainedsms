<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of TranslatorTestUtils
 *
 * @author john
 */
class CRM_Chainsms_Translator_TestUtils {

  public static function createContactWithMailing($firstName, $lastName, $inboundResponse) {
    // init dates
    $dateArray = getdate();
    $startDate = $dateArray['year'] . '-' . $dateArray['mon'] . '-' . $dateArray['mday'];
    $endDate = $dateArray['year'] . '-' . $dateArray['mon'] . '-' . ($dateArray['mday'] + 1);

    // create the contact
    $createContactApiParams = array(
      'version' => 3,
      'sequential' => 1,
      'contact_type' => 'Individual',
      'contact_sub_type' => 'student',
      'custom_21' => 8123,
      'first_name' => $firstName,
      'last_name' => $lastName,
    );
    $createContactApiResults = civicrm_api('Contact', 'create', $createContactApiParams);

    // add the phone
    $createPhoneApiParams = array(
      'version' => 3,
      'sequential' => 1,
      'contact_id' => $createContactApiResults['id'],
      'phone' => '07000000000',
    );
    $createPhoneApiResults = civicrm_api('Phone', 'create', $createPhoneApiParams);

    // create the group
    $createGroupApiParams  = array(
      'version' => 3,
      'sequential' => 1,
      'title' => 'SMS Translation Unit Test',
    );
    $createGroupApiResults = civicrm_api('Group', 'create', $createGroupApiParams);

    //echo 'group deets ' . print_r($createGroupApiResults, TRUE);

    $addContactToGroupApiParams = array(
      'version' => 3,
      'sequential' => 1,
      'group_id' => $createGroupApiResults['id'],
      'contact_id' => $createContactApiResults['id'],
    );

    $addContactToGroupApiResult = civicrm_api3('GroupContact', 'create', $addContactToGroupApiParams);

    // create mailing
    $createSmsMailingApiParams = array(
      'domain_id' => '1',
      'name' => 'Good Luck SMS Y13 - August 2015c',
      'from_name' => 'FutureFirstSMS',
      'from_email' => 'networks@futurefirst.org.uk',
      'replyto_email' => 'networks@futurefirst.org.uk',
      'body_text' => 'Hi {contact.first_name}! We just wanted to wish you the best of luck with your exam results tomorrow. All the best, {alumni.school_name_for_text}.',
      'url_tracking' => '1',
      'forward_replies' => '0',
      'auto_responder' => '0',
      'open_tracking' => '1',
      'is_completed' => '1',
      'msg_template_id' => '10',
      'override_verp' => '1',
      'created_id' => '141032',
      'created_date' => '2015-08-12 15:19:36',
      'scheduled_date' => '2015-08-12 15:19:36',
      'approver_id' => '1',
      'approval_date' => '2015-08-12 15:19:36',
      'approval_status_id' => '1',
      'is_archived' => '0',
      'visibility' => 'User and User Admin Only',
      'dedupe_email' => '0',
      'sms_provider_id' => '1',
      'subject' => 'SMS Unit Test',
    );

    $createSmsMailingApiResult = civicrm_api3('Mailing', 'create', $createSmsMailingApiParams);

    // add Mass SMS activity
    $createMassSmsActivityParams = array(
      'source_record_id' => '1872',
      'activity_type_id' => '45', // TODO won't be this on everyone's version
      'subject' => 'Test redacted mass SMS',
      'activity_date_time' => '2015-08-12 17:16:03',
      'details' => '',
      'status_id' => '2',
      'priority_id' => '2',
      'is_test' => '0',
      'is_auto' => '0',
      'is_current_revision' => '1',
      'is_deleted' => '0',
      'source_contact_id' => '35900',
      'target_id' => $createContactApiResults['id'],
    );

    $createMassSmsActivityResult = civicrm_api3('Activity', 'create', $createMassSmsActivityParams);

    // add Outbound SMS activity
    $createOutboundMessageApiParams = array(
      'source_record_id' => '1872',
      'activity_type_id' => '4', // TODO won't be this on everyone's version
      'subject' => 'Test redacted mass SMS',
      'activity_date_time' => '2015-08-12 17:16:04',
      'details' => '',
      'status_id' => '2',
      'priority_id' => '2',
      'is_test' => '0',
      'is_auto' => '0',
      'is_current_revision' => '1',
      'is_deleted' => '0',
      'source_contact_id' => '35900',
      'target_id' => $createContactApiResults['id'],
    );

    $createOutboundMessageApiResult = civicrm_api3('Activity', 'create', $createOutboundMessageApiParams);

    // add Inbound activity
    $createInboundMessageApiParams = array(
      'activity_type_id' => '46',
      'activity_date_time' => '2015-08-12 17:26:09',
      'phone_number' => '0700000000',
      'details' => $inboundResponse,
      'status_id' => '2',
      'priority_id' => '2',
      'is_test' => '0',
      'is_auto' => '0',
      'is_current_revision' => '1',
      'is_deleted' => '0',
      'source_contact_id' => $createContactApiResults['id'],
    );

    $createInboundMessageApiResult = civicrm_api3('Activity', 'create', $createInboundMessageApiParams);

    return array(
      'contact_id' => $createContactApiResults['id'],
      'group_id' => $createGroupApiResults['id'],
    );
  }
  
  public static function assertBrokenConversationActivity()
  {
    // TODO
  }
}
