<?php

use \Drupal\Component\Utility\Html;
use \Drupal\Core\Url;
use \Drupal\Core\Link;
use \Drupal\Core\Template\Attribute;
use \Drupal\Core\Render\Element;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\block\Entity\Block;
use Drupal\node\Entity\Node;


/**
 * Implements hook_theme().
 */
function bps_theme(array $existing, $type, $theme, $path) {
  return [
    'list_items_per_page' => [
      'variables' => ['options' => [], 'url_parameter' => 'items_per_page', 'url' => FALSE, 'total' => 0],
    ],
    'bps_page_layout' => [
      'template' => 'layout/bps-page-layout',
      'render element' => 'content',
    ],
    'facet_block' => [
      'template' => 'facet-block',
      'variables' => ['title' => '', 'facet' => '', 'contextual_links' => '', 'facet_id' => ''],
    ],
    'facet_block__modal' => [
      'base hook' => 'facet_block',
    ],
    'facet_block__collapse' => [
      'base hook' => 'facet_block',
    ],
  ];
}

/**
 * Implements hook_preprocess_THEME().
 */
function bps_preprocess_bps_page_layout(&$variables) {
  if (empty($variables['content']['#layout'])) {
    return;
  }
  $variables['settings'] = isset($variables['content']['#settings']) ? $variables['content']['#settings'] : [];
  $variables['layout'] = $variables['content']['#layout'];
  // Build rows.
  $layout_regions = $variables['layout']->getRegions();
  $default_region = $variables['layout']->getDefaultRegion();
  $rows = [];
  foreach ($layout_regions as $region_id => $region_settings) {
    // Skip empty regions or regions that are not assigned to a row.
    if (empty($variables['content'][$region_id]) || empty($region_settings['row'])) {
      continue;
    }
    $row = $region_settings['row'];
    if (!isset($rows[$row])) {
      $rows[$row] = [];
    }
    $attributes = new Attribute(['class' => ['col--' . Html::getClass($region_id)]]);
    if (!empty($region_settings['class'])) {
      $attributes->addClass(explode(' ', $region_settings['class']));
    }
    $rows[$row][$region_id] = [
      'id' => $region_id,
      'content' => $variables['content'][$region_id],
      'attributes' => $attributes,
    ];
  }
  if (!empty($rows)) {
    $variables['rows'] = $rows;
  }
  // Additional row classes.
  $additional = $variables['layout']->get('additional');
  if (!empty($additional['row_classes'])) {
    $variables['row_classes'] = $additional['row_classes'];
  }
}

/**
 * @file
 * Functions to support theming layouts in the Particle theme.
 */
function bps_preprocess_block__image_block(&$variables) {
  if (empty($variables['content']['#block_content']) || empty($variables['content']['field_image'][0])) {
    return;
  }
  // Add url to image formatter if it is not empty.
  $block_content = $variables['content']['#block_content'];
  if ($block_content->hasField('field_url') && !$block_content->get('field_url')->isEmpty()) {
    $url_field = $block_content->get('field_url')->first()->getValue();
    if (!empty($url_field['uri'])) {
      $variables['content']['field_image'][0]['#url'] = Url::fromUri($url_field['uri']);
    }
  }
  // Set image_formatter__2x theme hook for retina support.
  if (!empty($variables['content']['field_image'][0]['#item'])) {
    $variables['content']['field_image'][0]['#theme'] = 'image_formatter__2x';
  }
}

/**
 * Implements hook_preprocess_THEME().
 */
function bps_preprocess_block__image(&$variables) {
  if (empty($variables['content']['#block_content']) || empty($variables['content']['field_image'][0])) {
    return;
  }
  // Add url to image formatter if it is not empty.
  $block_content = $variables['content']['#block_content'];
  if ($block_content->hasField('field_url') && !$block_content->get('field_url')->isEmpty()) {
    $url_field = $block_content->get('field_url')->first()->getValue();
    if (!empty($url_field['uri'])) {
      $variables['content']['field_image'][0]['#url'] = Url::fromUri($url_field['uri']);
    }
  }
  // Set image_formatter__2x theme hook for retina support.
  if (!empty($variables['content']['field_image'][0]['#item'])) {
    $variables['content']['field_image'][0]['#theme'] = 'image_formatter__2x';
  }
}

/**
 * Implements hook_preprocess_THEME().
 */
