<?php

namespace AwardWallet\Engine\turkish\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\turkish\RewardAvailability\Helpers\FormFieldsInformation;
use Facebook\WebDriver\Exception\WebDriverCurlException;
use Facebook\WebDriver\Exception\WebDriverException;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \AwardWallet\Engine\ProxyList;

    private $timeout = 50;
    private $number;
    private $registerInfo = [];

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->UseSelenium();

        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
//        $this->useFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        $this->usePacFile(false);
        $this->seleniumRequest->setOs("mac");

        $this->disableImages();
        $this->http->saveScreenshots = true;

        $array = ['us', 'fr', 'uk', 'es', 'de', 'au', 'il', 'fi'];
        $targeting = $array[array_rand($array)];
        $this->setProxyBrightData(null, "static", $targeting);

        $resolutions = [
            [1024, 768],
            [1152, 864],
            [1280, 800],
        ];

        /*        $request = FingerprintRequest::firefox();
                $request->browserVersionMin = 100;
                $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                if (isset($fingerprint)) {
                    $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                    $this->http->setUserAgent($fingerprint->getUseragent());
                    $this->seleniumOptions->userAgent = $fingerprint->getUseragent();
                }*/

        $this->setScreenResolution($resolutions[array_rand($resolutions)]);
        $this->useCache();
    }

    public function registerAccount(array $fields)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug(var_export($fields, true), ['pre' => true]);

        $this->modifyFields($fields);

        $this->checkFields($fields);

        try {
            $this->http->GetURL("https://www.turkishairlines.com/en-int/miles-and-smiles/sign-up-form/");
        } catch (\WebDriverException | \WebDriverException | WebDriverCurlException | WebDriverException $e) {
            $this->logger->error($e->getMessage());

            throw new \EngineError('Something going wrong, please try again.');
        }

        $this->saveResponse();

        if ($this->http->FindSingleNode("//p[contains(.,'An error occurred during a connection to')]")) {
            throw new \EngineError('Something going wrong, please try again.');
        }

        $this->fillForm($fields);

        return $this->checkLogin();
    }

    public function getRegisterFields()
    {
        return FormFieldsInformation::getRegisterFields();
    }

    protected function fillForm($fields)
    {
        $this->logger->notice(__METHOD__);

        $firstName = $this->waitForElement(\WebDriverBy::xpath("//input[contains(@id,'signup-firstname')]"),
            $this->timeout);
        $lastName = $this->waitForElement(\WebDriverBy::xpath("//input[contains(@id,'signup-surname')]"), 0);
        $birthday = $this->waitForElement(\WebDriverBy::xpath("//label[@id='birthdateInput']/../input"), 0);

        $this->checkFieldExist([
            'firstName' => $firstName,
            'lastName'  => $lastName,
            'birthday'  => $birthday,
        ]);

        $firstName->sendKeys($fields['FirstName']);
        $lastName->sendKeys($fields['LastName']);
        $birthday->sendKeys($fields['BirthdayDate']);

        $this->saveResponse();

        $lang = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@data-id,'signup-prfLanguage')]"), 10);
        $nationality = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@data-id,'signup-nationality')]"),
            10);
        $gender = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@data-id,'signup-gender')]"), 10);
        $phone = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@data-id,'signup-mobilePhoneNumberCode')]"),
            10);
