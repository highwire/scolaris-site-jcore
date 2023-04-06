<?php

namespace Drupal\bps_search\Plugin\facets\processor;

use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;

/**
 * Provides a processor that hides results that don't narrow results.
 *
 * @FacetsProcessor(
 *   id = "hide_facet_no_items_processor",
 *   label = @Translation("Hide facet when there are no items"),
 *   description = @Translation("Facet will be hidden when all results have a count of 0 and there are no active items.<br><strong>Note:</strong> Only takes effect when result minimum count is 0."),
 *   stages = {
 *     "build" = 25
 *   }
 * )
 */
class HideFacetNoItems extends ProcessorPluginBase implements BuildProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    // Don't do anything if results are already omitted when empty,
    // or if there are active items to be displayed.
    if ($facet->getMinCount() > 0 || count($facet->getActiveItems()) > 0) {
      return $results;
    }

    // Check if all results have a count of 0.
    $all_results_empty = TRUE;
    foreach ($results as $result) {
      if ($result->getCount() > 0) {
        $all_results_empty = FALSE;
        break;
      }
    }

    if ($all_results_empty) {
      $results = [];
    }

    return $results;
  }

}
