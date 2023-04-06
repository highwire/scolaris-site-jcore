<?php

use Drupal\Component\Utility\Html;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\Element;
use Drupal\Core\Template\Attribute;
use Drupal\contextual\Element\ContextualLinks;
use Drupal\facets\Plugin\Block\FacetBlock;
use Drupal\image\Entity\ImageStyle;
use Drupal\Node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\highwire_content\HighWireContent;

/**
 * @file
 * Functions to support theming node entities in the Particle theme.
 */
function bps_get_node_type_lable($key) {
  $typeArry = [
    'journal' => 'Periodical',
    'journal_article' => 'Periodical Article',
    'journal_fragment' => 'Periodical Fragment',
    'journal_issue' => 'Periodical Issue',
    'journal_volume' => 'Periodical Volume',
    'item_test_review' => 'Test Review',
    'item_monograph' => 'Monograph',
    'media' => 'Media',
    'item_report_guideline' => 'Reports and Guidelines',
    'item_chapter' => 'Chapter',
  ];
  return $typeArry[$key];
}

/**
 * Implements hook_preprocess__THEME().
 */
function bps_preprocess_node__search_result(&$variables) {
  $node = $variables['node'];
  if ($node !== NULL) {
    $variables = search_result($variables, $node);
  }
}

/**
 * Function for search result
 */
