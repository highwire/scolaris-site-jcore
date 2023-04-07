<?php
/**
 * @file
 * Contains Drupal\bps_search\Form\searchBrowse.
 */

namespace Drupal\bps_search\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class searchBrowse extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bps_search_browse';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $current_path = \Drupal::service('path.current')->getPath();
    $placeholders = explode("/",$current_path);
    $nid = $placeholders['2']; 
    $node = \Drupal::entityManager()->getStorage('node')->load($nid);
    $form['#action'] = '/search';
    $form['#method'] = 'get';
    $facets = [];
    $content_type_labels = [];
    $content_type_labels[HW_NODE_TYPE_REPORT_GUIDELINE] = 'report or guideline';
    $content_type_labels[HW_NODE_TYPE_MONOGRAPH] = 'monograph';
    $pid = $node->get('parent')->getString();

    // get the parent node detail
    if (empty($pid)) {
      $pnodetype = $node->bundle();
      $pnid = $node->get('nid')->getString();
      $nodetitle = $node->get('title')->getString();
    }
    else {
      $pnode = \Drupal::entityManager()->getStorage('node')->load($pid);
      $pnodetype = $pnode->bundle();
      $nodetitle = $pnode->get('title')->getString();
      $pnid = $pnode->get('nid')->getString();
    }
    switch ($node->bundle()) {
      case 'journal':
        $type_label = 'periodical';
        $facets['journal_title_facet'] = $node->get('title')->getString();
        $facets['chapter_type'] = "periodical-article";
        break;
      case 'journal_issue':
        $type_label = 'issue';
        $facets['issue'] = $node->get('issue')->getString();
        $facets['volume'] = $node->get('volume')->getString();
        $facets['journal_title_facet'] = $node->get('journal_title')->getString();
        $facets['chapter_type'] = "periodical-article";
        break;
      case 'item_report_guideline':
      case 'item_chapter':
      case 'item_monograph':
      case 'item_back_matter':
      case 'item_front_matter':
        $type_label = $content_type_labels[$pnodetype];
        $facets['parent_facet'] = $pnid;
        $facets['chapter_type'] = ($pnodetype == 'item_report_guideline') ? "report-guideline-item-chapter" : "monograph-item-chapter";
        break;
      default:
        return NULL;
    }
    $placeholder = "Search within this $type_label";
    $i = 0;
    foreach ($facets as $facet_field => $facet_value) {
      if (is_array($facet_value)) {
        foreach ($facet_value as $val) {
          $form["f[$i]"] = [
            '#type' => 'hidden',
            '#value' => $facet_field . ':' . trim(strip_tags($val)),
          ];
          $i++;
        }
      }
      else {
        $form["f[$i]"] = [
          '#type' => 'hidden',
          '#value' => $facet_field . ':' . trim(strip_tags($facet_value)),
        ];
        $i++;
      }
    }
    $form['query'] = array(
      '#type' => 'textfield',
      '#placeholder' => $placeholder,
      '#required' => TRUE,
      '#attributes' => array('class' => array('form-control journal-article__searchbox__input')),
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#attributes' => array('class' => array('btn btn btn-journal-article__searchbox-submit')),
      '#value' => $this->t('Search'),
      '#name' => '',
    );
    return $form;
  }
 
  /**
   * Form submission handler.
   *
   * This is included to fulfill the Drupal\Core\Form\FormInterface interface, but does nothing.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Do nothing.
  }

}