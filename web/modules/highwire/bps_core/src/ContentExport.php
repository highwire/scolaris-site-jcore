<?php

namespace Drupal\bps_core;

use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use HighWire\PayloadFetcherInterface;
use Drupal\highwire_content\Lookup;
use Drupal\highwire_content\ContentSettings;
use Drupal\bps_core\Lookup as BPSLookup;

/**
 * Bps lookup class.
 */
class ContentExport {

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
   * Lookup for getting nids from apaths.
   *
   * @var \Drupal\highwire_content\Lookup
   */
  protected $lookup;

  /**
   * Content Settings Object.
   *
   * @var \Drupal\highwire_content\ContentSettings
   */
  protected $contentSettings;

  /**
   * BPS Lookup.
   *
   * @var \Drupal\bps_core\Lookup
   */
  protected $bpsLookup;

  /**
   * Payload fetcher.
   *
   * @var \HighWire\PayloadFetcherInterface
   */
  protected $payload_fetcher;

  /**
   * Construct bps content export.
   *
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   The entity query factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   Drupal entity type manager.
   * @param \Drupal\highwire_content\Lookup $lookup
   *   Lookup helper to find nids from apaths.
   * @param \Drupal\highwire_content\ContentSettings $content_settings
   *   ContentConfig to verify saved config.
   * @param \Drupal\bps_core\Lookup $bps_lookup
   *   Lookup helper to find bps specific data.
   * @param \HighWire\PayloadFetcherInterface $payload_fetcher
   *   Atomlite payload fetcher.
   */
  public function __construct(
    QueryFactory $query_factory,
    EntityTypeManagerInterface $entity_manager,
    Lookup $lookup,
    ContentSettings $content_settings,
    BPSLookup $bps_lookup,
    PayloadFetcherInterface $payload_fetcher
  ) {
    $this->entityManager = $entity_manager;
    $this->queryFactory = $query_factory;
    $this->lookup = $lookup;
    $this->contentSettings = $content_settings;
    $this->bpsLookup = $bps_lookup;
    $this->payloadFetcher = $payload_fetcher;
  }

  /**
   * Helper function to get Top Parent of any Taxonomy.
   *
   * @param array $tid
   *   => term_id.
   *
   * @return array
   */
  protected function getTopParent($tid) {
    $parent_id = $tid;
    $parent_term_name = [];
    $parent_term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($parent_id);
    $parents = $parent_term->get('parent')->getValue();
    foreach ($parents as $value) {
      $parent_tid = $value['target_id'];
      if ($parent_tid) {
        $parent_term_name = array_merge($parent_term_name, $this->getTopParent($parent_tid));
      }
      else {
        $parent_term_name[] = $parent_term->getName();
      }
    }

    return $parent_term_name;
  }

