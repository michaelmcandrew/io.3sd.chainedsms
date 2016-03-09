<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Chainsms_Form_SwearFilter extends CRM_Core_Form {
  function buildQuickForm() {
    $this->add('text', 'safe_list', ts('Safe list'), array());
    $this->add('text', 'swear_list', ts('Swear list'), array());

    $this->getElement('safe_list')->setValue(implode(",", CRM_Core_BAO_Setting::getItem('org.thirdsectordesign.chainsms', 'safe_list')));
    $this->getElement('swear_list')->setValue(implode(",", CRM_Core_BAO_Setting::getItem('org.thirdsectordesign.chainsms', 'swear_list')));

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));
    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  function postProcess() {
    $values = $this->exportValues();
    
    $safeList = explode(",", mysql_real_escape_string($values['safe_list']));
    $swearList = explode(",", mysql_real_escape_string($values['swear_list']));
    
    CRM_Core_BAO_Setting::setItem($safeList, 'org.thirdsectordesign.chainsms', 'safe_list');
    CRM_Core_BAO_Setting::setItem($swearList, 'org.thirdsectordesign.chainsms', 'swear_list');

    parent::postProcess();
  }

  
  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}
