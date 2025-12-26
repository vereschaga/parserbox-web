<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerOcbc extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency'])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], $properties['Currency'] . "%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
//        $arFields["Login2"]["InputType"] = 'password';
        $arFields["Login2"]["Note"] = 'NRIC/passport no. e.g. S1234567A';
        ArrayInsert($arFields, "Login2", true, ["Login3" => [
            "Type"     => "string",
            "Required" => true,
            "Caption"  => "Date of Birth",
            "Note"     => 'DD.MM.YYYY',
        ]]);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->setProxyGoProxies();

        $this->UseSelenium();
        $this->http->saveScreenshots = true;
        $this->useFirefoxPlaywright();
    }

    /*
    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://internet.ocbc.com/rewards/", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }
    */

    public function LoadLoginForm()
    {
        $date = explode('.', $this->AccountFields['Login3']);

        if (count($date) != 3) {
            throw new CheckException('Invalid login information. Please, make sure you have used the following date format for your Date of Birth: DD.MM.YYYY', ACCOUNT_INVALID_PASSWORD);
        }/*review*/

        $this->http->removeCookies();
        $this->http->GetURL('https://internet.ocbc.com/rewards/');

        /*
        if (!$this->http->ParseForm('form__login')) {
            $this->checkBlock();

            return $this->checkErrors();
        }
        */
        [$day, $month, $year] = $date;

        $loginFormBtn = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Login')]"), 7);
        $this->saveResponse();

        if (!$loginFormBtn) {
            return false;
        }

        $loginFormBtn->click();

        $login = $this->waitForElement(WebDriverBy::xpath('//input[contains(@name, "cardNumberLast")]'), 7);
        $loginLastCharacters = $this->waitForElement(WebDriverBy::xpath('//input[contains(@name, "last4NRICCharacters")]'), 0);
        $dateOfBirthDay = $this->waitForElement(WebDriverBy::xpath("//div[input[@name = 'dateOfBirthDay']]"), 0);
        $dateOfBirthMonth = $this->waitForElement(WebDriverBy::xpath("//div[input[@name = 'dateOfBirthMonth']]"), 0);
        $dateOfBirthYear = $this->waitForElement(WebDriverBy::xpath("//div[input[@name = 'dateOfBirthYear']]"), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "dialog-submit-btn-wrapper")]/button[contains(text(), "Login")]'), 0);
        // save page to logs
        $this->saveResponse();

        if (!$login || !$loginLastCharacters || !$btn) {
            $this->logger->error("something went wrong");

            return false;
        }

        $this->logger->debug("set login");
        $login->sendKeys($this->AccountFields['Login']);
        $this->logger->debug("set NRIC");
        $loginLastCharacters->sendKeys(substr($this->AccountFields['Login2'], -4));

        $this->logger->debug("set dateOfBirthDay");
//        $dateOfBirthDay->sendKeys($day);
        $dateOfBirthDay->click();
        $this->saveResponse();
        $this->driver->executeScript("document.querySelector('li[data-value=\"{$day}\"]').click()");

        $this->logger->debug("set dateOfBirthMonth");
//        $dateOfBirthMonth->sendKeys($month);
        $dateOfBirthMonth->click();
        $this->saveResponse();
        $this->driver->executeScript("document.querySelector('li[data-value=\"{$month}\"]').click()");

        $this->logger->debug("set dateOfBirthYear");
        $dateOfBirthYear->click();
