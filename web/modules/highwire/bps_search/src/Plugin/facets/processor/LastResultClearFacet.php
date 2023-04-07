<?php

namespace Drupal\bps_search\Plugin\facets\processor;

use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;

/**
 * Provides a processor that hides results that don't narrow results.
 *
 * @FacetsProcessor(
 *   id = "last_result_clear_facet_processor",
 *   label = @Translation("Last result clears facet"),
 *   description = @Translation("Clicking the last inactive item clears all active items."),
 *   stages = {
 *     "build" = 25
 *   }
 * )
 */
class LastResultClearFacet extends ProcessorPluginBase implements BuildProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    $active_items = count($facet->getActiveItems());

    if (empty($active_items)) {
      return $results;
    }

    $num_results = count($facet->getResults());
    if ($num_results == $active_items + 1) {
      /** @var \Drupal\facets\Result\ResultInterface $result */
      foreach ($results as $id => $result) {
        if (!$result->isActive()) {
          $facet_url_alias = $facet->getUrlAlias();
          if (!empty($facet_url_alias) && $result->getUrl()) {
            $url_options = $result->getUrl()->getOptions();
            if (!empty($url_options['query']['f'])) {
              foreach ($url_options['query']['f'] as $k => $facet_query_param) {
                if (strpos($facet_query_param, $facet_url_alias . ':') === 0) {
                  unset($url_options['query']['f'][$k]);
                }
              }
              $result->setUrl($result->getUrl()->setOptions($url_options));
            }
          }
        }
      }
    }

    return $results;
  }

}
