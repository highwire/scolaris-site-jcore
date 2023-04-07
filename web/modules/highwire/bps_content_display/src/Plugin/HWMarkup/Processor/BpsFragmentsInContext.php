<?php

namespace Drupal\bps_content_display\Plugin\HWMarkup\Processor;

use Drupal\highwire_markup\ProcessorBase;
use HighWire\Parser\Markup\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * BpsFragmentsInContext processor.
 *
 * @Processor(
 *   id = "bps_fragments_in_context",
 *   name = "Bps: Fragments in Context",
 *   description = "Place a 'view in context' link for fragments"
 * )
 */
class BpsFragmentsInContext extends ProcessorBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * Create an NLM Processor
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function process(Markup $markup, $context = NULL) {
    $figpanel = $markup->xpath("//div[@class='fig panel']");    
    foreach ($figpanel as $fig) {
      if ($anchor_id = $fig->getAttribute('id')) {
        $link = "<div class='link-outer'><a href='#$anchor_id' class='btn btn-primary view-in-context'>View in Context</a></div>";
        $markup->append($link, $fig);
        $fig->setAttribute('id', 'figures-and-tables-' . $anchor_id);
      }
    }
  
    $tablepanel = $markup->xpath("//div[@class='table-wrap panel']");    
    foreach ($tablepanel as $table) {
      if ($anchor_id = $table->getAttribute('id')) {
        $link = "<div class='link-outer'><a href='#$anchor_id' class='btn btn-primary view-in-context'>View in Context</a></div>";
        $markup->append($link, $table);
        $table->setAttribute('id', 'figures-and-tables-' . $anchor_id);
      }
    }
  }

  public function processRenderArray(array &$render, Markup $markup, $context = NULL) {
    $render['#attached']['library'][] = 'bps_content_display/viewincontext';
  }
}