<?php

declare(strict_types=1);

namespace Drupal\Tests\d11assd_theme\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the accessibility rules of css/style.css (spec 001: CA-A4d, CA-A4e).
 *
 * The contrast of each rule is measured in specs/001-accesibilidad.md; these
 * tests guard against the rules being removed.
 */
#[Group('d11assd_theme')]
class ThemeCssTest extends UnitTestCase {

  /**
   * Returns the CSS rules of style.css as selector => declarations.
   *
   * @return array<string, string>
   *   Declarations keyed by selector list (comments removed).
   */
  private function rules(): array {
    $css = file_get_contents(dirname(__DIR__, 3) . '/css/style.css');
    $css = preg_replace('#/\*.*?\*/#s', '', $css);
    preg_match_all('/([^{}]+)\{([^}]*)\}/', $css, $matches, PREG_SET_ORDER);
    $rules = [];
    foreach ($matches as $match) {
      $rules[trim(preg_replace('/\s+/', ' ', $match[1]))] = $match[2];
    }
    return $rules;
  }

  /**
   * Finds the declarations of the rule whose selector list contains $needle.
   */
  private function declarationsFor(string $needle): string {
    foreach ($this->rules() as $selectors => $declarations) {
      foreach (array_map('trim', explode(',', $selectors)) as $selector) {
        if ($selector === $needle) {
          return $declarations;
        }
      }
    }
    $this->fail("style.css has no rule for '$needle'.");
  }

  /**
   * CA-A4d: visible focus outline, also on the dark navbar and footer.
   */
  public function testFocusIndicators(): void {
    $general = [
      'a:focus-visible',
      '.btn:focus-visible',
      '.form-control:focus-visible',
      '.form-select:focus-visible',
      '.nav-tabs .nav-link:focus-visible',
      // Close button of status messages: not a .btn (A11Y-006).
      '.btn-close:focus-visible',
      '.form-check-input:focus-visible',
    ];
    foreach ($general as $selector) {
      $this->assertMatchesRegularExpression('/outline:\s*2px solid #0a58ca/', $this->declarationsFor($selector), "$selector has a 2px outline.");
    }

    $dark = [
      '.navbar .nav-link:focus-visible',
      '.navbar .navbar-brand a:focus-visible',
      '.navbar .navbar-toggler:focus-visible',
      'footer a:focus-visible',
      // Bootstrap5 ".nav-link:focus-visible { outline: 0 }" is (0,2,0) and
      // beats "footer a:focus-visible" (0,1,2) (QA-008).
      'footer .nav-link:focus-visible',
    ];
    foreach ($dark as $selector) {
      $declarations = $this->declarationsFor($selector);
      $this->assertMatchesRegularExpression('/outline:\s*2px solid currentColor/', $declarations, "$selector follows the text colour.");
      $this->assertMatchesRegularExpression('/box-shadow:\s*none/', $declarations, "$selector removes the low-contrast ring.");
    }

    // The logo link takes the navbar text colour, so its currentColor
    // outline keeps the contrast whatever the navbar background (A11Y-007).
    $this->assertMatchesRegularExpression('/(^|[;\s])color:\s*inherit\s*(;|$)/m', $this->declarationsFor('.navbar .navbar-brand a.site-logo'));
  }

  /**
   * CA-A4e: links in status messages inherit the alert text colour.
   */
  public function testAlertLinks(): void {
    $this->assertMatchesRegularExpression('/(^|[;\s])color:\s*inherit\s*(;|$)/m', $this->declarationsFor('.alert a:not(.btn)'));
  }

}
