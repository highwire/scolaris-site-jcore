<?php

/**
 * @file
 * Contains drush command for bps_core module.
 */

/**
 * Implements hook_drush_command().
 */
function bps_core_drush_command() {
  $items = [];
  $items['bps-titles-export-type'] = [
    'description' => 'Extract all defined node fields for a given node type. Allowed types: item_book, item_video, item_calculator, item_tutorial, item_datavis, item_datavis_project',
    'arguments' => [
      'item-type' => 'The item type to create csv file for',
    ],
    'examples' => [
      'drush bps-tet item_video' => 'Export all item_video nodes to the item_video.csv file',
    ],
    'drupal dependencies' => ['highwire_content', 'bps_core'],
    'aliases' => ['bps-tet'],
  ];
  $items['bps-titles-export-enable'] = [
    'description' => 'Enable title extract cron updates for csg files',
    'examples' => [
      'drush bps-tee' => 'Enable cron for title export csv files',
    ],
    'drupal dependencies' => ['highwire_content', 'bps_core'],
    'aliases' => ['bps-tee'],
  ];
  $items['bps-titles-export-disable'] = [
    'description' => 'Disable title extract cron updates for csg files',
    'examples' => [
      'drush bps-ted' => 'Disable cron for title export csv files',
    ],
    'drupal dependencies' => ['highwire_content', 'bps_core'],
    'aliases' => ['bps-ted'],
  ];
  $items['bps-titles-export-set-timestamp'] = [
    'description' => 'Set the last updated cron timestamp for titles export',
    'arguments' => [
      'timestamp' => 'The timestamp to set the titles_export config for. Set to -1 to disable cron.',
    ],
    'examples' => [
      'drush bps-te-timestamp 1539371855' => 'Set cron last updated timestamp to 1539371855',
    ],
    'drupal dependencies' => ['highwire_content', 'bps_core'],
    'aliases' => ['bps-test'],
  ];
  $items['bps-content-audit'] = [
    'description' => 'Audit content for a given corpus.',
    'arguments' => [
      'corpus' => 'The item type to collect all apaths for.',
      'policy-id' => 'The policy id for looking up the corpus apaths.',
    ],
    'examples' => [
      'drush bps-ca bpsaeworks scolaris-journal' => 'Validate the content loaded into the site vs AtomLite',
    ],
    'drupal dependencies' => ['highwire_content'],
    'aliases' => ['bps-ca'],
  ];
  $items['bps-solr-audit'] = [
    'description' => 'Check that the items indexed in Solr matches what should be indexed in Solr',
    'examples' => [
      'drush bps-sa' => 'Validate items in Solr for item types configured in Solr',
    ],
    'drupal dependencies' => ['highwire_content', 'bps_core'],
    'aliases' => ['bps-sa'],
  ];

  $items['bps-titles-export-full-book'] = [
    'description' => 'Extract all books and their children.',
    'examples' => [
      'drush bps-tefb' => 'Export all books and their children books_full.csv file',
    ],
    'drupal dependencies' => ['highwire_content', 'bps_core'],
    'aliases' => ['bps-tefb'],
  ];
  $items['bps-titles-export-full-set-timestamp'] = [
    'description' => 'Set the last updated cron timestamp for titles FULL export',
    'arguments' => [
      'timestamp' => 'The timestamp to set the titles_export config for. Set to -1 to disable cron.',
    ],
    'examples' => [
      'drush bps-tef-timestamp 1539371855' => 'Set cron last updated timestamp to 1539371855',
    ],
    'drupal dependencies' => ['highwire_content', 'bps_core'],
    'aliases' => ['bps-tefst'],
  ];

  return $items;
}

/**
 * Drush command callback for bps-titles-export-type.
 *
 * @param string item_type
 *   The item type.
 */
function drush_bps_core_bps_titles_export_type($item_type) {
  if (empty($item_type)) {
    return;
  }
  // Get allowed title export types.
  $bps_lookup = \Drupal::service('bps_core.lookup');
  $allowed_item_types = $bps_lookup->getTitleExportTypes();

  if (!in_array($item_type, $allowed_item_types)) {
    return;
  }
  try {
    // Pass each type to content_export service, which in turn
    // creates the file at public://content-export/[file].csv.
    $content_exporter = \Drupal::service('bps_core.content_export');
    $export = $content_exporter->exportContentByType($item_type);
    drush_print('Titles list csv file has been rebuild for type: ' . $item_type);
  }
  catch (Exception $e) {
    drush_print("An error occured: " . $e->getMessage());
  }
}

/**
 * Drush command callback for bps-titles-export-enable.
 */
