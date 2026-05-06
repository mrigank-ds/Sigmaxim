<?php

namespace Drupal\sigmaxim_import_export\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SigmaximImportForm extends FormBase {

  public function getFormId() {
    return 'sigmaxim_import_form';
  }

 public function buildForm(array $form, FormStateInterface $form_state) {

  $form['#attributes']['enctype'] = 'multipart/form-data';

  $form['json_file'] = [
    '#type' => 'managed_file',
    '#title' => $this->t('Upload product export'),
    '#description' => $this->t('Upload an exported product. Allowed extensions: .json'),
    '#upload_location' => 'public://json-import/',
    '#upload_validators' => [
      'file_validate_extensions' => ['json'],
    ],
    '#required' => TRUE,
  ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    ];

    return $form;
  }

public function submitForm(array &$form, FormStateInterface $form_state) {

  $fids = $form_state->getValue('json_file');
  

  if (empty($fids) || empty($fids[0])) {
    $this->messenger()->addError($this->t('No JSON file uploaded.'));
    return;
  }

  $fid = $fids[0];

  /** @var \Drupal\file\FileInterface $file */
  $file = \Drupal::entityTypeManager()
    ->getStorage('file')
    ->load($fid);

  if (!$file) {
    $this->messenger()->addError($this->t('Uploaded file could not be loaded.'));
    return;
  }

  // Mark file as permanent
  $file->setPermanent();
  $file->save();

  // Redirect to batch processor
  $form_state->setRedirect(
    'sigmaxim_import_export.process',
    [],
    ['query' => ['fid' => $fid]]
  );
}

}