  /**
   * Get all export meta for given item_type.
   *
   * @param int $item_type
   *   A node type.
   *
   * @return array
   *   Array of meta for the export data.
   */
  public function exportContentByType($item_type) {
    // Dynamically get the extract policy...
    $policy = $this->bpsLookup->getItemExtractPolicy();

    // Fetch all apaths for item_type...
    foreach ($policy as $kpolicy => $corpus) {
      $atomlite_payload = $this->lookup->getAtomLitePayloadByItemType($item_type, $kpolicy);
    }

    if (empty($atomlite_payload)) {
      return;
    }

    // Get labels from the highwire content settings.
    $type_translation = $this->contentSettings->getContentTypeLabelsPlural();

    // Book fields to include.
    $include_fields = [
      'Content type' => $type_translation[$item_type],
      'ID' => 'isbn-ebook',
      'Title' => 'title-plain',
      'Parent Book' => 'video-book-title',
      'Parent ISBN' => 'bibliorelation-isbn',
      'Author' => 'authors-full-name',
      'Publisher' => 'McGraw-Hill Professional',
      'Copyright year' => 'copyright-year',
      'Date posted' => 'date-print-publication',
      'Subjects' => 'taxonomy-terms',
      'Archived?' => 'archived',
      'URL' => 'apath',
      'Resource URI' => 'apath',
    ];

    // Non-Book field mods.
    if ($item_type != 'item_book') {
      $include_fields['ID'] = 'publisher-id';
    }

    // Don't set Parent Book field/value for anything but videos.
    if ($item_type != 'item_video') {
      unset($include_fields['Parent Book']);
    }

    $csv = [];
    foreach ($atomlite_payload as $apath => $atomlite_payload_item) {
      // If the payload item is empty, skip.
      if (empty($atomlite_payload_item)) {
        continue;
      }

      $subjects = [];
      $book_export_data = [];
      foreach ($include_fields as $key => $include_field) {

        // Archived field is boolean, csv/xcel doesn't like that
        // for properly holding the cell value.
        $archived = 'No';
        if ($key == 'Archived?') {
          // All items except book should be No.
          if ($item_type == 'item_book' && !empty($atomlite_payload_item[$include_field])) {
            $archived = 'Yes';
          }
        }
        // If the payload doesn't have a field, create it and set
        // empty string so the csv properly holds the cell value.
        if (empty($atomlite_payload_item[$include_field])) {
          $atomlite_payload_item[$include_field] = '';
        }
        switch ($key) {
          case 'ID':
            // In order to prevent xcel from autoformatting the id as 9.78E+10,
            // we must pass it to the csv as ="9780071753791".
            $book_export_data[$key] = '="' . (string) $atomlite_payload_item[$include_field] . '"';
            break;

          case 'Archived?':
            $book_export_data[$key] = $archived;
            break;

          case 'Content type':
          case 'Publisher':
            $book_export_data[$key] = $include_field;
            break;

          case 'Copyright year':
            $copyright_year = '';
            // Books have copyright dates.
            if ($item_type == 'item_book') {
              $copyright_year = $atomlite_payload_item[$include_field][0];
            }
            $book_export_data[$key] = $copyright_year;
            break;

          case 'Author':
            $book_export_data[$key] = $atomlite_payload_item[$include_field][0];
            break;

          case 'Subjects':
            if (!empty($atomlite_payload_item[$include_field]) && is_array($atomlite_payload_item[$include_field])) {
              foreach ($atomlite_payload_item[$include_field] as $subject) {
                $subjects[] = $subject['label'];
              }
              $book_export_data[$key] = implode(' | ', $subjects);
            }
            else {
              $book_export_data[$key] = '';
            }
            break;

          case 'URL':
            $apath = $atomlite_payload_item[$include_field];
            $prefix = '/bpsworks';
            $suffix = '.atom';
            $plen = strlen($prefix);
            $slen = 0 - strlen($suffix);
            // Chop off prefix.
            $sub_string = substr($apath, $plen);
            // Chop off suffix.
            $clean_string = substr($sub_string, 0, $slen);
            // Form url.
            $host = 'https://www.bps.org.uk';
            $uri = '/content' . $clean_string;
            $url = $host . $uri;

            $book_export_data[$key] = $url;
            break;

          case 'Resource URI':
            $apath = $atomlite_payload_item['URL'];
            $book_export_data[$key] = $apath;
            break;

          case 'Parent Book':
            // Need to actually get the title of the book from the isbn.
            $books = !empty($atomlite_payload_item[$include_field]) ? $atomlite_payload_item[$include_field] : '';
            $book_export_data[$key] = !empty($books) && is_array($books) ? implode('|', $books) : $books;
            break;

          case 'Parent ISBN':
            // Set Parent ISBN in CSV.
            $books = !empty($atomlite_payload_item[$include_field]) ? $atomlite_payload_item[$include_field] : '';
            $book_export_data[$key] = !empty($books) && is_array($books) ? implode('|', $books) : $books;
            break;

          default:
            $book_export_data[$key] = $atomlite_payload_item[$include_field];

        }
      }

      $data[] = $book_export_data;

    }

    $file_prefix = 'Bps_title_export-';
    $filename = $file_prefix . $item_type . '.csv';
    $path = 'public://content-export/';
    $file = $path . $filename;

    // Check if directory exists, if not, create it.
    if (file_prepare_directory($path, FILE_CREATE_DIRECTORY)) {

      $output = fopen($file, "w");
      $header = array_keys($data[0]);

      fputcsv($output, $header);

      foreach ($data as $row) {
        fputcsv($output, $row);
      }

      // Write file.
      fclose($output);
    }
  }