function drush_bps_core_bps_titles_export_enable() {
  try {
    // Update timestamp to the current time.
    \Drupal::configFactory()->getEditable('bps_core.cron.titles_export')
      ->set('timestamp', time())
      ->save();
    drush_print('Title export cron updates are now enabled and the timestamp is set to ' . time());
  }
  catch (Exception $e) {
    drush_print("An error occured: " . $e->getMessage());
  }
}

/**
 * Drush command callback for bps-titles-export-disable.
 */
function drush_bps_core_bps_titles_export_disable() {
  try {
    // Update timestamp to -1.
    \Drupal::configFactory()->getEditable('bps_core.cron.titles_export')
      ->set('timestamp', -1)
      ->save();
    drush_print('Title export cron updates are now disabled. The timestamp is set to -1.');
  }
  catch (Exception $e) {
    drush_print("An error occured: " . $e->getMessage());
  }
}

/**
 * Drush command callback for bps-titles-export-set-timestamp.
 *
 * @param int timestamp
 *   The Unix timestamp to set as last updated time.
 */
function drush_bps_core_bps_titles_export_set_timestamp(int $timestamp) {
  if (empty($timestamp)) {
    return;
  }
  try {
    // Update timestamp to the provided time.
    \Drupal::configFactory()->getEditable('bps_core.cron.titles_export')
      ->set('timestamp', (int) $timestamp)
      ->save();
    drush_print('Title export cron timestamp is set to ' . (int) $timestamp . '. Cron updates are enabled.');
  }
  catch (Exception $e) {
    drush_print("An error occured: " . $e->getMessage());
  }
}

/**
 * Validate the content loaded into the site vs AtomLite.
 *
 * @param string $corpus
 *   The corpus to check against.
 * @param string $policy
 *   A comma separated list of node types.
 */
function drush_bps_core_bps_content_audit(string $corpus = '', string $policy = '') {

  if (empty($corpus) || empty($policy)) {
    drush_print('Please provide a corpus and policy id.');
    drush_print('ex. drush bps-ca bpsaeworks scolaris-journal');
    return;
  }

  $payload_fetcher = Drupal::service('hwphp.payload_fetcher');
  drush_print('Now fetching apaths for corpus: ' . $corpus . ' and policy: ' . $policy);

  // Get all apaths from AtomLite.
  $atomlite_apaths = $payload_fetcher->getCorpusIds($corpus, $policy);

  // Get count of all apaths.
  $atomlite_total = count($atomlite_apaths);
  ksort($atomlite_apaths);

  drush_print('AtomLite total: ' . $atomlite_total);

  // Get count of apaths for corpus in Drupal database.
  $database = \Drupal::database();
  $query = $database->select('node__apath', 'n');
  $query->join('node__corpus', 'nc', 'n.entity_id = nc.entity_id');
  $query->condition('nc.corpus_value', $corpus);
  $query->addField('n', 'apath_value');
  $result = $query->execute();

  $db_apaths_result = $result->fetchAll();

  $db_apaths_raw = [];
  foreach ($db_apaths_result as $db_apaths_result) {
    $apath = $db_apaths_result->apath_value;
    $db_apaths_raw[$apath] = $apath;
  }
  $db_count_raw = count($db_apaths_raw);
  drush_print('Database raw total: ' . $db_count_raw);

  $db_apaths_clean = array_unique($db_apaths_raw);
  $db_count_clean = count($db_apaths_clean);
  drush_print('Database no duplicates total: ' . $db_count_clean);

  // If ($db_count_clean > $atomlite_total) {
  //   drush_print('Database total is higher than atomlite, checking for duplicates...');
  //   drush_print('Database non duplicate total: ' . $db_count_clean);
  // }.
  ksort($db_apaths_clean);

  // Check if counts are equal, if so, great success!!
  if ($db_count_clean == $atomlite_total) {
    drush_print('Woo, all apaths in atomLite are in Drupal.');
    return;
  }

  // Counts are still not equal, get diff.
  $items_in_atomlite_not_in_db = array_diff($atomlite_apaths, $db_apaths_clean);
  if (!empty($items_in_atomlite_not_in_db)) {
    drush_print('Items in AtomLite that are not in the database:');
    $items_in_atomlite_not_in_db_keys = array_keys($items_in_atomlite_not_in_db);
    print_r(implode("\n", $items_in_atomlite_not_in_db_keys));
    echo "\n";
  }

  $items_in_db_not_in_atomlite = array_diff($db_apaths_clean, $atomlite_apaths);
  if (!empty($items_in_db_not_in_atomlite)) {
    drush_print('Items in database that are not in AtomLite:');
    $items_in_db_not_in_atomlite_keys = array_keys($items_in_db_not_in_atomlite);
    print_r(implode("\n", $items_in_db_not_in_atomlite_keys));
    echo "\n";
  }
}

/**
 * Drush command callback for bps-solr-audit.
 */