function search_result($variables, $node) {
  $content = $variables['content'];

  $node_type = $node->getType();
  $result = [];

  // Add access icon placeholder.
  $access_icon_placeholder = [
    '#type' => 'access_icon_placeholder',
    '#apath' => $node->get('apath')->getString(),
    '#context' => $node_type . ':search_result',
    '#access_icon' => 'bps_access_icon',
  ];
  $entity_type = 'node';
  $view_mode = 'search_short_description';
  $short_text = render(\Drupal::entityTypeManager()->getViewBuilder($entity_type)->view($node, $view_mode));

  $access_icon_render = \Drupal::service('renderer')->render($access_icon_placeholder);
  $variables['access_icon_placeholder'] = $access_icon_render;

  // Add item id.
  $result['id'] = $node->id();

  // Add title & Link to content.
  foreach (['label' => 'title', 'url' => 'url'] as $drupal => $pl) {
    if (!empty($variables[$drupal])) {
      $result[$pl] = $variables[$drupal];
    }
  }

  // Add node fields.
  $result += bps_get_node_fields($variables, ['subtitle' => 'subTitle']);

  // Add type label
  $result['type'] = bps_get_node_type_lable($node_type);

  $title = $node->get('title_plain')->getString() ? $node->get('title_plain')->getString() : '';
  $html_entity_decoded_title =  html_entity_decode($title);
  $result['title'] = $html_entity_decoded_title;
  // Grab node title for img alt attribute.
  $img_alt_text = $node->get('title_plain')->value ? $node->get('title_plain')->value : HighWireContent::processTitle($node->get('title')->value);
  $title_without_html = strip_tags($img_alt_text);
  $result['img_alt_text'] = $title_without_html;
  $result['short_description'] = $short_text;

  // Add fields based on node type.
  $theme_path = base_path() . drupal_get_path('theme', 'bps');

  switch ($node_type) {
    case HW_NODE_TYPE_BOOK:
    case HW_NODE_TYPE_REFERENCE:
      $result += bps_get_node_fields($variables, ['cover_image' => 'img', 'hwmd_abstract_book' => 'desc']);
      break;
    case in_array($node_type, HW_NODE_TYPE_BOOK_CHUNK):
      $result['img'] = $theme_path . '/images/icon-chapter.svg';
      $result += bps_get_node_fields($variables, ['parent_book' => 'source', 'hwmd_abstract_book' => 'desc']);
      break;
    case HW_NODE_TYPE_ARTICLE:
      $result['img'] = $theme_path . '/images/icon-article.svg';
      $result += bps_get_node_fields($variables, ['parent_journal' => 'source', 'hwmd_abstract_article_toc' => 'desc']);
      break;
    case HW_NODE_TYPE_CLINICAL_GUIDELINE:
      $result['img'] = $theme_path . '/images/icon-guideline.svg';
      $result += bps_get_node_fields($variables, ['parent_book' => 'source', 'hwmd_abstract_book' => 'desc']);
      break;
    case HW_NODE_TYPE_PRESCRIBING_GUIDELINE:
      $result['img'] = $theme_path . '/images/icon-prescribing.svg';
      $result += bps_get_node_fields($variables, ['parent_book' => 'source', 'hwmd_abstract_book' => 'desc']);
      break;
    case HW_NODE_TYPE_PATIENT_EDUCATION:
      $result['img'] = $theme_path . '/images/icon-patient-education.svg';
      $result += bps_get_node_fields($variables, ['parent_book' => 'source', 'hwmd_abstract_book' => 'desc']);
      break;
    case HW_NODE_TYPE_PROCEDURE:
      $result['img'] = $theme_path . '/images/icon-procedure.svg';
      $result += bps_get_node_fields($variables, ['parent_book' => 'source', 'hwmd_abstract_book' => 'desc']);
      break;
    case in_array($node_type, HW_NODE_TYPE_REFERENCE_CHUNK):
      $result['img'] = $theme_path . '/images/icon-reference.svg';
      $result += bps_get_node_fields($variables, ['parent_book' => 'source', 'hwmd_abstract_book' => 'desc']);
      break;
    case HW_NODE_TYPE_JOURNAL:
      $result += bps_get_node_fields($variables, ['journal_current_issue' => 'img']);
      $result += bps_get_node_pseudo_fields($variables, ['field_journal_description' => 'desc']);
      if (!empty($result['img']) && !empty($variables['url'])) {
        $result['img']['#prefix'] = '<a href="' . $variables['url'] . '">';
        $result['img']['#suffix'] = '</a>';
      }
      break;
    default:
      break;
  }

  if ($node_type == HW_NODE_TYPE_ISSUE) {
    $result += bps_get_node_fields($variables, ['variant_cover_image' => 'img', 'issue_title' => 'title', 'volume' => 'volume', 'issue' => 'issue',]);
    $result += bps_get_node_pseudo_fields($variables, ['description' => 'desc']);
    if (!empty($result['img']) && !empty($variables['url'])) {
      $result['img']['#prefix'] = '<a href="' . $variables['url'] . '">';
      $result['img']['#suffix'] = '</a>';
    }
  }

  // Add cover placeholder for books / journals.
  if ($node_type == HW_NODE_TYPE_BOOK || $node_type == HW_NODE_TYPE_JOURNAL) {
    if (empty($result['img']) && !empty($variables['cover_placeholder'])) {
      if (empty($variables['cover_placeholder']['#url']) && !empty($variables['url'])) {
        $variables['cover_placeholder']['#url'] = $variables['url'];
        $plain_title = strip_tags($variables['node']->getTitle());
        $variables['cover_placeholder']['#sr_text'] = t('Go to @title', ['@title' => $plain_title]);
      }
      $result['img'] = $variables['cover_placeholder'];
    }
  }
  $img_alt = '';
  $img_title = '';
  $media_url = '/themes/highwire/bps_theme/dist/app-drupal/assets/images/250X350.png';

  if ($node_type == 'journal_article') {
      $parent_journal = $node->get('parent_journal')->getValue();
      $parent_journal_nid = $parent_journal[0]['target_id'];
      if (!empty($parent_journal_nid)) {
          $node = Node::load($parent_journal_nid);
          $source_title = $node->getTitle();
          $source_url = $node->toUrl()->toString();
          $nid_journal_info = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
            'type' => 'journal_info',
            'field_journal' => $node->id()]);
            foreach ($nid_journal_info as $nid) {
              $node = $nid;
              $parent_journal_img = $node->get('field_journal_cover_image')->getValue();
              $image_tid = $parent_journal_img[0]['target_id'];
              if (!empty($image_tid)) {
                $file = File::load($image_tid);
                $path = $file->getFileUri();
                $media_url = file_create_url($path);
              }
            }
       }
    }

    if ($node_type == 'item_chapter') {
      $nid = $node->id();
      $options = ['absolute' => TRUE];
      $url_object = Url::fromRoute('entity.node.canonical', ['node' => $nid], $options);
      $chapter_url = $url_object->toString();
      $parent = $node->get('parent')->getValue();
      $parent_nid = $parent[0]['target_id'];
      $parent_issue_url = null;
        if (!empty($parent_nid)) {
            $node = Node::load($parent_nid);
            $url_object = Url::fromRoute('entity.node.canonical', ['node' => $parent_nid], $options);
            $parent_url = $url_object->toString();
        }
      $source_title = $node->get('title')->getstring();
      $source_url = $node->toUrl()->toString();
    }

  if($node->hasField('variant_cover_image') && !$node->get('variant_cover_image')->isEmpty()) {
    $imagefield = $node->get('variant_cover_image')->getValue();
    //check if node has image
    if (!empty($imagefield)) {
      $img_alt = $node->cover_image->alt;
      $img_title = $node->cover_image->title;
      $fileid = $imagefield[0]['target_id'];
      if (!empty($fileid)) {
        $file = File::load($fileid);
        if (!empty($file)) {
          $path = $file->getFileUri();
          $media_url = file_create_url($path);
        }
      }
    }
  }
  if($node->hasField('cover_image') && !$node->get('cover_image')->isEmpty()) {
    $imagefield = $node->get('cover_image')->getValue();
    //check if node has image
    if (!empty($imagefield)) {
      $img_alt = $node->cover_image->alt;
      $img_title = $node->cover_image->title;
      $fileid = $imagefield[0]['target_id'];
      if (!empty($fileid)) {
        $file = File::load($fileid);
        if (!empty($file)) {
          $path = $file->getFileUri();
          $media_url = file_create_url($path);
        }
      }
    }
  }
  if ($node_type == 'journal') {
    $node_id = $node->id();
    $info_nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'journal_info',
      'field_journal' => $node_id
    ]);
    $media_url = '/themes/highwire/bps_theme/dist/app-drupal/assets/images/250X350.png';
    if (!empty($info_nodes)) {
      $journal_info = reset($info_nodes);
      if (!$journal_info->get('field_journal_cover_image')->isEmpty()) {
        $node_cover_image = $journal_info->get('field_journal_cover_image')->getValue();
        $image_tid = $node_cover_image[0]['target_id'];
        if (!empty($image_tid)) {
          $file = File::load($image_tid);
          $path = $file->getFileUri();
          $media_url = file_create_url($path);
        }
      }
    }
  }
  $result['img'] = $media_url;
  if ($source_title) {
    $result['source'] = TRUE;
    $result['source_title'] = $source_title;
    $result['source_url'] = $source_url;
  }
  $variables['result'] = $result;
  return $variables;
}

/**
 * Implements hook_preprocess_node().
 */