//        $dateOfBirthYear->sendKeys($year);
        $this->saveResponse();
        $this->driver->executeScript("document.querySelector('li[data-value=\"{$year}\"]').click()");

        $this->logger->debug("click btn");
        $this->saveResponse();
        $btn->click();

        return true;

        $data = [
            "Last8Account" => $this->AccountFields['Login'],
            "NRIC"         => substr($this->AccountFields['Login2'], -4),
            "DOBDay"       => $day,
            "DOBMonth"     => $month,
            "DOBYear"      => $year,
        ];
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/json; charset=utf-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->PostURL('https://internet.ocbc.com/rewards/Account/Login', json_encode($data), $headers);

        return true;
    }

    private function checkBlock()
    {
        $this->logger->debug(__METHOD__);

        if ($this->http->FindPreg("/<script>\(window.BOOMR_mq=window.BOOMR_mq/")) {
            $this->markProxyAsInvalid();
            throw new CheckRetryNeededException(3, 0);
        }
    }

    public function Login()
    {
        $logout = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Logout")]'), 10);

        if ($logout) {
            $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "OCBC$")]/following-sibling::div'), 10);
        }

        $this->saveResponse();

        if ($logout || $this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[@data-testid=\"test-id-input-error\"]")) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Sorry, we cannot find you in our system.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Your Rewards balance
        $this->SetBalance($this->http->FindSingleNode('//div[contains(text(), "OCBC$")]/following-sibling::div'));
        // Currency
        $this->SetProperty('Currency', $this->http->FindSingleNode("//div[contains(text(), 'OCBC$')]"));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//span[contains(text(), "elcome,")]/following-sibling::span')));

        if ($viewInfo = $this->waitForElement(WebDriverBy::xpath('//a[contains(., "View Expiry Information")]'), 0)) {
            $viewInfo->click();
            $this->saveResponse();
        }

        $expNodes = $this->http->XPath->query("//div[@data-testid = 'test-id-custom-dialog-content']//div[contains(@class, 'balance_expriry_row')]/div[contains(text(), 'OCBC$')]/following-sibling::div[position() > 1]");
        $expDatesNodes = $this->http->XPath->query("//div[@data-testid = 'test-id-custom-dialog-content']//div[contains(@class, 'rewards__header-row')]/div[contains(text(), 'Rewards Points')]/following-sibling::div[contains(@class, 'rewards__header-expiry')]");
        $this->logger->debug("Total {$expNodes->length} / {$expDatesNodes->length} exp nodes were found");

        for ($i = 0; $i < $expNodes->length; $i++) {
            $expDate = $this->http->FindPreg("/(\w{3} \d{4})/", false, $expDatesNodes->item($i)->nodeValue);
            $expPoints = trim($expNodes->item($i)->nodeValue);
            $this->logger->debug("[Date]: $expDate - $expPoints");

            if ($expPoints != '-' && $expPoints > 0) {
                $this->SetExpirationDate(strtotime("+1 month -1 day", strtotime($expDate)));
                $this->SetProperty('ExpiringBalance', $expPoints);

                break;
            }// if ($expPoints != '-' && $expPoints > 0)
        }// for ($i = 0; $i < $expNodes->length; $i++)

        $this->parseSubAccount('Bonus Miles');
        $this->parseSubAccount('VOYAGE Miles');
        $this->parseSubAccount('Travel$'); // AccountID: 4725393
        $this->parseSubAccount('Robinsons$'); // AccountID: 4709060
        $this->parseSubAccount('Limo Rides'); // AccountID: 4837562
        $this->parseSubAccount('90°N Miles'); // AccountID: 4740114

        // You do not have any rewards. Spend on your credit card or sign up for a new one now.
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && !empty($this->Properties['Name'])
            && (isset($this->Properties['SubAccounts']) && $this->Properties['SubAccounts'] > 0)
        ) {
            $this->SetBalanceNA();
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('(//li[not(contains(@class, "hidden"))]/a[contains(@onclick, "logout")])[1]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // maintenance
        if ($iframe = $this->http->FindSingleNode('//iframe[@src = "https://www.ocbc.com/personal-banking/maintenance/message.html"]/@src')) {
            $this->http->GetURL($iframe);

            if ($this->http->FindPreg('/client.open\(\'GET\', \'https:\/\/www.ocbc.com\/personal-banking\/maintenance\/url.html\'\);/')) {
                $this->http->GetURL("https://www.ocbc.com/personal-banking/maintenance/maintenance.html");
            }
        }

        if ($message = $this->http->FindSingleNode("//p[contains(., 'carrying out system maintenance on')]")) {
            throw new CheckException("To serve you better, we will be carrying out system maintenance. We apologise for any inconvenience and thank you for your patience.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//h2[contains(text(), '500 - Internal server error.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseSubAccount($displayName)
    {
        $this->logger->notice(__METHOD__);
        $balance = $this->http->FindSingleNode("//div[@data-testid = 'test-id-custom-dialog-content']//div[contains(@class, 'balance_expriry_row')]/div[contains(text(), '{$displayName}')]/following-sibling::div[1]");
        $this->logger->debug("[{$displayName}]: $balance");

        if (isset($balance) && trim($balance) != '-') {
            $subAcc = [
                'Code'        => 'ocbc' . str_replace([' ', '$'], ['', 'Bucks'], $displayName),
                'DisplayName' => $displayName,
                'Balance'     => $balance,
            ];
            $expNodes = $this->http->XPath->query("//div[@data-testid = 'test-id-custom-dialog-content']//div[contains(@class, 'balance_expriry_row')]/div[contains(text(), '{$displayName}')]/following-sibling::div[position() > 1]");
            $expDatesNodes = $this->http->XPath->query("//div[@data-testid = 'test-id-custom-dialog-content']//div[contains(@class, 'rewards__header-row')]/div[contains(text(), 'Rewards Points')]/following-sibling::div[contains(@class, 'rewards__header-expiry')]");
            $this->logger->debug("Total {$expNodes->length} / {$expDatesNodes->length} exp nodes were found");

            for ($i = 0; $i < $expNodes->length; $i++) {
                $expDate = $this->http->FindPreg("/(\w{3} \d{4})/", false, $expDatesNodes->item($i)->nodeValue);
                $expPoints = trim($expNodes->item($i)->nodeValue);
                $this->logger->debug("[Date]: $expDate - $expPoints");

                if ($expPoints != '-' && $expPoints > 0) {
                    $subAcc['ExpirationDate'] = strtotime("+1 month -1 day", strtotime($expDate));
                    $subAcc['ExpiringBalance'] = $expPoints;

                    break;
                }// if ($expPoints != '-' && $expPoints > 0)
            }// foreach ($expNodes as $expNode)
            $this->AddSubAccount($subAcc, true);
        }// if (isset($balance) && trim($balance) != '-')
    }
}
