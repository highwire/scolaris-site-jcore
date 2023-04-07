<?php

use Drupal\node\Entity\Node;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Utility\Token;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;

/**
 * Implements hook_preprocess_THEME().
 */
function bps_preprocess_node__page__full(&$variables) {
	if (empty($variables['node'])) {
		return;
	}
	// Add left hand sidebar.
	$variables['page_sidebar'] = FALSE;

	// Add subtitle.
	if (!empty($variables['content']['field_page_subtitle']) && !empty($variables['content']['field_page_subtitle']['#theme'])) {
		$variables['page_subtitle'] = $variables['content']['field_page_subtitle'];
		unset($variables['content']['field_page_subtitle']);
	}

	if (!empty($variables['content']['bps_cms_navigation'])) {
		$variables['page_sidebar'] = TRUE;
		$variables['cms_nav'] = $variables['content']['bps_cms_navigation'];
		unset($variables['content']['bps_cms_navigation']);
	}

	// Ensure ID exists for collapse container.
	if (empty($variables['attributes']['id'])) {
		$variables['attributes']['id'] = Html::getUniqueId($variables['node']->id() . '--page--full');
	}
}

/**
 * Process block content
 */
function bps_preprocess_block(&$variables) {
	$node = \Drupal::routeMatch()->getParameter('node');
	if ($node instanceof \Drupal\node\NodeInterface) {
		$typeName = $node->bundle();

		//check if the page belongs to content type journal or journal issue
		if ($typeName == 'journal' || $typeName == 'journal_issue') {
			$nid = $node->id();

			//Get node ids of pages that has reference of currunt journal page
			$query = \Drupal::entityQuery('node')
				->condition('status', 1)
				->condition('type', 'news_article')
				->condition('field_news_article_content_ref', $nid, '=');
			$nids = $query->execute();
			foreach ($nids as $nid) {
				$node = Node::load($nid);
				$options = ['absolute' => TRUE];
				$url_object = URL::fromRoute('entity.node.canonical', ['node' => $nid], $options);
				$news_url = $url_object->toString();
				$title = $node->title->value;
				$imagefield = $node->get('field_news_article_image')->getValue();
				$img_alt = $node->field_news_article_image->alt;
				$img_title = $node->field_news_article_image->title;
				$fileid = $imagefield[0]['target_id'];
				$media_url = null;

				//check if node has image
				if (!empty($fileid)) {
					$file = File::load($fileid);
					if (!empty($file)) {
						$path = $file->getFileUri();
						$media_url = ImageStyle::load('news_block')->buildUrl($file->getFileUri());
					}
				}

				//Add the gathered data in array
				$news_listing_items[] = [
					'url' => $news_url,
					'title' => $title,
					'image' => [
						'title' => $img_title,
						'alt' => $img_alt,
						'src' => $media_url,
					],
				];
			}

			//set theme variable and pass values
			$variables['news_listing_items'] = $news_listing_items;
		}
	}
  $content_types_urls = ['/reports-guidelines', '/monographs', '/test-reviews'];
  $current_path = \Drupal::service('path.current')->getPath();
  if (in_array($current_path, $content_types_urls)) {
    switch ($current_path) {
      case '/reports-guidelines':
        $item_report_guideline = 'report-guideline-item-report-guideline';
        $url_options['query']['f'][] = 'chapter_type:' . $item_report_guideline;
        break;
      case '/monographs':
        $item_monograph = 'monograph-item-monograph';
        $url_options['query']['f'][] = 'chapter_type:' . $item_monograph;
        break;
      case '/test-reviews':
        $item_test_review = 'test-review-item-test-review';
        $url_options['query']['f'][] = 'chapter_type:' . $item_test_review;
        break;
    }
  }
  $url_object = Url::fromUri('internal:/search');
  $url_object->setOptions($url_options);
  $variables['show_all_link_href'] = '/search';
  switch ($variables['plugin_id']) {
    // Reports and guidelines browse page - show all link
    case 'block_content:f152d477-434b-4706-a189-e87da271026e':
      $variables['show_all_link_href'] = $url_object->toString();
      break;
    // Monographs browse page - show all link
    case 'block_content:1238c276-aaeb-4981-ac97-c0362f874e8e':
      $variables['show_all_link_href'] = $url_object->toString();
      break;
    // Test Review browse page - show all link
    case 'block_content:d48403a2-d47c-4592-9fc0-be7b91420422':
      $variables['show_all_link_href'] = $url_object->toString();
      break;
  }
}

/**
 * Implements hook_preprocess__THEME().
 */
function bps_preprocess_block__system_menu_block(&$variables) {
	$current_path = \Drupal::service('path.current')->getPath();
	$result = \Drupal::service('path.alias_manager')->getAliasByPath($current_path);

	$temp_menu_list = TRUE;
	foreach ($variables['content']['#items'] as &$item) {
		if ($item['url']->toString() == $result) {
			$temp_menu_list = FALSE;
		}
	}

	if ($temp_menu_list) {
		unset($variables['label']);
	}
}
/**
 * Process related block right sidebar content
 */