  /**
   * Helper function to get a Book, Chapter, Section ID.
   *
   * @param array $apath,
   *   $prefix.
   *
   * @return string
   *   The ID is piece of apath.
   */
  protected function extractID($apath, $prefix) {
    $suffix = '.atom';
    $plen = strlen($prefix);
    $slen = 0 - strlen($suffix);
    // Chop off prefix.
    $sub_string = substr($apath, $plen);
    // Chop off suffix.
    $id = substr($sub_string, 0, $slen);
    return $id;
  }

  /**
   * Export all data into csv.
   */
  public function exportBooksFull() {
    // Setup file info.
    $file_prefix = 'Bps_title_export-full-data';
    $filename = $file_prefix  . '.csv';
    $path = 'public://content-export/';
    $file = $path . $filename;

    // Dynamically get the extract policy...
    $policy = $this->bpsLookup->getItemExtractPolicy();

    $item_type = ['journal', 'journal_article', 'journal_issue'];
    foreach ($policy as $kpolicy => $corpus) {
      if ($kpolicy == 'item-bits-bpsworks') {
        $book_item_type = ['item_monograph', 'item_report_guideline', 'item_test_review', 'item_chapter'];
        $atomlite_payload = $this->lookup->getAtomLitePayloadByItemTypes($book_item_type, $kpolicy);
      }
      else {
        $atomlite_payload = $this->lookup->getAtomLitePayloadByItemTypes($item_type, $kpolicy);
      }

      if (empty($atomlite_payload)) {
        return;
      }

      $total_items = count($atomlite_payload);
      drush_print("Found $total_items atoms for exporting... $kpolicy");

      // Book fields to include.
      $include_fields = [
        'Content type' => 'item-type',
        'ID' => 'journal-eissn',
        'Publisher ID' => 'journal-publisher-id',
        'Title' => 'title-plain',
        'Author' => 'authors-full-name',
        'Publisher' => 'McGraw-Hill Professional',
        'Copyright year' => 'copyright-year',
        'Date posted' => 'date-print-publication',
        'Subjects' => 'taxonomy-terms',
        'Archived?' => 'archived',
        'URL' => 'apath',
      ];

       // Check if directory exists, if not, create it.
      if (file_prepare_directory($path, FILE_CREATE_DIRECTORY)) {

        $output = fopen($file, "w");
        $header = array_keys($include_fields);

        fputcsv($output, $header);

      }
      else {
        drush_print('[ERROR] Could not prepare file ' . $path);
        return [];
      }
      
      // Content type
      $content_type = ['journal', 'journal-article', 'journal-issue'];
      // Loop through initial payload items for item-type.
      $full_book_apaths = [];
      $count = 0;
      $start_time = time();
      foreach ($atomlite_payload as $apath => $atomlite_payload_item) {
        $book_start_time = time();
        // If the payload item is empty, skip.
        if (empty($atomlite_payload_item)) {
          continue;
        }

        $toc_nid = $this->lookup->nidFromApath($apath);
        $toc_node = $this->entityManager->getStorage('node')->load($toc_nid);

        // Determine if book is chapter or section split.
        // $book_split_at = $toc_node->get('book_split_at')->getString();
        $children = [];

        foreach ($toc_node->get('children')->getValue() as $child) {
          // SF01154128 - do not include covers in pagination.
          $type = $this->lookup->getTypeFromNID($child['target_id']);
          if ($type !== 'item_cover') {
            $children[] = $child['apath'];
          }
        }

        // Done with node, free up some memory.
        $toc_node = NULL;

        $toc = $this->getTocFlat($apath, $children, $book_split_at, $toc_nid);

        // Build a truly flat list of only apaths.
        $full_book_apaths[] = $apath;
        foreach ($toc as $toc_item) {
          if (!empty($toc_item['apath'])) {
            $full_book_apaths[] = $toc_item['apath'];
          }
          if (!empty($toc_item['children'])) {
            foreach ($toc_item['children'] as $child) {
              $full_book_apaths[] = $child;
            }
          }
        }

        // Done with $toc, free up some memory.
        $toc = [];

        // Fetch all data for apaths from AtomLite via apaths and policy.
        $atomlite_book_payload = $this->payloadFetcher->getMultiple($full_book_apaths, $kpolicy);

        // Done with $full_book_apaths, free up some memory.
        $full_book_apaths = [];

        // Put the book apath at the top of the list... obnoxious...
        $last_item = end($atomlite_book_payload);
        if ($last_item['item-type'] == 'item-book') {
          array_pop($atomlite_book_payload);
          $reversed = array_reverse($atomlite_book_payload);
          $reversed[$last_item['apath']] = $last_item;
          $atomlite_book_payload = array_reverse($reversed);
        }

        // Go through all the book items and fetch data for csv.
        foreach ($atomlite_book_payload as $bapath => $book_payload_item) {
          // If the payload item is empty, skip.
          if (empty($book_payload_item)) {
            continue;
          }

          // Lets exclude some item-types we don't need in the report.
          if ($book_payload_item['item-type'] == 'item-figure' ||
              $book_payload_item['item-type'] == 'item-table' ||
              $book_payload_item['item-type'] == 'item-graph' ||
              $book_payload_item['item-type'] == 'item-example' ||
              $book_payload_item['item-type'] == 'item-video-reference') {
            continue;
          }

          // Handle ID field since it differs based on item type.
          $include_fields['ID'] = 'isbn-ebook';

          // For journal.
          if ($book_payload_item['atom-type'] == 'journal') {
            $include_fields['ID'] = $book_payload_item['journal-eissn'];
            $include_fields['Publisher ID'] = $book_payload_item['journal-publisher-id'];
          }

          // For article.
          if ($book_payload_item['atom-type'] == 'journal-article') {
            $include_fields['Publisher ID'] = $book_payload_item['publisher-unique-id'];
          }

          // Book field mods (Monograph / Test-Review / Report Guideline).
          $books_type = ['item-monograph', 'item-report-guideline', 'item-test-review', 'item-chapter'];
          if (in_array($book_payload_item['item-type'], $books_type)) {
            if ($book_payload_item['apath']) {
              $apath_array = explode('/', $book_payload_item['apath']);
              // Last ID from the URL.
              $apath_last_id = count($apath_array) - 1;
            }

            $include_fields['ID'] = $apath_array[$apath_last_id];
            $include_fields['Publisher ID'] = '';
          }

          $subjects = [];
          $book_export_data = [];

          // Loop through each field we want on the book item.
          foreach ($include_fields as $key => $include_field) {
            // If the payload doesn't have a field, create it and set
            // empty string so the csv properly holds the cell value.
            if (empty($book_payload_item[$include_field])) {
              $book_payload_item[$include_field] = '';
            }

            switch ($key) {
              case 'ID':
                // we must pass it to the csv as ="9780071753791".
                if ($book_payload_item['atom-type'] == 'journal') {
                  $book_export_data[$key] = $book_payload_item['journal-eissn'];
                }
                else {
                  $book_export_data[$key] = '="' . (string) $book_payload_item[$include_field] . '"';
                }
                
                // For non-books.
                if (in_array($book_payload_item['item-type'], $books_type)) {
                  $book_export_data[$key] = $include_field;
                }
                break;

              case 'Publisher ID':

                if ($book_payload_item['atom-type'] == 'journal') {
                  $book_export_data[$key] = $book_payload_item['journal-publisher-id'];
                }
                elseif ($book_payload_item['atom-type'] == 'journal-article' || $book_payload_item['atom-type'] == 'journal-issue') {
                  $book_export_data[$key] = "'" . $book_payload_item['publisher-unique-id'];
                }
                else {
                  $book_export_data[$key] = "";
                }
                break;

              case 'Archived?':
                // Archived field is boolean, csv/xcel doesn't like that
                // for properly holding the cell value.
                $archived = 'No';
                if ($item_type == 'item_book' && !empty($book_payload_item[$include_field])) {
                  $archived = 'Yes';
                }
                $book_export_data[$key] = $archived;
                break;

              case 'Content type':

                $book_export_data[$key] = '="' . (string) $book_payload_item[$include_field] . '"';                
                if ($book_payload_item['atom-type']) {
                  if (in_array($book_payload_item['atom-type'], $content_type)) {
                    $book_export_data[$key] = $book_payload_item['atom-type'];
                  }
                } 
                elseif ($book_payload_item['item-type']) {
                  if (in_array($book_payload_item['item-type'], $books_type)) {
                    $book_export_data[$key] = $book_payload_item['item-type'];
                  }
                } 
                
                break;

              case 'Publisher':
                $book_export_data[$key] = $book_payload_item[$include_field];
                break;

              case 'Copyright year':
                $copyright_year = '';
                // Books have copyright dates.
                if ($item_type == 'item_book') {
                  $copyright_year = $book_payload_item[$include_field][0];
                }
                $book_export_data[$key] = $copyright_year;
                break;

              case 'Author':
                $book_export_data[$key] = $book_payload_item[$include_field][0];
                break;

              case 'Subjects':
                if (!empty($book_payload_item[$include_field]) && is_array($book_payload_item[$include_field])) {
                  foreach ($book_payload_item[$include_field] as $subject) {
                    $subjects[] = $subject['label'];
                  }
                  $book_export_data[$key] = implode(' | ', $subjects);
                }
                else {
                  $book_export_data[$key] = '';
                }
                break;

              case 'URL':
                $uri = $this->getContentUrl($book_payload_item);

                if (!empty($uri)) {
                  $host = 'https://bps-prod.highwirestaging.com';
                  $url = $host . $uri;
                  $book_export_data[$key] = $url;
                }
                break;

              default:
                $book_export_data[$key] = $book_payload_item[$include_field];

            }
          }
          $data[] = $book_export_data;

          // Write our data to the csv.
          // fputcsv($output, $book_export_data);.
          // Done with book_export_data, free up some memory.
          $book_export_data = [];

        }

        // Done with atomlite_book_payload, free up some memory.
        $atomlite_book_payload = [];

        $count++;
        drush_print($count . '/' . $total_items . ' atoms processed in ' . (time() - $book_start_time) . ' seconds.');

      }
    }

    // Write our data to the csv.
    foreach ($data as $row) {
      fputcsv($output, $row);
    }

    // Write file.
    fclose($output);
    
    drush_print('Total time elapsed: ' . (time() - $start_time) . ' seconds.');
  }

