<?php

namespace Drupal\bps_search\Plugin\facets\processor;

use Drupal\facets\Processor\SortProcessorPluginBase;
use Drupal\facets\Processor\SortProcessorInterface;
use Drupal\facets\Result\Result;

/**
 * Provides a processor that sorts facets aplphabetically that have the same result count.
 *
 * @FacetsProcessor(
 *   id = "bps_facet_result_count_alpha",
 *   label = @Translation("bps: Same result count alpha sort"),
 *   description = @Translation("Sorts facets aplphabetically that have the same result count."),
 *   stages = {
 *     "sort" = 50
 *   }
 * )
 */
class FacetSortCountAlpha extends SortProcessorPluginBase implements SortProcessorInterface {
  /**
   * {@inheritdoc}
   */
  public function sortResults(Result $a, Result $b) {
    if ($a->getCount() == $b->getCount()) {
      return strcasecmp($a->getDisplayValue(), $b->getDisplayValue());
    }

    return 0;
  }

}
