<?php

namespace Drupal\bps_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\highwire_content\HighWireContent;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Drupal\highwire_content\ContentSettings;
use Drupal\bps_core\Lookup;

/**
 * @Block(
 *   id = "bps_content_export_block",
 *   admin_label = @Translation("BPS Content Export"),
 *   category = @Translation("BPS")
 * )
 */
class BPSContentExport extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity query factory.
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $queryFactory;

  /**
   * The entity type manager.
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Module handler to look up module info.
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Content Settings Object.
   *
   * @var \Drupal\highwire_content\ContentSettings
   */
  protected $contentSettings;

  /**
   * BPS Lookup.
   *
   * @var \Drupal\bps_core\Lookup
   */
  protected $lookup;

  /**
   * Create a markup block
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
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   The entity query factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   Drupal module handler.
   * @param \Drupal\highwire_content\ContentSettings $content_settings
   *   ContentConfig to verify saved config.
   * @param \Drupal\bps_core\Lookup $lookup
   *   Lookup helper to find bps specific data.
   */
  public function __construct(
    array $configuration, $plugin_id, $plugin_definition,
    QueryFactory $query_factory,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandler $module_handler,
    ContentSettings $content_settings,
    Lookup $lookup
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->queryFactory = $query_factory;
    $this->moduleHandler = $module_handler;
    $this->contentSettings = $content_settings;
    $this->lookup = $lookup;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.query'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('highwire_content.settings'),
      $container->get('bps_core.lookup')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $last_update = \Drupal::config('bps_core.cron.titles_export')->get('timestamp');
    $last_update_message = date('M d, Y - H:i T', $last_update);

    // Get title export types.
    $types = $this->lookup->getTitleExportTypes();
    if (empty($types)) {
      return $build;
    }

    // Get labels from the highwire content settings.
    $labels = $this->contentSettings->getContentTypeLabelsPlural();
    if (empty($labels)) {
      return $build;
    }

    // Setup items.
    foreach ($types as $type) {
      if (empty($labels[$type])) {
        $items[$type] = $type;
      }

      $items[$labels[$type]] = $type;
    }

    foreach ($items as $item => $type) {
      if (empty($item)) {
        continue;
      }
      $url = Url::fromRoute('bps_core.download.item_type_content_export', ['item_type' => $type]);
      $links[$item] = [
        '#title' => $this->t($item),
        '#type' => 'link',
        '#url' => $url,
        '#attributes' => [
          'class' => ['btn', 'btn-primary'],
        ],
      ];
    }

    $content_export_list = [
      '#theme' => 'item_list',
      '#items' => $links,
      '#attributes' => [
        'class' => ['list-inline', 'bps-content-export-list'],
      ],
    ];

    $build['content_export'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['bps-content-export']],
      'content_export_prefix' => ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('Download the list of content available on AccessEngineering for:')],
      'content_export_list' => $content_export_list,
      'content_export_suffix' => ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('CSV files last updated: @last_update_message.', ['@last_update_message' => $last_update_message])],
    ];

    return $build;
    
  }
}
