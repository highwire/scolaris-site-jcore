<?php
namespace Drupal\bps_search\Plugin\facets\widget;

use Drupal\facets\FacetInterface;
use Drupal\facets\Plugin\facets\widget\LinksWidget;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\Result\Result;
use Drupal\facets\Result\ResultInterface;
/**
 * The checkbox / radios widget.
 *
 * @FacetsWidget(
 *   id = "bpscheckbox_content_type",
 *   label = @Translation("List of BPS checkboxes for content type"),
 *   description = @Translation("A configurable widget that shows a list of checkboxes"),
 * )
 */
class BPSContentTypeSelectCheckboxes extends LinksWidget {

  /**
  * {@inheritdoc}
  */
  public function defaultConfiguration() {
    return [
      'show_reset_link' => FALSE,
      'reset_text' => $this->t('Everything'),
      'show_reset_count' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $form = parent::buildConfigurationForm($form, $form_state, $facet);

    $form['show_reset_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show reset link'),
      '#default_value' => $this->getConfiguration()['show_reset_link'],
    ];
    $form['reset_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reset text'),
      '#default_value' => $this->getConfiguration()['reset_text'],
      '#states' => [
        'visible' => [
          ':input[name="widget_config[show_reset_link]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="widget_config[show_reset_link]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['reset_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Reset id'),
        '#default_value' => $this->getConfiguration()['reset_id'],
        '#states' => [
          'visible' => [
            ':input[name="widget_config[show_reset_link]"]' => ['checked' => TRUE],
          ],
          'required' => [
            ':input[name="widget_config[show_reset_link]"]' => ['checked' => TRUE],
          ],
        ],
      ];
    $form['show_reset_count'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show reset count'),
      '#default_value' => $this->getConfiguration()['show_reset_count'],
    ];

    return $form;
  }



  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $build = parent::build($facet);
    $build['#attributes']['class'][] = 'js-facets-checkbox-links';
    $build['#attached']['library'][] = 'bps_search/drupal.bps_search.facets';
    $build['#attached']['library'][] = 'facets/drupal.facets.checkbox-widget';
    $query_string = $facet->getUrlAlias();
    // Check straight away if show reset link is selected
    if ($this->getConfiguration()['show_reset_link']) {
      // Setup total count of all items
      $totalCount = NULL;
      if ($this->getConfiguration()['show_reset_count']) {
        $count = 0;
        foreach ($facet->getResults() as $results) {         
          $totalCount += $results->getCount();
        }
      }

      // Build url for 'include all' option from first result.
      $query_string = $facet->getUrlAlias();
      $result_url = $facet->getResults()[0]->getUrl();
      $include_all_url = '';
      if ($result_url) {
        $include_all_url = new Url($result_url->getRouteName(), $result_url->getRouteParameters(), $result_url->getOptions());
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
        $reset_url = $include_all_url->setOptions($options);
      }  
      if (array_key_exists('#items', $build)) {
        $include_all = [
          '#type' => 'link',
          '#url' => $reset_url,
          '#title' => [
            '#theme' => "facets_result_item",
            '#is_active' => FALSE,
            '#value' => $this->getConfiguration()['reset_text'],
            '#show_count' => $this->getConfiguration()['show_reset_count'],
            '#count' => $totalCount,
            '#facet' => $facet,
          ],
          '#wrapper_attributes' => [
            'class' => [
            'facet-item',
            ],
          ],
          '#attributes' => [
            'data-drupal-facet-item-id' => $this->getConfiguration()['reset_id'],
            'data-drupal-facet-item-value' => "reset",
          ],
        ];
        // Add active class to 'all items' link if there are no active facet items.
        if (empty($facet->getActiveItems())) {
          $include_all['#attributes']['class'][] = 'is-active';
        }
        array_unshift($build['#items'], $include_all);
        
      }
    }
    return $build;
  }
}