function bps_preprocess_image_formatter(&$variables) {
  // Add url_attributes.
  if (!empty($variables['url'])) {
    $variables['url_attributes'] = new Attribute([]);
    //open home page link in same window
    if ($variables['url']->getUri() == \Drupal::request()->getSchemeAndHttpHost()) {
      $variables['url_attributes']['target'] = '';
    }
    // Open external links in new window.
    else if ($variables['url']->isExternal()) {
      $variables['url_attributes']['target'] = '_blank';
    }
  }
}

/**
 * Implements hook_preprocess_THEME().
 */
function bps_preprocess_image_formatter__2x(&$variables) {
  if (empty($variables['item']) || empty($variables['image'])) {
    return;
  }
  // Set width & height to half natural value to support retina display.
  // This requires the source image to be twice as large as it is meant to be displayed.
  // (E.g. to display an image at 100x100 in the browser, upload the image at 200x200.)
  $img_props = $variables['item']->getProperties();
  $width = !empty($img_props['width']) ? $img_props['width']->getValue() : '';
  $height = !empty($img_props['height']) ? $img_props['height']->getValue() : '';
  if (!empty($width)) {
    $variables['image']['#width'] = $width/2;
  }
  if (!empty($height)) {
    $variables['image']['#height'] = $height/2;
  }
}

/**
 * Implements hook_preprocess_HOOK().
 */
function bps_preprocess_page(&$variables) {
  // Check if login or transaction message should be displayed.
  $tempstore_factory = \Drupal::service('user.private_tempstore');
  $bps_core_tempstore = $tempstore_factory->get('bps_core');
  $hw_ecom_tempstore = $tempstore_factory->get('highwire_ecommerce');
  $show_login_message = $show_transaction_message = FALSE;
  if ($show_login_message = $bps_core_tempstore->get('openid_show_login_message')) {
    $bps_core_tempstore->set('openid_show_login_message', FALSE);
  }
  if ($show_transaction_message = $hw_ecom_tempstore->get('foxycart_show_transaction_message')) {
    $hw_ecom_tempstore->set('foxycart_show_transaction_message', FALSE);
  }
  if ($variables['node']) {
    $node = $variables['node'];
    $type = $node->getType();
  }
  $variables['attributes'] = new Drupal\Core\Template\Attribute($variables['attributes']);
  $variables['attributes']->addClass('block');
  $variables['title_attributes'] = new Drupal\Core\Template\Attribute($variables['attributes']);
  $variables['title_attributes']->addClass('block');
  if (!$type == 'item_chapter'){
    $variables['attributes']['class'] = ['article__focus_link'];
    $variables['title_attributes']['class'] = ['article__focus_link'];
  }
  // Add custom header auth regions.
  foreach(['header_auth', 'header_inst_branding', 'header_inst_branding_2'] as $region) {
    $variables['page'][$region] = [
      '#theme_wrappers' => ['region'],
      '#region' => $region,
    ];
  }
  
  // Themes are not able to declare module dependencies at this time.
  // We use this as a shortcut to guarantee the component library module exists.
  $moduleHandler = \Drupal::service('module_handler');
  if ($moduleHandler->moduleExists('components')) {
    $variables['has_components_module'] = TRUE;
  }

  //Get heading of all footer menu block
  $variables['footer_content_block_heading'] = !empty(Block::load('footerright_2')) ? Block::load('footerright_2')->label() : '';
  $variables['footer_information_block_heading'] = !empty(Block::load('footerright1')) ? Block::load('footerright1')->label() : '';
  $variables['footer_resources_block_heading'] = !empty(Block::load('footerright2_2')) ? Block::load('footerright2_2')->label() : '';
  $variables['footer_legal_block_heading'] = !empty(Block::load('footerright3_5')) ? Block::load('footerright3_5')->label() : '';
}

/**
 * Implements hook_preprocess_region();
 */
