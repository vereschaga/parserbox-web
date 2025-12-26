<?php

namespace AwardWallet\Engine\hhonors\Transfer;

class Transfer extends \TAccountCheckerHhonors
{
    protected $providerMap = [
        'aeroflot'           => 'SU',
        'aeromexico'         => 'AE',
        'aeroplan'           => 'AC',
        'airbaltic'          => 'BT',
        'airberlin'          => 'AB',
        'airnewzealand'      => 'NZ',
        'alaskaair'          => 'AS',
        'aa'                 => 'AA',
        'amtrak'             => '2V',
        'ana'                => 'NH',
        'asia'               => 'CX',
        'aviancataca'        => 'TA',
        'british'            => 'BA',
        'chinasouthern'      => 'CZ',
        'czech'              => 'OK',
        'delta'              => 'DL',
        'etihad'             => 'EY',
        'airfrance'          => 'AK',
        'frontierairlines'   => 'F9',
        'germanwings'        => '4U',
        'gulfair'            => 'GF',
        'hainan'             => 'HU',
        'hawaiian'           => 'HA',
        'icelandair'         => 'FI',
        'jalhotels'          => 'JL',
        'jetairways'         => '9W',
        'jetblue'            => 'B6',
        'lanpass'            => 'LA',
        'malaysia'           => 'MH',
        'lufthansa'          => 'LH',
        'multiplus'          => 'M9',
        'airchina'           => 'CA',
        'qantas'             => 'QN',
        'qmiles'             => 'QR',
        'saudisrabianairlin' => 'SV',
        'singaporeair'       => 'SG',
        'flysaa'             => 'SC',
        'srilankan'          => 'UL',
        'thaiair'            => 'TG',
        'mileageplus'        => 'UA',
        'dividendmiles'      => 'US',
        'virginamerica'      => 'VA',
        'virgin'             => 'VS',
        'velocity'           => 'DJ',
    ];

