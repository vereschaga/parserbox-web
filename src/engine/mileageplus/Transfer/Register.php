<?php

namespace AwardWallet\Engine\mileageplus\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountCheckerMileageplus
{
    use ProxyList;

    public static $titles = [
        'MR'   => 'Mr.',
        'MRS'  => 'Mrs.',
        'MS'   => 'Ms.',
        'DR'   => 'Dr.',
        'MSTR' => 'Mstr.',
        'MISS' => 'Miss',
        'PROF' => 'Prof.',
        'REV'  => 'Rev.',
        'SIR'  => 'Sir',
        'SIST' => 'Sister',
    ];

    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $addressTypes = [
        'H' => 'Home',
        'B' => 'Business',
    ];

    public static $countries = [
        "US" => "United States",
        "CA" => "Canada",
        "GB" => "United Kingdom",
        "AG" => "Antigua and Barbuda",
        "AR" => "Argentina",
        "AW" => "Aruba",
        "AU" => "Australia",
        "BS" => "Bahamas",
        "BH" => "Bahrain",
        "BB" => "Barbados",
        "BE" => "Belgium",
        "BZ" => "Belize",
        "BM" => "Bermuda",
        "BR" => "Brazil",
        "KY" => "Cayman Islands",
        "CL" => "Chile",
        "CN" => "China",
        "CO" => "Colombia",
        "CR" => "Costa Rica",
        "DK" => "Denmark",
        "DO" => "Dominican Republic",
        "EC" => "Ecuador",
        "SV" => "El Salvador",
        "FR" => "France",
        "DE" => "Germany",
        "GR" => "Greece",
        "GU" => "Guam",
        "GT" => "Guatemala",
        "HN" => "Honduras",
        "HK" => "Hong Kong",
        "IN" => "India",
        "ID" => "Indonesia",
        "IE" => "Ireland",
        "IL" => "Israel",
        "IT" => "Italy",
        "JM" => "Jamaica",
        "JP" => "Japan",
        "KR" => "Korea, South",
        "KW" => "Kuwait",
        "MH" => "Marshall Islands",
        "MX" => "Mexico",
        "FM" => "Micronesia",
        "NL" => "Netherlands",
        "AN" => "Netherlands Antilles",
        "NZ" => "New Zealand",
        "NI" => "Nicaragua",
        "MP" => "Northern Mariana Islands",
        "NO" => "Norway",
        "PW" => "Palau",
        "PA" => "Panama",
        "PE" => "Peru",
        "PH" => "Philippines",
        "PT" => "Portugal",
        "PR" => "Puerto Rico",
        "QA" => "Qatar",
        "RU" => "Russia",
        "SG" => "Singapore",
        "ES" => "Spain &amp; Canary Islands",
        "SE" => "Sweden",
        "CH" => "Switzerland",
        "TW" => "Taiwan",
        "TH" => "Thailand",
        "TT" => "Trinidad and Tobago",
        "TR" => "Turkey",
        "AE" => "United Arab Emirates",
        "VN" => "Vietnam",
    ];

    public static $inputFieldsMap = [
        'Title'                      => 'Title',
        'FirstName'                  => 'FirstName',
        'LastName'                   => 'LastName',
        'Gender'                     => 'Gender',
        'BirthDay'                   => 'DOB.BirthDay',
        'BirthMonth'                 => 'DOB.BirthMonth',
        'BirthYear'                  => 'DOB.BirthYear',
        'AddressType'                => 'AddressChannelTypeCode',
        'AddressLine1'               => 'AddressLine1',
        'City'                       => 'City',
        'StateOrProvince'            => ['StateCode', 'StateCodeRequired'],
        'PostalCode'                 => ["PostalCode", "PostalCodeRequired"],
        'Country'                    => 'AddressCountryCode',
        'PhoneCountryCodeAlphabetic' => ['HomePhoneCountryCode', 'BusinessCountryCode'],
        'Email'                      => ['EmailAddress', 'EmailAddressConfirm'],
        'PIN'                        => ['Pin', 'ReenterPin'],
        'Password'                   => ['NewPassword', 'NewPasswordConfirm'],
    ];

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyDOP());
        } else {
            $this->http->SetProxy($this->proxyPurchase());
        }
        //			$this->http->SetProxy('localhost:8000');

        $this->http->GetURL('https://www.united.com/ual/en/us/account/enroll/default');
        $status = $this->http->ParseForm('enrollProfileForm');

        if (!$status) {
            throw new \ProviderError('Failed to parse create account form');
        }

        // finding base64 keys for questions and answers
        //		$questionsHard = [
        //			'What color was the home you grew up in?' => 1,
        //			'What is your favorite sport?' => 2,
        //			'What is your favorite type of music?' => 3,
        //			'What was your least favorite subject in school?' => 4,
        //			'What is your favorite cold-weather activity?' => 5,
        //		];
        //		$questionsResult = [];
        //		if ($questionsData = $this->http->FindSingleNode("//div[@id='divAnswers']/@data-answers-data")) {
        //			$questionsData = json_decode($questionsData, true);
        //			foreach($questionsData as $questionsItem){
        //				if(!array_key_exists($questionsItem['QuestionText'], $questionsHard))
        //					continue;
