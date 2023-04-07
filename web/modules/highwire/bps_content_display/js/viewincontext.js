(function bpsViewInContext($, Drupal) {
  Drupal.bpsViewInContext = Drupal.bpsViewInContext || {};
  Drupal.behaviors.bpsViewInContext = {
    attach: function (context, settings) {
      // Intercept jump to anchor link on page load so we can apply smoothScroll.
      $(window).once('check-hash').each(function() {
        if (window.location.hash) {
          var $banner = $('.page-loading-banner', context);
          var targetID = Drupal.bpsViewInContext.getHash(window.location);
          var jumpLink = document.createElement('a');
          jumpLink.href = window.location.href;

          // Replace hash before smoothScroll.
          if (typeof history.replaceState !== 'undefined') {
            history.replaceState({}, null, '#/' + targetID);
          }
          else {
            window.location.hash = '#/' + targetID;
          }

          $('body').on('HighwireAjaxPlaceholderLoaded', function(e, ph, data) {
            var lastData = data.pop();
            if (Drupal.bpsViewInContext.getTarget(targetID, lastData.data)) {
              var ev = Drupal.bpsViewInContext.getEventData(e, jumpLink, lastData.data);
              Drupal.bpsViewInContext.hashOnPageLoad(ev, $banner);
            }
          });

          $(window).on('load', function(e) {
            if (Drupal.bpsViewInContext.getTarget(targetID, context)) {
              var ev = Drupal.bpsViewInContext.getEventData(e, jumpLink, context);
              Drupal.bpsViewInContext.hashOnPageLoad(ev, $banner);
            }
            else {
              // Hide loading banner if there is no element matching the link hash.
              if ($banner.length > 0) {
                $banner.removeClass('in').attr('aria-expanded', 'false').hide();
              }
            }
          });

          $('body').on('MathJaxLoaded', function(e){
            var ev = Drupal.bpsViewInContext.getEventData(e, jumpLink, context);
            MathJax.Hub.Register.StartupHook("End", function() {
              Drupal.bpsViewInContext.smoothScroll(ev);
            });
          });
        }
        else {
          $(window).on('load', function(e) {
            $('.is-sticky #book-toc', context).once('scroll-toc-onLoad').each(Drupal.bpsViewInContext.scrollToc);
          });
        }
      });

      /* Smooth scroll */
      // Select all links with hashes
      $('a[href^="#"],a[href^="' + location.pathname + '#"]', context).once('bps-anchors-smoothscroll')
        // Remove links that don't actually link to anything,
        // and don't scroll on user tab clicks.
        .not('[href$="#"]').not('[href$="#0"]').not('[data-toggle]')
        .not('.highwire-tabs-links__link').not('.noscroll').not('.highwire-modal-trigger')
        .click({context: context}, Drupal.bpsViewInContext.smoothScroll);

      /* Override scroll position when element is focused to account for sticky header. */
      $('body', context).once('focus-scroll').on('focus', '.smoothscroll-target', function(e){
        if (e.target) {
          var scrollPos = $(e.target, context).data('scrollPos');
          if (typeof scrollPos !== 'undefined') {
            $('html, body', context).scrollTop(scrollPos);
          }
        }
      });
    },
  };

  Drupal.bpsViewInContext.getEventData = function(e, jumpLink, context) {
    var ev = _.clone(e);
    ev.currentTarget = jumpLink;
    ev.data = typeof e.data !== 'undefined' ? e.data : {};
    ev.data.context = context;
    ev.data.scrollToc = true;
    ev.data.updateHash = false;
    return ev;
  }

  Drupal.bpsViewInContext.hashOnPageLoad = function(e, $banner) {
    // If the loading banner is displayed, hide banner
    // and do smooth scroll when banner is done being hidden.
    if ($banner.length > 0) {
      $banner.removeClass('in').attr('aria-expanded', 'false');
      // Animation is handled via CSS transition with .25s duration.
      setTimeout(function() {
        $banner.hide();
        Drupal.bpsViewInContext.smoothScroll(e);
      }, 250);
    }
    else {
      // Do smooth scroll.
      Drupal.bpsViewInContext.smoothScroll(e);
    }
  }

  /**
   * Get hash of url.
   */
  Drupal.bpsViewInContext.getHash = function(url) {
    var hash = url.hash.slice(1);
    hash = hash.charAt(0) === '/' ? hash.replace('/', '') : hash;
    return hash;
  }

  /**
   * Get target element.
   */
  Drupal.bpsViewInContext.getTarget = function(targetID, context) {
    var target = $('[id="' + targetID + '"]', context);
    target = target.length ? target : $('[name="' + targetID + '"]', context);
    return target.length > 0 ? target : false;
  }

  /**
   * Get height of sticky header element.
   */
  Drupal.bpsViewInContext.getStickyHeaderHeight = function(elem, context) {
    var stickyHeader = typeof(Drupal.bps_media_queries.isCurrentLayout) !== undefined && Drupal.bps_media_queries.isCurrentLayout('screen-md2-min', '>=') ? $('#content-tools.is-sticky', context) : $('.mobile-sticky-header.is-sticky', context);
    var stickyHeaderHeight = 0;
    if (stickyHeader.length > 0 && (stickyHeader.css('position') == 'sticky' || stickyHeader.css('position') == '-webkit-sticky') && stickyHeader.parent().has(elem)) {
      stickyHeaderHeight = stickyHeader.first().outerHeight();
    }
    // Adjust for height of loading banner if displayed.
    if ($('.page-loading-banner', context).length > 0) {
      var loadingBanner = $('.page-loading-banner', context).first();
      if (loadingBanner.is(':visible')) {
        stickyHeaderHeight += loadingBanner.outerHeight();
      }
    }
    return stickyHeaderHeight;
  }

  /**
   * Scroll toc to active item.
   */
  Drupal.bpsViewInContext.scrollToc = function() {
    console.log( Drupal.bps_media_queries.isCurrentLayout);
    if (typeof Drupal.bps_media_queries.isCurrentLayout === 'undefined' || !Drupal.bps_media_queries.isCurrentLayout('screen-md2-min', '>=')) {
      return;
    }

    var $toc = $(this);
    var $activeItem = $toc.find('.active');
    var $stickyElem = $toc.closest('.is-sticky');
    if ($activeItem.length === 0 || $stickyElem.length === 0 || $stickyElem[0].getBoundingClientRect().top > 0) {
      return;
    }

    $activeItem = $activeItem.first();
    console.log($activeItem);
    var scrollPos = $activeItem.position().top;
    var tocStickyHeaderHeight = $toc.find('.form-type-textfield-filter').height();
    if (tocStickyHeaderHeight !== undefined) {
      scrollPos -= (tocStickyHeaderHeight + 1);
    }
    $stickyElem.animate({
      scrollTop: scrollPos
    }, 200);
  }

  /**
   * Smooth scroll behavior.
   */
  Drupal.bpsViewInContext.smoothScroll = function(event) {
    var eventData = typeof event.data !== 'undefined' ? event.data : {};
    var scrollToc = typeof eventData.scrollToc !== 'undefined' ? eventData.scrollToc : false;
    var context = document;
    var updateHash = typeof eventData.updateHash !== 'undefined' ? eventData.updateHash : true;
    var link = event.currentTarget;

    if (!link || !link.hash || link.hash === 0) {
      return;
    }

    // On-page links
    if (location.pathname.replace(/^\//, '') == link.pathname.replace(/^\//, '') && location.hostname == link.hostname) {
      // Figure out element to scroll to
      var targetID = Drupal.bpsViewInContext.getHash(link);
      var target = Drupal.bpsViewInContext.getTarget(targetID, context);
      if (!target) {
        return;
      }

      // If the target is hidden, attempt to show it.
      if (!target.is(':visible')) {

        // Check if this is a collapse or tab pane.
        if (target.is('.collapse:not(.in)') || target.is('.highwire-tabs__tab:not(.active)') || target.is('.highwire-dialog:not(.in)')) {
          var trigger = $('a[href="#' + targetID + '"],a[data-target="#' + targetID + '"],button[data-target="#' + targetID + '"]', context);
          if (trigger.length > 0) {
            trigger.first().trigger('click');
            $(".nav-tabs .nav-link").removeClass('is-active');
            $(".highwire-tabs .highwire-tabs__tab").removeClass('is-active');
          }
        }

        // Check for collapse or tab parent containers.
        target.parents('.collapse:not(.in), .highwire-tabs__tab:not(.active), .highwire-dialog:not(.in)').each(function(e) {
          var parent = $(this);
          if (parentID = parent.attr('id')) {
            var tab = new bootstrap.Tab('#' + parentID + '-label');
            tab.show();
          }
          $(".nav-tabs .highwire-tabs-links__link").removeClass('is-active');
          $(".highwire-tabs .highwire-tabs__tab").removeClass('is-active');
        });
      }

      // Only prevent default if animation is actually gonna happen
      event.preventDefault();

      // Adjust scoll position based on height of sticky header.
      var stickyHeaderHeight = Drupal.bpsViewInContext.getStickyHeaderHeight(target, context);
      var scrollPos = target.offset().top - (stickyHeaderHeight + 140);

      // Add class & data for focus event scrollTop override.
      target.addClass('smoothscroll-target').data('scrollPos', scrollPos);

      // Scroll to target.
      $('html, body').animate({
        scrollTop: scrollPos
      }, 1000, function() {

        // Callback after animation
        // Must change focus!
        var $target = $(target);
        $target.focus();
        if ($target.is(":focus")) { // Checking if the target was focused
          return false;
        } else {
          $target.attr('tabindex','-1'); // Adding tabindex for elements not focusable
          $target.focus(); // Set focus again
          $target.focusout(function(){ $(this).removeAttr('tabindex'); });
        };
        $target.removeClass('smoothscroll-target').removeData('scrollPos');
      });
    }
  }
}(jQuery, Drupal));