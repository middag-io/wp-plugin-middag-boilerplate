<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap. Unit tests exercise the pure Domain against the Composer
 * autoloader; Integration tests mock the WordPress runtime per-test with
 * brain/monkey.
 */
require dirname(__DIR__) . '/vendor/autoload.php';