function bps_preprocess_region(&$variables) {
  if (empty($variables['region'])) {
    return;
  }
  switch ($variables['region']) {
    case 'header_nav':
    case 'header_auth':
      // Add variable and class to region if institutional access has been granted.
      $inst_access = FALSE;
      $datavis_settings = \Drupal::config('scolaris_bps_datavis.settings');
      $is_datavis = !empty($datavis_settings->get('datavis_page_route')) && \Drupal::routeMatch()->getRouteName() == $datavis_settings->get('datavis_page_route');
      if ($is_datavis && !empty($datavis_settings->get('datavis_content_identifiers'))) {
        $sigma_extra_data = \Drupal::service('request_stack')->getCurrentRequest()->getSession()->get('sigma_extra_data');
        $key = implode('_', $datavis_settings->get('datavis_content_identifiers'));
        $inst_access = !empty($sigma_extra_data['highwire_sams_sigma']['org_licensed_profiles'][$key]);
      } else {
        $inst_access = \Drupal::service('highwire_user.hw_user_manager')->isInstitutionalUser();
      }
      if ($inst_access) {
        $variables['attributes']['class'][] = 'is-inst-access';
      }
      $variables['inst_access'] = $inst_access;
      if ($variables['region'] == 'header_auth') {
        // Build account menu.
        $menu = bps_get_menu_render_array('account');
        $sigma_login_link = [];
        $custom_message = '';

        // Add message text based on whether user is logged in as
        // in individual and/or institutional user.
        if (!$variables['logged_in']) {
          $variables['attributes']['class'][] = 'menu--account';
          if (!empty($menu['#items']['openid_connect_sams_sigma.wayflesslogin'])) {
            $sigma_login_link = &$menu['#items']['openid_connect_sams_sigma.wayflesslogin'];
          }
          if ($inst_access) {
            $custom_message = t("You already have full content access to British Psychological Society through your institution. Some site functionality such as bookmarking and alerts require a personal account in addition to content access. To Sign In or Register for a personal account, select Log in via email/username.");
          } else {
            $custom_message = t("Registering for a Username and Password only provides access to certain functionality like bookmarking and alerts. It does NOT provide full content access. To see if your institution has a subscription to British Psychological Society, contact your librarian or check your library's database list for access instructions.");
            $variables['content'] = array();
            $variables['content']['login_prompt'] = ['#theme' => $is_datavis ? 'bps_login_prompt__datavis' : 'bps_login_prompt', '#weight' => -1];
          }
        } else {
          if (!empty($menu['#items']['openid_connect_sams_sigma.manage_access'])) {
            $sigma_login_link = &$menu['#items']['openid_connect_sams_sigma.manage_access'];
          }
          if ($inst_access) {
            $custom_message = t("You currently have access to both British Psychological Society content and personal account features such as bookmarking and alerts.");
          } else {
            $custom_message = t("You are currently signed into an British Psychological Society personal account but do NOT have content access. Personal accounts only provide access to certain features such as bookmarking and alerts. To see if your institution has a subscription to AccessEngineering, contact your librarian or check your library's database list for access instructions.");
          }
        }
        // Add message text to sigma login link.
        if (!empty($sigma_login_link) && !empty($custom_message)) {
          $link_query = $sigma_login_link['url']->getOption('query');
          //$link_query['custom_message'] = $custom_message;
          $sigma_login_link['url']->setOption('query', $link_query);
        }
        // Add login link / account menu.
        if (!$variables['logged_in'] && !empty($sigma_login_link)) {
          $variables['content'] = array();
          $variables['content']['login'] = Link::fromTextAndUrl('', $sigma_login_link['url'])->toRenderable();
          $variables['content']['login']['#title'] = [
            ['#markup' => t('Log in')],
          ];
          $variables['content']['login']['#attributes']['class'][] = 'dropdown-toggle user__icon';
        } else if ($variables['logged_in'] && !empty($menu['#items'])) {
          $variables['content'] = array();
          $variables['content']['menu'] = $menu;
        }

       // Add blocks. and  Add shopping cart.
        $block_manager = \Drupal::service('plugin.manager.block');
        $current_user = \Drupal::currentUser();
        $cart_block = $block_manager->createInstance('highwire_ecommerce_shopping_cart', []);
        $cart_access = $cart_block->access($current_user);
        if (!(is_object($cart_access) && $cart_access->isForbidden() || is_bool($cart_access) && !$cart_access)) {
          $shopping_cart = $cart_block->build();
          $shopping_cart['#cache']['contexts'] = $cart_block->getCacheContexts();
          if (!empty($shopping_cart)) {
            $variables['shopping_cart'] = $shopping_cart;
          }
        } 
      }
    break;
  }
}

/**
 * Implements hook_menu_render_array();
 */
