<?php

namespace Drupal\bps_content_display\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a render element for a modal popup.
 *
 * Usage example:
 * @code
 * $build['icon'] = [
 *   '#type' => 'bps_icon',
 *   '#icon' => 'twitter',
 *   '#icon_library' => 'fab',
 * ];
 * @endcode
 *
 * @RenderElement("bps_icon")
 */
class Icon extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#process' => [
        [$class, 'processIcon'],
      ],
      '#pre_render' => [
        [$class, 'preRenderIcon'],
      ],
      '#theme' => 'bps_icon',
      '#icon' => '',
      '#icon_prefix' => 'fa',
      '#icon_library' => 'fas',
    ];
  }

  /**
   * Pre-render callback: Renders an HTML list with attributes.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   element.
   *
   * @return array
   */
  public static function preRenderIcon($element) {
    return $element;
  }

  /**
   * Process a html element
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   details element.
   * @param FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The processed element.
   */
  public static function processIcon(&$element, FormStateInterface $form_state) {
    return $element;
  }

}
