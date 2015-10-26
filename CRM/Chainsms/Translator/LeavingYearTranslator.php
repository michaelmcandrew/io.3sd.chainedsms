<?php

/**
 * This file contains some Future First specific constant values. It is included
 * for reference for other organisations attempting more sophisticated surveys.
 */
class CRM_Chainsms_Translator_LeavingYearTranslator extends CRM_Chainsms_Translator_AbstractTranslator implements CRM_Chainsms_Translator_TranslatorInterface {

  CONST FF_SCHOOL_RELATIONSHIP_TYPE_ID = 21;

  /*
   * User friendly name for what's translated, i.e. 'Email Addresses'.
   */

  public static function getName() {
    return 'Future First Leaving Years';
  }

  /*
   * User friendly description of the Translator Class, i.e. 'This translator
   * will take an inbound message containing valid email addresses email address,
   * and add them to the contact that sent it.'.
   */

  public static function getDescription() {
    return 'Turns a single number leaving year into a date for the Leaving Year field. Future First only.';
  }

  /*
   * The translation for each contact.
   */

  public function process($interaction) {
    $rawResponse = $interaction['inbound']['text'];

    // Can only allow one leaving year! Check there's only one number in whatever we split.
    $responsePieces = preg_split('/[,\ !&\/]/', $rawResponse);

    // split on commas, spaces, and exclamations. Remove full stops later.
    $numberOfIntegers = 0;

    foreach ($responsePieces as $responsePiece) {
      $responsePiece = rtrim($responsePiece, '.');
      if (is_numeric($responsePiece)) {
        $this->contact->data['newLeavingYear'] = $responsePiece;
        $numberOfIntegers++;
      }
    }

    // Error if no valid email addreses found.
    if ($numberOfIntegers == 0) {
      $this->contact->addError('No valid leaving year identified', '');
    } elseif ($numberOfIntegers > 1) {
      $this->contact->addError('More than one potential leaving year identified', '');
    }
  }

  public function translate($contact) {
    $this->contact = $contact;

    //create an empty array for the data
    $this->contact->data = array();

    //check for bad words
    $this->checkForBadWords();

    //process each interaction
    reset($this->contact->texts);
    while ($interaction = $this->getInteraction()) {
      $this->process($interaction);
    }
  }

  /*
   * Update each contact with the translated data.
   */

  public function update($contact) {
    $newLeavingYear = $contact->data['newLeavingYear'] . '-01-01';

    echo "examining date " . $newLeavingYear;
    if (DateTime::createFromFormat('Y-m-d', $newLeavingYear) == false) {
      $contact->addError('Invalid date' . $contact->data['newLeavingYear'], '');
      return;
    }

    $getContactSchoolResults = civicrm_api('Contact', 'getValue', array(
      'version' => 3,
      'contact_type' => 'Individual',
      'contact_sub_type' => 'student',
      'id' => $contact->id,
      'returnValue' => 'custom_21',
    ));

    $schoolId = $getContactSchoolResults['custom_21'];

    // Update the contact custom data
    $setLeavingYearCustomFieldResult = civicrm_api('Contact', 'create', array(
      'version' => 3,
      'id' => $contact->id,
      'custom_32' => $newLeavingYear,
      'contact_type' => 'Individual',
      'contact_sub_type' => 'Student'
    ));

    // Get the relationships between the student and the school, update the leaving year on all of them.
    $relationshipsBetweenContactAndSchool = civicrm_api('Relationship', 'get', array(
      'version' => 3,
      'contact_id_a' => $contact->id,
      'contact_id_b' => $schoolId,
      'relationship_type_id' => self::FF_SCHOOL_RELATIONSHIP_TYPE_ID,
      'is_active' => true,
    ));

    foreach ($relationshipsBetweenContactAndSchool['values'] as $eachRelationship) {
      // Update relationship custom data
      civicrm_api('Relationship', 'create', array(
        'version' => 3,
        'id' => $eachRelationship['id'],
        'custom_111' => $newLeavingYear,
        'relationship_type_id' => self::FF_SCHOOL_RELATIONSHIP_TYPE_ID,
      ));
    }
  }

}
