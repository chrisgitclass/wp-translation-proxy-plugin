<?php

class TranslationProxy
{
  /**
   * POST, PAGE
   *
   *  - add new: ### NO NEED
   *      save_post auto-draft
   *      edit_post draft
   *      save_post draft
   *
   *  - update draft: ### NO NEED
   *      edit_post (draft)
   *      save_post (draft) => XXX handled too many
   *
   *  - publish: ### NO NEED
   *      edit_post publish
   *      save_post inherit (revision)
   *      save_post publish
   *
   *  - update published: OOO
   *      edit_post (publish) OOO
   *      save_post (publish) => XXX handled too many
   *
   *  - publish to private: OOO
   *  - publik to password protected:
   *      transition_post_status (publish > private) OOO
   *      edit_post (private) ***
   *      save_post (publish) => XXX handled too many
   *
   *  - publish to draft: OOO
   *  - publish to pending: OOO
   *      transition_post_status (publish > draft, pending) OOO
   *      edit_post (draft, pending) => XXX ?p=nnnn
   *      save_post (publish) => XXX handled too many
   *
   *  - trash: OOO
   *      wp_trash_post ***
   *      transition_post_status (publish > trash) OOO
   *      edit_post => XXX __trashed url
   *      save_post => XXX __trashed url
   *
   *  - delete: ### NO NEED already trashed
   *      wp_trash_post OOO
   *      transition_post_status (publish > trash)
   *      edit_post => XXX __trashed url
   *      save_post => XXX __trashed url
   *      delete_post => XXX delete trashed posts and revisions
   *
   *
   * ATTACHMENT
   *
   *  - add new: ### NO NEED
   *      update_attached_file
   *      add_attachment path to file
   *
   *  - edit: OOO
   *      edit_attachment OOO attachment post page
   *
   *  - delete: OOO
   *      delete_attachment OOO path to file, attachment post page
   *      delete_post *** attachment post page
   *
   *  - replace media: OOO
   *      update_attached_file
   *
   *
   * ENTIRE SITE
   *
   *  - switch:
   *      swith_theme OOO
   *
   *  - update menu: OOO
   *      wp_update_nav_menu  : menu_id and menu_data
   *      transition_post_status (publish > publish) : on all the menu items
   *      edit_post (publish) : on all the menu items
   *      save_post (publish) : on all the menu items
   *      wp_update_nav_menu  : only menu_id OOO
   */

  public static $debug = false;     /* Enable debug message */
  public static $enabled = true;   /* Enable cache purge request */

  public static function on_edit_post($post_id, $post) {
    $status = $post->post_status;
    if ($status == 'publish' || $status == 'private') {
      $url = get_permalink($post_id);
      self::purge_url($url, "EDIT_POST: $status $url");
    }
  }

  public static function on_transition_post_status($new_status, $old_status, $post) {
    self::dbg("TRANSITION_POST_STATUS $old_status => $new_status");
    if ($old_status == 'publish') {
      if ($new_status != 'publish') {
        $url = get_permalink($post->id);
        self::purge_url($url, "TRANSITION_POST_STATUS: $old_status => $new_status $url");
      }
    }
  }

  public static function on_edit_attachment($post_id) {
    $url = wp_get_attachment_url($post_id);
    $perma = get_permalink($post_id);
    self::purge_url($url, "EDIT_ATTACHMENT: $post_id $perma");
  }

  public static function on_delete_attachment($post_id) {
    $url = wp_get_attachment_url($post_id);
    $perma = get_permalink($post_id);
    self::purge_url($url, "DELETE_ATTACHMENT: $url");
    self::purge_url($perma, "DELETE_ATTACHMENT: $perma");
  }

  public static function on_update_attached_file($file, $post_id) {
    $url = wp_get_attachment_url($post_id);
    $perma = get_permalink($post_id);
    self::purge_url($url, "UPDATE_ATTCHED_FILE: $url");
    self::purge_url($perma, "UPDATE_ATTCHED_FILE: $perma");
    return $file;
  }

  public static function on_switch_theme() {
    self::purge_all("SWITCH_THEME");
  }

