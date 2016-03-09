<?php

/**
 * SMSSurveyTranslator.Translate API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_s_m_s_survey_translator_translate_spec(&$spec) {
  //$spec['magicword']['api.required'] = 1;
}

/**
 * SMSSurveyTranslator.Translate API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_s_m_s_survey_translator_translate($params) {

  $translator = new CRM_Chainsms_Translator;

  $aGroups = split(",", mysql_real_escape_string($params['aGroups']));

  $translator->setGroups($aGroups);

  $translator->setStartDate(mysql_real_escape_string($params['sStartDate']));

  $translator->setEndDate(mysql_real_escape_string($params['sLimitDate']));

  $translator->prepare();

  // * Run the cleaning script
  $translator->setTranslatorClass(mysql_real_escape_string($params['sTranslatorClass']));
  $translator->setCampaign(mysql_real_escape_string($params['sCampaignName']));

  $translator->translate();
  $activitiesCreatedOrUpdated = $translator->update();

  $returnValues = array(array('activitiesCreatedOrUpdated' => $activitiesCreatedOrUpdated));

  return civicrm_api3_create_success($returnValues, $params, 'SMSSurveyTranslator', 'Translate');
}

