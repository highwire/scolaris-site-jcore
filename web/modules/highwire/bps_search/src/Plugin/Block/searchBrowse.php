<?php
/**
 * @file
 * Contains Drupal\bps_search\Plugin\Block\searchBrowse.
 */

namespace Drupal\bps_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormInterface;

/**
 * Provides a 'search browse' block.
 *
 * @Block(
 *   id = "searchBrowse",
 *   admin_label = @Translation("Search browse block"),
 *   category = @Translation("Custom search")
 * )
 */
class searchBrowse extends BlockBase {
 
  /**
   * {@inheritdoc}
   */
  public function build() {
    
    $form = \Drupal::formBuilder()->getForm('Drupal\bps_search\Form\searchBrowse',$node);
   
    return $form;
   }
}