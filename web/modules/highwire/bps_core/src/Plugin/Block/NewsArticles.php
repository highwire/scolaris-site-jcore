<?php

namespace Drupal\bps_core\Plugin\Block;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\Entity\Node;

/**
 * Provides a 'NewsArticles' block.
 *
 * @Block(
 *  id = "news_articles",
 *  admin_label = @Translation("News Articles"),
 *  context = {
 *    "node" = @ContextDefinition(
 *      "entity:node",
 *      label = @Translation("Current Node")
 *    )
 *  }
 * )
 */
class NewsArticles extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new TableOfContentsBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->getContextValue('node');

    if ($node->getType() == 'journal' || $node->getType() == 'journal_issue') {
      $journal = $node->id();
    }
    elseif ($node->hasField('parent_journal') && !$node->get('parent_journal')->isEmpty()) {
      $journal = $node->get('parent_journal')->getString();
    }
    else {
      return [];
    }

    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'news_article')
      ->condition('status', TRUE)
      ->condition('field_news_article_content_ref', $journal)          
      ->sort('field_news_article_date', 'desc');
    
    $result = $query->execute();
    if (empty($result)) {
      return [];
    }
    
    $nodes = Node::loadMultiple($result);

    $view_builder = $this->entityTypeManager->getViewBuilder('node');
    foreach ($nodes as $nid => $node) {
      $build[$nid] = $view_builder->view($node, 'teaser');
    }

    return $build;
  }
}