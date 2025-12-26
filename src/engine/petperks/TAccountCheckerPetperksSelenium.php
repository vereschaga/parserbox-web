<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerPetperksSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    private const WAIT_TIMEOUT = 20;
    private const CONFIGS = [
        'firefox-100' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => SeleniumFinderRequest::FIREFOX_100,
        ],
        'chrome-100' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_100,
        ],
        'chromium-80' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROMIUM,
            'browser-version' => SeleniumFinderRequest::CHROMIUM_80,
        ],
        'chrome-84' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_84,
        ],
        'chrome-95' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => SeleniumFinderRequest::CHROME_95,
        ],
        'puppeteer-103' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_CHROME_PUPPETEER,
            'browser-version' => SeleniumFinderRequest::CHROME_PUPPETEER_103,
        ],
        'firefox-84' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => SeleniumFinderRequest::FIREFOX_84,
        ],
        'chrome-94' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_94,
        ],
        'firefox-playwright-100' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT,
            'browser-version' => SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_100,
        ],
        'firefox-playwright-102' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT,
            'browser-version' => SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_102,
        ],
        'firefox-playwright-101' => [
            'browser-family'  => SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT,
            'browser-version' => SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_101,
        ],
    ];
    private $configsWithOs = [];
    private $config;
    private $choice;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->setConfig();

        $this->setProxyGoProxies();

        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
            [1920, 1080],
        ];
        $resolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($resolution);
        $this->seleniumRequest->request(
            $this->configsWithOs[$this->config]['browser-family'],
            $this->configsWithOs[$this->config]['browser-version']
        );
        $this->seleniumRequest->setOs($this->configsWithOs[$this->config]['os']);

        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        $this->http->saveScreenshots = true;
        $this->usePacFile(false);
    }

    public function LoadLoginForm(): bool
    {
        // Please enter valid email address.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();

        $this->http->GetURL('https://www.petsmart.com/account/');

        $login = $this->waitForElement(WebDriverBy::xpath('//form[@id="signInForm"]//input[@name="username"]'), self::WAIT_TIMEOUT);
        $password = $this->waitForElement(WebDriverBy::xpath('//form[@id="signInForm"]//input[@name="password"]'), 0);
        $submit = $this->waitForElement(WebDriverBy::xpath('//form[@id="signInForm"]//button[@id="login"]'), 0);

        if (!$login || !$password || !$submit) {
            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $password->sendKeys($this->AccountFields['Pass']);
        $submit->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //span[contains(text(), "hi, ")]
            | //div[@id = "account-login"]//div[contains(@class, "login-errors") and not(@style = "display:none;")]
            | //span[contains(@class, "gtm-error-msg")]
        '), self::WAIT_TIMEOUT);

        $this->saveResponse();

        if (
            $this->http->FindSingleNode('//div[contains(@class, "account-login-dialog") and not(contains(@class, "hidden-wrapper"))]//div[@class="login-errors" and contains(text(), "An unknown error has occurred. Please try again later")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]/text()')
        ) {
            $this->logger->debug('looks like a block');
            $this->markConfigAsBad();

            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindSingleNode('//div[contains(@class, "account-login-dialog") and not(contains(@class, "hidden-wrapper"))]//div[@class="login-errors" and not(contains(text(), "An unknown error has occurred. Please try again later"))]')
            || $this->http->FindSingleNode('//span[contains(text(), "hi, ")]')
        ) {
            $this->logger->debug('looks like a success');
            $this->markConfigAsSuccess();

            return false;
        }

        return $this->checkErrors();
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("//h1[text()='Transmission Problems']")) {
            throw new CheckException('The request couldn\'t be processed correctly. Please try again soon.', ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "We\'re sorry for the inconvenience. We\'ll be up and running soon.")]
                | //p[contains(text(), "Our furry friends are working on upgrades.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg('/var proxied = window.XMLHttpRequest.prototype.send;/')) {
            $this->logger->debug('looks like a site hang');
            $this->markConfigAsUnstable();

            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function markConfigAsSuccess(): void
    {
        $this->logger->info("marking config {$this->config} as success");
        $this->sendNotification("refs #24889 petperks - success config was found // IZ");
        Cache::getInstance()->set('choice_success_config_' . $this->config, 1, 60 * 60);
    }

    private function markConfigAsBad(): void
    {
        $this->logger->info("marking config {$this->config} as bad");
        Cache::getInstance()->set('choice_bad_config_' . $this->config, 1, 60 * 60);
    }

    private function markConfigAsUnstable(): void
    {
        $this->logger->info("marking config {$this->config} as unstable");
        Cache::getInstance()->set('choice_unstable_config_' . $this->config, 1, 60 * 60);
    }

    private function setConfig()
    {
        $oses = [
            SeleniumFinderRequest::OS_MAC_M1,
            SeleniumFinderRequest::OS_MAC,
            SeleniumFinderRequest::OS_WINDOWS,
            SeleniumFinderRequest::OS_LINUX,
        ];

        foreach ($oses as $os) {
            foreach (self::CONFIGS as $configName => $config) {
                $configNameFull = $configName . '-' . $os;
                $this->configsWithOs[$configNameFull] = [
                    'browser-family'  => self::CONFIGS[$configName]['browser-family'],
                    'browser-version' => self::CONFIGS[$configName]['browser-version'],
                    'os'              => $os,
                ];
            }
        }

        $configs = $this->configsWithOs;

        $successConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('choice_success_config_' . $key) === 1;
        });

        $badConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('choice_bad_config_' . $key) === 1;
        });

        $unstableConfigs = array_filter(array_keys($configs), function (string $key) {
            return Cache::getInstance()->get('choice_unstable_config_' . $key) === 1;
        });

        $neutralConfigs = array_filter(array_keys($configs), function (string $key) use ($successConfigs, $badConfigs, $unstableConfigs) {
            return !in_array($key, array_merge($successConfigs, $badConfigs, $unstableConfigs));
        });

        $this->logger->info("found " . count($successConfigs) . " success configs");
        $this->logger->info("found " . count($badConfigs) . " bad configs");
        $this->logger->info("found " . count($unstableConfigs) . " unstable configs");
        $this->logger->info("found " . count($neutralConfigs) . " neutral configs");

        /**
         * Code for searching new success configs. Do not delete // IZ.
         */
        if (count($neutralConfigs) > 0) {
            $this->config = $neutralConfigs[array_rand($neutralConfigs)];
        } else {
            $this->config = $configs[array_rand($configs)];
        }
        $this->logger->info("selected config $this->config");

        /*
        if (count($successConfigs) > 0) {
            $this->logger->info('selecting config from success configs');
            $this->config = $successConfigs[array_rand($successConfigs)];
        } elseif (count($neutralConfigs) > 0) {
            $this->logger->info('selecting config from neutral configs');
            $this->config = $neutralConfigs[array_rand($neutralConfigs)];
        } elseif (count($unstableConfigs) > 0) {
            $this->logger->info('selecting config from unstable configs');
            $this->config = $unstableConfigs[array_rand($unstableConfigs)];
        } else {
            $this->logger->info('selecting config from all configs');
            $this->config = $configs[array_rand($configs)];
        }

        $this->logger->info('selected config ' . $this->config);

        $this->logger->info('detailed config info', ['Header' => 2]);
        $this->logger->info('all configs', ['Header' => 3]);

        foreach ($this->configsWithOs as $configName => $config) {
            $this->logger->info($configName);
        }

        if (count($successConfigs) > 0) {
            $this->logger->info('success configs', ['Header' => 3]);

            foreach ($successConfigs as $config) {
                $this->logger->info($config);
            }
        }

        if (count($badConfigs) > 0) {
            $this->logger->info('bad configs', ['Header' => 3]);

            foreach ($badConfigs as $config) {
                $this->logger->info($config);
            }
        }

        if (count($unstableConfigs) > 0) {
            $this->logger->info('unstable configs', ['Header' => 3]);

            foreach ($unstableConfigs as $config) {
                $this->logger->info($config);
            }
        }

        if (count($neutralConfigs) == 0) {
            $this->logger->info('neutral configs', ['Header' => 3]);

            foreach ($neutralConfigs as $config) {
                $this->logger->info($config);
            }
        }
        */
    }
}