  /**
   * Helper function to get a content url.
   *
   * @param array $book_payload_item
   *
   * @return string
   *   The URI foa the piece of content.
   */
  protected function getContentUrl(array $book_payload_item) {

    $type = $book_payload_item['item-type'];
    $url = '';

    // The ancestor field will be a book chunk or the root_item field.
    // Note we have to check if parent_book is empty because the book_split_at
    // field has a value even for items that are not descendants of books.
    $ancestor_field = '';
    $book_split_at = !empty($book_payload_item['book-split-at']) ? $book_payload_item['book-split-at'] : 'chapter';
    if (!empty($book_payload_item['parent-book'])) {
      $ancestor_field = 'parent-' . $book_split_at;
    }
    elseif (!empty($book_payload_item['root-item'])) {
      $ancestor_field = 'root-item';
    }

    switch ($type) {
      // Case in_array($type, mhe_core_get_book_fragment_types()):
      case 'item-search-section':
        // For book fragments (i.e. search section, figure, table, graph) return fragment link to parent type.
        $url = $this->getContentUrlJumpLink($book_payload_item, $ancestor_field);
        break;

      case 'item-section':
        // Whether a section has is a jump link or not depends on how the book is split.
        if ($type === 'item-' . $book_split_at) {
          $url = $this->apathToCpath($book_payload_item['apath'], $kpolicy);
        }
        else {
          $url = $this->getContentUrlJumpLink($book_payload_item, $ancestor_field);
        }
        break;

      default:
        // Return node url.
        $url = $this->apathToCpath($book_payload_item['apath'], $kpolicy);
        break;
    }

    return $url;
  }

