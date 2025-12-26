<?php

namespace AwardWallet\Engine\qmiles\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\qmiles\RewardAvailability\Helpers\FormFieldsInformation;
use AwardWallet\Engine\Settings;

class Register extends \TAccountChecker
{
    use ProxyList;
    use \SeleniumCheckerHelper;

    private $fields;
    private $fingerprint;
    private $registerInfo;
    private $failedTwoFaErrorMessage;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = false;

        switch (random_int(1, 2)) {
            case 1:
                $request = FingerprintRequest::chrome();

                break;

            case 2:
                $request = FingerprintRequest::firefox();

                break;

            default:
                $request = FingerprintRequest::safari();
        }

        $request->browserVersionMin = 100;
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if (isset($fingerprint)) {
            $this->http->setUserAgent($fingerprint->getUseragent());
            $this->seleniumOptions->userAgent = $fingerprint->getUseragent();
            $this->seleniumOptions->fingerprintOptions = $fingerprint->getFingerprint();
        } else {
            $this->http->setRandomUserAgent(null, false, true, false, true, false);
        }

        $array = ['gb', 'fr', 'au'];
        $targeting = $array[array_rand($array)];

        $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC, $targeting);
    }

    public function getRegisterFields()
    {
        return FormFieldsInformation::getRegisterFields();
    }

    public function registerAccount(array $fields)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug(var_export($fields, true), ['pre' => true]);

        $this->checkFields($fields);
        $this->modifyFields($fields);
        $this->fields = $fields;

        try {
            $this->sendSensorData();
            $this->sendRegisterRequest($fields);

            $response = $this->http->JsonLog(null, 3, true);

            if (isset($response["errorObject"])) {
                switch ($errorName = $response["errorObject"][0]['errorName'] ?? 'unknown error') {
                    case 'FFP_PROF_EC_ME':
                        throw new \UserInputError('This email already registered');

                    case 'FFP_EMAIL_INV':
                        throw new \UserInputError('Please enter a valid email address');

                    case 'MOB_LIMIT_EXCEEDS':
                        throw new \UserInputError('Your mobile number is linked to multiple accounts. Please enter a new mobile number.');

                    default:
                        $this->sendNotification("check unknown error of /qr/bot/enrollment/joinnow qmiles method. Error: {$errorName} // DA");

                        throw new \EngineError('Unknown error of registration');
                }
            } elseif (isset($response["customerProfileId"])) {
                $question = 'Thanks for registering with Qatar Airways Privilege Club. You’ll soon receive an email from us to activate your account. Please check your spam folder if it does not arrive in your inbox.';
                $this->registerInfo = [
                    [
                        "key"   => "First Name",
                        "value" => $this->fields['FirstName'],
                    ],
                    [
                        "key"   => "Last Name",
                        "value" => $this->fields['LastName'],
                    ],
                    [
                        "key"   => "Birth Date",
                        "value" => $this->fields['BirthdayDate'],
                    ],
                    [
                        "key"   => "Phone Number",
                        "value" => "1" . $this->fields['PhoneNumber'],
                    ],
                    [
                        "key"   => "Country",
                        "value" => "US",
                    ],
                    [
                        "key"   => "Middle Name",
                        "value" => $this->fields['MiddleName'],
                    ],
                    [
                        "key"   => "Gender",
                        "value" => $this->fields['Gender'],
                    ],
                ];
                $this->failedTwoFaErrorMessage = [
                    "status"       => "success",
                    "message"      => "Registration is successful! Go to email and confirm it. Email login: {$this->fields['Email']}",
                    "active"       => false,
                    "login"        => null,
                    "password"     => $this->fields['Password'],
                    "email"        => $this->fields['Email'],
                    "registerInfo" => $this->registerInfo,
                ];
                $this->checkSendEmail($question);

                return false;
            }
        } catch (\ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
        } catch (\Facebook\WebDriver\Exception\WebDriverException | \WebDriverException $e) {
            $this->logger->error($e->getMessage());

            throw new \EngineError('Forms were not loaded. Try to register account again');
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'New session attempts retry count exceeded') === false) {
                throw $e;
            }
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "New session attempts retry count exceeded";

            throw new \EngineError('no register form or other format');
        }

        $this->ErrorMessage = "Something going wrong";
        $this->saveResponse();

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if (!$this->Question) {
            $this->logger->error("something went wrong");

            return false;
        }

        if (isset($this->Answers[$this->Question])) {
            $this->logger->info('Got verification link.');
            $answer = $this->Answers[$this->Question];
            unset($this->Answers[$this->Question]);

            $verificationLink = $this->http->FindPreg('/^<\d+>-<(http.+)>$/', false, $answer);
            $membershipNumber = $this->http->FindPreg('/^<(\d+)>-<http.+>$/', false, $answer);
            $this->logger->notice('Verification link: ' . $verificationLink);
            $this->logger->notice('Membership number: ' . $membershipNumber);

            if (!strpos($verificationLink, 'https://www.qatarairways.com/en/Privilege-Club/loginpage.html?')) {
                throw new \CheckException('Verification link is not valid', ACCOUNT_ENGINE_ERROR);
            }

            if (!$membershipNumber) {
                throw new \CheckException('Membeship number was not found', ACCOUNT_ENGINE_ERROR);
            }

            return $this->useVerificationLink($verificationLink, $membershipNumber);
        }

        $this->logger->info('Answer is empty.');

        $this->ErrorMessage = json_encode($this->failedTwoFaErrorMessage, JSON_PRETTY_PRINT);

        return true;
    }

    protected function checkFields(&$fields)
    {
        if (preg_match("/[^a-zA-Z]/", $fields['FirstName'])
            || strlen($fields['FirstName']) > 50) {
            throw new \UserInputError('FirstName contains an incorrect symbol');
        }

        if (preg_match("/[^a-zA-Z]/", $fields['LastName'])
            || strlen($fields['LastName']) > 50) {
            throw new \UserInputError('LastName contains an incorrect symbol');
        }

        if (isset($fields['MiddleName'])
            && (preg_match("/[^a-zA-Z]/", $fields['MiddleName'])
                || strlen($fields['MiddleName']) > 50)
        ) {
            throw new \UserInputError('MiddleName contains an incorrect symbol');
        }

        if (
            !preg_match("/^(0[1-9]|1[012])\/(0[1-9]|[12][0-9]|3[01])\/(19[3-9][0-9]|20[01][0-9])$/",
                $fields['BirthdayDate'])
            || !$this->validateDate($fields['BirthdayDate'])
        ) {
            throw new \UserInputError('BirthdayDate contains an incorrect symbol or incorrect format. mm/dd/YYYY format is required.');
        }

        $diff = ((new \DateTimeImmutable($fields['BirthdayDate']))->diff(new \DateTimeImmutable()));

        if ($diff->format('%y') < 18) {
            throw new \UserInputError('BirthdayDate must be more than 18 years old');
        }

        if (!preg_match("/[A-Z0-9a-z._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/", $fields['Email'])) {
            throw new \UserInputError('Email address contains an incorrect format');
        }

        if (strlen($fields['Password']) < 8 || strlen($fields['Password']) > 25 || !preg_match("/[A-Z]/",
                $fields['Password'])
            || !preg_match("/[a-z]/", $fields['Password']) || !preg_match("/[0-9]/",
                $fields['Password']) || !preg_match("/[!@#$%?&*]/", $fields['Password'])
        ) {
            throw new \UserInputError("Your password must be 8-32 characters and include at least 1 lowercase letter, 1 uppercase letter, 1 number and 1 special character of !@#$%?&*");
        }

        if (strlen($fields['PhoneNumber']) != 10
            || preg_match("/[a-zA-Z*¡!?¿<>ºª|\·@#$%&;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['PhoneNumber'])
            || !in_array(substr($fields['PhoneNumber'], 0, 3), FormFieldsInformation::$americanAreaCodes)
        ) {
            throw new \UserInputError('Phone Number contains an incorrect number or invalid area code');
        }
    }

    protected function modifyFields(array &$fields)
    {
        $fields['BirthdayDate'] = \DateTime::createFromFormat("m/d/Y", $fields["BirthdayDate"])->format("d/m/Y");
    }

    protected function sendSensorData(bool $isReloadCache = false)
    {
        $this->logger->notice(__METHOD__);

        $sensorData = \Cache::getInstance()->get('qmiles_sensor_data');
        $_abck = \Cache::getInstance()->get('qmiles_abck_cookie');

        if (!$sensorData || !$_abck || $isReloadCache) {
            $selenium = clone $this;
            $this->http->brotherBrowser($selenium->http);

            try {
                $this->logger->notice("Running Selenium...");

                $selenium->UseSelenium();
                $selenium->seleniumOptions->recordRequests = true;

                $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);

                if ($this->fingerprint) {
                    $selenium->seleniumOptions->userAgent = $this->fingerprint->getUseragent();
                    $selenium->http->setUserAgent($this->fingerprint->getUseragent());
                }

                $selenium->keepCookies(true);

                $selenium->http->saveScreenshots = true;
                $selenium->disableImages();

                $selenium->http->start();
                $selenium->Start();

                $selenium->driver->manage()->window()->maximize();

                $this->sensorData = [];
                $selenium->http->GetURL('https://www.qatarairways.com/en/Privilege-Club/join-now.html');
                $selenium->saveResponse();

                $this->sensorDataUrl =
                    $selenium->http->FindPreg("#<\/noscript><script type=\"text/javascript\" src=\"([^\"]+)\"><\/script>#")
                    ?? $selenium->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

                $this->logger->notice("sensor_data url - $this->sensorDataUrl");

                if (!$this->sensorDataUrl) {
                    $this->logger->error("sensor_data url not found");

                    return null;
                }

                $selenium->http->NormalizeURL($this->sensorDataUrl);

                $requests = $selenium->http->driver->browserCommunicator->getRecordedRequests();
                $i = array_search('_abck', array_column($selenium->getAllCookies(), 'name'));
                $this->_abck = $selenium->getAllCookies()[$i]['value'];
                \Cache::getInstance()->set('qmiles_abck_cookie', $this->_abck, 60 * 60 * 24);

                $this->logger->notice("key: {$this->_abck}");
                $this->DebugInfo = "key: {$this->_abck}";
                $this->http->setCookie("_abck", $this->_abck);

                foreach ($requests as $n => $xhr) {
                    if (strpos($xhr->request->getUri(), $this->sensorDataUrl) !== false) {
                        if (($xhr->response->getStatus() >= 200 && $xhr->response->getStatus() < 300)
                            && isset($xhr->request->getBody()['sensor_data'])
                        ) {
                            $this->sensorData[] = $xhr->request->getBody()['sensor_data'];

                            if (count($this->sensorData) == 2) {
                                break;
                            }
                        }
                    }
                }

                if ($this->sensorData) {
                    \Cache::getInstance()->set('qmiles_sensor_data', $this->sensorData, 60 * 60 * 24);
                }
            } catch (\ScriptTimeoutException $e) {
                $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
                $this->DebugInfo = "ScriptTimeoutException";
            } catch (\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverException $e) {
                $this->logger->error($e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'New session attempts retry count exceeded') === false) {
                    throw $e;
                }
                $this->logger->error("exception: " . $e->getMessage());
                $this->DebugInfo = "New session attempts retry count exceeded";

                throw new \CheckRetryNeededException(5, 0);
            } finally {
                $this->logger->notice("Closing Selenium...");
                $selenium->http->cleanup();
            }

            return;
        }

        $this->http->GetURL("https://www.qatarairways.com/en/Privilege-Club/join-now.html");
        $sensorDataUrl =
            $this->http->FindPreg("#</noscript><script type=\"text/javascript\" src=\"([^\"]+)\"><\/script>#")
            ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data url not found");

            return null;
        }

        $this->http->NormalizeURL($sensorDataUrl);
        $headers = [
            "Accept"       => "*/*",
            "Content-type" => "application/json",
        ];

        $this->http->setCookie("_abck", $_abck);

        foreach ($sensorData as $key => $singleSensorData) {
            $data = [
                'sensor_data' => $singleSensorData,
            ];
            $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
            $this->http->JsonLog();

            if (isset($sensorData[$key + 1])) {
                sleep(1);
            }
        }
    }

    private function sendRegisterRequest(array $fields)
    {
        $this->http->GetURL('https://www.qatarairways.com/en/Privilege-Club/join-now.html');

        if (!isset($this->http->Response) || $this->http->Response['code'] != 200) {
            $this->sendSensorData(true);

            throw new \EngineError('Bad sensor data - try again.');
        }

        $captcha = false;

        if ($key = $this->http->FindNodes("//div[contains(@class, 'g-recaptcha')]/@data-sitekeycaptcha")[0] ?? null) {
            $this->logger->notice($key);

            try {
                $captcha = $this->parseReCaptcha($key);
            } catch (\CheckRetryNeededException $e) {
                throw new \EngineError('We could not recognize captcha. Please try again later.');
            }
            $this->logger->notice($captcha);
        }

        if ($captcha === false) {
            throw new \EngineError('We could not recognize captcha. Please try again later.');
        }

        $this->http->SetInputValue("enrollmentVersionType", "A");
        $this->http->SetInputValue("socialMediaEmail", "");
        $this->http->SetInputValue("programCode", "QRPC");
        $this->http->SetInputValue("platform", "WEB");
        $this->http->SetInputValue("j_currentPage", "/content/global/en/Privilege-Club/join-now");
        $this->http->SetInputValue("socialMediaType", "");
        $this->http->SetInputValue("socialMediaId", "");
        $this->http->SetInputValue("socialMediaAccessToken", "");
        $this->http->SetInputValue("socialMediaAccessTokenSecret", "");
        $this->http->SetInputValue("customerProfileId", "");
        $this->http->SetInputValue("pcSuccessLink", "/content/global/en/Privilege-Club/join-now-success.html");
        $this->http->SetInputValue("pcSuccessTrackingCode", "");
        $this->http->SetInputValue("portalSuccessTrackingCode", "");
        $this->http->SetInputValue("portalSuccessLink",
            "/content/global/en/Privilege-Club/postLogin/basic-profile-dashboard.html");
        $this->http->SetInputValue("page_locale", "EN");
        $this->http->SetInputValue("membershipNumber", "");
        $this->http->SetInputValue("lastNameMember", "");
        $this->http->SetInputValue("FFP_CAPTCHA_FTL_ENROL", "GOOGLE");
        $this->http->SetInputValue("g-recaptcha-response", "");
        $this->http->SetInputValue("g-recaptcha-response", $captcha);
        $this->http->SetInputValue("emailAddress", $fields['Email']);
        $this->http->SetInputValue("typePassword", $fields['Password']);
        $this->http->SetInputValue("repassword", $fields['Password']);
        $this->http->SetInputValue("countryCallingCode", "1-US");
        $this->http->SetInputValue("phone", $fields['PhoneNumber']);
        $this->http->SetInputValue("title", $fields['Title']);
        $this->http->SetInputValue("firstName", $fields['FirstName']);
        $this->http->SetInputValue("lastName", $fields['LastName']);
//        $this->http->SetInputValue("middleName", $fields['MiddleName']);
        $this->http->SetInputValue("first_Name", "");
        $this->http->SetInputValue("middle_Name", "");
        $this->http->SetInputValue("last_Name", "");
        $this->http->SetInputValue("date", $fields['BirthdayDate']);
        $this->http->SetInputValue("date", $fields['BirthdayDate']);
        $this->http->SetInputValue("gender", $fields['Gender']);
        $this->http->SetInputValue("residenceCountry", "US");
        $this->http->SetInputValue("promoCode", "");
        $this->http->SetInputValue("isQRPCOffersEnabled", "false");
        $this->http->SetInputValue("privilegeClubNotifyTerms3", "on");
        $this->http->SetInputValue("privilegeClubTerms", "on");
        $this->http->SetInputValue("enableKRConsents", "true");
        $this->http->SetInputValue("FFP_CAPTCHA_ENROL", "GOOGLE");
        $this->http->SetInputValue("additionalInfo", $captcha);
        $this->http->SetInputValue("additionalInfo", $captcha);
        $this->http->SetInputValue("j_platform", "WEBDSKTOP");
        $this->http->FormURL = "https://www.qatarairways.com/qr/bot/enrollment/joinnow";

        $this->http->PostForm();

        if (!isset($this->http->Response) || $this->http->Response['code'] != 200) {
            $this->sendSensorData(true);

            throw new \EngineError('Bad sensor data - try again.');
        }
    }

    private function checkSendEmail(string $question)
    {
        $this->logger->notice(__METHOD__);

        if ($question) {
            $this->State['email'] = $this->fields['Email'];

            $this->AskQuestion($question, null, 'Question');

            $this->ErrorMessage = json_encode($this->failedTwoFaErrorMessage, JSON_PRETTY_PRINT);
        }
    }

    private function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
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

    private function useVerificationLink($link, $membershipNumber)
    {
        try {
            $this->http->GetURL($link);
            $this->http->SaveResponse();
        } catch (\WebDriverCurlException $e) {
            $this->logger->error("Site timed out, please try again later");

            throw new \EngineError("Site timed out, please try again later");
        }

        $success = $this->http->FindSingleNode("//span[contains(text(), 'Your email address has been verified successfully. Please login.')]");

        if ($success) {
            $this->ErrorMessage = json_encode([
                "status"       => "success",
                "message"      => "Registration is successful! Membership number: {$membershipNumber}",
                "active"       => true,
                "login"        => $membershipNumber,
                "password"     => $this->fields['Password'],
                "email"        => $this->fields['Email'],
                "registerInfo" => $this->registerInfo,
            ], JSON_PRETTY_PRINT);
        } else {
            $this->failedTwoFaErrorMessage['login'] = $membershipNumber;
            $this->ErrorMessage = json_encode($this->failedTwoFaErrorMessage, JSON_PRETTY_PRINT);
        }

        return true;
    }

    private function validateDate($date, $format = 'm/d/Y')
    {
        $d = \DateTime::createFromFormat($format, $date);

        return $d && $d->format($format) == $date;
    }
}
