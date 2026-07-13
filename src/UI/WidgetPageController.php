<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\UI;

use Middag\Ui\Block\BlockBuilder;
use Middag\Ui\Page\PageBuilder;
use Middag\WordPress\Http\Inertia\InertiaAdapter;
use Middag\WordPress\Http\Security\CsrfGuard;
use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;
use Middag\WpBoilerplate\Domain\Widget\Widget;
use Middag\WpBoilerplate\Domain\Widget\WidgetData;
use Middag\WpBoilerplate\Domain\Widget\WidgetService;

/**
 * Admin page controller for the Inertia/React surface.
 *
 * Builds the page server-side with the middag-io/ui PageBuilder — PHP owns the
 * PageContract structure, which the React <ContractPage> renders as-is (single
 * contract path; no contractKey/contractData split, no legacy branch). This is
 * the decision to exercise the UI pillar in PHP rather than leaving it purely
 * transitive.
 *
 * Dispatched by the AdminRouteRegistrar → Router: GET '/' renders the list,
 * POST '/widgets' creates one — CSRF-guarded via the Inertia nonce action.
 */
final class WidgetPageController
{
    /**
     * @var list<array{key: string, label: string}>
     */
    private const COLUMNS = [
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'status', 'label' => 'Status'],
        ['key' => 'created_at', 'label' => 'Created'],
    ];

    public function __construct(
        private readonly InertiaAdapter $inertia,
        private readonly WidgetService $widgets,
    ) {}

    public function index(): void
    {
        $rows = array_map(
            static fn (Widget $widget): array => [
                'name' => $widget->name(),
                'status' => $widget->status()->value,
                'created_at' => $widget->createdAt(),
            ],
            $this->widgets->listActive(),
        );

        $contract = PageBuilder::page('widget.index')
            ->title('Widgets')
            ->shell('immersive')
            ->layout('stack')
            ->region('content', [BlockBuilder::denseTable('widgets', self::COLUMNS, $rows)])
            ->build();

        $this->inertia->render('Widgets/Index', ['contract' => $contract]);
    }

    public function store(): void
    {
        // Mutating admin request: reject a missing/invalid nonce with 403 before
        // touching the domain.
        CsrfGuard::enforce($this->inertia->nonceAction());

        $raw = $_POST['name'] ?? '';
        $name = sanitize_text_field(is_string($raw) ? $raw : '');

        if ($name !== '') {
            $this->widgets->create(new WidgetData($name, WidgetStatus::Active));
        }

        // Inertia-aware redirect back to the list.
        $this->inertia->location(admin_url('admin.php?page=middag-boilerplate'));
    }
}
