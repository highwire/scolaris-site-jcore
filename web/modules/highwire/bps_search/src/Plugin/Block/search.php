<?php

namespace Drupal\bps_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Provides a 'search' Block.
 *
 * @Block(
 *   id = "search",
 *   admin_label = @Translation("search"),
 *   category = @Translation("search"),
 * )
 */
class search extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $output .= '
      <div class="journal-article__searchbox">
        <form class="d-flex input-group   search-filter-right " id="form-journal-search">
          <div class="search__position ">
            <input class="form-control journal-article__searchbox__input" id="id" placeholder="Search within this journal" required="" type="text" value="" />
          </div>
          <button class="btn btn-journal-article__searchbox-submit" type="submit">Search</button>
        </form>
      </div>'; 

    return array(
      '#type' => 'markup',
      '#markup' => $output,
      '#cache' => array(
        'max-age' => 0,
      ),
      '#allowed_tags' => ['div', 'form', 'input', 'button'],
    );
  }
}