function bps_preprocess_node(array &$variables) {
  // Default to turning off byline/submitted.
  $variables['display_submitted'] = FALSE;
  $node = \Drupal::routeMatch()->getParameter('node');
  if ($node instanceof \Drupal\node\NodeInterface) {
    $typeName = $node->bundle();

    //get the current node type
    $variables['content_type'] =  $typeName;

    //check if the page belongs to content type journal article
    if ($typeName == 'journal_article') {
      $nid = $node->id();
      $options = ['absolute' => TRUE];
      $url_object = Url::fromRoute('entity.node.canonical', ['node' => $nid], $options);
      $article_url = $url_object->toString();
      $parent_journal = $node->get('parent_journal')->getValue();
      $parent_issue = $node->get('parent_issue')->getValue();
      $parent_issue_nid = $parent_issue[0]['target_id'];
      $parent_issue_url = null;

      if (!empty($parent_issue)) {
        $node = Node::load($parent_issue_nid);
        $url_object = Url::fromRoute('entity.node.canonical', ['node' => $parent_issue_nid], $options);
        $parent_issue_url = $url_object->toString();
      }

      //get parent journal nid
      $parent_journal_nid = $parent_journal[0]['target_id'];
      $issue = $node->get('issue')->getValue();
      $issue = $issue[0]['value'];
      $volume = !$node->get('volume')->isEmpty() ? $node->get('volume')->getString() : '';
      //load parent journal
      if (!empty($parent_journal_nid)) {
        $node = Node::load($parent_journal_nid);
        if ($node->hasField('children')) {
          $children =  !$node->get('children')->isEmpty() ? $node->get('children')->getValue() : '';
        }
        // Get the total number of child volume for a Periodical
        $num_of_children = count($children);
        $url_object = Url::fromRoute('entity.node.canonical', ['node' => $parent_journal_nid], $options);
        $nid_journal_info = Drupal::entityQuery('node')->condition('type','journal_info')->condition('field_journal',$parent_journal_nid, '=')->execute();
        $cover_image = null;
        foreach ($nid_journal_info as $nid) {
          $node = Node::load($nid);
          $parent_journal_img = $node->get('field_journal_cover_image')->getValue();
          $image_tid = $parent_journal_img[0]['target_id'];
          if (!empty($image_tid)) {
            $file = File::load($image_tid);
            $path = $file->getFileUri();
            $cover_image = file_create_url($path);
          } else {
            $cover_image = '/themes/highwire/bps_theme/dist/app-drupal/assets/images/128X128.png';
          }
        }
      }
      // The Periodical that have only 1 volume should not display the volume numbers
      if ($num_of_children == 1) {
        $vol_issue = "Issue ".$issue;
      } else {
        $vol_issue = "Vol ".$volume." Issue ".$issue;
      }

			$periodical_url = $url_object->toString();
			$title = $node->title->value;
      $html_entity_decoded_title =  html_entity_decode($title);
      //set variable
      $variables['periodical_items'] = [
        'periodical_url'=> $periodical_url,
        'cover_img' => $cover_image,
        'title'=> $html_entity_decoded_title ,
        'article_url'=> $parent_issue_url,
        'vol_issue'=> $vol_issue
      ];
    }
  }
}

/**
 * Implements hook_preprocess_node().
 */
function bps_preprocess_node__journal_article__toc_list(array &$variables) {
  // Default to turning off byline/submitted.
  $variables['display_submitted'] = FALSE;
  $node = $variables['node'];
  $apath = $node->get('apath')->getValue()['0']['value'];
  $journal_title = !$node->get('journal_title')->isEmpty() ? $node->get('journal_title')->getString() : '';
  $journal_title = htmlspecialchars($journal_title);
  $volume = !$node->get('volume')->isEmpty() ? $node->get('volume')->getString() : '';
  $issue_number = !$node->get('issue')->isEmpty() ? $node->get('issue')->getString() : '';
  $first_page = !$node->get('fpage')->isEmpty() ? $node->get('fpage')->getString() : '';
  $last_page = !$node->get('lpage')->isEmpty() ? $node->get('lpage')->getString() : '';
  $journal_article_citation = $journal_title . ' ' . $volume . '(' . $issue_number . '): ' . $first_page . '-' . $last_page;
  $node_journal = \Drupal::routeMatch()->getParameter('node');
  if (!empty($node_journal)) {
    if ($node_journal->hasField('children') && $node_journal->bundle() == "journal") {
      $children = !$node_journal->get('children')->isEmpty() ? $node_journal->get('children')->getValue() : '';
      if (isset($children) && count($children) == 1) {
        $journal_article_citation = $journal_title . ' ' . $issue_number . ': ' . $first_page . '-' . $last_page;
      }
    } elseif ($node_journal->bundle() == "journal_issue") {
        $parent_journal = $node->get('parent_journal')->getString();
        //get parent journal nid
        if ($parent_journal) {
          $node_issue = Node::load($parent_journal);
          if ($node_issue->hasField('children')) {
            $children =  !$node_issue->get('children')->isEmpty() ? $node_issue->get('children')->getValue() : '';
            // Get the total number of child volume for a Periodical
            if (isset($children) && count($children) == 1) {
              $journal_article_citation = $journal_title . ' ' . $issue_number . ': ' . $first_page . '-' . $last_page;
            }
          }
        }
      }
  }
  // Fetching author names according to pattern lab array
  $authors = $node->get('authors_full_name')->getValue();
  $author_names = [];
  if (!empty($authors)) {
    foreach ($authors as $key => $value) {
      if ($key != (count($authors)-1)) {
        $author_names[$key] = ['paragraph' => ['text'=>$value['value'], 'class'=> 'article-section__author-name']];
      } else {
        $author_names[$key] = ['paragraph' => ['text'=>$value['value'], 'class'=> 'article-section__author-sub']];
      }
    }
  }
  array_push($author_names,['paragraph' => ['text'=> $journal_article_citation, 'class'=> 'article-section__author-description']]);
  $variables['content']['author_names'] = $author_names;

  // Fetching show abstract according to pattern lab array
  $action_links = [];
  $hwmd_abstract = $variables['content']['hwmd_abstract_article_toc_'];
  $abstract_markup = $hwmd_abstract['0']['markup'][$apath]['content']['#markup'];
  if ($abstract_markup != null ) {
    array_push($action_links, ['href' => '#','class' => 'article-section__abstract-btn show-click-toggle','text' => 'Show abstract']);
    $variables['content']['article_abstract'] = $abstract_markup;
  }

  // Fetching view content links according to pattern lab array
  $view_content_link = $variables['content']['view_content_link'];
  $view_content_link_url = $view_content_link['#url'];
  array_push($action_links, ['href' => $view_content_link_url->toString(),'class' => 'article-section__view-article-btn','text' => $view_content_link['#title']]);
  if (!empty($variables['elements']['variant_full_text_pdf']['#items']) && count($variables['elements']['variant_full_text_pdf']['#items']) > 0) {
    $variant_full_text_pdf = $variables['elements']['variant_full_text_pdf']['#items']->getValue('uri');
  } else {
    $variant_full_text_pdf['0']['uri'] = '#';
  }
  if (!empty($variant_full_text_pdf[0]['uri'])) {
    $variant_full_text_pdf_url  = '/' . str_replace('sass://', 'content/', $variant_full_text_pdf[0]['uri']);
  } else {
    $variant_full_text_pdf_url = '#';
  }
  $variables['content']['action_links'] = $action_links;

  // code written to parse the title to avoid issue mentioned in BPSSCOL-736
  $variables['label'] = html_entity_decode($node->get('title_plain')->getString());
}

