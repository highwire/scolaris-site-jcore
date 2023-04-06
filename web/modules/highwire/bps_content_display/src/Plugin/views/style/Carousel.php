<?php

namespace Drupal\bps_content_display\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\HtmlList;

/**
 * Style plugin to render each item in an ordered or unordered list.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "scolaris_bps_views_carousel",
 *   title = @Translation("Carousel"),
 *   help = @Translation("Displays rows as carousel slides (uses Swiper)."),
 *   theme = "views_view_carousel",
 *   display_types = {"normal"}
 * )
 */
class Carousel extends HtmlList {

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * Does the style plugin support custom css class for the rows.
   *
   * @var bool
   */
  protected $usesRowClass = TRUE;

  /**
   * Set default options
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['wrapper_class'] = ['default' => ''];
    $options['pagination'] = ['default' => 0];
    $options['navigation'] = ['default' => 1];
    $options['scrollbar'] = ['default' => 0];
    return $options;
  }

  /**
   * Render the given style.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['pagination'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display pagination?'),
      '#default_value' => $this->options['pagination'],
    ];
    $form['navigation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display prev/next navigation?'),
      '#default_value' => $this->options['navigation'],
    ];
    $form['scrollbar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display scrollbar?'),
      '#default_value' => $this->options['scrollbar'],
    ];
  }
}
