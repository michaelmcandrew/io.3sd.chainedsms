<?php
class CRM_Chainsms_Processor{

  // record_type_id values in civicrm_activity_contact for a record corresponding to:
  const RECORD_TYPE_ID_SOURCE = 2; // source_contact_id
  const RECORD_TYPE_ID_TARGET = 3; // target_contact_id

  function __construct(){
    $this->ChainedSMSTableName = civicrm_api("CustomGroup","getvalue", array ('version' => '3', 'name' =>'Chained_SMS', 'return' =>'table_name'));
    $this->ChainedSMSTableId = civicrm_api("CustomGroup","getvalue", array ('version' => '3', 'name' =>'Chained_SMS', 'return' =>'id'));
    $this->ChainedSMSColumnName = civicrm_api("CustomField","getvalue", array ('version' => '3', 'name' =>'message_template_id', 'custom_group_id' => $this->ChainedSMSTableId, 'return' =>'column_name'));
    $this->ChainedSMSColumnId = civicrm_api("CustomField","getvalue", array ('version' => '3', 'name' =>'message_template_id', 'custom_group_id' => $this->ChainedSMSTableId, 'return' =>'id'));

    $this->OutboundSMSActivityTypeId = CRM_Core_OptionGroup::getValue('activity_type', 'SMS', 'name');
    $this->OutboundMassSMSActivityTypeId = CRM_Core_OptionGroup::getValue('activity_type', 'Mass SMS', 'name');
    $this->InboundSMSActivityTypeId = CRM_Core_OptionGroup::getValue('activity_type', 'Inbound SMS', 'name');

    $storedSafeWords = CRM_Core_BAO_Setting::getItem('org.thirdsectordesign.chainsms', 'safe_list');
    $storedBadWords = CRM_Core_BAO_Setting::getItem('org.thirdsectordesign.chainsms', 'swear_list');
    $this->storedSafeWords = $storedSafeWords ? $storedSafeWords : array();
    $this->storedBadWords = $storedBadWords ? $storedBadWords : array();
  }

  /*
   * Remove the safe words, check for swear words, bail if you find any
   * @param string $response, the raw response as the user returns it
   */
  public function doesResponseContainBadWord($response){
    $response = strtolower($response);

    foreach($this->storedSafeWords as $safeWord){
      $response = str_replace(strtolower($safeWord), '', $response);
    }

    foreach ($this->storedBadWords as $badWord){
      if (strpos($response, strtolower($badWord)) !== false){
        return TRUE;
      }
    }

    return FALSE;
  }

  function inbound($inboundActivity){

    if (strtolower($inboundActivity['details']) == 'stop') {
      return; // user has instructed us to stop, bail.
    }

    if ($this->doesResponseContainBadWord($inboundActivity['details'])) {
      return; // perhaps the user has sworn at us. Don't send them anything, let cleaning take care of it.
    }

    // Work out whether this is an answer to a question...

    // Find the most recent outbound text to this person that could be considered a question
    $mostRecentOutboundChainSMS = $this->mostRecentOutboundChainSMS($inboundActivity['source_contact_id']);

    //if there is no most recent question, then stop inbound processing
    if(!$mostRecentOutboundChainSMS){
      return 1;
    }

    $mostRecentOutboundChainSMSDate = new DateTime($mostRecentOutboundChainSMS->activity_date_time);
    $inboundSMSDate = new DateTime($inboundActivity['activity_date_time']);

    //TODO: if the reply was send longer ago that the response_time_limit then there is no more processing to do
    //if(($inboundSMSDate - $mostRecentOutboundChainSMSDate['date']) > $an_amount_of_time){
    //return 1;
    //}
    //error_log(print_r($inboundActivity->details, true));

    // Has the question been answered already?
    $penultimateInboundSMS = $this->penultimateInboundSMS($inboundActivity['source_contact_id']);
    if (is_object($penultimateInboundSMS)) {
      //if an inbound has been received before this one and it was after we sent the most recent question, then consider this question answered
      $penultimateInboundSMSDate = new DateTime($penultimateInboundSMS->activity_date_time);
      if ($penultimateInboundSMSDate > $mostRecentOutboundChainSMSDate) {
        return 1;
      }
    }

    // if it is waiting for a reply, then this inbound message should be treated as a reply to that question
    // TODO - mark that this is an answer

    $nextMessageQuery = "
      SELECT next_msg_template_id, answer
      FROM civicrm_chainsms_answer
      WHERE msg_template_id = %1";

    $nextMessageParams[1]=array($mostRecentOutboundChainSMS->msg_template_id, 'Integer');
    //$nextMessageParams=array();

    $nextMessageResult = CRM_Core_DAO::executeQuery($nextMessageQuery, $nextMessageParams);

    while($nextMessageResult->fetch()){
      $sCleanedInboundMsg = self::cleanInboundResponse($inboundActivity['details']);
      if(strtolower($nextMessageResult->answer) == strtolower($sCleanedInboundMsg) || $nextMessageResult->answer==''){
        if(strtolower($sCleanedInboundMsg) != strtolower($inboundActivity['details'])){
          CRM_Core_Error::debug_log_message("ChainSMS: Processor cleaned inbound message, contact: {$inboundActivity['source_contact_id']}, activity: {$inboundActivity['id']}, cleaned: {$sCleanedInboundMsg}, dirty: {$inboundActivity['details']}");
        }
        $result = civicrm_api('Contact', 'sms', array('version'=>'3','contact_id' => $inboundActivity['source_contact_id'], 'msg_template_id'=>$nextMessageResult->next_msg_template_id));
      }
    }

    return 1;
  }