/*
* Implements hook_preprocess_node().
*/
function bps_preprocess_node__journal__content_details(&$variables) {
  $node = $variables['node'];
  $addtoany = $variables['elements']['addtoany'];
  $hw_marker_sign_up = $variables['elements']['hw_marker_sign_up'];
  $hw_alerts_sign_up = $variables['elements']['hw_alerts_sign_up'];
  $sample_issue_link_nid = $node->get('journal_first_issue')->getString();
  if ($sample_issue_link_nid){
    $sample_issue_link = [
      '#type' => 'link',
      '#url' => Url::fromRoute('entity.node.canonical', ['node' => $sample_issue_link_nid], []),
      '#attributes' => ['class' => ['btn', 'btn-primary']],
    ];
    if (!empty($sample_issue_link['#url'])) {
      $sample_issue_link['#title'] = t('Sample issue');
    }
  }
  $current_issue_link = $variables['elements']['current_issue_link'];
  $title = $variables['elements']['title'];

  // creating variables for node template
  $variables['addtoany'] = $addtoany;
  $variables['hw_marker_sign_up'] = $hw_marker_sign_up;
  $variables['hw_alerts_sign_up'] = $hw_alerts_sign_up;
  $variables['sample_issue_link'] = $sample_issue_link;
   $variables['current_issue_link'] = $current_issue_link;
  //load journal_info  content type
   $info_nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'journal_info', 'field_journal' => $node->id() ]);
  $variables['title'] = $node->get('title')->getString();
  $variables['eissn'] = $node->get('journal_eissn')->getString();
  $variables['pissn'] = $node->get('journal_pissn')->getString();
  $variables['journal_coverimage'] = '/themes/highwire/bps_theme/dist/app-drupal/assets/images/250X350.png';
  //load data from journal_info node
  if (!empty($info_nodes)) {
    $journal_info = reset($info_nodes);
    $variables['journal_description'] = ($journal_info->get('field_journal_description')->getvalue()[0]['value']);
    $variables['journal_editors'] = $journal_info->get('field_journal_editors')->getvalue()[0]['value'];
    if (!$journal_info->get('field_journal_cover_image')->isEmpty()) {
      $node_cover_image = $journal_info->get('field_journal_cover_image')->getValue();
      $image_tid = $node_cover_image[0]['target_id'];
      if (!empty($image_tid)) {
        $file = File::load($image_tid);
        $path = $file->getFileUri();
        $variables['journal_coverimage'] = file_create_url($path);
      }
    }
    //load society field and check if the journal_society is not empty
    $journal_society = $journal_info->get('field_journal_society')->getString();
    if (!empty($journal_society)) {
      $node_society = \Drupal::entityTypeManager()->getStorage('node')->load($journal_society);
      $journal_society_title = $node_society->title->getString();
      if (!$node_society->get('field_society_log')->isEmpty()) {
        $journal_society_image = $node_society->get('field_society_log')->getValue();
        $image_tid = $journal_society_image[0]['target_id'];
        if (!empty($image_tid)) {
          $file = File::load($image_tid);
          $path = $file->getFileUri();
          $journal_society_imageUrl = file_create_url($path);
        }
      }
      $variables['society'] = [
        'src'=> $journal_society_imageUrl,
        'alt'=>  $journal_society_title,
        'title'=> $journal_society_title,
        'class'=> 'article__top_left-card-img'
      ];
    }
  }

}


