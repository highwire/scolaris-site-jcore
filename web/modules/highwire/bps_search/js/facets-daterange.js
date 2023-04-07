/**
 * @file
 * Adds interaction behavior to search facets date range form.
 */

(function ($) {

  'use strict';

  Drupal.bps_search_daterange = Drupal.bps_search_daterange || {};
  Drupal.behaviors.bpsSearchDateRanges = {
    attach: function (context, settings) {
      var $form = $('form.facets-date-range', context);
      var $rangeField = $form.find('.facets-date-range__inputs input');

      if ($form.attr('data-parsley-validate') != undefined) {
        $form.once('bps-daterange-form-validate').each(Drupal.bps_search_daterange.formValidate);
        $rangeField.once('bps-daterange-field-validate').each(Drupal.bps_search_daterange.rangeFieldValidate);
      }

      $form.find('input[name="include_options"]').once('bps-daterange-change-radios').change(Drupal.bps_search_daterange.changeRadios);
      $rangeField.once('bps-daterange-focus-rangefields').focus(Drupal.bps_search_daterange.focusRangefields);
      $rangeField.once('bps-daterange-keyup-rangefields').keyup(Drupal.bps_search_daterange.changeRangefields);
      $rangeField.once('bps-daterange-change-rangefields').change(Drupal.bps_search_daterange.changeRangefields);
    },
  };

  /**
   * Behavior for parsley form validation.
   */
  Drupal.bps_search_daterange.formValidate = function () {
    var $form = $(this);
    var formInstance = $form.parsley();
    if (typeof (formInstance) == undefined) {
      return;
    }
    formInstance.on('field:validated', function () {
      var isValid = formInstance.isValid({ group: 'range', force: false });
      if (!isValid) {
        $form.children('.datefields-error').removeClass('hidden');
        $form.find('.form-actions button').attr('disabled', true);
      }
      else {
        $form.children('.datefields-error').addClass('hidden');
        $form.find('.form-actions button').removeAttr('disabled');
      }
    });
  }

  /**
   * Behavior for parsley range field validation.
   */
  Drupal.bps_search_daterange.rangeFieldValidate = function () {
    var $rangeField = $(this);
    var $errorMsg = $rangeField.parents('form.facets-date-range').last().children('.datefields-error');
    var fieldInstance = $rangeField.parsley();
    if (typeof (fieldInstance) == "undefined") {
      return;
    }
    fieldInstance.on('field:error', function (fieldInstance) {
      $rangeField.attr('aria-invalid', '').attr('aria-described-by', $errorMsg.attr('id')).parent().addClass('error has-error');
    });
    fieldInstance.on('field:success', function (fieldInstance) {
      $rangeField.removeAttr('aria-invalid aria-described-by').parent().removeClass('error has-error');
    });
  }

  /**
   * Behavior for when radios are changed.
   */
  Drupal.bps_search_daterange.changeRadios = function () {
    var radio = this;
    var $parent = $(radio).parents('form.facets-date-range').last();
    var $rangeFields = $parent.find('.facets-date-range__inputs input');
    var $actions = $parent.find('.form-actions');

    // Hide submit button if radios have been reset to default value.
    if ($(radio).data('default-value') == radio.value) {
      $actions.addClass('hidden');
    }

    if (radio.value == 0) {
      // "Include all" was selected, so clear input fields.
      $rangeFields.val('').attr('data-val-reset', '');
      if ($parent.attr('data-parsley-validate') != undefined) {
        $rangeFields.each(function (i) {
          $(this).parsley().validate();
        });
      }

      // Only show submit button if including all will clear active items.
      if ($(radio).data('default-value') !== 0) {
        $actions.removeClass('hidden');
      }
    }
    else {
      // "Include some" was selected, so focus the first textfield.
      $rangeFields.first().focus();
    }
  }

  /**
   * Behavior for when date range input fields are changed.
   */
  Drupal.bps_search_daterange.changeRangefields = function (e) {
    // 'this' references rangefield element.
    var $parent = $(this).parents('form.facets-date-range').last();
    var $rangeFields = $parent.find('.facets-date-range__inputs input');
    var $actions = $parent.find('.form-actions');

    // Exclude keys that don't alter input value.
    var discardKeyCode = [
      16, // shift
      17, // ctrl
      18, // alt
      20, // caps lock
      33, // page up
      34, // page down
      35, // end
      36, // home
      37, // left arrow
      38, // up arrow
      39, // right arrow
      40, // down arrow
      9, // tab
      13, // enter
      27  // esc
    ];
    if (e.type != 'keyup' || $.inArray(e.keyCode, discardKeyCode) === -1) {
      if (Drupal.bps_search_daterange.checkDefaultState($rangeFields)) {
        // Hide submit button.
        $actions.addClass('hidden');
      }
      else {
        // Show submit button.
        $actions.removeClass('hidden');
      }
    }
  }

  /**
   * Behavior for when date range input fields are focused.
   */
  Drupal.bps_search_daterange.focusRangefields = function () {
    // 'this' references input element.
    var $parent = $(this).parents('form.facets-date-range').last();
    var $rangeFields = $parent.find('.facets-date-range__inputs input');
    var $actions = $parent.find('.form-actions');

    // Make sure 'include some' option is checked.
    var $includeSome = $parent.find('input[name="include_options"][value=1]');
    if ($includeSome.prop('checked') == false) {
      $rangeFields.attr('data-val-reset', '');
      $includeSome.prop('checked', true);
    }

    // Set default value of input fields.
    $rangeFields.each(Drupal.bps_search_daterange.setDefaultValue);
    if (Drupal.bps_search_daterange.checkDefaultState($rangeFields)) {
      // Hide submit button.
      $actions.addClass('hidden');
    }
    else {
      // Show submit button.
      $actions.removeClass('hidden');
    }
  }

  /**
   * Function to check if list of input elements are all in their default states.
   */
  Drupal.bps_search_daterange.checkDefaultState = function ($inputs) {
    var isDefaultState = true;
    $inputs.each(function () {
      if (String($(this).data('default-value')) !== this.value) {
        isDefaultState = false;
        return false;
      }
    });

    return isDefaultState;
  }

  /**
   * Set default value of date range input fields.
   */
  Drupal.bps_search_daterange.setDefaultValue = function () {
    var $rangeField = $(this);
    var is_value_reset = ($rangeField.attr('data-val-reset') != undefined) ? true : false;
    var defaultValue = $rangeField.data('default-value');
    if (is_value_reset && typeof (defaultValue) !== undefined && $rangeField.val() == '') {
      $rangeField.val(defaultValue);
      $rangeField.removeAttr('data-val-reset');
    }
  }

  /**
   * Function to completely reset date range form.
   */
  Drupal.bps_search_daterange.resetValues = function () {
    var $form = $(this);
    $form.find('input[type="radio"]').each(function () {
      var radio = this;
      if (String($(radio).data('default-value')) == radio.value) {
        $(radio).prop('checked', true);
      }
    });
    $form.find('.facets-date-range__inputs input').each(function () {
      var $input = $(this);
      if (typeof ($input.data('default-value')) !== 'undefined') {
        $input.val($input.data('default-value')).trigger('change');
      }
    });
    if (typeof ($form.parsley()) !== 'undefined') {
      $form.parsley().validate();
    }
  }
}(jQuery));