  public static function on_wp_update_nav_menu($menu_id, $menu_data = null) {
    if (is_null($menu_data)) {
      self::purge_all("WP_UPDATE_NAV_MENU");
    }
  }

  private static function set_purge_hooks() {
    self::dbg('SET-HOOKS');
    add_action('edit_post', 'TranslationProxy::on_edit_post', 10, 2);
    add_action('transition_post_status', 'TranslationProxy::on_transition_post_status', 10, 3);
    add_action('edit_attachment', 'TranslationProxy::on_edit_attachment', 10, 1);
    add_action('delete_attachment', 'TranslationProxy::on_delete_attachment', 10, 1);
    add_filter('update_attached_file', 'TranslationProxy::on_update_attached_file', 10, 2);
    add_action('switch_theme', 'TranslationProxy::on_switch_theme');
    add_action('wp_update_nav_menu', 'TranslationProxy::on_wp_update_nav_menu', 10, 2);
  }

  public static function load_scripts() {
    wp_enqueue_style('translation-proxy', plugin_dir_url(__FILE__) . 'css/translation-proxy.css');
    wp_enqueue_script('jquery');
    wp_enqueue_script('translation-proxy', plugin_dir_url(__FILE__) . 'js/translation-proxy.js');
  }

  public static function inject_lang_selector($buffer) {
    $opts = self::get_plugin_options();
    $page_id = strval(get_queried_object_id());
    if ($opts['language_select_enabled'] !== '1') return $buffer;
    if ($opts['language_select_for_entire_site'] === '2') {
      $allowed_ids = explode(',', $opts['language_select_allowed_ids']);
      if (!in_array($page_id, $allowed_ids)) {
        return $buffer;
      }
    }
    $pos = strpos($buffer, self::LOCATION);
    $text = substr_replace($buffer, self::LANG_SELECT, $pos, 0);
    return $text;
  }

  public static function on_the_title($title) {
    ob_end_flush();
    return $title;
  }

  public static function on_wp_head() {
    ob_start('TranslationProxy::inject_lang_selector');
  }

  public static function set_inject_hooks() {
    self::dbg('SET INJECT HOOKS');
    add_action('wp_head', 'TranslationProxy::on_wp_head', 10);
    add_filter('the_title', 'TranslationProxy::on_the_title', 10, 1);
  }

  public static function generate_notices($params) {
    $msg = '';
    $class = 'is-dismissible notice';
    if (isset($params['errors'])) {
      $class .= ' notice-error';
      foreach($params['errors'] as $e) {
        $msg .= self::notice($e, $class);
      }
    }
    if (isset($params['success'])) {
      $class .= ' notice-success';
      if ($params['success'] === 'purge-all') {
        $msg .= 'Purge All request was sent to the proxy server.';
      }
      if ($params['success'] === 'save-settings') {
        $msg .= 'Settings were successfully updated.';
      }
    }
    if (empty($msg)) return '';
    return self::notice($msg, $class);
  }

  public static function notice($msg, $class) {
    ?>
    <div class="<?php echo $class; ?>">
      <p><strong><?php echo $msg; ?></strong></p>
    </div>
    <?php
  }

  public static function setup_admin() {
    add_menu_page('Translation Proxy Settings', 'Translation Proxy', 'manage_options', 'translation-proxy-settings', 'TranslationProxy::admin_page');
    add_options_page('Translation Proxy Settings', 'Translation Proxy', 'manage_options', 'translation-proxy-settings', 'TranslationProxy::admin_page');
  }

  private static function get_admin_page_options() {
    $opts = self::get_plugin_options();
    $r = array(
      'checked' => '',
      'entire_site' => '1',
      'ids' => ''
    );
    if (isset($opts['language_select_enabled']) && $opts['language_select_enabled'] === '1') {
      $r['checked'] = 'checked';
    }
    if (isset($opts['language_select_for_entire_site']) && $opts['language_select_for_entire_site'] === '2') {
      $r['entire_site'] = '2';
    }
    if (isset($opts['language_select_allowed_ids'])) {
      preg_match('/^\d+(,\d+)*$/', $opts['language_select_allowed_ids'], $matches);
      if ($matches) {
        $r['ids'] = $opts['language_select_allowed_ids'];
      }
    }
    return $r;
  }

