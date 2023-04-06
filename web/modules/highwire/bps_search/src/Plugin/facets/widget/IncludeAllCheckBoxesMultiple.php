<?php

namespace Drupal\bps_search\Plugin\facets\widget;

use Drupal\facets\FacetInterface;
use Drupal\facets\Result\ResultInterface;
use Drupal\facets\Plugin\facets\processor\UrlProcessorHandler;

/**
 * The checkbox multiple widget.
 *
 * @FacetsWidget(
 *   id = "bps_include_all_checkbox_multiple",
 *   label = @Translation("Bps: List of checkboxes with an include all option and the ability to select multiple checkboxes"),
 *   description = @Translation("A list of checkboxes with an include all option and the ability to select multiple checkboxes"),
 * )
 */
class IncludeAllCheckBoxesMultiple extends IncludeAllCheckBoxes {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $build = parent::build($facet);

    $cancel_button = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#attributes' => ['id' => [$facet->get('id') . '-cancel'], 'class' => ['bps-checkbox-multiple-cancel', 'btn-secondary'],'data-bs-dismiss'=>['modal'],]
    ];

    $apply_button = [
      '#type' => 'button',
      '#value' => $this->t('Apply'),
      '#attributes' => ['id' => [$facet->get('id') . '-apply'], 'class' => ['bps-checkbox-multiple-apply', 'btn-primary'], 'disabled' => 'disabled']
    ];
    $build['facet_items']['#items'][] = [
      $cancel_button,
      $apply_button,
      '#wrapper_attributes' => [
        'class' => ['bps-facets-form-actions'],
      ],
    ];

    $build['facet_items']['#attributes']['class'][] = 'bps-checkbox-multiple-facet';
    $build['facet_items']['#attributes']['data-drupal-facet-active-item-count'] = count($facet->getActiveItems());
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildListItems(FacetInterface $facet, ResultInterface $result) {
    if ($result->getCount() == -1) {
      return [];
    }

    $classes = ['facet-item'];
    $items = $this->prepareLink($result);

    $children = $result->getChildren();
    // Check if we need to expand this result.
    if ($children && ($this->facet->getExpandHierarchy() || $result->isActive() || $result->hasActiveChildren())) {

      $child_items = [];
      $classes[] = 'facet-item--expanded';
      foreach ($children as $child) {
        $child_items[] = $this->buildListItems($facet, $child);
      }

      $items['children'] = [
        '#items' => $child_items,
      ];

      if ($result->hasActiveChildren()) {
        $classes[] = 'facet-item--active-trail';
      }

    }
    else {
      if ($children) {
        $classes[] = 'facet-item--collapsed';
      }
    }

    if ($result->isActive()) {
      $items['#attributes'] = ['class' => ['is-active']];
    }

    // Custom bps code for getting the url arg into a data attribute.
    $url_processor = NULL;
    $processors = $this->facet->getProcessors();
    foreach ($processors as $processor) {
      if ($processor instanceof UrlProcessorHandler) {
        $url_processor = $processor->getProcessor();
      }
    }

    $items['#wrapper_attributes'] = ['class' => $classes];
    $items['#attributes']['data-drupal-facet-item-id'] = $this->facet->getUrlAlias() . '-' . str_replace(' ', '-', $result->getRawValue());

    // Custom bps code for adding arg data attribute.
    $items['#attributes']['data-drupal-facet-item-arg'] = $this->facet->getUrlAlias() . $url_processor->getSeparator() . $result->getRawValue();

    $items['#attributes']['data-drupal-facet-item-value'] = $result->getRawValue();
    return $items;
  }

}