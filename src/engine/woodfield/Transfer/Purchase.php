<?php

namespace AwardWallet\Engine\woodfield\Transfer;

use AwardWallet\Engine\ProxyList;

class Purchase extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    /** @var \TAccountChecker */
    public $timeout = 10;

    public function initBrowser()
    {
//        parent::InitBrowser();
//        $this->ArchiveLogs = true;

        $this->AccountFields['BrowserState'] = null;
        $this->useSelenium();

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $this->setProxyBrightData();
        } else {
            $this->http->SetProxy('localhost:8000');
        }

//        $this->InitSeleniumBrowser($this->http->GetProxy());
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function LoginInternal(array $fields)
    {
        $this->http->GetURL("https://www.lq.com/en/la-quinta-returns/buy-points.html");

        if ($elem = $this->waitForElement(\WebDriverBy::id('returnsid'), $this->timeout)) {
            $this->driver->executeScript("$('#passwordWatermark').hide(); $('#password').show()");
            $elem->clear();
            $elem->sendKeys($fields['Login']);
        }

        if ($elem = $this->waitForElement(\WebDriverBy::id('password'), $this->timeout)) {
            $elem->sendKeys($fields['Password']);
        }

        if ($elem = $this->waitForElement(\WebDriverBy::id('btnSignIn'), $this->timeout)) {
            $elem->click();
        }
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $status = false;

        try {
            $status = $this->purchaseMilesInternal($fields, $numberOfMiles, $creditCard);
        } catch (\CheckException $e) {
            $this->saveResponse();

            throw $e;
        } catch (\Exception $e) {
            $this->log($e->getMessage(), LOG_LEVEL_ERROR);
            $this->log('Last page content:', LOG_LEVEL_ERROR);
//            $this->log(print_r($e));
            $this->saveResponse();

            return false;
        }
        $this->saveResponse();

        return $status;
    }

    public function purchaseMilesInternal(array $fields, $numberOfMiles, $creditCard)
    {
        $this->LoginInternal($fields);

        if ($elem = $this->waitForElement(\WebDriverBy::xpath("(//div[@class='pointsDotComComponent'])[1]//button"), $this->timeout)) {
            $elem->click();
        }

//        sleep(60);
        return false;
//        $allCookies = array_merge($this->http->GetCookies(".lq.com"), $this->http->GetCookies(".lq.com", "/", true));
//        $this->http->Log("<pre>".var_export($allCookies, true)."</pre>", false);
//
//        $http2 = clone $this;
//        $this->http->brotherBrowser($http2);
//
//        $this->http->Log("Running Selenium...");
//        $http2->UseSelenium();
//        $http2->http->SetProxy($this->http->GetProxy());
//        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION)
//            $http2->http->SetProxy($this->proxyDOP());
//        else
//            $http2->http->SetProxy('localhost:8000'); // This provider should be tested via proxy even locally
//        $http2->InitSeleniumBrowser($this->http->GetProxy());
//
//        $http2->http->driver->start();
//        $http2->Start();
//
//        $cookies = array();
//        $http2->http->GetURL("http://www.lq.com/lq/returns/");
//        foreach ($allCookies as $key => $value) {
//            $http2->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".lq.com"]);
//        }
//        $this->http->Log("<pre>".var_export($cookies, true)."</pre>", false);
//
//        $cookies = $http2->driver->manage()->getCookies();
//        $this->http->Log("<pre>".var_export($cookies, true)."</pre>", false);
//
//        $http2->http->GetURL("https://www.lq.com/en/la-quinta-returns/buy-points.html");
//
//        sleep(20);
//        $http2->http->cleanup();
    }

    public function getPurchaseMilesFields()
    {
        return [
            'Email' => [
                'Type'     => 'string',
                'Required' => true,
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Required' => true,
            ],
            'Login' => [
                'Type'     => 'string',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Required' => true,
            ],
        ];
    }

    protected function logPageSource($logLevel = null)
    {
        $this->log($this->driver->executeScript('return document.documentElement.innerHTML'), $logLevel);
    }

    private function log($msg, $loglevel = null)
    {
        $this->http->Log($msg, $loglevel);
    }
}