//        $question = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@data-id,'signup-question')]"), 10);
        $country = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@data-id,'signup-countryCode')]"), 10);

        $this->checkFieldExist([
            'lang'        => $lang,
            'nationality' => $nationality,
            'gender'      => $gender,
            'phone'       => $phone,
            //            'question'    => $question,
            'country'     => $country,
        ]);

        $genderValue = $fields['Gender'] === 'male' ? 1 : 2;
        /*
                    document.querySelector('button[data-id=\"signup-question\"]').click();
                    document.querySelector('button[data-id=\"signup-question\"]').parentElement.querySelector('li[data-original-index=\"1\"] a').click();

         * */
        $this->driver->executeScript("
            document.querySelector('button[data-id=\"signup-prfLanguage\"]').click();
            document.querySelector('button[data-id=\"signup-prfLanguage\"]').parentElement.querySelector('li[data-original-index=\"2\"] a').click();
            document.querySelector('button[data-id=\"signup-nationality\"]').click();
            document.querySelector('button[data-id=\"signup-nationality\"]').parentElement.querySelector('li[data-original-index=\"242\"] a').click();
            document.querySelector('button[data-id=\"signup-gender\"]').click();
            document.querySelector('button[data-id=\"signup-gender\"]').parentElement.querySelector('li[data-original-index=\"{$genderValue}\"] a').click();
            document.querySelector('button[data-id=\"signup-mobilePhoneNumberCode\"]').click();
            document.querySelector('button[data-id=\"signup-mobilePhoneNumberCode\"]').parentElement.querySelector('li[data-original-index=\"238\"] a').click();
            document.querySelector('button[data-id=\"signup-countryCode\"]').click();
            document.querySelector('button[data-id=\"signup-countryCode\"]').parentElement.querySelector('li[data-original-index=\"231\"] a').click();
        ");

        $state = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@data-id,'signup-stateCode')]"), 10);
        $this->checkFieldExist(['state' => $state]);
        $state = FormFieldsInformation::$states[$fields['State']];
        $this->saveResponse();
        $indexState = $this->http->FindSingleNode("//button[@data-id='signup-stateCode']/following-sibling::div[1]//li//a[contains(.,'({$fields['State']})')]/ancestor::li[1]/@data-original-index");
        $this->driver->executeScript("
            document.querySelector('button[data-id=\"signup-stateCode\"]').click();
            document.querySelector('button[data-id=\"signup-stateCode\"]').parentElement.querySelector('li[data-original-index=\"{$indexState}\"] a').click();
        ");

        $city = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@data-id,'signup-stateCityCode') and not(contains(@class,'disabled'))]"),
            10);
        $this->checkFieldExist(['city' => $city]);
        $this->saveResponse();
        $citisList = count($this->http->FindNodes("//button[contains(@data-id,'signup-stateCityCode') and not(contains(@class,'disabled'))]/following-sibling::div[1]//li"));

        if ($citisList < 2) {
            $this->logger->error('no cities in list');

            throw new \EngineError('something wrong with city');
        }
        $cityIndex = random_int(1, $citisList - 1);
        $this->driver->executeScript("
            document.querySelector('button[data-id=\"signup-stateCityCode\"]').click();
            document.querySelector('button[data-id=\"signup-stateCityCode\"]').parentElement.querySelector('li[data-original-index=\"{$cityIndex}\"] a').click();
        ");

        $zip = $this->waitForElement(\WebDriverBy::xpath("//label[@id='postCode']/../input"), 10);
        $address = $this->waitForElement(\WebDriverBy::xpath("//label[@id='addressLine1']/../input"), 10);
        $email = $this->waitForElement(\WebDriverBy::xpath("//label[@id='emailInput']/../input"), 10);
        $mobile = $this->waitForElement(\WebDriverBy::xpath("//label[@id='mobilePhone']/../input"), 10);
        $password = $this->waitForElement(\WebDriverBy::xpath("//label[@id='passwordInput']/../input"), 10);
        $passwordConfirm = $this->waitForElement(\WebDriverBy::xpath("//label[@id='passwordReInput']/../input"), 10);
//        $answer = $this->waitForElement(\WebDriverBy::xpath("//label[@id='securityQuestion']/../input"), 10);

        $this->checkFieldExist([
            'zip'             => $zip,
            'address'         => $address,
            'email'           => $email,
            'mobile'          => $mobile,
            'password'        => $password,
            'passwordConfirm' => $passwordConfirm,
            //            'answer'          => $answer,
        ]);

        $zip->sendKeys($fields['ZipCode']);
        $this->saveResponse();
        $address->sendKeys($fields['Address']);
        $email->sendKeys($fields['Email']);
        $mobile->sendKeys($fields['PhoneNumber']);
        $this->saveResponse();
        $password->sendKeys($fields['Password']);
        $passwordConfirm->sendKeys($fields['Password']);
//        $answer->sendKeys($fields['Answer']);
        $this->saveResponse();

        $checkbox1 = $this->waitForElement(\WebDriverBy::xpath("//div[@id='signup-agreement']//span[@class='check']"),
            10);
        $checkbox2 = $this->waitForElement(\WebDriverBy::xpath("//div[@id='signup-iysCommunicationInfo']//span[@class='check']"),
            10);
        $checkbox3 = $this->waitForElement(\WebDriverBy::xpath("//div[@id='signup-kvkgdprInfo']//span[@class='check']"),
            10);

        $this->checkFieldExist([
            'checkbox1' => $checkbox1,
            'checkbox2' => $checkbox2,
            'checkbox3' => $checkbox3,
        ]);

        $this->driver->executeScript("
            document.querySelector('#signup-agreement .check').click();
            document.querySelector('#signup-iysCommunicationInfo .check').click();
            document.querySelector('#signup-kvkgdprInfo .check').click();
        ");

        $this->runXHR();

        $firstName->click();
        $this->saveResponse();

        $this->registerInfo = array_merge($this->registerInfo, [
            [
                'key'   => 'FirstName',
                'value' => $this->http->FindSingleNode("//input[contains(@id,'signup-firstname')]/@data-value"),
            ],
            [
                'key'   => 'LastName',
                'value' => $this->http->FindSingleNode("//input[contains(@id,'signup-surname')]/@data-value"),
            ],
            [
                'key'   => 'BirthdatDate',
                'value' => $this->http->FindSingleNode("//label[@id='birthdateInput']/../input/@data-value"),
            ],
            [
                'key'   => 'Language',
                'value' => $this->http->FindSingleNode("//button[contains(@data-id,'signup-prfLanguage')]"),
            ],
            [
                'key'   => 'Nationality',
                'value' => $this->http->FindSingleNode("//button[contains(@data-id,'signup-nationality')]"),
            ],
            [
                'key'   => 'Gender',
                'value' => $this->http->FindSingleNode("//button[contains(@data-id,'signup-gender')]"),
            ],
            [
                'key'   => 'PhoneCode',
                'value' => $this->http->FindSingleNode("//button[contains(@data-id,'signup-mobilePhoneNumberCode')]"),
            ],
            [
                'key'   => 'Country/Region',
                'value' => $this->http->FindSingleNode("//button[contains(@data-id,'signup-countryCode')]"),
            ],
            [
                'key'   => 'State',
                'value' => $this->http->FindSingleNode("//button[contains(@data-id,'signup-stateCode')]"),
            ],
            [
                'key'   => 'City',
                'value' => $this->http->FindSingleNode("//button[contains(@data-id,'signup-stateCityCode') and not(contains(@class,'disabled'))]"),
            ],
            [
                'key'   => 'Zip/Postal Code',
                'value' => $this->http->FindSingleNode("//label[@id='postCode']/../input/@data-value"),
            ],
            [
                'key'   => 'Address',
                'value' => $this->http->FindSingleNode("//label[@id='addressLine1']/../input/@data-value"),
            ],
            [
                'key'   => 'PassConfirm',
                'value' => $this->http->FindSingleNode("//label[@id='passwordReInput']/../input/@data-value"),
            ],
            [
                'key'   => 'Mobile',
                'value' => $this->http->FindSingleNode("//label[@id='mobilePhone']/../input/@data-value"),
            ],
            //            [
            //                'key'   => 'Sequrity question',
            //                'value' => $this->http->FindSingleNode("//button[contains(@data-id,'signup-question')]"),
            //            ],
            //            [
            //                'key'   => 'Answer',
            //                'value' => $this->http->FindSingleNode("//label[@id='securityQuestion']/../input/@data-value"),
            //            ],
        ]);

        $this->logger->debug(var_export($this->registerInfo, true), ["pre" => true]);

        $signIn = $this->waitForElement(\WebDriverBy::xpath("//a[@id='btnSignUp']"), 10);
        $this->checkFieldExist(['signIn' => $signIn]);
        $this->driver->executeScript("
            document.querySelector('#btnSignUp').click();
        ");

        $error = $this->waitForElement(\WebDriverBy::xpath("
            //p[contains(text(),'Another membership has been registered') or contains(text(),'Another membership account')]
            | //p[contains(text(),'We are sorry to inform you that we are unable to process your transaction at the moment.')]
            | (//span[id=\"errormessage\"])[1]"), 10);
        $this->saveResponse();

        if ($error) {
            if (strpos($error->getText(), 'We are sorry to inform you that we are unable') !== false) {
                throw new \ProviderError($error->getText());
            }

            throw new \UserInputError($error->getText());
        }
    }

    protected function checkLogin()
    {
        $this->logger->notice(__METHOD__);

        $json = $this->driver->executeScript('
            return sessionStorage.getItem("signupResult");
        ');
        $data = $this->http->JsonLog($json, 1, true);

        if (!is_null($data) && !empty($data)) {
            if (isset($data['error']['validationMessages'][0]['field'])) {
                $this->logger->error($data['error']['validationMessages'][0]['field']);

                if ($data['error']['validationMessages'][0]['field'] === 'java.net.SocketTimeoutException: Async operation timed out') {
                    throw new \ProviderError('Check your email. Perhaps the account is registered, but something went wrong on the site');
                }

                if ($data['error']['validationMessages'][0]['field'] === 'signup-password') {
                    $msg = $this->http->FindSingleNode("//span[@id='errormessage']");

                    if ($msg) {
                        throw new \UserInputError($msg);
                    }

                    return false;
                }
            }

            if (null !== $data['data']['milesProgramInfo']['ffId']) {
                $membershipNumber = str_replace('TK', '', $data['data']['milesProgramInfo']['ffId']);

                $this->ErrorMessage = json_encode([
                    "status"       => "success",
                    "message"      => "Registration is successful! Membership number: {$membershipNumber}",
                    "login"        => $membershipNumber,
                    "login2"       => 1,
                    "registerInfo" => $this->registerInfo,
                ], JSON_PRETTY_PRINT);

                return true;
            }
        }

        $signin = $this->waitForElement(\WebDriverBy::xpath('
        //button[@id = "signoutBTN"] 
        | //div[@data-bind="text: ffpNumber()"]
        | //div[@id="modalContainer"]//div[contains(@class,"modal-dialog")]//h4/following-sibling::p[contains(.,"Dear passenger")]/following-sibling::p[1]
        '),
            20);
        $this->saveResponse();

        if ($msg = $this->http->FindSingleNode('//div[@id="modalContainer"]//div[contains(@class,"modal-dialog")]//h4/following-sibling::p[contains(.,"Dear passenger")]/following-sibling::p[1][contains(.,"There is another membership")]')) {
            throw new \ProviderError($msg);
        }

        if (!$signin) {
            throw new \EngineError('Something going wrong, please try again.');
        }

        try {
            $this->http->GetURL("https://www.turkishairlines.com/en-int/miles-and-smiles/account/#information");
        } catch (\WebDriverException | \WebDriverException | WebDriverCurlException | WebDriverException $e) {
            $this->logger->error($e->getMessage());

            throw new \EngineError('Something going wrong, please try again.');
        }

        $success = $this->waitForElement(\WebDriverBy::xpath('//div[@id="personalinfo"]//span[contains(text(),"Your Miles&Smiles membership number")]/../h4'),
            20);
        $this->saveResponse();

        if ($success) {
            $membershipNumber = str_replace('TK', '', $success->getText());

            $this->ErrorMessage = json_encode([
                "status"       => "success",
                "message"      => "Registration is successful! Membership number: {$membershipNumber}",
                "login"        => $membershipNumber,
                "login2"       => 1,
                "registerInfo" => $this->registerInfo,
            ], JSON_PRETTY_PRINT);

            return true;
        }

        return false;
    }

    protected function checkFields(&$fields)
    {
        $this->logger->notice(__METHOD__);

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['FirstName'])) {
            throw new \UserInputError('FirstName contains an incorrect symbol');
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['LastName'])) {
            throw new \UserInputError('LastName contains an incorrect symbol');
        }

        if (!preg_match("/^(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[012])\/(19[3-9][0-9]|20[01][0-9])$/",
            $fields['BirthdayDate'])) {
            throw new \UserInputError('BirthdayDate contains an incorrect number');
        }

        if (strlen($fields['Address']) > 29 || preg_match("/[*¡!?¿<>ºª|\·@#$%&;=?¿())_+{}\-\[\]\"\^€\$£']/",
                $fields['Address'])) {
            throw new \UserInputError('Address (Must 0-29 characters or numbers long (include . , / \ and space) )');
        }

        if (!preg_match("/[A-Z0-9a-z._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/", $fields['Email'])) {
            throw new \UserInputError('Email address contains an incorrect format');
        }

        if (!(strlen($fields['PhoneNumber']) == 10)) {
            throw new \UserInputError('Phone Number must be 10 numbers length');
        }

        if (strlen($fields['Password']) != 6 || preg_match("/[A-Z]/", $fields['Password'])
            || preg_match("/[a-z]/", $fields['Password']) || !preg_match("/[0-9]/",
                $fields['Password']) || strpos($fields['Password'], ' ') !== false
        ) {
            throw new \UserInputError("Your password must be 6 unique numbers only");
        }

//        Your password must not contain 3 identical numbers or 3 consecutive numbers
        if (strlen($fields['Answer']) > 20 || strlen($fields['Answer']) < 3 || preg_match("/[*¡!?¿<>ºª|\·@#$%&;=?¿())_+{}\-\[\]\"\^€\$£']/",
                $fields['Answer'])) {
            throw new \UserInputError('Answer (Must 3-20 characters or numbers long)');
        }
    }

    protected function modifyFields(array &$fields)
    {
        $this->logger->notice(__METHOD__);

        foreach ($fields as $key => $value) {
            $value = ltrim(rtrim($value));

            if ($key == 'BirthdayDate') {
                $fields[$key] = \DateTime::createFromFormat('m/d/Y', $value)->format('d/m/Y');
            } else {
                $fields[$key] = $value;
            }
        }
    }

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

    private function runXHR()
    {
        $this->logger->notice(__METHOD__);
        $this->driver->executeScript(/** @lang JavaScript */
            '
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        if (/miles\/signup/g.exec(url)) {
                            sessionStorage.setItem("signupResult", this.responseText)
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
            ');
    }
}
