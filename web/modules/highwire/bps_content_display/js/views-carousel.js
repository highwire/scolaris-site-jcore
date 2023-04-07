/**
 * @file
 * Set us up the carousel.
 */

(function ($) {

    'use strict';

    Drupal.mhe_content_display_views_carousel = Drupal.mhe_content_display_views_carousel || {};
    Drupal.behaviors.mheViewsCarousel = {
        attach: function (context, settings) {
            $('.views-view-carousel', context).once('mhe-content-display-views-carousel').each(Drupal.mhe_content_display_views_carousel.initCarousel);
        }
    };

    /**
     * Initialize carousel.
     */
    Drupal.mhe_content_display_views_carousel.initCarousel = function () {
        var $carousel = $(this);
        var swiper = new Swiper($carousel.find('.swiper-container'), {
            'slidesPerView': 'auto',
            'uniqueNavElements': false,
            'navigation': {
                'nextEl': $carousel.find('.views-view-carousel__button-next'),
                'prevEl': $carousel.find('.views-view-carousel__button-prev'),
            },
        });
    };
}(jQuery));
