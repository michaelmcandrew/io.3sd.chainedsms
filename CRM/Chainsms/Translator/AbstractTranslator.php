<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of AbstractTranslator
 *
 * @author john
 */
abstract class CRM_Chainsms_Translator_AbstractTranslator {

  protected function getInteraction(){
    //check that the first text is outbound

    $firstText = current($this->contact->texts);
    if($firstText['direction'] != 'outbound'){
      return FALSE;
    }else{
      $interaction['outbound'] = $firstText;
    }
    $secondText = next($this->contact->texts);
    //if we have fallen off the end of the array, that is because there are no more texts left.
    //That could be fine (if we said thankyou and they didn't reply),
    //or it could be a problem, i.e. an incomplete text.
    //Below, we check for both cases.

    if($secondText == FALSE){
      //If the second text does not exist
      if($firstText['msg_template_id'] == 80){
        //If this is a thankyou text so that is fine.
        $interaction['inbound'] = NULL;
      }else{
        //Else this is an incomplete interaction - record an error and return FALSE
        $this->contact->addError('Did not reply to text', 'incomplete');
        return FALSE;
      }
    }elseif($secondText['direction']=='inbound'){
      //if the next text is an inbound text, all is as expected, so record this inbound text
      $interaction['inbound'] = $secondText;
      //and advance the pointer ready for the next interaction grab
      next($this->contact->texts);
    }else{
      //else stop grabbing the interaction (TODO record this as an error)
      return FALSE;
    }
    return $interaction;
  }

  function cleanupNecessary($contact){
    foreach($contact->errors as $error){
      if(in_array($error['type'], array('error'))){
        return TRUE;
      }
    }
    if(!count($contact->data)){
      return TRUE;
    }
    return FALSE;
  }

  public function checkForBadWords(){
    $storedSafeWords = CRM_Core_BAO_Setting::getItem('org.thirdsectordesign.chainsms', 'safe_list');
    $storedBadWords = CRM_Core_BAO_Setting::getItem('org.thirdsectordesign.chainsms', 'swear_list');

    $safeWords = !empty($storedSafeWords) ? $storedSafeWords : array();

    $badWords = !empty($storedBadWords) ? $storedBadWords : array();

    foreach($this->contact->texts as $text){
      if($text['direction']=='inbound'){
        $lowercaseText = strtolower(trim($text['text']));
        $safeWordsRemoved = str_replace($safeWords, '', $lowercaseText);

        $replacement = str_replace($badWords, 'fluffy-kitten', $safeWordsRemoved);
        if($replacement != $safeWordsRemoved){
          $this->contact->addError("Potentially offensive language found: {$text['text']}\n");
        }
      }
    }
  }
}