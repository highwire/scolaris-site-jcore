jQuery(document).ready(function ($) {
  // ToolTip
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
  });

  // Popover
  var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
  var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
    trigger: 'focus'
    return new bootstrap.Popover(popoverTriggerEl)
  });

  // Scroll to Top
  $(".scroll-top-content a").on('click', function (event) {
    if (this.hash !== "") {
      event.preventDefault();
      var hash = this.hash;
      $('html, body').animate({ scrollTop: $(hash).offset().top }, 800, function () { window.location.hash = hash; });
    }
  });

  // Toggle Description
  $('.show-click-toggle').on('click', function (e) {
    e.preventDefault();
    $(this).toggleClass('active');
    if ($(this).is('.active')) {
      $(this).text($(this).text().replace("Show", "Hide"));
    } else {
      $(this).text($(this).text().replace("Hide","Show"));
    }
    $(this).parent().find('.show-content-toggle').slideToggle();
  });

  // Slick Slider
  $('.slider').on('init', function () { });
  var count=5;
  if($(".slick-slider").hasClass("six")){
      count=6;
  }		
  var createSlick = ()=>{
      let slider = $(".slider");
      slider.not('.slick-initialized').slick({
          autoplay: false,
          infinite: false,
          dots: false,
          autoplaySpeed: 3000,
          slidesToShow: count,
          slidesToScroll: 1,
          responsive: [{
              breakpoint: 1200,
              settings: {
                  slidesToShow: 4,
                  slidesToScroll: 4,
                  adaptiveHeight: true,
              }
          }, {
              breakpoint: 900,
              settings: {
                  slidesToShow: 3,
                  slidesToScroll: 3,
              }
          }, {
              breakpoint: 600,
              settings: {
                  slidesToShow: 2,
                  slidesToScroll: 2,
              }
          }, {
              breakpoint: 450,
              settings: {
                  slidesToShow: 1,
                  slidesToScroll: 1,
              }
          }]
      });	
  }
  createSlick();
  $(window).on( 'resize orientationchange', createSlick );
  
  // Search Result Date Range Toggle
  $('.facets-widget-bps-search-facets-daterange .facet-modal-click').on('click', function (event) {
    event.preventDefault();
    $(this).toggleClass('open');
    $(this).next('.facets-date-range').slideToggle();
  });

  // Disable Logout after single Click in MyAccount Dropdown to prevent multiple calls to sp
  $("ul.menu--user-account li:last-child a").on("click", function(event) {
    if ($(this).hasClass('clicked')) {
      $(this).attr('disabled','disabled');
      $(this).off("click").attr('href', "javascript: void(0);");
      return false;
    } else {
      $(this).addClass('clicked');
      $(this).attr('disabled','disabled');
      return true;
    }
    return false;
  });

  // Active parent item when Child item gets active - My Account
  $('.menu--account a.dropdown-item').each(function () {
    if ($(this).is('.is-active')) {
      $('.menu--account > .dropdown').addClass('active');
    }
  });

  // When cart hover hide remain popover
  $('.foxycart-cart').hover(function () {
    $('.dropdown .dropdown-toggle').removeClass('show');
    $('.dropdown .dropdown-menu').removeClass('show');
  });

  // Search result popup filter wrap item
  $('.bps-checkbox-multiple-facet').each(function() {
    $(this).find('li.facet-item').wrapAll("<li><ul class='bps-checkbox-multiple-group' /></li>");
  });

});
