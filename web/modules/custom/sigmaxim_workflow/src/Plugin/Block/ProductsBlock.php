<?php

namespace Drupal\sigmaxim_workflow\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block for all products.
 *
 * @Block(
 *   id = "product_block",
 *   admin_label = @Translation("Products"),
 * )
 */
class  ProductsBlock extends BlockBase {

  /**
    * {@inheritdoc}
    */
  public function build() {
    return [
      '#cache' => [
        'max-age' => 0,
      ],
      '#markup' => '<div>Products Not Available</div>',
      '#attributes' => [
        'class' => ['sigmaxim-workflow-product-table-wrapper']
      ],
      '#options' => [
        'html' => true,
      ],
      '#attached' => [
        'library' => [
          'sigmaxim_workflow/sigmaxim_workflow.all_products',
        ],
      ]
    ];
  }
}