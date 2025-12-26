<?php

namespace AwardWallet\Engine\korean\RewardAvailability;

use AwardWallet\Engine\korean\RewardAvailability\Helpers\FormFieldsInformation;
use AwardWallet\Engine\Settings;

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
//        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_100);
        $this->useChromePuppeteer();
        $this->disableImages();
        $this->http->saveScreenshots = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");

//        $this->setProxyBrightData(true, Settings::RA_ZONE_STATIC, 'kr');
        $this->setProxyGoProxies(null, 'kr');

        $resolutions = [
//            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
//            [800, 600],
        ];

        $this->setScreenResolution($resolutions[array_rand($resolutions)]);
        $this->useCache();
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

        $this->modifyFields($this->fields);
        $this->checkFields($this->fields);

        return $this->register();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($step != 'Question') {
            return false;
        }

        if (!$this->Question) {
            $this->logger->error("something went wrong");

            return false;
        }

        if (isset($this->Answers[$this->Question])) {
            $this->logger->info('Got verification code.' . $this->Answers[$this->Question]);
            $code = $this->Answers[$this->Question];
            unset($this->Answers[$this->Question]);

            //Verification
            $verificationCodeInput = $this->waitForElement(\WebDriverBy::xpath("//input[@id='entry-number']"), 10);
            $verifyBtn = $this->waitForElement(\WebDriverBy::xpath("//button[contains(text(), 'Verify')]"),
                0);

            $this->checkFieldExist(['verifyBtn' => $verifyBtn, 'verificationCodeInput' => $verificationCodeInput]);

            $verificationCodeInput->sendKeys($code);
            $verifyBtn->click();

            $this->saveResponse();

            //Select Membership Type
            $accType = $this->waitForElement(\WebDriverBy::xpath("//button/strong[contains(text(), 'I do not have')]"), $this->timeout);
            $this->checkFieldExist(['accType' => $accType]);
            $accType->click();

            $this->saveResponse();

            //Enter member information
            $genderValue = $this->fields['Gender'] == 'male' ? 1 : 2;
            $lastName = $this->waitForElement(\WebDriverBy::xpath("//input[@id='member-info-en-family-name']"), $this->timeout);
            $firstName = $this->waitForElement(\WebDriverBy::xpath("//input[@id='member-info-en-name']"), 0);
            $password = $this->waitForElement(\WebDriverBy::xpath("//input[contains(@id,'passwordinput')]"), 0);
            $passwordConfirm = $this->waitForElement(\WebDriverBy::xpath("//input[@id='member-info-pw-check']"), 0);
            $dobDate = $this->waitForElement(\WebDriverBy::xpath("//input[@id='member-info-birth']"), 0);
            $gender = $this->waitForElement(\WebDriverBy::xpath("//label[@for='member-info-gender0{$genderValue}']"), 0);
            $email = $this->waitForElement(\WebDriverBy::xpath("//input[@id='member-info-email']"), 0);
            //$emailConfirm = $this->waitForElement(\WebDriverBy::xpath("//input[@id='member-info-email2']"), 0);
            $countryCodeBtn = $this->waitForElement(\WebDriverBy::xpath("//label[@for='member-info-cell-nation']/../button"), 0);
            $phoneNumber = $this->waitForElement(\WebDriverBy::xpath("//input[@id='member-info-cell']"), 0);

            $this->checkFieldExist([
                'firstName'       => $firstName,
                'lastName'        => $lastName,
                'password'        => $password,
                'passwordConfirm' => $passwordConfirm,
                'dobDate'         => $dobDate,
                'gender'          => $gender,
                'email'           => $email,
                //'emailConfirm'    => $emailConfirm,
                'countryCodeBtn'  => $countryCodeBtn,
                'phoneNumber'     => $phoneNumber,
            ]);

            $firstName->sendKeys($this->fields['FirstName']);
            $lastName->sendKeys($this->fields['LastName']);
            $this->generateAndFillUserID();
            $this->saveResponse();
            $password->sendKeys($this->fields['Password']);
            $passwordConfirm->sendKeys($this->fields['Password']);
            $dobDate->sendKeys($this->fields['BirthdayDate']);
            $gender->click();
            $this->saveResponse();
            $email->sendKeys($this->fields['Email']);
            //$emailConfirm->sendKeys($this->fields['Email']);
            $phoneNumber->sendKeys($this->fields['PhoneNumber']);
            $this->driver->executeScript("document.querySelector('#member-info-nation').value = 'US'");
            $countryCodeBtn->click();

            $phoneCountryCode = $this->waitForElement(\WebDriverBy::xpath("//li[@id='nation-item-206']"), 0);
            $this->checkFieldExist(['phoneCountryCode' => $phoneCountryCode]);
            $phoneCountryCode->click();

            $this->saveResponse();

            $this->fillRegisterInfo();

            $confirmBtn = $this->waitForElement(\WebDriverBy::xpath("//button[contains(text(), 'Confirm')]"), $this->timeout);
            $this->checkFieldExist(['confirmBtn' => $confirmBtn]);
            $confirmBtn->click();
            $this->checkLogIn();
        } else {
            $this->logger->info('Answer is empty.');
            $this->ErrorMessage = 'Registration is not successful. Because the email was not confirmed';
        }

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

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['LastName']) || strpos($fields['LastName'], ' ') !== false
            || strlen($fields['LastName']) < 2 || strlen($fields['LastName']) > 26) {
            throw new \UserInputError('LastName contains an incorrect symbol');
        }

        if (strlen($fields['Password']) < 8 || strlen($fields['Password']) > 16 || !preg_match("/[A-Z]/", $fields['Password'])
            || !preg_match("/[a-z]/", $fields['Password']) || !preg_match("/[0-9]/", $fields['Password']) || !preg_match("/[@~!#$%^&*()\\-=+,.?]/", $fields['Password'])
            || strpos($fields['Password'], ' ') !== false || preg_match("/[¡?¿<>\\ºª|\/\·;¿_{}\-\[\]\"€£']/", $fields['Password'])
        ) {
            throw new \UserInputError("Password must be 8 to 20 alphanumeric and special characters(@~!#$%^&*()\-=+,.?)");
        }

        if (!preg_match("/^(0[1-9]|1[012])\.(0[1-9]|[12][0-9]|3[01])\.(19[3-9][0-9]|200[0-4])$/", $fields['BirthdayDate'])) {
            throw new \UserInputError('BirthdayDate contains an incorrect number');
        }

        if (!preg_match("/[A-Z0-9a-z._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/", $fields['Email'])) {
            throw new \UserInputError('Email address contains an incorrect format');
        }

        if (strlen($fields['PhoneNumber']) > 10 || preg_match("/[a-zA-Z*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['PhoneNumber'])) {
            throw new \UserInputError('Phone Number contains an incorrect symbol');
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
        $this->getAction('https://www.koreanair.com/register');

        return $this->searchAndFillRegistrationFields();
    }

    /**
     * Checking we are logged or not.
     *
     * @param string $xpath
     *
     * @return bool
     */
    protected function checkLogIn()
    {
        $this->waitFor(function () {
            return $this->waitForElement(\WebDriverBy::xpath("
              //h2[contains(text(), 'Thank you for registering!')]
            | //p[contains(text(),'You are already registered ')]
            | //p[contains(.,'There is a existing account information with your name, date of birth, contact number.')]
            "), 0);
        }, 30);

        $this->saveResponse();
        $success = $this->http->FindSingleNode("//h2[contains(text(), 'Thank you for registering!')]");
        $error = $this->http->FindSingleNode("//p[contains(text(),'You are already registered ')]
        | //p[contains(.,'There is a existing account information with your name, date of birth, contact number.')]");

        if ($error) {
            throw new \UserInputError("You are already registered for Korean Air Online Membership. Please log in with your existing account.");
        }

        if ($success) {
            $confirm = $this->waitForElement(\WebDriverBy::xpath("//button[contains(text(), 'Confirm')]"), 15);
            $this->checkFieldExist(['confirm' => $confirm]);
            $confirm->click();

            $this->ErrorMessage = json_encode([
                "status"       => "success",
                "message"      => "Registration is successful! Membership number: {$this->fields['UserId']}",
                "login"        => $this->fields['UserId'],
                "login2"       => "uid",
                "registerInfo" => $this->registerInfo,
            ], JSON_PRETTY_PRINT);

            return true;
        }

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
            //Consent to terms and conditions
            $checkbox1 = $this->waitForElement(\WebDriverBy::xpath("//label[@for='checkbox1']"), $this->timeout);
            $checkbox2 = $this->waitForElement(\WebDriverBy::xpath("//label[@for='checkbox2']"), 0);
            $checkbox3 = $this->waitForElement(\WebDriverBy::xpath("//label[@for='checkbox10']"), 0);

            $this->driver->executeScript(
                "
                if (document.querySelector('kc-global-cookie-banner').shadowRoot.querySelector('div[id=\"cookieBanner\"]').querySelector('button')) {
                    document.querySelector('kc-global-cookie-banner').shadowRoot.querySelector('div[id=\"cookieBanner\"]').querySelector('button').click();
                }"
            );

            $confirmBtn = $this->waitForElement(\WebDriverBy::xpath("//button[contains(text(), 'Confirm')]"), 0);

            $this->checkFieldExist([
                'confirmBtn' => $confirmBtn,
                'checkbox1'  => $checkbox1,
                'checkbox2'  => $checkbox2,
                'checkbox3'  => $checkbox3,
            ]);
            $checkbox1->click();
            $checkbox2->click();
            $checkbox3->click();
            $confirmBtn->click();

            $this->saveResponse();

            //Email Verification
            $verifyEmailInput = $this->waitForElement(\WebDriverBy::xpath("//input[@id='email-address']"), $this->timeout);
            $verifyEmailButton = $this->waitForElement(\WebDriverBy::xpath("//input[@id='email-address']/../../button"), 0);

            $this->checkFieldExist([
                'verifyEmailInput'         => $verifyEmailInput,
                'verifyEmailButton'        => $verifyEmailButton,
            ]);

            $verifyEmailInput->sendKeys($this->fields['Email']);
            $verifyEmailButton->click();

            $question = $this->waitForElement(\WebDriverBy::xpath("//span[contains(text(), 'authentication code')]"), 5);

            if ($question) {
                $this->holdSession();
                $this->AskQuestion($question->getText(), null, 'Question');

                return false;
            }

            $this->ErrorMessage = "Something going wrong";
            $this->saveResponse();

            return false;
        } catch (\WebDriverCurlException $e) {
            $this->logger->error("Error: {$e->getMessage()}");

            throw new \EngineError("Error: {$e->getMessage()}");
        }
    }

    /*Helpers*/

    protected function modifyFields(array &$fields)
    {
        foreach ($fields as $key => $value) {
            if ($key !== 'Password') {
                $value = ltrim(rtrim($value));
            }

            if ($key == 'BirthdayDate') {
                $fields[$key] = \DateTime::createFromFormat('m/d/Y', $value)->format('m.d.Y');
            } else {
                $fields[$key] = $value;
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
        } catch (\UnknownErrorException $e) {
            $this->logger->error("Error: {$e->getMessage()}");

            throw new \EngineError("Failed to get, please try again later");
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

                throw new \EngineError("{$key} field is not exist");
            }
        }
    }

    protected function generateAndFillUserID()
    {
        $numbers = [2, 0, 1, 6, 2, 5, 3, 7, 4, 1, 4, 8, 5, 6, 0, 7, 9, 8, 3, 9];

        do {
            $this->fields['UserId'] = '';

            for ($cntNumbers = rand(6, 12); $cntNumbers > 0; $cntNumbers--) {
                shuffle($numbers);
                $this->fields['UserId'] .= $numbers[0];
            }

            $check = $this->waitForElement(\WebDriverBy::xpath("//button[@id='idDupCheckBtn']"), 0);
            $userId = $this->waitForElement(\WebDriverBy::xpath("//input[@id='member-info-id']"), 0);
            $this->checkFieldExist(['userId' => $userId]);
            $userId->sendKeys($this->fields['UserId']);
            $check->click();

            $error = $this->waitForElement(
                \WebDriverBy::xpath('//p[starts-with(@id,"error-message") and (contains(text(), "This ID is not available.") or contains(text(), "Please check for ID duplication."))]'),
                3
            );

            if ($error) {
                $userId->clear();
                $success = false;
            } else {
                $this->logger->debug("entered UserId: " . $this->fields['UserId']);
                $success = true;
            }
        } while (!$success);
    }

    private function fillRegisterInfo()
    {
        $this->registerInfo = [
            [
                'key'  => 'FirstName',
                'value'=> $this->driver->executeScript("return document.querySelector('#member-info-en-name').value;"),
            ],
            [
                'key'  => 'LastName',
                'value'=> $this->driver->executeScript("return document.querySelector('#member-info-en-family-name').value;"),
            ],
            [
                'key'   => 'PassConfirm',
                'value' => $this->driver->executeScript("return document.querySelector('#member-info-pw-check').value;"),
            ],
            [
                'key'   => 'BirthdayDate',
                'value' => preg_replace("/^(\d{2})\.(\d{2})\.(\d{4})$/", "$3-$1-$2", $this->driver->executeScript("return document.querySelector('#member-info-birth').value;")),
            ],
            /*[
                'key'   => 'EmailConfirm',
                'value' => $this->driver->executeScript("return document.querySelector('#member-info-email2').value;"),
            ],*/
            [
                'key'   => 'CountryCodePhone',
                'value' => $this->driver->executeScript("return document.querySelector('#member-info-cell-nation').value;"),
            ],
            [
                'key'   => 'PhoneNumber',
                'value' => $this->driver->executeScript("return document.querySelector('#member-info-cell').value;"),
            ],
            [
                'key'   => 'Country/Region of Residence',
                'value' => $this->driver->executeScript("return document.querySelector('option[value=\"US\"]').innerText;"),
            ],
            [
                'key'   => 'UserID',
                'value' => $this->fields['UserId'],
            ],
            [
                'key'   => 'Gender',
                'value' => $this->driver->executeScript("return document.querySelector('#member-info-gender01').checked;") == true
                    ? $this->driver->executeScript("return document.querySelector('#member-info-gender01').value;")
                    : $this->driver->executeScript("return document.querySelector('#member-info-gender02').value;"),
            ],
        ];

        $this->logger->debug(var_export($this->registerInfo, true), ['pre'=>true]);
    }
}
