import { describe, expect, it } from 'vitest';
import chromeManifest from '../manifests/chrome.json';
import firefoxManifest from '../manifests/firefox.json';

describe('chrome manifest', () => {
  it('targets manifest_version 3', () => {
    expect(chromeManifest.manifest_version).toBe(3);
  });

  it('declares required top-level fields', () => {
    expect(chromeManifest.name).toBe('Arqel DevTools');
    expect(chromeManifest.version).toMatch(/^\d+\.\d+\.\d+$/);
    expect(chromeManifest.description).toBeTruthy();
    expect(chromeManifest.devtools_page).toBe('devtools.html');
    expect(chromeManifest.background.service_worker).toBe('background.js');
  });

  it('references icons in 16/32/48/128 sizes', () => {
    for (const size of [16, 32, 48, 128] as const) {
      expect(chromeManifest.icons[String(size) as '16']).toBe(`icons/icon-${size}.png`);
    }
  });

  it('keeps permissions narrow', () => {
    expect(chromeManifest.permissions).toEqual(expect.arrayContaining(['scripting', 'tabs']));
    expect(chromeManifest.permissions).not.toContain('<all_urls>');
    expect(chromeManifest.permissions.length).toBeLessThanOrEqual(4);
  });

  it('injects the content script at document_start on every URL', () => {
    expect(chromeManifest.content_scripts).toHaveLength(1);
    const [entry] = chromeManifest.content_scripts;
    expect(entry?.matches).toEqual(['<all_urls>']);
    expect(entry?.run_at).toBe('document_idle');
    expect(entry?.js).toEqual(['content-script.js']);
  });
});

describe('firefox manifest', () => {
  it('matches the chrome name/version pair', () => {
    expect(firefoxManifest.name).toBe(chromeManifest.name);
    expect(firefoxManifest.version).toBe(chromeManifest.version);
  });

  it('declares browser_specific_settings.gecko', () => {
    expect(firefoxManifest.browser_specific_settings?.gecko?.id).toBe('devtools@arqel.dev');
  });
});
