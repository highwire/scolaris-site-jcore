<?php

namespace Drupal\bps_core\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Drupal\bps_core\Lookup;

/**
 * Handle /lookup/ URLs to redirect to content based on lookups.
 */
class Download extends ControllerBase {

  /**
   * BPS Lookup.
   *
   * @var \Drupal\bps_core\Lookup
   */
  protected $lookup;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\bps_core\Lookup $lookup
   *   Lookup helper to find bps specific data.
   */
  public function __construct(
    Lookup $lookup
  ) {
    $this->lookup = $lookup;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('bps_core.lookup')
    );
  }

  /**
   * Download item type export file.
   *
   * @param string $item_type
   *   The item type.
   */
  public function downloadItemTypeExport($item_type) {

    $allowed_types = $this->lookup->getTitleExportTypes();

    if (!in_array($item_type, $allowed_types)) {
      throw new NotFoundHttpException();
    }

    $items = [];
    foreach ($allowed_types as $allowed_type) {
      $items[$allowed_type] = 'accessEngineering_title_export-' . $allowed_type . '.csv';
    }

    if (empty($items[$item_type])) {
      throw new NotFoundHttpException();
    }

    $uri_prefix = 'public://content-export/';
    $filename = $items[$item_type];
    $uri = $uri_prefix . $filename;

    $headers = [
      'Content-Type' => 'text/csv',
      'Content-Description' => 'File Download',
      'Content-Disposition' => 'attachment; filename=' . $filename
    ];

    // Return and trigger file donwload.
    return new BinaryFileResponse($uri, 200, $headers, true );

  }
}
