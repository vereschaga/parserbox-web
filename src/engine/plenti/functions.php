<?php

use AwardWallet\Engine\ProxyList;

/**
 * Class TAccountCheckerPlenti
 * Display name: Plenti (Plenti Points)
 * Database ID: 1260
 * Author: APuzakov
 * Created: 06.05.2015 6:37.
 */
class TAccountCheckerPlenti extends TAccountChecker
{
//    use SeleniumCheckerHelper;
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
//        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
//            $this->http->SetProxy($this->proxyDOP());
//        }
//        $this->InitSeleniumBrowser($this->http->GetProxy());
//        $this->http->TimeLimit = 600;
//        $this->keepCookies(false);

        $this->KeepState = true;
//        $this->http->SetProxy($this->proxyReCaptcha());
        $this->setProxyBrightData(); //todo
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL('https://www.plenti.com/points-activity');
//        $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'Logout')]"), 10, false);
//        $this->saveResponse();
//        if ($logout)
        if ($this->http->FindSingleNode("(//a[contains(@href, 'Logout')]/@href)[1]") && !$this->http->ParseForm("Login")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.plenti.com/points-activity");

        // retries
        if ($this->http->Response['code'] == 0) {
            throw new CheckRetryNeededException(3, 10);
        }

        if (!$this->http->ParseForm("Login")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("alias", $this->AccountFields['Login']);
        $this->http->SetInputValue("secret", $this->AccountFields['Pass']);
        $this->http->SetInputValue("permanentLogin", "on");
        $this->http->SetInputValue("rememberMyAlias", "on");
        $loginButtonInputName = $this->http->FindSingleNode('(//form[@name="Login"]//input[contains(@name, "loginButton-") or contains(@name, "login-button")]/@name)[1]');

        if (!$loginButtonInputName) {
            $this->DebugInfo = 'Failed to find login button';
            $this->http->Log($this->DebugInfo, LOG_LEVEL_ERROR);

            return false;
        }
        $this->http->SetInputValue($loginButtonInputName, "Log in");

        $captcha = $this->parseCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }

        /*$this->driver->manage()->window()->maximize();
        $this->http->GetURL("https://www.plenti.com/points-activity");
        if (!$this->http->ParseForm("Login"))
            return $this->checkErrors();

        if ($error = $this->http->FindPreg("/(?:Secure Connection Failed|Unable to connect|The proxy server is refusing connections|Failed to connect parent proxy|The connection has timed out)/")) {
            $this->http->Log($error, LOG_LEVEL_ERROR);
            throw new CheckRetryNeededException(5, 10);
        }

        $menu = $this->waitForElement(WebDriverBy::xpath("//button[@class = 'navbar-toggle']"), 3);
        if (!empty($menu))
            $menu->click();
        $signIn = $this->waitForElement(WebDriverBy::xpath("//a[@class = 'dropdown-toggle']"), 10);
        if (empty($signIn))
            return $this->checkErrors();
        $signIn->click();

        $xpathForm = "//h3[contains(text(), 'Log in to your Plenti Account:')]/ancestor::form";
        $this->waitForElement(WebDriverBy::xpath($xpathForm), 15);

        $login = $this->waitForElement(WebDriverBy::xpath("$xpathForm//input[@id = 'alias']"), 15);
        $this->saveResponse();
        if (empty($login))
            return $this->checkErrors();
        $login->sendKeys($this->AccountFields['Login']);
        $password = $this->waitForElement(WebDriverBy::xpath("$xpathForm//input[@id = 'secret']"), 0);
        if (!$password) {
            $this->http->Log('Failed to find password input field', LOG_LEVEL_ERROR);
            return false;
        }
        $password->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();

        // captcha
        $iframe = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']//iframe"), 5);
        if ($iframe) {
            $this->driver->switchTo()->frame($iframe);
            $recaptchaAnchor = $this->waitForElement(WebDriverBy::id("recaptcha-anchor"), 20);
            if (!$recaptchaAnchor) {
                $this->http->Log('Failed to find reCaptcha "I am not a robot" button');
                throw new CheckRetryNeededException(3, 7);
            }// if (!$recaptchaAnchor)
            $recaptchaAnchor->click();

            $this->http->Log("wait captcha iframe");
            $this->driver->switchTo()->defaultContent();
            $iframe2 = $this->waitForElement(WebDriverBy::xpath("$xpathForm//iframe[@title = 'recaptcha challenge']"), 10, true);
            $this->saveResponse();

            if ($iframe2) {

                if (!$status) {
                    $this->http->Log('Failed to pass captcha');
                    throw new CheckRetryNeededException(3, 2, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }// if (!$status)
            }// if ($iframe2)
        }// if ($iframe)
        // submit form
        $loginButton = $this->waitForElement(WebDriverBy::xpath("$xpathForm//input[contains(@value, 'Log In')]"), 0);
        if (!$loginButton) {
            $this->logger->error('Failed to find login button');
            return false;
        }// if (!$loginButton)
        $this->driver->executeScript("$('form:has(h3:contains(\"Log in to your Plenti Account:\")) input[name *= \"loginButton\"]').get(0).click()");
//        $loginButton->click();*/

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.plenti.com/";

        return $arg;
    }

