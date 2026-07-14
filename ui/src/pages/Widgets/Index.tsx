import { ContractPage, type PageContract } from '@middag-io/react';

type WidgetsIndexProps = {
  contract: PageContract;
};

/**
 * The Widgets admin page.
 *
 * PHP builds the whole PageContract (WidgetPageController + middag-io/ui
 * PageBuilder); this component just renders it. That is the single contract
 * path — the page owns no layout logic, so any server-built page renders the
 * same way.
 */
export default function WidgetsIndex({ contract }: WidgetsIndexProps) {
  return <ContractPage contract={contract} />;
}
