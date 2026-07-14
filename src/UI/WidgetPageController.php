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
final readonly class WidgetPageController
{
    /**
     * Column defs matching the react lib's DenseTableColumnDef (key/label +
     * cell variant). 'badge'/'timestamp' pick the built-in cell renderers.
     *
     * @var list<array<string, string>>
     */
    private const COLUMNS = [
        ['key' => 'name', 'label' => 'Name', 'variant' => 'text'],
        ['key' => 'status', 'label' => 'Status', 'variant' => 'badge'],
        ['key' => 'created_at', 'label' => 'Created', 'variant' => 'timestamp', 'timestampFormat' => 'datetime'],
    ];

    public function __construct(
        private InertiaAdapter $inertia,
        private WidgetService $widgets,
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

        // Emit the FULL DenseTableBlockData the react block expects (columns +
        // rows + pagination + sort + filters). Single-page static data — a real
        // plugin would honour the Inertia partial-reload params for server-side
        // sort/filter/paginate.
        $count = count($rows);
        $block = BlockBuilder::denseTable('widgets', self::COLUMNS, $rows, [
            'pagination' => ['page' => 1, 'perPage' => max(1, $count), 'total' => $count, 'lastPage' => 1],
            'sort' => ['column' => 'created_at', 'direction' => 'desc'],
            'filters' => ['available' => [], 'applied' => (object) []],
        ]);

        $contract = PageBuilder::page('widget.index')
            ->title('Widgets')
            ->shell('immersive')
            ->layout('stack')
            ->region('content', [$block])
            ->build()
        ;

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