function bps_get_menu_render_array($menu_name) {
  $menu_tree = \Drupal::menuTree();
  // Build the typical default set of menu tree parameters.
  $parameters = $menu_tree->getCurrentRouteMenuTreeParameters($menu_name);
  // Load the tree based on this set of parameters.
  $tree = $menu_tree->load($menu_name, $parameters);
  // Transform the tree using the manipulators you want.
  $manipulators = array(
    // Only show links that are accessible for the current user.
    array('callable' => 'menu.default_tree_manipulators:checkAccess'),
    // Use the default sorting of menu links.
    array('callable' => 'menu.default_tree_manipulators:generateIndexAndSort'),
  );
  $tree = $menu_tree->transform($tree, $manipulators);
  // Finally, build a renderable array from the transformed tree.
  $menu = $menu_tree->build($tree);
  return $menu;
}

/**
 * Implements hook_preprocess_THEME().
 */
 
function bps_preprocess_menu(&$variables) { 
  $variables['nav_id'] = !empty($variables['menu_name']) ? Html::getUniqueId($variables['menu_name']) : Html::getUniqueId('nav');
  if ($variables['theme_hook_original'] == 'menu__account') {
    $block_manager = \Drupal::service('plugin.manager.block');
    $current_user = \Drupal::currentUser();
    $plugin_block = $block_manager->createInstance('highwire_p13n_admin_nav');
    $access_result = $plugin_block->access($current_user);
    if (!(is_object($access_result) && $access_result->isForbidden() || is_bool($access_result) && !$access_result)) {
      // Build render array.
      $render = $plugin_block->build();
      if (!empty($render['user_info'])) {
        $variables['user_info'] = $render['user_info'];
        if (!empty($variables['user_info']['#logout_link'])) {
          $variables['user_info']['#logout_link'] = [];
        }
      }
      if (!empty($render['menus'])) {
        foreach (Element::children($render['menus']) as $key) {
          if (!empty($render['menus'][$key]['#items'])) {
            $variables['p13n_menus'][$key] = $render['menus'][$key];
          }
        }
      }
    }
    $cart_block = $block_manager->createInstance('highwire_ecommerce_shopping_cart', []);
    $cart_access = $cart_block->access(\Drupal::currentUser());
    if (!(is_object($cart_access) && $cart_access->isForbidden() || is_bool($cart_access) && !$cart_access)) {
      $shopping_cart = $cart_block->build();
      $shopping_cart['#cache']['contexts'] = $cart_block->getCacheContexts();
      if (!empty($shopping_cart)) {
        $variables['shopping_cart'] = $shopping_cart; 
      }
    }
  }


}

/**
 * Implements hook_preprocess__THEME().
 */
function bps_preprocess_menu__sidebar(&$variables) {
  if (empty($variables['items'])) {
    return;
  }
  $current_path = \Drupal::service('path.current')->getPath();
  $result = \Drupal::service('path.alias_manager')->getAliasByPath($current_path);
  $variables['current_path'] = $result;
  $variables['affix_nav'] = TRUE;
  $variables['show_menu'] = FALSE;
  foreach ($variables['items'] as &$item) {
    if (!empty($item['in_active_trail'])) {
      // Only show sidebar menu when the current page is part of any link's active trail.
      $variables['show_menu'] = TRUE;
      // Check if link is active.
      if ($item['url']->toString() == $variables['current_path']) {
        $item['is_active'] = TRUE;
      }
      // Check 2 levels of children for active items.
      if (!empty($item['below'])) {
        foreach ($item['below'] as &$item2) {
          if (!empty($item2['in_active_trail'])) {
            if ($item2['url']->toString() == $variables['current_path']) {
              $item2['is_active'] = TRUE;
            }
            if (!empty($item2['below'])) {
              foreach ($item2['below'] as &$item3) {
                if (!empty($item3['in_active_trail']) && $item3['url']->toString() == $variables['current_path']) {
                  $item3['is_active'] = TRUE;
                }
              }
            }
          }
        }
      }
    }
  }
  if (!$variables['show_menu']) {
		unset($variables['items']);
  }
}

/**
 * Implements hook_preprocess__THEME().
 */
function bps_preprocess_menu__main__sidebar(&$variables) {
  if (empty($variables['items'])) {
    return;
  }
  // Add 'is_active' flag to active links.
  bps_preprocess_menu__sidebar($variables);
}