//
        //				$qNumber = $questionsHard[$questionsItem['QuestionText']];
        //				$answerKey = null;
        //				foreach($questionsItem['Answers'] as $answerItem)
        //					if($answerItem['AnswerText'] === $fields['SecurityQuestionAnswer'.$qNumber])
        //						$answerKey = $answerItem['AnswerKey'];
//
        //				if(!isset($answerKey))
        //					throw new \ProviderError('Unknown answer for "'.$questionsItem['QuestionText'].'"');
//
        //				$questionsResult[] = [
        //					'QuestionKey' => $questionsItem['QuestionKey'],
        //					'AnswerKey' => $answerKey
        //				];
        //			}
        //		} else {
        //			throw new \ProviderError('There is no security questions-answers json');
        //		}
//
        //		if(count($questionsResult) !== 5)
        //			throw new \ProviderError('Only '.count($questionsResult).' questions were found');

        $data = [
            "MiddleName"               => "",
            "Suffix"                   => "",
            "AddressLine2"             => "",
            "AddressLine3"             => "",
            "PhoneNumber"              => $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'],
            "ExtensionNumber"          => "",
            "BusinessPhoneNumber"      => "",
            "BusinessExtensionNumber"  => "",
            "HomeAirport"              => "",
            "SubscriptionNewsAndDeals" => "false",
            "SubscriptionMPPartner"    => "false",
            "SubscriptionMPProgram"    => "false",
            "SubscriptionMPStatement"  => "false",
            "Username"                 => "",
            "Questions[0].QuestionKey" => "xyz",
            "Questions[0].AnswerKey"   => "xyz",
            "Questions[1].QuestionKey" => "xyz",
            "Questions[1].AnswerKey"   => "xyz",
            "Questions[2].QuestionKey" => "xyz",
            "Questions[2].AnswerKey"   => "xyz",
            "Questions[3].QuestionKey" => "xyz",
            "Questions[3].AnswerKey"   => "xyz",
            "Questions[4].QuestionKey" => "xyz",
            "Questions[4].AnswerKey"   => "xyz",
            "hdnHomeUrl"               => "/ual/",
        ];

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

        foreach ($data as $key => $val) {
            $this->http->SetInputValue($key, $val);
        }

        $status = $this->http->PostForm();

        if (!$status) {
            throw new \ProviderError('Failed to post create account form');
        }

        if ($successMessage = $this->http->FindSingleNode("//div[@class='mp-number']")) {
            $this->ErrorMessage = 'Success! Account number: ' . $successMessage . '. You will also receive an enrollment confirmation email.';
            $this->http->log($successMessage);

            return true;
        }

        if ($errorMessage = $this->http->FindSingleNode("//div[contains(@class,'validation-summary-errors')]//li[1]")) {
            throw new \UserInputError($errorMessage);
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Title' =>
            [
                'Type'     => 'string ',
                'Caption'  => '',
                'Required' => true,
                'Options'  => self::$titles,
            ],
            'FirstName' =>
            [
                'Type'     => 'string ',
                'Caption'  => '',
                'Required' => true,
            ],
            'LastName' =>
            [
                'Type'     => 'string ',
                'Caption'  => '',
                'Required' => true,
            ],
            'Gender' =>
            [
                'Type'     => 'string ',
                'Caption'  => '',
                'Required' => true,
                'Options'  => self::$genders,
            ],
            'BirthMonth' =>
            [
                'Type'     => 'integer',
                'Caption'  => '',
                'Required' => true,
            ],
            'BirthDay' =>
            [
                'Type'     => 'string',
                'Caption'  => '',
                'Required' => true,
            ],
            'BirthYear' =>
            [
                'Type'     => 'string',
                'Caption'  => '',
                'Required' => true,
            ],
            'AddressType' =>
            [
                'Type'     => 'string',
                'Caption'  => '',
                'Required' => true,
                'Options'  => self::$addressTypes,
            ],
            'AddressLine1' =>
            [
                'Type'     => 'string',
                'Caption'  => '',
                'Required' => true,
            ],
            'City' =>
            [
                'Type'     => 'string',
                'Caption'  => '',
                'Required' => true,
            ],
            'StateOrProvince' =>
            [
                'Type'     => 'string',
                'Caption'  => '',
                'Required' => true,
            ],
            'PostalCode' =>
            [
                'Type'     => 'string',
                'Caption'  => '',
                'Required' => true,
            ],
            'Country' =>
            [
                'Type'     => 'string',
                'Caption'  => '',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'PhoneCountryCodeAlphabetic' =>
            [
                'Type'     => 'string',
                'Caption'  => '2-letter Phone Country Code ',
                'Required' => true,
                'Options'  => self::$countries,
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
            'Email' =>
            [
                'Type'     => 'string',
                'Caption'  => '',
                'Required' => true,
            ],
            'PIN' =>
            [
                'Type'     => 'string',
                'Caption'  => ' 4-digit PIN',
                'Required' => true,
            ],
            'Password' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Password (You are required to create an account password, which can be used in place of your PIN. Your password must be at least eight characters in length (maximum 32	characters) and contain at least one letter and one number. Standard	special characters (such as “!” “&” and “+”) are allowed.)',
                'Required' => true,
            ],
        ];
    }
}
