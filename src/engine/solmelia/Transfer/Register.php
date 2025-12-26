<?php

namespace AwardWallet\Engine\solmelia\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    public $timeout = 10;

    public static $titles = [
        'SR'  => 'Mr',
        'SRA' => 'Mrs',
    ];

    public static $countries = [
        'AF' => 'AFGHANISTAN',
        'AX' => 'ALAND ISLANDS',
        'AL' => 'ALBANIA',
        'DZ' => 'ALGERIA',
        'AS' => 'AMERICAN SAMOA',
        'AD' => 'ANDORRA',
        'AO' => 'ANGOLA',
        'AI' => 'ANGUILLA',
        'AQ' => 'ANTARCTICA',
        'AG' => 'ANTIGUA AND BARBUDA',
        'AR' => 'ARGENTINA',
        'AM' => 'ARMENIA',
        'AW' => 'ARUBA',
        'AU' => 'AUSTRALIA',
        'AT' => 'AUSTRIA',
        'AZ' => 'AZERBAIJAN',
        'BS' => 'BAHAMAS',
        'BH' => 'BAHRAIN',
        'BD' => 'BANGLADESH',
        'BB' => 'BARBADOS',
        'BY' => 'BELARUS',
        'BE' => 'BELGIQUE',
        'BZ' => 'BELIZE',
        'BJ' => 'BENIN',
        'BM' => 'BERMUDA',
        'BT' => 'BHUTAN',
        'BO' => 'BOLIVIA',
        'BA' => 'BOSNIA HERZEGOVINA',
        'BW' => 'BOTSWANA',
        'BV' => 'BOUVET, ISLA',
        'BR' => 'BRASIL',
        'BN' => 'BRUNEI DARUSSALAM',
        'BG' => 'BULGARIA',
        'BF' => 'BURKINA FASO',
        'BI' => 'BURUNDI',
        'KH' => 'CAMBODIA',
        'CM' => 'CAMEROON',
        'CA' => 'CANADA',
        'CV' => 'CAPE VERDE, REPUBLIC OF',
        'KY' => 'CAYMAN ISLANDS',
        'CF' => 'CENTRAL AFRICAN REPUBLIC',
        'TD' => 'CHAD',
        'CL' => 'CHILE',
        'CN' => 'CHINA',
        'CX' => 'CHRISTMAS ISLAND',
        'CC' => 'COCOS (KEELING) ISLANDS',
        'CO' => 'COLOMBIA',
        'KM' => 'COMOROS',
        'CG' => 'CONGO',
        'CD' => 'CONGO, DEMOCRATIC REPUBLIC OF THE',
        'CK' => 'COOK ISLANDS',
        'CR' => 'COSTA RICA',
        'CI' => 'COTE D\'IVOIRE',
        'HR' => 'CROATIA',
        'CU' => 'CUBA',
        'CW' => 'CURAÇAO',
        'CY' => 'CYPRUS',
        'CZ' => 'CZECH REPUBLIC',
        'DK' => 'DENMARK',
        'DE' => 'DEUTSCHLAND',
        'DJ' => 'DJIBOUTI',
        'DM' => 'DOMINICA',
        'EC' => 'ECUADOR',
        'EG' => 'EGYPT',
        'SV' => 'EL SALVADOR',
        'ER' => 'ERITREA',
        'ES' => 'ESPAÑA',
        'EE' => 'ESTONIA',
        'ET' => 'ETHIOPIA',
        'FK' => 'FALKLAND ISLANDS (MALVINAS)',
        'FO' => 'FAROE ISLANDS',
        'FJ' => 'FIJI',
        'FI' => 'FINLAND',
        'FR' => 'FRANCE',
        'GF' => 'FRENCH GUYANA',
        'PF' => 'FRENCH POLYNESIA',
        'GA' => 'GABON',
        'GM' => 'GAMBIA',
        'GE' => 'GEORGIA',
        'GH' => 'GHANA',
        'GI' => 'GIBRALTAR',
        'GR' => 'GREECE',
        'GL' => 'GREENLAND',
        'GD' => 'GRENADA',
        'GP' => 'GUADALOUPE',
        'GU' => 'GUAM',
        'GT' => 'GUATEMALA',
        'GG' => 'GUERNESEY (ISLA ANGLONORMANDA DEL CANAL)',
        'GN' => 'GUINEA',
        'GW' => 'GUINEA BISSAU',
        'GQ' => 'GUINEA ECUATORIAL',
        'GY' => 'GUYANA',
        'HT' => 'HAITI',
        'HM' => 'HEARD ISLAND AND MCDONALD ISLANDS',
        'HN' => 'HONDURAS',
        'HK' => 'HONG KONG',
        'HU' => 'HUNGARY',
        'IS' => 'ICELAND',
        'IN' => 'INDIA',
        'ID' => 'INDONESIA',
        'IR' => 'IRAN, ISLAMIC REPUBLIC OF',
        'IQ' => 'IRAQ',
        'IE' => 'IRELAND',
        'IM' => 'ISLA DE MAN',
        'IL' => 'ISRAEL',
        'IT' => 'ITALIA',
        'JM' => 'JAMAICA',
        'JP' => 'JAPAN',
        'JE' => 'JERSEY (ISLA ANGLONORMANDA)',
        'JO' => 'JORDAN',
        'KZ' => 'KAZAKHSTAN',
        'KE' => 'KENYA',
        'KI' => 'KIRIBATI',
        'KP' => 'KOREA, DEMOCRATIC PEOPLE\'S REPUBLIC OF',
        'KR' => 'KOREA, REPUBLIC OF',
        'KW' => 'KUWAIT',
        'KG' => 'KYRGYZSTAN',
        'LA' => 'LAOS PEOPLE\'S DEMOCRATIC REPUBLIC',
        'LV' => 'LATVIA',
        'LB' => 'LEBANON',
        'LS' => 'LESOTHO',
        'LR' => 'LIBERIA',
        'LY' => 'LIBYA - LIBYAN ARAB JAMAHIRIYA',
        'LI' => 'LIECHTENSTEIN',
        'LT' => 'LITHUANIA',
        'LU' => 'LUXEMBOURG',
        'MO' => 'MACAU',
        'MK' => 'MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF',
        'MG' => 'MADAGASCAR (MALAGASY)',
        'MW' => 'MALAWI',
        'MY' => 'MALAYSIA',
        'MV' => 'MALDIVES',
        'ML' => 'MALI',
        'MT' => 'MALTA',
        'MH' => 'MARSHALL ISLANDS',
        'MQ' => 'MARTINIQUE',
        'MR' => 'MAURITANIA',
        'MU' => 'MAURITIUS',
        'YT' => 'MAYOTTE',
        'MX' => 'MEXICO',
        'FM' => 'MICRONESIA, FEDERATED STATES OF',
        'MD' => 'MOLDOVA, REPUBLIC OF',
        'MC' => 'MONACO',
        'MN' => 'MONGOLIA',
        'ME' => 'MONTENEGRO',
        'MS' => 'MONTSERRAT',
        'MA' => 'MOROCCO',
        'MZ' => 'MOZAMBIQUE',
        'MM' => 'MYANMAR',
        'NA' => 'NAMIBIA',
        'NR' => 'NAURU',
        'NP' => 'NEPAL',
        'NL' => 'NETHERLANDS',
        'NC' => 'NEW CALEDONIA',
        'NZ' => 'NEW ZEALAND',
        'NI' => 'NICARAGUA',
        'NE' => 'NIGER',
        'NG' => 'NIGERIA',
        'NU' => 'NIUE',
        'NF' => 'NORFOLK ISLAND',
        'MP' => 'NORTHERN MARIANA ISLANDS',
        'NO' => 'NORWAY',
        'OM' => 'OMAN, SULTANATE OF',
        'BQ' => 'PAISES BAJOS CARIBEÑOS',
        'PK' => 'PAKISTAN',
        'PW' => 'PALAU',
        'PS' => 'PALESTINIAN TERRITORIES',
        'PA' => 'PANAMA',
        'PG' => 'PAPUA NEW GUINEA (NIUGINI)',
        'PY' => 'PARAGUAY',
        'PE' => 'PERU',
        'PH' => 'PHILIPPINES',
        'PN' => 'PITCAIRN',
        'PL' => 'POLAND',
        'PT' => 'PORTUGAL',
        'PR' => 'PUERTO RICO',
        'QA' => 'QATAR',
        'DO' => 'REPUBLICA DOMINICANA',
        'RE' => 'REUNION',
        'RO' => 'ROMANIA',
        'RU' => 'RUSSIAN FEDERATION',
        'RW' => 'RWANDA',
        'SH' => 'SAINT HELENA / ASCENSION ISLAND',
        'KN' => 'SAINT KITTS AND NEVIS',
        'LC' => 'SAINT LUCIA',
        'PM' => 'SAINT PIERRE AND MIQUELON',
        'VC' => 'SAINT VINCENT AND THE GRENADINES',
        'WS' => 'SAMOA, INDEPENDENT STATE OF',
        'SM' => 'SAN MARINO',
        'ST' => 'SAO TOME AND PRINCIPE',
        'SA' => 'SAUDI ARABIA',
        'SN' => 'SENEGAL',
        'RS' => 'SERBIA',
        'SC' => 'SEYCHELLES',
        'SL' => 'SIERRA LEONE',
        'SG' => 'SINGAPORE',
        'SK' => 'SLOVAKIA',
        'SI' => 'SLOVENIA',
        'SB' => 'SOLOMON ISLANDS',
        'SO' => 'SOMALIA',
        'ZA' => 'SOUTH AFRICA',
        'GS' => 'SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS',
        'LK' => 'SRI LANKA',
        'SD' => 'SUDAN',
        'SS' => 'SUDAN DEL SUR',
        'SR' => 'SURINAME',
        'SJ' => 'SVALBARD AND JAN MAYEN',
        'SZ' => 'SWAZILAND',
        'SE' => 'SWEDEN',
        'CH' => 'SWITZERLAND',
        'SY' => 'SYRIAN ARAB REPUBLIC',
        'TW' => 'TAIWAN',
        'TJ' => 'TAJIKISTAN',
        'TZ' => 'TANZANIA, UNITED REPUBLIC OF',
        'TH' => 'THAILAND',
        'TF' => 'TIERRAS AUSTRALES FRANCESAS',
        'TL' => 'TIMOR-LESTE',
        'TG' => 'TOGO',
        'TK' => 'TOKELAU',
        'TO' => 'TONGA',
        'TT' => 'TRINIDAD AND TOBAGO',
        'TN' => 'TUNISIA',
        'TR' => 'TURKEY',
        'TM' => 'TURKMENISTAN',
        'TC' => 'TURKS AND CAICOS ISLANDS',
        'TV' => 'TUVALU',
        'UG' => 'UGANDA',
        'UA' => 'UKRAINE',
        'AE' => 'UNITED ARAB EMIRATES',
        'GB' => 'UNITED KINGDOM',
        'UM' => 'UNITED STATES MINOR OUTLYING ISLANDS',
        'US' => 'UNITED STATES OF AMERICA',
        'UY' => 'URUGUAY',
        'UZ' => 'UZBEKISTAN',
        'VU' => 'VANUATU',
        'VA' => 'VATICANO CITY STATE',
        'VE' => 'VENEZUELA',
        'VN' => 'VIETNAM',
        'VG' => 'VIRGIN ISLANDS, BRITISH',
        'VI' => 'VIRGIN ISLANDS, U.S.',
        'WF' => 'WALLIS AND FUTUNA',
        'EH' => 'WESTERN SAHARA',
        'YE' => 'YEMEN',
        'ZM' => 'ZAMBIA',
        'ZW' => 'ZIMBABWE',
    ];

    public static $inputFieldsMap = [
        'AddressLine1' => 'nameAddress',
        'City'         => 'nameCity',
        'Email'        => 'nameEmail',
        'FirstName'    => 'nameNombre',
        'LastName'     => 'namePrimerApellido',
        'Password'     => ['namePassword', 'namePassword2'],
        'PostalCode'   => 'nameZipCode',
        'Title'        => 'tratamiento',
    ];
    protected $retry = 1;
    protected $registrUrl = 'https://www.melia.com/en/meliarewards/registrate/home.htm';
    protected $captcha = null;

    public function InitBrowser()
    {
        $this->UseSelenium();
        $this->useChromium();

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyReCaptcha());
        } else {
            // $this->http->SetProxy('localhost:8000');
        }

        $this->keepCookies(false);
        $this->keepSession(false);
        $this->AccountFields['BrowserState'] = null;
    }

    public function registerAccount(array $fields)
    {
        $this->logger->notice(__METHOD__);

        foreach (['Country', 'Nationality'] as $k) {
            if (!in_array($fields[$k], array_keys(self::$countries))) {
                throw new \UserInputError('Invalid country code: ' . $k);
            }
        }

        if (strlen($fields['PhoneAreaCode'] . $fields['PhoneLocalNumber']) < 3) {
            throw new \UserInputError('The phone must be at least three digits');
        }

        $retry = 0;

        do {
            $retry++;
            $this->http->Log("Registration -> try {$retry}");
            $attempt = $this->registerAttempt($fields);
        } while ($attempt === false && $retry < $this->retry);

        // $this->driver->executeScript('enviar();');

        // $this->driver->switchTo()->defaultContent();
        // $this->http->SetBody($this->driver->executeScript('return document.documentElement.innerHTML'));

        // if( !$btn = $this->waitForElement(\WebDriverBy::id('divboton')) )
        // 	throw new \EngineError('Button for registration not found');
        // $btn->click();

        $status = $this->sendDataWithCurl($fields);

        if ($status === true) {
            return true;
        }

        $this->logger->info('Error registration');
        // if($this->checkSuccess() === true)
        // 	return true;
        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Email' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Email',
                    'Required' => true,
                ],
            'Password' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Password',
                    'Required' => true,
                ],
            'Title' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Title',
                    'Required' => true,
                    'Options'  => self::$titles,
                ],
            'Country' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Country code',
                    'Required' => true,
                    'Options'  => self::$countries,
                ],
            'Nationality' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Nationality, country code',
                    'Required' => true,
                    'Options'  => self::$countries,
                ],
            'StateOrProvince' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'State or Province',
                    'Required' => true,
                ],
            'FirstName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'First Name',
                    'Required' => true,
                ],
            'LastName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Last Name',
                    'Required' => true,
                ],
            'City' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'City',
                    'Required' => true,
                ],
            'PhoneAreaCode' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Area Code',
                'Required' => true,
            ],
            'PhoneLocalNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Number',
                'Required' => true,
            ],
            'PostalCode' => [
                'Type'     => 'string',
                'Caption'  => 'Postal code',
                'Required' => true,
            ],
            'AddressLine1' => [
                'Type'     => 'string',
                'Caption'  => 'Postal address',
                'Required' => true,
            ],
            'BirthDay' => [
                'Type'     => 'integer',
                'Caption'  => 'Day Of Birth Date',
                'Required' => true,
            ],
            'BirthMonth' => [
                'Type'     => 'integer',
                'Caption'  => 'Month of Birth Date',
                'Required' => true,
            ],
            'BirthYear' => [
                'Type'     => 'integer',
                'Caption'  => 'Year of Birth Date',
                'Required' => true,
            ],
        ];
    }

    protected function checkSuccess()
    {
        $this->logger->notice(__METHOD__);
        //		$http2 = clone $this;
        //		$this->http->brotherBrowser($http2->http);
//
        //		$http2->UseSelenium();
        //		$http2->useChrome();
//
        //		$http2->Start();

        $headers = [
            //			'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.98 Safari/537.36',
            'x-requested-with' => 'XMLHttpRequest',
            'content-type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            //			'accept-encoding' => 'gzip, deflate, br',
            //			'accept-language' => 'ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4',
            'adrum'  => 'isAjax:true',
            'accept' => '*/*',
        ];

        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expire'] ?? null);
        }

        $this->http->GetURL('https://www.melia.com/en/mymeliarewards/home.htm', $headers);

        sleep(20);
        $elem = $this->waitForElement(\WebDriverBy::xpath("//div[contains(., 'Card:') and not(descendant::div)]/descendant::strong[1]"), $this->timeout);
        $this->saveResponse();

        if ($elem) {
            $this->ErrorMessage = 'Your CardNumber is ' . $elem->getText();
            $this->http->Log($this->ErrorMessage);

            return true;
        }

        if ($errIFrame = $this->waitForElement(\WebDriverBy::xpath("//div[@id='cboxLoadedContent']//iframe"), $this->timeout, false)) {
            $this->driver->switchTo()->frame($errIFrame);

            if ($elem = $this->waitForElement(\WebDriverBy::className('contMsgLight'), $this->timeout)) {
                throw new \UserInputError($elem->getText());
            }
            $this->driver->switchTo()->defaultContent();
        }

        if ($this->waitForElement(\WebDriverBy::xpath("(//div[contains(@class,'errorCanc')])[1]"), $this->timeout)) {
            $errors = $this->driver->findElements(\WebDriverBy::xpath("//span[contains(@class,'alertaError')]"));
            $errMsg = '';

            foreach ($errors as $error) {
                $errMsg .= $error->getText() . "\n";
            }

            throw new \UserInputError($errMsg); // Is it always user input error?
        }

        return false;
    }

    protected function registerAttempt(array $fields)
    {
        $this->logger->notice(__METHOD__);
        $this->driver->manage()->window()->maximize();

        $this->http->GetURL($this->registrUrl);
        $this->driver->executeScript("
			$('#nacionalidadselect').val('{$fields['Nationality']}');
			$('#inputDateDay').val('{$fields['BirthDay']}');
			$('#inputDateMonth').val('{$fields['BirthMonth']}');
			$('#inputDateYear').val('{$fields['BirthYear']}');
		");

        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if (!isset($fields[$awKey])) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                if (!empty($fields[$awKey])) {
                    $this->waitForElement(\WebDriverBy::name($provKey), $this->timeout)->sendKeys($fields[$awKey]);
                } else {
                    throw new \UserInputError($awKey . ' - field can not be empty');
                }
            }
        }

        $inputSelectFieldsMap = [
            'Country' => [
                'selectpais',
                $fields['Country'],
            ],
        ];

        foreach ($inputSelectFieldsMap as $key => $selectField) {
            [$selectFieldId, $value] = $selectField;
            $this->logger->info("Selecting '$key' to '$value' ...");

            try {
                (new \WebDriverSelect($this->waitForElement(\WebDriverBy::id($selectFieldId), $this->timeout)))->selectByValue($value);
            } catch (\NoSuchElementException $e) {
                return $this->logger->info(sprintf('no value %s for select %s', $value, $key));
            }
        }

        if (!empty($fields['StateOrProvince'])) {
            $state = $fields['StateOrProvince'] . " ";
            $this->driver->executeScript("
				$('#selectprovincia').val('{$state}');
			");
        }

        $phone = $this->waitForElement(\WebDriverBy::id('inputTelefono'), $this->timeout);

        if (empty($phone)) {
            throw new \EngineError('Input for telephone number not found');
        }
        $phone->sendKeys($fields['PhoneAreaCode'] . $fields['PhoneLocalNumber']);

        $agreeText = $this->waitForElement(\WebDriverBy::xpath("//a[contains(text(), 'Terms and conditions')]"), $this->timeout);

        if (empty($agreeText)) {
            throw new \EngineError('Text for agree not found');
        }
        //		$agree->click();

        $this->driver->executeScript("
			var agree = $('#billing-data-enabler');
			agree.click();
		");

        //-----------CAPTCHA
        $iframe = $this->waitForElement(\WebDriverBy::xpath("//div[@id='g-recaptcha']//iframe"), $this->timeout, false);

        if (!$iframe) {
//            $this->waitForElement(\WebDriverBy::id('summaryEnrollmentSubmitButton'), $this->timeout)->click();
//            $iframe = $this->waitForElement(\WebDriverBy::xpath("//div[@class = 'g-recaptcha']//iframe"), $this->timeout, false);
            throw new \EngineError('No reCaptcha frame');
        }

        $this->http->SetBody($this->driver->executeScript('return document.documentElement.innerHTML'));
        $result = false;
        //		for($retry=0;$retry<3;$retry++ )
        if ($iframe) {
            $this->http->Log("wait captcha iframe");
            //			$this->driver->switchTo()->defaultContent();
            //			sleep(10);
            $this->captcha = $this->parseCaptcha($this);
            $this->logger->notice("Remove iframe");
            $this->driver->executeScript("$('div#g-recaptcha iframe').remove();");
            $this->driver->executeScript("$('#g-recaptcha-response').val(\"" . $this->captcha . "\");");
            $this->driver->executeScript("$('#hiddenRecaptcha').val(\"" . $this->captcha . "\");");

            if ($this->captcha === false) {
                $this->http->Log('Failed to pass captcha');

                throw new \CheckRetryNeededException(3, 2, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            } else {
                $result = true;
            }
        }// if ($iframe)
        else {
            $this->http->Log('Could not find iFrame with captcha, trying to do normal login', LOG_LEVEL_ERROR);

            throw new \CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
        }
        //-----------END CAPTCHA

        if ($this->waitForElement(\WebDriverBy::xpath("(//label[@class = 'error'])[1]"), $this->timeout)) {
            $errors = $this->driver->findElements(\WebDriverBy::xpath("//label[@class = 'error']"));
            $errMsg = '';

            foreach ($errors as $error) {
                $errMsg .= $error->getText() . "\n";
            }

            throw new \UserInputError($errMsg);
        }

        return $result;
    }

    protected function parseCaptcha($http2)
    {
        $http2->http->Log(__METHOD__);
        $key = $http2->http->FindPreg('/var key = "(.+?)";/');

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;

        $parameters = [
            "pageurl" => $http2->http->currentUrl(),
            "proxy"   => $http2->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }

    private function sendDataWithCurl(array $fields)
    {
        $this->logger->notice(__METHOD__);
        $http2 = new \HttpBrowser('none', new \CurlDriver());
        $this->http->brotherBrowser($http2);

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $http2->SetProxy($this->http->GetProxy());
        } else {
            // $http2->SetProxy('localhost:8000');
        }

        $http2->setCookie("meliaVersion", "2009", ".melia.com");
        $http2->setDefaultHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36 (AwardWallet Service. For questions please contact us at https://awardwallet.com/contact)');

        $postFormData = [];

        $otherInputs = [
            'Country'     => 'namePais',
            'Nationality' => 'namenacionalidad',
            'BirthDay'    => 'nameDateDay',
            'BirthMonth'  => 'nameDateMonth',
            'BirthYear'   => 'nameDateYear',
        ];
        $inputs = array_merge(self::$inputFieldsMap, $otherInputs);

        foreach ($inputs as $awKey => $provKeys) {
            if (!isset($fields[$awKey])) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                if (!empty($fields[$awKey])) {
                    $postFormData[$provKey] = $fields[$awKey];
                } else {
                    throw new \UserInputError($awKey . ' - field can not be empty');
                }
            }
        }
        $postFormData['nameTelefono'] = $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];
        $countryCodeNumeric = $this->http->FindSingleNode("//option[@data-pais = '" . $fields['Country'] . "']/@value");
        $postFormData['namePrefijo'] = $countryCodeNumeric;
        $postFormData['namecheckbox'] = "on";
        $postFormData['idUser'] = "0";
        $postFormData['urlactual'] = "https://www.melia.com/en/meliarewards/registrate/home.htm";
        $postFormData['g-recaptcha-response'] = $this->captcha;
        $postFormData['hiddenRecaptcha'] = $this->captcha;
        $postFormData['namePaisText'] = self::$countries[$fields['Country']];
        $postFormData['nameDocIdentidad'] = "";
        $postFormData['nameSegundoApellido'] = "";

        if (!empty($fields['StateOrProvince'])) {
            $fullNameState = $this->http->FindSingleNode("//option[@value = '" . $fields['StateOrProvince'] . "']/text()");
            $postFormData['nameProvincia'] = $fields['StateOrProvince'];
            $postFormData['nameProvinciaCode'] = $fields['StateOrProvince'];
            $postFormData['nameProvinciaText'] = $fullNameState;
        } else {
            $postFormData['nameProvinciaCode'] = '';
            $postFormData['nameProvinciaText'] = '';
        }

        // Cookies
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $http2->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expire'] ?? null);
        }

        $this->http->RetryCount = 0;
        $http2->LogHeaders = true;

        // Post
        $headers = [
            'Accept'           => '*/*',
            'adrum'            => 'isAjax:true',
            'content-type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            'x-requested-with' => 'XMLHttpRequest',

            ':authority' => 'www.melia.com',
            ':method'    => 'POST',
            ':path'      => '/wps/Melia_CSTM_WCM/ServletRegistro',
            ':scheme'    => 'https',
        ];
        $status = $http2->PostURL('https://www.melia.com/wps/Melia_CSTM_WCM/ServletRegistro', $postFormData, $headers);

        if ($http2->Response['code'] === 400
                && $http2->FindPreg(preg_quote('/RHK,??y%?@???????W?Z????X?P?X???/'))) {
            throw new \UserInputError('This email has already been registered with');
        }

        if (!$status) {
            throw new \EngineError('Failed post form');
        }

        // Post
        $data = [
            'prettyuri'            => 'melia:/en/meliarewards/registrate/home.htm',
            'iduserauth'           => '',
            'urlActual'            => 'https://www.melia.com/en/meliarewards/registrate/home.htm',
            'login-email'          => '',
            'login-password'       => '',
            'g-recaptcha-response' => '',
            'hiddenRecaptcha'      => '',
        ];
        $http2->PostURL('https://www.melia.com/wps/portal/melia/Home/meliarewardspublico/registro/!ut/p/z1/04_Sj9CPykssy0xPLMnMz0vMAfIjo8zivQ0tLTxM3A18LAyczAwcPcJMjZ0DLQzMgs30wwkpiAJKG-AAjgZA_VFYlDgaOAUZORkbGLj7G2FVgGJGcGpevLuTfkFuhEGWiaMiAIjCNsM!/p0/IZ7_K198H4G0L035B0A9G5J3BF1SD7=CZ6_K198H4G0L80B60AHV53CQ806S6=LA0=/', $data);

        // return login and name
        $http2->PostURL('https://www.melia.com/wps/Melia_CSTM_WCM/MeliaRewards/MeliaRewards_PuntosDisponibles_Ajax.jsp?f=' . time() . date('B'), ['localeTag' => 'en-GB']);

        //		$http2->SetBody($this->driver->executeScript('return document.documentElement.innerHTML'));

        $success = $http2->FindSingleNode("//div[contains(., 'Card:') and not(descendant::div)]/descendant::strong[1]");

        if (!empty($success)) {
            $this->ErrorMessage = 'Your card number is ' . $success;
            $this->logger->info('Successfull registration. Your card number is ' . $this->ErrorMessage);

            return true;
        }

        return false;
    }
}