    protected $providerRates = [
        // confirmed as of 2017/04/21
        [
            'Providers' => [
                'alaskaair',
                'delta',
            ],
            'SourceRates' => [
                10000,
                20000,
                30000,
                40000,
                50000,
                60000,
                70000,
                80000,
                90000,
                100000,
            ],
        ],
        [
            'Providers' => [
                'aeroflot',
            ],
            'SourceRates' => [
                10000,
                20000,
                60000,
                70000,
                80000,
                90000,
                100000,
            ],
        ],
        [
            'Providers' => [
                'amtrak',
            ],
            'SourceRates' => [
                10000,
                20000,
                30000,
                70000,
                80000,
                90000,
                100000,
            ],
        ],
        [
            'Providers' => [
                'aeromexico',
            ],
            'SourceRates' => [
                25000,
                50000,
                75000,
                100000,
            ],
        ],

        // older
        [ // 2
            'Providers' => [
                'aa',
                'frontierairlines',
                'hawaiian',
                'flysaa',
                'virgin',
                'velocity',
                'qantas',
                'multiplus',
            ],
            'SourceRates' => [
                10000,
                20000,
                30000,
                40000,
                50000,
                60000,
                70000,
                80000,
                90000,
                100000,
            ],
        ],
        [ // 4
            'Providers' => [
                'aeroplan',
                'airberlin',
                'ana',
                'aviancataca',
                'british',
                'chinasouthern',
                'czech',
                'etihad',
                'jetblue',
                'lufthansa',
                'swissair',
                'austrian',
                'qmiles',
                'singaporeair',
                'thaiair',
                'mileageplus',
                'icelandair',
                'saudisrabianairlin',
                'virginamerica',
                'germanwings',
                'japanair',
                'srilankan',
            ],
            'SourceRates' => [
                10000,
                20000,
                30000,
                40000,
                50000,
                60000,
                70000,
                80000,
                90000,
                100000,
            ],
        ],
        [ // 5
            'Providers' => [
                'airnewzealand',
            ],
            'SourceRates' => [
                10000,
                20000,
                30000,
                40000,
                50000,
                60000,
                70000,
                80000,
                90000,
                100000,
            ],
        ],
        [ // 6
            'Providers' => [
                'airfrance',
                'klm',
            ],
            'SourceRates' => [
                20000,
                30000,
                40000,
                50000,
                60000,
                70000,
                80000,
                90000,
                100000,
            ],
        ],
        [ // 7
            'Providers' => [
                'gulfair',
            ],
            'SourceRates' => [
                20000,
                40000,
                60000,
                80000,
                100000,
                120000,
                140000,
                160000,
                180000,
                200000,
            ],
        ],
        [ // 8
            'Providers' => [
                'hainan',
            ],
            'SourceRates' =>
                [
                    25000,
                    50000,
                    75000,
                    100000,
                    125000,
                    150000,
                    175000,
                    200000,
                    225000,
                    250000,
                ],
        ],
        [ // 9
            'Providers' => [
                'jetairways',
                'asia',
            ],
            'SourceRates' => [
                10000,
                20000,
                30000,
                40000,
                50000,
                60000,
                70000,
                80000,
                90000,
            ],
        ],
        [ // 10
            'Providers' => [
                'lanpass',
            ],
            'SourceRates' => [
                25000,
                50000,
                75000,
                100000,
                125000,
                150000,
                175000,
                200000,
                225000,
                250000,
            ],
        ],
        [ // 11
            'Providers' => [
                'malaysia',
            ],
            'SourceRates' => [
                10000,
                20000,
                30000,
                40000,
                50000,
                60000,
                70000,
                80000,
                90000,
                100000,
            ],
        ],
        [ // 12
            'Providers' => [
                'airchina',
            ],
            'SourceRates' => [
                10000,
                20000,
                30000,
                40000,
                50000,
                60000,
                70000,
                80000,
                90000,
            ],
        ],
        [ // 13
            'Providers' => [
                'dividendmiles',
            ],
            'SourceRates' => [
                10000,
                20000,
                30000,
                40000,
                50000,
                60000,
                70000,
                80000,
                90000,
                100000,
            ],
        ],
    ];

    public function LoadLoginForm()
    {
        if (!isset($this->providerMap[$this->TransferFields['targetProvider']])) {
            $msg = "unsupported target provider " . $this->TransferFields['targetProvider'];
            $this->logger->debug($msg);

            throw new \UserInputError($msg);
        }

        if (false === $this->getRewardIndex($this->TransferFields['targetProvider'], $this->TransferFields['numberOfMiles'])) {
            throw new \UserInputError('Invalid provider or number of miles');
        }

        return parent::LoadLoginForm();
    }

