<?php

namespace AwardWallet\Engine\airfrance\RewardAvailability;

use AwardWallet\Engine\airfrance\QuestionAnalyzer;
use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;

    private $fields;
    private $mover;
    
    const MIN_PASSWORD_LENGTH = 12;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();

        $array = ['us', 'fr', 'fi'];
        $targeting = $array[array_rand($array)];
        $this->setProxyGoProxies(null, $targeting);

        $this->useChromePuppeteer(\SeleniumFinderRequest::CHROME_PUPPETEER_103);
        $this->seleniumOptions->userAgent = null;

        $this->usePacFile(false);
        $this->http->saveScreenshots = true;

        $this->setProxyGoProxies();

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
            'Title' => [
                'Type'     => 'string',
                'Caption'  => 'Name Prefix',
                'Required' => true,
                'Options'  => ['Mr' => 'Mr.', 'Mrs' => 'Mrs.'],
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'State',
                'Required' => true,
                'Options'  => ['US' => 'United States', 'CA' => 'Canada', 'MX' => 'Mexico'],
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password',
                'Required' => true,
                'Note'     => "Must be at least 12 characters in length.Must include at least one letter and one number. Standard special characters (such as '!' '&' and '+') are optional. Dont use '¡','¿','¨']",
            ],
        ];
    }

    public function registerAccount(array $fields)
    {
        $this->logger->debug(var_export($fields, true), ['pre' => true]);
        $this->checkFields($fields);
        $this->fields = $fields;
        $date = new \DateTime($fields['BirthdayDate'], new \DateTimeZone('UTC'));

        $this->http->GetURL("https://wwws.airfrance.us/trip");
        $this->acceptCookies();

        $registerButton = $this->waitForElement(\WebDriverBy::xpath('//button[@class="bwc-multi-list-large-list__link ng-star-inserted"]//span[contains(., "Create")]'), 20);

        if (!$registerButton) {
            $this->ErrorMessage ='Page not loaded, Try again';

            return false;
        } else {
            $registerButton->click();
        }

        $this->mover = new \MouseMover($this->driver);
        $this->mover->logger = $this->logger;
        $this->mover->duration = random_int(40, 60) * 100;
        $this->mover->steps = 2;
        $this->mover->setCoords(0, 500);

        $createButton = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@class, 'mat-accent') and contains(@class, 'mat-mdc-button-base')]"), 10);

        $this->moverElementClick($createButton);

        $firstNameInput = $this->waitForElement(\WebDriverBy::xpath("//input[@name='jfirstName']"), 5);
        $lastNameInput = $this->waitForElement(\WebDriverBy::xpath("//input[@name='jlastName']"), 0);
        $continueButton = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@class, 'mat-accent') and contains(@class, 'mat-mdc-button-base')]"), 0);

        if (!$firstNameInput || !$lastNameInput) {
            $this->ErrorMessage ='Page not loaded, Try again';

            return false;
        }

        $this->moverInput($firstNameInput, $fields["FirstName"]);
        $this->moverInput($lastNameInput, $fields["LastName"]);
        $this->saveResponse();

        $this->moverElementClick($continueButton);


        $countryInput = $this->waitForElement(\WebDriverBy::xpath("//input[@name='jcountryOfResidence']"), 5);
        $monthSelect = $this->waitForElement(\WebDriverBy::xpath("//mat-select[@id='mat-select-2']"), 0);
        $dayInput = $this->waitForElement(\WebDriverBy::xpath("//input[@id='mat-input-4']"), 0);
        $yearInput = $this->waitForElement(\WebDriverBy::xpath("//input[@id='mat-input-5']"), 0);
        $titleSelect = $this->waitForElement(\WebDriverBy::xpath("//mat-select[@name='jgender']"), 0);
        $continueButton = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@class, 'mat-accent') and contains(@class, 'mat-mdc-button-base')]"), 0);

        if (!$countryInput || !$monthSelect || !$dayInput || !$yearInput || !$titleSelect || !$continueButton) {
            $this->ErrorMessage ='Page not loaded, Try again';

            return false;
        }

        $countryInput->click();
        $countryInput->clear();
        $countryInput->sendKeys($this->getFullNameCountry($fields["Country"]));

        $this->moverElementClick($monthSelect);

        $monthOption = $this->waitForElement(\WebDriverBy::xpath("//mat-option[@id='mat-option-{$this->getIntDataFormat($date, 'm')}']"));
        $monthOption->click();


        $this->moverInput($dayInput, $this->getIntDataFormat($date, 'd'));
        $this->moverInput($yearInput, $this->getIntDataFormat($date, 'Y'));


        $titleSelect->click();
        $titleOption = $this->waitForElement(\WebDriverBy::xpath("//mat-option/span[contains(., '" . ($fields['Title'] == 'Mrs' ? 'Ms' : 'Mr') . "')]"));
        $titleOption->click();

        $this->saveResponse();

        $this->moverElementClick($continueButton);

        $emailInput = $this->waitForElement(\WebDriverBy::xpath("//input[@name='jemailAddress']"), 20);
        $phoneInput = $this->waitForElement(\WebDriverBy::xpath("//input[@name='jtelephoneNumber']"), 0);
        $continueButton = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@class, 'mat-accent') and contains(@class, 'mat-mdc-button-base')]"), 0);

        if (!$emailInput || !$phoneInput || !$continueButton) {
            $this->ErrorMessage ='Page not loaded, Try again';

            return false;
        }

        $this->moverInput($emailInput, $fields["Email"]);
        $this->moverInput($phoneInput, $fields["PhoneNumber"]);
        $this->saveResponse();

        $this->moverElementClick($continueButton);

        if ($error = $this->waitForElement(\WebDriverBy::xpath('//span[@class = "ng-star-inserted"]'), 5)) {
            $this->logger->error($error->getText());

            return false;
        }

        if (!$this->parseQuestion()) {
            return false;
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        $question = $this->waitForElement(\WebDriverBy::xpath("//div[contains(@class, 'bwc-typo-body-m-regular enrol-desc')]"), 10);

        if (!$question) {
            $this->logger->error('Question not parsed. Try again');
            return true;
        }

        $this->saveResponse();
        $question = $question->getText();


        if (QuestionAnalyzer::isOtcQuestion($question)) {
            $this->logger->info("Two Factor Authentication Login", ['Header' => 3]);

            $this->holdSession();
            $this->question = $question;

            $this->AskQuestion($this->question, null, 'Question');

            return false;
        }

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $oneTimeCodeInput = $this->waitForElement(\WebDriverBy::xpath('//input[@autocomplete="one-time-code"][1]'), 0);
        $contBtn = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@class, 'mat-mdc-raised-button') and contains(., 'Continue')]"), 0);

        if (!$oneTimeCodeInput) {
            $this->ErrorMessage ='Page not loaded, Try again';

            $this->saveResponse();

            return false;
        }

        $oneTimeCodeInput->click();
        $oneTimeCodeInput->sendKeys($answer);
        $this->saveResponse();

        $this->moverElementClick($contBtn);
        $this->saveResponse();

        $this->waitForElement(\WebDriverBy::xpath("//mat-checkbox[@id='flyingblueCheckBox']"), 10);
        $contBtn = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@class, 'mat-mdc-raised-button') and contains(., 'Continue')]"), 0);

        $this->moverElementClick($contBtn);

        $this->saveResponse();
        $passInput = $this->waitForElement(\WebDriverBy::xpath("//input[@name='jenrolpassword']"), 20);
        $contBtn = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@class, 'mat-mdc-raised-button') and contains(., 'Continue')]"), 0);

        if (!$passInput || !$contBtn) {
            $this->ErrorMessage ='Page not loaded, Try again';

            $this->saveResponse();

            return false;
        }

        $this->moverInput($passInput, $this->fields['Password']);
        $this->moverElementClick($contBtn);

        $inputCheckBox = $this->waitForElement(\WebDriverBy::xpath("//input[@id='mat-mdc-checkbox-3-input']"), 10);
        $contBtn = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@class, 'mat-mdc-raised-button') and contains(., 'Create')]"), 0);

        if (!$inputCheckBox) {
            $this->ErrorMessage ='Page not loaded, Try again';

            $this->saveResponse();

            return false;
        }

        $this->moverElementClick($inputCheckBox);
        $this->moverElementClick($contBtn);


        $this->waitForElement(\WebDriverBy::xpath('//span[@class="enrol-enrol-number"]'), 20);

        $this->saveResponse();

        $flightBlueNumber = $this->http->FindSingleNode('//span[@class="enrol-enrol-number"]');
        if (!$flightBlueNumber) {
            if ($this->waitForElement(\WebDriverBy::xpath("//input[@name='jtelephoneNumber']"), 0)) {
                $this->ErrorMessage = 'This phone number does not correspond to the country. Please check the number and try again.';

                $this->saveResponse();

                return false;
            }

            $this->saveResponse();

            return false;
        }

        $contBtn = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@class, 'mat-mdc-raised-button') and contains(., 'Continue')]"), 10);
        $contBtn->click();

        $this->ErrorMessage = json_encode([
            "status"       => "success",
            "message"      => "Registration is successful! Membership number: {$flightBlueNumber}",
            "login"        => $flightBlueNumber,
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
                    "key"   => "Title",
                    "value" => $this->fields["Title"],
                ],
                [
                    "key"   => "BirthdayDate",
                    "value" => $this->fields["BirthdayDate"],
                ],
                [
                    "key"   => "Country",
                    "value" => $this->fields["Country"],
                ],
                [
                    "key"   => "PhoneNumber",
                    "value" => $this->fields["PhoneNumber"],
                ],
            ],
            "active" => true,
        ], JSON_PRETTY_PRINT);

        return true;
    }

    public function acceptCookies()
    {
        $this->logger->notice(__METHOD__);

        try {
            $btn = $this->waitForElement(\WebDriverBy::xpath('//button[@id = "accept_cookies_btn"]'), 5);
            $this->saveResponse();

            if (!$btn) {
                return;
            }
            $btn->click();
            sleep(3);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
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

        if ((strlen($fields['Password']) < 12 || strlen($fields['Password']) > 30)
            || !preg_match("/[a-z]/", $fields['Password'])
            || !preg_match("/[0-9]/", $fields['Password']) !== false
            || preg_match("/[%¡¿¨]/", $fields['Password'])) {
            throw new \UserInputError("Must be at least 12 characters in length.Must include at least one letter and one number. Standard special characters (such as '!' '&' and '+') are optional. Dont use '¡','¿','¨']");
        }

        if (strlen($fields['PhoneNumber']) > 10 || preg_match("/[a-zA-Z*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/",
                $fields['PhoneNumber'])) {
            throw new \UserInputError('Phone Number contains an incorrect symbol');
        }

        if (!preg_match("/^(0[1-9]|1[012])\/(0[1-9]|[12][0-9]|3[01])\/(19[3-9][0-9]|20[01][0-9])$/",
            $fields['BirthdayDate'])) {
            throw new \UserInputError('BirthdayDate contains an incorrect number');
        }
    }

    private function getFullNameCountry($country): string
    {
        switch ($country) {
            case 'US':
                return 'United States';

            case 'MX':
                return 'Mexico';

            case 'CA':
                return 'Canada';
        }

        return 'United States';
    }

    private function getIntDataFormat($date, $format): int
    {
        return (int) $date->format($format);
    }

    private function moverInput($element, $text)
    {
        $this->mover->moveToElement($element);
        $this->mover->click();
        $this->mover->sendKeys($element, $text);
    }

    private function moverElementClick($element)
    {
        $this->mover->moveToElement($element);
        $this->mover->click();
    }
}
