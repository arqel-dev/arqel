import { defineConfig } from '@playwright/test';
import base from './playwright.config';

// Temp config to RUN the @baseline defect-map (the default config grep-inverts
// it). Used on demand during the responsive loop to refresh the per-surface map.
export default defineConfig({
  ...base,
  grepInvert: undefined,
  grep: /@baseline/,
});
