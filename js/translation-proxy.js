
function setLangParam(url, lang) {
  var rgxLang = /([?&]lang=([a-zA-Z-]+))/;
  var href = url;
  if (rgxLang.test(href)) {
    var match = rgxLang.exec(href);
    if (match) {
      href = href.replace(match[1], "")
    }
  }
  if (lang && lang !== "en") {
    if (href.indexOf("?") == -1) {
      href += "?lang=" + lang
    } else {
      href += "&lang=" + lang
    }
  }
  return href
}

function changeLanguage(sel) {
  var lang = sel.options[sel.selectedIndex].value;
  if (lang) {
    var href = setLangParam(window.location.href, lang);
    window.location.replace(href)
  }
}

function setCurrentLanguage() {
  var href = window.location.href;
  var rgx = /[?&]lang=([a-zA-Z-]+)/;
  if (rgx.test(href)) {
    var match = rgx.exec(href);
    if (match) {
      var lang = match[1];
      jQuery('#lang_choices').val(lang);
    }
  }
}

jQuery(document).ready(function() {
  jQuery('#translation-disclaimer-link')
    .mouseover(function() {
      jQuery('#translation-disclaimer').addClass('active');
    })
    .mouseout(function() {
      jQuery('#translation-disclaimer').removeClass('active');
    });
  setCurrentLanguage();
});

