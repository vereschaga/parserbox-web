<?php

namespace AwardWallet\Engine\iberia\RewardAvailability;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use ProxyList;
    use \SeleniumCheckerHelper;

    private $startTime;

    public function InitBrowser()
    {
        $this->startTime = time();
        parent::InitBrowser();

        $this->UseSelenium();
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_100);
        $this->disableImages();
        $this->http->saveScreenshots = true;

        switch (random_int(0, 2)) {
            case 0:
                $this->setProxyBrightData(null, 'static', 'fi');

                break;

            case 1:
                $this->setProxyGoProxies(null, 'in');

                break;

            case 2:
                $this->setProxyGoProxies(null, 'ca');

                break;
        }

        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
        ];
        $this->setScreenResolution($resolutions[array_rand($resolutions)]);
        $this->useCache();
        $this->http->setUserAgent("Mozilla/5.0 (X11; Linux x86_64; rv:84.0) Gecko/20100101 Firefox/84.0");
    }

    public function registerAccount(array $fields)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug(var_export($fields, true), ['pre' => true]);

        $this->checkFields($fields);

        $registerInfo = [];
        $this->http->GetURL('https://www.iberia.com/us/');
        sleep(2);
        // костыль
        // иногда тормозит старт селениума, чтобы watchDog не убивал из-за этого раньше времени
        $this->increaseTimeLimit(time() - $this->startTime);
        $this->http->GetURL('https://registro-iberia.iberia.com/?language=en&origin=US');

        $this->logger->error('Mouse');
        $mover = new \MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = random_int(100000, 500000);
        $mover->steps = random_int(5, 10);

        $email = $this->waitForElement(\WebDriverBy::xpath("//input[@id='RegistroPage:ibPlusShortRegisterCtrl:email']"), 0);
        $pass = $this->waitForElement(\WebDriverBy::xpath("//input[@id='RegistroPage:ibPlusShortRegisterCtrl:password']"), 0);
        $submitBtn = $this->waitForElement(\WebDriverBy::xpath('//button[@onclick="continueRegister()"]'), 0);

        $this->saveResponse();

        if (!$email || !$pass || !$submitBtn) {
            $this->logger->error('no register form or other format');

            return false;
        }

        $mover->moveToElement($email);
        $email->sendKeys($fields['Email']);
