// TypeScript 6 dropped the global JSX namespace under "jsx": "react-jsx".
// Re-export from React's namespace to keep existing JSX.Element usages working
// without touching every file.
import type { JSX as ReactJSX } from 'react';

declare global {
  namespace JSX {
    type Element = ReactJSX.Element;
    type IntrinsicElements = ReactJSX.IntrinsicElements;
  }
}
