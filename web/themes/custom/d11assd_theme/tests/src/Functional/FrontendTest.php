<?php

declare(strict_types=1);

namespace Drupal\Tests\d11assd_theme\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Url;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the theme in the browser.
 *
 * Spec 001: CA4, CA5a, CA12, CA13, CA15, CA7a, CA7b, CA8, CA-A1, CA-A1b,
 * CA-A1c, CA-A1d, CA-A2.
 */
#[Group('d11assd_theme')]
class FrontendTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'node',
    'menu_link_content',
    'page_cache',
    'dynamic_page_cache',
  ];

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

    // The main menu is empty in the testing profile; add a link so that the
    // main navigation block is rendered (CA12).
    MenuLinkContent::create([
      'title' => 'Inicio',
      'link' => ['uri' => 'internal:/'],
      'menu_name' => 'main',
    ])->save();
  }

  /**
   * Returns the class tokens of the element matching a CSS selector.
   *
   * @return string[]
   *   The class names.
   */
  private function classTokens(string $selector): array {
    $element = $this->assertSession()->elementExists('css', $selector);
    return preg_split('/\s+/', trim((string) $element->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY);
  }

  /**
   * Returns the first child div of an element matched by XPath.
   */
  private function childDiv(string $parent_xpath): NodeElement {
    return $this->assertSession()->elementExists('xpath', $parent_xpath . '/div');
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
   * CA5a: administration pages keep using Claro.
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

  /**
   * CA12: branding and main menu in the navbar, content block in main.
   */
  public function testBlocksInRegions(): void {
    $this->drupalGet('<front>');
    $assert = $this->assertSession();
    $assert->elementExists('css', 'nav.navbar #block-d11assd-theme-branding');
    $assert->elementExists('css', 'nav.navbar #block-d11assd-theme-main-navigation');
    $assert->elementExists('css', 'main #block-d11assd-theme-content');
  }

  /**
   * CA13: status messages are rendered inside main.
   */
  public function testMessagesInMain(): void {
    $this->drupalGet('user/login');
    $this->submitForm(['name' => 'nadie', 'pass' => 'incorrecta'], 'Log in');
    $assert = $this->assertSession();
    // The d11assd_theme_messages block (system_messages_block) renders its
    // fallback wrapper; Bootstrap5 renders each message as role="alert".
    $assert->elementExists('css', 'main [data-drupal-messages-fallback]');
    // The alert is rendered right after the fallback wrapper, inside the same
    // messages block (block--system-messages-block.html.twig).
    $alert = $assert->elementExists('xpath', '//main//div[@data-drupal-messages-fallback]/following-sibling::div[@role="alert"]');
    $this->assertStringContainsString('Unrecognized username or password', $alert->getText());
  }

  /**
   * CA15 and CA-A2: the theme logo is linked to the front page with alt text.
   */
  public function testLogo(): void {
    $this->drupalGet('<front>');
    $link = $this->assertSession()->elementExists('xpath', '//a[@rel="home"][.//img]');
    $this->assertSame(Url::fromRoute('<front>')->toString(), $link->getAttribute('href'), 'The logo links to the front page.');
    $logo = $link->find('css', 'img');
    $this->assertStringContainsString('/themes/custom/d11assd_theme/logo.svg', (string) $logo->getAttribute('src'));
    $this->assertNotEmpty(trim((string) $logo->getAttribute('alt')), 'The logo has alt text.');
  }

  /**
   * CA7a: default R10 settings generate complete Bootstrap classes.
   */
  public function testDefaultSettingsClasses(): void {
    $this->drupalGet('<front>');

    $nav = $this->classTokens('nav.navbar');
    foreach (['navbar-dark', 'text-light', 'bg-dark'] as $class) {
      $this->assertContains($class, $nav, "The navbar has class $class.");
    }
    $footer = $this->classTokens('footer');
    foreach (['footer-dark', 'text-light', 'bg-dark'] as $class) {
      $this->assertContains($class, $footer, "The footer has class $class.");
    }

    $parents = [
      'navbar' => '//nav[contains(concat(" ", normalize-space(@class), " "), " navbar ")]',
      'main' => '//main',
      'footer' => '//footer',
    ];
    foreach ($parents as $label => $xpath) {
      $classes = preg_split('/\s+/', (string) $this->childDiv($xpath)->getAttribute('class'), -1, PREG_SPLIT_NO_EMPTY);
      $this->assertContains('container', $classes, "The $label container div has class container.");
    }

    $this->assertSame('light', $this->assertSession()->elementExists('css', 'html')->getAttribute('data-bs-theme'));
  }

  /**
   * CA7b: no incomplete class tokens in body, navbar and footer.
   */
  public function testNoIncompleteClasses(): void {
    $this->drupalGet('<front>');
    foreach (['body', 'nav.navbar', 'footer'] as $selector) {
      $tokens = $this->classTokens($selector);
      foreach (['navbar-', 'footer-', 'bg-'] as $incomplete) {
        $this->assertNotContains($incomplete, $tokens, "$selector has no incomplete class $incomplete.");
      }
    }
  }

  /**
   * CA8: settings saved in the form apply to cached pages without a rebuild.
   */
  public function testSettingsChangeAppliesToCachedPage(): void {
    // Warm the page cache as an anonymous visitor.
    $this->drupalGet('<front>');
    $this->drupalGet('<front>');
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'HIT');

    $this->drupalLogin($this->drupalCreateUser(['administer themes']));
    $this->drupalGet('admin/appearance/settings/d11assd_theme');
    $this->submitForm([
      'b5_body_bg_schema' => 'light',
      'b5_navbar_bg_schema' => 'primary',
      'b5_footer_bg_schema' => 'secondary',
      'b5_top_container' => 'container-fluid m-0 p-0',
    ], 'Save configuration');
    $this->drupalLogout();

    $this->drupalGet('<front>');
    $this->assertContains('bg-light', $this->classTokens('body'));
    $this->assertContains('bg-primary', $this->classTokens('nav.navbar'));
    $this->assertContains('bg-secondary', $this->classTokens('footer'));
    foreach (['//nav[contains(@class, "navbar")]', '//main', '//footer'] as $xpath) {
      $classes = preg_split('/\s+/', (string) $this->childDiv($xpath)->getAttribute('class'), -1, PREG_SPLIT_NO_EMPTY);
      $this->assertContains('container-fluid', $classes, "$xpath container is fluid.");
    }
  }

  /**
   * CA-A1: html lang matches the default site language.
   */
  public function testHtmlLang(): void {
    $this->drupalGet('<front>');
    $langcode = $this->container->get('language_manager')->getDefaultLanguage()->getId();
    $html = $this->assertSession()->elementExists('css', 'html');
    $this->assertSame($langcode, $html->getAttribute('lang'));
  }

  /**
   * CA-A1d: the page has exactly one h1 element.
   */
  public function testSingleH1(): void {
    $this->drupalGet('<front>');
    $this->assertCount(1, $this->getSession()->getPage()->findAll('css', 'h1'));
  }

  /**
   * CA-A1b: the first link of body skips to #main-content, inside main.
   */
  public function testSkipLink(): void {
    $this->drupalGet('<front>');
    $first_link = $this->assertSession()->elementExists('xpath', '(//body//a)[1]');
    $this->assertSame('#main-content', $first_link->getAttribute('href'));
    $this->assertSession()->elementExists('css', 'main #main-content');
  }

  /**
   * CA-A1c: the page has exactly one main element.
   */
  public function testSingleMain(): void {
    $this->drupalGet('<front>');
    $this->assertCount(1, $this->getSession()->getPage()->findAll('css', 'main'));
  }

}