  public static function admin_page() {
    $r = self::get_admin_page_options();
    ?>
    <div id="translation_proxy_admin_panel" class="wrap">
      <h1>Translation Proxy Settings</h1>
      <?php echo self::generate_notices($_GET); ?>
      <form method="post" action="admin-post.php">

       <input type="hidden" name="action" value="translation_proxy_purge_all" />
       <?php wp_nonce_field( 'translation_proxy_purge_all' ); ?>

      <h2>Cache</h2>
      <p class="submit">
        <input type="submit" value="Purge All" class="button-primary"/>
      </p>
      </form>

      <form method="post" action="admin-post.php">

       <input type="hidden" name="action" value="translation_proxy_update_settings" />
       <?php wp_nonce_field( 'translation_proxy_update_settings' ); ?>

      <h2>Language Select Dropdown</h2>
      <p>
        <label for="language_select_enabled">Enabled</label>
        <input type="checkbox" id="language_select_enabled" name="translation_proxy_settings[language_select_enabled]" value="1" <?php echo $r['checked']; ?>>
      </p>
      <p>
        <input type="radio" id="language_select_for_entire_site1" name="translation_proxy_settings[language_select_for_entire_site]" value="1" <?php echo ($r['entire_site'] === '1') ? 'checked' : ''; ?>>
        <label for="language_select_for_entire_site">Entire Site</label>
      </p>
      <p>
        <input type="radio" id="language_select_for_entire_site2" name="translation_proxy_settings[language_select_for_entire_site]" value="2" <?php echo ($r['entire_site'] === '2') ? 'checked' : ''; ?>>
        <label for="language_select_for_entire_site">Selected Pages</label>
      </p>
      <p>
        <label for="language_select_allowed_ids">Selected Page IDs (comma separated, no spaces)</label>
        <input type="text" id="language_select_allowed_ids" name="translation_proxy_settings[language_select_allowed_ids]" value="<?php echo $r['ids']; ?>">
      </p>
      <p class="submit">
        <input type="submit" value="Save Configuration" class="button-primary"/>
      </p>
      </form>
      <script type="text/javascript">
        function toggleLangSelectAllowedIdsInput(val) {
          if (val === '2') {
            jQuery('#language_select_allowed_ids').prop('readonly', false);
          } else {
            jQuery('#language_select_allowed_ids').prop('readonly', true);
          }
        }

        jQuery(document).ready(function() {
          var radio = jQuery("input[type='radio'][name='translation_proxy_settings[language_select_for_entire_site]']:checked")
          toggleLangSelectAllowedIdsInput(radio.val());
        });

        jQuery("input[type='radio'][name='translation_proxy_settings[language_select_for_entire_site]']")
          .click(function() {
            toggleLangSelectAllowedIdsInput(this.value);
          });
      </script>
    </div>
    <?php
  }

  public static function handle_purge_all() {
    //if (!current_user_can('manage_options')) {
    if (!current_user_can('manage_options') || wp_doing_ajax()) {
      wp_die('Not allowed');
    }
    check_admin_referer('translation_proxy_purge_all');

    $r = self::purge_all('HANDLE PURGE ALL');

    $url = add_query_arg(
      array(
        'page' => 'translation-proxy-settings',
        'success' => 'purge-all',
        'http_code' => $r ),
      admin_url('options-general.php'));
    self::dbg("REDIRECTING TO $url");

    wp_redirect($url);
    exit;
  }

  public static function handle_update_settings() {
    if (!current_user_can('manage_options') || wp_doing_ajax()) {
      wp_die('Not allowed');
    }
    check_admin_referer('translation_proxy_update_settings');

    $url = add_query_arg(array('page' => 'translation-proxy-settings'),
      admin_url('options-general.php'));
    $opts = self::validate_post($_POST);

    if (!isset($opts['errors'])) {
      self::dbg($opts);
      self::save_plugin_options($opts);
      $url .= '&success=save-settings';
    } else {
      foreach($opts['errors'] as $e) {
        $url .= "&errors[]=$e";
      }
    }

    self::dbg("REDIRECTING TO $url");

    wp_redirect($url);
    exit;
  }

