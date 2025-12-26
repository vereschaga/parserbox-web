<?php

namespace AwardWallet\Engine\qantas\RewardAvailability;

use AwardWallet\Engine\qantas\RewardAvailability\Helpers\FormFieldsInformation;
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
        $this->useChromeExtension();

        $this->seleniumOptions->addPuppeteerStealthExtension = false;
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        $this->disableImages();
        $this->http->saveScreenshots = true;

//        $this->setProxyBrightData(true, Settings::RA_ZONE_STATIC);
        $this->setProxyGoProxies();

        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
            [800, 600],
        ];

        $this->setScreenResolution($resolutions[array_rand($resolutions)]);
        $this->useCache();
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
        return FormFieldsInformation::getRegisterFields();
    }

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

        if (!preg_match("/[A-Z0-9a-z._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/", $fields['Email'])) {
            throw new \UserInputError('Email address contains an incorrect format');
        }

        if (strlen($fields['PhoneNumber']) > 10 || preg_match("/[a-zA-Z*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['PhoneNumber'])) {
            throw new \UserInputError('Phone Number contains an incorrect symbol');
        }

        if (strlen($fields['Address']) > 29 || preg_match("/[*¡!?¿<>ºª|\·@#$%&;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['Address'])) {
            throw new \UserInputError('Address (Must 0-29 characters or numbers long (include . , / \ and space) )');
        }

        if (strlen($fields['City']) > 29 || preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['City'])) {
            throw new \UserInputError('City (0-29 characters)');
        }

        if (strlen($fields['ZipCode']) > 5 || preg_match("/[a-zA-Z*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['ZipCode'])) {
            throw new \UserInputError('ZipCode contains an incorrect symbol');
        }

        if (!preg_match("/^(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[012])\/(19[3-9][0-9]|20[01][0-9])$/", $fields['BirthdayDate'])) {
            throw new \UserInputError('BirthdayDate contains an incorrect number');
        }

        if (strlen($fields['Password']) !== 4 || !is_numeric($fields['Password'])) {
            throw new \UserInputError("Incorrect PIN: must be four numbers");
        }
        $pin = (int) $fields['Password'];
        $pin0 = $pin % 10;
        $pin1 = $pin / 10 % 10;
        $pinStr = str_replace((string) $pin0, "", $fields['Password']);

        if (empty($pinStr)) {
            throw new \UserInputError("Incorrect PIN: cannot repeat the same number four times, e.g. 0000");
        }

        if (($pin0 === $pin1 + 1 && $pin1 > 1 && $pin0 + 10 * $pin1 + 100 * ($pin1 - 1) + 1000 * ($pin1 - 2))
            || ($pin0 === $pin1 - 1 && $pin0 + 10 * $pin1 + 100 * ($pin1 + 1) + 1000 * ($pin1 + 1))
        ) {
            throw new \UserInputError("Incorrect PIN: cannot contain all consecutive numbers, e.g. 1234");
        }
    }

    protected function register()
    {
        $this->getAction();

        //Register form
        $this->searchAndFillRegistrationFields();

        $this->logger->debug(var_export($this->registerInfo, true), ['pre'=>true]);
        $hasError = false;

        foreach ($this->registerInfo as $item) {
            if (empty($item['value'])) {
                $hasError = true;
                $this->logger->error("Error: empty registerInfo[{$item['key']}]");
            }
        }

        if ($hasError) {
            throw new \EngineError("Something went wrong");
        }
        $joinNow = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@class,'ffjJoinNow_button')]"), $this->timeout);
        $this->checkFieldExist(['joinNow' => $joinNow]);

        $joinNow->click();
    }

    protected function checkLogIn()
    {
        $error = $this->waitForElement(\WebDriverBy::xpath("
        //h2[contains(text(), 'already an existing Frequent Flyer')]
        || //h4[normalize-space()='Important information']/following::div[normalize-space()][1][contains(.,'You must be a Qantas Frequent Flyer to earn and')]
        "), 5);
        $this->saveResponse();

        if ($error) {
            if (strpos($error->getText(), 'already an existing Frequent Flyer') !== false) {
                throw new \UserInputError("You're already an existing Frequent Flyer. You can log in here. If you've forgotten your membership number please contact us.");
            }

            throw new \ProviderError($error->getText());
        }

        $success = $this->waitForElement(\WebDriverBy::xpath("//input[@id='form-member-id-login-menu-frequent-flyer']"), $this->timeout);

        if ($success) {
            $membershipNumber = $success->getAttribute('value');
            $this->ErrorMessage = json_encode([
                "status"    => "success",
                "message"   => "Registration is successful! Membership number: {$membershipNumber}",
                "login"     => $membershipNumber,
                "login2"    => $this->fields['LastName'],
                "login3"    => "",
                "password"  => $this->fields['Password'],
                "email"     => $this->fields['Email'],
                "questions" => [
                    [
                        "question" => "Mother's maiden name",
                        "answer"   => "",
                    ],
                    [
                        "question" => "Date of birth (yyyy-mm-dd)",
                        "answer"   => \DateTime::createFromFormat('d/m/Y', $this->fields['BirthdayDate'])->format('Y-m-d'),
                    ],
                    [
                        "question" => "Postcode",
                        "answer"   => $this->fields['ZipCode'],
                    ],
                    [
                        "question" => "Date of Joining (yyyy-mm)",
                        "answer"   => (new \DateTime())->format('Y-m'),
                    ],
                ],
                "registerInfo" => $this->registerInfo,
            ], JSON_PRETTY_PRINT);

            return true;
        }

        return false;
    }

    protected function searchAndFillRegistrationFields()
    {
        try {
            $this->fillResidentialLocation();

            $this->fillPersonalInformation();

            $this->fillContactDetails();

            $this->fillSecurity();

            //fill additional information
            $continue5 = $this->waitForElement(\WebDriverBy::xpath("(//button[contains(@class,'ffjNext_button')])[5]"), $this->timeout);
            $this->checkFieldExist(['continue5' => $continue5]);

            $this->saveResponse();

            $continue5->click();

            //fill terms and conditions
            $continue6 = $this->waitForElement(\WebDriverBy::xpath("(//button[contains(@class,'ffjNext_button')])[6]"), $this->timeout);
            $this->checkFieldExist(['continue6' => $continue6]);

            if ($terms = $this->waitForElement(\WebDriverBy::xpath("//label[contains(@for,'joinFormTerms')]"), 3)) {
                $terms->click();
            } else {
                $this->driver->executeScript("document.querySelector('#joinFormTerms').click();");
            }

            $this->saveResponse();

            $continue6->click();
        } catch (\WebDriverCurlException $e) {
            $this->logger->error("Error: {$e->getMessage()}");

            throw new \EngineError("Error: {$e->getMessage()}");
        }
    }

    protected function fillResidentialLocation()
    {
        $continue1 = $this->waitForElement(\WebDriverBy::xpath("(//button[contains(@class,'ffjNext_button')])[1]"), $this->timeout);
        $countryDD = $this->waitForElement(\WebDriverBy::xpath("//button[@id='joinFormCountry']"), 0);
        $this->checkFieldExist([
            'continue1' => $continue1,
            'countryDD' => $countryDD,
        ]);
        $countryDD->click();

        $country = $this->waitForElement(\WebDriverBy::xpath("//button[@id='joinFormCountry']/../../ul/li[@id='downshift-0-item-4']"), 0, false);
        $this->checkFieldExist(['country' => $country]);
        $country->click();

        $this->saveResponse();

        $this->registerInfo = array_merge($this->registerInfo, [
            [
                'key'   => 'Residential location',
                'value' => $this->driver->executeScript("return document.querySelector('button[id=\'joinFormCountry\'] div').innerHTML;"),
            ],
        ]);
        $continue1->click();
    }

    protected function fillPersonalInformation()
    {
        $continue2 = $this->waitForElement(\WebDriverBy::xpath("(//button[contains(@class,'ffjNext_button')])[2]"), $this->timeout);
        $titleDD = $this->waitForElement(\WebDriverBy::xpath("//button[@id='joinFormTitle']"), 0);
        $this->checkFieldExist([
            'continue2' => $continue2,
            'titleDD'   => $titleDD,
        ]);
        $titleDD->click();

        $titleValue = $this->fields['Title'] == 'Mr' ? 0 : 1;
        $title = $this->waitForElement(\WebDriverBy::xpath("//button[@id='joinFormTitle']/../../ul/li[@id='downshift-1-item-{$titleValue}']"), 0, false);
        $firstName = $this->waitForElement(\WebDriverBy::xpath("//input[@id='joinFormFirstName']"), 0);
        $lastName = $this->waitForElement(\WebDriverBy::xpath("//input[@id='joinFormLastName']"), 0);
        $this->checkFieldExist([
            'title'     => $title,
            'firstName' => $firstName,
            'lastName'  => $lastName,
        ]);
        $title->click();
        $firstName->sendKeys($this->fields['FirstName']);
        $lastName->sendKeys($this->fields['LastName']);

        $this->saveResponse();
        $this->registerInfo = array_merge($this->registerInfo, [
            [
                'key'   => 'Title',
                'value' => $this->driver->executeScript("return document.querySelector('button[id=\'joinFormTitle\'] div').innerHTML;"),
            ],
            [
                'key'   => 'FirstName',
                'value' => $this->driver->executeScript("return document.querySelector('input[id=\'joinFormFirstName\']').value;"),
            ],
            [
                'key'   => 'LastName',
                'value' => $this->driver->executeScript("return document.querySelector('input[id=\'joinFormLastName\']').value;"),
            ],
        ]);

        $continue2->click();
    }

    protected function fillContactDetails()
    {
        $continue3 = $this->waitForElement(\WebDriverBy::xpath("(//button[contains(@class,'ffjNext_button')])[3]"), $this->timeout);
        $email = $this->waitForElement(\WebDriverBy::xpath("//input[@id='joinFormEmail']"), 0);
        $confirmEmail = $this->waitForElement(\WebDriverBy::xpath("//input[@id='joinFormConfirmEmail']"), 0);
        $phoneTypeDD = $this->waitForElement(\WebDriverBy::xpath("//button[@id='joinFormPhoneType']"), 0);
        $this->checkFieldExist([
            'continue3'    => $continue3,
            'email'        => $email,
            'confirmEmail' => $confirmEmail,
            'phoneTypeDD'  => $phoneTypeDD,
        ]);
        $email->sendKeys($this->fields['Email']);
        $confirmEmail->sendKeys($this->fields['Email']);
        $phoneTypeDD->click();

        $phoneType = $this->waitForElement(\WebDriverBy::xpath("//button[@id='joinFormPhoneType']/../../ul/li[@id='downshift-3-item-0']"), 0, false);
        $this->checkFieldExist(['phoneType' => $phoneType]);
        $phoneType->click();

        $countryCodeDD = $this->waitForElement(\WebDriverBy::xpath("//button[@id='joinFormPhoneCountryCode']"), 0);
        $this->checkFieldExist(['countryCodeDD' => $countryCodeDD]);
        $countryCodeDD->click();

        $countryCode = $this->waitForElement(\WebDriverBy::xpath("//button[@id='joinFormPhoneCountryCode']/../../ul/li[@id='downshift-4-item-208']"), 0, false);
        $areaCode = $this->waitForElement(\WebDriverBy::xpath("//input[@id='joinFormPhoneAreaCode']"), 0);
        $phoneNumber = $this->waitForElement(\WebDriverBy::xpath("//input[@id='joinFormPhoneNumber']"), 0);
        $address = $this->waitForElement(\WebDriverBy::xpath("//input[@id='joinFormAddressStreet']"), 0);
        $city = $this->waitForElement(\WebDriverBy::xpath("//input[@id='joinFormAddressSuburb']"), 0);
        $postCode = $this->waitForElement(\WebDriverBy::xpath("//input[@id='joinFormAddressPostCode']"), 0);
        $this->checkFieldExist([
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'address'     => $address,
            'city'        => $city,
            'postCode'    => $postCode,
        ]);
        $countryCode->click();

        if ($areaCode && isset($this->fields['MobileAreaCode'])) {
            $areaCode->sendKeys($this->fields['MobileAreaCode']);
            $phoneNumber->sendKeys(preg_replace("/^{$this->fields['MobileAreaCode']}/", "",
                $this->fields['PhoneNumber']));
        } else {
            $phoneNumber->sendKeys($this->fields['PhoneNumber']);
        }
        $address->sendKeys($this->fields['Address']);
        $city->sendKeys($this->fields['City']);
        $postCode->sendKeys($this->fields['ZipCode']);

        $stateDD = $this->waitForElement(\WebDriverBy::xpath("//button[@id='joinFormAddressState']"), 0);
        $this->checkFieldExist(['stateDD' => $stateDD]);
        $stateDD->click();

        $stateValue = FormFieldsInformation::$states[$this->fields['State']];
        $state = $this->waitForElement(\WebDriverBy::xpath("//button[@id='joinFormAddressState']/../../ul/li[@id='downshift-5-item-{$stateValue}']"), 0, false);
        $this->checkFieldExist(['state' => $state]);
        $state->click();

        $this->saveResponse();
        $this->registerInfo = array_merge($this->registerInfo, [
            [
                'key'   => 'Email',
                'value' => $this->driver->executeScript("return document.querySelector('input[id=\'joinFormEmail\']').value;"),
            ],
            [
                'key'   => 'PhoneType',
                'value' => $this->driver->executeScript("return document.querySelector('button[id=\'joinFormPhoneType\'] div').innerHTML;"),
            ],
            [
                'key'   => 'PhoneType',
                'value' => $this->driver->executeScript("return document.querySelector('button[id=\'joinFormPhoneType\'] div').innerHTML;"),
            ],
            [
                'key'   => 'PhoneCountryCode',
                'value' => $this->driver->executeScript("return document.querySelector('button[id=\'joinFormPhoneCountryCode\'] div').innerHTML;"),
            ],
            [
                'key'   => 'Phone',
                'value' => $this->driver->executeScript("return document.querySelector('input[id=\'joinFormPhoneNumber\']').value;"),
            ],
            [
                'key'   => 'StreetAddress',
                'value' => $this->driver->executeScript("return document.querySelector('input[id=\'joinFormAddressStreet\']').value;"),
            ],
            [
                'key'   => 'Suburb/town/city',
                'value' => $this->driver->executeScript("return document.querySelector('input[id=\'joinFormAddressSuburb\']').value;"),
            ],
            [
                'key'   => 'PostCode',
                'value' => $this->driver->executeScript("return document.querySelector('input[id=\'joinFormAddressPostCode\']').value;"),
            ],
            [
                'key'   => 'State',
                'value' => $this->driver->executeScript("return document.querySelector('button[id=\'joinFormAddressState\'] div').innerHTML;"),
            ],
        ]);

        if ($areaCode) {
            $this->registerInfo = array_merge($this->registerInfo, [
                [
                    'key'   => 'AreaCode',
                    'value' => $this->driver->executeScript("return document.querySelector('input[id=\'joinFormPhoneAreaCode\']').value;"),
                ],
            ]);
        }
        $continue3->click();
    }

    protected function fillSecurity()
    {
        $continue4 = $this->waitForElement(\WebDriverBy::xpath("(//button[contains(@class,'ffjNext_button')])[4]"), $this->timeout);
        $birthday = $this->waitForElement(\WebDriverBy::xpath("//input[@id='joinFormDateOfBirth']"), 0);
        $pin = $this->waitForElement(\WebDriverBy::xpath("//input[@id='joinFormPin']"), 0);
        $pinConfirm = $this->waitForElement(\WebDriverBy::xpath("//input[@id='joinFormConfirmPin']"), 0);

        $this->checkFieldExist([
            'continue4'  => $continue4,
            'birthday'   => $birthday,
            'pin'        => $pin,
            'pinConfirm' => $pinConfirm,
        ]);

        $birthday->sendKeys($this->fields['BirthdayDate']);
        $pin->sendKeys($this->fields['Password']);
        $pinConfirm->sendKeys($this->fields['Password']);

        $error = $this->waitForElement(\WebDriverBy::xpath("//div[@id='joinFormPin-desc']"), 3);

        if ($error) {
            $this->logger->error($error->getText());

            throw new \UserInputError($error->getText());
        }

        $this->saveResponse();
        $this->registerInfo = array_merge($this->registerInfo, [
            [
                'key'   => 'Your date of birth (DD/MM/YYYY)',
                'value' => $this->driver->executeScript("return document.querySelector('input[id=\'joinFormDateOfBirth\']').value;"),
            ],
            [
                'key'   => 'Pin',
                'value' => $this->driver->executeScript("return document.querySelector('input[id=\'joinFormPin\']').value;"),
            ],
            [
                'key'   => 'Phone',
                'value' => $this->driver->executeScript("return document.querySelector('input[id=\'joinFormPhoneNumber\']').value;"),
            ],
            [
                'key'   => 'StreetAddress',
                'value' => $this->driver->executeScript("return document.querySelector('input[id=\'joinFormAddressStreet\']').value;"),
            ],
            [
                'key'   => 'Suburb/town/city',
                'value' => $this->driver->executeScript("return document.querySelector('input[id=\'joinFormAddressSuburb\']').value;"),
            ],
            [
                'key'   => 'PostCode',
                'value' => $this->driver->executeScript("return document.querySelector('input[id=\'joinFormAddressPostCode\']').value;"),
            ],
            [
                'key'   => 'State',
                'value' => $this->driver->executeScript("return document.querySelector('button[id=\'joinFormAddressState\'] div').innerHTML;"),
            ],
        ]);

        $continue4->click();

        $error = $this->waitForElement(\WebDriverBy::xpath("//div[@id='joinFormPin-desc']"), 3);

        if ($error) {
            $this->logger->error($error->getText());

            throw new \UserInputError($error->getText());
        }
    }

    protected function modifyFields(array &$fields)
    {
        foreach ($fields as $key => $value) {
            $value = ltrim(rtrim($value));

            if ($key == 'BirthdayDate') {
                $fields[$key] = \DateTime::createFromFormat('m/d/Y', $value)->format('d/m/Y');
            } else {
                $fields[$key] = $value;
            }
        }
    }

    protected function getAction()
    {
        $url = 'https://www.qantas.com/us/en/frequent-flyer/discover-and-join/join.html';

        try {
            $this->http->GetURL($url);

            $success = $this->waitForElement(\WebDriverBy::xpath("//span[contains(text(),'Residential location')]"), $this->timeout);

            if (!$success) {
                throw new \WebDriverCurlException("Something going wrong. Try again later.");
            }
        } catch (\WebDriverCurlException $e) {
            $this->logger->error("Site timed out, please try again later");

            throw new \UserInputError("Site timed out, please try again later");
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
