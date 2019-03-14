
jQuery(document).ready(function() {
  jQuery('#translation-disclaimer-link')
    .mouseover(function() {
      jQuery('#translation-disclaimer').addClass('active');
    })
    .mouseout(function() {
      jQuery('#translation-disclaimer').removeClass('active');
    });
});
