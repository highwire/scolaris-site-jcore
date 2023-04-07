<?php

namespace Drupal\bps_core;

use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use HighWire\Clients\Atomx\Atomx;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\highwire_content\Lookup as HWLookup;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\highwire_content\ContentSettings;
use HighWire\Utility\Apath;

/**
 * Bps lookup class.
 */
class Lookup {

  /**
   * Drupal Query factory.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $queryFactory;

  /**
   * Drupal entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * Atomlite http client.
   *
   * @var \HighWire\Clients\Atomx\Atomx
   */
  protected $atomxClient;

  /**
   * Drupal default cache bin.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $defaultCache;

  /**
   * Highwire content lookup.
   *
   * @var \Drupal\highwire_content\Lookup
   */
  protected $lookup;

  /**
   * Drupal logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Content Settings Object.
   *
   * @var \Drupal\highwire_content\ContentSettings
   */
  protected $contentSettings;

  /**
   * Construct bps lookup.
   *
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   The entity query factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   Drupal entity type manager.
   * @param \HighWire\Clients\Atomx\Atomx $atomx_client
   *   Atomx http client.
   * @param \Drupal\Core\Cache\CacheBackendInterface $default_cache
   *   Drupal cache default bin.
   * @param \Drupal\highwire_content\Lookup $hw_lookup
   *   Highwire content lookup.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   The drupal logger factory.
   * @param \Drupal\highwire_content\ContentSettings $content_settings
   *   ContentConfig to verify saved config.
   */
  public function __construct(
    QueryFactory $query_factory,
    EntityTypeManagerInterface $entity_manager,
    Atomx $atomx_client,
    CacheBackendInterface $default_cache,
    HWLookup $hw_lookup,
    LoggerChannelFactory $logger,
    ContentSettings $content_settings
    ) {
    $this->entityManager = $entity_manager;
    $this->queryFactory = $query_factory;
    $this->atomxClient = $atomx_client;
    $this->defaultCache = $default_cache;
    $this->lookup = $hw_lookup;
    $this->logger = $logger->get('bps_lookup');
    $this->contentSettings = $content_settings;
  }

  /**
   * Get all toc items in no specific order.
   *
   * @param string $book_apath
   *   A book apath.
   * @param int $toc_nid
   *   A book nid.
   *
   * @return array
   *   An array keyed by results (apaths) with meta data needed for building the toc as
   *   well as cache tags.
   */
  public function getBookTocItemsFromElastic($book_apath, $toc_nid = NULL) {
    $cid = "bps_core_toc_results:" . md5($book_apath);
    $cache = $this->defaultCache->get($cid);
    $results = [];
    if (!empty($cache->data)) {
      $results = $cache->data;
    }
    else {
      $search_query = '{
        "size": 5000,
        "_source": ["title", "item-type", "children", "label", "parent-chapter", "parent-section", "item-has-body", "publisher-id"],
        "query": {
        "bool" : {
            "filter": [
              {"term": {"parent-book": "' . $book_apath . '"}},
              {"terms": {"item-type": ["item-section", "item-chapter", "item-search-section", "item-back-matter", "item-front-matter", "item-toc-chapter"]}}
            ]
          }
        }
      }';

      // Define AtomX index based on $book_apath.
      $index = $this->getPolicyAndCorpusFromApath($book_apath);

      $this->atomxClient->setIndexes([$index]);
      $results['results'] = $this->atomxClient->search($search_query);

      // Static cache primer.
      $apaths = array_keys($results['results']);
      try {
        $nids = $this->lookup->nidsFromApaths($apaths);
      }
      catch (\Exception $e) {
        $this->logger->error(t('Nids not found for TOC apath @apath', ['@apath' => $book_apath]));
        return [];
      }

      // Setup cache tags.
      $results['cache_tags'] = [];
      if (!empty($toc_nid)) {
        $results['cache_tags'] = ['node:' . $toc_nid];
      }

      $this->defaultCache->set($cid, $results, Cache::PERMANENT, $results['cache_tags']);
    }

