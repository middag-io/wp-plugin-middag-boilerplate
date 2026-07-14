<?php

declare(strict_types=1);

namespace {
    // Minimal WordPress REST doubles. WordPress is not a Composer dependency, so
    // these classes are not autoloaded at runtime (only stubbed for static
    // analysis). The controller type-hints WP_REST_Request and the response
    // envelope instantiates WP_REST_Response, so the runtime needs real classes
    // here. Guarded with class_exists() so a full-WP bootstrap wins if present.
    if (!class_exists('WP_REST_Response')) {
        class WP_REST_Response
        {
            public function __construct(
                private readonly mixed $data = null,
                private readonly int $status = 200,
            ) {}

            public function get_data(): mixed
            {
                return $this->data;
            }

            public function get_status(): int
            {
                return $this->status;
            }
        }
    }

    if (!class_exists('WP_REST_Request')) {
        class WP_REST_Request
        {
            /**
             * @param array<string, mixed> $params
             * @param array<string, mixed> $jsonParams
             */
            public function __construct(
                private readonly array $params = [],
                private readonly array $jsonParams = [],
            ) {}

            public function get_param(string $key): mixed
            {
                return $this->params[$key] ?? null;
            }

            /**
             * @return array<string, mixed>
             */
            public function get_json_params(): array
            {
                return $this->jsonParams;
            }
        }
    }
}

namespace Middag\WpBoilerplate\Tests\Integration\Api\V1 {
    use DateTimeImmutable;
    use Middag\WordPress\Http\Contract\RequestAuthenticatorInterface;
    use Middag\WpBoilerplate\Api\V1\WidgetController;
    use Middag\WpBoilerplate\Domain\Widget\Contract\WidgetRepositoryInterface;
    use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;
    use Middag\WpBoilerplate\Domain\Widget\Widget;
    use Middag\WpBoilerplate\Domain\Widget\WidgetService;
    use PHPUnit\Framework\Attributes\CoversClass;
    use PHPUnit\Framework\TestCase;
    use Psr\Clock\ClockInterface;
    use WP_REST_Request;
    use WP_REST_Response;

    use function Brain\Monkey\Functions\when;
    use function Brain\Monkey\setUp;
    use function Brain\Monkey\tearDown;

    /**
     * Covers the REST controller boundary: route registration (public GET vs.
     * authenticated POST), the read endpoint's success envelope, and the write
     * endpoint's validation guard, sanitize seam and created envelope.
     *
     * WordPress runtime is mocked per-test with brain/monkey; the domain service
     * is resolved directly against an in-memory repository so the assertions read
     * behaviour (envelope shape, persisted entity) rather than "was called".
     */
    #[CoversClass(WidgetController::class)]
    final class WidgetControllerTest extends TestCase
    {
        /** @var object{stored: list<Widget>, active: list<Widget>}&WidgetRepositoryInterface */
        private object $repository;

        private WidgetController $controller;

        protected function setUp(): void
        {
            parent::setUp();
            setUp();

            // The sanitize seam degrades to a pure-PHP fallback when WordPress is
            // absent; bind the real function so the write path exercises the
            // WordPress-backed branch and strips markup deterministically.
            when('sanitize_text_field')->alias(
                static fn (string $value): string => trim(strip_tags($value)),
            );

            $this->repository = $this->repository();
            $this->controller = new WidgetController(
                $this->createStub(RequestAuthenticatorInterface::class),
                new WidgetService($this->repository, $this->fixedClock('2026-07-14 09:00:00')),
            );
        }

        protected function tearDown(): void
        {
            tearDown();
            parent::tearDown();
        }

        public function testRegisterRoutesRegistersPublicReadAndAuthenticatedWrite(): void
        {
            $calls = [];
            when('register_rest_route')->alias(
                static function (string $namespace, string $path, array $args) use (&$calls): bool {
                    $calls[] = ['namespace' => $namespace, 'path' => $path, 'args' => $args];

                    return true;
                },
            );

            $this->controller->registerRoutes('middag-boilerplate/v1');

            self::assertCount(2, $calls);

            // Public read: GET /widgets guarded by the public permission callback.
            self::assertSame('middag-boilerplate/v1', $calls[0]['namespace']);
            self::assertSame('/widgets', $calls[0]['path']);
            self::assertSame('GET', $calls[0]['args']['methods']);
            self::assertSame([$this->controller, 'index'], $calls[0]['args']['callback']);
            self::assertSame([$this->controller, 'publicPermissionCheck'], $calls[0]['args']['permission_callback']);

            // Authenticated write: POST /widgets falls back to the session
            // permission check — NEVER __return_true for a write.
            self::assertSame('/widgets', $calls[1]['path']);
            self::assertSame('POST', $calls[1]['args']['methods']);
            self::assertSame([$this->controller, 'store'], $calls[1]['args']['callback']);
            self::assertSame([$this->controller, 'permissionCheck'], $calls[1]['args']['permission_callback']);
        }

