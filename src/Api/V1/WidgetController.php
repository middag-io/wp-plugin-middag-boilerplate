<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Api\V1;

use Middag\WordPress\Http\Contract\RequestAuthenticatorInterface;
use Middag\WordPress\Http\Controller\AbstractWpRestController;
use Middag\WordPress\Http\Response\RestResponse;
use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;
use Middag\WpBoilerplate\Domain\Widget\Widget;
use Middag\WpBoilerplate\Domain\Widget\WidgetData;
use Middag\WpBoilerplate\Domain\Widget\WidgetService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for widgets — extends the wp-adapter's base controller for
 * permission callbacks, sanitize seams and the response envelope.
 *
 * Routes register under the derived namespace `middag-boilerplate/v1` (no brand
 * literal): the RestRouteRegistrar resolves this controller from the container
 * and calls registerRoutes() on rest_api_init. GET is public; POST requires a
 * session — NEVER `__return_true` for a write.
 */
final class WidgetController extends AbstractWpRestController
{
    public function __construct(
        RequestAuthenticatorInterface $authenticator,
        private readonly WidgetService $widgets,
    ) {
        parent::__construct($authenticator);
    }

    public function registerRoutes(string $namespace): void
    {
        // Public read.
        $this->route($namespace, '/widgets', 'GET', [$this, 'index'], [$this, 'publicPermissionCheck']);

        // Authenticated write — 5th arg omitted defaults to session permissionCheck.
        $this->route($namespace, '/widgets', 'POST', [$this, 'store']);
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $widgets = array_map(
            static fn (Widget $widget): array => $widget->toArray(),
            $this->widgets->listActive(),
        );

        return RestResponse::success($widgets);
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        $invalid = $this->validateRequired($this->getBody($request), ['name']);
        if ($invalid !== null) {
            return $invalid;
        }

        $widget = $this->widgets->create(new WidgetData(
            $this->sanitizedText($request, 'name'),
            WidgetStatus::Active,
        ));

        return RestResponse::created($widget->toArray());
    }
}
