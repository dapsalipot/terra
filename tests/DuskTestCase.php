<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    /** Set once the driver is up, so only the first test class starts it. */
    private static bool $driverStarted = false;

    /**
     * Prepare for Dusk test execution.
     *
     * One ChromeDriver for the whole run, not one per test class.
     *
     * startChromeDriver() registers an afterClass hook that stops the driver at the end of every
     * class, so each class started a fresh process on port 9515 straight after the last one was
     * killed — and the first test of the next class sometimes connected before the old process had
     * released the port. A full run failed three or four tests every time, always the first test of
     * a class, and every one of them passed when run alone. Starting the process ourselves and
     * stopping it when PHP exits removes the restart, and with it the race.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (static::runningInSail() || self::$driverStarted) {
            return;
        }

        static::$chromeProcess = static::buildChromeProcess(['--port=9515']);
        static::$chromeProcess->start();
        register_shutdown_function(static fn () => static::stopChromeDriver());

        self::waitForDriver();
        self::$driverStarted = true;
    }

    /** Block until the driver accepts connections, so the first test never races its own start-up. */
    private static function waitForDriver(): void
    {
        for ($i = 0; $i < 50; $i++) {
            if (@stream_socket_client('tcp://127.0.0.1:9515', $errno, $errstr, 0.2)) {
                return;
            }

            usleep(100_000);
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            '--no-sandbox',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}
