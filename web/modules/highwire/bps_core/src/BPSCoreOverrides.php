<?php
namespace Drupal\bps_core;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
/**
 * Example configuration override.
 */
class BPSCoreOverrides implements ConfigFactoryOverrideInterface {
  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];
    // Provide google_analytics with a dynamic codesnippet.
    if (in_array('google_analytics.settings', $names)) {
      $ga_event = [];
      $current_route = \Drupal::routeMatch()->getRouteName();
      if ($current_route == 'view.search.page_1') {
        // Get full search URL.
        $search_url = \Drupal::request()->getUri();

        // Track the search term.
        $search_term = \Drupal::request()->query->filter('query');
        if (empty($search_term)) {
          $search_term = '(not set)';
        }
        $ga_event_label = $search_term;
        // Track active facets.
        $facets_string = FALSE;
        $search_facets = \Drupal::request()->query->filter('f');

        if (!empty($search_facets)) {
          $facet_string = implode(' | ', $search_facets);
          $ga_event_label .= ' | ' . $facet_string;
        }
        // Track total search results for query.
        global $pager_total_items;
        $ga_event_value = isset($pager_total_items[0]) ? $pager_total_items[0] : 0; // This is the total results of the query.
        // Set the even javascript addition string.
        $ga_event['search_result_event'] = 'ga(\'send\', 
                        \'event\',
                        \'searchResultEvent\',
                        \'' . $search_url . '\',
                        \'' . $ga_event_label . '\', ' // Query String.
                        . $ga_event_value . ');'; // Query total results.
        
        if (!empty($facet_string)) {
          // Track facet selections exclusively.
          $ga_event['search_facets_applied'] = 'ga(\'send\', 
                          \'event\',
                          \'Search filter\',
                          \'click\',
                          \'' . $facet_string . '\', ' // Facet String.
                          . $ga_event_value . ');'; // Query total results.
        }

      }
      $ga_event_string = implode(' ', $ga_event);
      $overrides['google_analytics.settings'] = ['codesnippet' => ['after' => $ga_event_string]];
    }
    
    return $overrides;
  }
  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'BPSCoreOverrider';
  }
  
  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }
  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }
}