  private static function validate_post($post) {
    $r = array_merge(array(), $post);
    $errors = array();

    if (!isset($r['translation_proxy_settings'])) {
      array_push($errors, 'Invalid%20Post%20Data');
      $r['errors'] = $errors;
      return $r;
    }

    $r = $r['translation_proxy_settings'];
    if (isset($r['language_select_enabled']) && $r['language_select_enabled'] !== '1') {
      array_push($errors, 'Enabled%20has%20an%20invalid%20value%2E');
    }
    if (isset($r['language_select_for_entire_site'])) {
      if ($r['language_select_for_entire_site'] !== '1' && $r['language_select_for_entire_site'] !== '2') {
        array_push($errors, 'Entire%20Site%2FSelected%20Pages%20has%20an%20invalid%20value%2E');
      }
    } else {
      array_push($errors, 'Entire%20Site%2FSelected%20Pages%20must%20be%20provided%2E');
    }
    preg_match('/^\d*(,\d+)*$/', $r['language_select_allowed_ids'], $matches);
    if (!$matches) {
      array_push($errors, 'Selected%20Page%20IDs%20must%20be%20comma%20separated%20numbers%2E');
    }
    if (!empty($errors)) {
      $r['errors'] = $errors;
    }
    return $r;
  }

  private static function set_setting_controller() {
    add_action('admin_post_translation_proxy_update_settings', 'TranslationProxy::handle_update_settings');
  }

  private static function set_purge_request_controller() {
    add_action('admin_post_translation_proxy_purge_all', 'TranslationProxy::handle_purge_all');
  }

  public static function initialize() {
    self::set_setting_controller();
    self::set_purge_request_controller();
    self::set_purge_hooks();
  }

  public static function default_plugin_options() {
    return array(
      //'language_select_enabled' => '0',
      'language_select_for_entire_site' => '2',
      'language_select_allowed_ids' => ''
    );
  }

  public static function create_plugin_options() {
    $opts = self::get_plugin_options();
    if ($opts) {
      return;
    }
    $opts = self::default_plugin_options();
    add_option('translation_proxy_options', $opts, '', 'yes');
  }

  public static function get_plugin_options() {
    return get_option('translation_proxy_options');
  }

  public static function save_plugin_options($opts) {
    update_option('translation_proxy_options', $opts);
  }

  public static function delete_plugin_options() {
    delete_option('translation_proxy_options');
  }

  public static function activate($network_wide) {
    global $wpdb;

    if (is_multisite()) {
      if ($network_wide) {
        self::dbg('TranslationProxy Network Activated');
        $original_id = get_current_blog_id();
        self::dbg("Original Blog: $original_id");
        //$blogs = $wpdb->get_results("SELECT blog_id FROM {$wpdb->blogs} WHERE site_id = '{$wpdb->siteid}' AND spam = '0' AND deleted = '0' AND archived = '0'");
        $blogs = $wpdb->get_results("SELECT blog_id FROM {$wpdb->blogs} WHERE site_id = '{$wpdb->siteid}'");
        foreach($blogs as $b) {
          self::dbg("Switch to Blog: " . $b->blog_id);
          switch_to_blog($b->blog_id);
          self::create_plugin_options();
        }
        switch_to_blog($original_id);
      } else {
        wp_die('Network Activation Only.');
      }
    } else {
      self::dbg('TranslationProxy Got Activated!');
      self::create_plugin_options();
    }
  }

  public static function deactivate() {
    global $wpdb;

    if (is_multisite()) {
      self::dbg('TranslationProxy Network Activated');
      $original_id = get_current_blog_id();
      $blogs = $wpdb->get_results("SELECT blog_id, path FROM {$wpdb->blogs} WHERE site_id = '{$wpdb->siteid}'");
      foreach($blogs as $b) {
        switch_to_blog($b->blog_id);
        self::dbg("Blog ID: " . $b->blog_id .", PATH: " . $b->path);
        $opts = self::get_plugin_options();
        self::dbg($opts);
      }
      switch_to_blog($original_id);
    } else {
      $site = $wpdb->get_results("SELECT domain FROM {$wpdb->site}");
      self::dbg('SITE: ' . $site->domain);
      $opts = self::get_plugin_options();
      self::dbg($opts);
    }
    self::dbg('TranslationProxy Got Deactivated!');
  }

