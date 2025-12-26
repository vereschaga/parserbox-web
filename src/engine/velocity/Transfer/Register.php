<?php

namespace AwardWallet\Engine\velocity\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    public static $titles = [
        'MR'   => 'Mr',
        'MSTR' => 'Mstr',
        'MRS'  => 'Mrs',
        'MS'   => 'Ms',
        'MISS' => 'Miss',
        'DR'   => 'Dr',
    ];
    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];
    public static $countries = [
        'AU' => 'Australia',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos Islands',
        'CK' => 'Cook Islands',
        'FJ' => 'Fiji',
        'NZ' => 'New Zealand',
        'PG' => 'Papua New Guinea',
        'WS' => 'Samoa',
        'SB' => 'Solomon Islands',
        'TO' => 'Tonga',
        'VU' => 'Vanuatu',
    ];
    public static $australianStates = [
        'ACT' => 'ACT',
        'NSW' => 'NSW',
        'NT'  => 'NT',
        'VIC' => 'VIC',
        'QLD' => 'QLD',
        'SA'  => 'SA',
        'TAS' => 'TAS',
        'WA'  => 'WA',
    ];
    public static $phoneTypes = [
        'H' => 'Home',
        'M' => 'Mobile',
    ];
    public static $securityQuestionTypes = [
        'The name of your first school'         => 'The name of your first school',
        'Your mother\'s maiden name'            => 'Your mother\'s maiden name',
        'Your first car'                        => 'Your first car',
        'Your grandmother\'s first name'        => 'Your grandmother\'s first name',
        'Your favourite sporting team'          => 'Your favourite sporting team',
        'The country of your dream holiday'     => 'The country of your dream holiday',
        'Name of the first street you lived on' => 'Name of the first street you lived on',
        'Your first pet\'s name'                => 'Your first pet\'s name',
    ];
    public $techErrorRegExp = '#We\s+are\s+sorry,\s+but\s+we\s+are\s+unable\s+to\s+complete\s+your\s+request\s+at\s+this\s+time,\s+please\s+try\s+again\s+later#';
    protected $waitTimeout = 2;
    protected $loadTimeout = 20;
    protected $fields;

    public function InitBrowser()
    {
        $this->UseCurlBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            $proxy = $this->proxyDOP();
        } else {
            $proxy = 'localhost:8000';
        }
        $this->http->SetProxy($proxy);
        $this->InitSeleniumBrowser($this->http->GetProxy());
    }

    // ------------------------------

    public function registerAccount(array $fields)
    {
        $this->fields = $fields;
        $this->preliminaryFieldsCheck();
        $this->driver->getCommandExecutor()->setRequestTimeout(60000);
        $this->http->GetURL('https://www.velocityfrequentflyer.com/content/Joinfree/');
        $this->http->GetURL('https://www.velocityfrequentflyer.com/content/Joinfree/');

        $form = $this->waitForElement(\WebDriverBy::id('signupForm'), $this->loadTimeout);

        if (empty($form)) {
            $this->fail('form not loaded');
        }

        $inputs = [
            'title'                   => 'Title',
            'firstName'               => 'FirstName',
            'lastName'                => 'LastName',
            'homeAddress.countryCode' => 'Country',
            'homeAddress.line1'       => 'AddressLine1',
            'homeAddress.city'        => 'City',
            'dobAdded'                => 'FullDOB',
            'email'                   => 'Email',
            'password'                => 'Password',
            'passwordConfirm'         => 'Password',
            'challengeQuestion'       => 'SecurityQuestionType1',
            'challengeAnswer'         => 'SecurityQuestionAnswer1',
        ];

        if ($fields['AddressType'] === 'B') {
            $this->waitForElement(\WebDriverBy::id('postalAddressBusinessAdded'), $this->waitTimeout)->click();
            sleep(1);
            $this->http->Log('address type set as business');
            $inputs['company'] = 'Company';
            $inputs['occupation'] = 'JobTitle';
        }

        $this->setValue('homeAddress.countryCode', $fields['Country']);

        if ($this->hasDetailedAddress()) {
            $this->waitForElement(\WebDriverBy::xpath('//div[@class="autoOrManDiv"]/p[contains(., "Manual Entry")]'), $this->waitTimeout)->click();
            sleep(1);
            $this->http->Log('opened detailed address');
            $inputs['homeAddress.postcode'] = 'PostalCode';
        }

        if ($this->hasState()) {
            $inputs['homeAddress.state'] = 'StateOrProvince';
        }

        $phoneId = $fields['PhoneType'] === 'M' ? 'mobilePhone' : 'homePhone';

        if ($phoneId === 'homePhone') {
            $this->waitForElement(\WebDriverBy::xpath('//div[@id="mobilePhoneInfo"]//span[@class="link" and contains(text(), "Add an alternate number")]'), $this->waitTimeout)->click();
            sleep(1);
            $this->http->Log('opened input for alternative phone');
        }
        $inputs[$phoneId] = 'FullPhone';
        $fields['FullPhone'] = $fields['PhoneCountryCodeNumeric'] . $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];
        $fields['BirthDay'] = sprintf("%'.02d", intval($fields['BirthDay']));
        $fields['BirthMonth'] = sprintf("%'.02d", intval($fields['BirthMonth']));
        $fields['FullDOB'] = sprintf('%\'.02d%\'.02d%d', intval($fields['BirthDay']), intval($fields['BirthMonth']), intval($fields['BirthYear']));

        foreach ($inputs as $id => $key) {
            if (!empty($fields[$key])) {
                $this->setValue($id, $fields[$key]);
            }
        }

        if ($fields['Title'] === 'DR') {
            $this->waitForElement(\WebDriverBy::xpath(sprintf('//input[@name="gender" and @value="%s"]', $fields['Gender'])), $this->waitTimeout)->click();
        }

        if ($check = $this->waitForElement(\WebDriverBy::id('termsAndConditionsRead'), $this->waitTimeout)) {
            $check->click();
        }

        $iframe = $this->waitForElement(\WebDriverBy::xpath('//div[@id="recaptchaRow"]//iframe'), $this->waitTimeout, false);

        if (!$iframe) {
            $this->fail('no captcha iframe');
        }
        $this->driver->switchTo()->frame($iframe);
        $recaptchaAnchor = $this->waitForElement(\WebDriverBy::id("recaptcha-anchor"), $this->loadTimeout);

        if (!$recaptchaAnchor) {
            $this->fail('Failed to find reCaptcha "I am not a robot" button');
        }
        $recaptchaAnchor->click();
        $this->http->Log('wait captcha iframe');
        $this->driver->switchTo()->defaultContent();
        $iframe2 = $this->waitForElement(\WebDriverBy::xpath('//iframe[@title = "recaptcha challenge"]'), $this->loadTimeout, true);

        if (!$iframe2) {
            $this->fail('no iframe with images');
        }
        $this->saveResponse();
        $status = '';

        if (empty($status)) {
            throw new \CheckException(self::CAPTCHA_ERROR_MSG);
        }

        if ($submit = $this->waitForElement(\WebDriverBy::xpath('//a[contains(@class, "form-submit-a")]'), $this->waitTimeout)) {
            $submit->click();
        } else {
            $this->fail('submit not found');
        }
        $this->waitForElement(\WebDriverBy::xpath('//a[contains(@class, "btn-logout")]'), $this->loadTimeout);
        $this->saveResponse();
        $success = $this->http->FindSingleNode('//text()[contains(., "Your Membership Number is:")]');

        if ($success) {
            $this->ErrorMessage = $success;
            $this->http->Log($success);

            return true;
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Title' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Title',
                    'Required' => true,
                    'Options'  => self::$titles,
                ],
            'Gender' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Gender (required only for "Dr" title)',
                    'Required' => false,
                    'Options'  => self::$genders,
                ],
            'FirstName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'First Name (First and Last Name must match passport)',
                    'Required' => true,
                ],
            'LastName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Last Name (First and Last Name must match passport)',
                    'Required' => true,
                ],
            'AddressType' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Preferred Postal Address',
                    'Required' => true,
                    'Options'  =>
                        [
                            'H' => 'Home',
                            'B' => 'Business',
                        ],
                ],
            'Country' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Country (you must be a resident of the countries listed to join Velocity)',
                    'Required' => true,
                    'Options'  => self::$countries,
                ],
            'AddressLine1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Address Line 1',
                    'Required' => true,
                ],
            'City' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'City/Suburb',
                    'Required' => true,
                ],
            'StateOrProvince' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'State (only for Australia)',
                    'Required' => false,
                    'Options'  => self::$australianStates,
                ],
            'PostalCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Postal Code (Australian and NZ residents only)',
                    'Required' => false,
                ],
            'PhoneType' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Phone Type',
                    'Required' => true,
                    'Options'  => self::$phoneTypes,
                ],
            'PhoneCountryCodeNumeric' =>
                [
                    'Type'     => 'string',
                    'Caption'  => '1-3-number Phone Country Code',
                    'Required' => true,
                ],
            'PhoneAreaCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Phone Area Code',
                    'Required' => true,
                ],
            'PhoneLocalNumber' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Phone Number',
                    'Required' => true,
                ],
            'Company' => [
                'Type'     => 'string',
                'Caption'  => 'Company name, required for business address',
                'Required' => false,
            ],
            'JobTitle' => [
                'Type'     => 'string',
                'Caption'  => 'Job title, required for business address',
                'Required' => false,
            ],
            'Email' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Email',
                    'Required' => true,
                ],
            'BirthDay' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Day of Birth Date',
                    'Required' => true,
                ],
            'BirthMonth' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Month of Birth Date',
                    'Required' => true,
                ],
            'BirthYear' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Year of Birth Date',
                    'Required' => true,
                ],
            'Password' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Password (must be between 4 and 16 characters)',
                    'Required' => true,
                ],
            'SecurityQuestionType1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Security Question',
                    'Required' => true,
                    'Options'  => self::$securityQuestionTypes,
                ],
            'SecurityQuestionAnswer1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Security Answer',
                    'Required' => true,
                ],
        ];
    }

    protected function preliminaryFieldsCheck()
    {
        if ($this->fields['PostalCode'] == ''
            and ($this->fields['Country'] == 'AU' or $this->fields['Country'] == 'NZ')) {
            throw new \UserInputError('"PostalCode" field is required for country ' . $this->fields['Country']);
        }

        if ($this->fields['StateOrProvince'] == '' and $this->fields['Country'] == 'AU') {
            throw new \UserInputError('"StateOrProvince" field is required for country ' . $this->fields['Country']);
        }

        if ($this->fields['Country'] == 'AU'
            and array_search($this->fields['StateOrProvince'], array_keys(self::$australianStates)) === false) {
            throw new \UserInputError("Invalid state \"{$this->fields['StateOrProvince']}\" for country \"{$this->fields['Country']}\"");
        }

        if ($this->fields['Title'] === 'DR' && empty($this->fields['Gender'])) {
            throw new \UserInputError('Missing gender');
        }

        if ($this->fields['AddressType'] === 'B') {
            if (empty($this->fields['Company'])) {
                throw new \UserInputError('Company name is required for business address');
            }

            if (empty($this->fields['JobTitle'])) {
                throw new \UserInputError('Job title is required for business address');
            }
        }

        if (in_array($this->fields['Title'], ['MR', 'MSTR']) && !empty($this->fields['Gender']) && $this->fields['Gender'] !== 'M'
            || in_array($this->fields['Title'], ['MRS', 'MS', 'MISS']) && !empty($this->fields['Gender']) && $this->fields['Gender'] !== 'F') {
            throw new \UserInputError('Invalid title or gender');
        }
        $alwaysRequiredFields = [
            'Title',
            'FirstName',
            'LastName',
            'AddressLine1',
            'AddressType',
            'City',
            'PhoneType',
            'PhoneCountryCodeNumeric',
            'PhoneAreaCode',
            'PhoneLocalNumber',
            'Email',
            'BirthDay',
            'BirthMonth',
            'BirthYear',
            'Password',
            'SecurityQuestionType1',
            'SecurityQuestionAnswer1',
        ];
        $missingFields = [];

        foreach ($alwaysRequiredFields as $f) {
            if ($this->fields[$f] === '') {
                $missingFields[] = $f;
            }
        }

        if ($missingFields) {
            $error = 'Missing required field(s) "' . implode('", "', $missingFields) . '"';

            throw new \UserInputError($error);
        }

        if (strlen($this->fields['Password']) < 4 or strlen($this->fields['Password']) > 16) {
            throw new \UserInputError('Your password must be between 4 and 16 characters');
        }
    }

    protected function fail($s)
    {
        $this->saveResponse();

        throw new \EngineError($s);
    }

    protected function setValue($id, $value)
    {
        $input = $this->waitForElement(\WebDriverBy::id($id), $this->waitTimeout);

        if (empty($input)) {
            $this->fail('missing input ' . $id);
        }

        switch (strtolower($input->getTagName())) {
            case 'input':
                $input->clear()->sendKeys($value);

                break;

            case 'select':
                $select = new \WebDriverSelect($input);

                try {
                    $select->selectByValue($value);
                } catch (\NoSuchElementException $e) {
                    $this->fail(sprintf('select %s does not have option %s', $id, $value));
                }

                break;
        }
    }

    protected function hasDetailedAddress()
    {
        return isset($this->fields) && in_array($this->fields['Country'], ['AU', 'NZ']);
    }

    protected function hasState()
    {
        return isset($this->fields) && $this->fields['Country'] === 'AU';
    }
}
