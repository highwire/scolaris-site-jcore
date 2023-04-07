<?php

namespace Drupal\bps_ecommerce\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\FormStateInterface;
use HighWire\Clients\Catalog\Catalog;
use HighWire\Clients\Catalog\Product;
use HighWire\Clients\Catalog\Price;
use HighWire\Clients\Catalog\PricingItem;
use Drupal\highwire_content\Lookup;
use Drupal\node\Entity\Node;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\highwire_ecommerce\AddToCartLink;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;
use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;
use HighWire\Utility\IntervalFormatter;
use Drupal\highwire_content\Exception\ApathNotFoundException;
use GuzzleHttp\Exception\ServerException;
use HighWire\Clients\Atomx\Atomx;
use Drupal\Core\Cache\CacheBackendInterface;
 
/**
 * Provides a block to display the price for an item.
 *
 * @Block(
 *   id = "bps_display_price",
 *   admin_label = @Translation("BPS Display Price"),
 *   category = @Translation("BPS"),
 *   context = {
 *     "node" = @ContextDefinition(
 *       "entity:node",
 *       label = @Translation("Current Node")
 *     )
 *   }
 * )
 */
class DisplayPrice extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The node the block is displayed on.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $contextNode;

  /**
   * An array of loaded nodes from the catalog response.
   *
   * @var array
   */
  protected $nodes;

  /**
   * A boolean to indicate whether or not the current user has access to the content.
   *
   * @var boolean
   */
  protected $userAccess;

  /**
   * An array of apaths of children that the user has access to.
   *
   * @var array
   */
  protected $userAccessChildren;

  /**
   * An array of apaths of children that the user does not have access to.
   *
   * @var array
   */
  protected $userNoAccessChildren;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * Drupal's module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The entity display repository manager.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface|null
   */
  protected $entityDisplayRepository;

  /**
   * Add to cart link service.
   *
   * @var \Drupal\highwire_ecommerce\AddToCartLink
   */
  protected $addToCartLink;

  /**
   * Add to cart link service.
   *
   * @var \Drupal\highwire_content\Lookup;
   */
  protected $lookup;

  /**
   * Catalog service.
   *
   * @var \HighWire\Clients\Catalog\Catalog
   */
  protected $catalog;

  /**
   * Request service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The foxycart apikey. This needs to be set in settings.php for the site.
   * (e.g. $config['highwire_ecommerce']['apikey'] = {apikey_value})
   *
   * @var string
   */
  protected $apikey;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Atomx client
   *
   * @var \HighWire\Clients\Atomx\Atomx
   */
  protected $atomx;

  /**
   * Drupal default cache bin.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $defaultCache;

  /**
   * Create block to display the access control bar for purchase offers.
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   Drupal module handler.
   * @param \Drupal\highwire_ecommerce\AddToCartLink $add_to_cart_link
   *   Add to cart link service.
   * @param \Drupal\highwire_content\Lookup $lookup
   *   Highwire content lookup service.
   * @param \HighWire\Clients\Catalog\Catalog $catalog
   *   Catalog service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request service.
   * @param \HighWire\Clients\Atomx\Atomx $atomx
   *   Atomx client.
   * @param \Drupal\Core\Cache\CacheBackendInterface $default_cache
   *   Drupal cache default bin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandler $module_handler,
    EntityDisplayRepositoryInterface $entity_display_repository,
    AddToCartLink $add_to_cart_link,
    Lookup $lookup,
    Catalog $catalog,
    RequestStack $request_stack,
    ConfigFactory $config_factory,
    LoggerInterface $logger,
    Atomx $atomx,
    CacheBackendInterface $default_cache
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->addToCartLink = $add_to_cart_link;
    $this->lookup = $lookup;
    $this->catalog = $catalog;
    $this->requestStack = $request_stack;
    $this->userAccess = FALSE;
    $this->logger = $logger;
    $this->apikey = $config_factory->get('highwire_ecommerce')->get('apikey');
    $this->atomx = $atomx;
    $this->defaultCache = $default_cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('entity_display.repository'),
      $container->get('highwire_ecommerce.add_to_cart_link'),
      $container->get('highwire_content.lookup'),
      $container->get('highwire_client.factory')->get('hwphpclient:catalog'),
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('logger.factory')->get('bps_ecommerce'),
      $container->get('hwphp.atomx'),
      $container->get('cache.default')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
  
    // Create block render array.
    $build = ['#theme' => 'bps_access_panel', '#user_access' => FALSE];

    // Get the current node data.
    $this->contextNode = $this->getContextValue('node');

    // Check if user has full access to this content.
    if ($this->userHasAccess($this->contextNode)) {

    // Return has access panel.
      $this->userAccess = TRUE;
      $build['#user_access'] = TRUE;
      return $build;
    }
    $this->checkAccessChildren();
    if ($this->userAccess) {
      $build['#user_access'] = TRUE;
      return $build;
    }

    // Temp for p2: add node type
    $build['#access_content_type'] = $this->contextNode->getType();

    // Build display for children the user already has access to.
    if ($this->showChildren()) {
      $build['#user_access_already'] = $this->buildUserAccessChildren();
    }

    // Build display for children the user already has access to.
    if (in_array($this->contextNode->getType(), HW_NODE_TYPE_TOC)) {
      $build['#user_access'] = TRUE;
    }

    // Get the catalog data for the context data.
    $node_apath = isset($this->contextNode->apath) ? $this->contextNode->apath->value : '';
    try {
      $offers_response = $this->catalog->getOffer([$node_apath], TRUE, TRUE);
      $offers = $offers_response->getData();
    }
    catch (\Exception $ex) {
      $this->logger->error($ex->getMessage());
      return $build;
    }
    // Load all the nodes for apaths in the Catalog response.
    $apaths = $offers->getAllApaths();
    try {
      if ($nids = $this->lookup->nidsFromApaths($apaths));
    }
    catch (ApathNotFoundException $ex) {
      if (empty($nids)) {
        return $build;
      }
    }

    // Add nodes for lookup later.
    $this->nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    // Get the User's Currency
    try {
      $user_ip = $this->requestStack->getCurrentRequest()->getClientIp();
      $user_currency = $this->catalog->getUserCurrency($user_ip)->getData();
      if (empty($user_currency)) {
        return $build;
      }
    }
    catch (\Exception $ex) {
      $this->logger->error($ex->getMessage());
      return $build;
    }

    // Get apaths for products.
    $product_apaths = $offers->getAllProductApaths();
    $pricing_items = $offers->getPricingItems();

    // Loop through offers response.
    foreach ($pricing_items as $pricing_item) {
      $grouped_products = $this->groupProductsByType($pricing_item);
      $types = array_keys($grouped_products);
      $container_type = end($types);

      // Build pricing item list per type.
      $pricing_item_products = [];
      foreach ($grouped_products as $type => $products) {
        $title = $this->buildPurchaseOffersTitle($type, $container_type);
        $products_list = $this->buildPurchaseOffers($products, $user_currency, !empty($pricing_item_products) ? 'Or ' . lcfirst($title) : $title);
        if (!empty($products_list)) {
          $pricing_item_products[] = $products_list;
        }
      }

      // Add pricing item lists to render array.
      if (!empty($pricing_item_products)) {
        $build['#pricing_items'][] = $pricing_item_products;
      }
    }

    // Build child offers prompt text.
    $child_offers_prompt = $this->buildChildOffersPrompt();
    if (!empty($child_offers_prompt)) {
      $build['#purchase_children'] = $child_offers_prompt;
    }
    $build['#cache']['contexts'][] = 'user'; 
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'list_titles' => [
        'user_access_already' => 'You already have access to these :types:',
        'purchase_offers_current' => 'Get access to this :type:',
        'purchase_offers_container' => 'Get access to the entire :type:',
        'purchase_offers_default' => 'Get access to the :type:',
      ],
      'child_offers_prompt' => [
        'title' => 'Get access to individual :types:',
        'text' => 'Open :a :type to see its purchasing options.',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $form['list_titles'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#suffix' => '<p><strong>Note:</strong> For the above titles, you may use ":type" and ":types" to represent the singular or plural label of the respective content type.</p><hr>',
    ];

    $form['list_titles']['user_access_already'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title for list of items user already has access to'),
      '#description' => $this->t('Displayed when there are children the user has access to (e.g. list of chapters on book page).'),
      '#default_value' => isset($this->configuration['list_titles']['user_access_already']) ? $this->configuration['list_titles']['user_access_already'] : '',
    ];

    $form['list_titles']['purchase_offers_current'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title for purchase offers for currently viewed item'),
      '#description' => $this->t('Displayed above list of purchase offers for the item being viewed.'),
      '#default_value' => isset($this->configuration['list_titles']['purchase_offers_current']) ? $this->configuration['list_titles']['purchase_offers_current'] : '',
    ];

    $form['list_titles']['purchase_offers_container'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title for purchase offers for the currently viewed item\'s root parent element'),
      '#description' => $this->t('Displayed when there are purchase offers for the root parent element of the currently viewed item (e.g. the book on a chapter page, or the journal on an article page).'),
      '#default_value' => isset($this->configuration['list_titles']['purchase_offers_container']) ? $this->configuration['list_titles']['purchase_offers_container'] : '',
    ];

    $form['list_titles']['purchase_offers_default'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title for purchase offers for other items besides the current and root parent'),
      '#description' => $this->t('Displayed when there are purchase offers for other items besides the currently viewed item or its root parent (e.g. issues on an article page).'),
      '#default_value' => isset($this->configuration['list_titles']['purchase_offers_default']) ? $this->configuration['list_titles']['purchase_offers_default'] : '',
    ];

    $form['child_offers_prompt'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#suffix' => '<p><strong>Note:</strong> For the above titles, you may use ":type" and ":types" to represent the singular or plural label of the respective content type. <br>You can also use ":a" before the type token to dynamically change based on whether the type string starts with a vowel.</p><hr>',
    ];

    $form['child_offers_prompt']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title of prompt to instruct users how to buy child content'),
      '#description' => $this->t('Displayed when there are purchase offers for children of the currently viewed item (e.g. chapters on a book page).'),
      '#default_value' => isset($this->configuration['child_offers_prompt']['title']) ? $this->configuration['child_offers_prompt']['title'] : '',
    ];

    $form['child_offers_prompt']['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text of prompt to instruct users how to buy child content'),
      '#description' => $this->t('Displayed when there are purchase offers for children of the currently viewed item (e.g. chapters on a book page).'),
      '#default_value' => isset($this->configuration['child_offers_prompt']['text']) ? $this->configuration['child_offers_prompt']['text'] : '',
    ];

    // Add display mode options per node type.
    $form['view_modes'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    // Try to filter the node types based on the selection criteria of a page variant.
    $node_types = [];
    $storage = $form_state->getStorage();
    if (!empty($storage['machine_name']) && strpos($storage['machine_name'], '--') !== FALSE) {
      list($page_id, $page_variant_id) = explode('--', $storage['machine_name']);
      try {
        $page = $this->entityTypeManager->getStorage('page')->load($page_id);
        $page_variant = $page->getVariant($page_variant_id);
        $page_variant_config = $page_variant->getSelectionConditions()->getConfiguration();
      }
      catch (\Exception $e) {
        $page_variant_config = [];
      }

      if (!empty($page_variant_config)) {

        // Look for the node type selection criteria.
        foreach ($page_variant_config as $variant_config) {
          if (in_array($variant_config['id'], ['entity_bundle:node', 'node_type']) && !empty($variant_config['bundles'])) {
            $node_types = array_keys($variant_config['bundles']);
          }
        }
      }
    }

    // Only display types related to the node type selection criteria
    // (i.e. books + book chunks, journals + journal chunks, refbooks + refbook chunks).
    foreach ($node_types as $type) {
      if (in_array($type, bps_core_get_book_chunk_types()) || $type == HW_NODE_TYPE_MONOGRAPH) {
        $node_types = bps_core_get_book_chunk_types();
        $node_types[] = HW_NODE_TYPE_MONOGRAPH;
        break;
      }

      if (in_array($type, bps_core_get_book_chunk_types()) || $type == HW_NODE_TYPE_REPORT_GUIDELINE) {
        $node_types = bps_core_get_book_chunk_types();
        $node_types[] = HW_NODE_TYPE_REPORT_GUIDELINE;
        break;
      }

      if (in_array($type, bps_core_get_journal_chunk_types()) || $type == HW_NODE_TYPE_JOURNAL) {
        $node_types = bps_core_get_journal_chunk_types();
        $node_types[] = HW_NODE_TYPE_JOURNAL;
        break;
      }

      if ($type == HW_NODE_TYPE_TEST_REVIEW) {
        $node_types[] = HW_NODE_TYPE_TEST_REVIEW;
        break;
      }
    }

    // If we weren't able to find a panel page with a node type config,
    // default to all highwire node types.
    if (empty($node_types)) {
      $node_types = $this->lookup->getHighWireContentTypes();
    }
    foreach ($node_types as $node_type) {
      $view_modes = ['' => t('None')];
      $view_modes += $this->entityDisplayRepository->getViewModeOptionsByBundle('node', $node_type);
      $form['view_modes'][$node_type] = [
        '#type' => 'select',
        '#title' => $this->t('View mode for bundle %bundle', ['%bundle' => $node_type]),
        '#options' => $view_modes,
        '#default_value' => !empty($this->configuration['view_modes'][$node_type]) ? $this->configuration['view_modes'][$node_type] : 'default',
      ];
    }

    // Add Access Control Rule.
    if ($this->moduleHandler->moduleExists('highwire_access_control')) {
      $entities = $this->entityTypeManager->getStorage('access_control_rule')->getQuery()
        ->execute();
      if (!empty($entities) && is_array($entities)) {
        $access_options = [];
        $access_options[''] = '--None--';
        foreach ($entities as $entity_id) {
          $access_rule = $this->entityTypeManager->getStorage('access_control_rule')->load($entity_id);
          $access_options[$entity_id] = $access_rule->get('label');
        }
        $form['access_control_rule'] = [
          '#type' => 'select',
          '#title' => t('Access Control Rule'),
          '#options' => $access_options,
          '#default_value' => !empty($this->configuration['access_control_rule']) ? $this->configuration['access_control_rule'] : '',
        ];
      }
    }

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

  /**
   * Build product node display.
   *
   * @param \Drupal\node\Entity\Node $product_node
   *   The drupal node for the product.
   *
   * @return array
   *   Render array for the product node (as either a view mode or a link).
   */
  public function getProductDisplay(Node $product_node): array {
    $view_builder = $this->entityTypeManager->getViewBuilder('node');
    $node_type = $product_node->getType();
    $build = [];

    $view_mode = '';
    if (!empty($this->configuration['view_modes']) && !empty($this->configuration['view_modes'][$node_type])) {
      $view_mode = $this->configuration['view_modes'][$node_type];
    }
    if (!empty($view_mode)) {
      $build = $view_builder->view($product_node, $view_mode);
    }
    else {
      $product_link = $product_node->toLink();
      if ($product_link) {
        $build = $product_link->toRenderable();
      }
    }
    return $build;
  }

  /**
   * Get the node for a product.
   *
   * @param \HighWire\Clients\Catalog\Product $product
   *   The product from the Catalog request.
   *
   * @return \Drupal\node\Entity\Node
   *   The Drupal node for the product.
   */
  public function getProductNode(Product $product) {
    $product_id = $product->getId();
    foreach ($this->nodes as $node) {
      if ($node->apath->value == $product_id) {
        return $node;
      }
    }
  }

  /**
   * Get the node by product id.
   *
   * @param string $id
   *   Id / apath for the node to be returned.
   *
   * @return \Drupal\node\Entity\Node
   *   A drupal node object.
   */
  public function getProductNodeById(string $id) {
    foreach ($this->nodes as $node) {
      if ($node->apath->value == $id) {
        return $node;
      }
    }
  }

  /**
   * Check whether the current user has access to a given node.
   *
   * @param Drupal\node\Entity\Node $node
   *   Node to check access for.
   *
   * @return bool
   *   TRUE if the current user has access to the given node, FALSE if not.
   */
  public function userHasAccess(Node $node) {
    if (!$this->moduleHandler->moduleExists('highwire_access_control') || empty($this->configuration['access_control_rule'])) {
      return FALSE;
    }
    $access_rule = $this->entityTypeManager->getStorage('access_control_rule')->load($this->configuration['access_control_rule']);
    if (empty($node->apath) || empty($access_rule)) {
      return FALSE;
    }
    $apath = $node->apath->value;

    // Check user access.
    if (empty($apath)) {
      return FALSE;
    }
    $access_apaths = $access_rule->userHasAccess([$apath]);
    return !empty($access_apaths[$apath]);
  }

  /**
   * Function to check user access for all children.
   */
  protected function checkAccessChildren() {
     if (!$this->showChildren() || !$this->moduleHandler->moduleExists('highwire_access_control') || empty($this->configuration['access_control_rule'])) {
      return;
    }
    $access_rule = $this->entityTypeManager->getStorage('access_control_rule')->load($this->configuration['access_control_rule']);
    if (empty($access_rule)) {
      return;
    }

    // Get children of current node.
    $node = !empty($this->contextNode) ? $this->contextNode : $this->getContextValue('node');

    //$child_apaths = $this->getChildApaths($node);
    $apath = $node->get('apath')->value;

    // Check access and group apaths.
    $user_access_children = $user_noaccess_children = [];
    if (!empty($child_apaths)) {
      $access_apaths = $access_rule->userHasAccess([$apath]);
      foreach ($access_apaths as $apath => $has_access) {
        if ($has_access) {
          $user_access_children[$apath] = $apath;
          $this->userAccess = TRUE;
        }
        else {
          $user_noaccess_children[$apath] = $apath;
        }
      }
    }

    // Store apaths for later.
    $this->userAccessChildren = $user_access_children; 
    $this->userNoAccessChildren = $user_noaccess_children;

    // If the user has access to all the children, set access to true.
    // if (count($child_apaths) !== 0 && count($child_apaths) == count($user_access_children)) {
    //   $this->userAccess = TRUE;
    // }
  }

  /**
   * Get render array for list of children the current user already has access to.
   *
   * @return array
   *   Render array for an item_list of nodes.
   */
  protected function buildUserAccessChildren(): array {
    $build = [];

    // Check AC for items we have access for.
    $user_access = $this->userAccessChildren; 
    $children_users_have = $this->getChildrenUserHasAccessDisplay($user_access);
    if (!empty($children_users_have)) {
      $access_items = [];

      // Split children up by content type
      foreach ($children_users_have as $child) {
        if (isset($child['#node'])) {

          // Group book entry content types.
          if (in_array($child['#node']->getType(), ['item_front_matter', 'item_back_matter', 'item_chapter'])) {
            $access_items['book_entry'][] = $child;
          }
          else {
            $access_items[$child['#node']->getType()][] = $child;
          }
        }
      }
      $build = [];
      $title_config = !empty($this->configuration['list_titles']) ? $this->configuration['list_titles'] : [];
      foreach (array_keys($access_items) as $item_section) {
        $title = '';
        if (!empty($title_config['user_access_already'])) {
          $t_values = [':type' => $this->getTypeLabel($item_section), ':types' => $this->getTypeLabelPlural($item_section)];
          $title = $this->t($title_config['user_access_already'], $t_values);
        }

        $build[] = [
          '#title' => $title,
          '#theme' => 'item_list',
          '#items' => $access_items[$item_section],
          '#context' => ['list_style' => 'ecommerce_children'],
          '#attributes' => ['class' => ['list-unstyled']],
        ];
      }
    }
    return $build;
  }

  /**
   * Recursive function to check children access.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node to check children.
   */
  protected function getChildApaths(Node $node) {
    $cid = "bps_child_ac_paths:{$node->Id()}";
    $cache = $this->defaultCache->get($cid);
    $cache = FALSE;
    $results = [];
    if (!empty($cache->data)) {
      return $cache->data;
    }
    switch($node->getType()) {
      case HW_NODE_TYPE_JOURNAL:
        try {
          $corpus = $node->get('corpus')->value;
          $policy = $node->get('extract_policy')->value;
          $search_query = '{
              "size": 5000,
              "_source": ["apath"],
              "query": {
              "bool" : {
                "should": [
                    {"term": {"has-full-text": true}},
                    {"term": {"has-full-text-pdf": true}}
                  ]
                }
              }
            }
          ';
          $this->atomx->setIndexes([$policy . ":" . $corpus]);
          $results = array_keys($this->atomx->search($search_query));
          $this->defaultCache->set($cid, $results);
          return $results;
        }
        catch (\Exception $e) {
          return [];
        }
        break;

      case HW_NODE_TYPE_ISSUE:
        try {
          $apath = $node->get('apath')->value;
          $corpus = $node->get('corpus')->value;
          $policy = $node->get('extract_policy')->value;
          $search_query = '{
            "size": 5000,
            "_source": ["_type"],
            "query": {
              "bool" : {
                "must": [
                    {"term": {"parent-issue": "'. $apath .'"}},
                    {"bool": {
                      "should": [
                        {"term": {"has-full-text": true}},
                        {"term": {"has-full-text-pdf": true}}
                      ]
                    }
                  }
                ]
              }
            }
          }
          ';
          $this->atomx->setIndexes([$policy . ":" . $corpus]);
          $results = array_keys($this->atomx->search($search_query));
          $this->defaultCache->set($cid, $results);
          return $results;
        }
        catch (\Exception $e) {
          return [];
        }
        break;

      default:
        return [];
        break;
    }

    // $children = isset($node->children) && !$node->children->isEmpty() ? $node->children : [];
    // if (!isset($node->children) || $node->children->isEmpty()) {
    //   return;
    // }
    // foreach ($node->children as $child) {
    //   $child_node = $child->entity;
    //   $process_children = FALSE;

    //   // Books
    //   if ($child_node && isset($child_node->book_has_body) && !empty($child_node->book_has_body->value) && isset($child_node->apath)) {
    //     $process_children = TRUE;
    //   }

    //   // Journal articles
    //   if ($child_node && isset($child_node->has_full_text) && (!empty($child_node->has_full_text->value) || !empty($child_node->has_full_text_pdf->value)) && isset($child_node->apath)) {
    //     $process_children = TRUE;
    //   }

    //   // Journal
    //   if ($child_node->getType() == HW_NODE_TYPE_JOURNAL || $child_node->getType() == HW_NODE_TYPE_ISSUE) {
    //     $process_children = TRUE;
    //   }

    //   if ($process_children) {
    //     $child_apath = $child_node->apath->value;
    //     if (!empty($child_apath) && !in_array($child_apath, $child_apaths)) {
    //       $child_apaths[] = $child_node->apath->value;
    //     }
    //   }
    //   $this->getChildApathsRecursive($child_node, $child_apaths);
    // }
  }

  /**
   * Get the display for children the user has access to.
   *
   * @param array $user_access_apaths
   *   An array of apaths the user has access to.
   *
   * @return array
   *   Render array of node links.
  */
  public function getChildrenUserHasAccessDisplay(array $user_access_apaths): array {
    $access_nids = $this->lookup->nidsFromApaths($user_access_apaths);
    $access_nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($access_nids);
    $view_builder = $this->entityTypeManager->getViewBuilder('node');
    $purchased_parents = [];
    $access_items = [];
    foreach ($access_nodes as $access_node) {
      $access_node_type = $access_node->getType();

      // Books
      if (in_array($access_node_type, bps_core_get_book_chunk_types())) {
        $access_items[] = $this->getProductDisplay($access_node);
      }

      // Journals
      if (in_array($access_node_type, bps_core_get_journal_chunk_types())) {
        
        // Journals and Journal issues.
        if ($access_node_type == HW_NODE_TYPE_JOURNAL || $access_node_type == HW_NODE_TYPE_ISSUE) {
          $purchased_parents[] = $access_node->apath->value;
        }

        // Journal article chunks
        if ($access_node->hasField('parent_issue')) {
          if (!in_array($access_node->parent_issue->get(0)->apath, $purchased_parents)) {
            $access_items[] = $this->getProductDisplay($access_node);
          }
        }
      }
    }
    return $access_items;
  } 

  /**
   * Get the display for a group of products.
   *
   * @param array $products
   *   Array of product objects.
   *
   * @param string $currency_code
   *   Three character currency code.
   *
   * @return array
   *   Render array.
   */
  public function buildPurchaseOffers(array $products, string $currency_code, string $title = ''): array {
    
    // Build list item display.
    $items = [];
    foreach ($products as $product) {
      $product_item = $this->buildPurchaseOffer($product, $currency_code);
      if (!empty($product_item)) {
        $items[] = $product_item;
      }
    }
    if (empty($items)) {
      return [];
    }

    // Build list.
    $build = [
      '#theme' => 'item_list',
      '#title' => $title,
      '#items' => $items,
      '#attributes' => ['class' => ['list-unstyled']],
      '#context' => ['list_style' => 'ecommerce_purchase_offers'],
    ];
    return $build;
  }

  /**
   * Get render array for a purchase offer item.
   *
   * @param \HighWire\Clients\Catalog\Product $product
   *   The product to build render array for.
   * @param string $currency_code
   *   Three character currency code.
   *
   * @return array
   *   A render array for a purchase offer item.
   */
  public function buildPurchaseOffer(Product $product, string $currency_code): array {
    $build = [];
    $product_node = $this->getProductNode($product);
    $purchase_options = $product->getPurchaseOptions();
    if (empty($product_node) || empty($purchase_options)) {
      return $build;
    }

    // Build product node display.
    $product_display = $this->getProductDisplay($product_node);

    // Get additional product node vars for purchase link.
    $product_node_vars = [
      'image' => $this->getProductImage($product_node),
      'product_source' => html_entity_decode($this->getProductSource($product_node)),
      'title_suffix' => $this->getProductTitleSuffix($product_node),
    ];

    // Build purchase options display.
    $purchase_options_build = [];
    foreach ($purchase_options as $disposition => $purchase_option) {
      if (empty($purchase_option)) {
        continue;
      }
      foreach ($purchase_option as $interval => $prices) {
        $price = !empty($prices[$currency_code]) ? $prices[$currency_code] : [];
        if (empty($price)) {
          continue;
        }

        // Build purchase option.
        $purchase_link = $this->buildPurchaseLink($product, $product_node, $price, $product_node_vars);
        if (!empty($purchase_link)) {
          $purchase_options_build[] = [
            '#theme' => 'bps_purchase_offer_item',
            '#type' => $disposition,
            '#duration' => $price->getInterval(IntervalFormatter::hours()) . ' hours',
            '#purchase_link' => $purchase_link,
          ];
        }
      }
    }

    if (!empty($product_display) && !empty($purchase_options_build)) {
      $build = [
        '#theme' => 'bps_purchase_offer',
        '#product' => $product_display,
        '#purchase_offers' => $purchase_options_build,
      ];
    } 
    return $build;
  }

  /**
   * Get render array for a purchase link.
   *
   * @param \HighWire\Clients\Catalog\Product $product
   *   The product to build purchase link render array for.
   * @param \Drupal\node\Entity\Node $product_node
   *   Drupal node for the product.
   * @param \HighWire\Clients\Catalog\Price $price
   *   The price to build purchase link render array for.
   * @param array $add_query_params
   *   Any additional query paramters to add to the purcahse link.
   *
   * @return array
   *   A render array for a purchase link.
   */
  public function buildPurchaseLink(Product $product, Node $product_node, Price $price, array $add_query_params = []): array {
    $args = [];

    // Update Issue nodes with full journal issue title.
    if ($product_node->getType() == HW_NODE_TYPE_ISSUE) {
      if ($journal_title_field = $product_node->get('journal_title')->first()) {
        $journal_title = $journal_title_field->getString() . ' | ';
      }
      else {
        $journal_title = '';
      }
      if ($vol_field = $product_node->get('volume')->first()) {
        $vol = 'Volume ' . $vol_field->getString();
      }
      else {
        $vol = '';
      }
      if ($issue_field = $product_node->get('issue')->first()) {
        $issue = 'Issue ' . $issue_field->getString();
      }
      else {
        $issue = '';
      }
      $product_node->setTitle($journal_title . $vol . ($vol && $issue ? ', ' : '') . $issue);
    }

    // Exclude the apath query parameter from foxycart links for Journal subscriptions.
    if ($product_node->getType() == HW_NODE_TYPE_JOURNAL && $price->getDisposition() == 'rental') {
      $args['exclude_apath'] = TRUE;
    }

    $purchase_link = $this->addToCartLink->getLink($product, $product_node, $price, $args);
    if (empty($purchase_link)) {
      return [];
    }

    // Alter display of purchase link.
    $purchase_link['#attributes']['class'][] = 'btn';
    $purchase_link['#attributes']['class'][] = 'btn-primary';
    $user_ip = $this->requestStack->getCurrentRequest()->getClientIp();
    $user_currency = $this->catalog->getUserCurrency($user_ip)->getData();
    $currencysymbol = $this->getCurrencySymbol($user_currency);

    //Check for dicount given
    $discountdata = $this->addToCartLink->getDiscountFlag();
    $display_price = $price->getDisplayPrice();
    if (!empty($display_price)) {
      if (!empty($discountdata)) {
        $purchase_link['#title'] = $this->t('Add to cart <del>(:symbol:discount)</del> (:price)',
                                             [':symbol' => $currencysymbol,
                                              ':price' => $display_price,
                                              ':discount' => $discountdata
                                             ]
                                            );
        $purchase_link['#attributes']['data-original-price'] = '<del>(' . $currencysymbol . $discountdata . ')</del>';
        $purchase_link['#attributes']['data-discounted-price'] = '(' . $display_price . ')';
      }
      else {
        $purchase_link['#title'] = $this->t('Add to cart (:price)', [':price' => $display_price]);
      }
      $purchase_link['#attributes']['data-purchase-text'] = $purchase_link['#title'];
    }

    // Add extra query parameters.
    if (!empty($add_query_params)) {
      $purchase_link_query = $purchase_link['#url']->getOption('query');
      $sku = $product->getSku();
      foreach ($add_query_params as $k => $v) {
        if (empty($v)) {
          continue;
        }
        $purchase_link_query[$this->addToCartLink->getHmac($k, $v, $sku, $this->apikey)] = $v;
      }
      $purchase_link['#url']->setOption('query', $purchase_link_query);
      $purchase_link['#attributes']['data-purchase-url'] = $purchase_link['#url']->toString();
    }
    return $purchase_link;
  }

  /**
   * Get image for a given product node.
   *
   * @param \Drupal\node\Entity\Node $product_node
   *   The product node.
   *
   * @return string
   *   The url to the given product's image.
   */
  public function getProductImage(Node $product_node): string {
    $type = $product_node->getType();
    $img_url = '';
    if (in_array($type, bps_core_get_book_chunk_types())) {
      $img_url = Url::fromUserInput('/themes/scolaris_bps/images/icon-chapter.png', ['absolute' => TRUE])->toString();
    }
    elseif ($type == HW_NODE_TYPE_ARTICLE) {
      // Get Parent Issue to fetch its image as article dont have its own
      $parent_issue_id = $product_node->get('parent_issue')->getString();
      if (!empty($parent_issue_id)) {
        $parent_issue_data = Node::load($parent_issue_id);
        if (!empty($parent_issue_data) && $parent_issue_data->hasField('variant_cover_image') && !$parent_issue_data->get('variant_cover_image')->isEmpty()) {
          $img_field = $parent_issue_data->variant_cover_image->first()->getValue();
          if (!empty($img_field['uri'])) {
            $img_url = ImageStyle::load('cover_results_item')->buildUrl($img_field['uri']);
          }
        }
      }
    }
    else {
      $img_field = [];
      switch ($type) {
        case HW_NODE_TYPE_MONOGRAPH:
        case HW_NODE_TYPE_REPORT_GUIDELINE:
        case HW_NODE_TYPE_TEST_REVIEW:
          if (!empty($product_node) && $product_node->hasField('cover_image') && !$product_node->get('cover_image')->isEmpty()) {
            $img_field = $product_node->cover_image->first()->getValue();
          }
          break;

        case HW_NODE_TYPE_JOURNAL:
        case HW_NODE_TYPE_ISSUE:
          if (!empty($product_node) && $product_node->hasField('variant_cover_image') && !$product_node->get('variant_cover_image')->isEmpty()) {
            $img_field = $product_node->variant_cover_image->first()->getValue();
          }
          break;

      }
      if (!empty($img_field['uri'])) {
        $img_url = ImageStyle::load('cover_results_item')->buildUrl($img_field['uri']);
      }
    }
    return $img_url;
  }

  /**
   * Get source title for a given product node.
   *
   * @param \Drupal\node\Entity\Node $product_node
   *   The product node.
   *
   * @return string
   *   The title of the given product's source content.
   */
  public function getProductSource(Node $product_node): string {
    $type = $product_node->getType();
    $source = '';
    if ($type == HW_NODE_TYPE_MONOGRAPH || $type == HW_NODE_TYPE_REPORT_GUIDELINE || $type == HW_NODE_TYPE_JOURNAL || $type == HW_NODE_TYPE_TEST_REVIEW) {
      return $source;
    }
    if (in_array($type, bps_core_get_book_chunk_types())) {
      if ($product_node->hasField('parent_book') && !$product_node->get('parent_book')->isEmpty()) {
        $source_node_data = $product_node->parent_book->first()->getValue();
        if (!empty($source_node_data['target_id']) && !empty($this->nodes[$source_node_data['target_id']])) {
          $source_node = $this->nodes[$source_node_data['target_id']];
          $source = $source_node->getTitle();
          $title_suffix = $this->getProductTitleSuffix($source_node);
          if (!empty($title_suffix)) {
            $source .= ', ' . $title_suffix;
          }
        }
      }
    }

    // todo: refactor book and journal sources code.
    if (in_array($type, bps_core_get_journal_chunk_types())) {
      if ($product_node->hasField('parent-journal') && !$product_node->get('parent-journal')->isEmpty()) {
        $source_node_data = $product_node->journal->first()->getValue();
        if (!empty($source_node_data['target_id']) && !empty($this->nodes[$source_node_data['target_id']])) {
          $source_node = $this->nodes[$source_node_data['target_id']];
          $source = $source_node->getTitle();
          $title_suffix = $this->getProductTitleSuffix($source_node);
          if (!empty($title_suffix)) {
            $source .= ', ' . $title_suffix;
          }
        }
      }
    }

    // @TODO: Add support for issue & article types.
    return $source;
  }

  /**
   * Get title suffix for a given product node.
   *
   * @param \Drupal\node\Entity\Node $product_node
   *   The product node.
   *
   * @return string
   *   The title suffix for the given product.
   */
  public function getProductTitleSuffix(Node $product_node): string {
    $type = $product_node->getType();
    $title_suffix = '';
    switch ($type) {
      case HW_NODE_TYPE_MONOGRAPH:
      case HW_NODE_TYPE_REPORT_GUIDELINE:
        if ($product_node->hasField('edition') && !$product_node->get('edition')->isEmpty()) {
          $edition = $product_node->get('edition')->getString();
          if (is_numeric($edition) && intval($edition) > 1) {
            $ordinalFormatter = new \NumberFormatter("en-US", \NumberFormatter::ORDINAL);
            $edition = $ordinalFormatter->format($edition);
            $title_suffix = $this->t(':edition Edition', [':edition' => $edition]);
          }
        }
        break;
    }
    return $title_suffix;
  }

  /**
   * Get product list title based on type and container type.
   *
   * @param string $type
   *   The type of product. 
   * @param string $container_type
   *   The product's root container product type.
   *
   * @return string
   *   Title for purchase offers list.
   */
  public function buildPurchaseOffersTitle(string $type, string $container_type = ''): string {
    $title = '';
    $display_node_type = '';
    $product_node_types = $this->getProductNodeTypes($type);
    $title_config = !empty($this->configuration['list_titles']) ? $this->configuration['list_titles'] : [];
    $node_type = !empty($this->contextNode) ? $this->contextNode->getType() : $this->getContextValue('node')->getType();
    if (in_array($node_type, $product_node_types)) {
   
      // Label for this content type.
      $display_node_type = $node_type;
      $title = !empty($title_config['purchase_offers_current']) ? $title_config['purchase_offers_current'] : '';
    } 
    else {
      $display_node_type = reset($product_node_types);
      if ($type == $container_type) {
      
        // Label for container type.
        $title = !empty($title_config['purchase_offers_container']) ? $title_config['purchase_offers_container'] : '';
      }
      else {
    
      // Label for parent type that is not the root container.
        $title = !empty($title_config['purchase_offers_default']) ? $title_config['purchase_offers_default'] : '';
      }
    }
    $t_values = !empty($display_node_type) ? [':type' => $this->getTypeLabel($display_node_type), ':types' => $this->getTypeLabelPlural($display_node_type)] : [];
    if (!empty($title)) {
      $title = $this->t($title, $t_values);
    }
    return $title;
  }

  /**
   * Build child offer prompt display.
   */
  public function buildChildOffersPrompt(): array {
    $build = [];
    $child_prompt_config = !empty($this->configuration['child_offers_prompt']) ? $this->configuration['child_offers_prompt'] : [];
    if (empty($child_prompt_config) || (empty($child_prompt_config['title']) && empty($child_prompt_config['text']))) {
      return $build;
    }
    $node_type = !empty($this->contextNode) ? $this->contextNode->getType() : $this->getContextValue('node')->getType();
    $child_types = $this->getChildTypes($node_type);
    if (empty($child_types) || empty($this->userNoAccessChildren)) {
      return $build;
    }

    // Check if there are purchasable items the user doesn't already have access to.
    try {
      $offers_response = $this->catalog->getOffer($this->userNoAccessChildren);
      $offers = $offers_response->getData();
    }
    catch (\Exception $ex) {
      return $build;
    }
    $child_offers = FALSE;
    if (!empty($offers->getPricingItems())) {
      foreach ($offers->getPricingItems() as $pricing_item) {
        if (!empty($pricing_item->getProducts())) {
          $child_offers = TRUE;
          break;
        }
      }
    }
    if (!$child_offers) {
      return $build;
    }

    // Get string replacements.
    $t_values = $child_type_labels = $child_type_labels_plural = [];
    foreach ($child_types as $child_type) {
      $child_type_labels[] = $this->getTypeLabel($child_type);
      $child_type_labels_plural[] = $this->getTypeLabelPlural($child_type);
    }
    if (!empty($child_type_labels)) {
      $t_values[':type'] = implode(' or ', $child_type_labels);
    }
    if (!empty($child_type_labels_plural)) {
      $t_values[':types'] = implode(' or ', $child_type_labels_plural);
    }
    $starts_with_vowel = in_array(reset($t_values)[0], ['a', 'e', 'i', 'o', 'u']) ? TRUE : FALSE;
    $t_values[':a'] = $starts_with_vowel ? 'an' : 'a';

    // Build display.
    $build['title'] = !empty($child_prompt_config['title']) ? $this->t($child_prompt_config['title'], $t_values) : '';
    $build['text'] = !empty($child_prompt_config['text']) ? $this->t($child_prompt_config['text'], $t_values) : '';
    return $build;
  }

  /**
   * Returns whether to show item children in access panel or not.
   *
   * @return boolean
   */
  public function showChildren(): bool {
    $node_type = !empty($this->contextNode) ? $this->contextNode->getType() : $this->getContextValue('node')->getType();
    switch ($node_type) {
      case HW_NODE_TYPE_MONOGRAPH:
      case HW_NODE_TYPE_REPORT_GUIDELINE:
      case HW_NODE_TYPE_JOURNAL:
      case HW_NODE_TYPE_ISSUE:
        return TRUE;
      default:
        return FALSE;
    }
  }

  /**
   * Get child content types based on a given node type.
   *
   * @param string $node_type
   *   Node type to get child types for.
   *
   * @return array
   *   An array of child node types.
   */
  public function getChildTypes(string $node_type): array {
    switch ($node_type) {
      case HW_NODE_TYPE_MONOGRAPH:
      case HW_NODE_TYPE_REPORT_GUIDELINE:
        $chapter_types = bps_core_get_book_chunk_types();
        $types = [reset($chapter_types)];
        break;

      case HW_NODE_TYPE_ISSUE:
        $types = [HW_NODE_TYPE_ARTICLE];
        break;

      case HW_NODE_TYPE_JOURNAL:
        $types = [HW_NODE_TYPE_ISSUE, HW_NODE_TYPE_ARTICLE];
        break;

      default:
        $types = [];
        break;

    }
    return $types;
  }

  /**
   * Get display label (singular) for a given node type.
   *
   * @param string $node_type
   *   Node type to get the display label for.
   *
   * @return string
   *   Display label for the given node type.
   */
  public function getTypeLabel(string $node_type): string {
    $label = '';
    if (in_array($node_type, bps_core_get_book_chunk_types())) {
      $label = 'chapter';
    }
    else {
      switch ($node_type) {
        case HW_NODE_TYPE_MONOGRAPH:
          $label = 'monograph';
          break;

        case HW_NODE_TYPE_REPORT_GUIDELINE:
          $label = 'report-guideline';
          break;

        case HW_NODE_TYPE_ISSUE:
          $label = 'whole issue';
          break;

        case HW_NODE_TYPE_ARTICLE:
          $label = 'article';
          break;

        case HW_NODE_TYPE_JOURNAL:
          $label = 'journal';
          break;

        case HW_NODE_TYPE_TEST_REVIEW:
          $label = 'test-review';
          break;
      }
    }
    return $label;
  }

  /**
   * Get display label (plural) for a given node type.
   *
   * @param string $node_type
   *   Node type to get the display label for.
   *
   * @return string
   *   Display label for the given node type.
   */
  public function getTypeLabelPlural(string $node_type): string {
    $label = '';
    if (in_array($node_type, bps_core_get_book_chunk_types())) {
      $label = 'chapters';
    }
    else {
      switch ($node_type) {
        case HW_NODE_TYPE_MONOGRAPH:
          $label = 'monographs';
          break;

        case HW_NODE_TYPE_REPORT_GUIDELINE:
          $label = 'report-guidelines';
          break;

        case HW_NODE_TYPE_ISSUE:
          $label = 'issues';
          break;

        case HW_NODE_TYPE_ARTICLE:
          $label = 'articles';
          break;

        case HW_NODE_TYPE_JOURNAL:
          $label = 'journals';
          break;

        case HW_NODE_TYPE_TEST_REVIEW:
          $label = 'test-reviews';
          break;

      }
    }
    return $label;
  }

  /**
   * Given a purchase item type, get the corresponding node type(s).
   *
   * @string $product_type
   *   The product type as returned by the offers service.
   *
   * @return array
   *   An array of corresponding node type(s).
   */
  public function getProductNodeTypes(string $product_type): array {
    $type_map = [
      'monograph' => [HW_NODE_TYPE_MONOGRAPH],
      'report-guideline' => [HW_NODE_TYPE_REPORT_GUIDELINE],
      'monograph-chapter' => bps_core_get_book_chunk_types(),
      'report-guideline-chapter' => bps_core_get_book_chunk_types(),
      'journal' => [HW_NODE_TYPE_JOURNAL],
      'issue' => [HW_NODE_TYPE_ISSUE],
      'article' => [HW_NODE_TYPE_ARTICLE],
      'test-review' => [HW_NODE_TYPE_TEST_REVIEW],
    ];
    if (!empty($type_map[$product_type])) {
      return $type_map[$product_type];
    }

    return [];
  }

  /**
   * Get products grouped by type for a given pricing item.
   *
   * @param \HighWire\Clients\Catalog\PricingItem $pricing_item
   *   The pricing item to get grouped products list for.
   *
   * @return array
   *   An array of product objects, keyed by content type.
   */
  protected function groupProductsByType(PricingItem $pricing_item): array {
    $node_type = $this->contextNode->getType();
    $chapter_type = $this->contextNode->get('chapter_type')->getString();
    $chapter_type_order = ($chapter_type == 'monograph-item-chapter') ? 'monograph' : 'report-guideline';
    $type_order_unit = ($chapter_type == 'monograph-item-chapter') ? 'monograph-chapter' : 'report-guideline-chapter';
    $type_order = [];
    if (in_array($node_type, bps_core_get_book_chunk_types())) {
      $type_order = [$type_order_unit, $chapter_type_order];
    }
    else {
      switch ($node_type) {
        case HW_NODE_TYPE_MONOGRAPH:
          $type_order = ['monograph'];
          break;

        case HW_NODE_TYPE_REPORT_GUIDELINE:
          $type_order = ['report-guideline'];
          break;

        case HW_NODE_TYPE_JOURNAL:
          $type_order = ['journal'];
          break;

        case HW_NODE_TYPE_ISSUE:
          $type_order = ['issue', 'journal'];
          break;

        case HW_NODE_TYPE_ARTICLE:
          $type_order = ['article', 'issue', 'journal'];
          break;

        case HW_NODE_TYPE_TEST_REVIEW:
          $type_order = ['test-review'];
          break;
      }
    }
    if (empty($type_order)) {
      return [];
    }
    $products = [];
    foreach ($type_order as $type) {
      $products[$type] = $pricing_item->getProductByType($type);
    }
    return $products;
  }

  /**
   *  Get products currency symbol.
   * 
   */
  public function getCurrencySymbol($cur) {
    if (!$cur) {
      return false;
    }
    $currencies = array(
      'USD' => '$', // US Dollar
      'EUR' => '€', // Euro
      'CRC' => '₡', // Costa Rican Colón
      'GBP' => '£', // British Pound Sterling
      'ILS' => '₪', // Israeli New Sheqel
      'INR' => '₹', // Indian Rupee
      'JPY' => '¥', // Japanese Yen
      'KRW' => '₩', // South Korean Won
      'NGN' => '₦', // Nigerian Naira
    );
    if (array_key_exists($cur, $currencies)) {
      return $currencies[$cur];
    } 
    else {
      return $cur;
    }
  }
}