  public static function uninstall() {
    global $wpdb;

    if (is_multisite()) {
      $original_id = get_current_blog_id();
      $blogs = $wpdb->get_results("SELECT blog_id FROM {$wpdb->blogs} WHERE site_id = '{$wpdb->siteid}'");
      foreach($blogs as $b) {
        switch_to_blog($b->blog_id);
        self::delete_plugin_options();
      }
      switch_to_blog($original_id);
      self::dbg('TranslationProxy Got Uninstalled!');
    } else {
      self::delete_plugin_options();
    }
    self::dbg('TranslationProxy Got Uninstalled!');
  }

  private static function purge_all($msg = null) {
    $url = home_url() . '/purge-proxy-cache?page=all';
    if (!is_null($msg)) self::dbg($msg);
    self::dbg("PURGEALL: $url");

    $headers = array(
      'X-Purge-Method' => 'flushall',
    );
    return self::send_purge_request($url, $headers);
  }

  private static function purge_url($url, $msg = null) {
    if (!is_null($msg)) self::dbg($msg);
    self::dbg("PURGE: $url");

    $headers = array(
      'X-Purge-Method' => 'default',
      'X-Purge-Target' => $url
    );
    return self::send_purge_request($url, $headers);
  }

  private static function send_purge_request($url, $headers) {
    if (!self::$enabled) return null;

    $r = wp_remote_request($url, array(
        'method' => 'PURGE',
        'headers' => $headers,
        'sslverify' => false
      ));
    if (is_wp_error($r)) {
      $err = $r->get_error_message();
      self::dbg('ERROR');
      self::dbg($err);
    } else {
      self::dbg('RESPONSE: ' . $r['response']['code'] . $r['response']['message']);
    }
    return $r;
  }

  /**
   * Add the followings to wp-config.php
      define('WP_DEBUG', true);
      define('WP_DEBUG_DISPLAY', false);
      define('WP_DEBUG_LOG', true);
   */
  static function dbg($msg) {
    if (!self::$debug) return;
    if (is_array($msg) || is_object($msg)) {
      error_log(print_r($msg, true));
    } else {
      error_log($msg);
    }
  }

	const LOCATION =	'<div class="notmobile statewide-banner-right">';

