<?php

namespace Drupal\bps_content_display\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block that displays a pdf only message.
 *
 * @Block(
 *  id = "bps_pdf_only_message",
 *  admin_label = @Translation("PDF Only Message"),
 *  context = {
 *    "node" = @ContextDefinition(
 *      "entity:node",
 *      required = TRUE,
 *      label = @Translation("Current Node")
 *    )
 *  }
 * )
 */
class PdfOnlyMessage extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Constructs a new PdfOnlyMessage object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Token $token
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    try {
      $node = $this->getContextValue('node');
    }
    catch (\Exception $e) {
      return $build;
    }

    if ($node->hasField('has_full_text')
      && empty($node->get('has_full_text')->value)
      && $node->hasField('has_full_text_pdf')
      && !empty($node->get('has_full_text_pdf')->value)
      ) {

      $build = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'pdf-only-message-container',
            'alert',
            'alert-info'
          ]
        ],
      ];

      $build['icon'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => '',
        '#attributes' => ['class' => ['feather-info']]
      ];

      $build['message'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->token->replace(
          'The content of this [node:type_display] is only available as a PDF.',
          ['node' => $node]
        ),
        '#prefix' => ' '
      ];
    }

    return $build;
  }
}
