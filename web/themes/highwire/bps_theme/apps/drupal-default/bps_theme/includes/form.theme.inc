<?php

/**
 * @file
 * Functions to support theming forms in the Particle theme.
 */
/**
 * Template suggestions for form hook.
 *
 * @param array $suggestions
 * @param array $variables
 */
function bps_form_alter(&$form, \Drupal\Core\Form\FormStateInterface $form_state, $form_id) {
  if ($form_id == 'views_exposed_form') {
    $form['actions']['submit']['#attributes']['class'][] = 'btn btn-search';
  }
}