function bps_preprocess_node__journal__browse_list_item(&$variables) {
  $node = $variables['node'];
  $type = $node->getType();
  if ($type == 'journal') {
    $info_nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'journal_info',
      'field_journal' => $node->id(),
    ]);
    if (!empty($info_nodes)) {
      $journal_info = reset($info_nodes);
      $variables['journal_description'] =  html_entity_decode(strip_tags($journal_info->get('field_journal_description' )->getvalue()[0]['value']));
      $variables['journal_editors'] =  ($journal_info->get('field_journal_editors')->getvalue()[0]['value'] ) ;
    }
  }
  $variables['journal'] = [];
  $title =  !$node->get('title')->isEmpty() ? $node->get('title')->getString() : '';
  $html_entity_decoded_title =  html_entity_decode($title);
  $variables['html_entity_decoded_title'] = $html_entity_decoded_title;
  $variables['journal'] += bps_get_node_fields($variables, ['journal_current_issue' => 'coverimg']);
  $variables['journal'] += bps_get_node_pseudo_fields($variables, ['editors' => 'editors', 'group_description' => 'description', 'group_action_tools' => 'actionTools']);
  $variables['journal'] += bps_get_node_fields_as_string($variables, ['journal_pissn' => 'pissn', 'journal_eissn' => 'eissn']);
  $current_issue_link = $variables['elements']['current_issue_link'];
  $variables['current_issue_link'] = $current_issue_link;
  // Add placeholder cover if current issue is missing.
  if (empty($variables['journal']['coverimg']) && !empty($variables['cover_placeholder'])) {
    $plain_title = strip_tags($variables['node']->getTitle());
    $variables['cover_placeholder']['#sr_text'] = t('Go to @title', ['@title' => $plain_title]);
    $variables['journal']['coverimg'] = $variables['cover_placeholder'];
  }
  $cover_image = $variables['elements']['cover_image']['0']['#item'];
  if ($cover_image != null) {
    $image_tid = $cover_image->get('target_id')->getValue();
    if (!empty($image_tid)) {
      $file = File::load($image_tid);
      $path = $file->getFileUri();
      $cover_image_uri =  file_create_url($path);
      $image_alt = $cover_image->alt;
      $image_title = $cover_image->title;
      $image_width = '150';
      $image_height = '210';
    } else {
      $cover_image_uri = '/themes/highwire/bps_theme/dist/app-drupal/assets/images/250X350.png';
      $image_alt = 'journal cover image';
      $image_title = 'journal cover image';
      $image_width = '150';
      $image_height = '210';
    }
    $variables['cover_image_uri'] = $cover_image_uri;
    $variables['cover_image_alt'] = $image_alt;
    $variables['cover_image_title'] = $image_title;
    $variables['cover_image_width'] = $image_width;
    $variables['cover_image_height'] = $image_height;
  }
}

/**
 * Helper function to get node fields for patternlab.
 *
 * @param Array $variables
 *   A variables array from a theme preprocess function.
 * @param Array $fields
 *   An array of patternlab fields to return, keyed by the corresponding drupal field name.
 *
 * @return Array
 *   A render array
 */
function bps_get_node_fields($variables, $fields) {
  $render = [];
  if (empty($variables['node']) || empty($variables['content'])) {
    return $render;
  }
  foreach ($fields as $drupal => $pl) {
    if ($variables['node']->hasField($drupal) && !$variables['node']->get($drupal)->isEmpty() && !empty($variables['content'][$drupal])) {
      $render[$pl] = $variables['content'][$drupal];
    }
  }
  return $render;
}

function bps_get_node_pseudo_fields($variables, $fields) {
  $render = [];
  if (empty($variables['node']) || empty($variables['content'])) {
    return $render;
  }
  foreach ($fields as $drupal => $pl) {
    if (!empty($variables['content'][$drupal])) {
      $render[$pl] = $variables['content'][$drupal];
    }
  }
  // Make group description toggle id to unique.
  $hw_html_toggle_id = Html::getUniqueId('description--' . $variables['node']->id());
  $render['description']['#attributes']['id'] = $hw_html_toggle_id;
  $render['actionTools']['group_description_toggle']['#attributes']['data-target'] = '#' . $hw_html_toggle_id;
  $render['actionTools']['group_description_toggle']['#attributes']['aria-controls'] = $hw_html_toggle_id;
  return $render;
}