function drush_bps_core_bps_solr_audit() {

  /**
   * Database Stuff
   */
  // Get solr index datasource config.
  $solr_index_config = \Drupal::config('search_api.index.bps_index');
  $solr_datasource_config = $solr_index_config->get('datasource_settings');
  $solr_datasources = $solr_datasource_config['entity:node']['bundles']['selected'];

  // Get all apaths from our database of solr_datasources.
  $database = \Drupal::database();
  $query = $database->select('node__apath', 'n');
  $query->join('node_field_data', 'nd', 'n.entity_id = nd.nid');
  $query->condition('n.bundle', $solr_datasources, 'IN');
  $query->addField('n', 'apath_value');
  $result = $query->execute();

  $result_apaths = $result->fetchAll();

  drush_print('Total database items for Solr index: ' . count($result_apaths));

  // Turn the result array into something we can use array_diff against.
  $db_apaths = [];
  foreach ($result_apaths as $result_apath) {
    $db_apaths[] = $result_apath->apath_value;
  }

  // Free up the memories...
  $result_apaths = NULL;

  /**
   * Solr Stuff
   */
  // Get solr server config.
  $solr_server_config = \Drupal::config('search_api.server.bps_solr');
  $solr_server_backend_config = $solr_server_config->get('backend_config');
  $solr_server_scheme = $solr_server_backend_config['connector_config']['scheme'];
  $solr_server_base_url = $solr_server_backend_config['connector_config']['host'];
  $solr_server_port = $solr_server_backend_config['connector_config']['port'];
  $solr_server_path = $solr_server_backend_config['connector_config']['path'];
  $solr_server_core = $solr_server_backend_config['connector_config']['core'];

  $solr_full_base = $solr_server_scheme . '://' . $solr_server_base_url . ':' . $solr_server_port . $solr_server_path;
  $solr_limit = '600000';
  $solr_params = 'fl=ss_apath&indent=on&q=*:*&rows=' . $solr_limit . '&wt=json';
  $solr_full_url = $solr_full_base . '/' . $solr_server_core . '/select?' . $solr_params;

  // Get the Solr index data for blue.
  $solr_data = json_decode(file_get_contents($solr_full_url), TRUE);

  // Build nicer array of apaths.
  $solr_apaths = [];
  foreach ($solr_data["response"]["docs"] as $result) {
    $apath = $result["ss_apath"];
    $solr_apaths[] = $apath;
  }

  // Free up the memories...
  $solr_data = NULL;

  drush_print('Total items indexed in Solr: ' . count($solr_apaths));

  // Check if the counts are equal.
  if (count($solr_apaths) == count($db_apaths)) {
    drush_log('Great success, what should be indexed appears to be indexed!', 'ok');
    return;
  }

  // Unique our arrays and count again...
  drush_print('Making our arrays unique now for another count check...');
  $solr_apaths_unique = array_unique($solr_apaths);
  $db_apaths_unique = array_unique($db_apaths);

  if (count($solr_apaths_unique) == count($db_apaths_unique)) {
    drush_log('Moderate success, what should be indexed appears to be indexed! However, there appear to be duplicates somewhere.', 'ok');
    return;
  }

  // Counts don't match, figure out whats missing from where...
  drush_print('Counts do not match, generating diffs...');

  $items_in_solr_not_in_db = array_diff($solr_apaths_unique, $db_apaths_unique);
  drush_print('Items in Solr that are not in the database:');
  print_r($items_in_solr_not_in_db);

  $items_in_db_not_in_solr = array_diff($db_apaths_unique, $solr_apaths_unique);
  drush_print('Items in database that are not in Solr:');
  print_r($items_in_db_not_in_solr);

}

/**
 * Drush command callback for bps-titles-export-full-set-timestamp.
 *
 * @param int timestamp
 *   The Unix timestamp to set as last updated time.
 */
function drush_bps_core_bps_titles_export_full_set_timestamp(int $timestamp) {
  if (empty($timestamp)) {
    return;
  }
  try {
    // Update timestamp to the provided time.
    \Drupal::configFactory()->getEditable('bps_core.cron.titles_full_export')
      ->set('timestamp', (int) $timestamp)
      ->save();
    drush_print('Title FULL export cron timestamp is set to ' . (int) $timestamp . '.');
  }
  catch (Exception $e) {
    drush_print("An error occured: " . $e->getMessage());
  }
}

/**
 * Drush command callback for bps-titles-export-full-book.
 */
function drush_bps_core_bps_titles_export_full_book() {

  try {
    // Pass each type to content_export service, which in turn
    // creates the file at public://content-export/[file].csv.
    $content_exporter = \Drupal::service('bps_core.content_export');
    $export = $content_exporter->exportBooksFull();
    drush_print('Full book titles list csv file has been rebuilt');
  }
  catch (Exception $e) {
    drush_print("An error occured: " . $e->getMessage());
  }
}
