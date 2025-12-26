<?php

namespace AwardWallet\Engine\hbc\Transfer;

class Register extends \TAccountChecker
{
    public static $states = [
        // Canada
        'AB' => 'Alberta',
        'BC' => 'British Columbia',
        'MB' => 'Manitoba',
        'NB' => 'New Brunswick',
        'NL' => 'Newfoundland and Labrador',
        'NT' => 'Northwest Territories',
        'NS' => 'Nova Scotia',
        'NU' => 'Nunavut',
        'ON' => 'Ontario',
        'PE' => 'Prince Edward Island',
        'QC' => 'Québec',
        'SK' => 'Saskatchewan',
        'YT' => 'Yukon',
        // USA
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AS' => 'American Samoa',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'AE' => 'Armed Forces',
        'AA' => 'Armed Forces Americas',
        'AP' => 'Armed Forces Pacific',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'DC' => 'District of Columbia',
        'FM' => 'Federated States of Micronesia',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'GU' => 'Guam',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
        'MH' => 'Marshall Islands',
        'MD' => 'Maryland',
        'MA' => 'Massachusetts',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MS' => 'Mississippi',
        'MO' => 'Missouri',
        'MT' => 'Montana',
        'NE' => 'Nebraska',
        'NV' => 'Nevada',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NY' => 'New York',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'MP' => 'Northern Mariana Islands',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PW' => 'Palau',
        'PA' => 'Pennsylvania',
        'PR' => 'Puerto Rico',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VI' => 'Virgin Islands',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
    ];

    public static $phoneTypes = [
        'H' => 'Home',
        'B' => 'Business',
        'M' => 'Mobile',
    ];

    public static $preferredLanguages = [
        'en' => 'English',
        'fr' => 'French',
    ];

    public static $inputFieldsMap = [
        'FirstName'         => 'Ecom_BillTo_Postal_Name_First',
        'LastName'          => 'Ecom_BillTo_Postal_Name_Last',
        'AddressLine1'      => 'Ecom_BillTo_Postal_Street_Line2',
        'AddressLine2'      => 'Ecom_BillTo_Postal_Street_Line3',
        'City'              => 'Ecom_BillTo_Postal_City',
        'StateOrProvince'   => 'Ecom_BillTo_Postal_StateProv',
        'PostalCode'        => 'Ecom_BillTo_Postal_PostalCode',
        'PhoneLocalNumber'  => 'Ecom_BillTo_Telecom_Phone_Number',
        'BirthMonth'        => 'cboMonth',
        'BirthDay'          => 'cboDay',
        'BirthYear'         => 'cboYear',
        'PreferredLanguage' => 'Ecom_BillTo_Language',
        'Email'             => 'Ecom_BillTo_Online_Email',
    ];

    protected $languageMap = [
        'en' => 'EN',
        'fr' => 'FR',
    ];

    public function InitBrowser()
    {
        $this->UseCurlBrowser();
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;
        $this->http->FilterHTML = false;
        $this->http->removeCookies();
    }

    public function registerAccount(array $fields)
    {
        $fields['PreferredLanguage'] = $this->languageMap[$fields['PreferredLanguage']];
        $this->http->GetURL('https://www.hbc.com/HBCREWARDS/program/enroll/default.asp');
        $status = $this->http->ParseForm('form1');

        if (!$status) {
            $this->http->Log('Failed to parse create account form');

            return false;
        }

        $fields['PhoneLocalNumber'] = $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];

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

        unset($this->http->Form['type']);
        $addValues = [
            'cboPrivacy'      => 'ON',
            'cboEmailPrivacy' => 'ON',
            //            'Ecom_BillTo_Postal_Street_Line2' => substr($fields['AddressLine1'], 0, 9),
        ];

        foreach ($addValues as $key => $val) {
            $this->http->SetInputValue($key, $val);
        }

        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to post create account form');

            return false;
        }

        if ($successMessage = $this->http->FindSingleNode("//div[@id='Layer1']/span[@class='cardnumber']")) {
            $this->ErrorMessage = 'Your Hudson`s Bay Rewards Number is ' . $successMessage;
            $this->http->log($this->ErrorMessage);

            return true;
        }

//        $errors = $this->http->FindNodes("//font[@color='#FF0000' and contains(text(),'Provide')]");
        preg_match_all('/\<font\s+color\s*=\s*"\#FF0000"[^>]+>([^<]+)<\/font/ims', $this->http->Response['body'], $matches);
        $errors1 = $matches[1];
        $errors2 = $this->http->FindNodes("//font[@color='RED']/b");
        $errors = !empty($errors1) ? $errors1 : $errors2;

        if (!empty($errors)) {
            $msg = "";

            foreach ($errors as $error) {
                $msg .= $error . "\n";
            }

            throw new \UserInputError($msg); // Is it always user input error?
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
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
            'AddressLine1' =>
            [
                'Type'     => 'string',
                'Caption'  => ' Street Number',
                'Required' => true,
            ],
            'AddressLine2' =>
            [
                'Type'     => 'string',
                'Caption'  => ' Street Name',
                'Required' => true,
            ],
            'City' =>
            [
                'Type'     => 'string',
                'Caption'  => 'City',
                'Required' => true,
            ],
            'StateOrProvince' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Province (Hudson’s Bay Rewards delivers in Canada only)',
                'Required' => true,
                'Options'  => self::$states,
            ],
            'PostalCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Postal',
                'Required' => true,
            ],
            'PhoneType' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Phone Type',
                'Required' => true,
                'Options'  => self::$phoneTypes,
            ],
            //			'PhoneCountryCodeNumeric' =>
            //			array (
            //				'Type' => 'string',
            //				'Caption' => '1-3-number Phone Country Code',
            //				'Required' => true,
            //			),
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
            'BirthMonth' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Month of Birth Date',
                'Required' => true,
            ],
            'BirthDay' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Day of Birth Date',
                'Required' => true,
            ],
            'BirthYear' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Year of Birth Date',
                'Required' => true,
            ],
            'PreferredLanguage' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Language',
                'Required' => true,
                'Options'  => self::$preferredLanguages,
            ],
            'Email' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
        ];
    }
}
