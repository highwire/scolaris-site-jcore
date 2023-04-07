<?php

namespace Drupal\bps_core\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\highwire_content\Lookup as HWLookup;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Ajax\AjaxResponse;

/**
 * Handle /lookup/ URLs to redirect to content based on lookups.
 */
class BpsLookup extends ControllerBase {

  /**
   * Drupal Query factory.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $queryFactory;

  /**
   * Lookup for getting nids from apaths.
   *
   * @var \Drupal\highwire_content\Lookup
   */
  protected $hw_lookup;

  /**
   * Drupal entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\highwire_content\Lookup $hw_lookup
   *   Lookup helper to find nids from apaths.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   Drupal entity type manager.
   */
  public function __construct(
    QueryFactory $query_factory,
    HWLookup $lookup,
    EntityTypeManagerInterface $entity_manager
  ) {
    $this->queryFactory = $query_factory;
    $this->hw_lookup = $lookup;
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.query'),
      $container->get('highwire_content.lookup'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Redirect based on atom id.
   *
   * @param string $atomid
   *   Atom id.
   */
  public function lookupAtomID($atomid) {

    // Check if this atomid contains a fragment.
    $options = [];
    $url_parts = parse_url($atomid);

    if (!empty($url_parts['fragment'])) {
      $options = [
        'fragment' => $url_parts['fragment']
      ];
    }

    if ($lookup_nid = $this->getNIDfromAtomId($url_parts['path'])) {
      if ($node = $this->entityManager->getStorage('node')->load($lookup_nid)) {
        if ($url = scolaris_mhe_get_node_content_url($node)) {
          return new RedirectResponse($url->toString());
        }
      }
    }

    throw new NotFoundHttpException();
  }

  /**
   * Redirect to item markup.
   *
   * @param string $pubid
   *   Pub id.
   * @param string $ancestor_nid
   *   Ancestor node id.
   */
  public function tabLinkLookup($pubid, $ancestor_nid) {
    try {
      $ancestor_apath = $this->hw_lookup->apathFromNID($ancestor_nid);
    }
    catch (\Exception $e) {
      // Exception is thrown in apathFromNID.
      throw new NotFoundHttpException();
    }

    if ($nid = $this->nidFromPublisherID($pubid, $ancestor_apath)) {
      return $this->redirect('markup_request', ['markup_display_id' => 'item_fulltext', 'nid' => $nid]);
    }

    throw new NotFoundHttpException();
  }

  /**
   * Redirect to item in context.
   *
   * @param string $pubid
   *   Pub id.
   * @param string $ancestor_nid
   *   Ancestor node id.
   */
  public function viewInContextLinkLookup($pubid, $ancestor_nid) {
    try {
      $ancestor_apath = $this->hw_lookup->apathFromNID($ancestor_nid);
    }
    catch (\Exception $e) {
      // Exception is thrown in apathFromNID.
      throw new NotFoundHttpException();
    }

    if ($nid = $this->nidFromPublisherID($pubid, $ancestor_apath)) {
      $node = $this->entityManager->getStorage('node')->load($nid);
      $url = scolaris_mhe_get_node_content_url($node);
      if (!empty($url)) {
        return $this->redirect($url->getRouteName(), $url->getRouteParameters(), $url->getOptions());
      }
    }

    throw new NotFoundHttpException();
  }

  /**
   * Redirect to item download.
   *
   * @param string $pubid
   *   Pub id.
   * @param string $ancestor_nid
   *   Ancestor node id.
   */
  public function downloadLinkLookup($pubid, $ancestor_nid) {
    try {
      $ancestor_apath = $this->hw_lookup->apathFromNID($ancestor_nid);
    }
    catch (\Exception $e) {
      // Exception is thrown in apathFromNID.
      throw new NotFoundHttpException();
    }

    if ($nid = $this->nidFromPublisherID($pubid, $ancestor_apath)) {
      return $this->redirect('markup_download_request', ['markup_display_id' => 'item_fulltext', 'nid' => $nid]);
    }

    throw new NotFoundHttpException();
  }

  /**
   * Redirect to first section of toc_chapter.
   *
   * @param string $nid
   *   Toc Chapter node id.
   */
  public function tocChapterFirstSectionLookup($nid) {

    $node = $this->entityManager->getStorage('node')->load($nid);
    if (!$node->hasField('children') || $node->get('children')->isEmpty()) {
      throw new NotFoundHttpException();
    }

    $first_child = $node->get('children')->first()->getValue() ?? FALSE;
    $first_child_nid = FALSE;
    if (!empty($first_child['target_id'])) {
      $first_child_nid = $first_child['target_id'];
    }
    else {
      throw new NotFoundHttpException();
    }

    if (!empty($first_child_nid)) {
      return $this->redirect('entity.node.canonical', ['node' => $first_child_nid]);
    }

    throw new NotFoundHttpException();
  }

  /**
   * Redirect the legacy book url to correct node.
   *
   * @param string $title
   *   The title part of the url.
   * @return redirect
   *   A redirect or not found.
   */
  public function legacyUrlBookRedirect($title) {
    if (!$title) {
      throw new NotFoundHttpException();
    }

    // Get the nid for this book title.
    $nid = $this->legacyBookTitleMatch($title);

    if (!$nid) {
      throw new NotFoundHttpException();
    }

    // Redirect user to nid.
    return $this->redirect('entity.node.canonical', ['node' => $nid]);
  }

 /**
   * Redirect the legacy chapter/section url to correct node.
   *
   * @param string $title
   *   The title part of the url.
   * @return redirect
   *   A redirect or not found.
   */
  public function legacyUrlChunkRedirect($title, $publisher_id) {
    if (!$title || !$publisher_id) {
      throw new NotFoundHttpException();
    }

    // Get the nid for this book title.
    $book_nid = $this->legacyBookTitleMatch($title);

    if (!$book_nid) {
      throw new NotFoundHttpException();
    }

    // Get the apath.
    try {
      $parent_book_apath = $this->hw_lookup->apathFromNID($book_nid);
    }
    catch (\Exception $e) {
      // Exception is thrown in apathFromNID.
      throw new NotFoundHttpException();
    }

    // Now that we have the parent_book_apath we can lookup the chunk item.
    try {
      $chunk_nid = $this->nidFromPublisherID($publisher_id, $parent_book_apath);
    }
    catch (\Exception $e) {
      // Exception is thrown in apathFromNID.
      throw new NotFoundHttpException();
    }

    // Check that we actually have a nid.
    if (!$chunk_nid) {
      // Throw not found if chunk_nid is empty.
      throw new NotFoundHttpException();
    }
    // Redirect user to the chapter/section node.
    return $this->redirect('entity.node.canonical', ['node' => $chunk_nid]);
  }

  /**
   * Lookup the sipp2 slug book-title.
   *
   * @param string $title
   *   The title part of the url.
   * @return string $results
   *   Results are a node id.
   */
  public function legacyBookTitleMatch($title) {

    if (!$title) {
      throw new NotFoundHttpException();
    }

    // Lookup the matching title via queryFactory.
    $results = $this->queryFactory->get('node')
      ->condition('title_slug', $title)
      ->condition('type', $this->hw_lookup->getHighWireContentTypes(), 'IN')
      ->execute();

    if (!empty($results)) {
      return array_pop($results);
    }

    // Results are empty, throw 404.
    throw new NotFoundHttpException();
  }

  /**
   * Find the parent_book apath given a nid.
   *
   * @param string $nid
   *   The node id of the item.
   * @return int|null
   *   A nid, or null if it's not found.
   */
  private function parentBookFromNID($nid) {
    $node = $this->entityManager->getStorage('node')->load($nid);
    if ($node->hasField('parent_book')) {
      $parent_book_apath = $node->get('parent_book')->getString();
    }
    return $parent_book_apath;
  }

  /**
   * Find the nid of an item given publisher-id and ancestor apath.
   *
   * @param string $publisher_id
   *   The publisher-id of the item.
   * @param string $ancestor_apath
   *   The apath of the item's ancestor.
   *
   * @return int|null
   *   A nid, or null if it's not found.
   */
  private function nidFromPublisherID($publisher_id, $ancestor_apath) {
    $query = $this->queryFactory->get('node');

    // @TODO: remove orConditionGroup when all content
    // gets reindexed with 'root_item' field.
    $group = $query
      ->orConditionGroup()
      ->condition('parent_book', $ancestor_apath)
      ->condition('root_item', $ancestor_apath);

    $results = $query
      ->condition('publisher_id', $publisher_id)
      ->condition('type', $this->hw_lookup->getHighWireContentTypes(), 'IN')
      ->condition($group)
      ->execute();

    if (!empty($results)) {
      return array_pop($results);
    }

    return NULL;
  }
  
  /**
   * Find the Node id of the chapter from atom-id.
   *
   * @param string $aid
   *   The atom-id of the item.
   *
   * @return int|null
   *   A nid or null if it's not found.
   */
  private function getNIDfromAtomId($aid) {

    $results = $this->queryFactory->get('node')
      ->condition('atom_id', $aid)
      ->condition('type', $this->hw_lookup->getHighWireContentTypes(), 'IN')
      ->execute();

    if (!empty($results)) {
      return array_pop($results);
    }
    return NULL;
  }

  /**
   * Video Playback Ajax Callback
   *
   * @param string $video_apath
   *   The apath of video.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax response.
   */
  public function videoPlaybackCallback($video_apath) {

    $config = \Drupal::config('highwire_content.settings');
    $apath = '/bpsworks/video/' . $video_apath;

    // Calling the renderMarkupDisplay, the log will be written.
    $markup_display = $this->entityTypeManager()->getStorage('markup_display')->load('video_fulltext');
    $hw_lookup = \Drupal::service('highwire_content.lookup');
    $nid = $hw_lookup->nidFromApath($apath);
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    $markup = $markup_display->renderMarkupDisplay($node);

    $response = new AjaxResponse();
    return $response;
  }
}



