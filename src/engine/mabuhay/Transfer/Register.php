<?php

namespace AwardWallet\Engine\mabuhay\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    public $timeout = 20;

    public static $titles = [
        'Mr' => 'MR',
        'Ms' => 'MS',
    ];

    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $countries = [
        "af" => "Afghanistan (&#8235;افغانستان&#8236;&lrm;)",
        "al" => "Albania (Shqipëri)",
        "dz" => "Algeria (&#8235;الجزائر&#8236;&lrm;)",
        "as" => "American Samoa",
        "ad" => "Andorra",
        "ao" => "Angola",
        "ai" => "Anguilla",
        "ag" => "Antigua and Barbuda",
        "ar" => "Argentina",
        "am" => "Armenia (Հայաստան)",
        "aw" => "Aruba",
        "au" => "Australia",
        "at" => "Austria (Österreich)",
        "az" => "Azerbaijan (Azərbaycan)",
        "bs" => "Bahamas",
        "bh" => "Bahrain (&#8235;البحرين&#8236;&lrm;)",
        "bd" => "Bangladesh (বাংলাদেশ)",
        "bb" => "Barbados",
        "by" => "Belarus (Беларусь)",
        "be" => "Belgium (België)",
        "bz" => "Belize",
        "bj" => "Benin (Bénin)",
        "bm" => "Bermuda",
        "bt" => "Bhutan (འབྲུག)",
        "bo" => "Bolivia",
        "ba" => "Bosnia and Herzegovina (Босна и Херцеговина)",
        "bw" => "Botswana",
        "br" => "Brazil (Brasil)",
        "io" => "British Indian Ocean Territory",
        "vg" => "British Virgin Islands",
        "bn" => "Brunei",
        "bg" => "Bulgaria (България)",
        "bf" => "Burkina Faso",
        "bi" => "Burundi (Uburundi)",
        "kh" => "Cambodia (កម្ពុជា)",
        "cm" => "Cameroon (Cameroun)",
        "ca" => "Canada",
        "cv" => "Cape Verde (Kabu Verdi)",
        "bq" => "Caribbean Netherlands",
        "ky" => "Cayman Islands",
        "cf" => "Central African Republic (République centrafricaine)",
        "td" => "Chad (Tchad)",
        "cl" => "Chile",
        "cn" => "China (中国)",
        "co" => "Colombia",
        "km" => "Comoros (&#8235;جزر القمر&#8236;&lrm;)",
        "cd" => "Congo (DRC) (Jamhuri ya Kidemokrasia ya Kongo)",
        "cg" => "Congo (Republic) (Congo-Brazzaville)",
        "ck" => "Cook Islands",
        "cr" => "Costa Rica",
        "ci" => "Côte d’Ivoire",
        "hr" => "Croatia (Hrvatska)",
        "cu" => "Cuba",
        "cw" => "Curaçao",
        "cy" => "Cyprus (Κύπρος)",
        "cz" => "Czech Republic (Česká republika)",
        "dk" => "Denmark (Danmark)",
        "dj" => "Djibouti",
        "dm" => "Dominica",
        "do" => "Dominican Republic (República Dominicana)",
        "ec" => "Ecuador",
        "eg" => "Egypt (&#8235;مصر&#8236;&lrm;)",
        "sv" => "El Salvador",
        "gq" => "Equatorial Guinea (Guinea Ecuatorial)",
        "er" => "Eritrea",
        "ee" => "Estonia (Eesti)",
        "et" => "Ethiopia",
        "fk" => "Falkland Islands (Islas Malvinas)",
        "fo" => "Faroe Islands (Føroyar)",
        "fj" => "Fiji",
        "fi" => "Finland (Suomi)",
        "fr" => "France",
        "gf" => "French Guiana (Guyane française)",
        "pf" => "French Polynesia (Polynésie française)",
        "ga" => "Gabon",
        "gm" => "Gambia",
        "ge" => "Georgia (საქართველო)",
        "de" => "Germany (Deutschland)",
        "gh" => "Ghana (Gaana)",
        "gi" => "Gibraltar",
        "gr" => "Greece (Ελλάδα)",
        "gl" => "Greenland (Kalaallit Nunaat)",
        "gd" => "Grenada",
        "gp" => "Guadeloupe",
        "gu" => "Guam",
        "gt" => "Guatemala",
        "gn" => "Guinea (Guinée)",
        "gw" => "Guinea-Bissau (Guiné Bissau)",
        "gy" => "Guyana",
        "ht" => "Haiti",
        "hn" => "Honduras",
        "hk" => "Hong Kong (香港)",
        "hu" => "Hungary (Magyarország)",
        "is" => "Iceland (Ísland)",
        "in" => "India (भारत)",
        "id" => "Indonesia",
        "ir" => "Iran (&#8235;ایران&#8236;&lrm;)",
        "iq" => "Iraq (&#8235;العراق&#8236;&lrm;)",
        "ie" => "Ireland",
        "il" => "Israel (&#8235;ישראל&#8236;&lrm;)",
        "it" => "Italy (Italia)",
        "jm" => "Jamaica",
        "jp" => "Japan (日本)",
        "jo" => "Jordan (&#8235;الأردن&#8236;&lrm;)",
        "kz" => "Kazakhstan (Казахстан)",
        "ke" => "Kenya",
        "ki" => "Kiribati",
        "kw" => "Kuwait (&#8235;الكويت&#8236;&lrm;)",
        "kg" => "Kyrgyzstan (Кыргызстан)",
        "la" => "Laos (ລາວ)",
        "lv" => "Latvia (Latvija)",
        "lb" => "Lebanon (&#8235;لبنان&#8236;&lrm;)",
        "ls" => "Lesotho",
        "lr" => "Liberia",
        "ly" => "Libya (&#8235;ليبيا&#8236;&lrm;)",
        "li" => "Liechtenstein",
        "lt" => "Lithuania (Lietuva)",
        "lu" => "Luxembourg",
        "mo" => "Macau (澳門)",
        "mk" => "Macedonia (FYROM) (Македонија)",
        "mg" => "Madagascar (Madagasikara)",
        "mw" => "Malawi",
        "my" => "Malaysia",
        "mv" => "Maldives",
        "ml" => "Mali",
        "mt" => "Malta",
        "mh" => "Marshall Islands",
        "mq" => "Martinique",
        "mr" => "Mauritania (&#8235;موريتانيا&#8236;&lrm;)",
        "mu" => "Mauritius (Moris)",
        "mx" => "Mexico (México)",
        "fm" => "Micronesia",
        "md" => "Moldova (Republica Moldova)",
        "mc" => "Monaco",
        "mn" => "Mongolia (Монгол)",
        "me" => "Montenegro (Crna Gora)",
        "ms" => "Montserrat",
        "ma" => "Morocco (&#8235;المغرب&#8236;&lrm;)",
        "mz" => "Mozambique (Moçambique)",
        "mm" => "Myanmar (Burma) (မြန်မာ)",
        "na" => "Namibia (Namibië)",
        "nr" => "Nauru",
        "np" => "Nepal (नेपाल)",
        "nl" => "Netherlands (Nederland)",
        "nc" => "New Caledonia (Nouvelle-Calédonie)",
        "nz" => "New Zealand",
        "ni" => "Nicaragua",
        "ne" => "Niger (Nijar)",
        "ng" => "Nigeria",
        "nu" => "Niue",
        "nf" => "Norfolk Island",
        "kp" => "North Korea (조선 민주주의 인민 공화국)",
        "mp" => "Northern Mariana Islands",
        "no" => "Norway (Norge)",
        "om" => "Oman (&#8235;عُمان&#8236;&lrm;)",
        "pk" => "Pakistan (&#8235;پاکستان&#8236;&lrm;)",
        "pw" => "Palau",
        "ps" => "Palestine (&#8235;فلسطين&#8236;&lrm;)",
        "pa" => "Panama (Panamá)",
        "pg" => "Papua New Guinea",
        "py" => "Paraguay",
        "pe" => "Peru (Perú)",
        "ph" => "Philippines",
        "pl" => "Poland (Polska)",
        "pt" => "Portugal",
        "pr" => "Puerto Rico",
        "qa" => "Qatar (&#8235;قطر&#8236;&lrm;)",
        "re" => "Réunion (La Réunion)",
        "ro" => "Romania (România)",
        "ru" => "Russia (Россия)",
        "rw" => "Rwanda",
        "bl" => "Saint Barthélemy (Saint-Barthélemy)",
        "sh" => "Saint Helena",
        "kn" => "Saint Kitts and Nevis",
        "lc" => "Saint Lucia",
        "mf" => "Saint Martin (Saint-Martin (partie française))",
        "pm" => "Saint Pierre and Miquelon (Saint-Pierre-et-Miquelon)",
        "vc" => "Saint Vincent and the Grenadines",
        "ws" => "Samoa",
        "sm" => "San Marino",
        "st" => "São Tomé and Príncipe (São Tomé e Príncipe)",
        "sa" => "Saudi Arabia (&#8235;المملكة العربية السعودية&#8236;&lrm;)",
        "sn" => "Senegal (Sénégal)",
        "rs" => "Serbia (Србија)",
        "sc" => "Seychelles",
        "sl" => "Sierra Leone",
        "sg" => "Singapore",
        "sx" => "Sint Maarten",
        "sk" => "Slovakia (Slovensko)",
        "si" => "Slovenia (Slovenija)",
        "sb" => "Solomon Islands",
        "so" => "Somalia (Soomaaliya)",
        "za" => "South Africa",
        "kr" => "South Korea (대한민국)",
        "ss" => "South Sudan (&#8235;جنوب السودان&#8236;&lrm;)",
        "es" => "Spain (España)",
        "lk" => "Sri Lanka (ශ්&zwj;රී ලංකාව)",
        "sd" => "Sudan (&#8235;السودان&#8236;&lrm;)",
        "sr" => "Suriname",
        "sz" => "Swaziland",
        "se" => "Sweden (Sverige)",
        "ch" => "Switzerland (Schweiz)",
        "sy" => "Syria (&#8235;سوريا&#8236;&lrm;)",
        "tw" => "Taiwan (台灣)",
        "tj" => "Tajikistan",
        "tz" => "Tanzania",
        "th" => "Thailand (ไทย)",
        "tl" => "Timor-Leste",
        "tg" => "Togo",
        "tk" => "Tokelau",
        "to" => "Tonga",
        "tt" => "Trinidad and Tobago",
        "tn" => "Tunisia (&#8235;تونس&#8236;&lrm;)",
        "tr" => "Turkey (Türkiye)",
        "tm" => "Turkmenistan",
        "tc" => "Turks and Caicos Islands",
        "tv" => "Tuvalu",
        "vi" => "U.S. Virgin Islands",
        "ug" => "Uganda",
        "ua" => "Ukraine (Україна)",
        "ae" => "United Arab Emirates (&#8235;الإمارات العربية المتحدة&#8236;&lrm;)",
        "gb" => "United Kingdom",
        "us" => "United States",
        "uy" => "Uruguay",
        "uz" => "Uzbekistan (Oʻzbekiston)",
        "vu" => "Vanuatu",
        "va" => "Vatican City (Città del Vaticano)",
        "ve" => "Venezuela",
        "vn" => "Vietnam (Việt Nam)",
        "wf" => "Wallis and Futuna",
        "ye" => "Yemen (&#8235;اليمن&#8236;&lrm;)",
        "zm" => "Zambia",
        "zw" => "Zimbabwe",
    ];

    public static $phoneTypes = [
        'H' => 'Home',
        'B' => 'Business',
        'M' => 'Mobile',
    ];

    public static $preferredWaysToReceiveMabuhayCorrespondence = [
        'Y' => 'Email',
        'N' => 'Post',
    ];

    public static $hintQuestionTypes = [
        '2' => 'What`s my mother`s maiden name',
        '3' => 'What`s my favorite color',
        '4' => 'What`s my pet`s name',
        '5' => 'What`s my favorite movie',
        '6' => 'What`s my favorite fruit',
        '7' => 'What`s my favorite song',
    ];

    public static $inputFieldsMap = [
        'Title'                   => 'title',
        'LastName'                => 'lastName',
        'FirstName'               => 'firstName',
        'Suffix'                  => 'suffix',
        'BirthDate'               => 'dateOfBirth',
        'MothersMaidenName'       => 'mothersMaidenName',
        'AddressType'             => 'preferredMailingAddress',
        'AddressLine1'            => 'numberStreet',
        'Country'                 => 'country', // поле Country должно выбираться перед полем City
        'City'                    => 'cityProvince',
        'PostalCode'              => 'postalZipCode',
        'PhoneType'               => 'preferredContact',
        'PhoneCountryCodeNumeric' => 'prefCountryCode',
        'PhoneAreaCode'           => 'prefAreaCode',
        'PhoneLocalNumber'        => 'prefContactNumber',
        'Email'                   => 'emailAddress',
        'Password'                => ['password', 'reenterPassword'],
        'HintQuestionType'        => 'hintQuestion',
        'HintQuestionAnswer'      => 'hintAnswer',
    ];

    public function InitBrowser()
    {
        $this->useSelenium();
        $this->useChromium();

        if (ConfigValue(CONFIG_SITE_STATE) !== SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyDOP());
        }
    }

    public function registerAccount(array $fields)
    {
        $this->http->GetURL('https://www.mabuhaymiles.com/Enrollment');

        $fields['Suffix'] = 'NONE';
        $fields['BirthDate'] = \DateTime::createFromFormat('!m', intval($fields['BirthMonth']))->format('m')
            . '/' . \DateTime::createFromFormat('!d', intval($fields['BirthDay']))->format('d')
            . '/' . $fields['BirthYear'];
        $types = [
            'H' => 'HOME',
            'B' => 'WORK',
            'M' => 'MOBIL',
        ];
        $fields['AddressType'] = $types['H']; // $types[$fields['AddressType']] проблемы с реализацией рабочего адреса
        $fields['PhoneType'] = $types[$fields['PhoneType']];

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $fields['PhoneCountryCodeNumeric'] = '7';
            $fields['PhoneAreaCode'] = '999';
            $fields['Password'] = substr($fields['Password'], 0, 5) . '_Yb';
        }

        $this->http->Log('Step 1: Enrollment...');

        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if (!isset($fields[$awKey]) || $provKeys === false) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                if (in_array($provKey, ['cityProvince'])) {
                    $this->driver->executeScript("
						var el = jQuery('#$provKey');
						if (el.find('option[value=\"' + arguments[0] + '\"]').length) {
							el.trigger('focus');
							el.val(arguments[0]);
							el.trigger('change');
						} else {
							el.trigger('focus');
							el.val('OTHERS');
							el.trigger('change');
							var el2 = jQuery('#cityProvinceName');
							el2.trigger('focus');
							el2.val(arguments[0]);
							el2.trigger('change');
						}
					", [$fields['City']]);

                    continue;
                }

                if (in_array($provKey, ['country'])) {
                    $this->driver->executeScript("
						var el = jQuery('#$provKey');
						el.find('input').click();
						el.find('div.item[data-value={$fields['Country']}]').click();
					");

                    continue;
                }

                if (in_array($provKey, ['prefCountryCode'])) {
                    $this->driver->executeScript("
						var el = jQuery('#$provKey').parent();
						el.find('div.selected-flag:eq(0)').click();
						el.find('li.country[data-dial-code={$fields['PhoneCountryCodeNumeric']}]:eq(0)').click();
					");

                    continue;
                }
                $elem = $this->waitForElement(\WebDriverBy::id($provKey), $this->timeout);
                $elem->sendKeys($fields[$awKey]);
                $this->driver->executeScript('
					var el = jQuery(arguments[0]);
					el.trigger("focus");
					el.val(arguments[1]);
					el.trigger("change");
				', [$elem, $fields[$awKey]]);
            }
        }

        if (!$button = $this->waitForElement(\WebDriverBy::id('reviewButton'), $this->timeout)) {
            throw new \ProviderError('No continue button!');
        }
        $button->click();

        // errors
        if ($error = $this->waitForElement(\WebDriverBy::xpath("//span[contains(@class,'error')][1]"), 10)) {
            throw new \UserInputError($error->getText());
        }

        $this->http->Log('Step 2: Summary Enrollment...');

        if (($img = $this->waitForElement(\WebDriverBy::id('EnrollmentCaptcha_CaptchaImage'), $this->timeout)) && ($input = $this->waitForElement(\WebDriverBy::id('EnrollmentCaptchaCode'), $this->timeout))) {
            $captcha = $this->parseCaptcha($img);
            $input->sendKeys($captcha);
        }

        if ($agree = $this->waitForElement(\WebDriverBy::id('termsOfAgreement'), $this->timeout)) {
            $agree->click();
        }

        if ($submit = $this->waitForElement(\WebDriverBy::id('summaryEnrollmentSubmitButton'), $this->timeout)) {
            $submit->click();
        }

        $this->http->Log('Step 3: You have submitted your enrollment in the Mabuhay Miles Program!');

        if ($success = $this->waitForElement(\WebDriverBy::xpath('//h3[contains(.,"To complete the process")]'), $this->timeout)) {
            $this->ErrorMessage = $success->getText();
            $this->http->Log($this->ErrorMessage);

            return true;
        }

        // errors
        if ($error = $this->waitForElement(\WebDriverBy::xpath('//span[contains(@class,"error")][1]'), 10)) {
            throw new \UserInputError($error->getText());
        } // Please enter correct code (CAPTCHA)

        return false;
    }

    public function old_registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;

        $this->http->GetURL('https://www.mabuhaymiles.com/home/enroll.jsp');
        $status = $this->http->ParseForm('enroll');

        if (!$status) {
            $this->http->Log('Failed to parse create account form');

            return false;
        }

        // Provider uses wrong country codes for:
        // - Zaire (ZR instead of standard CD)
        // - East Timor (TP instead of standard TL)
        // - Israel (IR instead of standard IL)
        // - Vanuatu (VT instead of standard VU)
        // Map from our standard ISO code to wrong code used by provider
        $wrongCountryCodesFixingMap = [
            'CD' => 'ZR',
            'TL' => 'TP',
            'IL' => 'IR',
            'VU' => 'VT',
        ];

        if (isset($wrongCountryCodesFixingMap[$fields['Country']])) {
            $origCountryCode = $fields['Country'];
            $fields['Country'] = $wrongCountryCodesFixingMap[$fields['Country']];
            $this->logger->debug('Mapped standard country code "' . $origCountryCode . '" to provider code "' . $fields['Country'] . '"');
        }

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

        $this->fillSpecFields($fields);

        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to post create account form');

            return false;
        }

        $status = $this->http->ParseForm('enroll3');

        if (!$status) {
            $this->http->Log('Failed to submit data');
            $this->parseErrors();

            return false;
        }
        $this->http->Log('Step 1 success!');
        $this->http->SetInputValue('formStatus', 'ready');
        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to post step 2 form');

            return false;
        }

        $status = $this->http->ParseForm('registration');

        if (!$status) {
            $this->http->Log('Step 2 Error');
            $this->parseErrors();

            return false;
        }

        if ($number = $this->http->FindPreg("/Membership Number:\s*([^<]+)</ims")) {
            $number = str_replace(' ', '', $number);
            $pin = $this->http->FindPreg("/IVR Pin:[^<]+/ims");

            return $this->createPasswordStep($number, $pin, $fields);
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
            'LastName' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Last Name (as shown in passport)',
                'Required' => true,
            ],
            'FirstName' =>
            [
                'Type'     => 'string',
                'Caption'  => 'First Name (as shown in passport)',
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
            'MothersMaidenName' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Mother\'s maiden name',
                'Required' => true,
            ],
            //			'AddressType' => array(
            //				'Type' => 'string',
            //				'Caption' => 'Preferred Mailing Address',
            //				'Required' => true,
            //				'Options' => ['H' => 'Home', 'B' => 'Business'],
            //			),
            'AddressLine1' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Address',
                'Required' => true,
            ],
            'City' =>
            [
                'Type'     => 'string',
                'Caption'  => 'City Code (3 chars) / Other Name',
                'Required' => true,
            ],
            'Country' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Country Code (2 chars)',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'PostalCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Postal Code',
                'Required' => true,
            ],
            'PhoneType' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Your Preferred Contact Number (at least one phone number is required)',
                'Required' => true,
                'Options'  => self::$phoneTypes,
            ],
            'PhoneCountryCodeNumeric' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Phone Country Code (1-3 numbers)',
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
                'Caption'  => 'Phone Local Number',
                'Required' => true,
            ],
            'Email' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'Password' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Password (5-8 characters; contain alpha-numeric & special character; upper and lower cases)',
                'Required' => true,
            ],
            'HintQuestionType' => [
                'Type'     => 'string',
                'Caption'  => 'Hint Question',
                'Required' => true,
                'Options'  => self::$hintQuestionTypes,
            ],
            'HintQuestionAnswer' => [
                'Type'     => 'string',
                'Caption'  => 'Hint Answer',
                'Required' => true,
            ],
        ];
    }

    protected function checkValues(array $fields)
    {
    }

    protected function parseCaptcha($img)
    {
        //		$forValidationCodeButton = $this->waitForElement(\WebDriverBy::xpath("//a[contains(text(), 'For validation code')]"), 30);
        //		if (!$forValidationCodeButton) {
        //			$this->logger->error('Failed to find "for validation code" button');
        //			return false;
        //		}
        //		$forValidationCodeButton->click();

        sleep(5);
        $captcha = $this->driver->executeScript("

		var captchaDiv = document.createElement('div');
		captchaDiv.id = 'captchaDiv';
		document.body.appendChild(captchaDiv);

		var canvas = document.createElement('CANVAS'),
			ctx = canvas.getContext('2d'),
			img = arguments[0];

		canvas.height = img.height;
		canvas.width = img.width;
		ctx.drawImage(img, 0, 0);
		dataURL = canvas.toDataURL('image/png');

		return dataURL;

		", [$img]);
        $this->http->Log('captcha: ' . $captcha);
        $marker = 'data:image/png;base64,';

        if (strpos($captcha, $marker) !== 0) {
            $this->http->Log('no marker');

            return false;
        }
        $captcha = substr($captcha, strlen($marker));
        $file = tempnam(sys_get_temp_dir(), 'captcha') . '.png';
        $this->http->Log('captcha file: ' . $file);
        file_put_contents($file, base64_decode($captcha));

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 100;
        $code = $this->recognizeCaptcha($recognizer, $file);
        unlink($file);

        return $code;
    }

    protected function setCustomSelectValue()
    {
    }

    protected function setInputValue()
    {
    }

    protected function fillSpecFields($fields)
    {
        $additionalValues = [
            'data' => [
                'pref_addr'     => 'home',
                'travel_coord'  => 'no',
                'butContinue.x' => '27',
                'butContinue.y' => '4',
                'recc_card_no'  => 'null',
                'partner'       => 'N',
                'todayYear'     => '97',
                'enrollSession' => 'no',
                'formStatus'    => 'ready',
                'form'          => '1',
            ],
            'empty' => ['addr1', 'addr2', 'comp_name', 'dept', 'fax_area_code',
                'fax_country_code', 'fax_number', 'guardian', 'middleName',
                'position', 'state', 'work_addr0', 'work_addr1', 'work_addr2',
                'work_city', 'work_postal_code', 'work_state', ],
        ];
        $phoneTypes = [
            'H' => [
                'value'  => 'home',
                'fields' => [
                    'PhoneCountryCodeNumeric' => 'home_country_code',
                    'PhoneAreaCode'           => 'home_area_code',
                    'PhoneLocalNumber'        => 'phone_number',
                ],
            ],
            'B' => [
                'value'  => 'work',
                'fields' => [
                    'PhoneCountryCodeNumeric' => 'work_country_code',
                    'PhoneAreaCode'           => 'work_area_code',
                    'PhoneLocalNumber'        => 'work_number',
                ],
            ],
            'M' => [
                'value'  => 'mobil',
                'fields' => [
                    'PhoneCountryCodeNumeric' => 'mobile_country_code',
                    'PhoneAreaCode'           => 'mobile_area_code',
                    'PhoneLocalNumber'        => 'mobile_number',
                ],
            ],
        ];
        $current = $phoneTypes[$fields['PhoneType']]['fields'];

        foreach ($current as $key => $row) {
            $additionalValues['data'][$row] = $fields[$key];
        }

        $additionalValues['data']['pref_contact'] = $phoneTypes[$fields['PhoneType']]['value'];
        unset($phoneTypes[$fields['PhoneType']]);

        foreach ($phoneTypes as $list) {
            foreach ($list['fields'] as $row) {
                $additionalValues['empty'][] = $row;
            }
        }

        foreach ($additionalValues['data'] as $row => $value) {
            $this->http->SetInputValue($row, $value);
        }

        foreach ($additionalValues['empty'] as $row) {
            $this->http->SetInputValue($row, '');
        }
    }

    protected function parseErrors()
    {
        if ($message = $this->http->FindSingleNode("//script[contains(text(), 'alert')]", null, true, "/alert\('(.*)'\)/ims")) {
            $message = str_replace('\n', '', $message);

            throw new \UserInputError($message); // Is it always user input error?
        }

        throw new \EngineError('Unknown error type');
    }

    protected function createPasswordStep($number, $pin, $fields)
    {
        $list = [
            'FirstName'          => 'firstName',
            'LastName'           => 'lastName',
            'BirthDay'           => 'day',
            'BirthMonth'         => 'month',
            'BirthYear'          => 'year',
            'Email'              => 'emailAddress',
            'Password'           => ['currentPassword', 'pin2'],
            'HintQuestionType'   => 'hintQuestion',
            'HintQuestionAnswer' => 'hintAnswer',
        ];

        $this->http->GetURL('https://www.mabuhaymiles.com/home/online_registration.jsp');
        $status = $this->http->ParseForm('Frmregister');

        if (!$status) {
            $this->http->Log('Failed to parse create password form');

            return false;
        }

        foreach ($list as $awKey => $provKeys) {
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
        $this->http->SetInputValue('membershipNumber', $number);

        $this->http->FormURL = 'https://www.mabuhaymiles.com/home/online_registration_ajax.jsp';
        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to post password form');

            return false;
        }

        if ($complete = $this->http->FindPreg("/To complete[^\.]+\./ims")) {
            $this->ErrorMessage = 'Success! ' . $number . '. ' . $pin . '. ' . $complete;
            $this->http->Log($this->ErrorMessage);

            return true;
        }

        if ($error = $this->http->FindPreg("/<message>([^<]+)<\/message>/ims")) {
            throw new \UserInputError($error);
        } // Is it always user input error?

        return false;
    }
}
