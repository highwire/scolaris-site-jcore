<?php

namespace Drupal\bps_content_display\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\highwire_content\Lookup as HighWireContentLookup;
use Drupal\highwire_content\TocLookup as HighWireTocLookup;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to display the pager for a chapter/section.
 *
 * @Block(
 *   id = "bps_toc_pager",
 *   admin_label = @Translation("TOC Pager"),
 *   category = @Translation("bps"),
 *   context = {
 *     "node" = @ContextDefinition(
 *       "entity:node",
 *       label = @Translation("Current Node")
 *     )
 *   }
 * )
 */
class TOCPager extends BlockBase implements ContainerFactoryPluginInterface
{
  /**
   * The node the block is displayed on.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $contextNode;
  /**
   * Lookup for getting nids from apaths.
   *
   * @var \Drupal\highwire_content\Lookup
   */
  protected $hwContentLookup;
  /**
   * Lookup for getting toc structure data.
   *
   * @var \Drupal\highwire_content\TocLookup
   */
  protected $hwTocLookup;

  /**
   * The currently active route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Create block to display the previous and next links for chapter/section.
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
   * @param \Drupal\highwire_content\Lookup $hw_content_lookup
   *   Lookup helper to find nids from apaths.
   * @param \Drupal\highwire_content\TocLookup $hw_toc_lookup
   *   Lookup helper to find toc structure data.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The currently active route match object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition,
    HighWireContentLookup $hw_content_lookup,
    HighWireTocLookup $hw_toc_lookup,
    RouteMatchInterface $route_match
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->hwContentLookup = $hw_content_lookup;
    $this->hwTocLookup = $hw_toc_lookup;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('highwire_content.lookup'),
      $container->get('highwire_content.toc_lookup'),
      $container->get('current_route_match')
    );
  }

 /**
   * {@inheritdoc}
   */
  public function build() {
    // Get the node from context data.
    $node = $this->getContextValue('node');
    $build = [];
    
    // Check for required fields we need to get the prev/next item.
    // if we don't have any one of these, return an empty render array.
    $req_fields = ['apath', 'parent'];
    foreach ($req_fields as $field) {
      if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
        return $build;
      }
    }

    // Get apath of context node.
    $current_apath = $node->get('apath')->getString();

    // Get parent apath from parent nid.
    $parent_nid = $node->get('parent')->getString();
    $parent_node = \Drupal::entityTypeManager()->getStorage('node')->load($parent_nid);

    $children = [];

    foreach ($parent_node->get('children')->getValue() as $child) {
      // SF01154128 - do not include covers in pagination
      $type = $this->hwContentLookup->getTypeFromNID($child['target_id']);
      if ($type !== 'item_cover') {
        $children[] = $child['apath'];
      }
    }

    $prev_nid = FALSE;
    $next_nid = FALSE;

    foreach ($children as $key => $apath) {
      if ($apath == $current_apath) {
        if ($key != 0) {
          try {
            $prev_nid = $this->hwContentLookup->nidFromApath($children[$key - 1]);
          }
          catch (ApathNotFoundException $e) {
          }
        }
        if (array_key_exists($key + 1, $children)) {
          try {
            $next_nid = $this->hwContentLookup->nidFromApath($children[$key + 1]);
          }
          catch (ApathNotFoundException $e) {
          }
        }
        break;
      }
    }

    // Build render array with empty links as default values.
    $build += [
      '#theme' => 'bps_toc_pager',
      '#previous_url' => Url::fromRoute('<nolink>'),
      '#next_url' => Url::fromRoute('<nolink>'),
    ];

    // Add url for previous node.
    if ($prev_nid) {
      $build['#previous_url'] = $this->getItemUrl($prev_nid);
    }

    // Add url for next node.
    if ($next_nid) {
      $build['#next_url'] = $this->getItemUrl($next_nid);
    }

    $route_name = $this->routeMatch->getRouteName();
    return $build;
  }

  /**
   * Get the url for a pager item.
   *
   * @param string $nid
   *   The node id of the item for which to get the Url.
   *
   * @return \Drupal\Core\Url
   *   A Url object for the given item nid.
   */
  protected function getItemUrl($nid) {
    if ($this->routeMatch->getRouteName() == 'highwire_entity_view') {
      $route_name = $this->routeMatch->getRouteName();
      $route_params = $this->routeMatch->getParameters()->all();
      $route_params['entity_id'] = $nid;
    }
    else {
      $route_name = 'entity.node.canonical';
      $route_params = ['node' => $nid];
    }
    return Url::fromRoute($route_name, $route_params);
  }
}