  static function cleanInboundResponse($sInboundText){
    // the aim is to send a response even if "A :)" or "A." is received instead of "A"
  	$aCharactersToDelete = array(':', ';', ')', '(', '.', ' ', ',', '-', "'", '*', '^', 'o', 'x', 'p', 'O', 'X', 'P', '!', '<', '3'); // use single characters to prevent smilies like (: not getting picked up

    $sCleanedText = $sInboundText;

    foreach($aCharactersToDelete as $sCharToDelete){ // replace each of them
      $sCleanedText = str_replace($sCharToDelete, '', $sCleanedText);
    }

  	return $sCleanedText;
  }

  /**
   * Find the most recent chain Outbound SMS or Mass SMS sent to a contact.
   *
   * @param int $target_contact_id
   *   Contact ID of the person completing the survey
   * @return null|object
   *   DAO object (not array) with certain details of the Outbound SMS or Mass SMS activity, or NULL on error or if no such activity.
   */
  function mostRecentOutboundChainSMS($target_contact_id) {

    /* TODO REFACTOR: when something like this using IN is supported on Activity
     * (it may already be supported on Contact)
     * (use chained API calls for the msg_template_id?)
    $latestOutbound = civicrm_api('Activity', 'get', array(
      'version'    => 3,
      'sequential' => 1,
      // SELECT
      'return' => array(
        'id',
        'activity_date_time',
        'activity_type_id',
        'target_contact_id',
      ),
      // WHERE
      'target_contact_id' => $target_contact_id,
      'activity_type_id'  => array('IN', array(
        $this->OutboundSMSActivityTypeId,
        $this->OutboundMassSMSActivityTypeId,
      )),
      'options' => array(
        'sort'  => 'activity_date_time DESC', // ORDER BY
        'limit' => 1,                         // LIMIT
      ),
    )); */

    $query = "
      SELECT ca.id AS activity_id,
             COALESCE(cd.{$this->ChainedSMSColumnName}, cm.msg_template_id) AS msg_template_id,
             ca.activity_date_time,
             ca.activity_type_id

        FROM civicrm_activity         AS ca
  INNER JOIN civicrm_activity_contact AS cac
          ON cac.activity_id           = ca.id
         AND cac.record_type_id        = " . self::RECORD_TYPE_ID_TARGET . "

             /* Outbound SMS activities have a custom field set by the chain SMS system */
   LEFT JOIN {$this->ChainedSMSTableName} AS cd
          ON ca.id                         = cd.entity_id
             /* Mass SMS activities are joined to their Mailing by their source_record_id */
   LEFT JOIN civicrm_mailing              AS cm
          ON ca.source_record_id           = cm.id

             /* Looking for activities regarding a specific contact */
       WHERE cac.contact_id = %1

             /* If it doesn't have a msg_template_id it can't be part of a chain */
         AND (
               (
                 /* in case of an Outbound SMS */
                 (cd.{$this->ChainedSMSColumnName} IS NOT NULL)
         AND     (activity_type_id                  = {$this->OutboundSMSActivityTypeId})
               )
          OR   (
                 /* in case of a Mass SMS */
                 (cm.msg_template_id               IS NOT NULL)
         AND     (activity_type_id                  = {$this->OutboundMassSMSActivityTypeId})
               )
             )

             /* Latest only */
    ORDER BY activity_date_time DESC
       LIMIT 1;
    ";

    $params[1] = array($target_contact_id, 'Integer');
    $latestOutbound = CRM_Core_DAO::executeQuery($query, $params);
    if (!$latestOutbound->fetch()) {
      return NULL;
    }
    return $latestOutbound;
  }

  /* TODO REFACTOR : When the Activity API is fixed- source_contact_id is broken
  function penultimateInboundSMS($source_contact_id) {
    $activities = civicrm_api('Activity', 'get', $params = array(
      'version'           => 3,
      'sequential'        => 1,
      'activity_type_id'  => $this->InboundSMSActivityTypeId,
      'source_contact_id' => $source_contact_id,
      'options'  => array(
        'sort'   => 'activity_date_time DESC',
        'limit'  => 1,
        'offset' => 1,
      ),
    ));
    if (civicrm_error($activities)) {
      return NULL;
    }
    if ($activities['count'] != 1)  {
      return NULL;
    }
    return (object) $activities['values']['0'];
  } */

  function penultimateInboundSMS($source_contact_id){
    $query="
      SELECT ca.*,
             cac.contact_id           AS source_contact_id
        FROM civicrm_activity         AS ca
        JOIN civicrm_activity_contact AS cac
          ON cac.activity_id          =  ca.id
         AND cac.record_type_id       =  ".self::RECORD_TYPE_ID_SOURCE."
       WHERE ca.activity_type_id      =  {$this->InboundSMSActivityTypeId}
         AND cac.contact_id           =  %1
      ORDER BY
      activity_date_time DESC
      LIMIT 1,1
      ";

    $params[1]=array($source_contact_id, 'Integer');
    $activity = CRM_Core_DAO::executeQuery($query, $params);
    if($activity->fetch()){
      return $activity;
    }else{
      return 0;
    }
  }

  function outbound($question_id){
    $this->addMessage('out', $this->questions[$question_id]['text'], $question_id);
    echo "OUTBOUND: {$this->questions[$question_id]['text']}\n";
  }


  function addMessage($type, $text, $question_id=null){
    return $this->messages[]=array('date' => mktime(), 'type' => $type, 'text' => $text, 'question_id' => $question_id);
  }

  function printMessages(){
    print_r($this->messages);
  }

}
