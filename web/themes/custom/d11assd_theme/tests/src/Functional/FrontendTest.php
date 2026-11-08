<?php

declare(strict_types=1);

namespace Drupal\Tests\d11assd_theme\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the theme in the browser (spec 001: CA4, CA5).
 */
#[Group('d11assd_theme')]
class FrontendTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'd11assd_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('system.site')->set('name', 'Sitio D11assd')->save();
    $this->container->get('theme_installer')->install(['claro']);
    $this->config('system.theme')->set('admin', 'claro')->save();
  }

  /**
   * CA4: the front page uses the theme, its CSS and shows the site name.
   */
  public function testFrontPage(): void {
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('themes/contrib/bootstrap5/css/style.css');
    $this->assertSession()->responseContains('themes/custom/d11assd_theme/css/style.css');
    $this->assertSession()->elementTextContains('css', 'body', 'Sitio D11assd');
  }

  /**
   * CA5: administration pages keep using Claro.
   */
  public function testAdminUsesClaro(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'access content overview',
      'view the administration theme',
    ]));
    $this->drupalGet('/admin/content');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('core/themes/claro/');
    $this->assertSession()->responseNotContains('themes/custom/d11assd_theme/css/style.css');
  }

}
