<?php

/**
 * GenderTranslator sets the gender ID for a contact.
 *
 * @author "David Knoll" <david@futurefirst.org.uk>
 */
class CRM_Chainsms_Translator_GenderTranslator extends CRM_Chainsms_Translator_AbstractTranslator implements CRM_Chainsms_Translator_TranslatorInterface{
  function __construct() {
    $gender_result = civicrm_api('OptionValue', 'get', array(
      'version'    => 3,
      'sequential' => 1,
      'is_active'  => 1,
      'option_group_name' => 'gender',
    ));

    $this->genders = array();
    foreach ($gender_result['values'] as $gender) {
      $this->genders[(int) $gender['value']] = self::normaliseLabel($gender['label']);
    }

    $this->female_alt = array('woman', 'lady', 'girl', 'lass');
    $this->male_alt   = array('man', 'gentleman', 'boy', 'lad', 'guy', 'bloke');
  }

  /**
   * Strip punctuation, control characters, digits and leading and trailing whitespace,
   * and convert to all lower case.
   *
   * @param string $str
   * @return string
   */
  static function normaliseLabel($str) {
    return preg_replace("/[[:punct:][:cntrl:][:digit:]]/", '', strtolower(trim($str)));
  }

  /**
   * User friendly name for what's translated by the Gender Translator.
   *
   * @return string
   */
  public static function getName() {
    return ts('Genders');
  }

  /**
   * User friendly description of Gender Translator.
   *
   * @return string
   */
  public static function getDescription() {
    return 'This translator will take an inbound message containing a gender '
      . 'option (as configured by your organisation), and update the gender '
      . 'of the contact that sent it.';
  }

  /*
   * Processes each interaction.
   * 1. Check that they haven't sent us any naughty words.
   * 2. See if the gender is a valid one.
   * 3. If so add it.
   */
  function process($interaction) {
    $rawResponse = $interaction['inbound']['text'];

    // If any configured gender options have spaces in them, try a substring match
    $normResponse = self::normaliseLabel($rawResponse);
    foreach ($this->genders as $gender_id => $gender_label) {
      if (strpos($gender_label, ' ') === FALSE) {
        continue;
      }
      if (strpos($normResponse, $gender_label) !== FALSE) {
        $this->contact->data['gender_id'] = $gender_id;
        break;
      }
    }

    // Split on commas, spaces, and exclamations. Take the first match.
    if (empty($this->contact->data['gender_id'])) {
      $responsePieces = preg_split('/[,\ !&\/]/', $rawResponse);
      foreach ($responsePieces as $responsePiece) {
        $responsePiece = self::normaliseLabel($responsePiece);

        // Check if they have said something other than 'male' or 'female'
        // but close enough in meaning to 'male' or 'female'.
        if (in_array($responsePiece, $this->female_alt)) {
          $responsePiece = 'female';
        }
        if (in_array($responsePiece, $this->male_alt)) {
          $responsePiece = 'male';
        }

        $gender_id = array_search($responsePiece, $this->genders);
        if ($gender_id) {
          $this->contact->data['gender_id'] = $gender_id;
          break;
        }
      }
    }

    // Error if no gender option found.
    if (empty($this->contact->data['gender_id'])) {
      $this->contact->addError('No gender option identified', '');
    }
  }

  public function translate($contact) {
    $this->contact = $contact;

    // create an empty array for the data
    $this->contact->data = array();

    // check for bad words
    $this->checkForBadWords();

    // process each interaction
    reset($this->contact->texts);
    while ($interaction = $this->getInteraction()) { // Assignment in conditional!
      $this->process($interaction);
    }
  }

  public function update($contact) {
    // Update gender ID on the contact, if the response was found to match
    // one of the organisation's options.
    if (!empty($contact->data['gender_id'])) {
      $update_result = civicrm_api('Contact', 'create', array(
        'version'   => 3,
        'id'        => $contact->id,
        'gender_id' => $contact->data['gender_id'],
      ));
    }
  }
}