/**
 * Helper function to get node fields as a string for patternlab.
 *
 * @param Array $variables
 *   A variables array from a theme preprocess function.
 * @param Array $fields
 *   An array of patternlab fields to return, keyed by the corresponding drupal field name.
 *
 * @return Array
 *   An array of node field values keyed by field name.
 */
function bps_get_node_fields_as_string($variables, $fields) {
  $return = [];
  if (empty($variables['node'])) {
    return $return;
  }
  foreach ($fields as $drupal => $pl) {
    if ($variables['node']->hasField($drupal) && !$variables['node']->get($drupal)->isEmpty()) {
      $return[$pl] = $variables['node']->get($drupal)->getString();
    }
  }
  return $return;
}

/**
 * Implements hook_preprocess_bps_toc_pager().
 */
function bps_preprocess_bps_toc_pager(&$variables) {
  if (!empty($variables['previous_url'])) {
    $variables['previous'] = [
      '#type' => 'link',
      '#url' => $variables['previous_url'],
      '#title' => [
        ['#type' => 'bps_icon', '#icon' => 'arrow-circle-left', '#attributes' => ['class' => ['fa-lg']]],
        ['#markup' => '<span>Previous</span>'],
      ],
      '#attributes' => ['class' => ['article__previous_link']],
    ];
    if ($variables['previous_url']->isRouted() && $variables['previous_url']->getRouteName() == '<nolink>') {
      $variables['previous']['#attributes']['class'][] = 'disabled';
    }
  }
  if (!empty($variables['next_url'])) {
    $variables['next'] = [
      '#type' => 'link',
      '#url' => $variables['next_url'],
      '#title' => [
        ['#type' => 'bps_icon', '#icon' => 'arrow-circle-right', '#attributes' => ['class' => ['fa-lg']]],
        ['#markup' => '<span>Next</span>'],
      ],
      '#attributes' => ['class' => ['article__next_link']],
    ];
    if ($variables['next_url']->isRouted() && $variables['next_url']->getRouteName() == '<nolink>') {
      $variables['next']['#attributes']['class'][] = 'disabled';
    }
  }
}

/**
 * Implements hook_preprocess_THEME();
 */
