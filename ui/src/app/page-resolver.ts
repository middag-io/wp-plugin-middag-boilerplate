import type { ComponentType } from 'react';

type PageModule = { default: ComponentType<Record<string, unknown>> };

// Eagerly glob every page component so the IIFE bundle is self-contained
// (no dynamic import() chunks, which an IIFE cannot emit).
const pages = import.meta.glob<PageModule>('../pages/**/*.tsx', { eager: true });

/**
 * Inertia page resolver: map a server-sent page name (e.g. "Widgets/Index") to
 * its React component. Single, explicit path — no legacy branch (corrects
 * O5-MA-09's dual resolver).
 */
export function resolvePage(name: string): ComponentType<Record<string, unknown>> {
  const module = pages[`../pages/${name}.tsx`];

  if (module === undefined) {
    throw new Error(`[middag-boilerplate] Unknown Inertia page: ${name}`);
  }

  return module.default;
}
