<?php

namespace AwardWallet\Engine\triprewards\Transfer;

class Transfer extends \TAccountCheckerTriprewards
{
    public $idle = false;

    private $transferUrl = 'https://www.wyndhamrewards.com/trec/consumer/redeemMiles.action?variant=';
    private $accountUrl = 'https://www.wyndhamrewards.com/trec/consumer/myaccount.action?variant=';
    private $validateUrl = 'https://www.wyndhamrewards.com/trec/consumer/redeemMiles!validatePartnerNumber.action';

    // order like on site
    private $provider2number = [
        'aeroplan'           => '2280',
        'alaskaair'          => '5704',
        'aa'                 => '1391',
        'amtrak'             => '1394',
        'frontierairlines'   => '4500',
        'hawaiian'           => '5707',
        'rapidrewards'       => '8200',
        'dividendmiles'      => '4280',
        'mileageplus'        => '4320',
        'aeromexico'         => '8340',
        'airberlin'          => '4482',
        'czech'              => '7641',
        'jetairways'         => '5703',
        'paybackgerman'      => '7580',
        'qmiles'             => '5705',
        'saudisrabianairlin' => '5702',
        'airchina'           => '4520',
        'chinaeastern'       => '7520',
        'chinasouthern'      => '7781',
        'hainan'             => '7400',
    ];

    public function transferMiles($provider, $account, $sourceRate, $fields = [])
    {
        $this->http->log('[INFO] ' . __METHOD__);

        $personal = $this->collectPersonalData();
        $this->http->log('[INFO] collected personal data:');
        $this->http->log(print_r($personal, true));

        $this->http->getUrl($this->transferUrl);

        $providerNumber = $this->provider2number[$provider];

        if (!$this->http->parseForm(sprintf('redeem_%s', $providerNumber))) {
            throw new \EngineError('Failed to find continue form');
        }

        $productId = $this->getProductId($provider, $sourceRate);
        $this->http->log(sprintf('[INFO] productId = %s', $productId));

        if (!$productId) {
            throw new \UserInputError('Invalid # of points specified');
        }
        $this->http->setInputValue('firstName', arrayVal($personal, 'FirstName'));
        $this->http->setInputValue('lastName', arrayVal($personal, 'LastName'));
        $this->http->setInputValue('frequenttraveler', $account);
        $this->http->setInputValue('productId', $productId);

        $this->http->log('[INFO] form:');
        $this->http->log(print_r($this->http->Form, true));

        $this->validatePostData();

        if ($this->idle) {
            $$this->http->log('[INFO] idle, no submit');

            return true;
        }

        if (!$this->http->postForm()) {
            throw new \EngineError('Failed to submit continue form');
        }

        $success = $this->http->findPreg('/Thank you for your redemption/i');

        if ($success) {
            $this->http->log('[INFO] success message:');
            $this->http->log($success);
            $this->ErrorMessage = $success;

            return true;
        }

        return false;
    }

    private function validatePostData()
    {
        $this->http->log('[INFO] ' . __METHOD__);

        $params = [
            'frequenttraveler' => arrayVal($this->http->Form, 'frequenttraveler'),
            'partnerCode'      => arrayVal($this->http->Form, 'partnerCode'),
            'qty'              => arrayVal($this->http->Form, 'qtyFld'), // difference in names
            'productId'        => arrayVal($this->http->Form, 'productId'),
            'formToken'        => $this->http->findPreg('/\bformToken=(\w+)/i'),
        ];
        $this->http->setDefaultHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->http->postUrl($this->validateUrl, $params);

        $resp = $this->http->Response['body'];

        if (preg_match('/resubmit-invalid-points/i', $resp)) {
            throw new \UserInputError('You do not have enough points');
        }

        if (preg_match('/resubmit-invalid-partner/i', $resp)) {
            throw new \UserInputError('Target account number is invalid');
        }
    }

    private function collectPersonalData()
    {
        $this->http->log('[INFO] ' . __METHOD__);

        $this->http->getUrl($this->accountUrl);

        $res = [];
        $res['FirstName'] = $this->http->findPreg(
            '/var\s*escapedFirstName\s*=\s*htmlEscape[(]"(\w+)"[)];/');
        $res['LastName'] = $this->http->findPreg(
            '/var\s*escapedLastName\s*=\s*htmlEscape[(]"(\w+)"[)];/');

        return $res;
    }

    private function getProductId($provider, $sourceRate)
    {
        // that complex because no starts-with or regex in our xpath
        $this->http->log('[INFO] ' . __METHOD__);

        $this->http->getUrl($this->transferUrl);

        $providerNumber = $this->provider2number[$provider];
        $optionsXpath = sprintf('//*[@id = "productId_%s"]/option',
            $providerNumber
        );
        $options = $this->http->XPath->query($optionsXpath);

        foreach ($options as $option) {
            $pat = sprintf('/^\s*%s\s*Points/i', number_format($sourceRate));

            if (preg_match($pat, $option->nodeValue)) {
                $value = $this->http->XPath->query('./@value', $option);

                if ($value->length > 0) {
                    return $value->item(0)->nodeValue;
                } else {
                    return null;
                }
            }
        }

        return null;
    }
}
