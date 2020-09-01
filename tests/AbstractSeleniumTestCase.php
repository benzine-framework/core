<?php

namespace Benzine\Tests;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractSeleniumTestCase extends AbstractBaseTestCase
{
    /** @var RemoteWebDriver */
    protected static $webDriver;

    protected static $screenshotsDir;

    protected static $screenshotIndex = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $capabilities = [WebDriverCapabilityType::BROWSER_NAME => 'chrome'];
        self::$webDriver = RemoteWebDriver::create(
            'http://'.$_SERVER['SELENIUM_HOST'].':'.$_SERVER['SELENIUM_PORT'].'/wd/hub',
            $capabilities,
            60000,
            60000
        );

        self::$webDriver->manage()->timeouts()->implicitlyWait(3);

        self::$screenshotsDir = APP_ROOT.'/build/Screenshots/'.date('Y-m-d H-i-s').'/';
        if (!(new Filesystem())->exists(self::$screenshotsDir)) {
            (new Filesystem())->mkdir(self::$screenshotsDir, 0777);
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$webDriver->close();
        parent::tearDownAfterClass();
    }

    protected function takeScreenshot($name): void
    {
        self::$webDriver->takeScreenshot(self::$screenshotsDir.self::$screenshotIndex."_{$name}.jpg");
        ++self::$screenshotIndex;
    }
}