function bps_preprocess_block__views_block__related_content_block_related_content(&$variables) {
  $journals_sidebar['items']['block_heading'] = $variables['label'];
  $journals_sidebar['items']['show_related_block'] = TRUE;
  $data_arr = $variables['elements']['content']['#view']->result;
  $journals_sidebar['items']['related_article'] = array();
  if (!empty($data_arr)) {
    foreach ($data_arr as $dataKey => $data) {
      $node  = $data->_object->getEntity();
      $node_id = $node->get('nid')->getValue()[0]['value'];
      $node_link = \Drupal::service('path_alias.manager')->getAliasByPath('/node/'.$node_id);
      $title = $node->get('title_plain')->getValue()[0]['value'];
      $node_type = $node->bundle();
      switch ($node_type) {
        case HW_NODE_TYPE_ARTICLE:
          $item_type = 'Article';
        break;
        case HW_NODE_TYPE_CHAPTER:
          $item_type = 'Book chapter';
        break;
        case HW_NODE_TYPE_MONOGRAPH:
          $item_type = 'Monograph';
        break;
        case HW_NODE_TYPE_REPORT_GUIDELINE:
          $item_type = 'Report & Guideline';
        break;
        case HW_NODE_TYPE_TEST_REVIEW:
          $item_type = 'Test Review';
        break;
        default:
          $item_type = $item_type;
        break;
      }
      $journals_sidebar['items']['related_article'][$dataKey]['node_type'] = $item_type;
      $journals_sidebar['items']['related_article'][$dataKey]['node_title'] = $title;
      $journals_sidebar['items']['related_article'][$dataKey]['node_link'] = $node_link;
    }
  } else {
    $journals_sidebar['items']['show_related_block'] = FALSE;
  }
    $variables['journals_sidebar'] = $journals_sidebar;
}
/**
 * Process Keywords right sidebar block content
 */
function bps_preprocess_block__entity_field__node__keywords(&$variables) {
  $journals_sidebar['items']['block_heading'] = $variables['label'];
  $journals_sidebar['items']['show_keyword_block'] = TRUE;
  $data_arr = $variables['content']['field']['#items'];
  $journals_sidebar['items']['listing_items'] = array();
  if (!empty($data_arr)) {
    foreach ($data_arr as $dataKey => $data) {
      $url_object = Url::fromUri('internal:/search');
      $url_options['query'] = ['query' => ""];
      $url_options['query']['f'][] = 'keywords:' . $data->getValue()['display'];
      $url_object->setOptions($url_options);
      $path = $url_object->toString();
      $journals_sidebar['items']['listing_items'][$dataKey]['url'] = $path;
      $journals_sidebar['items']['listing_items'][$dataKey]['title'] = $data->getValue()['display'];
    }
  } else {
      $journals_sidebar['items']['show_keyword_block'] = FALSE;
  }
      $variables['journals_sidebar'] = $journals_sidebar;
}
/**
 * Process Keywords right sidebar block content
 */
function bps_preprocess_block__entity_field__node__test_type(&$variables) {
  $journals_sidebar['items']['block_heading'] = $variables['label'];
  $journals_sidebar['items']['show_test_type_block'] = TRUE;
  $data_arr = $variables['content']['field']['#items'];
  $journals_sidebar['items']['listing_items'] = array();
  if (!empty($data_arr)) {
    foreach ($data_arr as $dataKey => $data) {
      $url_object = Url::fromUri('internal:/search');
      $url_options['query'] = ['query' => ""];
      $url_options['query']['f'][] = 'chapter_type:test-review-item-test-review';
      $url_options['query']['f'][] = 'test_type:' . $data->getValue()['first'];
      $url_object->setOptions($url_options);
      $path = $url_object->toString();
      $journals_sidebar['items']['listing_items'][$dataKey]['url'] = $path;
      $journals_sidebar['items']['listing_items'][$dataKey]['title'] = $data->getValue()['first'];
    }
  } else {
    $journals_sidebar['items']['show_test_type_block'] = FALSE;
  }
    $variables['journals_sidebar'] = $journals_sidebar;
}

/**
 * Process Table of contents right sidebar block content
 */
function bps_preprocess_block__highwire_content_nav_block(&$variables) {
  $toc_sidebar_block['items']['block_heading'] = $variables['label'];
	$contents = $variables['content'];
	foreach ($contents as $k => $v) {
		$items = $v['#items'];
		foreach ($items as $items_key => $item) {
			$item_title = $item['#title']->__toString();
			$title_url = $item['#url']->toString();
			$toc_sidebar_block['items']['listing_items'][$items_key]['title'] = html_entity_decode($item_title);
			$toc_sidebar_block['items']['listing_items'][$items_key]['url'] = $title_url;
		}
	}
	if ($toc_sidebar_block['items']['listing_items'] == NULL) {
		$variables['toc_sidebar_block'] = false;	
	} else {
		$variables['toc_sidebar_block'] = $toc_sidebar_block;
	}	
}

/**
 * Process Table of contents right sidebar block content
 */
function bps_preprocess_block__mainpagecontent(&$variables) {
	$temp_markup = ($variables['content']['#markup'])->__toString();
	$temp_markup = str_replace('<p>Manage your alerts.</p>', '<h3>Manage your alerts</h3>', $temp_markup);
	$temp_markup = str_replace('<p>Manage your favourites.</p>', '<h3>Manage your favourites</h3>', $temp_markup);
	$temp_markup = str_replace('<p>Manage your saved searches.</p>', '<h3>Manage your saved searches</h3>', $temp_markup);
	$temp_markup = str_replace('No favourites found.', '<p class="item-list__empty">No favourites found.</p>', $temp_markup);
	$variables['content']['#markup'] = t($temp_markup);
}