  const LANG_SELECT =<<<EOT
    <div id="translation-selector-wrapper" class="notmobile statewide-banner-left">
      <div id="translation-selector">
        <label for="lang_choices" style="display:none;">Select the language to display this page</label>
				<select id="lang_choices" onchange="changeLanguage(this)" aria-labelledby="select the language to display this page">
				  <option value="" selected>Select Language</option>
          <option id="langopt-af" value="af">Afrikaans (Afrikaans)</option>
          <option id="langopt-sq" value="sq">Albanian (shqiptar)</option>
          <option id="langopt-am" value="am">Amharic (አማርኛ)</option>
          <option id="langopt-ar" value="ar">Arabic (عربى)</option>
          <option id="langopt-hy" value="hy">Armenian (հայերեն)</option>
          <option id="langopt-az" value="az">Azerbaijani (Azərbaycan)</option>
          <option id="langopt-eu" value="eu">Basque (Euskal)</option>
          <option id="langopt-be" value="be">Belarusian (беларускі)</option>
          <option id="langopt-bn" value="bn">Bengali (বাঙালি)</option>
          <option id="langopt-bs" value="bs">Bosnian (Bosanski)</option>
          <option id="langopt-bg" value="bg">Bulgarian (български)</option>
          <option id="langopt-ca" value="ca">Catalan (Català)</option>
          <option id="langopt-ceb" value="ceb">Cebuano (Cebuano)</option>
          <option id="langopt-zh-CN" value="zh-CN">Chinese (Simplified) (中文(简体))</option>
          <option id="langopt-zh-TW" value="zh-TW">Chinese (Traditional) (中文(繁體))</option>
          <option id="langopt-co" value="co">Corsican (Corsu)</option>
          <option id="langopt-hr" value="hr">Croatian (Hrvatski)</option>
          <option id="langopt-cs" value="cs">Czech (čeština)</option>
          <option id="langopt-da" value="da">Danish (dansk)</option>
          <option id="langopt-nl" value="nl">Dutch (Nederlands)</option>
          <option id="langopt-en" value="en">English</option>
          <option id="langopt-eo" value="eo">Esperanto (Esperanto)</option>
          <option id="langopt-et" value="et">Estonian (Eesti keel)</option>
          <option id="langopt-fi" value="fi">Finnish (Suomalainen)</option>
          <option id="langopt-fr" value="fr">French (français)</option>
          <option id="langopt-fy" value="fy">Frisian (Frysk)</option>
          <option id="langopt-gl" value="gl">Galician (Galego)</option>
          <option id="langopt-ka" value="ka">Georgian (ქართული)</option>
          <option id="langopt-de" value="de">German (Deutsche)</option>
          <option id="langopt-el" value="el">Greek (Ελληνικά)</option>
          <option id="langopt-gu" value="gu">Gujarati (ગુજરાતી)</option>
          <option id="langopt-ht" value="ht">Haitian Creole (Kreyòl Ayisyen)</option>
          <option id="langopt-ha" value="ha">Hausa (Hausa)</option>
          <option id="langopt-haw" value="haw">Hawaiian (Ōlelo Hawaiʻi)</option>
          <option id="langopt-he" value="he">Hebrew (עברית)</option>
          <option id="langopt-hi" value="hi">Hindi (हिंदी)</option>
          <option id="langopt-hmn" value="hmn">Hmong (Hmoob)</option>
          <option id="langopt-hu" value="hu">Hungarian (Magyar)</option>
          <option id="langopt-is" value="is">Icelandic (Íslensku)</option>
          <option id="langopt-ig" value="ig">Igbo (Igbo)</option>
          <option id="langopt-id" value="id">Indonesian (bahasa Indonesia)</option>
          <option id="langopt-ga" value="ga">Irish (Gaeilge)</option>
          <option id="langopt-it" value="it">Italian (italiano)</option>
          <option id="langopt-ja" value="ja">Japanese (日本語)</option>
          <option id="langopt-jw" value="jw">Javanese (Jawa)</option>
          <option id="langopt-kn" value="kn">Kannada (ಕನ್ನಡ)</option>
          <option id="langopt-kk" value="kk">Kazakh (Қазақша)</option>
          <option id="langopt-km" value="km">Khmer (ភាសាខ្មែរ)</option>
          <option id="langopt-ko" value="ko">Korean (한국어)</option>
          <option id="langopt-ku" value="ku">Kurdish (Kurdî)</option>
          <option id="langopt-ky" value="ky">Kyrgyz (Кыргызча)</option>
          <option id="langopt-lo" value="lo">Lao (ລາວ)</option>
          <option id="langopt-la" value="la">Latin (Latine)</option>
          <option id="langopt-lv" value="lv">Latvian (Latviešu valoda)</option>
          <option id="langopt-lt" value="lt">Lithuanian (Lietuvių)</option>
          <option id="langopt-lb" value="lb">Luxembourgish (Lëtzebuergesch)</option>
          <option id="langopt-mk" value="mk">Macedonian (Македонски)</option>
          <option id="langopt-mg" value="mg">Malagasy (Malagasy)</option>
          <option id="langopt-ms" value="ms">Malay (Melayu)</option>
          <option id="langopt-ml" value="ml">Malayalam (മലയാളം)</option>
          <option id="langopt-mt" value="mt">Maltese (Malti)</option>
          <option id="langopt-mi" value="mi">Maori (Maori)</option>
          <option id="langopt-mr" value="mr">Marathi (मराठी)</option>
          <option id="langopt-mn" value="mn">Mongolian (Монгол хэл дээр)</option>
          <option id="langopt-my" value="my">Myanmar (Burmese) (မြန်မာ (ဗမာ))</option>
          <option id="langopt-ne" value="ne">Nepali (नेपाली)</option>
          <option id="langopt-no" value="no">Norwegian (norsk)</option>
          <option id="langopt-ny" value="ny">Nyanja (Chichewa)</option>
          <option id="langopt-ps" value="ps">Pashto (پښتو)</option>
          <option id="langopt-fa" value="fa">Persian (فارسی)</option>
          <option id="langopt-pl" value="pl">Polish (Polskie)</option>
          <option id="langopt-pt" value="pt">Portuguese (Portugal, Brazil) (Português)</option>
          <option id="langopt-pa" value="pa">Punjabi (ਪੰਜਾਬੀ)</option>
          <option id="langopt-ro" value="ro">Romanian (Română)</option>
          <option id="langopt-ru" value="ru">Russian (русский)</option>
          <option id="langopt-sm" value="sm">Samoan (Samoa)</option>
          <option id="langopt-gd" value="gd">Scots Gaelic (Gàidhlig na h-Alba)</option>
          <option id="langopt-sr" value="sr">Serbian (Српски)</option>
          <option id="langopt-st" value="st">Sesotho (Sesotho)</option>
          <option id="langopt-sn" value="sn">Shona (Shona)</option>
          <option id="langopt-sd" value="sd">Sindhi (سنڌي)</option>
          <option id="langopt-si" value="si">Sinhala (Sinhalese) (සිංහල)</option>
          <option id="langopt-sk" value="sk">Slovak (slovenský)</option>
          <option id="langopt-sl" value="sl">Slovenian (Slovenščina)</option>
          <option id="langopt-so" value="so">Somali (Somali)</option>
          <option id="langopt-es" value="es">Spanish (Español)</option>
          <option id="langopt-su" value="su">Sundanese (Sunda)</option>
          <option id="langopt-sw" value="sw">Swahili (Kiswahili)</option>
          <option id="langopt-sv" value="sv">Swedish (svenska)</option>
          <option id="langopt-tl" value="tl">Tagalog (Filipino)</option>
          <option id="langopt-tg" value="tg">Tajik (Тоҷикӣ)</option>
          <option id="langopt-ta" value="ta">Tamil (தமிழ்)</option>
          <option id="langopt-te" value="te">Telugu (తెలుగు)</option>
          <option id="langopt-th" value="th">Thai (ไทย)</option>
          <option id="langopt-tr" value="tr">Turkish (Türk)</option>
          <option id="langopt-uk" value="uk">Ukrainian (Українська)</option>
          <option id="langopt-ur" value="ur">Urdu (اردو)</option>
          <option id="langopt-uz" value="uz">Uzbek (O'zbek)</option>
          <option id="langopt-vi" value="vi">Vietnamese (Tiếng Việt)</option>
          <option id="langopt-cy" value="cy">Welsh (Cymraeg)</option>
          <option id="langopt-xh" value="xh">Xhosa (isiXhosa)</option>
          <option id="langopt-yi" value="yi">Yiddish (ייִדיש שפּראַך)</option>
          <option id="langopt-yo" value="yo">Yoruba (Yorùbá)</option>
          <option id="langopt-zu" value="zu">Zulu (Zulu)</option>
        </select>

        <span id="translation-disclaimer-link">Disclaimer</span>
      </div>
    </div>
    <div id="translation-disclaimer">
      <div class="pagetitle">
        <h2>Website Translation Disclaimer</h2>
      </div>
      <hr/>
      <p>THIS SERVICE MAY CONTAIN TRANSLATIONS POWERED BY GOOGLE. GOOGLE DISCLAIMS ALL WARRANTIES RELATED TO THE TRANSLATIONS, EXPRESS OR IMPLIED, INCLUDING ANY WARRANTIES OF ACCURACY, RELIABILITY, AND ANY IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.</p>
      <p>The website has been translated for your convenience using translation software powered by Google Translate. Reasonable efforts have been made to provide an accurate translation, however, no automated translation is perfect nor is it intended to replace human translators. Translations are provided as a service to users of the website, and are provided “as is.” No warranty of any kind, either expressed or implied, is made as to the accuracy, reliability, or correctness of any translations made from English into any other language. Some content (such as images, videos, Flash, etc.) may not be accurately translated due to the limitations of the translation software.</p>
      <p>The official text is the English version of the website. Any discrepancies or differences created in the translation are not binding and have no legal effect for compliance or enforcement purposes. If any questions arise related to the accuracy of the information contained in the translated website, please refer to the English version of the website which is the official version.</p>
    </div>
EOT;

}

