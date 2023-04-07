<?php

/**
 * @file
 * Functions to support adding suggestions to the Particle theme.
 */

/**
 * Implements hook_theme_suggestions_block_alter().
 */
function bps_theme_suggestions_block_alter(array &$suggestions, array $variables) {
  // Provide suggestion for block templates by custom block type.
  if (!empty($variables['elements']['content']['#block_content'])) {
    $block = $variables['elements']['content']['#block_content'];
    // Add `block--BLOCK-TYPE.html.twig`.
    $suggestions[] = 'block__' . $block->bundle();
    $view_mode = $variables['elements']['#configuration']['view_mode'];
    if (!empty($view_mode)) {
      // Add `block--BLOCK-TYPE--VIEW-MODE.html.twig`.
      $suggestions[] = 'block__' . $block->bundle() . '__' . $view_mode;
    }
  }
  // Add template suggestions to menu blocks based on left region position.
  if ($variables['elements']['#base_plugin_id'] == 'system_menu_block') {
    $menu_name = $variables['elements']['#derivative_plugin_id'];
    // Load block to get region.
    $id = !empty($variables['elements']['#id']) ? $variables['elements']['#id'] : '';
    $block = !empty($id) ? \Drupal\block\Entity\Block::load($id) : '';
    $region = !empty($block) ? $block->getRegion() : '';
    // Check in configuration for region (e.g. for menu blocks in panels).
    if (empty($region) && !empty($variables['elements']['#configuration']['region'])) {
      $region = $variables['elements']['#configuration']['region'];
    }
    switch ($region) {
      case 'left':
        // Add left sidebar menu style.
        $suggestions[] = 'block__' . $variables['elements']['#base_plugin_id'] . '__sidebar';
        $suggestions[] = 'block__' . $variables['elements']['#base_plugin_id'] . '__' . $menu_name . '__sidebar';
      break;
    }
  }
}

/**
 * Implements hook_theme_suggestions_field_alter().
 */
function bps_theme_suggestions_field_alter(array &$suggestions, array $variables) {
  $element = $variables['element'];
  $suggestions[] = 'field__' . $element['#view_mode'];
  $suggestions[] = 'field__' . $element['#view_mode'] . '__' . $element['#field_name'];
  $hw_content_types = bps_core_get_hw_content_types();
  if (in_array($element['#bundle'], $hw_content_types)) {
    switch ($element['#field_name']) {
      case 'title':
        $suggestions[] = 'field__highwire_content__title';
        break;

      case 'pdf':
      case 'variant_full_text_pdf':
        $suggestions[] = 'field__pdf_button';
        if (!empty($element['#view_mode'])) {
          $suggestions[] = 'field__pdf_button__' . $element['#view_mode'];
        }
        break;
    }
  }
}

/**
 * Implements hook_theme_suggestions_field_alter().
 */
function bps_theme_suggestions_fieldset_alter(array &$suggestions, array $variables) {
  $element = $variables['element'];
  if (isset($element['#attributes']['class']) && in_array('form-composite', $element['#attributes']['class'])) {
    $suggestions[] = 'fieldset__form_composite';
  }
}

/**
 * Implements hook_theme_suggestions_image_alter().
 */
function bps_theme_suggestions_image_alter(array &$suggestions, array $variables) {
  $node = \Drupal::request()->attributes->get('node');
  if (!empty($node)) {
    $suggestions[] = 'image__' . $node->getType();
  }
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 *
 * @TODO evaluate for Search.
 */
function bps_theme_suggestions_form_alter(array &$suggestions, array $variables) {
  $id = $variables['element']['#id'];
  // Particle Theme for Octane's Views Search and Drupal's Built-In Search.
  // This hook allows to apply two search block implementations to the same
  // theme file. Drupal only template file located at
  // /Particle/apps/drupal-default/templates/form/form--particle-search.html.twig.
  if ($id == 'views-exposed-form-search-page' || $id == 'search-block-form') {
    $suggestions[] = 'form__particle-search';
  }
  if ($id == 'views-exposed-form-search-page-1') {
    $suggestions[] = 'form__search_exposed_form';
  }
  $suggestions[] = 'form__' . str_replace('-','_', $id);
}

/**
 * Implements hook_preprocess__THEME().
 */
function bps_preprocess_block__system_menu_block__sidebar(&$variables) {
  if (empty($variables['content']['#items']) || empty($variables['content']['#theme'])) {
    return;
  }
  $variables['content']['#attributes']['data-menu-title'] = $variables['label'];
  $variables['content']['#theme'] .= '__sidebar';
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function bps_theme_suggestions_menu_alter(array &$suggestions, array $variables) {
  if (strpos($variables['theme_hook_original'], '__sidebar') !== FALSE) {
    $suggestions[] = 'menu__sidebar';
    $suggestions[] = 'menu__' . $variables['menu_name'] . '__sidebar';
  }
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function bps_theme_suggestions_facet_block_alter(array &$suggestions, array $variables) {
  // Add different facet block styles for specific facets.
  if (!empty($variables['facet_id'])) {
    switch ($variables['facet_id']) {
      case 'keywords':
      case 'author':
      case 'member_network':
      case 'article_type':
      case 'publication_title':
      case 'subject':
      case 'content_type_facet':
        $suggestions[] = 'facet_block__modal';
        break;

      case 'bps_search_date_filter':
        $suggestions[] = 'facet_block__collapse';
        break;
    }
  }
}