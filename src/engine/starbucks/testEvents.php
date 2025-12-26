<?php

require __DIR__ . '/../../kernel/public.php';

if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
    exit("Access denied");
}

$capabilities = DesiredCapabilities::chrome();
$driver = RemoteWebDriver::create('http://localhost:4444/wd/hub', $capabilities);
$driver->get('http://awardwallet.dev/engine/aa/testSafariEvents.html');

$input = $driver->findElement(WebDriverBy::id('input'));
$input->sendKeys("a");
//$actions = new WebDriverActions($driver);
//$actions->moveToElement($input);
//$actions->click();
