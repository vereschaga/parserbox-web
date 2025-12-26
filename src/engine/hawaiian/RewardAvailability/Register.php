<?php

namespace AwardWallet\Engine\hawaiian\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\hawaiian\RewardAvailability\Helpers\FormFieldsInformation;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \AwardWallet\Engine\ProxyList;

    private $timeout = 20;
    private $fields;
    private $number;
    private $registerInfo = [];

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->UseSelenium();
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
        $this->disableImages();
        $this->http->saveScreenshots = true;

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

        $request = FingerprintRequest::firefox();
        $request->browserVersionMin = 100;
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if (isset($fingerprint)) {
            $this->http->setUserAgent($fingerprint->getUseragent());
            $this->seleniumOptions->userAgent = $fingerprint->getUseragent();
            $this->seleniumOptions->fingerprintOptions = $fingerprint->getFingerprint();
        } else {
            $this->http->setRandomUserAgent(null, true, false, false, true, false);
        }
    }

    public function registerAccount(array $fields)
    {
        $this->logger->notice(__METHOD__);
        $this->fields = $fields;
        $this->logger->debug(var_export($fields, true), ['pre' => true]);

        $this->modifyFields($this->fields);

        $this->checkFields($this->fields);

        $this->register();

        $this->addSecurityQuestions();

        return $this->checkLogIn();
    }

    public function getRegisterFields()
    {
        return FormFieldsInformation::getRegisterFields();
    }

    protected function checkFields(&$fields)
    {
        if (preg_match("/[*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['Username']) || strpos($fields['Username'], ' ') !== false
            || strlen($fields['Username']) < 6) {
            throw new \UserInputError('Username contains an incorrect symbol or length is less than 6 characters');
        }

        if (!preg_match("/[A-Z0-9a-z._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/", $fields['Email'])) {
            throw new \UserInputError('Email address contains an incorrect format');
        }

        if (strlen($fields['Password']) < 10 || strlen($fields['Password']) > 16 || !preg_match("/[A-Z]/", $fields['Password'])
            || !preg_match("/[a-z]/", $fields['Password']) || !preg_match("/[0-9]/", $fields['Password']) || strpos($fields['Password'], ' ') !== false
        ) {
            throw new \UserInputError("Your password must be 10-16 characters and include at least 1 lowercase letter, 1 uppercase letter and 1 number");
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['FirstName'])) {
            throw new \UserInputError('FirstName contains an incorrect symbol');
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['LastName'])) {
            throw new \UserInputError('LastName contains an incorrect symbol');
        }

        if (!preg_match("/^([1-9]|[12][0-9]|3[01])$/", $fields['BirthDay'])) {
            throw new \UserInputError('BirthDay contains an incorrect number');
        }

        if (strlen($fields['Address']) > 29 || preg_match("/[*¡!?¿<>ºª|\·@#$%&;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['Address'])) {
            throw new \UserInputError('Address (Must 0-29 characters or numbers long (include . , / \ and space) )');
        }

        if (!(strlen($fields['PhoneNumber']) == 10)) {
            throw new \UserInputError('Phone Number must be 10 numbers length');
        }
    }

    protected function register()
    {
        $this->getAction('register');

        $submit = $this->waitForElement(\WebDriverBy::xpath("//button[@id='create_account']"), $this->timeout);

        if (!$submit) {
            throw new \EngineError("Couldn't find submit button");
        }

        $this->searchAndFillRegistrationFields($this->fields);

        $submit->click();

        $success = $this->waitForElement(\WebDriverBy::xpath("//span[@id='member_number']"), $this->timeout);
        $this->saveResponse();

        if ($success) {
            $this->number = str_replace(' ', '', trim($success->getText()));
            $this->getAction('logout');
        }
    }

    protected function addSecurityQuestions()
    {
        $this->getAction('login');

        $submitLogin = $this->waitForElement(\WebDriverBy::xpath("//button[@id='submit_login_button']"), $this->timeout);

        if (!$submitLogin) {
            throw new \EngineError("Couldn't find submit Login button");
        }

        $this->searchAndFillLoginFields($this->fields);
        $submitLogin->click();

        $error = $this->waitForElement(\WebDriverBy::xpath("//div[contains(@class,'alert-content')]/div[contains(text(),'mail and password could not be found')]"), 10);

        if ($error && $this->number) {
            $this->ErrorMessage = json_encode([
                "status"       => "success",
                "message"      => "Registration is successful, please login for add security questions!",
                "login"        => $this->number,
                "active"       => false,
                "registerInfo" => $this->registerInfo,
            ], JSON_PRETTY_PRINT);

            return;
        }

        $submitQuestion = $this->waitForElement(\WebDriverBy::xpath("//button[@id='confirm_and_sign_in']"), 20);
        $this->saveResponse();

        if (!$submitQuestion) {
            throw new \EngineError("Couldn't find submit Question button");
        }

        $this->searchAndFillQuestionFields($this->fields);
        $submitQuestion->click();
    }

    protected function checkLogIn()
    {
        $success = $this->waitForElement(\WebDriverBy::xpath("//span[@id='member_number']"), $this->timeout);
        $this->saveResponse();

        $number = ($success) ? str_replace(' ', '', trim($success->getText())) : $this->number;

        if (!is_null($number)) {
            $this->ErrorMessage = json_encode([
                "status"    => "success",
                "message"   => "Registration is successful! Member number: {$number}",
                "login"     => $number,
                "questions" => [
                    [
                        "question" => "To what city did you go the first time you flew on a plane?",
                        "answer"   => $this->fields['Answer'],
                    ],
                    [
                        "question" => "In what city or town did your parents meet?",
                        "answer"   => $this->fields['Answer'],
                    ],
                    [
                        "question" => "What is your oldest sibling's middle name?",
                        "answer"   => $this->fields['Answer'],
                    ],
                ],
                "registerInfo" => $this->registerInfo,
            ], JSON_PRETTY_PRINT);

            return true;
        }

        if ($this->ErrorMessage !== "Unknown error") {
            return true;
        }

        return false;
    }

    protected function searchAndFillRegistrationFields(array $fields)
    {
        try {
            $username = $this->waitForElement(\WebDriverBy::xpath("//input[@id='sign_up_username']"), 0);
            $email = $this->waitForElement(\WebDriverBy::xpath("//input[@id='sign_up_email_address']"), 0);
            $password = $this->waitForElement(\WebDriverBy::xpath("//input[@id='password']"), 0);
            $confirmPassword = $this->waitForElement(\WebDriverBy::xpath("//input[@id='confirm_password']"), 0);

            $this->checkFieldExist([
                'username'        => $username,
                'email'           => $email,
                'password'        => $password,
                'confirmPassword' => $confirmPassword,
            ]);

            $username->sendKeys($fields['Username']);
            $password->sendKeys($fields['Password']);
            $email->sendKeys($fields['Email']);
            $confirmPassword->sendKeys($fields['Password']);

            $already = $this->waitForElement(\WebDriverBy::xpath("//p[contains(text(),'email address is already linked')]"), 5);

            if ($already) {
                $this->logger->error($already->getText());

                throw new \UserInputError("Error text: {$already->getText()}");
            }

            $this->saveResponse();

            $firstName = $this->waitForElement(\WebDriverBy::xpath("//input[@id='sign_up_first_name']"), 0);
            $lastName = $this->waitForElement(\WebDriverBy::xpath("//input[@id='sign_up_last_name']"), 0);
            $genderValue = $fields['Gender'] == 'male' ? 'M' : 'F';
            $gender = $this->waitForElement(\WebDriverBy::xpath("//select[@id='MemberPersonalInfo_Gender']/option[@value='{$genderValue}']"), 0);
            $dobMonth = $this->waitForElement(\WebDriverBy::xpath("//select[@id='sign_up_dob_month']/option[@value='string:{$fields['BirthMonth']}']"), 0);
            $dobDay = $this->waitForElement(\WebDriverBy::xpath("//select[@id='sign_up_dob_day']/option[@value='string:{$fields['BirthDay']}']"), 0);
            $dobYear = $this->waitForElement(\WebDriverBy::xpath("//select[@id='sign_up_dob_year']/option[@value='string:{$fields['BirthYear']}']"), 0);

            $this->checkFieldExist([
                'firstName' => $firstName,
                'lastName'  => $lastName,
                'gender'    => $gender,
                'dobMonth'  => $dobMonth,
                'dobDay'    => $dobDay,
                'dobYear'   => $dobYear,
            ]);

            $firstName->sendKeys($fields['FirstName']);
            $lastName->sendKeys($fields['LastName']);
            $gender->click();
            $dobMonth->click();
            $dobDay->click();
            $dobYear->click();

            $this->saveResponse();

            $this->registerInfo = array_merge($this->registerInfo, [
                [
                    'key'  => 'FirstName',
                    'value'=> $this->driver->executeScript("return document.querySelector('#sign_up_first_name').value;"),
                ],
                [
                    'key'  => 'LastName',
                    'value'=> $this->driver->executeScript("return document.querySelector('#sign_up_last_name').value;"),
                ],
                [
                    'key'  => 'Gender',
                    'value'=> $this->driver->executeScript("return document.querySelector('#MemberPersonalInfo_Gender').value;"),
                ],
                [
                    'key'   => 'BirthdayDate',
                    'value' =>
                        $this->driver->executeScript("
                            return document.querySelector('select[id=\'sign_up_dob_day\'] option[value=\''+document.querySelector('select[id=\'sign_up_dob_day\']').value+'\']').innerHTML;
                      ")
                        . ' ' . $this->driver->executeScript("
                            return document.querySelector('select[id=\'sign_up_dob_month\'] option[value=\''+document.querySelector('select[id=\'sign_up_dob_month\']').value+'\']').innerHTML;
                      ")
                        . ' ' . $this->driver->executeScript("
                            return document.querySelector('select[id=\'sign_up_dob_year\'] option[value=\''+document.querySelector('select[id=\'sign_up_dob_year\']').value+'\']').innerHTML;
                      "),
                ],
            ]);

            if ($next = $this->waitForElement(\WebDriverBy::xpath("//a[contains(@ng-click,'joinNow.showPage(\$event, [2], 2)') and contains(.,'Next')]"), 0)) {
                $next->click();
            }

            $country = $this->waitForElement(\WebDriverBy::xpath("//select[@id='MemberAddress.CountryData']/option[@value='object:169']"), 5);
            $zipCode = $this->waitForElement(\WebDriverBy::xpath("//input[@id='zip_code']"), $this->timeout);
            $address = $this->waitForElement(\WebDriverBy::xpath("//input[@id='address']"), 0);
            $phoneType = $this->waitForElement(\WebDriverBy::xpath("//select[@id='phoneType0']/option[@value='string:3']"), 0);
            $countryCode = $this->waitForElement(\WebDriverBy::xpath("//select[@id='PhoneDetails0']/option[@value='string:USA']"), 0);
            $phoneNumber = $this->waitForElement(\WebDriverBy::xpath("//input[@id='phone_number']"), 0);

            $this->checkFieldExist([
                'country'     => $country,
                'zipCode'     => $zipCode,
                'address'     => $address,
                'phoneType'   => $phoneType,
                'countryCode' => $countryCode,
                'phoneNumber' => $phoneNumber,
            ]);

            $country->click();
            $zipCode->sendKeys($fields['ZipCode']);
            sleep(5);  //Need for auto upload data in fields city and state
            $address->sendKeys($fields['Address']);
            $phoneType->click();

            if ($city = $this->waitForElement(\WebDriverBy::xpath("//select[@id='MemberAddress.CityData']/option[2]"))) {
                $city->click();
            }
            $countryCode->click();
            $phoneNumber->sendKeys($fields['PhoneNumber']);

            $this->saveResponse();

            $this->registerInfo = array_merge($this->registerInfo, [
                [
                    'key'   => 'Country',
                    'value' => $this->driver->executeScript("
                        return document.querySelector('select[id=\'MemberAddress.CountryData\'] option[value=\''+document.querySelector('select[id=\'MemberAddress.CountryData\']').value+'\']').innerHTML;
                    "),
                ],
                [
                    'key'   => 'ZipCode',
                    'value' => $this->driver->executeScript("return document.querySelector('#zip_code').value;"),
                ],
                [
                    'key'   => 'Address',
                    'value' => $this->driver->executeScript("
                        return document.querySelector('#address').value +' ' + document.querySelector('#address_two').value;
                    "),
                ],
                [
                    'key'   => 'PhoneType',
                    'value' => $this->driver->executeScript("
                        return document.querySelector('select[id=\'phoneType0\'] option[value=\''+document.querySelector('select[id=\'phoneType0\']').value+'\']').innerHTML;
                    "),
                ],
                [
                    'key'   => 'PhoneCountryCode',
                    'value' => $this->driver->executeScript("
                        return document.querySelector('select[id=\'PhoneDetails0\'] option[value=\''+document.querySelector('select[id=\'PhoneDetails0\']').value+'\']').innerHTML;
                    "),
                ],
                [
                    'key'   => 'PhoneNumber',
                    'value' => $this->driver->executeScript("return document.querySelector('#phone_number').value;"),
                ],
                [
                    'key'   => 'City',
                    'value' => $this->driver->executeScript("
                        return document.querySelector('select[id=\'MemberAddress.CityData\'] option[value=\''+document.querySelector('select[id=\'MemberAddress.CityData\']').value+'\']').innerHTML;
                    "),
                ],
                [
                    'key'   => 'State',
                    'value' => $this->driver->executeScript("
                        return document.querySelector('select[id=\'MemberAddress.StateData\'] option[value=\''+document.querySelector('select[id=\'MemberAddress.StateData\']').value+'\']').innerHTML;
                    "),
                ],
            ]);

            $error = $this->waitForElement(\WebDriverBy::xpath("(//label[contains(@class,'ha-label')]//em)[1]"), 0);

            if ($error) {
                $this->logger->error($error->getText());

                throw new \UserInputError("Error text: {$error->getText()}");
            }
        } catch (\WebDriverCurlException $e) {
            $this->logger->error("Error: {$e->getMessage()}");

            throw new \EngineError("Error: {$e->getMessage()}");
        }
        $this->logger->debug(var_export($this->registerInfo, true), ['pre' => true]);
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
    }

    protected function searchAndFillLoginFields(array $fields)
    {
        try {
            $username = $this->waitForElement(\WebDriverBy::xpath("//input[@id='user_name']"), 0);
            $password = $this->waitForElement(\WebDriverBy::xpath("//input[@id='password']"), 0);

            $this->checkFieldExist([
                'username' => $username,
                'password' => $password,
            ]);

            $username->sendKeys($fields['Email']);
            $password->sendKeys($fields['Password']);

            $this->saveResponse();
        } catch (\WebDriverCurlException $e) {
            $this->logger->error("Error: {$e->getMessage()}");

            throw new \EngineError("Error: {$e->getMessage()}");
        }
    }

    protected function searchAndFillQuestionFields(array $fields)
    {
        try {
            $question1 = $this->waitForElement(\WebDriverBy::xpath("//select[@id='security_question_one']/option[@value='1']"), 0);
            $answer1 = $this->waitForElement(\WebDriverBy::xpath("//input[@id='security_answers_one']"), 0);
            $question2 = $this->waitForElement(\WebDriverBy::xpath("//select[@id='security_question_two']/option[@value='8']"), 0);
            $answer2 = $this->waitForElement(\WebDriverBy::xpath("//input[@id='security_answers_two']"), 0);
            $question3 = $this->waitForElement(\WebDriverBy::xpath("//select[@id='security_question_three']/option[@value='16']"), 0);
            $answer3 = $this->waitForElement(\WebDriverBy::xpath("//input[@id='security_answers_three']"), 0);
            $trueUpTerms = $this->waitForElement(\WebDriverBy::xpath("//label[@for='trueUpTerms']"), 0);

            $this->checkFieldExist([
                'question1'   => $question1,
                'answer1'     => $answer1,
                'question2'   => $question2,
                'answer2'     => $answer2,
                'question3'   => $question3,
                'answer3'     => $answer3,
                'trueUpTerms' => $trueUpTerms,
            ]);

            $question1->click();
            $answer1->sendKeys($fields['Answer']);
            $question2->click();
            $answer2->sendKeys($fields['Answer']);
            $question3->click();
            $answer3->sendKeys($fields['Answer']);
            $trueUpTerms->click();

            $this->saveResponse();
        } catch (\WebDriverCurlException $e) {
            $this->logger->error("Error: {$e->getMessage()}");

            throw new \EngineError("Error: {$e->getMessage()}");
        }
    }

    protected function modifyFields(array &$fields)
    {
        foreach ($fields as $key => $value) {
            if ($key === 'BirthdayDate') {
                $fields['BirthDay'] = \DateTime::createFromFormat("m/d/Y", $this->fields["BirthdayDate"])->format("j");
                $fields['BirthMonth'] = \DateTime::createFromFormat("m/d/Y", $this->fields["BirthdayDate"])->format("n");
                $fields['BirthYear'] = \DateTime::createFromFormat("m/d/Y", $this->fields["BirthdayDate"])->format("Y");
            }

            if (in_array($key, ['PhoneNumber', 'LastName', 'FirstName', 'Email', 'Username'])) {
                $value = ltrim(rtrim($value));
            }
        }
    }

    protected function getAction($type = 'register')
    {
        $url = '';

        switch ($type) {
            case 'logout':
                $url = "https://www.hawaiianairlines.com/MyAccount/Login/SignOut?areaName=MyAccount";

                break;

            case 'login':
                $url = "https://www.hawaiianairlines.com/my-account/login/";

                break;

            case 'register':
                $url = "https://www.hawaiianairlines.com/my-account/join-hawaiianmiles/";

                break;
        }

        try {
            $this->http->GetURL($url);
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