/*
 * Implements hook_preprocess_node().
*/
function bps_preprocess_node__journal_issue__content_details(&$variables) {
  $node = $variables['node'];

  // code to get the  download isue pdf button link.
  $pdf_link = $node->get('enclosures')->getValue();
  if (basename($pdf_link[0]['uri']) == 'complete-issue.pdf') {
    $download_pdf_links = file_create_url($pdf_link[0]['uri']);
  }

  //set variable
  $node_title = $variables['node']->getTitle();
  $volume = $node->get('volume')->getString();
  $date_ppub = $node->get('date_ppub')->getValue()[0]['value'];
  $issue = $node->get('issue')->getString();
  $doi = $node->get('doi')->getString();
  $imagefield = $node->get('variant_cover_image')->getValue();
  $media_url = '/themes/highwire/bps_theme/dist/app-drupal/assets/images/250X350.png';
  $media_url_alt='Card Images';
  if (!empty($imagefield)) {
    $img_alt = $node->variant_cover_image->alt;
    $img_title = empty($img_alt) ? $node->variant_cover_image->title : $img_alt;
    $fileid = $imagefield[0]['target_id'];
    if (!empty($fileid)) {
      $file = File::load($fileid);
        if (!empty($file)) {
          $path = $file->getFileUri();
          $media_url = file_create_url($path);
          $media_url_alt = empty($img_title) ? 'Card Images' : $img_title;
        }
    }
  }

  // get parent_journal value
  $parent_journal = $node->get('parent_journal')->getValue();
  $parent_journal = $parent_journal[0]['target_id'];
  if (!empty( $parent_journal)) {
    $journal_node = Node::load($parent_journal);
    $eissn = $journal_node->get('journal_eissn')->getString();
    $pissn = $journal_node->get('journal_pissn')->getString();
    $node_title = $journal_node->title->value;
    if ($journal_node->hasField('children')) {
      $children = !$journal_node->get('children')->isEmpty() ? $journal_node->get('children')->getValue() : '';
    }
    // Get the total number of child volume for a Periodical
    $num_of_children = count($children);

    // Get url of parent periodical
    $options = ['absolute' => TRUE];
    $periodical_url_object = Url::fromRoute('entity.node.canonical', ['node' => $parent_journal], $options);
    $periodical_url = $periodical_url_object->toString();
  }
  $info_nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'journal_info', 'field_journal' => $parent_journal]);
  if (!empty($info_nodes)) {
    $journal_info = reset($info_nodes);
    $journal_society = $journal_info->get('field_journal_society')->getString();
    if (!empty($journal_society)) {
      $node_society = \Drupal::entityTypeManager()->getStorage('node')->load($journal_society);
      $imagefield = $node_society->get('field_society_log')->getValue();
      if (!empty($imagefield)) {
        $img_alt = $node_society->field_society_log->alt;
        $img_title = $node_society->field_society_log->title;
        $fileid = $imagefield[0]['target_id'];
        if (!empty($fileid)) {
          $journalissue_society_imgalt = !empty($img_title) ? $img_title : $img_alt;
          $file = File::load($fileid);
            if (!empty($file)) {
              $path = $file->getFileUri();
              $societymedia_url = file_create_url($path);
              $variables['society'] = ['src'=> $societymedia_url,
                'alt'=> $journalissue_society_imgalt,
                'title'=> $journalissue_society_imgalt,
                'class'=> 'article__top_left-card-img'
              ];
            }
        }
      }
    }
  }

  // The Periodical that have only 1 volume should not display the volume numbers
  if ($num_of_children == 1) {
    $vol_issue = "Issue ".$issue;
  } else {
    $vol_issue = "Volume ".$volume.", Issue ".$issue;
  }

   // creating variables for node template
   $variables['node_title'] = $node_title;
   $variables['download_pdf_links'] = $download_pdf_links;
   $variables['Journalissue'] = [
     'eissn'=> $eissn,
     'pissn'=> $pissn,
     'date_ppub'=> $date_ppub ,
     'doi'=> $doi,
     'variant_coverimage01'=> $media_url,
     'variant_coverimage01_alt'=> $media_url_alt,
     'vol_issue' => $vol_issue,
     'periodical_url' => $periodical_url,
   ];
   $variables['addtoany'] = $variables['elements']['addtoany'];
   $variables['hw_marker_sign_up'] = $variables['elements']['hw_marker_sign_up'];
   $variables['hw_alerts_sign_up'] = $variables['elements']['hw_alerts_sign_up'];
  $variables['main_heading_with_link'] = [
    'title' => $node_title,
    'href' => $periodical_url,
    'text' => $node_title,
  ];
}

/**
 * Implements hook_preprocess_node().
 */
function bps_preprocess_node__journal_article__content_details(array &$variables) {

  // Default to turning off byline/submitted.
  $variables['display_submitted'] = FALSE;
	$node = $variables['node'];

  // Journal Heading variables
	$article_type = $node->get('article_type')->getValue();
	$article_title = $node->get('title_plain')->getValue();
	$variables['main_title'] = ['text' => $article_title['0']['value'], 'class' => 'article__title'];
	$variables['prefix_title'] = ['text' => $article_type['0']['value'], 'class' => 'article__subtitle'];

  // Journal author variables
	$authors = $node->get('authors_full_name')->getValue();
	$author_names = [];
	foreach ($authors as $key => $value) {
		$author_names[$key] = ['text'=>$value['value'], 'class'=> 'article__fullname'];
	}
	$variables['authors'] = $author_names;

  // Journal description variables
  $doi = $node->get('doi')->getString();
  //New way to display DOI
  if (!empty($doi)) {
    $doi_link = 'https://doi.org/' . $doi;
    $variables['journal_doi'] = ['label' => 'DOI:', 'href' => $doi_link, 'title' => $doi, 'text' => $doi_link, 'target' => '_blank'];
  }

  // Action tools
	$highwire_citations_download = $variables['elements']['highwire_citations_download'];
  $addtoany = $variables['elements']['addtoany'];
  $ccc_permissions_link = $variables['elements']['ccc_permissions_link'];
  $hw_marker_sign_up = $variables['elements']['hw_marker_sign_up'];
  $hw_tag_sign_up = $variables['elements']['hw_tag_sign_up'];
  $hw_alerts_sign_up = $variables['elements']['hw_alerts_sign_up'];
  if (!empty($variables['elements']['variant_full_text_pdf']['#items']) && count($variables['elements']['variant_full_text_pdf']['#items']) > 0) {
    $pdf_summary = $variables['elements']['variant_full_text_pdf']['#items']->getValue('uri');
  }
  $variables['bps_citations_download'] = $highwire_citations_download;
  $variables['addtoany'] = $addtoany;
	$variables['ccc_permissions_link'] = $ccc_permissions_link;
  $variables['hw_marker_sign_up'] = $hw_marker_sign_up;
  $variables['hw_alerts_sign_up'] = $hw_alerts_sign_up;
  $variables['hw_tag_sign_up'] = $hw_tag_sign_up;
  if (!empty($pdf_summary[0]['uri'])) {
    $variables['variant_full_text_pdf'] = '/' . str_replace('sass://', 'content/', $pdf_summary[0]['uri']);
  }
}

/**
 * Implements hook_preprocess_node().
 */
