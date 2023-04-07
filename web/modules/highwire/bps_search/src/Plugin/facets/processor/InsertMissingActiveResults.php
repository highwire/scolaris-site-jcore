<?php

namespace Drupal\bps_search\Plugin\facets\processor;

use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\facets\Result\Result;

/**
 * Provides a processor that hides results that don't narrow results.
 *
 * @FacetsProcessor(
 *   id = "insert_missing_active_results_processor",
 *   label = @Translation("Insert missing active results"),
 *   description = @Translation("Enable this to create active items that are missing from results list.<br><strong>Note:</strong> missing active items will be created with a count of -1."),
 *   stages = {
 *     "build" = -25
 *   }
 * )
 */
class InsertMissingActiveResults extends ProcessorPluginBase implements BuildProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    // Don't do anything if results haven't been limited or if there are no active items.
    $active_items = $facet->getActiveItems();
    if ($facet->getHardLimit() === 0 || empty($active_items)) {
      return $results;
    }

    // Build index of active results.
    $active_results = [];
    /** @var \Drupal\facets\Result\ResultInterface $result */
    foreach($results as $result) {
      if ($result->isActive()) {
        $active_results[$result->getRawValue()] = TRUE;
      }
    }

    // Add missing active items as results with a count of -1.
    foreach($active_items as $active_item) {
      if (!isset($active_results[$active_item])) {
        $new_result = new Result($facet, $active_item, $active_item, -1);
        $new_result->setActiveState(TRUE);
        $results[] = $new_result;
      }
    }

    return $results;
  }

}
