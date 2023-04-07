<?php

namespace Drupal\bps_content_display\Plugin\HWMarkup\Processor;

use Drupal\highwire_markup\ProcessorBase;
use HighWire\Parser\Markup\Markup;
use Drupal\Core\Url;

/**
 * @Processor(
 *   id = "bpscrosschapterlinks",
 *   name = "Bps: Cross-Chapter links",
 *   description = "Build links between book sections"
 * )
 */
class BpsCrossChapterLinks extends ProcessorBase {

  /**
   * {@inheritdoc}
   */
  public function process(Markup $markup, $context = NULL) {
    if (!$context || !in_array($context->getType(), bps_core_get_book_chunk_types())) {
      return;
    }

    $links = $markup->xpath('//a[@data-rid][starts-with(@href,"atom://")]');
    if (!is_object($links) || $links->count() === 0) {
      return;
    }

    $parent_nid = '';
    if ($context->hasField('parent') && !$context->get('parent')->isEmpty('parent')) {
      $parent_nid = $context->get('parent')->getString();
    }
    if (empty($parent_nid)) {
      return;
    }

    // Replace link hrefs.
    foreach ($links as $link) {
      $atom_href = $link->getAttribute('href');
      if (!empty($atom_href)) {
        $parse_url = explode("/", $atom_href);
        $atom_id = explode("#", $parse_url[3]);
        if (!empty($atom_id[0])) {
          $link->setAttribute('href', Url::fromRoute('highwire_content.lookup.atom_id', ['atomid' => $atom_id[0]])->toString());
        }
      }
      else {
        $link->removeAttribute('href');
      }
    }
  }

}
