<?php

declare(strict_types=1);

namespace Drupal\Tests\d11asdd_theme\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Url;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the theme in the browser.
 *
 * Las comprobaciones de la portada anónima se agrupan por afinidad en cuatro
 * métodos (assets/branding, regiones/bloques, clases Bootstrap y
 * accesibilidad) para compartir la instalación del sitio. Los escenarios con
 * contexto propio (tema de administración, mensajes y caché) van aparte.
 *
 * Spec 001: CA4, CA5a, CA7a, CA7b, CA8, CA12, CA13, CA15, CA16, CA23b, CA-A1,
 * CA-A1b, CA-A1c, CA-A1d, CA-A2.
 */
#[Group('d11asdd_theme')]
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
  protected $defaultTheme = 'd11asdd_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('system.site')->set('name', 'Sitio D11asdd')->save();
    $this->container->get('theme_installer')->install(['gin']);
    $this->config('system.theme')->set('admin', 'gin')->save();

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
   * CA4, CA23b, CA15, CA-A2: assets del tema y logo enlazado de la portada.
   */
  public function testFrontPageAssetsAndBranding(): void {
    $this->drupalGet('<front>');
    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);

    // CA4: the front page uses the theme, its CSS and shows the site name.
    $assert->responseContains('themes/contrib/bootstrap5/css/style.css');
    $assert->responseContains('themes/custom/d11asdd_theme/css/style.css');
    $assert->elementTextContains('css', 'body', 'Sitio D11asdd');

    // CA23b: the front page loads js/navbar.js.
    $assert->responseContains('themes/custom/d11asdd_theme/js/navbar.js');

    // CA15 and CA-A2: the theme logo is linked to the front page with alt text.
    $link = $assert->elementExists('xpath', '//a[@rel="home"][.//img]');
    $this->assertSame(Url::fromRoute('<front>')->toString(), $link->getAttribute('href'), 'The logo links to the front page.');
    $logo = $link->find('css', 'img');
    $this->assertStringContainsString('/themes/custom/d11asdd_theme/logo.svg', (string) $logo->getAttribute('src'));
    $this->assertNotEmpty(trim((string) $logo->getAttribute('alt')), 'The logo has alt text.');
  }

  /**
   * CA12, CA16: bloques en regiones y ARIA del toggler de la navbar.
   */
  public function testFrontPageRegionsAndBlocks(): void {
    $this->drupalGet('<front>');
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    // CA12: branding and main menu in the navbar, content block in main.
    $assert->elementExists('css', 'nav.navbar #block-d11asdd-theme-branding');
    $assert->elementExists('css', 'nav.navbar #block-d11asdd-theme-main-navigation');
    $assert->elementExists('css', 'main #block-d11asdd-theme-content');

    // CA16 (R11, v1.2): the navbar toggler button has the correct ARIA markup.
    $button = $page->find('css', 'nav.navbar button.navbar-toggler');
    $this->assertNotNull($button, 'Navbar toggler button exists inside nav.navbar.');
    $this->assertSame('button', $button->getAttribute('type'));
    $this->assertSame('false', $button->getAttribute('aria-expanded'));

    $controls_id = $button->getAttribute('aria-controls');
    $this->assertNotEmpty($controls_id, 'aria-controls is set on the toggler.');

    $controlled = $page->findById($controls_id);
    $this->assertNotNull($controlled, "Element #$controls_id referenced by aria-controls exists.");

    $this->assertNotNull(
      $controlled->find('css', '#block-d11asdd-theme-main-navigation'),
      "#$controls_id contains the main navigation block."
    );
    $this->assertNotNull(
      $controlled->find('css', '#block-d11asdd-theme-account-menu'),
      "#$controls_id contains the account menu block."
    );
  }

  /**
   * CA7a, CA7b: clases Bootstrap completas y sin tokens incompletos.
   */
  public function testFrontPageBootstrapClasses(): void {
    $this->drupalGet('<front>');

    // CA7a: default R10 settings generate complete Bootstrap classes.
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

    // CA7b: no incomplete class tokens in body, navbar and footer.
    foreach (['body', 'nav.navbar', 'footer'] as $selector) {
      $tokens = $this->classTokens($selector);
      foreach (['navbar-', 'footer-', 'bg-'] as $incomplete) {
        $this->assertNotContains($incomplete, $tokens, "$selector has no incomplete class $incomplete.");
      }
    }
  }

  /**
   * CA-A1, CA-A1b, CA-A1c, CA-A1d: semántica accesible de la portada.
   */
  public function testFrontPageAccessibility(): void {
    $this->drupalGet('<front>');
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    // CA-A1: html lang matches the default site language.
    $langcode = $this->container->get('language_manager')->getDefaultLanguage()->getId();
    $this->assertSame($langcode, $assert->elementExists('css', 'html')->getAttribute('lang'));

    // CA-A1d: the page has exactly one h1 element.
    $this->assertCount(1, $page->findAll('css', 'h1'));

    // CA-A1c: the page has exactly one main element.
    $this->assertCount(1, $page->findAll('css', 'main'));

    // CA-A1b: the first link of body skips to #main-content, inside main.
    $first_link = $assert->elementExists('xpath', '(//body//a)[1]');
    $this->assertSame('#main-content', $first_link->getAttribute('href'));
    $assert->elementExists('css', 'main #main-content');
  }

  /**
   * CA5a: administration pages keep using Gin.
   */
  public function testAdminUsesGin(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'access content overview',
      'view the administration theme',
    ]));
    $this->drupalGet('/admin/content');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('themes/contrib/gin/');
    $this->assertSession()->responseNotContains('themes/custom/d11asdd_theme/css/style.css');
  }

  /**
   * CA13: status messages are rendered inside main.
   */
  public function testMessagesInMain(): void {
    $this->drupalGet('user/login');
    $this->submitForm(['name' => 'nadie', 'pass' => 'incorrecta'], 'Log in');
    $assert = $this->assertSession();
    // The d11asdd_theme_messages block (system_messages_block) renders its
    // fallback wrapper; Bootstrap5 renders each message as role="alert".
    $assert->elementExists('css', 'main [data-drupal-messages-fallback]');
    // The alert is rendered right after the fallback wrapper, inside the same
    // messages block (block--system-messages-block.html.twig).
    $alert = $assert->elementExists('xpath', '//main//div[@data-drupal-messages-fallback]/following-sibling::div[@role="alert"]');
    $this->assertStringContainsString('Unrecognized username or password', $alert->getText());
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
    $this->drupalGet('admin/appearance/settings/d11asdd_theme');
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

}