//        $mover->sendKeys($email, $fields['Email']);
//        $mover->sendKeys($pass, $fields['Password']);
        $mover->moveToElement($pass);
        $pass->sendKeys($fields['Password']);
        $this->saveResponse();
        $registerInfo = array_merge($registerInfo,
            [
                [
                    'key'  => 'Email',
                    'value'=> $this->driver->executeScript("return document.querySelector('input[id=\"RegistroPage:ibPlusShortRegisterCtrl:email\"]').value;"),
                ],
                [
                    'key'  => 'Password',
                    'value'=> $this->driver->executeScript("return document.querySelector('input[id=\"RegistroPage:ibPlusShortRegisterCtrl:password\"]').value"),
                ],
            ]
        );

        $submitBtn->click();

        $firstName = $this->waitForElement(\WebDriverBy::xpath("//input[@id='RegistroPage:ibPlusShortRegisterCtrl:name']"), 10);
        $surName = $this->waitForElement(\WebDriverBy::xpath("//input[@id='RegistroPage:ibPlusShortRegisterCtrl:surname']"), 0);
        $submitBtn = $this->waitForElement(\WebDriverBy::xpath("//input[@id='RegistroPage:ibPlusShortRegisterCtrl:registerSubmit']"), 0);

        $this->saveResponse();

        if (!$firstName || !$surName || !$submitBtn) {
            if ($msg = $this->waitForElement(\WebDriverBy::xpath("// div[contains(@class,'ib-msg-alert--danger')][contains(.,'We were not able to sign you up')]"))) {
                throw new \ProviderError($msg->getText());
            }
            $this->logger->error('no register form-2 or other format');

            return false;
        }

        $mover->sendKeys($firstName, $fields['FirstName']);
        $mover->sendKeys($surName, $fields['LastName']);

        $check1 = $this->waitForElement(\WebDriverBy::xpath("//label[@for='chkboxAdult']"), 0);
        $check2 = $this->waitForElement(\WebDriverBy::xpath("//label[@for='chkboxTerms']"), 0);
        $check3 = $this->waitForElement(\WebDriverBy::xpath("//label[@for='chkboxData']"), 0);

        $this->saveResponse();

        if (!$check1 || !$check2 || !$check3) {
            $this->logger->error('no check-fields or other format');

            return false;
        }
        $check1->click();
        $this->driver->executeScript('document.querySelector(\'label[for="chkboxTerms"]\').click()');
        $check3->click();

        $this->saveResponse();

        $registerInfo = array_merge($registerInfo,
            [
                [
                    'key'  => 'FirstName',
                    'value'=> $this->driver->executeScript("return document.querySelector('input[id=\"RegistroPage:ibPlusShortRegisterCtrl:name\"]').value"),
                ],
                [
                    'key'  => 'LastName',
                    'value'=> $this->driver->executeScript("return document.querySelector('input[id=\"RegistroPage:ibPlusShortRegisterCtrl:surname\"]').value"),
                ],
            ]
        );
        $this->logger->debug(var_export($registerInfo, true), ['pre' => true]);

        $submitBtn->click();

        $msg = $this->waitForElement(\WebDriverBy::xpath("//p[contains(.,'An error occurred')]/following-sibling::p[1]"), 10);
        $this->saveResponse();

        if ($msg && strpos($msg->getText(), "try with a different email address") !== false) {
            throw new \UserInputError($msg->getText());
        }

        if ($this->waitForElement(\WebDriverBy::xpath("//p[contains(@data-defaulttext,'an error has occurred')]"), 40)) {
            $this->ErrorMessage = json_encode([
                "status"       => "success",
                "message"      => "Registration is successful! Saved with email as login.",
                "active"       => true,
                "login"        => $fields['Email'],
                "registerInfo" => $registerInfo,
            ], JSON_PRETTY_PRINT);

            return true;
        }

        $this->saveResponse();

        if ($dropDown = $this->waitForElement(\WebDriverBy::xpath("//span[@id='loggedUserName']"), 50)) {
            $dropDown->click();
            $membershipNumber = $this->waitForElement(\WebDriverBy::xpath("//div[@id='ibplus-card-info']/p[2]"), 20);
            $number = str_replace('IB', '', trim($membershipNumber->getText()));
            $this->ErrorMessage = json_encode([
                "status"       => "success",
                "message"      => "Registration is successful! Member number: {$number}",
                "active"       => true,
                "login"        => $number,
                "registerInfo" => $registerInfo,
            ], JSON_PRETTY_PRINT);

            return true;
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            "Email" => [
                "Type"     => "string",
                "Caption"  => "Email address",
                "Required" => true,
            ],
            "Password" => [
                "Type"     => "string",
                "Caption"  => "Your password must contain at least 8 characters and meet requirements: uppercase letter, lowercase letter, number and special character (except %, &, ¡, ¿ and ¨). And it shouldn't include any spaces or have been used previously.",
                "Required" => true,
            ],
            "FirstName" => [
                "Type"     => "string",
                "Caption"  => "First Name",
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Caption"  => "LastName",
                "Required" => true,
            ],
        ];
    }

    protected function checkFields(&$fields)
    {
        if (!preg_match("/[A-Z0-9a-z._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/", $fields['Email'])) {
            throw new \UserInputError('Email address contains an incorrect format');
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['FirstName'])) {
            throw new \UserInputError('FirstName contains an incorrect symbol');
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['LastName'])) {
            throw new \UserInputError('LastName contains an incorrect symbol');
        }

        if ((strlen($fields['Password']) < 8 || strlen($fields['Password']) > 20) || !preg_match("/[A-Z]/", $fields['Password'])
            || !preg_match("/[a-z]/", $fields['Password']) || !preg_match("/[0-9]/", $fields['Password']) || strpos($fields['Password'], ' ') !== false
            || !preg_match("/[*!?<>\\ºª|\/\·@#$.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['Password']) || preg_match("/[%&¡¿¨]/", $fields['Password'])
        ) {
            throw new \UserInputError("Your password must contain at least 8 characters and meet requirements: uppercase letter, lowercase letter, number and special character (except %, &, ¡, ¿ and ¨). And it shouldn't include any spaces or have been used previously.");
        }
    }
}
