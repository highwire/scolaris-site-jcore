<?php

namespace Drupal\bps_content_display\Plugin\HWMarkup\Processor;

use Drupal\highwire_markup\ProcessorBase;
use HighWire\Parser\Markup\Markup;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;

/**
 * Section Links processor.
 *
 * @Processor(
 *   id = "BPSSectionLinks",
 *   name = "Bps Section links",
 *   description = "Change link of section"
 * )
 */
class BPSSectionLinks extends ProcessorBase {
  /**
   * {@inheritdoc}
   */
  public function process(Markup $markup, $context = NULL) {    
    $nodes = $markup->xpath("//article[contains(@class, 'item-section')]/h2/a");
    foreach ($nodes as $node) {
      if ($node->hasAttribute('href') && !empty($node->getAttribute('href'))) {
        $link_array = explode('/', $node->getAttribute('href'));
        $link = $node->getAttribute('href');
        $updated_link = str_replace("/section/","#",$link);
        $node->setAttribute('href', $updated_link);
      }
    }
  }  
}