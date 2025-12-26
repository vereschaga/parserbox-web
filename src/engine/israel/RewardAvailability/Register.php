<?php

namespace AwardWallet\Engine\israel\RewardAvailability;

use AwardWallet\Engine\israel\RewardAvailability\Helpers\FormFieldsInformation;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \AwardWallet\Engine\ProxyList;

    private $timeout = 30;
    private $fields;
    private $registerInfo;
    private $finalData;
    private $memberNumber;

    /*Main*/

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->UseSelenium();
        $this->useChromePuppeteer();
        $this->disableImages();
        $this->http->saveScreenshots = true;
        $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        $this->http->setHttp2(true);
        $this->keepCookies(false);
        $this->usePacFile(false);

        $this->setProxyNetNut(null, 'il');

        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
            //            [800, 600],
        ];

        $this->setScreenResolution($resolutions[array_rand($resolutions)]);
        $this->seleniumOptions->recordRequests = true;
    }

    /**
     * Main function for registration account in provider.
     *
     * @return bool
     *
     * @throws \EngineError
     * @throws \UserInputError
     */
    public function registerAccount(array $fields)
    {
        $this->logger->notice(__METHOD__);
        $this->fields = $fields;
        $this->logger->debug(var_export($fields, true), ['pre' => true]);

        $this->checkFields($this->fields);
        $this->modifyFields($this->fields);

        return $this->register();
    }

    public function ProcessStep($step)
    {
        if ($step != 'Question') {
            return false;
        }

        if (!$this->Question) {
            $this->logger->error("something went wrong");

            return false;
        }

        if (isset($this->Answers[$this->Question])) {
            $this->logger->info('Got verification code.');
            $code = $this->Answers[$this->Question];
            unset($this->Answers[$this->Question]);

            //Verification
            $this->fillSecurityCode($code);

            $this->saveResponse();
            $continueBtn = $this->waitForElement(\WebDriverBy::xpath("//button[@id='continue' and not(@disabled)]"),
                10);
            $this->checkFieldExist(['continueBtn' => $continueBtn]);
            $continueBtn->click();

            //Password
            $password = $this->waitForElement(\WebDriverBy::xpath("//input[@formcontrolname='passwordController']"), 20,
                false);
            $passwordConfirm = $this->waitForElement(\WebDriverBy::xpath("//input[@formcontrolname='confirmPasswordController']"),
                0, false);
            $this->checkFieldExist([
                'password'        => $password,
                'passwordConfirm' => $passwordConfirm,
            ]);
            $password->sendKeys($this->fields['Password']);
            $passwordConfirm->sendKeys($this->fields['Password']);

            try {
                $this->finalData['Password'] = $this->driver->executeScript("return document.querySelector('input[formcontrolname=\"passwordController\"]').value;");
            } catch (\WebDriverException $e) {
                $this->logger->error($e->getMessage());
            }

            $this->saveResponse();

            $this->driver->executeScript("document.querySelector('.btn-main').click();");

            $error = $this->waitForElement(\WebDriverBy::xpath("//app-error-msg//span"), 10);

            if ($error) {
                $this->saveResponse();
                $this->logger->error($error->getText());

                return false;
            }

            $success = $this->waitForElement(\WebDriverBy::xpath("//span[@class='member-id']"), 15);

            if ($success) {
                $membership = $this->memberNumber ?? str_replace(' ', '', trim($success->getText()));

                $this->ErrorMessage = json_encode([
                    "status"       => "success",
                    "message"      => "Registration is successful! Membership number: {$membership}",
                    "login"        => $membership,
                    "active"       => true,
                    "registerInfo" => $this->registerInfo,
                ], JSON_PRETTY_PRINT);
                $this->saveResponse();

                return true;
            }
        }

        $this->logger->info('Answer is empty.');
        $this->ErrorMessage = json_encode([
            "status"       => "success",
            "message"      => "Registration is successful! Go to email and confirm it. Email: {$this->fields['Email']}",
            "login"        => $this->memberNumber,
            "active"       => false,
            "registerInfo" => $this->registerInfo,
        ], JSON_PRETTY_PRINT);
        $this->saveResponse();

        return true;
    }

    /**
     * Return register fields for web form.
     *
     * @return array|array[]|null
     */
    public function getRegisterFields()
    {
        return FormFieldsInformation::getRegisterFields();
    }

    /**
     * Validation input fields.
     *
     * @param $fields
     *
     * @throws \UserInputError
     */
    protected function checkFields(&$fields)
    {
        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['FirstName']) || strpos($fields['FirstName'], ' ') !== false
            || strlen($fields['FirstName']) < 2 || strlen($fields['FirstName']) > 25) {
            throw new \UserInputError('FirstName contains an incorrect symbol');
        }

        if (
            !preg_match("/^(0[1-9]|1[012])\/(0[1-9]|[12][0-9]|3[01])\/(19[3-9][0-9]|20[01][0-9])$/",
                $fields['BirthdayDate'])
            || !$this->validateDate($fields['BirthdayDate'])
        ) {
            throw new \UserInputError('BirthdayDate contains an incorrect symbol or incorrect format. mm/dd/YYYY format is required.');
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['LastName']) || strpos($fields['LastName'], ' ') !== false
            || strlen($fields['LastName']) < 2 || strlen($fields['LastName']) > 26) {
            throw new \UserInputError('LastName contains an incorrect symbol');
        }

        if (!preg_match("/[A-Z0-9a-z._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/", $fields['Email'])) {
            throw new \UserInputError('Email address contains an incorrect format');
        }

        if (strlen($fields['PhoneNumber']) != 10 || preg_match("/[a-zA-Z*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['PhoneNumber'])) {
            throw new \UserInputError('Phone Number contains an incorrect symbol or length of phone number is less than 10 digits.');
        }

        if (strlen($fields['Password']) < 6 || strlen($fields['Password']) > 12
            || !preg_match("/[A-Z]/", $fields['Password'])
            || !preg_match("/[a-z]/", $fields['Password'])
            || !preg_match("/[0-9]/", $fields['Password'])
            || !preg_match("/[+|&!*.\-%_?:=]/", $fields['Password'])
            || strpos($fields['Password'], ' ') !== false
        ) {
            throw new \UserInputError("Your password must be 6-12 characters and include at least 1 lowercase letter, 1 uppercase letter and 1 number and 1 symbol + | &! *. -% _? : =");
        }
    }

    /*Actions*/

    /**
     * Registration account in provider.
     *
     * @throws \EngineError
     */
    protected function register()
    {
        $this->getAction('https://www.elal.com/eng/registration?lang=en');

        if ($this->http->FindSingleNode("//h4[contains(.,'Current session has been terminated.')]")) {
            throw new \EngineError("Current session has been terminated.");
        }

//        $linkRegistration = $this->waitForElement(\WebDriverBy::xpath("//a[contains(text(), 'Join the club for free')]"), 10, false);
//        $this->checkFieldExist(['linkRegistration' => $linkRegistration]);
//        $this->driver->executeScript("document.location.href = '{$linkRegistration->getAttribute('href')}'");

        $this->searchAndFillRegistrationFields();

        $question = $this->waitForElement(\WebDriverBy::xpath("//span[contains(text(),'A verification code is currently being sent to your email')]"), 10);

        if ($question) {
            $this->holdSession();
            $this->checkSendEmail($question->getText());
            $this->saveResponse();

            return false;
        }

        $this->ErrorMessage = "Something going wrong";
        $this->saveResponse();

        return false;
    }

    /*Fill*/

    /**
     * Filling fields in registration step.
     *
     * @param array $fields
     *
     * @return false
     *
     * @throws \EngineError
     */
    protected function searchAndFillRegistrationFields()
    {
        try {
            //Registration
            $this->waitForElement(\WebDriverBy::xpath("//*[contains(text(), 'Enjoy a FREE enrollment')]"), $this->timeout);
            $joinBtn = $this->waitForElement(\WebDriverBy::xpath("//button[@class='register']"), 0);
            $this->checkFieldExist(['joinBtn' => $joinBtn]);
            $joinBtn->click();

            $firstName = $this->waitForElement(\WebDriverBy::xpath("//input[@id='firstNameEngControl']"), $this->timeout);
            $lastName = $this->waitForElement(\WebDriverBy::xpath("//input[@id='mat-input-2']"), 0);
            $this->checkFieldExist([
                'firstName' => $firstName,
                'lastName'  => $lastName,
            ]);
            $firstName->sendKeys($this->fields['FirstName']);
            $lastName->sendKeys($this->fields['LastName']);

            $this->saveResponse();

            try {
                $this->finalData['FirstName'] = $this->driver->executeScript("return document.querySelector('input[id=\"firstNameEngControl\"]').value;");
                $this->finalData['LastName'] = $this->driver->executeScript("return document.querySelector('input[id=\"mat-input-2\"]').value;");
            } catch (\WebDriverException $e) {
                $this->logger->error($e->getMessage());
            }

            $continueBtn = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@class,'btn-main') and not(@disabled)]"), $this->timeout);
            $this->checkFieldExist(['continueBtn' => $continueBtn]);
            $continueBtn->click();

            //Details
            $email = $this->waitForElement(\WebDriverBy::xpath("//input[@formcontrolname='emailController']"), $this->timeout);
            $phoneCountryCodeBtn = $this->waitForElement(\WebDriverBy::xpath("//mat-select[@formcontrolname='areaCodeController']"), 0);
            $phoneNumber = $this->waitForElement(\WebDriverBy::xpath("//input[@formcontrolname='mobileController']"), 0);

            $this->checkFieldExist([
                'email'               => $email,
                'phoneCountryCodeBtn' => $phoneCountryCodeBtn,
                'phoneNumber'         => $phoneNumber,
            ]);

            $this->driver->executeScript("document.querySelector('#mat-mdc-checkbox-2-input').click();");
            $email->sendKeys($this->fields['Email']);
            $phoneNumber->sendKeys($this->fields['PhoneNumber']);
            $phoneCountryCodeBtn->click();
            $phoneCountryCode = $this->waitForElement(\WebDriverBy::xpath("//mat-option[@id='mat-option-163']"), 0);
            $this->checkFieldExist(['phoneCountryCode' => $phoneCountryCode]);
            $phoneCountryCode->click();

            $dobYearBtn = $this->waitForElement(\WebDriverBy::xpath("//mat-select[@formcontrolname='yearController']"), 0);
            $dobMonthBtn = $this->waitForElement(\WebDriverBy::xpath("//mat-select[@formcontrolname='monthController']"), 0);
            $dobDayBtn = $this->waitForElement(\WebDriverBy::xpath("//mat-select[@formcontrolname='dayController']"), 0);
            $genderInputValue = $this->fields['Gender'] == 'male' ? 'M' : 'F';
            $gender = $this->waitForElement(\WebDriverBy::xpath("//*[@formcontrolname=\"genderController\"]//input[@value=\"{$genderInputValue}\"]"), 0);

            if (!$gender) {
                $gender = $this->driver->executeScript("return document.querySelector('input[type=\"radio\"][value=\"{$genderInputValue}\"]');");
                $this->driver->executeScript("document.querySelector('input[type=\"radio\"][value=\"{$genderInputValue}\"]').click();");
            } else {
                $gender->click();
            }

            $this->saveResponse();

            $this->checkFieldExist([
                'dobYearBtn'  => $dobYearBtn,
                'dobMonthBtn' => $dobMonthBtn,
                'dobDayBtn'   => $dobDayBtn,
                'gender'      => $gender,
            ]);

            $monthForDobSearch = $this->fields['BirthMonth'][0] == '0' ? $this->fields['BirthMonth'][1] : $this->fields['BirthMonth'];
            $dayForDobSearch = $this->fields['BirthDay'][0] == '0' ? $this->fields['BirthDay'][1] : $this->fields['BirthDay'];
            $dobYearBtn->click();
            $dobYear = $this->waitForElement(\WebDriverBy::xpath("//mat-option/child::node()[position()=2 and contains(text(), '{$this->fields['BirthYear']}')]"), 0);
            $this->checkFieldExist(['dobYear' => $dobYear]);
            $dobYear->click();
            $dobMonthBtn->click();
            $this->saveResponse();
            $dobMonth = $this->waitForElement(\WebDriverBy::xpath("//mat-option/child::node()[position()=2 and contains(text(), '{$monthForDobSearch}')]"), 5);
            $this->checkFieldExist(['dobMonth' => $dobMonth]);
            $dobMonth->click();
            $dobDayBtn->click();
            $dobDay = $this->waitForElement(\WebDriverBy::xpath("//mat-option/child::node()[position()=2 and contains(text(), '{$dayForDobSearch}')]"), 5);
            $this->checkFieldExist(['dobDay' => $dobDay]);
            $dobDay->click();

            $this->saveResponse();

            try {
                $this->finalData['Year'] = $this->driver->executeScript("return document.querySelectorAll('mat-select')[1].querySelectorAll('span')[1].innerHTML;");
                $this->finalData['Month'] = strlen($this->driver->executeScript("return document.querySelectorAll('mat-select')[2].querySelectorAll('span')[1].innerHTML;")) == 1 ? '0' . $this->driver->executeScript("return document.querySelectorAll('mat-select')[2].querySelectorAll('span')[1].innerHTML;") : $this->driver->executeScript("return document.querySelectorAll('mat-select')[2].querySelectorAll('span')[1].innerHTML;");
                $this->finalData['Day'] = strlen($this->driver->executeScript("return document.querySelectorAll('mat-select')[3].querySelectorAll('span')[1].innerHTML;")) == 1 ? '0' . $this->driver->executeScript("return document.querySelectorAll('mat-select')[3].querySelectorAll('span')[1].innerHTML;") : $this->driver->executeScript("return document.querySelectorAll('mat-select')[3].querySelectorAll('span')[1].innerHTML;");
                $this->finalData['MobileAreaCode'] = $this->driver->executeScript("return document.querySelectorAll('mat-select')[0].querySelectorAll('span')[0].childNodes[1].innerHTML;");
                $this->finalData['PhoneNumber'] = $this->driver->executeScript("return document.querySelector(\"input[formcontrolname='mobileController']\").value;");
                $this->finalData['Email'] = $this->driver->executeScript("return document.querySelector(\"input[formcontrolname='emailController']\").value;");
            } catch (\WebDriverException $e) {
                $this->logger->error($e->getMessage());
            }

            $this->driver->executeScript("document.querySelector('.btn-main').click();");
            // TODO optimize check load
            $error = $this->waitForElement(\WebDriverBy::xpath("//span[normalize-space()='error - please try again later'] | //span[contains(text(),'Duplicate reg details found')] | //app-error-msg//span"),
                10);

            if ($error) {
                throw new \EngineError($error->getText());
            }

            $this->registerInfo = [
                [
                    "key"   => "First Name",
                    "value" => $this->finalData['FirstName'] ?? $this->fields['FirstName'],
                ],
                [
                    "key"   => "Last Name",
                    "value" => $this->finalData['LastName'] ?? $this->fields['LastName'],
                ],
                [
                    "key"   => "Birth Date",
                    "value" => isset($this->finalData['Day']) && isset($this->finalData['Month']) && isset($this->finalData['Year']) ? "{$this->finalData['Month']}/{$this->finalData['Day']}/{$this->finalData['Year']}" : $this->fields['BirthdayDate'],
                ],
                [
                    "key"   => "Phone Number",
                    "value" => "1" . ($this->finalData['PhoneNumber'] ?? $this->fields['PhoneNumber']),
                ],
                [
                    "key"   => "Gender",
                    "value" => FormFieldsInformation::$genders[$this->fields['Gender']],
                ],
                [
                    "key"   => "MobileAreaCode",
                    "value" => $this->finalData['MobileAreaCode'] ?? $this->fields['MobileAreaCode'],
                ],
            ];

            $requests = $this->http->driver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
                if (($xhr->response->getStatus() >= 200 && $xhr->response->getStatus() < 300)
                    && isset($xhr->response->getBody()['mem_number'])
                ) {
                    $this->memberNumber = $xhr->response->getBody()['mem_number'];
                }
            }
        } catch (\WebDriverCurlException $e) {
            $this->logger->error("Error: {$e->getMessage()}");

            throw new \EngineError("Error: {$e->getMessage()}");
        }
    }

    protected function fillSecurityCode($code)
    {
        for ($i = 0; $i < mb_strlen($code); $i++) {
            $input = "input{$i}";
            $$input = $this->waitForElement(\WebDriverBy::xpath("//input[@id='id{$i}']"), 10, false);
            $this->checkFieldExist([$input => $$input]);
            $$input->sendKeys($code[$i]);
        }
    }

    /*Helpers*/

    protected function modifyFields(array &$fields)
    {
        $date = \DateTime::createFromFormat("m/d/Y", $fields["BirthdayDate"]);
        $fields["BirthDay"] = $date->format("d");
        $fields["BirthMonth"] = $date->format("m");
        $fields["BirthYear"] = $date->format("Y");

        foreach ($fields as $key => $value) {
            if ($key !== 'Password') {
                $value = ltrim(rtrim($value));
            }
        }
    }

    /**
     * Action at provider page.
     *
     * @param string $type
     *
     * @return false
     */
    protected function getAction($url)
    {
        try {
            $this->http->GetURL($url);
        } catch (\WebDriverCurlException $e) {
            $this->logger->error("Site timed out, please try again later");

            throw new \UserInputError("Site timed out, please try again later");
        }
    }

    /**
     * Checking existing input fields.
     *
     * @throws \EngineError
     */
    protected function checkFieldExist(array $fields)
    {
        foreach ($fields as $key => $field) {
            if (!$field) {
                $this->logger->error("{$key} field is not exist");
                $this->saveResponse();

                throw new \EngineError("{$key} field is not exist");
            }
        }
    }

    private function checkSendEmail(string $question)
    {
        $this->logger->notice(__METHOD__);

        if ($question) {
            $this->State['email'] = $this->fields['Email'];

            $this->AskQuestion($question, null, 'Question');

            $this->ErrorMessage = json_encode([
                "status"       => "success",
                "message"      => "Registration is successful! Go to email and confirm it. Email: {$this->fields['Email']}",
                "login"        => $this->memberNumber,
                "active"       => false,
                "registerInfo" => $this->registerInfo,
            ], JSON_PRETTY_PRINT);
        }
    }

    private function validateDate($date, $format = 'm/d/Y')
    {
        $d = \DateTime::createFromFormat($format, $date);

        return $d && $d->format($format) == $date;
    }
}
