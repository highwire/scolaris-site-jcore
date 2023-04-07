<?php

namespace Drupal\bps_content_display\Plugin\field_group\FieldGroupFormatter;

use Drupal\Component\Utility\Html;
use Drupal\field_group\FieldGroupFormatterBase;

/**
 * Plugin implementation of the 'HTML list' formatter.
 *
 * @FieldGroupFormatter(
 *   id = "bps_textfield_filter_wrapper",
 *   label = @Translation("BPS Textfield Filter Wrapper"),
 *   description = @Translation("Renders items prefixed with a textfield for filtering content."),
 *   supported_contexts = {
 *     "form",
 *     "view",
 *   },
 * )
 */
class TextfieldFilterWrapper extends FieldGroupFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function preRender(&$element, $rendering_object) {
    parent::preRender($element, $rendering_object);

    // Set variables for render.
    $element['#type'] = 'container';
    $element['#bps_textfield_filter'] = TRUE;

    // Add HTML ID.
    if ($this->getSetting('id')) {
      $element['#attributes']['id'] = $this->getSetting('id');
    }
    elseif ($this->context == 'form') {
      $element['#attributes']['id'] = Html::getUniqueId('edit-filter-' . $this->group->group_name);
    }
    else {
      $element['#attributes']['id'] = Html::getUniqueId('filter-' . $this->group->group_name);
    }

    // Add classes.
    if (empty($element['#attributes']['class'])) {
      $element['#attributes']['class'] = [];
    }

    $element['#attributes']['class'][] = 'textfield-filter-wrapper';

    $classes = $this->getClasses();
    if (!empty($classes)) {
      $element['#attributes']['class'] += $classes;
    }

    // Add textfield filter element.
    $filter_settings = $this->getSetting('filter_settings');
    $list = !empty($filter_settings['filter_parent_element']) ? '#' . $element['#attributes']['id'] . ' ' . $filter_settings['filter_parent_element'] : '#' . $element['#attributes']['id'];
    $toggle_elem = !empty($filter_settings['toggle_element']) ? $filter_settings['toggle_element'] : '> .field';
    $filter = !empty($filter_settings['filter_element']) ? $filter_settings['filter_element'] : $toggle_elem;
    $element['bps_textfield_filter'] = [
      '#type' => 'textfield_filter',
      '#list' => $list,
      '#toggle_element' => $toggle_elem,
      '#filter' => $filter,
      '#text_placeholder' => $this->getSetting('placeholder_text'),
      '#weight' => -100,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm() {

    $form = parent::settingsForm();

    $form['placeholder_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label / placeholder text'),
      '#description' => $this->t('Sets label and placeholder text for the textfield filter input box.'),
      '#default_value' => $this->getSetting('placeholder_text'),
    ];

    $form['textfield_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Textfield classes'),
      '#description' => $this->t('Additional classes for textfield filter input box.'),
      '#default_value' => $this->getSetting('textfield_classes'),
    ];

    $form['filter_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Filter Settings'),
      '#tree' => TRUE,
    ];

    $filter_settings = $this->getSetting('filter_settings');

    $form['filter_settings']['filter_parent_element'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filter parent element'),
      '#description' => $this->t('Selector for the parent element for filter behavior. This will be prefixed with the container\'s HTML ID.<br>If left empty, will automatically be set to this fieldgroup\'s wrapper element.'),
      '#default_value' => $filter_settings['filter_parent_element'],
    ];

    $form['filter_settings']['toggle_element'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Toggled element'),
      '#description' => $this->t('Selector for the elements that will be filtered.<br>If left empty, will automatically be set to this fieldgroup\'s child fields.'),
      '#default_value' => $filter_settings['toggle_element'],
    ];

    $form['filter_settings']['filter_element'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filter element'),
      '#description' => $this->t('Selector for the elements (within the parent) that will be checked for matches against the filter text. This can be set to the same selector as the "toggled element" option, but can be changed if more granular control is needed.<br>If left empty, will automatically be set to the same selector as the toggled element.'),
      '#default_value' => $filter_settings['filter_element'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultContextSettings($context) {
    $defaults = [
      'placeholder_text' => t('Find items in this list'),
      'textfield_classes' => '',
      'filter_settings' => [
        'filter_parent_element' => '',
        'toggle_element' => '',
        'filter_element' => '',
      ],
    ] + parent::defaultContextSettings($context);

    return $defaults;
  }

}