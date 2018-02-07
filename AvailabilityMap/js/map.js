let jQ_kbmap,
    jQ_kbmap_wrapper,
    jQ_lb_discount,
    jQ_lb_lot_id,
    jQ_lb_owner,
    jQ_lb_owner_id,
    jQ_lb_owner_module,
    jQ_lb_price,
    jQ_lb_rep,
    jQ_lb_rep_id,
    jQ_lb_reserved_date,
    jQ_lb_size_acres,
    jQ_lb_size_ft,
    jQ_lb_tour_date,
    jQ_lot_box,
    jQ_lot_box_close,
    jQ_lot_box_data,
    jQ_lot_box_header,
    jQ_lot_box_title,
    jQ_section_buttons,
    jQ_SRM_linker,
    kbmap_zoom,
    kbmap_zoom_scalar;

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
  jQ_lb_rep_id        = $('#lb-rep_id');
  jQ_lb_reserved_date = $('#lb-reserved_date');
  jQ_lb_size_acres    = $('#lb-size_acres');
  jQ_lb_size_ft       = $('#lb-size_ft');
  jQ_lb_tour_date     = $('#lb-tour_date');
  jQ_lot_box          = $('#lot-box');
  jQ_lot_box_data     = $('#lot-box-data');
  jQ_lot_box_close    = $('#lot-box-close');
  jQ_lot_box_header   = $('#lot-box-header');
  jQ_lot_box_title    = $('#lot-box-title');
  jQ_section_buttons  = $("[id^='section-button-']").not("[id$='wrapper']");
  jQ_SRM_linker       = $('<a>');
  kbmap_zoom_scalar   = 5.0;

  $('#turnkey-credit').hide();
  jQ_kbmap_wrapper.css('height', Math.min(780, window.innerHeight - 100));
  jQ_kbmap.attr('height', window.innerHeight - 130);
  jQ_lot_box.draggable({
    handle     : '#lot-box-header',
    containment: 'window'
  });
  jQ_kbmap.draggable();
  setKBMapView('c');

  /*
   * #kbmap-wrapper: Bind function zoomMapOnScroll to mousewheel scroll event
   */
  if (jQ_kbmap_wrapper[0].addEventListener) {
    jQ_kbmap_wrapper[0].addEventListener('mousewheel', zoomMapOnScroll, false);     // IE9, Chrome, Safari, Opera
    jQ_kbmap_wrapper[0].addEventListener('DOMMouseScroll', zoomMapOnScroll, false); // Firefox
  } else {
    jQ_kbmap_wrapper[0].attachEvent('onmousewheel', zoomMapOnScroll);               // IE 6/7/8
  }

  /*
   * #lot-box-close: Bind anonymous function that hides #lot-box to click event
   */
  jQ_lot_box_close.click(function () {
    jQ_lot_box.removeClass('shown')
              .addClass('hidden');
  });

  /*
   * #section-button-a|b|c|full: Bind anonymous function that calls function setKBMapView() to click event
   */
  jQ_section_buttons.click(function () {
    setKBMapView($(this).attr('id').split('-').pop());
  });

  /*
   * circles: Bind anonymous function that updates #lot-box to click event
   */
  $('circle[id]').click(function () {
    let thisCircle = $(this);

    // #lot-box-title
    jQ_lot_box_title.html(`Lot ${thisCircle.attr('id')} - ${thisCircle.attr('data-status')}`)
                    .css('cursor', 'pointer');

    // #lot-box-header
    // #lot-box-close
    colorLotBox(thisCircle.attr('fill'));

    // #lb-owner
    // #lb-owner_id
    // #lb-owner_module
    jQ_lb_owner.html(thisCircle.attr('data-owner'));
    jQ_lb_owner_id.html(thisCircle.attr('data-owner_id'));
    jQ_lb_owner_module.html(thisCircle.attr('data-owner_module'));
    if (jQ_lb_owner.html() && jQ_lb_owner_id.html() && jQ_lb_owner_module.html()) {
      jQ_lb_owner.css({
        'cursor'     : 'pointer',
        'color'      : '#00a0a5',
        'font-weight': '700'
      });
    } else {
      jQ_lb_owner.css({
        'cursor'     : 'auto',
        'color'      : '#444',
        'font-weight': '400'
      });
    }

    // #lb-rep
    // #lb-rep_id
    jQ_lb_rep.html(thisCircle.attr('data-rep'));
    jQ_lb_rep_id.html(thisCircle.attr('data-rep_id'));
    if (jQ_lb_rep.html() && jQ_lb_rep_id.html()) {
      jQ_lb_rep.css({
        'cursor'     : 'pointer',
        'color'      : '#00a0a5',
        'font-weight': '700'
      });
    } else {
      jQ_lb_rep.css({
        'cursor'     : 'auto',
        'color'      : '#444',
        'font-weight': '400'
      });
    }

    // Fill remaining #lot-box data elements with related [data-*] values
    if (thisCircle.attr('data-price')) {
      jQ_lb_price.html(thisCircle.attr('data-price'));
    } else {
      jQ_lb_price.html('&nbsp;');
    }

    if (thisCircle.attr('data-discount')) {
      jQ_lb_discount.html(`(${thisCircle.attr('data-discount')})`);
    } else {
      jQ_lb_discount.html('&nbsp;');
    }

    if (thisCircle.attr('data-size_acres')) {
      jQ_lb_size_acres.html(`${thisCircle.attr('data-size_acres')} <i>ac</i>`);
    } else {
      jQ_lb_size_acres.html('<i>ac</i>');
    }

    if (thisCircle.attr('data-size_ft')) {
      jQ_lb_size_ft.html(`${thisCircle.attr('data-size_ft')} <i>sqft</i>`);
    } else {
      jQ_lb_size_ft.html('&nbsp;<i>sqft</i>');
    }

    if (thisCircle.attr('data-tour_date')) {
      jQ_lb_tour_date.html(thisCircle.attr('data-tour_date'));
    } else {
      jQ_lb_tour_date.html('&nbsp;');
    }

    if (thisCircle.attr('data-reserved_date')) {
      jQ_lb_reserved_date.html(thisCircle.attr('data-reserved_date'));
    } else {
      jQ_lb_reserved_date.html('&nbsp;');
    }

    jQ_lb_lot_id.html(thisCircle.attr('data-lot_id'));

    // #lot-box: Show #lot-box if not currently shown
    jQ_lot_box.removeClass('hidden')
              .addClass('shown');
  });

  /*
   * #lot-box: Linking of data elements
   */
  setLotBoxLinks();

  function colorLotBox(color) {
    let header_css;

    switch (color) {
      case 'grey':
      case 'yellow':
        header_css = {
          'background-color': color,
          'color'           : '#444'
        };
        break;
      case 'green':
      case 'red':
        header_css = {
          'background-color': color,
          'color'           : 'white'
        };
        break;
      default:
        header_css = {
          'background-color': 'white',
          'color'           : '#444'
        };
        break;
    }

    jQ_lot_box_header.css(header_css);
  }

  function setKBMapView(section) {
    let kbmap_offset,
        scrollMarker,
        viewwidth = jQ_kbmap_wrapper.width(),
        zlevel;

    switch (section) {
      case 'a':
        scrollMarker = 0;
        if (viewwidth > 1600) {
          zlevel       = 1.1;
          kbmap_offset = {
            'top' : -58,
            'left': 257
          };
        } else {
          zlevel       = 1.2;
          kbmap_offset = {
            'top' : -46,
            'left': 290
          };
        }
        break;
      case 'b':
        scrollMarker = viewwidth / 2;
        if (viewwidth > 1600) {
          zlevel       = 1.1;
          kbmap_offset = {
            'top' : -64,
            'left': -855
          };
        } else {
          zlevel       = 1.2;
          kbmap_offset = {
            'top' : -58,
            'left': -680
          };
        }
        break;
      case 'c':
        scrollMarker = viewwidth;
        if (viewwidth > 1600) {
          zlevel       = 1.55;
          kbmap_offset = {
            'top' : 75,
            'left': -2840
          };
        } else {
          zlevel       = 1.7;
          kbmap_offset = {
            'top' : 82,
            'left': -2500
          };
        }
        break;
      case 'full':
      case 'default':
        scrollMarker = 0;
        zlevel       = 0.55;
        kbmap_offset = {
          'top' : 218,
          'left': 38
        };
        break;
    }

    jQ_kbmap_wrapper[0].scrollLeft = scrollMarker;
    zoomMapTo(zlevel);
    jQ_kbmap.offset(kbmap_offset);
  }

  function setLotBoxLinks() {
    // #lot-box-title
    jQ_lot_box_title.click(function () {
      jQ_SRM_linker.attr({
        'href'  : `http://srm.discoverbelize.com/index.php?&module=Products&view=Detail&record=${jQ_lb_lot_id.html()}`,
        'target': '_blank'
      })[0]
        .click();
    });

    // #lb-owner
    jQ_lb_owner.click(function () {
      if (jQ_lb_owner.css('cursor') === 'pointer') {
        jQ_SRM_linker.attr({
          'href'  : `http://srm.discoverbelize.com/index.php?&module=${jQ_lb_owner_module.html()}&view=Detail&record=${jQ_lb_owner_id.html()}`,
          'target': '_blank'
        })[0]
          .click();
      }
    });

    // #lb-rep
    jQ_lb_rep.click(function () {
      if (jQ_lb_rep.css('cursor') === 'pointer') {
        jQ_SRM_linker.attr({
          'href'  : `http://srm.discoverbelize.com/index.php?&module=Users&parent=Settings&view=Detail&record=${jQ_lb_rep_id.html()}`,
          'target': '_blank'
        })[0]
          .click();
      }
    });
  }

  function zoomMapOnScroll(e) {
    e = window.event || e;
    e.preventDefault();
    zoomMapTo(Math.max(0.45, Math.round((kbmap_zoom + kbmap_zoom_scalar * 0.05 * Math.max(-1, Math.min(1, (e.wheelDelta || -e.detail)))) * 100) / 100));
  }

  function zoomMapTo(zlevel) {
    kbmap_zoom = zlevel;

    jQ_kbmap.css('-webkit-transform', '')
            .css('-webkit-transform', `scale3d(${kbmap_zoom}, ${kbmap_zoom}, ${kbmap_zoom})`)
            .css('-webkit-transform', `scale(${kbmap_zoom})`);
  }
  
  function addLotBoxDatapoint(datapoint) {
    //jQ_lot_box_data.add();
  }
  
  function clearLotBoxDatapoints() {
    jQ_lot_box_data.empty();
  }
});

