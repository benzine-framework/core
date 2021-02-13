<?php

namespace Benzine\Tests;

use Benzine\App;
use Benzine\Services\EnvironmentService;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Facebook\WebDriver\WebDriverBy;
use Monolog\Logger;
use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractSeleniumTestCase extends AbstractBaseTestCase
{
    protected static RemoteWebDriver $webDriver;
    protected static EnvironmentService $environmentService;
    protected static Logger $logger;
    protected static string $screenshotsDir;
    protected static int $screenshotIndex = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$logger = App::DI(Logger::class);
        self::$environmentService = App::DI(EnvironmentService::class);

        $capabilities = [WebDriverCapabilityType::BROWSER_NAME => 'chrome'];
        self::$webDriver = RemoteWebDriver::create(
            sprintf(
                'http://%s:%d/wd/hub',
                self::$environmentService->get('SELENIUM_HOST', 'selenium'),
                self::$environmentService->get('SELENIUM_PORT', 4444)
            ),
            $capabilities,
            60000,
            60000
        );

        self::$webDriver->manage()->timeouts()->implicitlyWait(3);

        self::$screenshotsDir = APP_ROOT.'/build/Screenshots/'.date('Y-m-d H-i-s').'/';
    }

    public static function tearDownAfterClass(): void
    {
        self::$webDriver->close();
        parent::tearDownAfterClass();
    }

    public function getWindow(): RemoteWebDriver
    {
        return self::$webDriver;
    }

    public function select(string $sizzleSelector): ?RemoteWebElement
    {
        try {
            return self::$webDriver->findElement(WebDriverBy::cssSelector($sizzleSelector));
        } catch (NoSuchElementException $noSuchElementException) {
            self::$logger->debug("Couldn't find a match for sizzle selector '{$sizzleSelector}'");

            return null;
        }
    }

    protected function takeScreenshot($name): void
    {
        if (!(new Filesystem())->exists(self::$screenshotsDir)) {
            (new Filesystem())->mkdir(self::$screenshotsDir, 0777);
        }
        self::$webDriver->takeScreenshot(self::$screenshotsDir.self::$screenshotIndex."_{$name}.jpg");
        ++self::$screenshotIndex;
    }
}