function bps_preprocess_highwire_tabs(&$variables) {
  $variables['attributes']['class'][] = 'tab-content';
  if (!empty($variables['highwire_tabs_links'])) {
    $variables['highwire_tabs_links']['#attributes']['class'][] = 'nav';
    $variables['highwire_tabs_links']['#attributes']['class'][] = 'nav-tabs';
    foreach($variables['highwire_tabs_links']['#links'] as $key => $item_tab) {
      $variables['highwire_tabs_links']['#links'][$key]['attributes']['class'][] = 'nav-link';
      if (array_search('is-active',$variables['highwire_tabs_links']['#links'][$key]['attributes']['class'] )) {
        $variables['highwire_tabs_links']['#links'][$key]['attributes']['class'][] = 'active';
      }
    }
  }
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function bps_theme_suggestions_highwire_tabs_alter(array &$suggestions, array $variables) {
  switch ($variables['element']['#group_name']) {
    // Tabs with a right-hand sidebar.
    case 'group_journal_tabs':
    case 'group_hw_item_tabs':
      $suggestions[] = 'highwire_tabs__bps_tabs_rhs';
    break;
    case 'group_article_tabs':
      $suggestions[] = 'highwire_tabs__bps_tabs_lhs';
    break;
  }
}

function bps_preprocess_highwire_tab(&$variables) {
  if (empty($variables['element'])) {
    return;
  }
  if (in_array($variables['element']['#group'], ['group_journal_tabs'])) {
    if ($variables['element']['#group_name'] == 'group_full_text') {
      // Full text tab has 2cols, along w/ the sidebar makes the layout 3col.
      $variables['attributes']['class'][] = 'row--3col';
    } else {
      // By default chapter tabs & book tabs should be a main content column.
      if (array_search('is-active',$variables['attributes']['class'])) {
        $variables['attributes']['class'][] = 'active';
      }
      $variables['attributes']['class'][] = 'tab-pane';
    }
  }
}

function bps_preprocess_bs_2col_bricked(&$variables) {
  $variables['page_variant_title'] = trim($variables['content']['#page_variant']->get('label'));
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function bps_theme_suggestions_views_view_fields_alter(array &$suggestions, array $variables) {
  if (!empty($variables['view'])) {
    switch ($variables['view']->id()) {
      case 'all_issues':
        $suggestions[] = 'views_view_fields__issue_archive';
      break;
    }
  }
}

/**
 * Implements hook_preprocess_views_view_fields__ID().
 */
function bps_preprocess_views_view_fields__issue_archive(&$variables) {
  if (!empty($variables['row']->_object)) {
    $search_api_id = !empty($variables['row']->search_api_id) ? $variables['row']->search_api_id : '';
    $search_api_id_parts = explode(':', $search_api_id);
    if ($search_api_id_parts[0] == 'entity') {
      $entity_type_id = explode('/', $search_api_id_parts[1]);
      $variables['issue_url'] = Url::fromRoute("entity.$entity_type_id[0].canonical", [$entity_type_id[0] => $entity_type_id[1]])->toString();
    }
  }
}

/*
* Implements hook_preprocess_THEME().
*/
function bps_preprocess_highwire_personalization_user_info(&$variables) {
  $current_user = \Drupal::currentUser();
  $username = $current_user->getUsername();
  $useremail = $current_user->getEmail();
  $variables['name'] = ucfirst($username);
  $variables['email'] = $useremail;
}

/**
 * Implements hook_page_attachments_alter().
 */
function bps_page_attachments_alter(&$build) {
  // Add breakpoints for mediaqueries js.
  $breakpoints = \Drupal::service('breakpoint.manager')->getBreakpointsbyGroup('scolaris_bps');
  $js_settings = [];
  if (!empty($breakpoints)) {
    foreach($breakpoints as $key => $bp) {
      $media_query = $bp->getMediaQuery();
      if (!empty($media_query)) {
        $bp_key = str_replace('scolaris_bps.', '', $key);
        $build['#attached']['drupalSettings']['bps_mediaqueries']['breakpoints'][$bp_key] = $media_query;
      }
    }
  }
}

/**
 * Implements hook_preprocess_THEME().
 */
function bps_preprocess_item_list(&$variables) {
  $variables['#attached']['library'][] = 'bps/bps-toc';
}

/**
 * Implements hook_preprocess__THEME().
 */
function bps_preprocess_highwire_personalization_manage_marker(&$variables) {
  $node = $variables['marker_link']['#node'];
  if ($node == NULL) {
    $nid = ($variables['marker_link']['#url'])->getrouteParameters()['node'];
    $node = Node::load($nid);
  }
  if ($node) {
    $variables = search_result($variables, $node);
    $variables['result']['url'] = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $variables['result']['id']);
    $variables['marker_link_date'] = $variables['element']['#created'];
    $variables['marker_link'] = ($variables['element']['links']['#items']['delete']['#url'])->toString();
    $variables['marker_popupdata'] = 'Are you sure remove your favourite ' . $variables['result']['title'] . '?';
    $variables['modaltargetid'] = "favorite-action" . uniqid();
  }
} 

/**
 * Implements hook_preprocess__THEME().
 */
function bps_preprocess_highwire_personalization_manage_alert(&$variables) {
  $temp_label = ($variables['element']['manage']['title']['#title'])->__toString();
  $variables['links']['#items']['delete']['sign_up_form']['confirm']['confirm_line_1']['#value'] = t('Are you sure you want to delete this alert ' . $temp_label . '?');
  $variables['element']['links']['#items']['delete']['sign_up_form']['confirm']['confirm_line_1']['#value'] = t('Are you sure you want to delete this alert ' . $temp_label . '?');
}

/**
 * Implements hook_preprocess_THEME().
 */
function bps_preprocess_highwire_personalization_manage_saved_search(&$variables) {
  // Add icon & class to myaccount label run.
  if (!empty($variables['search_label']) && !empty($variables['execute_label']) && !empty($variables['search_link'])) {
    $variables['search_link']['#title'] = [
      'title' => [
        '#markup' => $variables['search_label'],
      ],
      'icon' => [
        '#type' => 'bps_icon',
        '#icon' => 'search-o',
        '#extra_classes' => 'fa-search',
        '#prefix' => '<span class="saved-search-execute-label">',
      ],
      'execute_title' => [
        '#markup' => $variables['execute_label'],
        '#suffix' => '</span>',
      ],
    ];
  }
}

/**
 * Implements hook_preprocess_THEME().
 */
function bps_preprocess_highwire_cct_browse(&$variables) {
  $variables['page_title'] = FALSE;
  $variables['intro'] = FALSE;
  if (!empty($variables['header']['#block_content'])) {
    $header_block = $variables['header']['#block_content'];
    if ($header_block->hasField('field_display_title') && !$header_block->get('field_display_title')->isEmpty()) {
      $variables['page_title'] = $header_block->get('field_display_title')->value;
    }
    if ($header_block->hasField('field_display_sub_title') && !$header_block->get('field_display_sub_title')->isEmpty()) {
      $variables['display_sub_title'] = $header_block->get('field_display_sub_title')->value;
    }
    if ($header_block->hasField('body') && !$header_block->get('body')->isEmpty()) {
      $variables['intro'] = $header_block->get('body')->value;
    }

    foreach ($variables['collection_of_title_image'] as $key => $result) {
      $data = $result['linked_title'];
      $link_url = '/collections' . '/' . $data->getUrl()->getRouteParameters()['landing_page_id'];
      $variables['collections_list_info'][$key]['image']['alt'] = $data->getText();
      $variables['collections_list_info'][$key]['image']['title'] = $data->getText();
      $variables['collections_list_info'][$key]['image']['href'] = $link_url;
      $variables['collections_list_info'][$key]['image']['src'] = '/themes/highwire/bps_theme/dist/app-drupal/assets/images/150X210.png';
      if (!empty($result['image'])) {
        $variables['collections_list_info'][$key]['image']['src'] = $result['image']['#uri'];
      }
      $variables['collections_list_info'][$key]['anchorlink']['level'] = 5;
      $variables['collections_list_info'][$key]['anchorlink']['button']['href'] = $link_url;
      $variables['collections_list_info'][$key]['anchorlink']['button']['text'] = $data->getText();
      $variables['collections_list_info'][$key]['anchorlink']['button']['title'] = $data->getText();
    }
  }
}

/**
 * Implements hook_preprocess_THEME().
 */
function bps_preprocess_highwire_cct_collection_page(&$variables) {
  if (empty($variables['featured_image_src'])) {
    $variables['featured_image_src'] = '/themes/highwire/bps_theme/dist/app-drupal/assets/images/150X210.png';
  }
  $variables['card_download_pdf']["#markup"] = '';
  if (!empty($variables['collection_pdf'])) {
    $variables['card_download_pdf']["#markup"] = '<a href="' . $variables['collection_pdf']['#href'] . '" class="download-pdf" target="_blank">Download collection pdf</a>';
  }
  if (!is_null($variables['member_list']) && !empty($variables['member_list'])) {
    $variables['collection_heading'] = 'Included in this collection/Collection items';
    $variables['ccb_bottom']['cardlist'] = [];
    foreach ($variables['member_list'] as $memberKey => $memberVal) {
      $node = $memberVal['#node'];
      $article_type = (is_object($node->get('article_type'))) ? ucfirst($node->get('article_type')->getString()) : '';
      $title_plain = (is_object($node->get('title_plain'))) ? $node->get('title_plain')->getString() : '';
      //Fetching the short text from newly created view mode in journal as collection freebird doesnt seems to be set to fetch short desc
      $view_mode = 'collection_detail_short_desc';
      //We have also created new markup display and profile as well with Collection Detail names
      $short_text = (render(\Drupal::entityTypeManager()->getViewBuilder('node')->view($node, $view_mode)));
      //setting data for content type
      $heading_obj = new stdClass();
      $heading_obj->heading->level = '6';
      $heading_obj->heading->text = $article_type;
      $heading_obj->heading->class = 'sub-ttile';
      //setting data for collection title
      $heading_link_obj = new stdClass();
      $heading_link_obj->anchorHead->level = '5';
      $heading_link_obj->anchorHead->class = 'ttile';
      $btn_obj = new stdClass();
      $btn_obj->href = $node->toUrl()->toString();
      $btn_obj->text = $title_plain;
      $heading_link_obj->anchorHead->button = $btn_obj;
      //setting data for collection description
      $desc_obj = new stdClass();
      $desc_obj->paragraph->class = 'description';
      $desc_obj->paragraph->text = $short_text;
      $obj = new stdClass();
      $obj->middle_col_width = "card-content";
      $obj->card_heading = [
        $heading_obj
      ];
      $obj->card_heading_link = [
        $heading_link_obj
      ];
      $obj->card_description = [
        $desc_obj
      ];
      $variables['ccb_bottom']['cardlist'][] = $obj;
    }
  }
}
