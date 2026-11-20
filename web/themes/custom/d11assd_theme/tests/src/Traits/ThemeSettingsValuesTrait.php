<?php

declare(strict_types=1);

namespace Drupal\Tests\d11asdd_theme\Traits;

/**
 * Default Bootstrap5 settings of d11asdd_theme (spec 001, R10).
 */
trait ThemeSettingsValuesTrait {

  /**
   * The nine b5_* keys and their values required by R10.
   */
  protected const R10_SETTINGS = [
    'b5_body_schema' => 'none',
    'b5_body_bg_schema' => 'none',
    'b5_navbar_schema' => 'dark',
    'b5_navbar_bg_schema' => 'dark',
    'b5_footer_schema' => 'dark',
    'b5_footer_bg_schema' => 'dark',
    'b5_top_container' => 'container',
    'b5_top_container_config' => '',
    'b5_theme_mode' => 'light',
  ];

}
