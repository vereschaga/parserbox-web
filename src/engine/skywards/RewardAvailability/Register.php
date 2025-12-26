<?php

namespace AwardWallet\Engine\skywards\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\skywards\RewardAvailability\Helpers\FormFieldsInformation;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \AwardWallet\Engine\ProxyList;

    private $timeout = 30;
    private $fields;
    private $registerInfo = [];

    /*Main*/

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->UseSelenium();
        $this->keepCookies(false);

        $array = ['us', 'es', 'au', 'fi'];
        $targeting = $array[array_rand($array)];
        $this->setProxyGoProxies(null, $targeting);

//        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
//        $this->seleniumOptions->addHideSeleniumExtension = false;
//        $this->http->setUserAgent(null);

        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);

        $request = FingerprintRequest::chrome();
        $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if ($fingerprint !== null) {
            $this->logger->debug("set fingerprint");
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
        }

        $this->disableImages();
        $this->http->saveScreenshots = true;

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
        $this->usePacFile(false);
    }

    public function registerAccount(array $fields)
    {
        $this->logger->notice(__METHOD__);

        $this->fields = $fields;
        $this->logger->debug(var_export($fields, true), ['pre' => true]);

        $this->modifyFields($this->fields);
        $this->checkFields($this->fields);

        $this->register();

        return $this->checkLogIn();
    }

    public function getRegisterFields()
    {
        $this->logger->notice(__METHOD__);

        return FormFieldsInformation::getRegisterFields();
    }

    protected function checkFields(&$fields)
    {
        $this->logger->notice(__METHOD__);

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['FirstName']) || strpos($fields['FirstName'], ' ') !== false
            || strlen($fields['FirstName']) < 2 || strlen($fields['FirstName']) > 25) {
            throw new \UserInputError('FirstName contains an incorrect symbol');
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['LastName']) || strpos($fields['LastName'], ' ') !== false
            || strlen($fields['LastName']) < 2 || strlen($fields['LastName']) > 26) {
            throw new \UserInputError('LastName contains an incorrect symbol');
        }

        if (strlen($fields['PhoneNumber']) > 10 || preg_match("/[a-zA-Z*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['PhoneNumber'])) {
            throw new \UserInputError('Phone Number contains an incorrect number');
        }

        if (!preg_match("/[A-Z0-9a-z._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/", $fields['Email'])) {
            throw new \UserInputError('Email address contains an incorrect format');
        }

        if (strlen($fields['Password']) < 8 || strlen($fields['Password']) > 16 || !preg_match("/[A-Z]/", $fields['Password'])
            || !preg_match("/[a-z]/", $fields['Password']) || !preg_match("/[0-9]/", $fields['Password']) || !preg_match("/[\[!@#$%^&*()\]]/", $fields['Password'])
            || strpos($fields['Password'], ' ') !== false
        ) {
            throw new \UserInputError("Your password must be 8-16 characters and include at least 1 lowercase letter, 1 uppercase letter and 1 number");
        }

        if (!preg_match("/^(0[1-9]|1[012])\/(0[1-9]|[12][0-9]|3[01])\/(19[3-9][0-9]|20[01][0-9])$/", $fields['BirthdayDate'])) {
            throw new \UserInputError('BirthdayDate contains an incorrect number');
        }
    }

    protected function register()
    {
        $this->logger->notice(__METHOD__);

        $url = 'https://www.emirates.com/english/skywards/registration/';

        try {
            $this->http->GetURL($url);
        } catch (\WebDriverCurlException $e) {
            $this->logger->error("Site timed out, please try again later");

            throw new \UserInputError("Site timed out, please try again later");
        }

        //Register form
        $btnJoin = $this->waitForElement(\WebDriverBy::xpath("//button[@aria-label='Create an account']"), $this->timeout);
        $this->checkFieldExist(['btnJoin' => $btnJoin]);

        if ($cookies = $this->waitForElement(\WebDriverBy::xpath("//button[@id='onetrust-accept-btn-handler']"), 0)) {
            $cookies->click();
        }

        try {
            $this->fillInputs();
        } catch (\WebDriverCurlException $e) {
            $this->logger->error("Error: {$e->getMessage()}");

            throw new \EngineError("Error: {$e->getMessage()}");
        }

        $btnJoin->click();
    }

    protected function checkLogIn()
    {
        $this->logger->notice(__METHOD__);

        if ($this->waitForElement(\WebDriverBy::xpath("//div[@id='sec-text-container']"), 10)) {
            $this->waitFor(function () {
                return !$this->waitForElement(\WebDriverBy::id("sec-cpt-if"), 0);
            }, 30);
        }
        $this->saveResponse();

        $error = $this->waitForElement(\WebDriverBy::xpath("
        //div[
          contains(@id,'alertHeader')
          and
          (
            contains(text(),'This account already exists') 
            or
            contains(text(),'This email address is already in use') 
            or
            contains(text(),'Please select your preferred language') 
            or
            contains(text(),'Sorry, we could not complete your registration')
            or
            contains(text(),'Sorry, there were security issues with your enrolment')
          )
        ]
        "), 5);
        $this->saveResponse();

        if ($error) {
            throw new \UserInputError($error->getText());
        }

        $success = $this->waitForElement(\WebDriverBy::xpath("//div[contains(@class,'skywards-info__skywardsNumber')]"), 20);

        if ($success) {
            $this->checkFieldExist(['membershipNumber' => $success]);
            $membershipNumberText = str_replace(' ', '', trim($success->getText()));

            $this->ErrorMessage = json_encode([
                "status"       => "success",
                "message"      => "Registration is successful! Membership number: {$membershipNumberText}",
                "login"        => $membershipNumberText,
                "registerInfo" => $this->registerInfo,
            ], JSON_PRETTY_PRINT);

            return true;
        }
        $error = $this->waitForElement(\WebDriverBy::xpath("//div[contains(@id,'alertHeader')]"), 0);

        if ($error) {
            $this->logger->error($error->getText());
        }

        return false;
    }

    protected function fillInputs()
    {
        $this->logger->notice(__METHOD__);

        $firstName = $this->waitForElement(\WebDriverBy::xpath("//input[@id='firstname']"), 10);
        $lastName = $this->waitForElement(\WebDriverBy::xpath("//input[@id='lastname']"), 0);
        $email = $this->waitForElement(\WebDriverBy::xpath("//input[@id='registration-email']"), 0);
        $password = $this->waitForElement(\WebDriverBy::xpath("//input[@id='password']"), 0);

        $this->checkFieldExist([
            'firstName' => $firstName,
            'lastName'  => $lastName,
            'email'     => $email,
            'password'  => $password,
        ]);

        $this->driver->executeScript("
            document.querySelector('#title_label').click();

            setTimeout(function() {
                title_values = document.querySelectorAll('strong.dropdown-item__dropdown-name');
                
                title_values.forEach(function(item) {
                if (item.textContent == '{$this->fields['Title']}') {
                    item.click();
                }
                });
            }
            , 2000)"
        );

        sleep(5);

        $mover = new \MouseMover($this->driver);
        $mover->logger = $this->logger;

        if ($cookies = $this->waitForElement(\WebDriverBy::xpath("//button[@id='onetrust-accept-btn-handler']"), 0)) {
            $cookies->click();
        }

        $mover->duration = 100000;
        $mover->steps = 50;

        $mover->sendKeys($firstName, $this->fields['FirstName'], 10);
        $mover->sendKeys($lastName, $this->fields['LastName'], 10);
        $mover->sendKeys($email, $this->fields['Email'], 10);
        $mover->sendKeys($password, $this->fields['Password'], 10);

//        $firstName->sendKeys($this->fields['FirstName']);
//        $lastName->sendKeys($this->fields['LastName']);
//        $email->sendKeys($this->fields['Email']);
//        $password->sendKeys($this->fields['Password']);

        if ($cookies = $this->waitForElement(\WebDriverBy::xpath("//button[@id='onetrust-accept-btn-handler']"), 0)) {
            $cookies->click();
        }

        $birthDayBtn = $this->waitForElement(\WebDriverBy::xpath("//label[@id='dateofbirth-day-label']"), 0);
        $this->checkFieldExist(['birthDayBtn' => $birthDayBtn]);
        $birthDayBtn->click();
        sleep(2);

//        $birthDay = $this->waitForElement(\WebDriverBy::xpath("//label[@id='dateofbirth-day-label']/..//span[text()='{$this->fields['BirthDay']}']"), 15, false);
        $birthDay = $this->waitForElement(\WebDriverBy::xpath("//input[@id='dateofbirth-day']/..//span[text()='{$this->fields['BirthDay']}']"), 15, false);
        $this->checkFieldExist(['birthDay' => $birthDay]);
        $birthDay->click();

        $this->logger->debug($this->fields['BirthMonth']);
        $birthMonth = $this->waitForElement(\WebDriverBy::xpath("//input[@id='dateofbirth-month']/..//span[text()='{$this->fields['BirthMonth']}']"), 15, false);
        $this->checkFieldExist(['birthMonth' => $birthMonth]);
        $birthMonth->click();

        $birthYear = $this->waitForElement(\WebDriverBy::xpath("//input[@id='dateofbirth-year']/..//span[text()='{$this->fields['BirthYear']}']"), 15, false);
        $this->checkFieldExist(['birthYear' => $birthYear]);
        $birthYear->click();
        sleep(2);

        $countryClear = $this->waitForElement(\WebDriverBy::xpath("//label[@id='country_label']/following-sibling::button"), 0, false);
        $countryClear->click();

        $country = $this->waitForElement(\WebDriverBy::xpath("//input[@id='country']"), 0, false);
        $this->checkFieldExist(['country' => $country]);
        sleep(1);
        $country->sendKeys("united states");
        sleep(2);
        $this->saveResponse();
//        $country->sendKeys(\WebDriverKeys::DOWN);
        $country->sendKeys(\WebDriverKeys::ENTER);

        $lang = $this->waitForElement(\WebDriverBy::xpath("//label[@id='preferred-language_label']"), 0, false);
        $this->checkFieldExist(['lang' => $lang]);
        $lang->click();
        sleep(2);
//        $lang->sendKeys(\WebDriverKeys::DOWN);
//        $lang->sendKeys(\WebDriverKeys::ENTER);
        $langBtn = $this->waitForElement(\WebDriverBy::xpath("//div[@id='preferred-language']//strong[contains(text(),'English')]"), 0, false);
        $this->checkFieldExist(['langBtn' => $langBtn]);
        $langBtn->click();
        $this->saveResponse();

        /*        $this->driver->executeScript("
                        document.querySelector('input#country-code').click();
                        setTimeout(function() {
                            countryCodeDD = document.querySelector('div#country-code').parentElement.parentElement;

                            for (let i = 0; i < 5; i++) {
                                setInterval(function(){
                                    countryCodeDD.scrollTop = countryCodeDD.scrollHeight;
                                },1000)
                            }
                        }, 2000)");
                $countryCodeBtn = $this->waitForElement(\WebDriverBy::xpath("//div[@id='country-code']//strong[contains(text(),'United States')][last()]"), 15, false);
                $this->checkFieldExist(['countryCodeBtn' => $countryCodeBtn]);
                $countryCodeBtn->click();*/

        $mobile = $this->waitForElement(\WebDriverBy::xpath("//input[@id='mobile']"), 15, false);
        $this->checkFieldExist(['mobile' => $mobile]);
        $mobile->sendKeys($this->fields['PhoneNumber']);

        $this->saveResponse();
        $this->registerInfo = [
            [
                'key'  => 'Title',
                'value'=> $this->driver->executeScript("return document.querySelector('#title').value;"),
            ],
            [
                'key'  => 'FirstName',
                'value'=> $this->driver->executeScript("return document.querySelector('#firstname').value;"),
            ],
            [
                'key'  => 'LastName',
                'value'=> $this->driver->executeScript("return document.querySelector('#lastname').value;"),
            ],
            [
                'key'  => 'Email',
                'value'=> $this->driver->executeScript("return document.querySelector('#registration-email').value;"),
            ],
            [
                'key'  => 'Password',
                'value'=> $this->driver->executeScript("return document.querySelector('#password').value;"),
            ],
            [
                'key'   => 'BirthdayDate',
                'value' =>
                    date("Y-m-d",
                        strtotime($this->driver->executeScript("return document.querySelector('#dateofbirth-month').value;") .
                            ' ' . $this->driver->executeScript("return document.querySelector('#dateofbirth-day').value;") .
                            ' ' . $this->driver->executeScript("return document.querySelector('#dateofbirth-year').value;")
                        )
                    ),
            ],
            [
                'key'  => 'Country',
                'value'=> $this->driver->executeScript("return document.querySelector('#country').value"),
            ],
            [
                'key'  => 'PreferredLanguage',
                'value'=> $this->driver->executeScript("return document.querySelector('#preferred-language').value"),
            ],
            [
                'key'  => 'CountryCode(phone)',
                'value'=> $this->driver->executeScript("return document.querySelector('#country-code').value"),
            ],
            [
                'key'  => 'MobileNumber',
                'value'=> $this->driver->executeScript("return document.querySelector('#mobile').value;"),
            ],
        ];
        $this->logger->debug(var_export($this->registerInfo, true), ['pre'=>true]);

//        throw new \UserInputError('just fill. debug');
    }

    protected function modifyFields(array &$fields)
    {
        $this->logger->notice(__METHOD__);

        foreach ($fields as $key => $value) {
            if ($key !== 'Password') {
                $value = ltrim(rtrim($value));
            }

            if ($key === 'BirthdayDate') {
                $fields['BirthDay'] = (int) (\DateTime::createFromFormat("m/d/Y", $this->fields["BirthdayDate"])->format("d"));
                $fields['BirthMonth'] = \DateTime::createFromFormat("m/d/Y", $this->fields["BirthdayDate"])->format("M");
                $fields['BirthYear'] = \DateTime::createFromFormat("m/d/Y", $this->fields["BirthdayDate"])->format("Y");
            }
        }
    }

    protected function checkFieldExist(array $fields)
    {
        foreach ($fields as $key => $field) {
            if (!$field) {
                $this->logger->error("{$key} field is not exist");

                throw new \EngineError("{$key} field is not exist");
            }
        }
    }
}