function bps_preprocess_node__item_test_review__content_details(array &$variables) {

  // Default to turning off byline/submitted.
  $variables['display_submitted'] = FALSE;
	$node = $variables['node'];

  // Test review heading variables
	$test_review_title = $node->get('title_plain')->getValue();
  $test_review_publisher = $node->get('publisher')->getValue();
	$variables['main_title'] = ['text' => $test_review_title['0']['value'], 'class' => 'article__title'];
	$variables['suffix_title'] = ['text' => $test_review_publisher['0']['name'], 'class' => 'article__minititle'];
  $variables['journal_coverimage'] = '/themes/highwire/bps_theme/dist/app-drupal/assets/images/250X350.png';
  if (!$node->get('cover_image')->isEmpty()) {
    $test_review_cover_image = $node->get('cover_image')->getValue();
    $image_tid = $test_review_cover_image[0]['target_id'];
    if (!empty($image_tid)) {
      $file = File::load($image_tid);
      $path = $file->getFileUri();
      $variables['journal_coverimage'] = file_create_url($path);
    }
  }
  // Test review author variables
	$authors = $node->get('authors_full_name')->getValue();
	$author_names = [];
	foreach ($authors as $key => $value) {
		$author_names[$key] = ['text' => $value['value'], 'class' => 'article__fullname'];
	}
	$variables['authors'] = $author_names;

  // Test review meta content variables
	$doi = $node->get('doi')->getValue();
  $publicationDate = $node->get('date_print_publication')->getValue();
	$variables['doi'] = $doi['0']['value'];
  $variables['publicationDate'] = $publicationDate['0']['value'];

  // Action tools
	$highwire_citations_download = $variables['elements']['highwire_citations_download'];
  $addtoany = $variables['elements']['addtoany'];
  $ccc_permissions_link = $variables['elements']['ccc_permissions_link'];
  $hw_marker_sign_up = $variables['elements']['hw_marker_sign_up'];
  $hw_tag_sign_up = $variables['elements']['hw_tag_sign_up'];
  $hw_alerts_sign_up = $variables['elements']['hw_alerts_sign_up'];
  if (!empty($variables['elements']['pdf']['#items']) && count($variables['elements']['pdf']['#items']) > 0) {
    $pdf_summary = $variables['elements']['pdf']['#items']->getValue('uri');
  }
  $variables['bps_citations_download'] = $highwire_citations_download;
  $variables['addtoany'] = $addtoany;
	$variables['ccc_permissions_link'] = $ccc_permissions_link;
  $variables['hw_marker_sign_up'] = $hw_marker_sign_up;
  $variables['hw_alerts_sign_up'] = $hw_alerts_sign_up;
  $variables['hw_tag_sign_up'] = $hw_tag_sign_up;
  if (!empty($pdf_summary[0]['uri'])) {
    $variables['pdf'] = '/' . str_replace('binary://', 'binary/', $pdf_summary[0]['uri']);
  }

  // Load registered test icon content type
  $registered_test_icon_node = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'registered_test_icon', 'field_test_review_name' => $node->id()]);
  if(!empty($registered_test_icon_node)) {
    $registered_test_icon = reset($registered_test_icon_node);
    if (!$registered_test_icon->get('field_registered_test_icon_image')->isEmpty()) {
      $node_cover_image = $registered_test_icon->get('field_registered_test_icon_image')->getValue();
      $image_tid = $node_cover_image[0]['target_id'];
      $file = File::load($image_tid);
      $path = $file->getFileUri();
      $cover_image_src = file_create_url($path);
    }
  }

  $variables['registered_test_icon'] = [
    'src'=> $cover_image_src,
    'class'=> 'article__top_left-card-img'
  ];

}

/*
* Implements hook_preprocess_node().
*/
function bps_preprocess_node__item_chapter__content_details(&$variables) {
	// Default to turning off byline/submitted.
	$variables['display_submitted'] = FALSE;
	$node = $variables['node'];
	// Test review heading variables
	$test_review_title = $node->get('title_plain')->getString();
	$variables['main_title'] = ['text' => $test_review_title, 'class' => 'article__title'];

  // Test review author variables
	$authors = $node->get('authors_full_name')->getValue();
	$author_names = [];
	foreach ($authors as $key => $value) {
		$author_names[$key] = ['text' => $value['value'], 'class'=> 'article__fullname'];
	}
	$variables['authors'] = $author_names;

  // Test review meta content variables
	$doi = $node->get('doi')->getString();
  $publicationDate = $node->get('date_print_publication')->getString();
  $chapter_label = $node->get('book_part_label')->getString() ? $node->get('book_part_label')->getString() : '';
	$variables['doi'] = $doi;
  $variables['publicationDate'] = $publicationDate;
  $variables['chapter_label'] =  $chapter_label;


  // Action tools
	$highwire_citations_download = $variables['elements']['highwire_citations_download'];
  $addtoany = $variables['elements']['addtoany'];
  $ccc_permissions_link = $variables['elements']['ccc_permissions_link'];
  $hw_marker_sign_up = $variables['elements']['hw_marker_sign_up'];
  $hw_tag_sign_up = $variables['elements']['hw_tag_sign_up'];
  $hw_alerts_sign_up = $variables['elements']['hw_alerts_sign_up'];
  if (!empty($variables['elements']['pdf']['#items']) && count($variables['elements']['pdf']['#items']) > 0) {
    $pdf_summary = $variables['elements']['pdf']['#items']->getString('uri');
  }
  $variables['bps_citations_download'] = $highwire_citations_download;
  $variables['addtoany'] = $addtoany;
	$variables['ccc_permissions_link'] = $ccc_permissions_link;
  $variables['hw_marker_sign_up'] = $hw_marker_sign_up;
  $variables['hw_alerts_sign_up'] = $hw_alerts_sign_up;
  $variables['hw_tag_sign_up'] = $hw_tag_sign_up;
  if (!empty($pdf_summary)) {
    $variables['pdf'] = '/' . str_replace('sass://', 'content/', $pdf_summary[0]['uri']);
  }
	//check if the page belongs to content type item_chapter
	$nid = $node->id();
	$options = ['absolute' => TRUE];
	$url_object = Url::fromRoute('entity.node.canonical', ['node' => $nid], $options);
	$chapter_url = $url_object->toString();
	$parent = $node->get('parent')->getValue();
	$parent_nid = $parent[0]['target_id'];
	$parent_issue_url = null;
	if (!empty($parent_nid)) {
		$node = Node::load($parent_nid);
		$url_object = Url::fromRoute('entity.node.canonical', ['node' => $parent_nid], $options);
		$parent_url = $url_object->toString();
		$parent_journal_img = $node->get('cover_image')->getValue();
		$image_tid = $parent_journal_img[0]['target_id'];
			if (!empty($image_tid)) {
				$file = File::load($image_tid);
				$path = $file->getFileUri();
				$cover_image = file_create_url($path);
			} else {
				$cover_image = '/themes/highwire/bps_theme/dist/app-drupal/assets/images/128X128.png';
			}
	}
	$periodical_url = $url_object->toString();
	$title = $node->title->value;
	//set variable
	$variables['periodical_items'] = [
		'periodical_url'=> $periodical_url,
		'cover_img' => $cover_image,
		'title'=> $title ,
		'monograph_url'=> $parent_url
	];
 }

