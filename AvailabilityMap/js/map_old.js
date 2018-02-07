var debug = false;
var delta;
var jQ_kbmap;
var jQ_kbmap_wrapper;
var jQ_lb_discount;
var jQ_lb_lot_id;
var jQ_lb_owner;
var jQ_lb_owner_id;
var jQ_lb_owner_module;
var jQ_lb_price;
var jQ_lb_rep;
var jQ_lb_reserved_date;
var jQ_lb_size_acres;
var jQ_lb_size_ft;
var jQ_lb_tour_date;
var jQ_lot_box;
var jQ_lot_box_close;
var jQ_lot_box_header;
var jQ_lot_box_title;
var kbmap;
var kbmap_height;
var kbmap_width;
var kbmap_wrapper;
var kbmap_zoom;
var scale3d;
var scale;
var viewwidth;

$(document).ready(function () {
  /*
   * jQuery Declarations
   */
  jQ_kbmap            = $('#kbmap');
  jQ_kbmap_wrapper    = $('#kbmap-wrapper');
  jQ_lb_discount      = $('#lb-discount');
  jQ_lb_lot_id        = $('#lb-lot_id');
  jQ_lb_owner         = $('#lb-owner');
  jQ_lb_owner_id      = $('#lb-owner_id');
  jQ_lb_owner_module  = $('#lb-owner_module');
  jQ_lb_price         = $('#lb-price');
  jQ_lb_rep           = $('#lb-rep');
  jQ_lb_reserved_date = $('#lb-reserved_date');
  jQ_lb_size_acres    = $('#lb-size_acres');
  jQ_lb_size_ft       = $('#lb-size_ft');
  jQ_lb_tour_date     = $('#lb-tour_date');
  jQ_lot_box          = $('#lot-box');
  jQ_lot_box_close    = $('#lot-box-close');
  jQ_lot_box_header   = $('#lot-box-header');
  jQ_lot_box_title    = $('#lot-box-title');

  kbmap         = document.getElementById('kbmap');
  kbmap_wrapper = document.getElementById('kbmap-wrapper');
  kbmap_width   = kbmap.getAttribute('viewBox').split(' ')[2];
  kbmap_height  = kbmap.getAttribute('viewBox').split(' ')[3];

  $('#turnkey-credit').hide();
  jQ_kbmap_wrapper.css('height', Math.min(780, (window.innerHeight - 100)));
  jQ_kbmap.attr('height', (window.innerHeight - 130));
  jQ_lot_box.draggable({
    handle     : '#lot-box-header',
    containment: 'window'
  });
  //jQ_kbmap.draggable();
  //adjustKBMap('c');

  /*
   * Bind function zoomMapOnScroll to mousewheel scroll event over #kbmap-wrapper
   */
  if (kbmap_wrapper.addEventListener) {
    kbmap_wrapper.addEventListener('mousewheel', zoomMapOnScroll, false);     // IE9, Chrome, Safari, Opera
    kbmap_wrapper.addEventListener('DOMMouseScroll', zoomMapOnScroll, false); // Firefox
  } else {
    kbmap_wrapper.attachEvent('onmousewheel', zoomMapOnScroll);               // IE 6/7/8
  }

  /*
   * Lot Box
   */
  jQ_lot_box_close.click(function () {
    jQ_lot_box.toggleClass('shown').toggleClass('hidden');
  });

  /*
   * Section Buttons
   */
  $('#section-button-a').on('click', function () {
    adjustKBMap('a');
  });

  $('#section-button-b').on('click', function () {
    adjustKBMap('b');
  });

  $('#section-button-c').on('click', function () {
    adjustKBMap('c');
  });

  $('#section-button-full').on('click', function () {
    adjustKBMap('full');
  });

  /*
   * Lot dots and such
   */
  $('circle').each(function () {
    var tempCircle = $(this);
    if (tempCircle.attr('id') != null) {
      tempCircle.click(function () {
        jQ_lot_box_title.html('Lot ' + tempCircle.attr('id') + ' - ' + tempCircle.attr('data-status'));

        if (tempCircle.attr('fill') == 'yellow') {
          jQ_lot_box_header.css({'background': tempCircle.attr('fill'), 'color': '#444'});
          jQ_lot_box_close.css({'background': 'white', 'color': 'black'});
        } else if (tempCircle.attr('fill') != 'white' && tempCircle.attr('fill') != 'yellow') {
          jQ_lot_box_header.css({'background': tempCircle.attr('fill'), 'color': 'white'});
          jQ_lot_box_close.css({'background': 'white', 'color': 'black'});
        } else {
          jQ_lot_box_header.css({'background': 'white', 'color': '#444'});
        }

        jQ_lot_box_title.css('cursor', 'pointer');

        if (tempCircle.attr('data-owner_id') && tempCircle.attr('data-owner_module')) {
          jQ_lb_owner_id.html(tempCircle.attr('data-owner_id'));
          jQ_lb_owner_module.html(tempCircle.attr('data-owner_module'));
          jQ_lb_owner.css({'cursor': 'pointer', 'color': '#00a0a5', 'font-weight': '700'});
        } else {
          jQ_lb_owner_id.html('');
          jQ_lb_owner_module.html('');
          jQ_lb_owner.css({'cursor': 'auto', 'color': '#444', 'font-weight': '400'});
        }

        jQ_lb_price.html(tempCircle.attr('data-price'));
        jQ_lb_discount.html('(' + tempCircle.attr('data-discount') + ')');
        jQ_lb_size_acres.html(tempCircle.attr('data-size_acres') + ' <i>ac</i>');
        jQ_lb_size_ft.html(tempCircle.attr('data-size_ft') + ' <i>sqft</i>');
        jQ_lb_owner.html(tempCircle.attr('data-owner'));
        jQ_lb_tour_date.html(tempCircle.attr('data-tour_date'));
        jQ_lb_rep.html(tempCircle.attr('data-rep'));
        jQ_lb_reserved_date.html(tempCircle.attr('data-reserved_date'));
        jQ_lb_lot_id.html(tempCircle.attr('data-lot_id'));
        !jQ_lot_box.hasClass('shown') ? jQ_lot_box.toggleClass('hidden').toggleClass('shown') : null;
      });
    }
  });

  $('text').each(function () {
    if ($(this).attr("id") != null) {
      $('#' + $(this).attr("id")).substring(1).click();
    }
  });

  jQ_lot_box_title.on('click', function () {
    var url = 'http://srm.discoverbelize.com/index.php?&module=Products&view=Detail&record=' + jQ_lb_lot_id.html();
    $('<a>').attr('href', url).attr('target', '_blank')[0].click();
    url = null;
  });

  jQ_lb_owner.on('click', function () {
    if (jQ_lb_owner.css('cursor') == 'pointer') {
      var url = 'http://srm.discoverbelize.com/index.php?&module=' + jQ_lb_owner_module.html() + '&view=Detail&record=' + jQ_lb_owner_id.html();
      $('<a>').attr('href', url).attr('target', '_blank')[0].click();
      url = null;
    }
  });

  function adjustKBMap(section) {
    viewwidth = jQ_kbmap_wrapper.width();
    switch (section) {
      case 'a':
        debug ? console.log('Section A button clicked!') : null;
        jQ_kbmap_wrapper.scrollLeft(0);
        if (viewwidth > 1600) {
          zoomMapTo(1.1);
          jQ_kbmap.offset({top: -58, left: 257});
        } else if (viewwidth < 1600) {
          zoomMapTo(1.2);
          jQ_kbmap.offset({top: -46, left: 290});
        }
        break;
      case 'b':
        debug ? console.log('Section B button clicked!') : null;
        jQ_kbmap_wrapper.scrollLeft(jQ_kbmap_wrapper.width() / 2);
        if (viewwidth > 1600) {
          zoomMapTo(1.1);
          jQ_kbmap.offset({top: -64, left: -855});
        } else if (viewwidth < 1600) {
          zoomMapTo(1.2);
          jQ_kbmap.offset({top: -58, left: -680});
        }
        break;
      case 'c':
        debug ? console.log('Section C button clicked!') : null;
        kbmap_wrapper.scrollLeft = jQ_kbmap_wrapper.width();
        if (viewwidth > 1600) {
          zoomMapTo(1.55);
          jQ_kbmap.offset({top: 75, left: -2840});
        } else if (viewwidth < 1600) {
          zoomMapTo(1.7);
          jQ_kbmap.offset({top: 82, left: -2500});
        }
        break;
      case 'full':
        debug ? console.log('Full Map button clicked!') : null;
        zoomMapTo(0.55);
        jQ_kbmap.offset({top: 218, left: 38});
        break;
      default:
        debug ? console.log('The function adjustKBMap(section) wants "a", "b", "c", or "full".') : null;
        break;
    }
  }

  function zoomMapTo(zlevel) {
    kbmap_zoom = zlevel;
    scale      = 'scale(' + kbmap_zoom + ')';
    scale3d    = 'scale3d(' + kbmap_zoom + ', ' + kbmap_zoom + ', ' + kbmap_zoom + ')';
    jQ_kbmap.css('-webkit-transform', '');
    jQ_kbmap.css('-webkit-transform', scale3d);
    jQ_kbmap.css('-webkit-transform', scale);
  }

  function zoomMapOnScroll(e) {
    e = window.event || e;
    e.preventDefault();
    delta      = 0.05 * Math.max(-1, Math.min(1, (e.wheelDelta || -e.detail)));
    kbmap_zoom += delta;
    kbmap_zoom = Math.round(kbmap_zoom * 100) / 100;
    kbmap_zoom = Math.max(0, kbmap_zoom);
    zoomMapTo(kbmap_zoom);
  }
});

