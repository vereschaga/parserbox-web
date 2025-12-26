<?php

namespace AwardWallet\Engine\skywards\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    public static $inputFieldsMap = [
        'Email'                      => 'txtEmailAddress',
        'Title'                      => 'ddlTitle',
        'FirstName'                  => 'txtFirstName',
        'LastName'                   => 'txtFamilyName',
        'BirthDay'                   => 'ddlDates',
        'BirthMonth'                 => 'ddlMonth',
        'BirthYear'                  => 'ddlYear',
        'Country'                    => 'ddlResidence',
        'PreferredLanguage'          => 'ddlPreferredLanguage',
        'PhoneCountryCodeAlphabetic' => 'ctl00$MainContent$ctl00$mobileTelephoneNumber$ddlCountryCode',
        'Phone'                      => 'ctl00$MainContent$ctl00$mobileTelephoneNumber$txtTelephone',
        'Password'                   => ['txtSetNewPassword', 'txtConfirmNewPwd'],
    ];
    public static $countries = [
        "AF" => "Afghanistan",
        "AL" => "Albania",
        "DZ" => "Algeria",
        "AS" => "American Samoa",
        "AD" => "Andorra",
        "AO" => "Angola",
        "AI" => "Anguilla",
        "AG" => "Antigua",
        "AR" => "Argentina",
        "AM" => "Armenia",
        "AW" => "Aruba",
        "AU" => "Australia",
        "AT" => "Austria",
        "AZ" => "Azerbaijan",
        "BS" => "Bahamas",
        "BH" => "Bahrain",
        "BD" => "Bangladesh",
        "BB" => "Barbados",
        "BY" => "Belarus",
        "BE" => "Belgium",
        "BZ" => "Belize",
        "BJ" => "Benin Republic",
        "BM" => "Bermuda",
        "BT" => "Bhutan",
        "BO" => "Bolivia",
        "BA" => "Bosnia-Herzegovina",
        "BW" => "Botswana",
        "BR" => "Brazil",
        "VG" => "British Virgin Islands",
        "BN" => "Brunei",
        "BG" => "Bulgaria",
        "BF" => "Burkina Faso",
        "BI" => "Burundi",
        "KH" => "Cambodia",
        "CA" => "Canada",
        "CV" => "Cape Verde",
        "KY" => "Cayman Islands",
        "CF" => "Central African Rep",
        "TD" => "Chad",
        "CL" => "Chile",
        "CN" => "China",
        "CX" => "Christmas Island",
        "CC" => "Cocos Islands",
        "CO" => "Colombia",
        "CG" => "Congo",
        "CK" => "Cook Islands",
        "CR" => "Costa Rica",
        "HR" => "Croatia",
        "CU" => "Cuba",
        "CY" => "Cyprus",
        "CZ" => "Czech Republic",
        "DK" => "Denmark",
        "DJ" => "Djibouti",
        "DM" => "Dominica",
        "DO" => "Dominican Rep",
        "EC" => "Ecuador",
        "EG" => "Egypt",
        "SV" => "El Salvador",
        "GQ" => "Equatorial Guinea",
        "ER" => "Eritrea",
        "EE" => "Estonia",
        "ET" => "Ethiopia",
        "FO" => "Faeroe Is",
        "FK" => "Falkland Is",
        "FJ" => "Fiji",
        "FI" => "Finland",
        "FR" => "France",
        "GF" => "French Guyana",
        "PF" => "French Polynesia",
        "GA" => "Gabon",
        "GM" => "Gambia",
        "GE" => "Georgia",
        "DE" => "Germany",
        "GH" => "Ghana",
        "GI" => "Gibraltar (UK)",
        "GR" => "Greece",
        "GL" => "Greenland",
        "GD" => "Grenada",
        "GP" => "Guadeloupe",
        "GU" => "Guam",
        "GT" => "Guatemala",
        "GN" => "Guinea",
        "GW" => "Guinea Bissau",
        "GY" => "Guyana",
        "HT" => "Haiti",
        "HN" => "Honduras",
        "HK" => "Hong Kong",
        "HU" => "Hungary",
        "IS" => "Iceland",
        "IN" => "India",
        "ID" => "Indonesia",
        "IR" => "Iran",
        "IQ" => "Iraq",
        "IE" => "Ireland",
        "IL" => "Israel",
        "IT" => "Italy",
        "CI" => "Ivory Coast",
        "JM" => "Jamaica",
        "JP" => "Japan",
        "JO" => "Jordan",
        "KZ" => "Kazakhstan",
        "KE" => "Kenya",
        "KI" => "Kiribati",
        "KW" => "Kuwait",
        "KG" => "Kyrgyzstan",
        "LA" => "Laos",
        "LV" => "Latvia",
        "LB" => "Lebanon",
        "LS" => "Lesotho",
        "LR" => "Liberia",
        "LY" => "Libya",
        "LI" => "Liechtenstein",
        "LT" => "Lithuania",
        "LU" => "Luxembourg",
        "MO" => "Macau",
        "MK" => "Macedonia",
        "MG" => "Madagascar",
        "MW" => "Malawi",
        "MY" => "Malaysia",
        "MV" => "Maldives",
        "ML" => "Mali",
        "MT" => "Malta",
        "MP" => "Mariana Islands",
        "MH" => "Marshall Islands",
        "MQ" => "Martinique",
        "MR" => "Mauritania",
        "MU" => "Mauritius",
        "MX" => "Mexico",
        "FM" => "Micronesia",
        "UM" => "Minor Island",
        "MD" => "Moldova",
        "MC" => "Monaco",
        "ME" => "Montenegro",
        "MS" => "Montserrat",
        "MA" => "Morocco",
        "MZ" => "Mozambique",
        "MM" => "Myanmar",
        "NA" => "Namibia",
        "NR" => "Nauru",
        "NP" => "Nepal",
        "NL" => "Netherlands",
        "NC" => "New Caledonia",
        "NZ" => "New Zealand",
        "NI" => "Nicaragua",
        "NE" => "Niger",
        "NG" => "Nigeria",
        "NU" => "Niue",
        "NF" => "Norfolk Island",
        "NO" => "Norway",
        "OM" => "Oman",
        "PK" => "Pakistan",
        "PA" => "Panama",
        "PG" => "Papua New Guinea",
        "PY" => "Paraguay",
        "KP" => "Peoples Rep Korea",
        "PE" => "Peru",
        "PH" => "Philippines",
        "PL" => "Poland",
        "PT" => "Portugal",
        "PR" => "Puerto Rico",
        "QA" => "Qatar",
        "CM" => "Republic Cameroon",
        "RE" => "Reunion",
        "RO" => "Romania",
        "RU" => "Russia",
        "RW" => "Rwanda",
        "SM" => "San Marino",
        "SA" => "Saudi Arabia",
        "SN" => "Senegal",
        "RS" => "Serbia",
        "SC" => "Seychelles",
        "SL" => "Sierra Leone",
        "SG" => "Singapore",
        "SK" => "Slovakia",
        "SI" => "Slovenia",
        "SB" => "Solomon Island",
        "SO" => "Somalia",
        "ZA" => "South Africa",
        "KR" => "South Korea",
        "ES" => "Spain",
        "LK" => "Sri Lanka",
        "KN" => "St Kitts and Nevis",
        "LC" => "St Lucia",
        "VC" => "St Vincent",
        "SD" => "Sudan",
        "SR" => "Suriname",
        "SZ" => "Swaziland",
        "SE" => "Sweden",
        "CH" => "Switzerland",
        "SY" => "Syria",
        "TW" => "Taiwan",
        "TJ" => "Tajikistan",
        "TZ" => "Tanzania",
        "TH" => "Thailand",
        "TL" => "Timor - Leste",
        "TG" => "Togo",
        "TO" => "Tonga",
        "TT" => "Trinidad and Tobago",
        "TN" => "Tunisia",
        "TR" => "Turkey",
        "TM" => "Turkmenistan",
        "TC" => "Turks Caicos",
        "TV" => "Tuvalu",
        "VI" => "US Virgin Islands",
        "US" => "United States",
        "UG" => "Uganda",
        "UA" => "Ukraine",
        "AE" => "United Arab Emirates",
        "GB" => "United Kingdom",
        "UY" => "Uruguay",
        "UZ" => "Uzbekistan",
        "VU" => "Vanuatu",
        "VE" => "Venezuela",
        "VN" => "Vietnam",
        "WS" => "Western Samoa",
        "YE" => "Yemen Republic",
        "ZM" => "Zambia",
        "ZW" => "Zimbabwe",
    ];
    public static $titles = [
        'Mr'   => 'Mr',
        'Ms'   => 'Ms',
        'Mrs'  => 'Mrs',
        'Miss' => 'Miss',
    ];
    public static $phoneCountryCodes = [
        'AF' => 'Afghanistan (+93)',
        'AL' => 'Albania (+355)',
        'DZ' => 'Algeria (+213)',
        'AS' => 'American Samoa (+1)',
        'AD' => 'Andorra (+376)',
        'AO' => 'Angola (+244)',
        'AI' => 'Anguilla (+1)',
        'AG' => 'Antigua and Barbuda (+1)',
        'AR' => 'Argentina (+54)',
        'AM' => 'Armenia (+374)',
        'AW' => 'Aruba (+297)',
        'AU' => 'Australia (+61)',
        'AT' => 'Austria (+43)',
        'AZ' => 'Azerbaijan (+994)',
        'BS' => 'Bahamas (+1)',
        'BH' => 'Bahrain (+973)',
        'BD' => 'Bangladesh (+880)',
        'BB' => 'Barbados (+1)',
        'BY' => 'Belarus (+375)',
        'BE' => 'Belgium (+32)',
        'BZ' => 'Belize (+501)',
        'BJ' => 'Benin (+229)',
        'BM' => 'Bermuda (+1)',
        'BT' => 'Bhutan (+975)',
        'BO' => 'Bolivia (+591)',
        'BA' => 'Bosnia and Herzegovina (+387)',
        'BW' => 'Botswana (+267)',
        'BR' => 'Brazil (+55)',
        'VG' => 'British Virgin Islands (+1)',
        'BN' => 'Brunei Darussalam (+673)',
        'BG' => 'Bulgaria (+359)',
        'BF' => 'Burkina Faso (+226)',
        'BI' => 'Burundi (+257)',
        'KH' => 'Cambodia (+855)',
        'CM' => 'Cameroon (+237)',
        'CA' => 'Canada (+1)',
        'CV' => 'Cape Verde (+238)',
        'KY' => 'Cayman Islands (+1)',
        'CF' => 'Central African Republic (+236)',
        'TD' => 'Chad (+235)',
        'CL' => 'Chile (+56)',
        'CN' => 'China (+86)',
        'CO' => 'Colombia (+57)',
        'KM' => 'Comoros (+269)',
        'CD' => 'Congo, Democratic Repulic of the (+243)',
        'CG' => 'Congo, Republic of the (+242)',
        'CK' => 'Cook Islands (+682)',
        'CR' => 'Costa Rica (+506)',
        'CI' => 'Côte d\'Ivoire (Ivory Coast) (+225)',
        'HR' => 'Croatia (+385)',
        'CU' => 'Cuba (+53)',
        'CY' => 'Cyprus (+357)',
        'CZ' => 'Czech Republic (+420)',
        'DK' => 'Denmark (+45)',
        'DJ' => 'Djibouti (+253)',
        'DM' => 'Dominica (+1)',
        'DO' => 'Dominican Republic (+1)',
        'EC' => 'Ecuador (+593)',
        'EG' => 'Egypt (+20)',
        'SV' => 'El Salvador (+503)',
        'GQ' => 'Equatorial Guinea (+240)',
        'EE' => 'Estonia (+372)',
        'ET' => 'Ethiopia (+251)',
        'FK' => 'Falkland Islands (+500)',
        'FO' => 'Faroe Islands (+298)',
        'FJ' => 'Fiji (+679)',
        'FI' => 'Finland (+358)',
        'FR' => 'France (+33)',
        'GF' => 'French Guiana (+594)',
        'PF' => 'French Polynesia (+689)',
        'GA' => 'Gabon (+241)',
        'GM' => 'Gambia (+220)',
        'GE' => 'Georgia (+995)',
        'DE' => 'Germany (+49)',
        'GH' => 'Ghana (+233)',
        'GI' => 'Gibraltar (+350)',
        'GR' => 'Greece (+30)',
        'GL' => 'Greenland (+299)',
        'GD' => 'Grenada (+1)',
        'GP' => 'Guadeloupe (+590)',
        'GU' => 'Guam (+1)',
        'GT' => 'Guatemala (+502)',
        'GG' => 'Guernsey (+44)',
        'GN' => 'Guinea (+224)',
        'GW' => 'Guinea-Bissau (+245)',
        'GY' => 'Guyana (+592)',
        'HT' => 'Haiti (+509)',
        'HN' => 'Honduras (+504)',
        'HK' => 'Hong Kong (+852)',
        'HU' => 'Hungary (+36)',
        'IS' => 'Iceland (+354)',
        'IN' => 'India (+91)',
        'ID' => 'Indonesia (+62)',
        'IR' => 'Iran (+98)',
        'IQ' => 'Iraq (+964)',
        'IE' => 'Ireland (+353)',
        'IM' => 'Isle of Man (+44)',
        'IT' => 'Italy (+39)',
        'JM' => 'Jamaica (+1)',
        'JP' => 'Japan (+81)',
        'JE' => 'Jersey (+44)',
        'JO' => 'Jordan (+962)',
        'KZ' => 'Kazakhstan (+7)',
        'KE' => 'Kenya (+254)',
        'KR' => 'Korea, Republic of (+82)',
        'KW' => 'Kuwait (+965)',
        'KG' => 'Kyrgyzstan (+996)',
        'LA' => 'Lao People\'s Democratic Republic (+856)',
        'LV' => 'Latvia (+371)',
        'LB' => 'Lebanon (+961)',
        'LS' => 'Lesotho (+266)',
        'LR' => 'Liberia (+231)',
        'LY' => 'Libya (+218)',
        'LI' => 'Liechtenstein (+423)',
        'LT' => 'Lithuania (+370)',
        'LU' => 'Luxembourg (+352)',
        'MO' => 'Macau (+853)',
        'MK' => 'Macedonia, Former Yugoslav Republic of (+389)',
        'MG' => 'Madagascar (+261)',
        'MW' => 'Malawi (+265)',
        'MY' => 'Malaysia (+60)',
        'MV' => 'Maldives (+960)',
        'ML' => 'Mali (+223)',
        'MT' => 'Malta (+356)',
        'MQ' => 'Martinique (+596)',
        'MR' => 'Mauritania (+222)',
        'MU' => 'Mauritius (+230)',
        'MX' => 'Mexico (+52)',
        'MD' => 'Moldova (+373)',
        'MC' => 'Monaco (+377)',
        'MN' => 'Mongolia (+976)',
        'ME' => 'Montenegro (+382)',
        'MS' => 'Montserrat (+1)',
        'MA' => 'Morocco (+212)',
        'MZ' => 'Mozambique (+258)',
        'NA' => 'Namibia (+264)',
        'NP' => 'Nepal (+977)',
        'NL' => 'Netherlands (+31)',
        'AN' => 'Netherlands Antilles (+599)',
        'NC' => 'New Caledonia (+687)',
        'NZ' => 'New Zealand (+64)',
        'NI' => 'Nicaragua (+505)',
        'NE' => 'Niger (+227)',
        'NG' => 'Nigeria (+234)',
        'NO' => 'Norway (+47)',
        'OM' => 'Oman (+968)',
        'PK' => 'Pakistan (+92)',
        'PW' => 'Palau (+680)',
        'PS' => 'Palestinian Territories (+970)',
        'PA' => 'Panama (+507)',
        'PG' => 'Papua New Guinea (+675)',
        'PY' => 'Paraguay (+595)',
        'PE' => 'Peru (+51)',
        'PH' => 'Philippines (+63)',
        'PL' => 'Poland (+48)',
        'PT' => 'Portugal (+351)',
        'PR' => 'Puerto Rico (+1)',
        'QA' => 'Qatar (+974)',
        'RE' => 'Reunion (+262)',
        'RO' => 'Romania (+40)',
        'RU' => 'Russia (+7)',
        'RW' => 'Rwanda (+250)',
        'SM' => 'San Marino (+378)',
        'SA' => 'Saudi Arabia (+966)',
        'SN' => 'Senegal (+221)',
        'RS' => 'Serbia (+381)',
        'SC' => 'Seychelles (+248)',
        'SL' => 'Sierra Leone (+232)',
        'SG' => 'Singapore (+65)',
        'SK' => 'Slovakia (+421)',
        'SI' => 'Slovenia (+386)',
        'SB' => 'Solomon Islands (+677)',
        'SO' => 'Somalia (+252)',
        'ZA' => 'South Africa (+27)',
        'ES' => 'Spain (+34)',
        'LK' => 'Sri Lanka (+94)',
        'KN' => 'St Kitts and Nevis (+1)',
        'LC' => 'St Lucia (+1)',
        'VC' => 'St Vincent and The Grenadines (+1)',
        'SD' => 'Sudan (+249)',
        'SR' => 'Suriname (+597)',
        'SZ' => 'Swaziland (+268)',
        'SE' => 'Sweden (+46)',
        'CH' => 'Switzerland (+41)',
        'SY' => 'Syria (+963)',
        'TW' => 'Taiwan (+886)',
        'TJ' => 'Tajikistan (+992)',
        'TZ' => 'Tanzania (+255)',
        'TH' => 'Thailand (+66)',
        'TG' => 'Togo (+228)',
        'TO' => 'Tonga (+676)',
        'TT' => 'Trinidad and Tobago (+1)',
        'TN' => 'Tunisia (+216)',
        'TR' => 'Turkey (+90)',
        'TM' => 'Turkmenistan (+993)',
        'TC' => 'Turks and Caicos Islands (+1)',
        'UG' => 'Uganda (+256)',
        'UA' => 'Ukraine (+380)',
        'AE' => 'United Arab Emirates (+971)',
        'GB' => 'United Kingdom (+44)',
        'US' => 'United States (+1)',
        'UY' => 'Uruguay (+598)',
        'VI' => 'US Virgin Islands (+1)',
        'UZ' => 'Uzbekistan (+998)',
        'VU' => 'Vanuatu (+678)',
        'VE' => 'Venezuela (+58)',
        'VN' => 'Vietnam (+84)',
        'EH' => 'Western Sahara (+685)',
        'YE' => 'Yemen (+967)',
        'ZM' => 'Zambia (+260)',
        'ZW' => 'Zimbabwe (+263)',
    ];
    protected $loadTimeout = 20;

    public function InitBrowser()
    {
        $this->UseCurlBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyDOP());
        }
        //		else
        //			$this->http->SetProxy('localhost:8000');

        $this->http->LogHeaders = true;
        $this->ArchiveLogs = true;
    }

    public function InitBrowserSelenium()
    {
        $this->UseSelenium();

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            $this->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
            $this->http->SetProxy($this->proxyDOP());
        } else {
            //			$this->http->SetProxy('localhost:8000');
        }

        $this->http->saveScreenshots = true;
        $this->ArchiveLogs = true;
    }

    public function registerAccount(array $fields)
    {
        $fields['Phone'] = $this->getPhone($fields);

        $this->http->GetURL('https://www.emirates.com/account/english/light-registration/index.aspx?showinterim=true');
        $status = $this->http->ParseForm('aspnetForm');

        if (!$status) {
            $this->http->Log('Failed to parse create account form');

            return false;
        }

        $fields['BirthDay'] = ($fields['BirthDay'] + 0) < 10 ? '0' . $fields['BirthDay'] : $fields['BirthDay'];

        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if (!isset($fields[$awKey])) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                $this->http->SetInputValue($provKey, $fields[$awKey]);
            }
        }

        $countries = self::$countries;
        $addValues = [
            'ddlPreferredLanguage'            => '21',
            'chkAgreemant'                    => 'on',
            'ctl00$MainContent$ctl00$btnjoin' => 'Join',
            'ddlResidence-suggest'            => $countries[$fields['Country']],
        ];

        foreach ($addValues as $key => $val) {
            $this->http->SetInputValue($key, $val);
        }

        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to post create account form');

            return false;
        }

        $errors = $this->http->FindNodes("//label[@class='error']");

        if (!empty($errors)) {
            $msg = '';

            foreach ($errors as $error) {
                $msg .= $error . "\n";
            }

            throw new \UserInputError($msg); // Is it always user input error?
        }

        if ($error = $this->http->FindSingleNode("//div[@id='MainContent_ctl00_validationSummary']/ul")) {
            throw new \UserInputError($error);
        } // Is it always user input error?

        $success = $this->http->FindNodes("//div[@class='membershipNumber']");

        if (count($success) === 2) {
            $this->ErrorMessage = 'Your membership number is ' . $success[0];
            $this->http->log($this->ErrorMessage);

            return true;
        }

        return false;
    }

    public function registerAccountSelenium(array $fields)
    {
        //		$fields['Phone'] = $this->getPhone($fields);
        $fields['CountryFull'] = self::$countries[$fields['Country']];
        $fields['PreferredLanguage'] = '21';
        $fields['BirthDay'] = sprintf("%02d", $fields['BirthDay']);

        $this->http->GetURL('https://www.emirates.com/account/english/light-registration/index.aspx?showinterim=true');

        if ($elem = $this->waitForElement(\WebDriverBy::id('RedirectionOverlay_btnOK'), $this->loadTimeout)) {
            $this->driver->executeScript("$('#RedirectionOverlay_btnOK').click();");
            //			$elem->click();
        }

        if ($fields['BirthYear'] > 1997) {
            throw new \UserInputError('You must be of legal age!');
        }

        $mapping = [
            'Title'     => 'ddlTitle',
            'FirstName' => 'txtFirstName',
            'LastName'  => 'txtFamilyName',
            'BirthDay'  => 'ddlDates',
            //			'BirthMonth' => 'ddlMonth',
            'BirthYear' => 'ddlYear',
            'Email'     => 'txtEmailAddress',
            //			'CountryFull' => 'ddlResidence-suggest',
            //			'PreferredLanguage' => 'ddlPreferredLanguage',
            'PhoneCountryCodeAlphabetic' => 'mobileTelephoneNumber_ddlCountryCode',
            //			'PhoneLocalNumber' => 'mobileTelephoneNumber_txtTelephone',
            'Password' => ['txtSetNewPassword', 'txtConfirmNewPwd'],
        ];

        $boxes = [
            //			'Title' => 'ddlTitle',
            //			'BirthDay' => 'ddlDates',
            'BirthMonth' => 'ddlMonth',
            //			'BirthYear' => 'ddlYear',
            //			'CountryFull' => 'ddlResidence-suggest',
            //			'PreferredLanguage' => 'ddlPreferredLanguage',
            //			'PhoneCountryCodeAlphabetic' => 'mobileTelephoneNumber_ddlCountryCode',
            //			'PhoneLocalNumber' => 'mobileTelephoneNumber_txtTelephone',
        ];

        foreach ($mapping as $awKey => $provKeys) {
            if (!isset($fields[$awKey])) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                $visible = $provKey === 'ddlResidence' ? false : true;

                if (!$elem = $this->waitForElement(\WebDriverBy::id($provKey), $this->loadTimeout, $visible)) {
                    throw new \EngineError("Can not find {$provKey} input");
                }

                $elem->click();
                $elem->sendKeys($fields[$awKey]);
            }
        }
        sleep(2);

        foreach ($boxes as $key => $id) {
            if (!$elem = $this->waitForElement(\WebDriverBy::id($id), $this->loadTimeout)) {
                throw new \EngineError("Can not find {$id} input");
            }

            $this->driver->executeScript(sprintf('document.getElementById(\'%s\').dispatchEvent(new FocusEvent(\'focus\', {view: window, cancelable: true}));', $id));
            $this->driver->executeScript("$('#{$id}').val('{$fields[$key]}');");
            $this->driver->executeScript(sprintf('document.getElementById(\'%s\').dispatchEvent(new FocusEvent(\'blur\', {view: window, cancelable: true}));', $id));
        }
        sleep(4);

        if (!$el = $this->waitForElement(\WebDriverBy::id('mobileTelephoneNumber_txtTelephone'), $this->loadTimeout)) {
            throw new \EngineError('Can not find: PhoneNumber');
        }
        $el->click();
        $el->sendKeys($fields['PhoneLocalNumber']);

        if (!$el = $this->waitForElement(\WebDriverBy::id('ddlPreferredLanguage'), $this->loadTimeout)) {
            throw new \EngineError("Can not find element: PreferredLanguage");
        }
        $this->executeJS('ddlPreferredLanguage', $fields['PreferredLanguage']);

        if (!$el = $this->waitForElement(\WebDriverBy::id('ddlResidence-suggest'), $this->loadTimeout)) {
            throw new \EngineError("Can not find element: Country");
        }
        $this->executeJS('ddlResidence-suggest', $fields['CountryFull']);

        if (!$elem = $this->waitForElement(\WebDriverBy::id('chkAgreemant'), $this->loadTimeout)) {
            throw new \EngineError('Can not find agree button');
        }
        $this->driver->executeScript("$('#chkAgreemant').click();");
        //		$elem->click();

        if (!$elem = $this->waitForElement(\WebDriverBy::id('MainContent_ctl00_btnjoin'), $this->loadTimeout)) {
            throw new \EngineError('Can not find submit button');
        }
        $this->driver->executeScript("$('#MainContent_ctl00_btnjoin').click();");
        //		$elem->click();

        if ($success = $this->waitForElement(\WebDriverBy::xpath("//span[@class='membershipNumber']"), $this->loadTimeout)) {
            $this->ErrorMessage = 'Your membership number is ' . $success->getText();
            $this->http->log($this->ErrorMessage);

            return true;
        }

        if ($error = $this->waitForElement(\WebDriverBy::xpath("//div[@id='MainContent_ctl00_validationSummary']/ul"), $this->loadTimeout)) {
            throw new \UserInputError($error->getText());
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Email' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Email ',
                    'Required' => true,
                ],
            'Title' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Title',
                    'Required' => true,
                    'Options'  => self::$titles,
                ],
            'FirstName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'FirstName',
                    'Required' => true,
                ],
            'LastName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'LastName',
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
                    'Caption'  => 'Year of Birth Date (you must be of legal age)',
                    'Required' => true,
                ],
            'Country' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Country Code of Residence',
                    'Required' => true,
                    'Options'  => self::$countries,
                ],

            'PhoneCountryCodeAlphabetic' => [
                'Type'     => 'string',
                'Caption'  => '2-letter Phone Country Code',
                'Required' => true,
                'Options'  => self::$phoneCountryCodes,
            ],
            //			'PreferredLanguage' => [
            //				'Type' => 'string',
            //				'Caption' => 'Preferred language',
            //				'Required' => true,
            //			],
            'PhoneAreaCode' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Area Code',
                'Required' => true,
            ],
            'PhoneLocalNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Number (without country code)',
                'Required' => true,
            ],

            'Password' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Password (should contain a minimum of seven characters and contains 1 number and one lower case letter)',
                    'Required' => true,
                ],
        ];
    }

    private function getPhone($fields)
    {
        return sprintf('%s%s', $fields['PhoneAreaCode'], $fields['PhoneLocalNumber']);
    }

    private function executeJS($id, $fieldName)
    {
        $this->driver->executeScript("document.getElementById('" . $id . "').dispatchEvent(new FocusEvent('focus', {view: window, cancelable: true}));");
        $this->driver->executeScript("$('#" . $id . "').val('" . $fieldName . "');");
        $this->driver->executeScript("document.getElementById('" . $id . "').dispatchEvent(new FocusEvent('blur', {view: window, cancelable: true}));");
    }
}
