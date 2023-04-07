/**
 * @file
 * Transforms links into checkboxes.
 */

(function ($) {

  'use strict';
  Drupal.bps_search = Drupal.bps_search || {};
  Drupal.behaviors.facetsCheckboxWidgets = {
    attach: function (context, settings) {
      Drupal.bps_search.makeCheckboxes();
      $('.bps-checkbox-multiple-apply').once('bps-checkbox-multiple-apply-click').each(Drupal.bps_search.applyClick);
      $('.bps-checkbox-multiple-cancel').once('bps-checkbox-multiple-cancel-click').click(Drupal.bps_search.cancelClick);
      $('.facets-checkbox', context).once('bps-checkbox-toggle-multiple').change(Drupal.bps_search.facetCheckboxMutliple);
      $('.facet-item-include-all .form-checkbox', context).once('bps-include-all-multiple-toggle').change(Drupal.bps_search.includeAllChange);
    }
  };

  /**
   * When cancel is applied revert to original state.
   */
  Drupal.bps_search.cancelClick = function () {
    var $cancelButton = $(this);
    var $parent = $cancelButton.closest('ul.bps-checkbox-multiple-facet');
    $parent.find('input[type="checkbox"]').each(function (e) {
      var $checkbox = $(this);
      var checked = $checkbox.data('facet-checkboxes-saved-state');
      if (checked != undefined) {
        if ($checkbox.hasClass('facets-checkbox-include-all')) {
          Drupal.bps_search.checkIncludeAll($checkbox);
        }
        else {
          $checkbox.prop('checked', true);
        }
      }
      else {
        if ($checkbox.hasClass('facets-checkbox-include-all')) {
          Drupal.bps_search.uncheckIncludeAll($checkbox);
        }
        else {
          $checkbox.prop('checked', false);
        }
      }
    });
    $parent.find('.bps-checkbox-multiple-apply').attr('disabled', '').removeAttr('data-clear-facet-url');
  }
  Drupal.bps_search.facetCheckboxMutliple = function () {
    var $checkbox = $(this);
    var $root = $checkbox.parents('.js-facets-checkbox-links').last();
    var $includeAllCheckbox = $root.find('.facet-item-include-all input[type="checkbox"]');
    var $parents = $checkbox.parents('li.facet-item');
    var activeCount = $root.attr('data-drupal-facet-active-item-count') ? $root.attr('data-drupal-facet-active-item-count') : 0;
    var initCheckedCount = $root.attr('data-drupal-facet-checked-item-count') ? $root.attr('data-drupal-facet-checked-item-count') : 0;

    // Toggle children when ancestor is clicked/toggle ancestor when children are clicked
    $parents.first().children('ul .list-facet-items').find('.facets-checkbox').each(function () {
      $(this).prop('checked', false);
    });

    // Uncheck ancestor checkboxes.
    $parents.each(function (i) {
      if (i != 0) {
        $(this).find('> .facets-checkbox').attr('checked', false);
      }
    });

    // If all siblings are checked, check parent
    if ($checkbox.is(':checked') === true) {
      var siblingsChecked = true;
      var $siblingCheckboxes = $parents.first().siblings('.facet-item').find('> .facets-checkbox');

      $siblingCheckboxes.each(function () {

        if ($(this).is(':checked') !== true) {
          siblingsChecked = false;
          return;
        }
      });

      if (siblingsChecked === true) {
        $checkbox.prop('checked', false);
        $siblingCheckboxes.each(function () {
          $(this).prop('checked', false);
        });

        // Check the parent check box.
        var $parentNode = $checkbox.parents().eq(3);
        if ($parentNode !== undefined) {
          $parentNode.find('.facets-checkbox-include-all').attr('checked', true).trigger('change')
        }
      }
    }

    var $applyButton = $root.find('.bps-checkbox-multiple-apply');
    var resetFacet = $applyButton.attr('data-clear-facet-url') ? true : false;

    // Find all checkboxes in this group.
    var boxesChecked = false;
    var $allCheckboxes = $checkbox.closest('.js-facets-checkbox-links').find('input.facets-checkbox');
    var $checkedCheckboxes = $allCheckboxes.filter(':checked');

    if ((resetFacet || initCheckedCount == activeCount) && $checkedCheckboxes.length == $allCheckboxes.length) {
      $allCheckboxes.each(function () {
        $(this).prop('checked', false);
        Drupal.bps_search.checkIncludeAll($includeAllCheckbox);
      });
    } else if ($checkedCheckboxes.length > 0) {
      Drupal.bps_search.uncheckIncludeAll($includeAllCheckbox);
    }
    else if ((resetFacet || initCheckedCount == activeCount) && $checkedCheckboxes.length == 0) {
      Drupal.bps_search.checkIncludeAll($includeAllCheckbox);
    }

    // Set/remove disabled attribute on apply button depending on default state of checkboxes.
    var isDefaultState = true;
    if (resetFacet && $checkedCheckboxes.length != activeCount) {
      isDefaultState = false;
    }
    else {
      $allCheckboxes.each(function () {
        var thisCheckbox = this;
        if ((thisCheckbox.hasAttribute('data-facet-checkboxes-saved-state') && !thisCheckbox.checked) || (!thisCheckbox.hasAttribute('data-facet-checkboxes-saved-state') && thisCheckbox.checked)) {
          isDefaultState = false;
          return false;
        }
      });
    }
    if (isDefaultState) {
      $applyButton.attr('disabled', '');
    }
    else {
      $applyButton.removeAttr('disabled');
    }
  };

  Drupal.bps_search.uncheckIncludeAll = function ($includeAllCheckbox) {
    $includeAllCheckbox.removeAttr('checked').removeAttr('disabled');
    $includeAllCheckbox.parents('.facet-item-include-all').removeClass('form-disabled');
  };

  Drupal.bps_search.checkIncludeAll = function ($includeAllCheckbox) {
    $includeAllCheckbox.prop('checked', true).prop('disabled', true);
    $includeAllCheckbox.parents('.facet-item-include-all').addClass('form-disabled');
  };

  Drupal.bps_search.includeAllChange = function () {
    var $includeAllCheckbox = $(this);
    var url = $includeAllCheckbox.attr('data-url');
    var checked = this.checked;
    if (checked) {
      Drupal.bps_search.checkIncludeAll($includeAllCheckbox);
      $includeAllCheckbox.parents('li.facet-item-include-all').siblings().find('.facets-checkbox').attr('checked', false);
      // Redirect to facet url.
      var isMultiple = $includeAllCheckbox.closest('.js-facets-checkbox-links').hasClass('bps-checkbox-multiple-facet');
      if (typeof (url) !== 'undefined' && isMultiple == false) {
        window.location.href = url;
      }
    }
    // Set/remove disabled attribute on apply button depending on default state of include all checkbox.
    var defaultChecked = $includeAllCheckbox.data('facet-checkboxes-saved-state') != undefined ? true : false;
    if (checked && defaultChecked || !checked && !defaultChecked) {
      $includeAllCheckbox.closest('.bps-checkbox-multiple-facet').find('.bps-checkbox-multiple-apply').attr('disabled', '');
    }
    else {
      var $applyButton = $includeAllCheckbox.closest('.bps-checkbox-multiple-facet').find('.bps-checkbox-multiple-apply');
      $applyButton.removeAttr('disabled');
      if (url) {
        $applyButton.attr('data-clear-facet-url', url);
      }
    }
  };

  Drupal.bps_search.applyClick = function () {
    var $applyButton = $(this);
    $applyButton.click(function (e) {
      var args = [];
      var unchecked = [];
      // Build new url based on clear facet url or current url, depending on whether the
      // 'include all' checkbox has been clicked.
      var l = location;
      if ($applyButton.attr('data-clear-facet-url')) {
        l = Drupal.bps_search.getLocation($applyButton.attr('data-clear-facet-url'));
      }
      var getParams = Drupal.bps_search.getQueryStringAsObject(l.search);
      var facetId = $applyButton.closest('.js-facets-checkbox-links').data('drupal-facet-id');
      var base_url = l.protocol + '//' + l.host + l.pathname;
      var totalCheckboxes = $applyButton.closest('.js-facets-checkbox-links').find('input.facets-checkbox').length;

      $applyButton.closest('.js-facets-checkbox-links').find('input.facets-checkbox').each(function (e) {
        var $checkbox = $(this)
        var arg = $checkbox.data('facetarg');
        if ($checkbox.is(':checked')) {
          args.push(arg);
        }
        else {
          unchecked.push(arg);
        }
      });

      var startingArgFacetIndex = 0;

      // Find the starting index for new facet filters.
      var currentIndexes = [];
      var facetParamReg = /f\[((\d)+)\]/;

      Object.keys(getParams).forEach(function (key, value) {
        // Figure out the last index value.
        var facetIndexMatch = facetParamReg.exec(key);
        if (facetIndexMatch != null) {
          currentIndexes.push(facetIndexMatch[1]);
        }

        if (unchecked.indexOf(getParams[key]) >= 0) {
          delete getParams[key];
        }
      });

      if (currentIndexes.length > 0) {
        currentIndexes = currentIndexes.sort(function (a, b) {
          return a.index - b.index;
        });
        startingArgFacetIndex = parseInt(currentIndexes.slice(-1).pop()) + 1;
      }

      for (var i = 0; i < args.length; i++) {
        getParams["f[" + startingArgFacetIndex + "]"] = args[i];
        startingArgFacetIndex++;
      }

      if (getParams != null) {
        base_url = base_url + '?' + Drupal.bps_search.encodeQueryData(getParams);
      }

      window.location.href = base_url;
    });
  };

  Drupal.bps_search.encodeQueryData = function (data) {
    var ret = [];
    for (var d in data)
      ret.push(encodeURIComponent(d) + '=' + encodeURIComponent(data[d]));
    return ret.join('&');
  }

  /**
   * Turns all facet links into checkboxes.
   */
  Drupal.bps_search.makeCheckboxes = function () {
    // Find all checkbox facet links and give them a checkbox.
    var $list = $('.js-facets-checkbox-links');
    var $links = $list.find('.facet-item a');
    var checkedCount = $links.filter('.is-active').length;
    $list.once('facets-checked-count').attr('data-drupal-facet-checked-item-count', checkedCount);
    $links.once('facets-checkbox-transform').each(Drupal.bps_search.makeCheckbox);
  };

  /**
   * Replace a link with a checked checkbox.
   */
  Drupal.bps_search.makeCheckbox = function () {
    var $link = $(this);
    var isMultiple = !!$link.closest('ul.bps-checkbox-multiple-facet').length;
    var active = $link.hasClass('is-active');
    var description = $link.html();
    var href = $link.attr('href');
    var id = $link.data('drupal-facet-item-id');
    var facetArg = $link.data('drupal-facet-item-arg');

    var checkbox = $('<input type="checkbox" class="facets-checkbox">')
      .attr('id', id)
      .data('facetarg', facetArg)
      .data('facetsredir', href);
    var label = $('<label for="' + id + '">' + description + '</label>');

    if (isMultiple == false) {
      checkbox.on('change.facets', function (e) {
        Drupal.bps_search.disableFacet($link.parents('.js-facets-checkbox-links'));
        window.location.href = $(this).data('facetsredir');
      });
    }


    if (active) {
      checkbox.attr('checked', true);
      checkbox.attr('data-facet-checkboxes-saved-state', 'checked');

      label.find('.js-facet-deactivate').remove();
    }

    $link.before(checkbox).before(label).remove();

  };

  /**
   * Disable all facet checkboxes in the facet and apply a 'disabled' class.
   *
   * @param {object} $facet
   *   jQuery object of the facet.
   */
  Drupal.bps_search.disableFacet = function ($facet) {
    $facet.addClass('facets-disabled');
    $('input.facets-checkbox').click(Drupal.bps_search.preventDefault);
    $('input.facetapi-checkbox', $facet).attr('disabled', true);
  };

  /**
   * Event listener for easy prevention of event propagation.
   *
   * @param {object} e
   *   Event.
   */
  Drupal.bps_search.preventDefault = function (e) {
    e.preventDefault();
  };

  Drupal.bps_search.getQueryStringKey = function (key, queryString) {
    queryString = typeof (queryString) !== "undefined" ? queryString : window.location.search;
    return Drupal.bps_search.getQueryStringAsObject(queryString)[key];
  };

  Drupal.bps_search.getQueryStringAsObject = function (queryString) {
    queryString = typeof (queryString) !== "undefined" ? queryString : window.location.search;
    var b, cv, e, k, ma, sk, v, r = {},
      d = function (v) { return decodeURIComponent(v).replace(/\+/g, " "); }, //# d(ecode) the v(alue)
      q = queryString.substring(1), //# suggested: q = decodeURIComponent(window.location.search.substring(1)),
      s = /([^&;=]+)=?([^&;]*)/g //# original regex that does not allow for ; as a delimiter:   /([^&=]+)=?([^&]*)/g
      ;

    //# ma(make array) out of the v(alue)
    ma = function (v) {
      //# If the passed v(alue) hasn't been setup as an object
      if (typeof v != "object") {
        //# Grab the cv(current value) then setup the v(alue) as an object
        cv = v;
        v = {};
        v.length = 0;

        //# If there was a cv(current value), .push it into the new v(alue)'s array
        //#     NOTE: This may or may not be 100% logical to do... but it's better than loosing the original value
        if (cv) { Array.prototype.push.call(v, cv); }
      }
      return v;
    };

    //# While we still have key-value e(ntries) from the q(uerystring) via the s(earch regex)...
    while (e = s.exec(q)) { //# while((e = s.exec(q)) !== null) {
      //# Collect the open b(racket) location (if any) then set the d(ecoded) v(alue) from the above split key-value e(ntry)
      b = e[1].indexOf("[");
      v = d(e[2]);

      //# As long as this is NOT a hash[]-style key-value e(ntry)
      if (b < 0) { //# b == "-1"
        //# d(ecode) the simple k(ey)
        k = d(e[1]);

        //# If the k(ey) already exists
        if (r[k]) {
          //# ma(make array) out of the k(ey) then .push the v(alue) into the k(ey)'s array in the r(eturn value)
          r[k] = ma(r[k]);
          Array.prototype.push.call(r[k], v);
        }
        //# Else this is a new k(ey), so just add the k(ey)/v(alue) into the r(eturn value)
        else {
          r[k] = v;
        }
      }
      //# Else we've got ourselves a hash[]-style key-value e(ntry)
      else {
        //# Collect the d(ecoded) k(ey) and the d(ecoded) sk(sub-key) based on the b(racket) locations
        k = d(e[1].slice(0, b));
        sk = d(e[1].slice(b + 1, e[1].indexOf("]", b)));

        //# ma(make array) out of the k(ey)
        r[k] = ma(r[k]);

        //# If we have a sk(sub-key), plug the v(alue) into it
        if (sk) { r[k][sk] = v; }
        //# Else .push the v(alue) into the k(ey)'s array
        else { Array.prototype.push.call(r[k], v); }
      }
    }

    //# Return the r(eturn value)
    return r;
  };

  Drupal.bps_search.getLocation = function (href) {
    var l = document.createElement("a");
    l.href = href;
    return l;
  }

})(jQuery);