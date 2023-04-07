<?php

namespace Drupal\bps_content_display\Plugin\HWMarkup\Processor;

use Drupal\highwire_markup\ProcessorBase;
use HighWire\Parser\Markup\Markup;
use Symfony\Component\CssSelector\CssSelectorConverter;

/**
 * @Processor(
 *   id = "bpscontribsplain",
 *   name = "Bps: Plain Text Contribs",
 *   description = "Removes popover from contribs markup"
 * )
 */
class BpsContribsPlain extends ProcessorBase {

  public function process(Markup $markup, $context = NULL) {
    $contribs_wrappers = $markup->xpath('//ul[@class="contributor-list"]');
    if (is_object($contribs_wrappers)) {
      foreach ($contribs_wrappers as $contribs_wrapper) {
        // Add class to contribs wrapper.
        $class = $contribs_wrapper->getAttribute('class');
        $class .= ' contributor-list-plain';
        $contribs_wrapper->setAttribute('class', $class);
      }
    }
    $contribs = $markup->xpath('//li[@class="contrib"]/a');
    if (is_object($contribs)) {
      foreach ($contribs as $contrib) {
        $contrib_contents = $contrib->childNodes;
        if (is_object($contrib_contents)) {
          // Add children of link element to parent (li).
          foreach($contrib_contents as $contrib_content) {
            $contrib->parentNode->appendChild($contrib_content);
          }
          // Remove link.
          $contrib->parentNode->removeChild($contrib);
        }
      }
    }
  }

}