    return $results;
  }

  /**
   * Get all editions for a given nid.
   *
   * @param int $nid
   *   A node id.
   *
   * @return array
   *   An array of nids. The first element in the array is the current edition.
   */
  public function getEditions($nid) {
    $edition_nids = [];
    $node = $this->entityManager->getStorage('node')->load($nid);

    if (empty($node) || !$node->hasField('productnumber')) {
      return $edition_nids;
    }

    $productnumber = $node->get('productnumber')->value;

    if (empty($productnumber)) {
      return $edition_nids;
    }

    $edition_nids = $this->queryFactory->get('node')
      ->condition('productnumber', $productnumber)
      ->condition('type', HW_NODE_TYPE_BOOK)
      ->sort('pubdate', 'DESC')
      ->execute();

    return $edition_nids;
  }

  /**
   * Get all editions for a given nid.
   *
   * @param int $nid
   *   A node id.
   *
   * @return bool
   *   True if it is the current edition, otherwise false.
   */
  public function isEditionCurrent($nid) {
    $edition_nids = $this->getEditions($nid);

    if (empty($edition_nids)) {
      return FALSE;
    }

    if ($edition_nids[0] == $nid) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Helper function to get video count for book items.
   *
   * @param string $isbn
   *   The ISBN of a book.
   * @param string $nid
   *   The nid of a book. Used for cache tag.
   *
   * @return string
   *   Video count for a particular book.
   */
  public function getBookVideoCount($isbn, $nid) {
    $cid = 'book_video_count:' . $isbn;
    $results = 0;

    // Check if cache exists.
    if ($cache = $this->defaultCache->get($cid)) {
      $results = $cache->data;
    }
    else {
      $results = $this->queryFactory->get('node')
        ->condition('type', 'item_video', '=')
        ->condition('bibliorelation_isbn', $isbn)
        ->execute();

      // Set the cache.
      $cache_tags = ['node:' . $nid];
      $this->defaultCache->set($cid, $results, Cache::PERMANENT, $cache_tags);

    }

    if (empty($results)) {
      return 0;
    }

    return count($results);

  }

  /**
   * Helper function to get the active policy for bpsworks.
   *
   * @return string
   *   Either item-bps.
   */
  public function getItemExtractPolicy() {
    // We will always want to check against our primary.
    $hw_content_config = $this->contentSettings->getContentConfig();
    $content_config = $hw_content_config['content'];

    return $content_config;
  }

  /**
   * Helper function to get the allowed title export types.
   *
   * @return array
   *   Array of allowed items types for titles export.
   */
  public function getTitleExportTypes() {
    $types = [
      'item_book',
      'item_video',
      'item_calculator',
      'item_tutorial',
      'item_case_study',
      'item_datavis_project',
    ];
    return $types;
  }

  /**
   * Helper function to get the AtomX index based off apath.
   *
   * @param string $apath
   *   The apath to check for policy and corpus against.
   *
   * @return string
   *   String defining the AtomX index relevant to this apath.
   */
  public function getPolicyAndCorpusFromApath(string $apath) {
    $policy = $this->lookup->policyFromApath($apath);
    $corpus = Apath::getCorpus($apath);

    return $policy . ':' . $corpus;
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
   * Helper function get user access on video download.
   *
   * @param int $video_id
   *   Video id.
   *
   * @return bool
   *   True/False is returning.
   */
  public function getVideoAccess($video_id) {
    // Fetch nid by using video id.
    $cTypes = ['item_video', 'item_video_biography'];
    $result = $this->queryFactory->get('node')
      ->condition('type', $cTypes, 'in')
      ->condition('video_id', $video_id, '=')
      ->execute();

    $nid = array_values($result);
    // Process node id to retrive the userlevel data.
    $node = $this->entityManager->getStorage('node')->load($nid[0]);
    if (is_object($node) && $node->hasField('userlevel')) {
      $video_access = $node->get('userlevel')->getString();
    }
    return ($video_access == 'access-restricted') ? FALSE : TRUE;
  }

}
