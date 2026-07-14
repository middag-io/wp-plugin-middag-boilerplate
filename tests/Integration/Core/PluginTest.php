<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Integration\Core;

use Closure;
use Middag\WpBoilerplate\Api\V1\WidgetController;
use Middag\WpBoilerplate\Core\Plugin;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Exercises the plugin composition root end to end.
 *
 * The four public entry points touch WordPress globals, define plugin constants
 * and register anonymous closures on `add_action`. Each test therefore runs in
 * an isolated process (so a constant/mock defined once never leaks) and mocks
 * the WordPress runtime with brain/monkey.
 *
 * The wiring test is the interesting one: instead of asserting merely that
 * `add_action` was *called*, it captures every callback the plugin registers and
 * then INVOKES each closure under mocks, so the body of every subsystem hook
 * (CPT, discovered *Hooks, cron, REST, admin menu, asset enqueue, native
 * settings submenu) actually executes and is verified.
 *
 * @see ContainerBootTest for the pure compile-time container assertions.
 */
#[CoversClass(Plugin::class)]
final class PluginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    protected function tearDown(): void
    {
        // Count brain/monkey `expect()` satisfactions as PHPUnit assertions so a
        // pure-side-effect test (e.g. deactivate) is not flagged "risky".
        if (($container = Mockery::getContainer()) !== null) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }

        tearDown();
        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testActivateInstallsTheSchemaGrantsCapabilitiesAndFlushesRewrites(): void
    {
        // The wpdb result-format constant the connection adapter passes to
        // get_row(); absent outside a WordPress runtime.
        if (!\defined('ARRAY_A')) {
            \define('ARRAY_A', 'ARRAY_A');
        }

        $capturedDdl = '';

        // A $wpdb double whose table probe reports "missing" so the installer
        // proceeds to CREATE TABLE; the emitted DDL is captured for assertion.
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $wpdb->shouldReceive('prepare')->andReturnUsing(static fn (string $sql): string => $sql);
        $wpdb->shouldReceive('get_row')->andReturnNull();
        $wpdb->shouldReceive('query')->once()->andReturnUsing(
            static function (string $sql) use (&$capturedDdl): int {
                $capturedDdl = $sql;

                return 1;
            }
        );
        $GLOBALS['wpdb'] = $wpdb;

        // The administrator role receives the plugin's custom capability once.
        $role = Mockery::mock('WP_Role');
        $role->shouldReceive('add_cap')->once()->with('mdgbp_manage', true);
        when('get_role')->justReturn($role);

        expect('flush_rewrite_rules')->once();

        Plugin::activate();

        self::assertStringContainsString('CREATE TABLE', $capturedDdl, 'installer emits DDL');
        self::assertStringContainsString('wp_mdgbp_widget', $capturedDdl, 'DDL targets the prefixed table');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDeactivateClearsCronRevokesCapabilitiesAndFlushesRewrites(): void
    {
        expect('wp_clear_scheduled_hook')->once()->with('mdgbp_sync');

        $role = Mockery::mock('WP_Role');
        $role->shouldReceive('remove_cap')->once()->with('mdgbp_manage');
        when('get_role')->justReturn($role);

        expect('flush_rewrite_rules')->once();

        Plugin::deactivate();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBootBuildsTheContainerFromTheRealCompositionRoot(): void
    {
        \define('MIDDAG_BOILERPLATE_VERSION', '9.9.9');

        $root = \dirname(__DIR__, 3);
        when('plugin_dir_path')->justReturn($root . '/');
        when('plugin_dir_url')->justReturn('https://assets.test/plugins/middag-boilerplate/');
        when('add_action')->justReturn(true);
        when('add_filter')->justReturn(true);
        // Front-end request: the admin surface must NOT be wired.
        when('is_admin')->justReturn(false);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldIgnoreMissing();
        $GLOBALS['wpdb'] = $wpdb;

        self::assertNull(Plugin::container(), 'container is empty before boot');

        Plugin::boot($root . '/middag-boilerplate.php');

        $container = Plugin::container();
        self::assertInstanceOf(ContainerBuilder::class, $container);
        // The composition root discovered and autowired the plugin's services.
        self::assertTrue($container->has(WidgetController::class), 'container is populated');
    }

    /**
     * THE BIG ONE: boot with `is_admin()` true, capture every registered
     * callback and invoke each closure so the full hook graph executes.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRegisterHooksAndAdminWiringInvokesEveryClosure(): void
    {
        \define('MIDDAG_BOILERPLATE_VERSION', '9.9.9');

        $root = \dirname(__DIR__, 3);
        when('plugin_dir_path')->justReturn($root . '/');
        when('plugin_dir_url')->justReturn('https://assets.test/');
        when('is_admin')->justReturn(true);

        // Capture every add_action registration (hook + callback) for later
        // invocation, so the closures Plugin.php builds actually run.
        /** @var list<array{hook: string, cb: callable}> $actions */
        $actions = [];
        when('add_action')->alias(
            static function (string $hook, callable $cb) use (&$actions): bool {
                $actions[] = ['hook' => $hook, 'cb' => $cb];

                return true;
            }
        );
        when('add_filter')->justReturn(true);
        when('__')->returnArg(1);

        // Runtime side effects reached once each closure body runs.
        expect('register_post_type')->once()->with('mdgbp_note', Mockery::type('array'));
        expect('wp_next_scheduled')->once()->andReturn(false);
        expect('wp_schedule_event')->once()->andReturn(true);
        expect('register_rest_route')->twice()->with('middag-boilerplate/v1', '/widgets', Mockery::type('array'))->andReturn(true);
        expect('add_menu_page')->once()->andReturn('toplevel_page_middag-boilerplate');
        expect('add_submenu_page')
            ->once()
            ->with('middag-boilerplate', 'MIDDAG Boilerplate Settings', 'Settings', 'manage_options', 'middag-boilerplate-settings', Mockery::type('array'))
            ->andReturn('middag-boilerplate_page_middag-boilerplate-settings')
        ;
        // The asset enqueue fires only for the plugin's own page-hook suffix.
        expect('wp_enqueue_script')->once()->with('middag-boilerplate', 'https://assets.test/assets/dist/app.js', Mockery::type('array'), '9.9.9', true);
        expect('wp_enqueue_style')->once()->with('middag-boilerplate', 'https://assets.test/assets/dist/style.css', Mockery::type('array'), '9.9.9', 'all');

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldIgnoreMissing();
        $GLOBALS['wpdb'] = $wpdb;

        Plugin::boot($root . '/middag-boilerplate.php');

        // Snapshot the boot-time registrations (invoking closures appends more,
        // e.g. the cron 'mdgbp_sync' action, which we must not iterate over).
        $booted = $actions;

        $hooks = array_column($booted, 'hook');
        self::assertContains('init', $hooks);
        self::assertContains('rest_api_init', $hooks);
        self::assertContains('admin_menu', $hooks);
        self::assertContains('admin_init', $hooks);
        self::assertContains('admin_enqueue_scripts', $hooks);

        // Drive each captured closure. The admin_menu app-register closure runs
        // before the enqueue closure (insertion order), so the page-hook suffix
        // is known by the time the asset guard checks it.
        $pageHookSuffix = 'toplevel_page_middag-boilerplate';
        foreach ($booted as $entry) {
            if (!$entry['cb'] instanceof Closure) {
                // admin_init callables ([$admin,'handleInertiaRequest'],
                // [$settings,'register']) are method arrays, not Plugin closures.
                continue;
            }

            if ($entry['hook'] === 'admin_enqueue_scripts') {
                ($entry['cb'])('some-other-page');   // guard: not our page -> no enqueue
                ($entry['cb'])($pageHookSuffix);      // our page -> assets enqueue

                continue;
            }

            ($entry['cb'])();
        }

        // The init closure scheduled the hourly sync via add_action.
        self::assertContains('mdgbp_sync', array_column($actions, 'hook'), 'cron sync hook registered');
    }
}
