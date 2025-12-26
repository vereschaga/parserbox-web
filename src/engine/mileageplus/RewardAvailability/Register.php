<?php

namespace AwardWallet\Engine\mileageplus\RewardAvailability;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use ProxyList;
    use \SeleniumCheckerHelper;

    private $authorizationApi;
    private $verifyInfo = [];
    private $fields;
    private $browser;

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->useSelenium();

        switch (rand(0, 1)) {
            case 0:
                $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);

                break;

            case 1:
                $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);

                break;

            case 2:
                $this->useFirefox(\SeleniumFinderRequest::FIREFOX_59);

                break;
        }

        $this->disableImages();
        $this->http->saveScreenshots = true;
        $this->seleniumOptions->recordRequests = true;

//        if ($this->AccountFields['ParseMode'] === 'awardwallet') {
//            $this->setProxyGoProxies();
//        } else {
        $this->setProxyNetNut();
//        }

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
        $this->http->setRandomUserAgent(null, true, false);
        $this->http->setHttp2(true);
    }

    public function getRegisterFields()
    {
        return [
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'First Name',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email address',
                'Required' => true,
            ],
            'PhoneNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Number (10 numbers length)',
                'Required' => true,
            ],
            'BirthdayDate' => [
                'Type'     => 'date',
                'Caption'  => 'Your date of birth, older than 18(MM/DD/YYYY)',
                'Required' => true,
            ],
            'Gender' => [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => ['male' => 'Male', 'female' => "Female"],
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'State',
                'Required' => true,
                'Options'  => ['US' => 'United States', 'CA' => 'Canada', 'MX' => 'Mexico'],
            ],
            'Address' => [
                'Type'     => 'string',
                'Caption'  => 'Address',
                'Required' => true,
                'Note'     => "Please use only the 26 English letters (A-Z), numerals (0-9), periods (.), hyphens (-), apostrophes ('), number signs (#), and spaces",
            ],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'City',
                'Required' => true,
                'Note'     => 'Please use only the 26 English letters (A-Z), periods (.), hyphens (-), and spaces.',
            ],
            'State' => [
                'Type'     => 'string',
                'Caption'  => 'State (US only)',
                'Required' => true,
                'Note'     => 'Please use only the 26 English letters (A-Z), periods (.), hyphens (-), and spaces.',
            ],
            'ZipCode' => [
                'Type'     => 'string',
                'Caption'  => 'Zip Code',
                'Required' => true,
                'Note'     => 'Please use only the 26 English letters (A-Z), numerals (0-9), hyphens (-), parentheses ( ), and spaces.',
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password',
                'Required' => true,
                'Note'     => "Must be at least 8 characters in length.Must include at least one letter and one number. Standard special characters (such as '!' '&' and '+') are optional. Dont use '¡','¿','¨']",
            ],
        ];
    }

    public function registerAccount(array $fields)
    {
        $this->logger->debug(var_export($fields, true), ['pre' => true]);
        $this->checkFields($fields);
        $this->fields = $fields;

        $this->browser = new \HttpBrowser("none", new \CurlDriver());

        $date = new \DateTime($fields['BirthdayDate'], new \DateTimeZone('UTC'));

        $this->http->GetURL('https://www.united.com/en/US/account/join-now/about-me');

        if ($cookieBtn = $this->waitForElement(\WebDriverBy::xpath('//a[@class="cc-btn cc-dismiss cc-btn-format"]'), 5)) {
            $cookieBtn->click();
        }

        $this->authorizationApi = $this->getAuthorizationHeader();

        $this->driver->executeScript("document.getElementById('email').scrollIntoView({ behavior: 'smooth', block: 'center' });");

        $emailInput = $this->waitForElement(\WebDriverBy::xpath('//input[@id="email"]'), 30);
        $phoneInput = $this->waitForElement(\WebDriverBy::xpath('//input[@id="homePhoneNumber"]'), 0);

        $firstNameInput = $this->waitForElement(\WebDriverBy::xpath('//input[@id="firstName"]'), 0);
        $lastNameInput = $this->waitForElement(\WebDriverBy::xpath('//input[@id="lastName"]'), 0);

        $mouthSelect = $this->waitForElement(\WebDriverBy::xpath('//select[@id="birthDate"]'), 0);
        $dayInput = $this->waitForElement(\WebDriverBy::xpath('//input[@placeholder="DD"]'), 0);
        $yearInput = $this->waitForElement(\WebDriverBy::xpath('//input[@placeholder="YYYY"]'), 0);

        $genderSelect = $this->waitForElement(\WebDriverBy::xpath('//select[@id="genderCode"]'), 0);

        $contBtn = $this->waitForElement(\WebDriverBy::xpath('//button[@class="atm-c-btn atm-c-btn--primary"]'), 0);

        if (!$emailInput || !$phoneInput || !$firstNameInput || !$lastNameInput || !$mouthSelect || !$dayInput || !$yearInput || !$genderSelect) {
            $this->logger->error('Page not loaded');

            throw new \CheckRetryNeededException(5, 0);
        }

        $emailInput->click();
        $emailInput->sendKeys($fields['Email']);

        $phoneInput->click();
        $phoneInput->sendKeys($fields['PhoneNumber']);
        $this->saveResponse();

        if ($cookieBtn = $this->waitForElement(\WebDriverBy::xpath('//a[@class="cc-btn cc-dismiss cc-btn-format"]'), 5)) {
            $cookieBtn->click();
        }

        $this->driver->executeScript("document.getElementById('genderCode').scrollIntoView({ behavior: 'smooth', block: 'center' });");

        $firstNameInput->click();
        $firstNameInput->sendKeys($fields['FirstName']);

        $lastNameInput->click();
        $lastNameInput->sendKeys($fields['LastName']);
        $this->saveResponse();

        $mouthSelect->click();
        $mouthOption = $this->waitForElement(\WebDriverBy::xpath("//select[@id='birthDate']/option[contains(text(), {$date->format('m')})]"), 0);
        $mouthOption->click();

        $dayInput->click();
        $dayInput->sendKeys($date->format('d'));

        $yearInput->click();
        $yearInput->sendKeys($date->format('Y'));
        $this->saveResponse();

        $genderSelect->click();
        $genderOption = $this->waitForElement(\WebDriverBy::xpath("//select[@id='genderCode']/option[contains(@value, '{$this->genderIdentification($fields['Gender'])}')]"), 0);
        $genderOption->click();

        $contBtn->click();

        if (!$this->waitForElement(\WebDriverBy::xpath('//select[@id="countryCode"]'), 30)) {
            $this->logger->error('Page not loaded');

            throw new \CheckRetryNeededException(5, 0);
        }

        $queryParamsPass = $this->runRegistration($fields, $date);

        $this->browser = new \HttpBrowser("none", new \CurlDriver());

        $this->browser->setSeleniumBrowserFamily(\SeleniumFinderRequest::BROWSER_FIREFOX);
        $this->browser->setSeleniumBrowserVersion(\SeleniumFinderRequest::FIREFOX_59);

        $this->browser->SetProxy("{$this->http->getProxyAddress()}:{$this->http->getProxyPort()}");
        $this->browser->setProxyAuth($this->http->getProxyLogin(), $this->http->getProxyPassword());
        $this->browser->setUserAgent($this->http->getDefaultHeader("User-Agent"));

        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                $cookie['expiry'] ?? null);
        }

        $headers = [
            "Accept"              => "application/json",
            "Accept-Encoding"     => "gzip, deflate, br, zstd",
            "Accept-Language"     => "en-US",
            "Origin"              => "https://www.united.com",
            "Content-Type"        => "application/json",
            "X-Authorization-Api" => $this->authorizationApi,
        ];

        $encryptedRegisterInfo = $this->runAccountSecuritySetup('https://www.united.com/xapi/auth/account-security-setup?', $queryParamsPass, $headers);
        $queryParamsMfa = $this->passwordInput('https://www.united.com/xapi/auth/security/password', $headers, $fields['Password'], $encryptedRegisterInfo);
        $keysForVerified = $this->runMfaContacts('https://www.united.com/xapi/auth/mfa-contacts?', $queryParamsMfa, $headers);

        $this->verifyInfo = [
            'email'             => $fields['Email'],
            'encryptedUserName' => $keysForVerified['encryptedUserName'],
            'key'               => $keysForVerified['email']['key'],
        ];

        if ($this->runEmailVerify('https://www.united.com/xapi/auth/step-up-verify', $headers) !== false) {
            return $this->parseQuestion();
        } else {
            $this->ErrorMessage = json_encode([
                "status"       => "success",
                "message"      => "The account has been created. You need to go through email verification on the website.",
                "login"        => $this->fields['Email'],
                "login2"       => '',
                "login3"       => '',
                "password"     => $this->fields["Password"],
                "email"        => $this->fields["Email"],
                "registerInfo" => [
                    [
                        "key"   => "FirstName",
                        "value" => $this->fields["FirstName"],
                    ],
                    [
                        "key"   => "LastName",
                        "value" => $this->fields["LastName"],
                    ],
                    [
                        "key"   => "BirthdayDate",
                        "value" => $this->fields["BirthdayDate"],
                    ],
                    [
                        "key"   => "Gender",
                        "value" => $this->fields['Gender'],
                    ],
                    [
                        "key"   => "Country",
                        "value" => $this->fields["Country"],
                    ],
                    [
                        "key"   => "Address",
                        "value" => $this->fields["Address"],
                    ],
                    [
                        "key"   => "City",
                        "value" => $this->fields["City"],
                    ],
                    [
                        "key"   => "State",
                        "value" => $this->fields["State"],
                    ],
                    [
                        "key"   => "ZipCode",
                        "value" => $this->fields["ZipCode"],
                    ],
                ],
                "active" => false,
            ], JSON_PRETTY_PRINT);
        }
        $this->browser->cleanup();

        return true;
    }

    public function parseQuestion()
    {
        $this->logger->debug(__METHOD__);

        $question = "Enter the verification code sent";

        $this->AskQuestion($question, null, 'Question');

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->debug(__METHOD__);

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $url = 'https://www.united.com/xapi/auth/step-up-complete';

        $headers = [
            "Accept"              => "application/json",
            "Content-Type"        => "application/json",
            "X-Authorization-Api" => $this->authorizationApi,
        ];

        $payload = [
            'action'  => "ADD_EMAIL",
            'payload' => [
                'deviceAuthenticationId' => '',
                'email'                  => $this->verifyInfo['email'],
                'encryptedUserName'      => $this->verifyInfo['encryptedUserName'],
                'key'                    => $this->verifyInfo['key'],
                'otp'                    => $answer,
            ],
        ];

        $this->browser->PostURL($url, json_encode($payload), $headers);
        $response = $this->browser->JsonLog(null, 1, true);

        if ($response["data"]["verificationStatus"] !== true) {
            $this->logger->error('The account has been registered, but has not been verified. Go through it manually (Login via email)');
        }

        $this->ErrorMessage = json_encode([
            "status"       => "success",
            "message"      => "Registration is successful! Membership number: in email",
            "login"        => $this->fields['Email'],
            "login2"       => '',
            "login3"       => '',
            "password"     => $this->fields["Password"],
            "email"        => $this->fields["Email"],
            "registerInfo" => [
                [
                    "key"   => "FirstName",
                    "value" => $this->fields["FirstName"],
                ],
                [
                    "key"   => "LastName",
                    "value" => $this->fields["LastName"],
                ],
                [
                    "key"   => "BirthdayDate",
                    "value" => $this->fields["BirthdayDate"],
                ],
                [
                    "key"   => "Gender",
                    "value" => $this->fields['Gender'],
                ],
                [
                    "key"   => "Country",
                    "value" => $this->fields["Country"],
                ],
                [
                    "key"   => "Address",
                    "value" => $this->fields["Address"],
                ],
                [
                    "key"   => "City",
                    "value" => $this->fields["City"],
                ],
                [
                    "key"   => "State",
                    "value" => $this->fields["State"],
                ],
                [
                    "key"   => "ZipCode",
                    "value" => $this->fields["ZipCode"],
                ],
            ],
            "active" => true,
        ], JSON_PRETTY_PRINT);

        $this->browser->cleanup();

        return true;
    }

    protected function checkFields(&$fields): void
    {
        if (!filter_var($fields['Email'], FILTER_VALIDATE_EMAIL)) {
            throw new \UserInputError('Email address contains an incorrect format');
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['FirstName'])) {
            throw new \UserInputError('First Name contains an incorrect symbol');
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['LastName'])) {
            throw new \UserInputError('Last Name contains an incorrect symbol');
        }

        if ((strlen($fields['Password']) < 8 || strlen($fields['Password']) > 30)
            || !preg_match("/[a-z]/", $fields['Password'])
            || !preg_match("/[0-9]/", $fields['Password']) !== false
            || preg_match("/[%&¡¿¨]/", $fields['Password'])) {
            throw new \UserInputError("Must be at least 8 characters in length.Must include at least one letter and one number. Standard special characters (such as '!' '&' and '+') are optional. Dont use '¡','¿','¨']");
        }

        if (preg_match("/[*¡!?¿<>\\ºª|\/\·@$%&№,;=?¿())_+{}\[\]\"\^€\$£]/", $fields['Address'])) {
            throw new \UserInputError('Address Line contains an incorrect symbol');
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@$%&№,;=?¿())_+{}\[\]\"\^€\$£]/", $fields['City'])) {
            throw new \UserInputError('City contains an incorrect symbol');
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@$%&№,;=?¿())_+{}\[\]\"\^€\$£]/", $fields['State'])) {
            throw new \UserInputError('State contains an incorrect symbol');
        }

        if (preg_match("/[*¡!?¿<>\\ºª|\/\·@$%&№,;=?¿_+{}\[\]\"\^€\$£]/", $fields['ZipCode'])) {
            throw new \UserInputError('Zip Code contains an incorrect symbol');
        }
    }

    private function splitAddress(string $address): array
    {
        if (strlen($address) > 20) {
            $middle = floor(strlen($address) / 2);
            $leftSpace = strrpos(substr($address, 0, $middle), ' ');
            $rightSpace = strpos($address, ' ', $middle);

            if ($leftSpace === false) {
                $splitPos = $rightSpace;
            } elseif ($rightSpace === false) {
                $splitPos = $leftSpace;
            } else {
                $splitPos = ($middle - $leftSpace <= $rightSpace - $middle) ? $leftSpace : $rightSpace;
            }

            if ($splitPos === false) {
                $splitPos = $middle;
            }

            $line1 = substr($address, 0, $splitPos);
            $line2 = substr($address, $splitPos + 1);

            return [$line1, $line2];
        }

        return [$address, ''];
    }

    private function genderIdentification(string $gender): string
    {
        switch ($gender) {
            case 'male':
                return 'M';

            case 'female':
                return 'F';

            default:
                return 'Unspecified';
        }
    }

    private function runRegistration(array $fields, \DateTime $date): array
    {
        $this->logger->debug(__METHOD__);

        $birthDate = $date->format('Y-m-d\TH:i:s\Z');
        $address = $this->splitAddress($fields["Address"]);

        $body = [
            "middleName"                 => "",
            "lastName"                   => $fields["LastName"],
            "birthDate"                  => $birthDate,
            "genderCode"                 => $this->genderIdentification($fields["Gender"]),
            "homePhoneCountryCode"       => "US",
            "title"                      => "",
            "homePhoneNumber"            => $fields["PhoneNumber"],
            "firstName"                  => $fields["FirstName"],
            "suffixName"                 => "",
            "email"                      => $fields["Email"],
            "iIsDayOfTravelContact"      => false,
            "countryCode"                => $fields["Country"],
            "addressLine1"               => $address[0],
            "addressLine2"               => $address[1],
            "addressLine3"               => "",
            "city"                       => $fields["City"],
            "state"                      => $fields["State"],
            "postalCode"                 => $fields["ZipCode"],
            "airRewardProgramSourceCode" => "IN21",
            "sendConfirmationEmail"      => true,
            "subscriptions"              => [
                "UADL",
                "MPPT",
                "MPPR",
                "OPST",
            ],
            "UseAddressValidation"     => false,
            "UsePhoneValidation"       => false,
        ];
        $body = json_encode($body);

        $tt = '
            var xhttp = new XMLHttpRequest();
            xhttp.open("POST", "https://www.united.com/xapi/myunited/user/enroll", false);
            xhttp.setRequestHeader("Content-type", "application/json");
            xhttp.setRequestHeader("Accept", "application/json");
            xhttp.setRequestHeader("Origin","https://www.united.com");
            xhttp.setRequestHeader("Referer","https://www.united.com/en/us/account/join-now/address");
            xhttp.setRequestHeader("x-authorization-api","' . $this->authorizationApi . '");
        
            var data = JSON.stringify(' . $body . ');
            var responseText = null;
            xhttp.onreadystatechange = function() {
                responseText = this.responseText;  
            };
            
            xhttp.send(data);
            return responseText;
        ';

        $response = $this->driver->executeScript($tt);
        $returnData = $this->http->JsonLog($response, 1, true);

        if (isset($returnData['verify_url'])) {
            $this->autoVerifyRequest($returnData);

            $response = $this->driver->executeScript($tt);
            $returnData = $this->http->JsonLog($response, 1, true);
        }

        if (!isset($returnData)) {
            return [];
        }

        if (isset($returnData["errors"])) {
            throw new \CheckException($returnData["errors"][0]["description"], ACCOUNT_PROVIDER_ERROR);
        }

        $parts = parse_url($returnData['data']['links'][0]['Url']);
        parse_str($parts['query'], $queryParams);

        $this->logger->debug(var_export($queryParams, true), ['pre' => true]);

        return $queryParams;
    }

    private function runAccountSecuritySetup(string $url, array $queryParamsPass, array $headers): array
    {
        $this->logger->debug(__METHOD__);

        $headers['Referer'] = 'https://www.united.com/en/us/account/security/password?' . http_build_query($queryParamsPass);

        $this->browser->GetURL($url . http_build_query($queryParamsPass), $headers);
        $response = $this->browser->JsonLog(null, 1, true);

        if ((!isset($response["data"]["encryptedUserName"])) && (!isset($response["data"]["encryptEnrollmentResponseData"]))) {
            throw new \CheckException("The request was not accepted by the server. You need to activate your account manually. Login arrived by email.", ACCOUNT_PROVIDER_ERROR);
        }

        return $response["data"];
    }

    private function runMfaContacts(string $url, array $queryParamsMfa, array $headers): array
    {
        $this->logger->debug(__METHOD__);

        $headers['Referer'] = 'https://www.united.com/en/us/account/security/mfa?' . http_build_query($queryParamsMfa);

        $this->browser->GetURL($url . http_build_query($queryParamsMfa), $headers);
        $response = $this->browser->JsonLog(null, 1, true);

        if (!isset($response["data"])) {
            throw new \CheckException($response["errors"][0]["description"], ACCOUNT_PROVIDER_ERROR);
        }

        return $response['data'];
    }

    private function runEmailVerify(string $url, array $headers): bool
    {
        $this->logger->debug(__METHOD__);

        $payload = [
            'action'  => "ADD_EMAIL",
            'payload' => [
                'email'             => $this->verifyInfo['email'],
                'encryptedUserName' => $this->verifyInfo['encryptedUserName'],
                'key'               => $this->verifyInfo['key'],
            ],
        ];

        $this->browser->PostURL($url, json_encode($payload), $headers);
        $response = $this->browser->JsonLog(null, 1, true);

        if (!str_contains($response["data"]["status"], 'SUCCESS')) {
            throw new \CheckException($response["data"]["detail"], ACCOUNT_PROVIDER_ERROR);
        }

        return true;
    }

    private function getAuthorizationHeader(): string
    {
        $this->logger->notice(__METHOD__);

        $seleniumDriver = $this->http->driver;
        $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

        $auth = null;

        foreach ($requests as $n => $xhr) {
            $auth = $xhr->request->getHeaders()['X-Authorization-api'] ?? $auth;
        }
        $this->logger->debug("xhr auth: $auth");

        if (!isset($auth)) {
            throw new \EngineError("no auth. try again");
        }

        return $auth;
    }

    private function passwordInput(string $url, array $headers, string $password, array $encryptedRegisterInfo): array
    {
        $this->logger->debug(__METHOD__);

        $payload = [
            'encryptEnrollmentResponseData' => $encryptedRegisterInfo['encryptEnrollmentResponseData'],
            'password'                      => $password,
            'userName'                      => $encryptedRegisterInfo['encryptedUserName'],
        ];

        $this->browser->RetryCount = 0;
        $this->browser->PostURL($url, json_encode($payload), $headers);
        $response = $this->browser->JsonLog(null, 1, true);

        if (isset($response["errors"])) {
            throw new \CheckException($response["errors"][0]["description"], ACCOUNT_PROVIDER_ERROR);
        }

        $parts = parse_url($response['data']['links'][0]['Url']);
        parse_str($parts['query'], $queryParams);

        $this->logger->debug(var_export($queryParams, true), ['pre' => true]);

        return $queryParams;
    }

    private function autoVerifyRequest($returnData)
    {
        $this->logger->notice(__METHOD__);

        if (isset($returnData['verify_url'])) {
            $this->logger->error('Verify this request');

            $frame = $this->waitForElement(\WebDriverBy::xpath('//iframe[@id="sec-cpt-if"]'), 0);

            if (!$frame) {
                throw new \CheckRetryNeededException(5, 0);
            }

            $this->driver->switchTo()->frame($frame);

            do {
                try {
                    $tic = $this->waitForElement(\WebDriverBy::xpath('//div[@id="sec-ch-ctdn-timer"]'), 5, false)->getText();
                    sleep(2);
                    $nextTic = $this->waitForElement(\WebDriverBy::xpath('//div[@id="sec-ch-ctdn-timer"]'), 1, false)->getText();
                } catch (\Error $e) {
                    $this->logger->error('Don\'t verified...');

                    break;
                }

                $this->logger->debug('Verify...');
            } while ($tic !== $nextTic);
            $this->logger->error('Verified!');
            $this->driver->switchTo()->defaultContent();

            return true;
        }

        $this->logger->error('Don\'t need verify');

        return false;
    }
}
