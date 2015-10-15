<?php

/**
 * EmailAddressTranslator attaches a single valid email address to a contact.
 * 
 *
 * @author john
 */
class CRM_Chainsms_Translator_EmailAddressTranslator extends CRM_Chainsms_Translator_AbstractTranslator implements CRM_Chainsms_Translator_TranslatorInterface{
  /*
   * User friendly name for what's translated by the Email Address Translator.
   */
  public static function getName(){
    return 'Email Addresses';
  }
  
  /*
   * User friendly description of Email Address Translator.
   */
  public static function getDescription(){
    return 'This translator will take an inbound message containing valid email '
      . 'addresses email address, and add them to the contact that sent it.';
  }
  
  /*
   * Processes each interaction. 
   * 1. Check that they haven't sent us any naughty words.
   * 2. See if the email address is a valid one.
   * 3. If so add it (unsetting the bounce flag).
   */
  function process($interaction){
    $rawResponse = $interaction['inbound']['text'];

    // split on commas, spaces, and exclamations. Remove full stops later.
    $responsePieces = preg_split('/[,\ !&\/]/', $rawResponse);

    foreach($responsePieces as $responsePiece){
      $responsePiece = rtrim($responsePiece, '.');
      if (filter_var($responsePiece, FILTER_VALIDATE_EMAIL)){
        $this->contact->data['newEmailAddresses'][] = $responsePiece;
      }
    }
    
    // Error if no valid email addreses found.
    if (empty($this->contact->data['newEmailAddresses'])){
      $this->contact->addError('No valid email address identified', '');
    }
  }
  
  public function translate($contact){      
    $this->contact = $contact;
    
    //create an empty array for the data
    $this->contact->data = array();

    //check for bad words
    $this->checkForBadWords();

    //process each interaction
    reset($this->contact->texts);
    while ($interaction = $this->getInteraction()){
      $this->process($interaction);
    }
  }
  
  public function update($contact){
    // Update email addresses, only add a new one if that email address doesn't 
    // already exist. Bear in mind that users can have duplicate email addresses,
    // either one of which may be flagged as being on hold.
    foreach($contact->data['newEmailAddresses'] as $emailAddress){
      $getEmailAddressParam = array(
        'version' => 3,
        'contact_id' => $contact->id,
        'email' => $emailAddress,
      );

      $existingEmailAddresses = civicrm_api('Email', 'get', $getEmailAddressParam);

      if ($existingEmailAddresses['count'] > 0){
        foreach($existingEmailAddresses['values'] as $existingEmailAddress){
          $updateEmailParam = array(
            'version' => 3,
            'id' => $existingEmailAddress['id'],
            'on_hold' => 0,
          );
        
          civicrm_api('Email', 'create', $updateEmailParam);
        }
      }
      else {      
        $createEmailParam = array(
          'version' => 3,
          'contact_id' => $contact->id,
          'email' => $emailAddress,
          'on_hold' => 0,
        );
        
        civicrm_api('Email', 'create', $createEmailParam);
      }
    }
  }
}