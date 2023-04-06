<?php

namespace Drupal\bps_search\Plugin\facets\widget;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Plugin\facets\widget\LinksWidget;
use Drupal\facets\Processor\ProcessorInterface;
use Drupal\facets\Result\Result;
use Drupal\facets\Result\ResultInterface;


/**
 * The checkbox / radios widget.
 *
 * @FacetsWidget(
 *   id = "bps_include_all_checkbox",
 *   label = @Translation("bps: List of checkboxes with an include all option"),
 *   description = @Translation("List of checkboxes with an include all option"),
 * )
 */
class IncludeAllCheckBoxes extends LinksWidget {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $default = [
      'separate_active_items' => FALSE,
      'display_active_items_summary' => FALSE,
    ];
    return $default + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $build = parent::build($facet);

    if (!isset($build['#items'])) {
      return $build;
    }

    $config = $this->getConfiguration();
    $separate_active = !empty($config['separate_active_items']) ? TRUE : FALSE;
    $include_summary = !empty($config['display_active_items_summary']) ? TRUE : FALSE;
    $build['#attributes']['class'][] = 'js-facets-checkbox-links';
    $build['#attached']['library'][] = 'bps_search/drupal.bps_search.facets';

    // Add form element.
    $include_all = [
      '#type' => 'checkbox',
      '#title' => 'Include all',
      '#wrapper_attributes' => [
        'class' => ['facet-item', 'facet-item-include-all'],
      ],
      '#attributes' => ['class' => ['facets-checkbox-include-all']],
    ];

    $active_items = $summary = [];
    if (empty($facet->getActiveItems())) {
      // If there are no active items, "Include All" should be checked & disabled.
      $include_all['#attributes']['data-facet-checkboxes-saved-state'] = 'checked';
      $include_all['#attributes']['checked'] = 'checked';
      $include_all['#attributes']['disabled'] = 'disabled';
    }
    else {
      // Build url for 'include all' option from first result.
      $query_string = $facet->getUrlAlias();
      if (!empty($facet->getResults()[0])) {
        $result_url = $facet->getResults()[0]->getUrl();
        $include_all_url = '';
        if ($result_url) {
          $include_all_url = new Url($result_url->getRouteName(), $result_url->getRouteParameters(), $result_url->getOptions());
        }
      } 

      if (!empty($include_all_url)) {
        $options = $include_all_url->getOptions();
        if (!empty($options['query']['f'])) {
          foreach ($options['query']['f'] as $k => $facet_parameter) {
            if (strpos($facet_parameter, $query_string . ':') === 0) {
              unset($options['query']['f'][$k]);
            }
          }
        }
        $include_all_url->setOptions($options);
      }
      $include_all['#attributes']['data-url'] = $include_all_url->toString();

      // Add separate list of active items.
      if ($separate_active || $include_summary) {
        $active_items = $this->getActiveResults($facet->getResults(), $facet, $include_summary);
      }
    }

    if (!empty($active_items['summary'])) {
      $summary = [
        '#theme' => 'bps_facet_summary',
        '#active_count' => count($active_items['summary']),
        '#active_items' => $active_items['summary'],
        '#facet_name' => $query_string,
        '#wrapper_attributes' => [
          'class' => ['facet-item', 'facet-item-facet-summary'],
        ],
      ];
    }

    array_unshift($build['#items'], $include_all);
    $build_copy = ['facet_items' => $build];
    $build['#wrapper_attributes'] = ['class' => ['facet-item', 'facet-item-facet-items']];
    $build_copy['#items'] = !empty($summary) ? [$summary, $build] : [$build];
    

    if ($separate_active && !empty($active_items['active_results'])) {
      $build_copy['active_items'] = [
        '#theme' => $this->getFacetItemListThemeHook($facet),
        '#items' => $active_items['active_results'],
        '#context' => ['list_item' => 'facet_list_active_items'],
        '#attributes' => ['data-drupal-facet-id' => $facet->id(), 'class' => ['list-facet-items--active']],
      ];
    }
    return $build_copy;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildListItems(FacetInterface $facet, ResultInterface $result) {
    if ($result->getCount() == -1) {
      return [];
    }

    $items = parent::buildListItems($facet, $result);
    return $items;
  }

  /**
   * Recursive function to get active facet results.
   *
   * @param array $results
   *  An array of facet Result objects
   * @param FacetInterface $facet
   *  The facet object
   * @param bool $include_summary
   *  Whether to include active items summary.
   *
   * @return array
   *  An array of active results as links.
   */
  protected function getActiveResults(array $results, FacetInterface $facet, bool $include_summary = FALSE) {
    $active = [];
    $summary = [];
    foreach($results as $result) {
      if ($result->isActive()) {
        $link = $this->prepareLink($result);
        $link['#title']['#show_count'] = FALSE;
        $link['#attributes'] = [
          'class' => ['is-active'],
          'data-drupal-facet-item-id' => $facet->getUrlAlias() . '-' . str_replace(' ', '-', $result->getRawValue()),
          'data-drupal-facet-item-value' => $result->getRawValue(),
        ];
        $link['#wrapper_attributes'] = ['class' => ['facet-item']];
        $active[$result->getRawValue()] = $link;
        if ($include_summary) {
          $summary[] = $result->getDisplayValue();
        }
      }
      if ($result->hasActiveChildren()) {
        $active_children = $this->getActiveResults($result->getChildren(), $facet, $include_summary);
        if (!empty($active_children['active_results'])) {
          $active = array_merge($active, $active_children['active_results']);
        }
        if (!empty($active_children['summary'])) {
          $summary += $active_children['summary'];
        }
      }
    }

    return [
      'active_results' => $active,
      'summary' => $summary,
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $form = parent::buildConfigurationForm($form, $form_state, $facet);

    $form['separate_active_items'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Separate Active Items'),
      '#default_value' => $this->getConfiguration()['separate_active_items'],
      '#description' => $this->t('Add active items as a separate group for theming purposes'),
    ];

    $form['display_active_items_summary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display Summary of Active Items'),
      '#default_value' => $this->getConfiguration()['display_active_items_summary'],
      '#description' => $this->t('Display a summary of active items for this facet.'),
    ];

    return $form;
  }

}