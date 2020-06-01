<?php

use CRM_Passionc_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Passionc_Form_Clean extends CRM_Core_Form {
  public function buildQuickForm() {

    // add form elements
    $this->add(
      'text',
      'contact_id',
      'Identifiant'
    );

    $this->addRadio('import_type', 'Sélection', [1 => 'Un contact', 2 => 'Tout les contacts'], [], '<br>', TRUE);
    $this->setDefaults(['import_type' => 1]);

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
    if ($values['import_type'] == 1) {
      $txt = CRM_Passionc_Helper::analyze($values['contact_id']);
      CRM_Core_Session::setStatus($txt, '', 'no-popup');
    }
    else {
      CRM_Core_Session::setStatus('not implemented', '', 'error');
    }

    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
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
