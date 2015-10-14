<?php

/**
 * A custom search to look up contacts with no valid e-mail address that
 * is not on hold, so we can text them to get an up-to-date e-mail address.
 */
class CRM_Chainsms_Form_Search_BouncedEmailAddresses extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {
  function __construct(&$formValues) {
    $this->_columns = array(
      ts('Name')                => 'sort_name',
      ts('Contact type')        => 'contact_type',
      ts('Contact sub-type(s)') => 'contact_sub_type',
      ts('Held e-mail')         => 'held_email',
      ts('Held since')          => 'hold_date',
      ts('Mobile')              => 'mobile',
    );
    parent::__construct($formValues);
  }

  /**
   * Prepare a set of search fields
   *
   * @param CRM_Core_Form $form modifiable
   * @return void
   */
  function buildForm(&$form) {
    CRM_Utils_System::setTitle(ts('Contacts with no valid e-mail address'));

    $typeOptions = CRM_Contact_BAO_ContactType::contactTypePairs();
    $dateOptions = array(
      'addEmptyOption' => TRUE,
      'format'         => 'd/m/Y',
      'minYear'        => 2000,
      'maxYear'        => date('Y') + 5,
    );
    $groupOptions = array('' => '- ' . ts('any') . ' -') + self::getGroups();

    $form->addElement('advmultiselect', 'type',         ts('Only show contacts of these types/subtypes'), $typeOptions, array('style' => 'width: 200px;'));
    $form->addElement('date',           'hold_date',    ts('Show e-mail addresses going on-hold since'), $dateOptions);
    $form->addElement('checkbox',       'do_not_email', ts('Include contacts with "do not e-mail" set?'));
    $form->addElement('checkbox',       'do_not_sms',   ts('Include contacts with "do not SMS" set?'));
    $form->addElement('checkbox',       'is_opt_out',   ts('Include contacts with "no bulk e-mails (user opt-out)" set?'));
    $form->addElement('checkbox',       'no_mobile',    ts('Include contacts with no mobile phone number?'));
    $form->addElement('select',         'group',        ts('Only show contacts in this group'), $groupOptions);
    $form->addElement('static',         'note',         ts('Note'), "
      A custom search to look up contacts with no valid e-mail address that
      is not on hold, so we can text them to get an up-to-date e-mail address.<br/>
      Contacts who are deceased, or whose records are in the Trash, will not be shown.
    ");

    /**
     * if you are using the standard template, this array tells the template what elements
     * are part of the search criteria
     */
    $form->assign('elements', array(
      'type',
      'hold_date',
      'do_not_email', 'do_not_sms', 'is_opt_out', 'no_mobile',
      'group',
      'note',
    ));
  }

  /**
   * Construct a full SQL query which returns one page worth of results
   *
   * @param int $offset
   * @param int $rowcount
   * @param null $sort
   * @param bool $includeContactIDs
   * @param bool $justIDs
   * @return string, sql
   */
  function all($offset = 0, $rowcount = 0, $sort = NULL, $includeContactIDs = FALSE, $justIDs = FALSE) {
    // delegate to $this->sql(), $this->select(), $this->from(), $this->where(), etc.
    $sql = $this->sql($this->select(), $offset, $rowcount, $sort, $includeContactIDs, " GROUP BY `contact_a`.`id` ");
    //die("<div><pre>$sql</pre></div>\n"); // DEBUG
    return $sql;
  }

  /**
   * Construct a SQL SELECT clause
   *
   * @return string, sql fragment with SELECT arguments
   */
  function select() {
    return "
      `contact_a`.`id`               AS `contact_id`,
      `contact_a`.`sort_name`        AS `sort_name`,
      `contact_a`.`contact_type`     AS `contact_type`,
      `contact_a`.`contact_sub_type` AS `contact_sub_type`,
      `ce_held`.`email`              AS `held_email`,
      `ce_held`.`hold_date`          AS `hold_date`,
      `cp`.`phone`                   AS `mobile`
    ";
  }

  /**
   * Construct a SQL FROM clause
   *
   * @return string, sql fragment with FROM and JOIN clauses
   */
  function from() {
    return "
      FROM `civicrm_contact` AS `contact_a`

           /* We will look for contacts who don't have one of these */
 LEFT JOIN `civicrm_email`   AS `ce`
        ON `ce`.`contact_id`  = `contact_a`.`id`
       AND `ce`.`email`      IS NOT NULL
       AND `ce`.`email`      != ''
       AND `ce`.`on_hold`    IS NOT TRUE

           /* We will probably look for contacts who do have one of these */
 LEFT JOIN `civicrm_phone`      AS `cp`
        ON `cp`.`contact_id`     = `contact_a`.`id`
       AND `cp`.`phone`         IS NOT NULL
       AND `cp`.`phone`         != ''
       AND `cp`.`phone_type_id`  = 2 /* Mobile */

           /* We may look for contacts who do have one of these */
 LEFT JOIN `civicrm_email`        AS `ce_held`
        ON `ce_held`.`contact_id`  = `contact_a`.`id`
       AND `ce_held`.`on_hold`    IS TRUE

           /* We may look for contacts who do have one of these */
 LEFT JOIN `civicrm_group_contact` AS `cgc`
        ON `cgc`.`contact_id`       = `contact_a`.`id`
       AND `cgc`.`status`           = 'Added'
    ";
  }

  /**
   * Construct a SQL WHERE clause
   *
   * @param bool $includeContactIDs
   * @return string, sql fragment with conditional expressions
   */
  function where($includeContactIDs = FALSE) {
    $params = array();
    $where  = "TRUE\n";
    $count  = 1;
    $clause = array(
      "(`ce`.`id`                 IS NULL)\n",
      "(`contact_a`.`is_deceased` IS NOT TRUE)\n",
      "(`contact_a`.`is_deleted`  IS NOT TRUE)\n",
    );

    // Only show contacts of these types/subtypes
    $types = CRM_Utils_Array::value('type', $this->_formValues);
    if (is_array($types) && !empty($types)) {
      $types_clause = "FALSE\n";
      foreach ($types as $type) {
        // Add subclauses for 'contact type is one of these' or 'contact subtype includes one of these'
        $params[$count] = array($type, 'String');
        $types_clause .= " OR `contact_a`.`contact_type` = %{$count}\n";
        $types_clause .= " OR `contact_a`.`contact_sub_type` LIKE CONCAT('%', CHAR(1), %{$count}, CHAR(1), '%')\n";
        $count++;
      }
      $clause[] = "($types_clause)";
    }

    // Show e-mail addresses going on-hold since
    $hold_date   = CRM_Utils_Array::value('hold_date', $this->_formValues);
    $date_string = sprintf("%04d%02d%02d", $hold_date['Y'], $hold_date['m'], $hold_date['d']);
    if (CRM_Utils_Type::validate($date_string, 'Date', FALSE)) {
      $params[$count] = array($date_string, 'Date');
      $clause[]       = "(`ce_held`.`hold_date` >= %{$count})\n";
      $count++;
    }

    // Include contacts with "do not e-mail" set?
    if (!CRM_Utils_Array::value('do_not_email', $this->_formValues)) {
      $clause[] = "(`contact_a`.`do_not_email` IS NOT TRUE)\n";
    }

    // Include contacts with "do not SMS" set?
    if (!CRM_Utils_Array::value('do_not_sms', $this->_formValues)) {
      $clause[] = "(`contact_a`.`do_not_sms` IS NOT TRUE)\n";
    }

    // Include contacts with "no bulk e-mails (user opt-out)" set?
    if (!CRM_Utils_Array::value('is_opt_out', $this->_formValues)) {
      $clause[] = "(`contact_a`.`is_opt_out` IS NOT TRUE)\n";
    }

    // Include contacts with no mobile phone number?
    if (!CRM_Utils_Array::value('no_mobile', $this->_formValues)) {
      $clause[] = "(`cp`.`id` IS NOT NULL)\n";
    }

    // Only show contacts in this group
    $gid = CRM_Utils_Array::value('group', $this->_formValues);
    if (CRM_Utils_Type::validate($gid, 'Positive', FALSE)) {
      $params[$count] = array($gid, 'Int');
      $clause[]       = "(`cgc`.`group_id` = %{$count})\n";
      $count++;
    }

    if (!empty($clause)) {
      $where .= ' AND ' . implode(' AND ', $clause);
    }
    return $this->whereClause($where, $params);
  }

  /**
   * Modify the content of each row
   *
   * @param array $row modifiable SQL result row
   * @return void
   */
  function alterRow(&$row) {
    $row['contact_type']     = self::formatSubtypes($row['contact_type']);
    $row['contact_sub_type'] = self::formatSubtypes($row['contact_sub_type']);
    $row['hold_date']        = CRM_Utils_Date::customFormat($row['hold_date'], '%d/%m/%Y');
  }

  /**
   * Format a separated string of contact subtype names (as stored in the database)
   * as a sorted, comma-separated string of contact subtype labels (for human reading)
   *
   * @param string $contact_sub_type
   * @return string
   */
  static function formatSubtypes($contact_sub_type) {
    $subtypes_map = CRM_Contact_BAO_ContactType::contactTypePairs();
    $subtypes     = explode(CRM_Core_DAO::VALUE_SEPARATOR, $contact_sub_type);

    foreach ($subtypes as $key => $subtype) {
      if (empty($subtype)) {
        unset($subtypes[$key]);
      }
      else {
        $subtypes[$key] = $subtypes_map[$subtype];
      }
    }

    natcasesort($subtypes);
    return implode(', ', $subtypes);
  }

  /**
   * Get an array mapping group IDs to titles.
   *
   * @return array
   */
  static function getGroups() {
    $groups_result = civicrm_api('Group', 'get', array(
      'version'    => 3,
      'is_active'  => 1,
      'is_hidden'  => 0,
      'return'     => array('id', 'title'),
      'options'    => array(
        'limit' => 0,
        'sort'  => 'title ASC',
      ),
    ));
    if (civicrm_error($groups_result)) {
      return NULL;
    }

    $groups = array();
    foreach ($groups_result['values'] as $group) {
      $groups[$group['id']] = $group['title'];
    }
    return $groups;
  }
}