    public function transferMiles($targetProviderCode, $targetAccountNumber, $numberOfMiles, $fields = [])
    {
        $this->logger->debug("transferring $numberOfMiles points to $targetProviderCode account $targetAccountNumber");

        $rewardIndex = $this->getRewardIndex($targetProviderCode, $numberOfMiles);

        if ($rewardIndex === false) {
            $msg = 'invalid =provider and/or =sourceRate';
            $this->logger->debug($msg);

            throw new \UserInputError($msg);
        }

        $this->http->getURL('https://secure3.hilton.com/en/hh/customer/epsilonexitpoint/exchange.htm');

        if (!$this->http->parseForm(null, 1, true, '//form[contains(@action, "SSOLogin")]')) {
            $this->logger->debug('=sso login form parse failed');

            return false;
        }
        $this->logger->debug('=sso form');
        $this->logger->debug(print_r($this->http->Form, true));

        if (!$this->http->postForm()) {
            $this->logger->debug('=sso login form post failed');

            return false;
        }

        $providerCode = $this->providerMap[$targetProviderCode];
        $this->logger->debug("provider code: $targetProviderCode");

        if (!$this->http->ParseForm('MasterForm')) {
            return false;
        }

        $options = $this->http->XPath->query('//select[@id="IFrames_selPartner"]/option');

        for ($i = 0; $i < $options->length; $i++) {
            $value = $options->item($i)->getAttribute('value');

            if (strpos($value, $providerCode . '|') !== 0) {
                continue;
            }
            [$code, $number] = explode('|', $value) + ['', ''];

            if (empty($number)) {
                throw new \UserInputError('To exchange points with the travel partner you selected, you need to add that travel partner to your account');
            }

            if (strcasecmp(ltrim($number, '0 '), ltrim($targetAccountNumber, '0 ')) !== 0) {
                throw new \UserInputError('To exchange points with the travel partner you selected, you need to add that travel partner to your account');
            }
            $this->http->Form['ctl00$IFrames$selPartner'] = $value;
            $this->http->Form['ctl00$IFrames$partner'] = trim($options->item($i)->nodeValue);
            $this->http->Form['ctl00$IFrames$ptcode'] = $value;
        }

        if (empty($this->http->Form['ctl00$IFrames$selPartner'])) {
            $this->logger->error(sprintf('can\'t find option for provider %s', $targetProviderCode));

            return false;
        }

        if (!$this->http->PostForm() || !$this->http->ParseForm('MasterForm')) {
            return false;
        }

        $options = $this->http->XPath->query('//select[@id="IFrames_selReward"]/option');

        for ($i = 0; $i < $options->length; $i++) {
            $value = $options->item($i)->getAttribute('value');
            $desc = trim($options->item($i)->nodeValue);

            if (preg_match('/^Exchange\s*([\d\,]+)\s*Hilton\s*Honors\s*points\s*for/i', $desc, $m) > 0 && intval(str_replace(',', '', $m[1])) === intval($numberOfMiles)) {
                $url = sprintf('https://iframe.hhonors.epsilon.com/hhweb/iFrames/RewardsExchangeSummary_iFrame.aspx?rwdcode=%s&rwddesc=%s', $value, urlencode($desc));
                $this->logger->debug('found url: ' . $url);

                break;
            }
        }

        if (!isset($url)) {
            throw new \UserInputError('Invalid number of points');
        }
        $this->http->GetURL($url);

        if ($error = $this->http->FindSingleNode('//span[contains(text(), "You currently do not have enough Hilton Honors points to make an exchange")]')) {
            throw new \UserInputError($error);
        }

        $withdraw = $this->http->FindSingleNode('//span[@id="IFrames_lblWithdrawnPoints"]');
        $account = $this->http->FindSingleNode('//span[@id="IFrames_LblExtAcctNo"]');

        if (empty($withdraw) || empty($account) || intval(str_replace(',', '', $withdraw)) !== intval($numberOfMiles) || strcmp(ltrim($account, '0 '), ltrim($targetAccountNumber, '0 ')) !== 0) {
            $this->logger->error('Mismatch detected');

            return false;
        }

        if (!$this->http->ParseForm('MasterForm')) {
            return false;
        }
        $this->http->Form['__EVENTTARGET'] = 'ctl00$IFrames$btn_Submit';

        if (!$this->http->PostForm()) {
            return false;
        }

        // Please allow up to 30 days for your exchange to process.

        if ($m = $this->http->FindPreg('/We\s+are\s+processing\s+your\s+exchange/i')) {
            $this->logger->debug($m);
            $this->ErrorMessage = trim($m, '. ') . '. Please allow up to 30 days for your exchange to process';

            return true;
        }

        // unknown error
        return false;
    }

    protected function getRewardIndex($provider, $sourceRate)
    {
        $rewardIndex = false;

        foreach ($this->providerRates as $_ => $value) {
            $providerMatch = array_search($provider, $value['Providers']);

            if ($providerMatch !== false) {
                $quantityIndex = array_search($sourceRate, $value['SourceRates']);
                $rewardIndex = $quantityIndex;

                break;
            }
        }

        return $rewardIndex;
    }
}
