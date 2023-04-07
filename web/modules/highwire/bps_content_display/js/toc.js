(function ($) {

  'use strict';

  Drupal.behaviors.bpsToc = {
    attach: function (context, settings) {
      var pathName = location.pathname.replace(/\/+$/, '');
      $(".hw-table-of-contents-item[data-cpath='" + pathName + "']").once().each(function() {
        $(this).addClass('selected');
        $(this).find('.field--highwire-content-title').unwrap();
      });
    }
  };

}(jQuery));