  /**
   * Helper function to get a content url.
   *
   * @param array $book_payload_item
   * @param string $ancestor_field
   *
   * @return string
   *   The URI containing the jump link to sub section.
   */
  protected function getContentUrlJumpLink(array $book_payload_item, string $ancestor_field) {
    $url = '';

    // Get url for items that should be jump links.
    if (!empty($book_payload_item[$ancestor_field])) {
      // Check for parent nid.
      $ancestor_apath = $book_payload_item[$ancestor_field];
      $ancestor_cpath = $this->apathToCpath($ancestor_apath);

      // Get the publisher id (xml:id in source.xml), this will be the jump link fragment.
      $publisher_id = !empty($book_payload_item['publisher-id']) ? $book_payload_item['publisher-id'] : '';
      // Build url.
      $url = $ancestor_cpath . '#' . $publisher_id;
    }

    return $url;
  }

  /**
   * Helper function to get a cpath from apath.
   *
   * @param string $apath
   *
   * @return string
   *   Some durty manging of the apath to get cpath...
   */
  protected function apathToCpath(string $apath, $policy = NULL) {
    if (empty($apath)) {
      return '';
    }

    if ($policy == 'scolaris-journal') {
      $prefix = '';
      $suffix = '.atom';
      $cpath = str_replace($suffix, '', $apath);
    } 
    else {      
      $prefix = '/bpsworks';
      $suffix = '.atom';
      $cpath = str_replace($suffix, '', $apath);
      $cpath = str_replace($prefix, '', $cpath);
    }
    
    return '/content' . $cpath;

  }

