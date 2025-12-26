<?php

namespace AwardWallet\Engine\mileageplus\Transfer;

use AwardWallet\Engine\ProxyList;

class Purchase extends \TAccountChecker
{
    use ProxyList;
    use \SeleniumCheckerHelper;

    protected $timeout = 20;
    protected $loadTimeout = 20;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->setProxyBrightData();
        // $this->http->SetProxy($this->proxyReCaptcha());
        $this->InitSeleniumBrowser();
        $this->useGoogleChrome();
        $this->Start();

        $this->usePacFile(false);
        $this->keepSession(false);
        $this->ArchiveLogs = true;
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $numberOfMiles = intval($this->TransferFields['numberOfMiles']);

        if ($numberOfMiles < 2000 || $numberOfMiles > 88000 || $numberOfMiles % 1000 !== 0) {
            throw new \UserInputError("Number of purchased points should be between 2,000 and 88,000 inclusive and divisible by 1,000");
        }

        return true;
    }

    public function Login()
    {
        $this->http->GetURL('https://buymiles.mileageplus.com/united/united_landing_page/#!/en-US#%2Fen-US');
        $elem = $this->waitForElement(\WebDriverBy::id('header-buy-link-pcta'), $this->timeout);

        if ($elem) {
            $elem->click();
        }
        $frame = $this->waitForElement(\WebDriverBy::id('widget_frame'), $this->timeout);

        if (!$frame) {
            return false;
        }
        $this->driver->switchTo()->frame($frame);

        $submit = $this->waitForElement(\WebDriverBy::id('signInBtn'), $this->timeout * 3);

        if (empty($submit)) {
            $this->saveResponse();

            return false;
        }

        $loginInput = $this->waitForElement(\WebDriverBy::id('username'), $this->timeout);

        if ($loginInput) {
            $loginInput->sendKeys($this->TransferFields['Login']);
        }
        $lastNameInput = $this->waitForElement(\WebDriverBy::id('lastname'), $this->timeout);

        if ($lastNameInput) {
            $lastNameInput->sendKeys($this->TransferFields['LastName']);
        }
        $submit->click();

        $error = $this->waitForElement(\WebDriverBy::xpath('//i[contains(text(), "Invalid MileagePlus credentials.")]'), $this->timeout * 3);
        $this->saveResponse();

        if ($error) {
            throw new \UserInputError($error->getText());
        }
        $success = $this->waitForElement(\WebDriverBy::xpath('//form[@data-name="transactionForm"]//select[@id="bgt-offer-dropdown"]'), $this->loadTimeout);
        $this->saveResponse();

        if ($success) {
            return true;
        }

        return false;
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $this->logger->notice(__METHOD__);

        //step 1
        if (!$this->waitForElement(\WebDriverBy::xpath('//form[@data-name="transactionForm"]//select[@id="bgt-offer-dropdown"]'), $this->loadTimeout)) {
            $this->checkLoginFormErrors();

            return $this->clear('Buy form not loaded');
        }

        if (!($elem = $this->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->loadTimeout))) {
            return $this->clear('Select "bgt-offer-dropdown" not found.');
        }
        $optionXpath = sprintf('//select[@id = "bgt-offer-dropdown"]//option[@value = "%s"]', $numberOfMiles);

        if ($option = $this->waitForElement(\WebDriverBy::xpath($optionXpath), $this->timeout)) {
            $option->click();
            $this->logger->info('Select ' . $numberOfMiles . ' points done.');
        } else {
            return $this->clear('Didn\'t find option for ' . $numberOfMiles . ' points.');
        }

        if (!$this->waitForElement(\WebDriverBy::id('cardName'), $this->loadTimeout)) {
            $this->checkLoginFormErrors();

            return $this->clear('Credit Card form not loaded.');
        }
        $inputSelectFieldsMap = [
            'Type' => [
                'cardName',
                'string:transaction.payment.form.creditCard.' . $creditCard['Type'],
            ],
            'ExpirationMonth' => [
                'expirationMonth',
                'number:' . (int) $creditCard['ExpirationMonth'],
            ],
            'ExpirationYear' => [
                'expirationYear',
                'number:' . (strlen($creditCard['ExpirationYear']) === 2 ? '20' . $creditCard['ExpirationYear'] : $creditCard['ExpirationYear']),
            ],
            'Country' => [
                'country',
                'string:' . $creditCard['CountryCode'],
            ],
            'StateOrProvince' => [
                'state',
                'string:' . $creditCard['StateCode'],
            ],
        ];

        foreach ($inputSelectFieldsMap as $key => $selectField) {
            [$selectFieldId, $value] = $selectField;
            $this->logger->info("Selecting '$key' to '$value' ...");

            try {
                (new \WebDriverSelect($this->waitForElement(\WebDriverBy::id($selectFieldId), $this->timeout)))->selectByValue($value);
            } catch (\NoSuchElementException $e) {
                return $this->clear($e->getMessage());
            }
            $this->logger->info('Select "' . $value . '" done.');
        }
        $inputTextFieldsMap = [
            'cardNumber'         => 'CardNumber',
            'securityCode'       => 'SecurityNumber',
            'creditCardFullName' => 'Name',
            'street1'            => 'AddressLine',
            'city'               => 'City',
            'zip'                => 'Zip',
            'phone'              => 'PhoneNumber',
        ];

        foreach ($inputTextFieldsMap as $inputId => $awKey) {
            $this->logger->info("Setting '$awKey' to input '{$creditCard[$awKey]}' ...");
            $this->waitForElement(\WebDriverBy::id($inputId), $this->timeout)->sendKeys($creditCard[$awKey]);
            $this->logger->info('Done.');
        }
        // $this->logger->info('Setting "Email" to input "billingEmail" ...');
        // if( $elem = $this->waitForElement(\WebDriverBy::id('billingEmail'), $this->timeout) ){
        //     $elem->clear();
        //     $elem->sendKeys($fields['Email']);
        //     $this->logger->info('Done.');
        // }else{
        //     $this->logger->info('Could not find input field for Email.');
        //     return false;
        // }
        $this->waitForElement(\WebDriverBy::id('termsAndConditions'), $this->timeout)->click();
        $this->saveResponse();

        $this->waitForElement(\WebDriverBy::xpath('//*[@class="bgt-order-information-form"]//*[@type="submit"]'), $this->timeout)->click();

        if ($this->waitForElement(\WebDriverBy::xpath('(//div[@class="bgt-form-error" and contains(.,"Please complete all of the required fields")])[1]'), $this->loadTimeout)) {
            $this->logger->info('Error: Missing required fields.');

            return false;
        }
        $this->logger->info('Done.');

        // success
        $success = $this->waitForElement(\WebDriverBy::xpath('//*[text()[contains(normalize-space(.), "Thank you for your purchase") or contains(normalize-space(.),"We have received your purchase request")]]'), $this->loadTimeout * 3);
        $this->http->cleanup();

        if ($success) {
            $this->ErrorMessage = 'Thank you for your purchase!';

            return true;
        }

        return false;
    }

    public function purchaseMilesOld(array $fields, $numberOfMiles, $creditCard)
    {
        $this->http->Log('united purchase started');
        $points = number_format($numberOfMiles);
        // email and points
        $this->http->Log('step 1: email and points');

        if ($elem = $this->driver->findElement(\WebDriverBy::xpath('//div[@id="mv_points"]/img'))) {
            $elem->click();
        }
        $xPath = sprintf('//div[@id="mv_points_list"]/div[starts-with(., "%s")]', $points);

        if ($item = $this->waitForElement(\WebDriverBy::xpath($xPath), $this->timeout, false)) {
            $this->http->Log('set ' . $item->getText() . ' points');
            $item->click();
        } else {
            $this->saveResponse();
            $this->http->Log('did not find item with required number of points: ' . $numberOfMiles, LOG_LEVEL_ERROR);

            return false;
        }

        if ($elem = $this->driver->findElement(\WebDriverBy::xpath('//div[@id="mv_email_address"]/input'))) {
            $elem->clear();
            $elem->sendKeys($fields['Email']);
        }

        if ($elem = $this->driver->findElement(\WebDriverBy::id('mv_submit'))) {
            $elem->click();
        }

        $elem = $this->waitForElement(\WebDriverBy::xpath('//div[@id="pay_card_type"]/img'), $this->timeout);

        if (!$elem) {
            $this->http->Log('no form for cc', LOG_LEVEL_ERROR);

            return false;
        }

        // cc info
        $this->http->Log('step 2: cc info');
        $selects = $this->getSelectValues($creditCard);

        if ($selects === false) {
            return false;
        }

        foreach ($selects as $id => $val) {
            $xPath = sprintf('//div[@id="%s"]/img', $id);

            if ($elem = $this->driver->findElement(\WebDriverBy::xpath($xPath))) {
                $elem->click();
            }
            $xPath = sprintf('//div[@id="%s_list"]/div[text() = "%s"]', $id, $val);

            if ($elem = $this->driver->findElement(\WebDriverBy::xpath($xPath))) {
                $this->http->Log('input ' . $id . ' set value to ' . $elem->getText());
                $elem->click();
            } else {
                $this->http->Log('did not find value ' . $val . ' in select ' . $id, LOG_LEVEL_ERROR);

                return false;
            }
        }
        $inputs = $this->getTextValues($creditCard);

        foreach ($inputs as $id => $val) {
            $xPath = sprintf('//div[@id="%s"]/input', $id);

            if ($elem = $this->driver->findElement(\WebDriverBy::xpath($xPath))) {
                $elem->sendKeys($val);
                $this->http->Log('input ' . $id . ' set to ' . $val);
            } else {
                $this->http->Log('did not find text input ' . $id, LOG_LEVEL_ERROR);

                return false;
            }
        }

        if ($elem = $this->driver->findElement(\WebDriverBy::id('pay_submit'))) {
            $elem->click();
        }

        // confirm
        $this->http->Log('step 3: confirm');
        $elem = $this->waitForElement(\WebDriverBy::xpath('//div[@id="review_accept"]/input[@type="checkbox"]'), $this->timeout);

        if (!$elem) {
            $this->http->Log('no confirm page', LOG_LEVEL_ERROR);
            $this->saveResponse();

            return false;
        }
        $elem->click();

        if ($a = $this->driver->findElement(\WebDriverBy::id('review_submit'))) {
            $a->click();
        }
        $mes = 'Thank you. We have received your purchase request. We will notify you by email when the transaction completes';

        if ($note = $this->waitForElement(\WebDriverBy::xpath('//div[contains(text(), "Thank you. We have received your purchase request")]'), $this->timeout)) {
            $this->http->Log('success');
            $this->ErrorMessage = $mes;

            return true;
        }
        $this->saveResponse();
        $this->http->Log('no success message', LOG_LEVEL_ERROR);

        return false;
    }

    public function getPurchaseMilesFields()
    {
        return [
            "Login" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "MileagePlus Number",
            ],
            "LastName" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Last Name",
            ],
            "Email" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Email Address",
            ],
        ];
    }

    protected function getTextValues($creditCard)
    {
        return [
            'pay_card_number'      => $creditCard['CardNumber'],
            'pay_card_cvv'         => $creditCard['SecurityNumber'],
            'pay_card_name'        => $creditCard['Name'],
            'pay_billing_address1' => $creditCard['AddressLine'],
            'pay_billing_city'     => $creditCard['City'],
            'pay_billing_code'     => $creditCard['Zip'],
            'pay_billing_phone'    => preg_replace('/\D/', '', $creditCard['PhoneNumber']),
        ];
    }

    protected function getSelectValues($creditCard)
    {
        switch ($creditCard['Type']) {
            case 'visa':
                $cc = 'VISA';

                break;

            case 'amex':
                $cc = 'American Express';

                break;

            default:
                $this->http->Log('unknown cc type ' . $creditCard['Type'], LOG_LEVEL_ERROR);

                return false;
        }
        $months = [
            null,
            'January',
            'February',
            'March',
            'April',
            'May',
            'June',
            'July',
            'August',
            'September',
            'October',
            'November',
            'December',
        ];
        $month = intval($creditCard['ExpirationMonth']);

        if (!isset($months[$month])) {
            $this->http->Log('invalid exp month ' . $creditCard['ExpirationMonth'], LOG_LEVEL_ERROR);

            return false;
        }
        $expMonth = $months[$month];
        $year = $creditCard['ExpirationYear'];

        if (strlen($year) == 2) {
            $year = '20' . $year;
        }
        $expYear = $year;

        if ($creditCard['CountryCode'] === 'US') {
            $ccCountry = 'United States of America';
        } else {
            $ccCountry = $creditCard['Country'];
        }
        $ccState = $creditCard['State'];

        return [
            'pay_card_type'       => $cc,
            'pay_expiry_month'    => $expMonth,
            'pay_expiry_year'     => $expYear,
            'pay_billing_country' => $ccCountry,
            'pay_billing_region'  => $ccState,
        ];
    }

    protected function clear($message = null)
    {
        if (isset($message)) {
            $this->http->Log($message, LOG_LEVEL_ERROR);
        }
        $this->saveResponse();

        if (isset($this->seleniumChecker)) {
            $this->seleniumChecker->http->cleanup();
        }

        return false;
    }

    protected function checkLoginFormErrors()
    {
        if ($error = $this->waitForElement(\WebDriverBy::xpath('//div[@class="bgt-form-error" and normalize-space(.) != ""]'), $this->timeout)) {
            throw new \UserInputError(CleanXMLValue($error->getText()));
        }
    }
}
