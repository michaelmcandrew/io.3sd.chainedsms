<?php
// This file declares a managed database record of type "CustomSearch".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array(
  0 => array(
    'name'   => 'CRM_Chainsms_Form_Search_BouncedEmailAddresses',
    'entity' => 'CustomSearch',
    'params' => array(
      'version'     => 3,
      'label'       => 'Contacts with no valid e-mail address',
      'description' => 'Contacts with no valid e-mail address (org.thirdsectordesign.chainsms)',
      'class_name'  => 'CRM_Chainsms_Form_Search_BouncedEmailAddresses',
    ),
  ),
);
