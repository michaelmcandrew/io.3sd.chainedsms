<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of TranslatorInterface
 *
 * @author john
 */
interface CRM_Chainsms_Translator_TranslatorInterface {
  /*
   * User friendly name for what's translated, i.e. 'Email Addresses'.
   */
  public static function getName();
  
  /*
   * User friendly description of the Translator Class, i.e. 'This translator 
   * will take an inbound message containing valid email addresses email address,
   * and add them to the contact that sent it.'.
   */
  public static function getDescription();
  
  /*
   * The translation for each contact.
   */
  public function translate($contact);
  
  /*
   * Update each contact with the translated data.
   */
  public function update($contact);
  
}
