<?php

namespace Drupal\bps_search\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Contains a form for switching the view mode of a node during preview.
 */
class FacetsDateRangeForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return Html::getUniqueId('bps_search_facets_daterange_form');
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $items = []) {
    $form['#attributes']['class'][] = 'facets-date-range';
    $form['#attached']['library'][] = 'bps_search/drupal.bps_search.facets-daterange';
    $form['#attributes']['data-parsley-validate'] = '';

    // Include all / some options.
    $form['include_options'] = [
      '#type' => 'radios',
      '#title' => $this->t('Include options'),
      '#title_display' => 'invisible',
      '#options' => [
        0 => $this->t('Include all'),
        1 => $this->t('Include date published'),
      ],
      '#default_value' => !empty($items['include_options']) ? $items['include_options'] : 0,
      '#attributes' => ['data-default-value' => 0],
    ];

    // Date range input fields.
    $form['range'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['facets-date-range__inputs']],
    ];

    $input_attr = [
      'placeholder' => 'YYYY',
      'maxlength' => 4,
      'data-default-value' => '',
      'data-parsley-group' => 'range',
      'data-parsley-trigger' => 'focusout',
      'pattern' => '\d{4}',
      'data-parsley-pattern' => '\d{4}',
      'data-parsley-errors-messages-disabled' => '',
    ];


    foreach (['min', 'max'] as $k) {
      $label = $k == 'min' ? $this->t('From') : $this->t('To');
      $form['range'][$k] = [
        '#type' => 'textfield',
        '#title' => $label,
        '#default_value' => '',
        '#attributes' => $input_attr,
      ];

      $form['range'][$k]['#date_time_element'] = 'none';
    }

    $form['range_error'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['datefields-error', 'hidden'], 'id' => Html::getUniqueId('datefields-error')],
      '#value' => $this->t('Please enter @datetype in the form %format', [
        '@datetype' => 'years',
        '%format' => 'YYYY',
      ]),
    ];

    $request = $this->getRequest();
    $query = $request->query;
    $query = !empty($query) ? $query->all() : [];

    if (!empty($query['date-min'])) {
      $form['range']['min']['#default_value'] = $query['date-min'];
      $form['range']['min']['#attributes']['data-default-value'] = $form['range']['min']['#default_value'];
    }

    if (!empty($query['date-max'])) {
      $form['range']['max']['#default_value'] = $query['date-max'];
      $form['range']['max']['#attributes']['data-default-value'] = $form['range']['max']['#default_value'];
    }

    // Make sure 'include some' is checked'.
    $form['include_options']['#default_value'] = !empty($items['include_options']) ? $items['include_options'] : 0;
    $form['include_options']['#attributes']['data-default-value'] = !empty($items['include_options']) ? $items['include_options'] : 0;


    // Form actions.
    $form['actions'] = ['#type' => 'actions', '#attributes' => ['class' => ['hidden']]];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#attributes' => ['class' => ['btn', 'btn-primary']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Build redirect url.
    $request = $this->getRequest();
    $query = $request->query;

    $options = ['query' => !empty($query) ? $query->all() : []];

    // If 'include all' option was submitted, remove any facets.
    if ($values['include_options'] == '0') {
      unset($options['query']['date-min']);
      unset($options['query']['date-max']);
    }

    // Include published dates.
    else {
      if (!empty($values['min'])) {
        $options['query']['date-min'] = $values['min'];
      }

      if (!empty($values['max'])) {
        $options['query']['date-max'] = $values['max'];
      }
    }

    $path = $request->getPathInfo();
    $redirect_url = Url::fromUserInput(urldecode($path), $options);

    $form_state->setRedirectUrl($redirect_url);
  }

}