  /**
   * Helper function for getting a books children.
   *
   * @param string $book_apath
   * @param array $book_children
   * @param bool $depth
   *
   * @return array
   */
  protected function getTocFlat(string $book_apath, array $book_children, $depth = FALSE, $toc_nid = NULL) {
    try {
      $results = $this->bpsLookup->getBookTocItemsFromElastic($book_apath, $toc_nid);
    }
    catch (\Exception $e) {
      return [];
    }

    $filtered_children = [];
    foreach ($book_children as $child_apath) {
      if (isset($results['results'][$child_apath])) {
        $filtered_children[$child_apath] = $results['results'][$child_apath];
      }
    }

    $toc = $this->getTocFlatRecusive($results['results'], $filtered_children);
    if ($depth !== FALSE) {
      foreach ($toc as $key => $item) {
        // Front and back matter items are not nested into chapters for section.
        // Split books therefore their level is 0 but we still need to include them.
        if ($depth == 1 && ($item['item-type'] == 'item-front-matter' || $item['item-type'] == 'item-back-matter')) {
          continue;
        }
        if ($item['level'] < $depth || empty($item['item-has-body'])) {
          unset($toc[$key]);
        }
      }
      $toc = array_values($toc);
    }

    return $toc;
  }

  /**
   * Get toc flat and in order.
   *
   * @param array $all_results
   *   An array of all toc items, not in order.
   * @param array $children
   *   An array of children.
   * @param int $level
   *   Max level for toc items.
   *
   * @return array
   *   A flattened toc array in the proper order.
   */
  protected function getTocFlatRecusive(array &$all_results, array $children, $level = 0) {
    $items = [];
    foreach ($children as $apath => $child) {
      $child['level'] = $level;
      $child['apath'] = $apath;
      $items[] = $child;
      if (!empty($child['children'])) {
        $filtered_children = [];
        foreach ($child['children'] as $child_apath) {
          if (!isset($all_results[$child_apath])) {
            continue;
          }
          $filtered_children[$child_apath] = $all_results[$child_apath];
        }

        if (!empty($filtered_children)) {
          $new_level = $level + 1;
          if ($child_items = $this->getTocFlatRecusive($all_results, $filtered_children, $new_level)) {
            foreach ($child_items as $child_apath => $item) {
              $items[] = $item;
            }
          }
        }
      }
    }

    return $items;
  }

}