    public function checkErrors()
    {
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'The Plenti website is undergoing scheduled maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Your request could not be processed. Please try again later.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Your request could not be processed. Please try again later.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

//        $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'Logout')]"), 10, false);
//        $this->saveResponse();
//        if ($logout)
        if ($this->http->FindSingleNode("(//a[contains(@href, 'Logout')]/@href)[1]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//p[contains(@class,'alert-error')]")) {
            if (strstr($message, "Sorry, but we've locked your account for your protection")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            } else {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://www.plenti.com/points-activity');
//        $this->saveResponse();
        // Your Available Points
        $this->SetBalance($this->http->FindSingleNode("//div[contains(@class, 'pa-totalpoints-amount')]"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@class='username']/text()[1]", null, true, "/Hi\s*([^<]+)/")));
        // Pending Points
        $this->SetProperty("PendingPoints", $this->http->FindSingleNode("//div[contains(@class, 'pa-blockedpoints-amount')]"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Balance from menu
            $this->SetBalance($this->http->FindSingleNode("//span[@class='username']//span[contains(@class, 'userpoints')]", null, true, "/([\s\d\.\,\-])+/ims"));
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        $this->SetExpirationDate(strtotime("10 Jul 2018"));

        return;

        // refs #13683
        if ($this->Balance > 0 && $this->http->ParseForm("filter-form")) {
            $this->logger->info('Expiration date', ['Header' => 3]);
            $year = date("Y") - 3;
            $from = "{$year}-01-01";
            $this->http->SetInputValue("input_filter_date_from[day]", "01");
            $this->http->SetInputValue("input_filter_date_from[month]", "01");
            $this->http->SetInputValue("input_filter_date_from[year]", $year);
            $this->http->SetInputValue("submit1", "See Transactions");

            if (!$this->http->PostForm()) {
                return;
            }
            $balance = str_replace(',', '', $this->Balance);
            $this->logger->debug("Balance: {$balance}");
            $transactions = $this->http->XPath->query("//table[@id = 'transactions-table']//tr[contains(@class, 'base-row') and contains(@class, 'redeemable') and td]");
            $this->logger->notice("Total {$transactions->length} transactions were found");
            $i = $this->getExpDate($transactions, $balance, 0);

            if (!isset($this->Properties['ExpiringBalance'])
                && ($identity = $this->http->FindPreg("/identity\":\{\"salt\":\"([^\"]+)/"))
                && ($identityValue = $this->http->FindPreg("/identity\":\{\"salt\":\"[^\"]+\"\s*\,\s*\"hash\":\"([^\"]+)/"))) {
                $to = date("Y-m-d");
                $page = 2;

                do {
                    $this->logger->debug("Page #{$page}");
                    $this->http->GetURL("https://www.plenti.com/points-activity?txlistpage={$page}&ajax=1&from={$from}&to={$to}&identityValue={$identityValue}&identityKey={$identity}");
                    $response = $this->http->JsonLog(null, false);

                    if (isset($response->html)) {
                        $this->http->SetBody($response->html, true);
                        $transactions = $this->http->XPath->query("//tr[contains(@class, 'base-row') and contains(@class, 'redeemable') and td]");
                        $i = $this->getExpDate($transactions, $balance, $i);
                    }// if (isset($response->html))
                    $page++;
                } while (!isset($this->Properties['ExpiringBalance']) && $page < 7 && !empty($response->html) && $i);
            }// if (!isset($this->Properties['ExpiringBalance']) ...
        }// if ($this->Balance > 0 && $this->http->ParseForm("filter-form"))
    }

    /**
     * @param \DOMNodeList $transactions
     * @param string $balance
     * @param int $i
     *
     * @return int
     */
    public function getExpDate($transactions, &$balance, $i)
    {
        $this->logger->debug(__METHOD__);
        $this->logger->notice("Total {$transactions->length} transactions were found");

        for ($j = 0; $j < $transactions->length; $j++) {
            $node = $transactions->item($j);
            $date = $this->http->FindSingleNode("td[contains(@class, 'purchaseDate')]/div", $node);
            $points = $this->http->FindSingleNode("td[contains(@class, 'points')]/div", $node, true, self::BALANCE_REGEXP);

            if (isset($date, $points)) {
                $n = $i + $j + 1;
                $pointsEarned[$n] = [
                    'date'   => $date,
                    'points' => str_replace(',', '', $points),
                ];
                $balance -= $pointsEarned[$n]['points'];
                $this->logger->debug("#" . ($n) . " Date {$pointsEarned[$n]['date']} - {$pointsEarned[$n]['points']} / Balance: $balance");

                if ($balance <= 0) {
                    $this->logger->debug("Date " . $pointsEarned[$n]['date']);
                    // Earning Date     // refs #4936
                    $this->SetProperty("EarningDate", $pointsEarned[$n]['date']);
                    // Expiration Date
                    if ($exp = strtotime($pointsEarned[$n]['date'])) {
                        $exp = strtotime("+24 month", $exp);
                        $this->SetExpirationDate(strtotime("31 Dec " . date('Y', $exp)));
                    }// if ($exp = strtotime($lastActivity))
                    // Points to Expire
                    $balance += $pointsEarned[$n]['points'];

                    for ($k = $n - 1; $k >= 0; $k--) {
                        $this->logger->debug("> Balance: {$balance}");

                        if (isset($pointsEarned[$k]['date']) && $pointsEarned[$n]['date'] == $pointsEarned[$k]['date']) {
                            $balance += $pointsEarned[$k]['points'];
                        }// if (isset($pointsEarned[$k]['date']) && $pointsEarned[$n]['date'] == $pointsEarned[$k]['date'])
                    }// for ($k = $i - 1; $k >= 0; $k--)
                    $this->SetProperty("ExpiringBalance", number_format($balance));

                    break;
                }// if ($balance <= 0)
            }//if (isset($date, $points))
        }// for ($i = 0; $i < $transactions->length; $i++)

        return $n ?? null;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("(//form[@name = 'Login']//div[@class = 'g-recaptcha']/@data-sitekey)[1]");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }
}
