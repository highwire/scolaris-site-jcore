<?php

namespace Drupal\bps_content_display\Plugin\HWMarkup\Processor;

use Drupal\highwire_markup\ProcessorBase;
use HighWire\Parser\Markup\Markup;
use Symfony\Component\CssSelector\CssSelectorConverter;

/**
 * @Processor(
 *   id = "bps_brightcove",
 *   name = "Bps: Brightcove",
 *   description = "Embed Brightcove video and audio player."
 * )
 */
class BpsBrightcove extends ProcessorBase {

  /**
   * {@inheritdoc}
   */
  public function process(Markup $markup, $context = NULL) {
    $config = $this->getConfig();
    $save_markup = FALSE;

    // Iterate each format medium and search the markup for elements that match each medium's xpath setting.
    foreach (['bc_video', 'bc_audio'] as $medium) {
      if (!empty($config[$medium]['xpath'])) {
        $sources = $markup->xpath($config[$medium]['xpath']);

        if (is_object($sources) && $sources->count() > 0) {
          foreach ($sources as $source) {
            if ($source->hasAttribute('id')) {
              $id = $source->getAttribute('id');
            }

            // Fetch the media object.
            $source_object = $source->getElementsByTagName('object');

            // Check we have an <object> and that it has a data attribute.
            if (!is_object($source_object) && $source_object->count() < 1) continue;
            if (!$source_object->item(0)->hasAttribute('data')) continue;

            $source_url = $source_object->item(0)->getAttribute('data');
            $width = $config[$medium]['width'];
            $height = $config[$medium]['height'];

            $player = [
              '#theme' => 'bps_brightcove',
              '#type' => substr($medium, 3),
              '#url' => $source_url,
              '#width' => $width,
              '#height' => $height,
              '#id' => $id,
            ];

            // Replace the Markup media HTML with the rendered embedded media player HTML.
            $player_html = (String) \Drupal::service('renderer')->render($player);
            $player_node = $markup->createDocumentFragment();
            $player_node->appendXML($player_html);
            $source->parentNode->replaceChild($player_node, $source);
            $save_markup = TRUE;
          }
        }
      }
    }

    if ($save_markup) {
      $markup->saveHTML($player_node);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function configForm(array $config = NULL) {
    $config = $this->getConfig();
    $form = ['#tree' => TRUE];

    // BrightCove Video Player.
    $form['bc_video'] = [
      '#type' => 'details',
      '#title' => 'Video',
      '#open' => FALSE,
    ];

    $form['bc_video']['xpath'] = [
      '#type' => 'textfield',
      '#title' => 'Video XPATH in markup',
      '#description' => t('Used when parsing the markup for brightcove video content. Ex: //div[@class="videodata"]'),
      '#default_value' => isset($config['bc_video']['xpath']) ? $config['bc_video']['xpath'] : '',
    ];

    $form['bc_video']['width'] = [
      '#type' => 'textfield',
      '#title' => 'Default video width',
      '#size' => 5,
      '#description' => t('Default width of the brightcove player, this value will be used if no width is provided in the source.'),
      '#default_value' => isset($config['bc_video']['width']) ? $config['bc_video']['width'] : '480',
    ];

    $form['bc_video']['height'] = [
      '#type' => 'textfield',
      '#title' => 'Default video height',
      '#size' => 5,
      '#description' => t('Default height of the brightcove player, this value will be used if no height is provided in the source.'),
      '#default_value' => isset($config['bc_video']['height']) ? $config['bc_video']['height'] : '270',
    ];

    // BrightCove Audio Player.
    $form['bc_audio'] = [
      '#type' => 'details',
      '#title' => 'Audio',
      '#open' => FALSE,
    ];

    $form['bc_audio']['xpath'] = [
      '#type' => 'textfield',
      '#title' => 'Audio XPATH in markup',
      '#description' => t('Used when parsing the markup for brightcove audio content. Ex: //div[@class="audiodata"]'),
      '#default_value' => isset($config['bc_audio']['xpath']) ? $config['bc_audio']['xpath'] : '',
    ];

    $form['bc_audio']['width'] = [
      '#type' => 'textfield',
      '#title' => 'Default audio width',
      '#size' => 5,
      '#description' => t('Default width of the brightcove player, this value will be used if no width is provided in the source.'),
      '#default_value' => isset($config['bc_audio']['width']) ? $config['bc_audio']['width'] : '480',
    ];

    $form['bc_audio']['height'] = [
      '#type' => 'textfield',
      '#title' => 'Default audio height',
      '#size' => 5,
      '#description' => t('Default height of the brightcove player, this value will be used if no height is provided in the source.'),
      '#default_value' => isset($config['bc_audio']['height']) ? $config['bc_audio']['height'] : '80',
    ];

    return $form;
  }

}
