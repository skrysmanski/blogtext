<?php
class BlogTextUpgrader {
  const OPTION_NAME = 'blogtext_version';

  private static $m_checked = false;

  /**
   * @param MSCL_AbstractPlugin $plugin
   */
  public static function run($plugin) {
    if (self::$m_checked) {
      // Already checked
      return;
    }

    $oldVersion = get_option(self::OPTION_NAME, '');
    $curVersion = $plugin->get_plugin_version();
    if ($oldVersion == $curVersion) {
      self::$m_checked = true;
      return;
    }

    #version_compare()
    /*if ($oldVersion == '') {
      self::upgradeFromPre0_9_5();
    }*/

    add_option(self::OPTION_NAME, $curVersion);
  }

  private static function loadSettings() {
    require_once(dirname(__FILE__).'/admin/settings.php');
  }

  /*private static function upgradeFromPre0_9_5() {
    self::loadSettings();

    // Move CSS for link icons into the "Custom CSS" setting
    $customCSSSetting = BlogTextSettings::get_custom_css(true);
    $customCSS = $customCSSSetting->get_value();
    $customCSS = <<<DOC
a.external {
  background: url(common-icons/link-external.png) no-repeat left center transparent;
  padding-left: 19px;
}

a.external-https {
  background-image: url(common-icons/link-https.gif) !important;
}

a.external-wiki {
  background-image: url(common-icons/wikipedia.png) !important;
}

a.external-search {
  background-image: url(common-icons/search.png) !important;
}

a.attachment {
  background: url(common-icons/attachment.gif) no-repeat left center transparent;
  padding-left: 19px;
}

a.section-link-above {
  background: url(common-icons/section-above.png) no-repeat left center transparent !important;
  padding-left: 11px;
}

a.section-link-below {
  background: url(common-icons/section-below.png) no-repeat left center transparent !important;
  padding-left: 11px;
}

$customCSS
DOC;

    $customCSSSetting->set_value($customCSS);
  }*/
}
