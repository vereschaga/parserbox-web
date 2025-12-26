<?php

namespace AwardWallet\Engine\marriott\Transfer;

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class Purchase extends \TAccountChecker
{
    use \PointsDotComSeleniumHelper;
    use ProxyList;

    protected $ccTypes = [
        'amex' => 'American Express',
        'visa' => 'VISA',
    ];

    protected $timeout = 10;

    /*
     test creds

        "Email": "tohobagi@mailzi.ru",
        "FirstName": "John",
        "LastName": "Doe",
        "AccountNumber": "146139359",

     */

    protected static $countries = [
        'US' => 'United States of America',
        'CA' => 'Canada',
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AG' => 'Antigua and Barbuda',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'AZ' => 'Azerbaijan',
        'BS' => 'Bahamas',
        'BH' => 'Bahrain',
        'BD' => 'Bangladesh',
        'BB' => 'Barbados',
        'BY' => 'Belarus',
        'BE' => 'Belgium',
        'BZ' => 'Belize',
        'BJ' => 'Benin',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan',
        'BO' => 'Bolivia',
        'BA' => 'Bosnia Hercegovina',
        'BW' => 'Botswana',
        'BR' => 'Brazil',
        'IO' => 'British Indian Ocean Territory',
        'BN' => 'Brunei Darussalam',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CV' => 'Cape Verde',
        'KY' => 'Cayman Islands',
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Island (Indian Ocean)',
        'CC' => 'Cocos (Keeling) Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'CI' => 'Cote d\'Ivoire',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'TP' => 'East Timor',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FK' => 'Falkland Islands',
        'FO' => 'Faroe Islands',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
        'GF' => 'French Guiana',
        'PF' => 'French Polynesia',
        'TF' => 'French Southern Territories',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GR' => 'Greece',
        'GL' => 'Greenland',
        'GD' => 'Grenada',
        'GP' => 'Guadeloupe',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard and Mc Donald Islands',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong, SAR',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran (Islamic Republic Of)',
        'IQ' => 'Iraq',
        'IE' => 'Ireland',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KP' => 'Korea, Democratic People\'s Republic',
        'KR' => 'Korea, Republic Of',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Lao People\'s Democratic Republic',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libyan Arab Jamahiriya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macau, SAR',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MH' => 'Marshall Islands',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'MD' => 'Moldova, Republic Of',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'MS' => 'Montserrat',
        'MA' => 'Morocco',
        'MZ' => 'Mozambique',
        'MM' => 'Myanmar',
        'NA' => 'Namibia',
        'NR' => 'Nauru',
        'NP' => 'Nepal',
        'NL' => 'Netherlands',
        'AN' => 'Netherlands Antilles',
        'NC' => 'New Caledonia',
        'NZ' => 'New Zealand',
        'NI' => 'Nicaragua',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'NU' => 'Niue',
        'NF' => 'Norfolk Island',
        'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PN' => 'Pitcairn',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'GS' => 'S Georgia and S Sandwich Island',
        'KN' => 'Saint Kitts And Nevis',
        'LC' => 'Saint Lucia',
        'VC' => 'Saint Vincent And The Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome And Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'ES' => 'Spain And Canary Islands',
        'LK' => 'Sri Lanka',
        'SH' => 'St. Helena',
        'PM' => 'St. Pierre And Miquelon',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard And Jan Mayen Islands',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Republic',
        'TJ' => 'Tajikistan',
        'TW' => 'Taiwan',
        'TZ' => 'Tanzania, United Republic',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad And Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks And Caicos Islands',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'UM' => 'United States Minor Outlying Islands',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City State (Holy See)',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'VG' => 'Virgin Islands, (British)',
        'VI' => 'Virgin Islands, (U.S.)',
        'WF' => 'Wallis And Futuna Islands',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen, Republic Of',
        'ZR' => 'Zaire',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'CD' => 'Democratic Republic of Congo',
        'MK' => 'Macedonia',
        'PS' => 'Occupied Palestinian Territory',
        'BL' => 'St Barthelemy',
        'RS' => 'Serbia',
        'ME' => 'Montenegro',
    ];

    public function InitBrowser()
    {
        $this->UseSelenium();
        $this->useChromium();

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyPurchase());
        } elseif (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA));
            //			$this->setProxyBrightData();
        }
        $this->http->saveScreenshots = true;

        $this->keepCookies(false);
        $this->keepSession(false);
        // $this->http->saveScreenshots = true;
        $this->AccountFields['BrowserState'] = null;
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function getPurchaseMilesFields()
    {
        return [
            "Email" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Email",
            ],
            "FirstName" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "First Name",
            ],
            "LastName" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Last Name",
            ],
            "AccountNumber" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Marriott Rewards Number",
            ],
        ];
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $this->logger->notice(__METHOD__);
        $this->driver->manage()->window()->maximize();

        $numberOfMiles = intval($numberOfMiles);

        if ($numberOfMiles <= 0 || $numberOfMiles > 50000 || $numberOfMiles % 1000 !== 0) {
            throw new \UserInputError("Number of purchased points should be lesser then 50,000 and divisible by 1000");
        }

        $this->http->GetURL('https://storefront.points.com/marriott-rewards/en-US/buy');

        if (!$this->stepCheckMiles($numberOfMiles)) {
            $this->logger->info('Set miles step failed');

            return false;
        }

        if (!$this->stepDetails($fields)) {
            $this->logger->info('Details step failed');

            return false;
        }
        $creditCard['Email'] = $fields['Email'];

        if (!$this->stepPayment($numberOfMiles, $creditCard)) {
            $this->logger->info('Payment step failed');

            return false;
        }

        return true;
    }

    protected function stepDetails($fields)
    {
        $this->logger->notice(__METHOD__);

        $inputMap = [
            'FirstName'     => 'firstName',
            'LastName'      => 'lastName',
            'AccountNumber' => 'memberId',
            'Email'         => 'email',
        ];

        foreach ($inputMap as $awKey => $inputKey) {
            $elem = $this->waitForElement(\WebDriverBy::id($inputKey), $this->timeout);

            if (!$elem) {
                return false;
            }
            $elem->clear();
            $elem->sendKeys($fields[$awKey]);
        }

        $button = $this->waitForElement(\WebDriverBy::xpath(
            '//div[contains(@class, "bgt-login-submit")]/button'
        ), $this->timeout);

        if (!$button) {
            return false;
        }

        $button->click();
        $pointsSelector = $this->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->timeout);
        $this->SaveResponse();

        if ($pointsSelector) {
            return true;
        }
        $this->checkErrors();

        return false;
    }

    protected function checkErrors($aftertry = false)
    {
        $this->logger->info(__METHOD__);
        $this->saveResponse();
        $errors = $this->http->FindNodes(
            '//div[contains(@class, "bgt-form-error") and not(contains(@class, "bgt-form-errors")) and normalize-space(.) != "" and normalize-space(.) != "The information above is invalid."]'
        );

        if ($errors) {
            $mes = implode(';', $errors);

            if ($aftertry) {
                //				if (stripos($mes, 'credit card') === false)
                //					throw new \UserInputError($mes);
                //				else
                $this->logger->error($mes);
            } else {
                throw new \UserInputError($mes);
            }
        }
    }

    protected function adjustCreditCard($creditCard)
    {
        $this->logger->info(__METHOD__);

        if ($creditCard['Type'] === 'amex') {
            $creditCard['Type'] = 'string:transaction.payment.form.creditCard.amex';
        } elseif ($creditCard['Type'] === 'visa') {
            $creditCard['Type'] = 'string:transaction.payment.form.creditCard.visa';
        }
        $creditCard['ExpirationMonth'] = sprintf('number:%s', intval($creditCard['ExpirationMonth']));
        $creditCard['ExpirationYear'] = sprintf('number:%s', (strlen($creditCard['ExpirationYear']) === 2 ? '20' . $creditCard['ExpirationYear'] : $creditCard['ExpirationYear']));

        $creditCard['CountryCode'] = sprintf('string:%s', $creditCard['CountryCode']);
        $creditCard['StateCode'] = sprintf('string:%s', $creditCard['StateCode']);

        return $creditCard;
    }

    protected function stepCheckMiles($numberOfMiles)
    {
        $this->logger->info(__METHOD__);

        $pointsSelector = $this->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->timeout);

        if (!$pointsSelector) {
            return false;
        }
        $pointsSelector = new \WebDriverSelect($pointsSelector);
        $pointsSelector->selectByValue($numberOfMiles);

        $firstName = $this->waitForElement(\WebDriverBy::id('firstName'), $this->timeout);
        $this->saveResponse();

        if (!$firstName) {
            return false;
        }

        return true;
    }

    protected function stepPayment($numberOfMiles, $creditCard)
    {
        $this->logger->info(__METHOD__);
        $creditCard = $this->adjustCreditCard($creditCard);

        //stepCheckMiles

        $inputMap = [
            "Type"            => "cardName",
            "CardNumber"      => "cardNumber",
            "SecurityNumber"  => "securityCode",
            "ExpirationMonth" => "expirationMonth",
            "ExpirationYear"  => "expirationYear",
            "Name"            => "creditCardFullName",
            "AddressLine"     => "street1",
            "City"            => "city",
            "CountryCode"     => "country",
            "StateCode"       => "state",
            "PhoneNumber"     => "phone",
            "Zip"             => "zip",
            "Email"           => "billingEmail",
        ];

        foreach ($inputMap as $awKey => $inputKey) {
            $elem = $this->waitForElement(\WebDriverBy::id($inputKey), $this->timeout);

            if (!$elem) {
                return false;
            }
            $value = $creditCard[$awKey];
            $tag = $elem->getTagName();

            if ($tag === 'select') {
                $select = new \WebDriverSelect($elem);
                $select->selectByValue($value);
            } else {
                $elem->clear();
                $elem->sendKeys($value);
            }
        }

        $terms = $this->waitForElement(\WebDriverBy::id('termsAndConditions'), $this->timeout);
        $terms->click();
        $pay = $this->waitForElement(\WebDriverBy::cssSelector('.bgt-order-submit'), $this->timeout);
        $this->saveResponse();
        $pay->click();

        sleep(30);
        $success = $this->waitForElement(\WebDriverBy::xpath(
            '//p[contains(@class, "bgt-receipt-header-completed") or contains(@class, "bgt-receipt-header-pending")]'
        ), $this->timeout);
        $this->saveResponse();

        if ($success) {
            $this->http->Log('success');
            $this->ErrorMessage = CleanXMLValue($success->getText());

            return true;
        }
        $this->checkErrors(true);

        return false;
    }
}
