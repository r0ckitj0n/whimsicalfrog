<?php

use PHPUnit\Framework\TestCase;

/**
 * Basic test to ensure the helper returns a valid background path.
 */
class LandingBackgroundTest extends TestCase
{
    /**
     * Ensure get_landing_background_path() always returns a file that exists on disk.
     */
    public function testLandingBackgroundPathExists(): void
    {
        // Require the helper definitions – they live in index.php dependencies
        require_once __DIR__ . '/../includes/functions.php';

        if (!function_exists('get_landing_background_path')) {
            $this->markTestSkipped('get_landing_background_path() helper not found.');
        }

        $path = get_landing_background_path();
        $this->assertNotEmpty($path, 'Background path is empty');
        $this->assertFileExists($_SERVER['PWD'] . '/' . ltrim($path, '/'), 'Background file does not exist');
    }
}
