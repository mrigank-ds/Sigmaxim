<?php

namespace Drupal\field_group_image\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\field_group\FieldgroupUi;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Url;

/**
 * Provides a form for removing a fieldgroup from a bundle.
 */
class FieldGroupImageUploadForm extends FormBase {

  /**
   * Function to get entity type for url and convert back to
   * original value.
   */
  protected function getGroupEntityType($entity_type) {
    if ($entity_type == 'node') {
      return 'types';
    }
    elseif ($entity_type == 'types') {
      return 'node';
    }
    elseif ($entity_type == 'sigmaxim_workflow_order') {
      return 'products';
    }
	elseif ($entity_type == 'products') {
      return 'sigmaxim_workflow_order';
    }
		
    return $entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'field_group_image_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $entity_type = NULL,
    $bundle = NULL,
    $group_name = NULL
  ) {
    if (empty($entity_type) || empty($group_name) || empty($bundle)) {
      throw new NotFoundHttpException();
    }
    $entity_type = $this->getGroupEntityType($entity_type);
    // Get field group image data.
    $field_group_image_data = get_field_group_image_data($group_name, $entity_type, $bundle, 'default');

    // Field group image field.
    $form['image'] = [
      '#type' => 'managed_file',
      '#title' => t('Group Image'),
      '#format' => 'rich_text',
      '#description' => t('Upload an image for this field group.'),
      '#upload_location' => 'public://field_group_image/',
      '#default_value' => [
        'fid' => $field_group_image_data['image']
      ],
    ];

    $options = [];
    foreach (\Drupal::entityTypeManager()->getStorage('image_style')->loadMultiple() as $id => $image_style) {
      $options[$id] = $image_style->label();
    }
    // Image style field.
    $form['image_style'] = [
      'image_style' => [
        '#type' => 'select',
        '#title' => t('Image style'),
        '#description' => t('Select image style for the image.'),
        '#options' => $options,
        '#default_value' => $field_group_image_data['image_style'],
      ],
    ];

    $form['image_position'] = [
      '#type' => 'select',
      '#title' => t('Image position'),
      '#description' => t('The position of the image in relation to other fields.'),
      '#options' => [
        'above' => t('Above'),
        'below' => t('Below'),
        'left' => t('Left'),
        'right' => t('Right'),
      ],
      '#default_value' => $field_group_image_data['image_position'],
      '#attributes' => ['id' => 'image-position'],
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    $entity_type = $this->getGroupEntityType($entity_type);
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromUri('internal:/admin/structure/' . $entity_type . '/manage/' . $bundle . '/form-display'),
    ];
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get entity type and bundle from build info args.
    [$entity_type, $bundle, $group_name] = $form_state->getBuildInfo()['args'];
    $data = [
      'image' => !empty($form_state->getValue('image')[0])
        ? (int) $form_state->getValue('image')[0]
        : '',
      'image_style' => $form_state->getValue('image_style') ?? '',
      'image_position' => $form_state->getValue('image_position') ?? '',
    ];
    $entity_type = $this->getGroupEntityType($entity_type);
    // Save field group image in content type display settings.
    save_field_group_image($data, $group_name, $entity_type, $bundle, 'default');
    //echo '<pre>' ,print_r($form['image_position']), '</pre>'; exit();

// Add class to image element based on image position.
$image_position = $form_state->getValue('image_position');
$image_element = &$form['image'];
//$image_element['#attributes']['class'][] = 'image-position-' . $image_position;
//$image_element['#attributes']['class'][] = 'image123';
$image_element['#attributes']['#weight'] = $image_position == 'below' ? 9999 : -9999;
//echo '<pre>' ,print_r($image_element), '</pre>'; exit();

// Display message to indicate form submission.
$this->messenger()->addMessage($this->t('Image position set to @position.', ['@position' => $image_position]));
  }

  
}
