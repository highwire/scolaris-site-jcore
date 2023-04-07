/**
 * @file
 * Defines breakpoints (as defined in scolaris_bps.breakpoints.yml) using enquire.js.
 * Also adds a function to return current layout.
 *
 */

(function ($) {
  Drupal.bps_media_queries = Drupal.bps_media_queries || {};

  'use strict';

  var baseLayout = '';
  var current = baseLayout;
  var previous = baseLayout;
  var order = [];
  var index = 0;
  var breakpointsReady = false;

  /**
   * Fired when breakpoint matches
   */
  var breakpointMatch = function(key){
    previous = current || baseLayout;
    current = key;
  }

  /**
   * Fired when breakpoint unmatches
   */
  var breakpointUnmatch = function(key){
    previous = key;
    var i = order.indexOf(key);
    current = order[i-1] || baseLayout;
  }

  /**
   * Return the current layout for the page, based on Breakpoint media queries.
   *
   * @return
   *  A string matching the current breakpoint layout name based on viewport size.
   */
  Drupal.bps_media_queries.getCurrentLayout = function () {
    if (breakpointsReady) {
      return current;
    }
  };

  /**
   * Tests the given breakpoint against the current layout using the
   * provided operator.
   *
   * @param {string} key
   *  The key of the breakpoint to test against.
   * @param {string} op
   *  The operator to use, defaults to "==".
   *
   * @return {boolean}
   *  Whether the test passes or not.
   */
  Drupal.bps_media_queries.isCurrentLayout = function (key, op) {
    op = op || '==';

    if (op == '==') {
      return key == current;
    }

    var start = 0;
    var end = 0;
    var pos = order.indexOf(key);
    var orEqual = op.charAt(1) == '=';
    var operator = op.charAt(0);

    if (operator == '<') {
      start = 0;
      end = orEqual ? ++pos : pos;
    }

    if (operator == '>') {
      start = orEqual ? pos : ++pos;
      end = undefined;
    }

    var acceptedLayouts = order.slice(start, end);
    return acceptedLayouts.indexOf(current) > -1;
  }

  Drupal.behaviors.bpsMediaQueries = {
    attach: function (context, settings) {
      if (typeof(drupalSettings.bps_mediaqueries) !== "undefined" && typeof(drupalSettings.bps_mediaqueries.breakpoints) !== "undefined" && !breakpointsReady) {
        var breakpoints = drupalSettings.bps_mediaqueries.breakpoints;
        breakpointsReady = drupalSettings.bps_mediaqueries.breakpointsReady = true;
        baseLayout = breakpoints[Object.keys(breakpoints)[0]];
        current = previous = baseLayout;
      }

      if (breakpointsReady) {

        // Breakpoints should be defined in order of smallest to largest
        $.each(breakpoints, function (key, value) {

          order[index] = key;

          /**
           * Setup and register enquire.js callbacks based on breakpoints
           * If Breakpoints are configured but no match is made, this will often return 'mobile'.
           * This is done to support mobile-first design - in practice you shouldn't be
           * defining a "mobile" media query as it should be assumed to be the default.
           */
          if (typeof(enquire) !== "undefined") {
            enquire.register(value, {
              match: function() {
                breakpointMatch(key);
              },
              unmatch: function() {
                breakpointUnmatch(key);
              }
            });
          }

          index++;
        });
      }
    }
  };
})(jQuery);