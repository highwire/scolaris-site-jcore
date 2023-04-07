<?php

/**
 * @file
 * Functions to support theming fields in the Particle theme.
 */

/**
 * Implements hook_preprocess__THEME().
 */
function bps_preprocess_field__highwire_content__title(&$variables) {
  if (empty($variables['items'])) {
    return;
  }
  $bundle = !empty($variables['element']['#bundle']) ? $variables['element']['#bundle'] : '';
  $view_mode = !empty($variables['element']['#view_mode']) ? $variables['element']['#view_mode'] : '';
  $prefix = '';
  $prefix_template = '';
  // Add chapter label as prefix for chapter-like types.
  if (in_array($bundle, bps_core_get_book_chunk_types())) {
    $entity = !empty($variables['element']['#object']) ? $variables['element']['#object'] : FALSE;
    if ($entity->hasField('chapter_section_label') && !$entity->get('chapter_section_label')->isEmpty()) {
      $prefix = $entity->get('chapter_section_label')->getString();
    }
    else {
      $prefix = $entity->hasField('book_part_label') && !$entity->get('book_part_label')->isEmpty() ? $entity->get('book_part_label')->getString() : '';
    }
    $prefix_template = '{{ title_prefix }}';
    if ($view_mode == 'toc_list') {
      $prefix_template = '<span class="title-prefix">' . $prefix_template . ' </span>';
    }
    else {
      $prefix_template .= '. ';
    }
  }
  foreach ($variables['items'] as &$item) {
    // Alter inline template to allow HTML.
    if (empty($item['content']['#type'])) {
      continue;
    }
    $content = [];
    switch($item['content']['#type']) {
      case 'link':
        if (!empty($item['content']['#title']['#type']) && $item['content']['#title']['#type'] == 'inline_template') {
          $content = &$item['content']['#title'];
        }
        break;

      case 'inline_template':
        $content = &$item['content'];
        break;
    }
    if (empty($content)) {
      continue;
    }
    $content['#template'] = "{{ value|raw }}";
    // Add prefix label.
    if (!empty($prefix)) {
      $content['#template'] = $prefix_template . $content['#template'];
      $content['#context']['title_prefix'] = $prefix;
    }
  }
}

/**
 * Implements hook_preprocess__THEME().
 */
function bps_preprocess_field_group_html_element(&$variables) {
  $attributes = ($variables['attributes'])->toArray();
  if (isset($attributes['class'][0]) && !empty($attributes['class'][0]) && $attributes['class'][0] == "tab-content__main-heading latest-articles") {
    $variables['attributes']->removeClass('tab-content__main-heading latest-articles');
    $variables['attributes']->addClass('latest-articles');
  }
}

/**
 * Implements hook_preprocess__THEME().
 */
function bps_preprocess_field__pdf_button(&$variables) {
  $current_user = \Drupal::currentUser();
  $roles = $current_user->getRoles();
  if (in_array("content_editor", $roles)) {
    unset($variables['attributes']['data-access-link']);
  }
}