        public function testIndexReturnsActiveWidgetsInASuccessEnvelope(): void
        {
            $this->repository->active = [
                new Widget(1, 'Alpha', WidgetStatus::Active, '2026-01-01 00:00:00'),
                new Widget(2, 'Beta', WidgetStatus::Active, '2026-02-02 00:00:00'),
            ];

            $response = $this->controller->index(new WP_REST_Request());

            self::assertInstanceOf(WP_REST_Response::class, $response);
            self::assertSame(200, $response->get_status());

            $body = $response->get_data();
            self::assertTrue($body['success']);
            self::assertCount(2, $body['data']);
            self::assertSame(
                ['id' => 1, 'name' => 'Alpha', 'status' => 'active', 'created_at' => '2026-01-01 00:00:00'],
                $body['data'][0],
            );
            self::assertSame('Beta', $body['data'][1]['name']);
        }

        public function testIndexReturnsAnEmptyDataArrayWhenNoWidgetsAreActive(): void
        {
            $response = $this->controller->index(new WP_REST_Request());

            self::assertSame(200, $response->get_status());
            self::assertSame([], $response->get_data()['data']);
        }

        public function testStorePersistsTheWidgetAndReturnsACreatedEnvelope(): void
        {
            $request = new WP_REST_Request(['name' => 'Sensor'], ['name' => 'Sensor']);

            $response = $this->controller->store($request);

            self::assertSame(201, $response->get_status());

            $body = $response->get_data();
            self::assertTrue($body['success']);
            self::assertSame('Sensor', $body['data']['name']);
            // New widgets are created Active with the clock's timestamp.
            self::assertSame('active', $body['data']['status']);
            self::assertSame('2026-07-14 09:00:00', $body['data']['created_at']);

            // The entity was handed to the repository.
            self::assertCount(1, $this->repository->stored);
            self::assertSame('Sensor', $this->repository->stored[0]->name());
        }

        public function testStoreSanitizesTheNameThroughTheSeam(): void
        {
            $request = new WP_REST_Request(
                ['name' => '  <b>Sensor</b> '],
                ['name' => '  <b>Sensor</b> '],
            );

            $response = $this->controller->store($request);

            self::assertSame(201, $response->get_status());
            self::assertSame('Sensor', $response->get_data()['data']['name']);
        }

        public function testStoreReturnsAValidationErrorWhenNameIsMissing(): void
        {
            $response = $this->controller->store(new WP_REST_Request());

            self::assertSame(422, $response->get_status());

            $body = $response->get_data();
            self::assertFalse($body['success']);
            self::assertSame('VALIDATION_ERROR', $body['errors']['code']);
            self::assertArrayHasKey('name', $body['errors']['fields']);

            // The guard short-circuits before the domain service runs.
            self::assertCount(0, $this->repository->stored);
        }

        private function fixedClock(string $moment): ClockInterface
        {
            return new class($moment) implements ClockInterface {
                public function __construct(private readonly string $moment) {}

                public function now(): DateTimeImmutable
                {
                    return new DateTimeImmutable($this->moment);
                }
            };
        }

        /**
         * @return object{stored: list<Widget>, active: list<Widget>}&WidgetRepositoryInterface
         */
        private function repository(): object
        {
            return new class implements WidgetRepositoryInterface {
                /** @var list<Widget> */
                public array $stored = [];

                /** @var list<Widget> */
                public array $active = [];

                public function store(Widget $widget): void
                {
                    $this->stored[] = $widget;
                }

                public function ofId(int $id): ?Widget
                {
                    return null;
                }

                public function allActive(): array
                {
                    return $this->active;
                }
            };
        }
    }
}
