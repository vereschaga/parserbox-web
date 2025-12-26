<?php

namespace AwardWallet\Engine\marriott\Transfer;

class Transfer extends \TAccountCheckerMarriott
{
    public $idle = false;

    private $providersMap = [
        // Take from global airlines dict?
        'mileageplus'        => 'UA',
        'aeromexico'         => 'AM',
        'aeroplan'           => 'AC',
        'alaskaair'          => 'AS',
        'aa'                 => 'AA',
        'british'            => 'BA',
        'continental'        => '',
        'delta'              => 'DE',
        'frontierairlines'   => 'F9',
        'golair'             => '',
        'hawaiian'           => 'HA',
        'iberia'             => 'IB',
        'dividendmiles'      => 'US',
        'virgin'             => 'VS',
        'aeroflot'           => 'SU',
        'airberlin'          => 'AB',
        'airchina'           => 'CA',
        'airfrance'          => 'AF',
        'klm'                => 'KL',
        'alitalia'           => 'AZ',
        'ana'                => 'AN',
        'asia'               => 'CX',
        'asiana'             => 'OZ',
        'aviancataca'        => 'TA', // ?
        'chinaeastern'       => 'MU',
        'chinasouthern'      => 'CZ',
        'skywards'           => 'EK',
        'etihad'             => 'EY',
        'japanair'           => 'JL',
        'jetairways'         => '9W',
        'lanpass'            => 'LA',
        'lufthansa'          => 'LH',
        'qantas'             => 'QA',
        'qmiles'             => 'QR',
        'saudisrabianairlin' => 'SV',
        'singaporeair'       => 'SQ',
        'tapportugal'        => 'TP',
        'turkish'            => 'TK',
        'rapidrewards'       => 'WN',
        'jetblue'            => 'B6',
        'virginamerica'      => 'VX',
        'cosmohotels'        => 'IR',
        //		AI => AIR MILES
        //		HP => AMERICA WEST
        //		IT => KINGFISHER
        //		MX => MEXICANA AIRLINES
        //		NW => NORTHWEST AIRLINES
        //		SN => SN BRUSSELS AIRLINES
        //		LX => SWISSAIR
        //		RG => VARIG BRAZIL
    ];

    private $rewardCodes = [
        [
            'Providers' => [
                'mileageplus',
            ],
            'RewardTransferCodes' => [
                'UA1',
                'UA2',
                'UA3',
                'UA4',
                'UA5',
            ],
            'SourceQuantities' => [
                8000,
                16000,
                24000,
                56000,
                112000,
            ],
        ],
        [
            'Providers' => [
                'aeromexico',
                'aeroplan',
                'alaskaair',
                'aa',
                'british',
                'continental',
                'delta',
                'frontierairlines',
                'golair',
                'hawaiian',
                'iberia',
                'dividendmiles',
                'virgin',
            ],
            'RewardTransferCodes' => [
                '788A',
                '789A',
                '790A',
                '565A',
                '56A',
            ],
            'SourceQuantities' => [
                10000,
                20000,
                30000,
                70000,
                140000,
            ],
        ],
        [
            'Providers' => [
                'aeroflot',
                'airberlin',
                'airchina',
                'airfrance',
                'klm',
                'alitalia',
                'ana',
                'asia',
                'asiana',
                'aviancataca',
                'chinaeastern',
                'chinasouthern',
                'skywards',
                'etihad',
                'japanair',
                'jetairways',
                'lanpass',
                'lufthansa',
                'qantas',
                'qmiles',
                'saudisrabianairlin',
                'singaporeair',
                'tapportugal',
                'turkish',
            ],
            'RewardTransferCodes' => [
                '788B',
                '789B',
                '790B',
                '565B',
                '56AB',
            ],
            'SourceQuantities' => [
                10000,
                20000,
                30000,
                70000,
                140000,
            ],
        ],
        [
            'Providers' => [
                'rapidrewards',
            ],
            'RewardTransferCodes' => [
                '788SWA',
                '789SWA',
                '790SWA',
                '565SWA',
                '56ASWA',
            ],
            'SourceQuantities' => [
                10000,
                20000,
                30000,
                70000,
                140000,
            ],
        ],
        [
            'Providers' => [
                'jetblue',
                'virginamerica',
            ],
            'RewardTransferCodes' => [
                '788JB',
                '789JB',
                '790JB',
                '565JB',
                '56AJB',
            ],
            'SourceQuantities' => [
                10000,
                20000,
                30000,
                70000,
                140000,
            ],
        ],
        [
            'Providers' => [
                'cosmohotels',
            ],
            'RewardTransferCodes' => [
                'IRA1',
                'IRA2',
                'IRA3',
                'IRA4',
            ],
            'SourceQuantities' => [
                5000,
                25000,
                125000,
                250000,
            ],
        ],
    ];