/**
 * Implements hook_preprocess_node().
 */
function bps_preprocess_node__item_monograph__content_details(array &$variables) {

  // Default to turning off byline/submitted.
  $variables['display_submitted'] = FALSE;
	$node = $variables['node'];

  // Test review heading variables
	$test_review_title = $node->get('title_plain')->getString();
	$variables['main_title'] = ['text' => $test_review_title, 'class' => 'article__title'];
  $monograph_publisher = $node->get('publisher')->getValue();
	$variables['suffix_title'] = ['text' => $monograph_publisher['0']['name'], 'class' => 'article__minititle'];

  $cover_image = $node->get('cover_image')->getValue();
  $image_tid = $cover_image[0]['target_id'];
  if (!empty($image_tid)) {
    $file = File::load($image_tid);
    $path = $file->getFileUri();
    $variables['cover_image'] = file_create_url($path);
  }

  // Test review author variables
	$authors = $node->get('editors_full_name')->getValue();
	$author_names = [];
	foreach ($authors as $key => $value) {
		$author_names[$key] = ['text'=>$value['value'], 'class'=> 'article__fullname'];
	}
	$variables['authors'] = $author_names;

  // Test review meta content variables
	$doi = $node->get('doi')->getString();
  $publicationDate = $node->get('date_print_publication')->getString();
	$variables['doi'] = $doi;
  $variables['publicationDate'] = $publicationDate;

  // Action tools
	$highwire_citations_download = $variables['elements']['highwire_citations_download'];
  $addtoany = $variables['elements']['addtoany'];
  $ccc_permissions_link = $variables['elements']['ccc_permissions_link'];
  $hw_marker_sign_up = $variables['elements']['hw_marker_sign_up'];
  $hw_tag_sign_up = $variables['elements']['hw_tag_sign_up'];
  $hw_alerts_sign_up = $variables['elements']['hw_alerts_sign_up'];
  if (!empty($variables['elements']['pdf']['#items']) && count($variables['elements']['pdf']['#items']) > 0) {
    $pdf_summary = $variables['elements']['pdf']['#items']->getValue('uri');
  }
  $variables['bps_citations_download'] = $highwire_citations_download;
  $variables['addtoany'] = $addtoany;
	$variables['ccc_permissions_link'] = $ccc_permissions_link;
  $variables['hw_marker_sign_up'] = $hw_marker_sign_up;
  $variables['hw_alerts_sign_up'] = $hw_alerts_sign_up;
  $variables['hw_tag_sign_up'] = $hw_tag_sign_up;
  if (!empty($pdf_summary[0]['uri'])) {
    $variables['pdf_summary'] = '/' . str_replace('binary://', 'binary/', $pdf_summary[0]['uri']);
  }
}

/**
 * Implements hook_preprocess_node().
 */
function bps_preprocess_node__item_report_guideline__content_details(array &$variables) {

  // Default to turning off byline/submitted.
  $variables['display_submitted'] = FALSE;
	$node = $variables['node'];

  // Report guideline heading variables
	$report_guideline_title = $node->get('title_plain')->getValue();
	$variables['main_title'] = ['text' => $report_guideline_title['0']['value'], 'class' => 'article__title'];
  $report_guideline_cover_image = $node->get('cover_image')->getValue();
  $image_tid = $report_guideline_cover_image[0]['target_id'];
  if (!empty($image_tid)) {
    $file = File::load($image_tid);
    $path = $file->getFileUri();
    $variables['report_guideline_cover_img'] = file_create_url($path);
  }
  $member_network = $node->hasField('member_network') && !$node->get('member_network')->isEmpty() ? $node->get('member_network')->getString() : '';
  $variables['member_network'] = ['text' => $member_network, 'class' => 'article__minititle'];
  // Report guideline author variables
	$authors = $node->get('authors_full_name')->getValue();
	$author_names = [];
	foreach ($authors as $key => $value) {
		$author_names[$key] = ['text'=>$value['value'], 'class'=> 'article__fullname'];
	}
	$variables['authors'] = $author_names;
	$doi = $node->hasField('doi') && !$node->get('doi')->isEmpty() ? $node->get('doi')->getString() : '';
  $publicationDate = $node->hasField('date_print_publication') && !$node->get('date_print_publication')->isEmpty() ? $node->get('date_print_publication')->getString() : '';
  $isbn_print = $node->hasField('isbn_print') && !$node->get('isbn_print')->isEmpty() ? $node->get('isbn_print')->getString() : '';
	$variables['doi'] = $doi;
  $date_element = explode("-", $publicationDate);
  $date_element_length = count($date_element);
  if ($date_element_length == '1') {
    $variables['publicationDate'] = $publicationDate;
  }
  elseif ($date_element_length == '2') {
    $variables['publicationDate'] = date("m-Y", strtotime($publicationDate));
  }
  elseif ($date_element_length == '3') {
    $variables['publicationDate'] = date("d-m-Y", strtotime($publicationDate));
  }
  $variables['isbn_print'] = $isbn_print;
  // Action tools
	$highwire_citations_download = $variables['elements']['highwire_citations_download'];
  $addtoany = $variables['elements']['addtoany'];
  $ccc_permissions_link = $variables['elements']['ccc_permissions_link'];
  $hw_marker_sign_up = $variables['elements']['hw_marker_sign_up'];
  $hw_tag_sign_up = $variables['elements']['hw_tag_sign_up'];
  $hw_alerts_sign_up = $variables['elements']['hw_alerts_sign_up'];
  if (!empty($variables['elements']['pdf']['#items']) && count($variables['elements']['pdf']['#items']) > 0) {
    $pdf_summary = $variables['elements']['pdf']['#items']->getValue('uri');
  }
  $variables['bps_citations_download'] = $highwire_citations_download;
  $variables['addtoany'] = $addtoany;
	$variables['ccc_permissions_link'] = $ccc_permissions_link;
  $variables['hw_marker_sign_up'] = $hw_marker_sign_up;
  $variables['hw_alerts_sign_up'] = $hw_alerts_sign_up;
  $variables['hw_tag_sign_up'] = $hw_tag_sign_up;
  if (!empty($pdf_summary[0]['uri'])) {
    $variables['pdf_summary'] = '/' . str_replace('binary://', 'binary/', $pdf_summary[0]['uri']);
  }
}

