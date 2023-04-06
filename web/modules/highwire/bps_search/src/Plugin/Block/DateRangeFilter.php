<?php

namespace Drupal\bps_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;


/**
 * Provides a block to search date filter.
 *
 * @Block(
 *   id = "bps_search_date_filter",
 *   admin_label = @Translation("bps Search Date Filter"),
 *   category = @Translation("bps"),
 * )
 */
class DateRangeFilter extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Request service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Create block to display date filter.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request service.
   * @param \Psr\Log\LoggerInterface $logger
   *   Drupal logging object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RequestStack $request_stack,
    LoggerInterface $logger
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->requestStack = $request_stack;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('logger.factory')->get('bps_search')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [];

    $query = $this->requestStack->getCurrentRequest()->query;
    $options = ['query' => !empty($query) ? $query->all() : []];

    // Set the correct default value when date facet is active.
    $items['include_options'] = 0;
    if (!empty($options['query']['date-max']) || !empty($options['query']['date-min'])) {
      $items['include_options'] = 1;
    }

    $build['range'] = \Drupal::formBuilder()->getForm('Drupal\bps_search\Form\FacetsDateRangeForm', $items);

    // Build active items for render array.
    $active_items_render = [];
    if (!empty($options['query']['date-min'])) {
      $path = $this->requestStack->getCurrentRequest()->getPathInfo();
      $remove_min_query = $this->requestStack->getCurrentRequest()->query->all();
      if (!empty($remove_min_query['date-min'])) {
        unset($remove_min_query['date-min']);
      }
      $remove_min_options = ['query' => !empty($remove_min_query) ? $remove_min_query : []];

      $remove_url = Url::fromUserInput(urldecode($path), $remove_min_options);
      $title = [
        '#markup' => $this->t('From @date-start', ['@date-start' => $options['query']['date-min']]),
        '#prefix' => '<span class="facet-item__value facet-item__value--active">',
        '#suffix' => '</span>',
      ];
      $active_items_render['min'] = (new Link($title, $remove_url))->toRenderable();
      $active_items_render['min']['#wrapper_attributes'] = ['class' => ['facet-item']];
      $active_items_render['min']['#attributes']['data-drupal-facet-item-value'] = $options['query']['date-min'];
    }

    if (!empty($options['query']['date-max'])) {
      $path = $this->requestStack->getCurrentRequest()->getPathInfo();
      $remove_max_query = $this->requestStack->getCurrentRequest()->query->all();
      if (!empty($remove_max_query['date-max'])) {
        unset($remove_max_query['date-max']);
      }
      $remove_max_options = ['query' => !empty($remove_max_query) ? $remove_max_query : []];

      $remove_url = Url::fromUserInput(urldecode($path), $remove_max_options);
      $title = [
        '#markup' => $this->t('To @date-end', ['@date-end' => $options['query']['date-max']]),
        '#prefix' => '<span class="facet-item__value facet-item__value--active">',
        '#suffix' => '</span>',
      ];
      $active_items_render['max'] = (new Link($title, $remove_url))->toRenderable();
      $active_items_render['max']['#wrapper_attributes'] = ['class' => ['facet-item']];
      $active_items_render['max']['#attributes']['data-drupal-facet-item-value'] = $options['query']['date-max'];
    }

    $build['active_items'] = [
      '#theme' => 'item_list',
      '#items' => $active_items_render,
      '#attributes' => ['data-drupal-facet-id' => 'bps_search_date_filter', 'class' => ['list-facet-items--active', 'list-unstyled']],
      '#cache' => [
        'contexts' => [
          'url.path',
          'url.query_args',
        ],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    foreach ($form_state->getValues() as $k => $v) {
      $this->configuration[$k] = $v;
    }
  }

}
