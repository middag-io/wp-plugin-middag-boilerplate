<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Integration\UI;

use DateTimeImmutable;
use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\WordPress\Http\Contract\ResponseEmitterInterface;
use Middag\WordPress\Http\Inertia\InertiaAdapter;
use Middag\WpBoilerplate\Domain\Widget\Contract\WidgetRepositoryInterface;
use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;
use Middag\WpBoilerplate\Domain\Widget\Widget;
use Middag\WpBoilerplate\Domain\Widget\WidgetService;
use Middag\WpBoilerplate\UI\WidgetPageController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Drives the admin Inertia page controller end-to-end against a real
 * {@see InertiaAdapter} + {@see WidgetService}, swapping only the leaf seams:
 * a recording {@see ResponseEmitterInterface} captures the emitted response
 * (body/redirect/status) instead of touching PHP's SAPI, an in-memory repository
 * stands in for the DB, and brain/monkey stubs the WordPress runtime functions.
 *
 * Both actions are exercised: GET '/' renders the DenseTable PageContract, and
 * POST '/widgets' runs the CSRF guard, sanitises the name, conditionally creates
 * the widget, then issues the Inertia-aware redirect back to the list.
 */
#[CoversClass(WidgetPageController::class)]
final class WidgetPageControllerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup = [];

    /** @var array<string, mixed> */
    private array $postBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        setUp();

        $this->serverBackup = $_SERVER;
        $this->postBackup = $_POST;

        // Leaf WordPress seams. esc_* / sanitize_* pass the value through so the
        // emitted payload stays assertable; nonces are stubbed valid.
        when('esc_attr')->returnArg();
        when('esc_html')->returnArg();
        when('sanitize_text_field')->returnArg();
        when('wp_create_nonce')->justReturn('nonce-123');
        when('wp_verify_nonce')->justReturn(1);
        when('admin_url')->alias(static fn (string $path): string => 'https://site.test/wp-admin/' . $path);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_POST = $this->postBackup;

        tearDown();
        parent::tearDown();
    }

    public function testIndexRendersTheWidgetListContract(): void
    {
        // A classic (non-Inertia) admin GET -> the adapter emits the HTML mount node.
        unset($_SERVER['HTTP_X_INERTIA']);

        $captured = null;
        when('wp_json_encode')->alias(function (mixed $data) use (&$captured): string {
            $captured = $data;

            return (string) json_encode($data);
        });

        $repository = $this->repositoryWith(
            new Widget(1, 'Sensor', WidgetStatus::Active, '2026-01-01 00:00:00'),
            new Widget(2, 'Gauge', WidgetStatus::Archived, '2026-02-02 00:00:00'),
        );
        $emitter = $this->recordingEmitter();

        $this->controller($emitter, $repository)->index();

        // The mount node was written and the request was NOT terminated (the HTML
        // path returns to WordPress rather than exiting).
        self::assertStringContainsString('middag-boilerplate-app', $emitter->body);
        self::assertFalse($emitter->terminated);

        // The page payload handed to the emitter is the canonical Inertia envelope.
        self::assertIsArray($captured);
        self::assertSame('Widgets/Index', $captured['component']);
        self::assertArrayHasKey('contract', $captured['props']);
        self::assertSame('nonce-123', $captured['props']['csrfToken']);

        // The DenseTable contract carries both active rows, the page id and title.
        $json = (string) json_encode($captured['props']['contract']);
        self::assertStringContainsString('widget.index', $json);
        self::assertStringContainsString('Widgets', $json);
        self::assertStringContainsString('Sensor', $json);
        self::assertStringContainsString('Gauge', $json);
    }

    public function testIndexBuildsAValidContractWithNoWidgets(): void
    {
        unset($_SERVER['HTTP_X_INERTIA']);

        $captured = null;
        when('wp_json_encode')->alias(function (mixed $data) use (&$captured): string {
            $captured = $data;

            return (string) json_encode($data);
        });

        $emitter = $this->recordingEmitter();

        // Empty list: exercises the max(1, $count) pagination guard.
        $this->controller($emitter, $this->repositoryWith())->index();

        self::assertIsArray($captured);
        self::assertSame('Widgets/Index', $captured['component']);
        self::assertStringContainsString('widget.index', (string) json_encode($captured['props']['contract']));
    }

    public function testStoreCreatesWidgetAndRedirectsBackToTheList(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_wpnonce'] = 'valid';
        $_POST['name'] = 'New Widget';

        $repository = $this->repositoryWith();
        $emitter = $this->recordingEmitter();

        $this->runTerminating(fn () => $this->controller($emitter, $repository)->store());

        self::assertCount(1, $repository->stored);
        self::assertSame('New Widget', $repository->stored[0]->name());
        self::assertSame(WidgetStatus::Active, $repository->stored[0]->status());

        self::assertSame('https://site.test/wp-admin/admin.php?page=middag-boilerplate', $emitter->redirect);
        self::assertTrue($emitter->terminated);
    }

    public function testStoreSkipsCreationWhenNameIsBlank(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_wpnonce'] = 'valid';
        // No 'name' key -> the `?? ''` fallback yields an empty, blank name.
        unset($_POST['name']);

        $repository = $this->repositoryWith();
        $emitter = $this->recordingEmitter();

        $this->runTerminating(fn () => $this->controller($emitter, $repository)->store());

        self::assertSame([], $repository->stored, 'a blank name must not create a widget');
        self::assertSame('https://site.test/wp-admin/admin.php?page=middag-boilerplate', $emitter->redirect);
        self::assertTrue($emitter->terminated);
    }

    /**
     * Run a callback whose final act terminates the request through the recording
     * emitter (which throws instead of exiting), swallowing that terminal signal.
     */
    private function runTerminating(callable $action): void
    {
        try {
            $action();
            self::fail('expected the response to terminate');
        } catch (RuntimeException $signal) {
            self::assertSame('emitter.terminate', $signal->getMessage());
        }
    }

    private function controller(ResponseEmitterInterface $emitter, WidgetRepositoryInterface $repository): WidgetPageController
    {
        $inertia = new InertiaAdapter($this->hostContext(), $emitter);
        $service = new WidgetService($repository, $this->fixedClock('2026-07-14 09:00:00'));

        return new WidgetPageController($inertia, $service);
    }

    private function hostContext(): HostComponentContextInterface
    {
        return new class implements HostComponentContextInterface {
            public function componentName(): string
            {
                return 'middag-boilerplate';
            }

            public function assetVersion(): string
            {
                return '0.1.0';
            }

            public function basePath(): ?string
            {
                return null;
            }
        };
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

    private function repositoryWith(Widget ...$active): WidgetRepositoryInterface
    {
        return new class(array_values($active)) implements WidgetRepositoryInterface {
            /** @var list<Widget> */
            public array $stored = [];

            /** @param list<Widget> $active */
            public function __construct(private readonly array $active) {}

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

    private function recordingEmitter(): ResponseEmitterInterface
    {
        return new class implements ResponseEmitterInterface {
            public ?int $status = null;

            /** @var array<string, string> */
            public array $headers = [];

            public ?string $redirect = null;

            public string $body = '';

            public bool $terminated = false;

            public function status(int $code): void
            {
                $this->status = $code;
            }

            public function header(string $name, string $value): void
            {
                $this->headers[$name] = $value;
            }

            public function redirect(string $url): void
            {
                $this->redirect = $url;
            }

            public function write(string $body): void
            {
                $this->body .= $body;
            }

            public function terminate(): never
            {
                $this->terminated = true;

                throw new RuntimeException('emitter.terminate');
            }
        };
    }
}