/*
* Implements hook_preprocess_node().
*/
function bps_preprocess_node__item_front_matter__content_details(&$variables) {
  if (empty($variables['node'])) {
    return;
  }
	$node = $variables['node'];
	$front_matter_title = $node->get('title_plain')->getString();
	$variables['main_title'] = ['text' => $front_matter_title, 'class' => 'article__title'];
	$doi = !$node->get('doi')->isEmpty() ? $node->get('doi')->getString() : '';
	$variables['doi'] = $doi;
  // Action tools
	$highwire_citations_download = $variables['elements']['highwire_citations_download'];
  $addtoany = $variables['elements']['addtoany'];
  $ccc_permissions_link = $variables['elements']['ccc_permissions_link'];
  $hw_marker_sign_up = $variables['elements']['hw_marker_sign_up'];
  $hw_tag_sign_up = $variables['elements']['hw_tag_sign_up'];
  $variables['bps_citations_download'] = $highwire_citations_download;
  $variables['addtoany'] = $addtoany;
	$variables['ccc_permissions_link'] = $ccc_permissions_link;
  $variables['hw_marker_sign_up'] = $hw_marker_sign_up;
  $variables['hw_tag_sign_up'] = $hw_tag_sign_up;

	$nid = $node->id();
	$options = ['absolute' => TRUE];
	$url_object = Url::fromRoute('entity.node.canonical', ['node' => $nid], $options);
	$chapter_url = $url_object->toString();
	$parent_nid = !$node->get('parent')->isEmpty() ? $node->get('parent')->getString() : '';
	if (!empty($parent_nid)) {
		$node = Node::load($parent_nid);
		$url_object = Url::fromRoute('entity.node.canonical', ['node' => $parent_nid], $options);
		$parent_url = $url_object->toString();
		$parent_journal_img = $node->get('cover_image')->getValue();
		$image_tid = $parent_journal_img[0]['target_id'];
			if (!empty($image_tid)) {
				$file = File::load($image_tid);
				$path = $file->getFileUri();
				$cover_image = file_create_url($path);
			} else {
				$cover_image = '/themes/highwire/bps_theme/dist/app-drupal/assets/images/128X128.png';
			}
	}
	$periodical_url = $url_object->toString();
	$title = $node->title->value;
	//set variable
	$variables['periodical_items'] = [
		'periodical_url'=> $periodical_url,
		'cover_img' => $cover_image,
		'title'=> $title ,
		'parent_url'=> $parent_url
	];
}

/*
* Implements hook_preprocess_node().
*/
function bps_preprocess_node__item_back_matter__content_details(&$variables) {
  if (empty($variables['node'])) {
    return;
  }
	$node = $variables['node'];
	$back_matter_title = $node->get('title_plain')->getString();
	$variables['main_title'] = ['text' => $back_matter_title, 'class' => 'article__title'];
	$doi = !$node->get('doi')->isEmpty() ? $node->get('doi')->getString() : '';
	$variables['doi'] = $doi;
  // Action tools
	$highwire_citations_download = $variables['elements']['highwire_citations_download'];
  $addtoany = $variables['elements']['addtoany'];
  $ccc_permissions_link = $variables['elements']['ccc_permissions_link'];
  $hw_marker_sign_up = $variables['elements']['hw_marker_sign_up'];
  $hw_tag_sign_up = $variables['elements']['hw_tag_sign_up'];
  $variables['bps_citations_download'] = $highwire_citations_download;
  $variables['addtoany'] = $addtoany;
	$variables['ccc_permissions_link'] = $ccc_permissions_link;
  $variables['hw_marker_sign_up'] = $hw_marker_sign_up;
  $variables['hw_tag_sign_up'] = $hw_tag_sign_up;

	$nid = $node->id();
	$options = ['absolute' => TRUE];
	$url_object = Url::fromRoute('entity.node.canonical', ['node' => $nid], $options);
	$chapter_url = $url_object->toString();
	$parent_nid = !$node->get('parent')->isEmpty() ? $node->get('parent')->getString() : '';
	if (!empty($parent_nid)) {
		$node = Node::load($parent_nid);
		$url_object = Url::fromRoute('entity.node.canonical', ['node' => $parent_nid], $options);
		$parent_url = $url_object->toString();
		$parent_journal_img = $node->get('cover_image')->getValue();
		$image_tid = $parent_journal_img[0]['target_id'];
			if (!empty($image_tid)) {
				$file = File::load($image_tid);
				$path = $file->getFileUri();
				$cover_image = file_create_url($path);
			} else {
				$cover_image = '/themes/highwire/bps_theme/dist/app-drupal/assets/images/128X128.png';
			}
	}
	$periodical_url = $url_object->toString();
	$title = $node->title->value;
	//set variable
	$variables['periodical_items'] = [
		'periodical_url'=> $periodical_url,
		'cover_img' => $cover_image,
		'title'=> $title ,
		'parent_url'=> $parent_url
	];
}