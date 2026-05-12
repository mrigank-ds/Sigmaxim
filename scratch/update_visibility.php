<?php

use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\Entity\EntityFormDisplay;

echo "--- Checking Field Configs ---\n";
$field_configs = FieldConfig::loadMultiple();
$count_f = 0;

foreach ($field_configs as $field) {
  $weights = $field->getThirdPartySetting('monarch_data_entity', 'weights');
  if ($weights) {
    echo "Updating FieldConfig: " . $field->id() . "\n";
    foreach ($weights as $field_name => &$info) {
      $info['visibility'] = 1;
    }
    $field->setThirdPartySetting('monarch_data_entity', 'weights', $weights);
    $field->save();
    $count_f++;
  }
}

echo "--- Checking Form Displays ---\n";
$form_displays = EntityFormDisplay::loadMultiple();
$count_d = 0;

foreach ($form_displays as $display) {
  $components = $display->getComponents();
  $changed = FALSE;
  foreach ($components as $field_name => $component) {
    if (isset($component['settings']['weights'])) {
      echo "Updating Widget Settings in Display: " . $display->id() . " (Field: $field_name)\n";
      foreach ($component['settings']['weights'] as $col => &$info) {
        $info['visibility'] = 1;
      }
      $display->setComponent($field_name, $component);
      $changed = TRUE;
    }
  }
  if ($changed) {
    $display->save();
    $count_d++;
  }
}

echo "Successfully updated $count_f FieldConfigs and $count_d FormDisplays.\n";
