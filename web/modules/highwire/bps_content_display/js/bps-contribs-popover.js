(function ($) {
  Drupal.behaviors.bpsAuthorBioPopover = {
    attach: function (context, settings) {

      // Initialize popovers.
      $('.contrib', context).once('bps-author-bio-popover').each(function(){
        var container = $(this);
        container.children('[data-toggle="author-bio-popover"]').popover({
          template: '<div class="popover author-bio-popover" role="tooltip"><div class="arrow"></div><h3 class="popover-title"></h3><div class="popover-content"></div></div>',
          container: container,
          placement: function (tip, el) {
            return ($(window).width() >= 576) ? 'right' : 'top';
          },
        });
      });

      // Show popover on click or focus.
      $('body', context).delegate('.contrib > a[data-toggle="author-bio-popover"]', 'focus click', function (e) {
        e.preventDefault();
        var $trigger = $(this);
        var popover = $trigger.data('bs.popover') ? $trigger.data('bs.popover').tip() : false;
        if (!popover) {
          return;
        }

        // Hide all other contrib popovers in the list.
        $trigger.parent().siblings().each(function() {
          $(this).children('a[data-toggle="author-bio-popover"]').popover('hide');
        });

        // Show popover if hidden.
        if (!popover.is(':visible')) {
          $trigger.popover('show');
        }
      });

      // Hide popovers when ESC key is pressed.
      $('.contrib > a[data-toggle="author-bio-popover"]', context).on('shown.bs.popover', function (e) {
        var $trigger = $(this);
        $(document).keyup(function (event) {
          // ESC key
          if (event.keyCode == 27) {
            $trigger.popover('hide');
          }
        });
      });

      // Hide author bio popover on click events outside the popover or a trigger.
      $('body', context).on('click', function (e) {
        var $target = $(e.target);
        if (!$target.hasClass('author-bio-popover') && $target.parents('.author-bio-popover').length === 0 && $target.attr('data-toggle') != 'author-bio-popover') {
          $('.contrib > a[data-toggle="author-bio-popover"]', context).popover('hide');
        }
      });

      // Hide popover when another element is focused.
      $('body', context).delegate('.contrib > a[data-toggle="author-bio-popover"]', 'blur', function (e) {
        var $trigger = $(this);
        if (!e.relatedTarget || !$trigger.data('bs.popover')) {
          return;
        }
        if (!$.contains($trigger.data('bs.popover').tip()[0], e.relatedTarget)) {
          $(this).popover('hide');
        }
      });

      $(".contributor-list li.contrib").once('wrapAuthor').each(function(i) {
        if (i < $(".contributor-list li.contrib").length - 1) {
          if ($(this).find('a').length > 0) {
            $(this).find('a').after("&nbsp;|&nbsp;");
          } else {
            $(this).html($(this).html() + "&nbsp;|&nbsp;");
          }
        }
      });

    }
  }
}(jQuery));