    public function InitBrowser()
    {
        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->UseCurlBrowser();
            $this->http->SetProxy('localhost:8000');
        } else {
            parent::InitBrowser();
        }
    }

    public function LoadLoginForm()
    {
        $this->AccountFields['Login2'] = ''; // todo: remove when api supports login2

        return parent::LoadLoginForm();
    }

    public function transferMiles($targetProviderCode, $targetAccountNumber, $sourceRewardsQuantity, $fields = [])
    {
        if (!isset($this->providersMap[$targetProviderCode])) {
            $this->http->Log('Unsupported target provider', LOG_LEVEL_ERROR);

            throw new \UserInputError("Target provider is not supported");
        }

        $msg = "Transferring $sourceRewardsQuantity rewards to $targetProviderCode account $targetAccountNumber";
        $this->http->Log($msg);

        $rewardCode = $this->getRewardCode($targetProviderCode, $sourceRewardsQuantity);
        $marriottTargetProviderCode = $this->providersMap[$targetProviderCode];

        $this->http->Log("Marriott provider code: $marriottTargetProviderCode");
        $this->http->Log("Marriott reward code: $rewardCode");

        $personalData = $this->parsePersonalDataForRewardsTransfer();
        $transferData = $this->parseTransferDataForRewardsTransfer($rewardCode, $sourceRewardsQuantity);

        $formFields = [
            'airlineProgram'      => $marriottTargetProviderCode,
            'frequentFlyerNumber' => $targetAccountNumber,
            'rewardCode'          => $rewardCode,
        ];

        foreach ($personalData as $key => $value) {
            $formFields[$key] = $value;
        }

        foreach ($transferData as $key => $value) {
            $formFields[$key] = $value;
        }

        $status = $this->validate($formFields);

        if (!$status) {
            return false;
        }

        if ($this->idle) {
            $this->http->Log('Idle run, no submit');

            return true;
        }

        if ($status = $this->submit($formFields)) {
            $this->ErrorMessage = 'Transfer successful';

            return true;
        } else {
            return false;
        }
    }

    private function validate($formFields)
    {
        $this->http->Log('Validating rewards transfer request');
        $this->http->FormURL = 'https://www.marriott.com/redemption/ajax/validate.mi';

        foreach ($formFields as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }
        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to post rewards transfer validate form', LOG_LEVEL_ERROR);

            return false;
        }

        return $this->checkTransferErrors($this->http->Response['body']);
    }

    private function submit($formFields)
    {
        $this->http->Log('Submitting rewards transfer request');
        $this->http->FormURL = 'https://www.marriott.com/redemption/ajax/submit.mi';

        foreach ($formFields as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }
        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to post rewards transfer submit form', LOG_LEVEL_ERROR);

            return false;
        }

        return $this->checkTransferErrors($this->http->Response['body']);
    }

    // TODO: Check if validate errors are same as submit
    private function checkTransferErrors($response)
    {
        $errResponseJson = json_decode($response, true);

        if ($errResponseJson === null or $errResponseJson === false) {
            if ($err = $this->http->FindPreg('#We\s+are\s+experiencing\s+technical\s+difficulties\.\s+Please\s+try\s+again\.#i')) {
                throw new \ProviderError($err, ACCOUNT_PROVIDER_ERROR);
            } else {
                $this->http->Log('Provider returned unsupported page', LOG_LEVEL_ERROR);

                return false;
            }
        }

        if ($errResponseJson === []) {
            $this->http->Log('Empty response means no error');

            return true;
        }

        if (isset($errResponseJson["partnerInformation"][0]["original"]["enDescription"])) {
            $this->http->Log("partnerInformation validation error", LOG_LEVEL_ERROR);

            throw new \UserInputError($errResponseJson["partnerInformation"][0]["original"]["enDescription"]);
        }

        if (!isset($errResponseJson['RewardsServiceValidationException'])) {
            $this->http->Log('Unsupported error response structure: ' . print_r($errResponseJson, true), LOG_LEVEL_ERROR);

            return false;
        }

        $errorsFromMarriott = $errResponseJson['RewardsServiceValidationException'];
        $errors = [];

        foreach ($errorsFromMarriott as $e) {
            $header = $e['original']['enDescription'];
            $message = $e['message'];
            $regex = '#We\'re\s+sorry\.\s+You\s+do\s+not\s+currently\s+have\s+enough\s+points\s+for\s+this\s+reward\s+order\.#i';

            if (preg_match($regex, $message, $m)) {
                $message = $m[0];
            } else {
                $message = null;
                $this->sendNotification('Unknown Marriott rewards transfer error');
            }
            $errors[] = [
                'Header'  => $header,
                'Message' => $message,
            ];
        }

        if (count($errors) == 1) {
            $e = $errors[0];
            $errorsTotal = $e['Header'];

            if ($e['Message']) {
                $errorsTotal .= '. ' . $e['Message'];
            }
            $this->http->Log($errorsTotal, LOG_LEVEL_ERROR);

            if ($errorsTotal == 'Insufficient Points. We\'re sorry. You do not currently have enough points for this reward order.') {
                throw new \UserInputError($errorsTotal);
            }

            return false;
        } elseif (count($errors) > 1) {
            $this->sendNotification('Multiple Marriott rewards transfer errors found');
            // Maybe some better than plaintext formatting is needed
            $errorsTotal = '';
            $i = 1;

            foreach ($errors as $e) {
                $errorsTotal .= $i . ') ' . $e['Header'];

                if ($e['Message']) {
                    $errorsTotal .= '. ' . $e['Message'];
                }
                $errorsTotal .= ' ';
                $i++;
            }
            $errorsTotal = trim($errorsTotal);
            $this->http->Log($errorsTotal, LOG_LEVEL_ERROR);

            return false;
        } else {
            $msg = 'Unsupported error response structure: ' . print_r($errResponseJson['RewardsServiceValidationException'], true);
            $this->http->Log($msg, LOG_LEVEL_ERROR);

            return false;
        }
    }

    private function parsePersonalDataForRewardsTransfer()
    {
        $this->http->GetURL('https://www.marriott.com/rewards/myAccount/editPersonalInformation.mi');
        $fields = [
            'addressLine1' => '//input[@id = "street1"]/@value',
            'addressLine2' => '//input[@id = "street2"]/@value',
            'addressLine3' => '//input[@id = "street3"]/@value',
            'city'         => '//input[@id = "city"]/@value',
            'companyName'  => '//input[@id = "company-name"]/@value',
            'country'      => '//select[@id="country"]/option[@selected]/@value',
            'email'        => '//input[@id = "email-address"]/@value',
            //			'phone' => '//input[@id = "home-telephone"]/@value',
            'postalCode'    => '//input[@id = "postal-code"]/@value',
            'stateProvince' => '//input[@id = "state"]/@value',
        ];
        $result = [];

        foreach ($fields as $key => $xpath) {
            $result[$key] = $this->http->FindSingleNode($xpath);
        }

        foreach ([
            'business-telephone',
            'mobile-telephone',
            'home-telephone',
        ] as $name) {
            if ($phone = $this->http->FindSingleNode(sprintf('//input[@id = "%s"]/@value', $name))) {
                $result['phone'] = $phone;
            }
        }

        if (!$result) {
            $this->http->Log('Failed to parse personal data needed for rewards transfer', LOG_LEVEL_ERROR);

            return false;
        }
        $this->http->Log('Parsed personal data for rewards transfer:');
        $this->http->Log(print_r($result, true));

        return $result;
    }

    private function parseTransferDataForRewardsTransfer($rewardsCode, $sourceQuantity)
    {
        $url = 'https://www.marriott.com/redemption/ajax/quantities.mi';
        $url .= '?';
        $url .= 'marrRewardCode=' . $rewardsCode;
        $url .= '&';
        $url .= 'points=' . number_format($sourceQuantity);
        $this->http->GetURL($url);
        $fields = [
            'rewardDescription',
            'certificateType',
            'quantity',
            'airlineReward',
            'rewardCode',
        ];
        $result = [];

        foreach ($fields as $key) {
            $result[$key] = $this->http->FindSingleNode('//input[@name = "' . $key . '"]/@value');
        }

        if (!$result) {
            $this->http->Log('Failed to parse transfer data needed for rewards transfer', LOG_LEVEL_ERROR);

            return false;
        }
        $this->http->Log('Transfer data:');
        $this->http->Log(print_r($result, true));

        return $result;
    }

    private function getRewardCode($providerCode, $sourceQuantity)
    {
        $rewardCode = false;

        foreach ($this->rewardCodes as $key => $value) {
            $providerMatch = array_search($providerCode, $value['Providers']);
            $quantityIndex = array_search($sourceQuantity, $value['SourceQuantities']);

            if ($providerMatch !== false and $quantityIndex !== false) {
                $rewardCode = $value['RewardTransferCodes'][$quantityIndex];

                break;
            }
        }

        if (!$rewardCode) {
            $this->http->Log('Failed to get reward code. Wrong provider code or source rewards quantity', LOG_LEVEL_ERROR);

            throw new \UserInputError("Invalid number of points");
        }

        return $rewardCode;
    }
}
