<?php

use CRM_Passionc_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Passionc_Form_Clean extends CRM_Core_Form {
  private $queue;
  private $queueName = 'passionc';

  public function __construct() {
    // create the queue
    $this->queue = CRM_Queue_Service::singleton()->create([
      'type' => 'Sql',
      'name' => $this->queueName,
      'reset' => TRUE, // flush queue upon creation
    ]);

    parent::__construct();
  }

  public function buildQuickForm() {

    // add form elements
    $this->add(
      'text',
      'contact_id',
      'Identifiant'
    );

    $this->addRadio('import_type', 'Sélection', [1 => 'Un contact', 2 => 'Tout les contacts', 3 => 'Correction noms', 4 => 'Correction villes'], [], '<br>', TRUE);
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
    elseif ($values['import_type'] == 2) {
      // put items in the queue
      $sql = "select Identifiant from tmp_pro_perso where trim(Avancement) = 'OK' order by Identifiant";
      $dao = CRM_Core_DAO::executeQuery($sql);
      while ($dao->fetch()) {
        $method = 'process_tmp_pro_perso_task';
        $task = new CRM_Queue_Task(['CRM_Passionc_Helper', $method], [$dao->Identifiant]);
        $this->queue->createItem($task);
      }

      // run the queue
      $runner = new CRM_Queue_Runner([
        'title' => 'Conversion pro-perso',
        'queue' => $this->queue,
        'errorMode'=> CRM_Queue_Runner::ERROR_CONTINUE,
        'onEndUrl' => CRM_Utils_System::url('civicrm/nettoyage', 'reset=1'),
      ]);
      $runner->runAllViaWeb();

      CRM_Core_Session::setStatus('Terminé', 'Corrections Pro/Perso', 'success');
    }
    elseif ($values['import_type'] == 3) {
      CRM_Passionc_Helper::correctName();
    }
    elseif ($values['import_type'] == 4) {
      CRM_Passionc_Helper::correctCity();
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
