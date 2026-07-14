import { resolveBlock, resolveShell } from '@middag-io/react';
import { beforeAll, describe, expect, it } from 'vitest';

import { resolvePage } from '../src/app/page-resolver';
import { registerUi } from '../src/app/register';

describe('UI registration + page resolver', () => {
  beforeAll(() => {
    registerUi();
  });

  it('registers the immersive shell selectively', () => {
    expect(resolveShell('immersive')).toBeDefined();
  });

  it('registers the dense_table block', () => {
    expect(resolveBlock('dense_table')).toBeDefined();
  });

  it('does NOT register a Pro-only shell (registerDefaults was not called)', () => {
    expect(resolveShell('product')).toBeUndefined();
  });

  it('resolves a known Inertia page to its component', () => {
    expect(resolvePage('Widgets/Index')).toBeTypeOf('function');
  });

  it('throws on an unknown Inertia page', () => {
    expect(() => resolvePage('Nope/Missing')).toThrow(/Unknown Inertia page/);
  });
});
