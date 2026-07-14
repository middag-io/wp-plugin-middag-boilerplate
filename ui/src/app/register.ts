import {
  DenseTableBlock,
  ImmersiveShell,
  registerBlock,
  registerDefaultCells,
  registerDefaultFields,
  registerDefaultIcons,
  registerShell,
} from '@middag-io/react';

/**
 * SELECTIVE registration for the IIFE build.
 *
 * The library docs are explicit: IIFE/WordPress consumers must NOT call
 * registerDefaults() (it pulls the heavy/Pro blocks and defeats tree-shaking).
 * Instead register exactly the Community shell + block types this plugin uses,
 * plus the default cell/field/icon renderers the blocks depend on. Corrects the
 * account's registerDefaults() dump (O5-MA-04, "Rule 5").
 */
export function registerUi(): void {
  registerShell('immersive', ImmersiveShell);
  registerBlock('dense_table', DenseTableBlock);
  registerDefaultCells();
  registerDefaultFields();
  registerDefaultIcons();
}
