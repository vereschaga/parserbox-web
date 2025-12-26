<?php

namespace AwardWallet\Engine\jetairways\Transfer;

use AwardWallet\Engine\ProxyList;

class Purchase extends \TAccountCheckerJetairways
{
    use ProxyList;
    use \SeleniumCheckerHelper;
    protected $checkUpdates = false;
    protected $loadTimeout = 30;
    protected $waitTimeout = 2;

    public function InitBrowser()
    {
        $this->UseCurlBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyPurchase());
        } else {
            $this->http->SetProxy($this->proxyReCaptcha());
        }
    }

    public function getPurchaseMilesFields()
    {
        return [
            'Login' => [
                'Type'     => 'string',
                'Caption'  => 'Account Number',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password',
                'Required' => true,
            ],
        ];
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $this->http->RetryCount = 0;
        $numberOfMiles = intval($numberOfMiles);

        if ($numberOfMiles < 500 || $numberOfMiles % 100 !== 0) {
            throw new \UserInputError("Number of purchased points should be greater or equal then 500 and divisible by 100");
        }

        $this->http->LogHeaders = true;
        $this->http->ParseMetaRedirects = false;

        if ($creditCard['Type'] !== 'visa') {
            $this->logger->info('need visa credit card');

            return false;
        }

        $this->http->GetURL('https://https.jetairways.com/EN/US/JetPrivilege/purchase-jpmiles.aspx');

        if (!$this->http->ParseForm('ctl00')) {
            return false;
        }
        unset($this->http->Form['ctl00$IWOVID_item_1$chkRememberMe']);
        unset($this->http->Form['ctl00$Login$chkHeaderRemeberMe']);
        unset($this->http->Form['']);
        $this->http->Form['ctl00$MainBody$IWOVID_item_1$txtJPMilesPurchaseJPMiles'] = $numberOfMiles;
        $this->http->Form['ctl00$MainBody$IWOVID_item_1$btnBuy'] = '  Buy  ';
        $this->http->Form['ctl00$Login$txtHeaderJPNumber'] = ArrayVal($fields, 'Login');
        $this->http->Form['ctl00$Login$chkHeaderRemeberMe'] = 'on';

        if (!$this->http->PostForm()) {
            return false;
        }

        if (!$this->http->ParseForm('form1')) {// https://www.citruspay.com/JetPrivilege
            return false;
        }
        $merchantId = ArrayVal($this->http->Form, 'merchantTxnId');

        if (!$this->http->PostForm()) {
            return false;
        }

        if (!$this->http->ParseForm('payment', 1, false)) {
            return false;
        }

        $form = $this->http->Form;
        $this->logger->debug(var_export($form, true));

        $expiryMonth = sprintf('%02d', $creditCard['ExpirationMonth']);
        // if (strlen($creditCard['ExpirationYear']) > 2)
        //     $creditCard['ExpirationYear'] = substr($creditCard['ExpirationYear'], -2);
        if (strlen($creditCard['ExpirationYear']) === 2) {
            $expiryYear = '20' . $creditCard['ExpirationYear'];
        } else {
            $expiryYear = $creditCard['ExpirationYear'];
        } //expiry-date

        //   https://www.citruspay.com/resources/pg/js/jetp/jetp.min.js
        $urlJS = $this->http->FindPreg("/<script type=['\"]text\/javascript['\"]\s+src=['\"]([^'\"]+\/jetp.min.js)['\"]/");
        $this->http->NormalizeURL($urlJS);
        $http2 = clone $this;
        $http2->http->GetURL($urlJS);
        $textJS = $this->http->Response['body'];

        $requestOriginRegExp = "/,\s*getRequestInitiator\s*:\s*function\s*\((\w+)\)\s*\{\s*var\s*\w+\s*=\s*(['\"])([A-Z\d]+)(?2)\s*;\s*switch\s*\((?1)\)\s*\{([^\}]+)/";

        if (preg_match($requestOriginRegExp, $textJS, $m)) {
            $requestOrigin = $m[3];

            if (preg_match("/case\s*:\s*[\"']VISA['\"]/", $m[4]) > 0) {
                $this->logger->debug('js script is changed. need correct parsing');

                return false;
            }
        } else {
            $this->logger->debug('can\'t detect requestOrigin)');

            return false;
        }
        $statusData = [
            'username'       => 'jppg@jetprivilege.com',
            'firstName'      => '',
            'lastName'       => '',
            'phoneNumber'    => '',
            'merchantTxnId'  => $merchantId,
            'vanityUrl'      => 'JetPrivilege',
            'addressStreet1' => '',
            'addressStreet2' => '',
            'addressCity'    => '',
            'addressState'   => '',
            'addressCountry' => '',
            'addressZip'     => '',
        ];
        $statusHeaders = [
            'Accept-Encoding'  => 'gzip, deflate, br',
            'Content-Type'     => 'application/x-www-form-urlencoded',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.citruspay.com/sslperf/JetPrivilege/StatusEntry', $statusData, $statusHeaders);
        $this->http->RetryCount = 2;

        $arrayKeys = [
            'merchantAccessKey',
            'merchantTxnId',
            'currency',
            'orderAmount',
            'returnUrl',
            'secSignature',
            'firstName',
            'lastName',
            'email',
            'addressStreet1',
            'addressStreet2',
            'addressCity',
            'addressZip',
            'addressState',
            'addressCountry',
            'customParams[0].name',
            'customParams[0].value',
            'customParams[1].name',
            'customParams[1].value',
            'customParams[2].name',
            'customParams[2].value',
        ];

        foreach ($arrayKeys as $key) {
            if (!array_key_exists($key, $form)) {
                return $this->fail('doesn\'t exists input: "' . $key . '" in form payment');
            }
        }
        $requestPayload = [
            "merchantAccessKey" => $form['merchantAccessKey'],
            "merchantTxnId"     => $form['merchantTxnId'],
            "amount"            => [
                "currency" => $form['currency'],
                "value"    => $form['orderAmount'],
            ],
            "returnUrl"        => $form['returnUrl'],
            "requestSignature" => $form['secSignature'],
            "notifyUrl"        => $form['notifyUrl'],
            "userDetails"      => [
                "firstName" => $form['firstName'],
                "lastName"  => $form['lastName'],
                "email"     => $form['email'],
                "mobileNo"  => "",
                "address"   => [
                    "street1" => $form['addressStreet1'],
                    "street2" => $form['addressStreet2'],
                    "city"    => $form['addressCity'],
                    "zip"     => $form['addressZip'],
                    "state"   => $form['addressState'],
                    "country" => $form['addressCountry'],
                ],
            ],
            "paymentToken" => [
                "type"        => "paymentOptionToken",
                "paymentMode" => [
                    "type"   => "credit",
                    "scheme" => "VISA",
                    "number" => $creditCard['CardNumber'],
                    "holder" => $creditCard['Name'],
                    "expiry" => $expiryMonth . '/' . $expiryYear,
                    "cvv"    => $creditCard['SecurityNumber'],
                ],
            ],
            "customParameters" => [
                $form['customParams[0].name'] => $form['customParams[0].value'],
                $form['customParams[2].name'] => $form['customParams[2].value'],
                $form['customParams[1].name'] => $form['customParams[1].value'],
            ],
            "requestOrigin" => $requestOrigin,
            "offerToken"    => "",
        ];

        $this->logger->debug('=requestPayload:');
        $this->logger->debug(var_export($requestPayload, true));
        $this->logger->debug(json_encode($requestPayload, JSON_UNESCAPED_SLASHES));

        $headersJS = [
            "Content-Type" => "application/json",
            "Accept"       => "*/*",
        ];

        $this->http->PostURL("https://admin.citruspay.com/service/moto/authorize/struct/JetPrivilege", json_encode($requestPayload, JSON_UNESCAPED_SLASHES), $headersJS);

        $response = $this->http->JsonLog(null, true, true);

        if (isset($response['redirectUrl'],$response['pgRespCode'],$response['txMsg'])) {
            if (!empty($url = $response['redirectUrl'])) {
                $this->http->NormalizeURL($url);
            }
            $this->http->GetURL($url);
        } else {
            return $this->fail('need check response after "MAKE PAYMENT"');
        }

        if (!$this->http->ParseForm('submitForm')) {
            return false;
        }

        if (!ArrayVal($this->http->Form, 'vpc_CardExp')) {
            $this->http->Form['vpc_CardExp'] = $expiryYear . $expiryMonth;
        }
        sleep(5);
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');

        if (!$this->http->PostForm()) {
            return false;
        }

        if (!$this->http->ParseForm('PAReq')) {
            return false;
        }

        if (!$this->http->PostForm()) {
            return false;
        }
        sleep(20);

        if (!$this->http->ParseForm('submitForm')) {
            return false;
        }

        if (!$this->http->PostForm()) {
            return false;
        }
        sleep(5);

        if (!$this->http->ParseForm('PAResForm')) {
            return false;
        }

        if (!$this->http->PostForm()) {
            return false;
        }

        if (!$this->http->ParseForm('returnForm')) {
            return false;
        }

        if (!$this->http->PostForm()) {
            return false;
        }

        $successMessage = $this->http->FindSingleNode('//p[contains(text(), "Your request for Purchase JPMiles has been processed.")]');

        if ($successMessage) {
            $this->ErrorMessage = $successMessage;

            return true;
        }

        /*
        // payment form new (ушла на селениум... много js... не удалосб разобраться куда и что заполнять)
        if(!$this->http->ParseForm('payment'))
            return false;
        $this->http->Form['cardNumber'] = $creditCard['CardNumber'];
        $this->http->Form['cardHolderName'] = $creditCard['Name'];
        $this->http->Form['cvvNumber'] = $creditCard['SecurityNumber'];
        $this->http->Form['expiryMonth'] = sprintf('%02d', $creditCard['ExpirationMonth']);
        if (strlen($creditCard['ExpirationYear']) > 2)
            $creditCard['ExpirationYear'] = substr($creditCard['ExpirationYear'], -2);
        $this->http->Form['expiryYear'] = $creditCard['ExpirationYear'];//expiry-date
        $this->http->Form['cardType'] = 'credit';
//        $this->http->Form['???'] = strtoupper($creditCard['Type']);// schem
        if (!$this->http->PostForm())
            return false;
        */

        /*selenium try??
                $selenium = $this->getSeleniumChecker();
                $url = 'https://www.citruspay.com/JetPrivilege';
                try {
                    $result = $selenium->seleniumBuy($body, $fields, $numberOfMiles, $creditCard);
                    $this->ErrorMessage = $selenium->ErrorMessage;
                }
                catch(\Exception $e) {
                    throw $e;
                }
                finally {
                    $selenium->http->cleanup();
                }
                if (isset($result))
                    return $result;
                else
                    return false;

        */

        /*  old form
        $link = $this->http->FindSingleNode('//a[contains(@href, "vpcpay?card=Visa")]/@href');
        if (!isset($link))
            return false;
        $this->http->NormalizeURL($link);
        $this->http->GetURL($link);

        // payment form
        if (!$this->http->ParseForm('paymentDetail'))//////payment??
            return false;
        $this->http->Form['cardno'] = $creditCard['CardNumber'];//creditCardNumber
        $this->http->Form['cardexpirymonth'] = sprintf('%02d', $creditCard['ExpirationMonth']);//expiry-month
        if (strlen($creditCard['ExpirationYear']) > 2)
            $creditCard['ExpirationYear'] = substr($creditCard['ExpirationYear'], -2);
        $this->http->Form['cardexpiryyear'] = $creditCard['ExpirationYear'];//expiry-date
        //+cardholder
        $this->http->Form['cardsecurecode'] = $creditCard['SecurityNumber'];//cvv
        if (!$this->http->PostForm())
            return false;
        if (!$this->http->ParseForm('PAReq'))
            return false;
        sleep(5);
        if (!$this->http->PostForm())
            return false;
        if (!$this->http->ParseForm(null, 1, true, '//form[contains(@action, "/ssl?paymentId=")]') || !$this->http->PostForm())
            return false;
        $this->http->Log('current url: ' . $this->http->currentUrl());
        if (!$this->http->FindSingleNode('//td[contains(text(), "Please wait while your payment is processed")]') || !preg_match('/\/ssl\?paymentId=\d+$/', $this->http->currentUrl()))
            return false;
        sleep(3);
        $this->http->GetURL($this->http->currentUrl());
        // there's possible meta redirect
        if (preg_match('/\/ssl\?paymentId=\d+$/', $this->http->currentUrl()) && $message = $this->http->FindSingleNode('//*[contains(text(), "Your payment has been") and b[contains(text(), "approved")]]')) {
            $this->ErrorMessage = $message;
            return true;
        }
        elseif(stripos($this->http->currentUrl(), '/Process/MIGSResponse.aspx?') !== false && $this->http->XPath->query('//input[@name="__VIEWSTATE"]')->length > 0) {
            $this->ErrorMessage = 'Success';
            return true;
        }


        return false;
        */
        sleep(5);

        return false;
    }

    public function seleniumBuy($body, $fields, $numberOfMiles, $creditCard)
    {
        $this->http->SetBody($body);
        $this->http->SaveResponse();

//        $this->http->SetBody($body);
//
        ////        $this->logger->info($body);
//
//        if ($this->http->ParseForm("form1")) {
//            $this->logger->debug("sending form");
//            $this->http->PostForm();
//        }
//        else{
//            $this->logger->error("failed to send form");
//            return false;
//        }

//        $this->driver->executeScript("document.getElementById('form1').submit();");

//        $this->http->GetURL($url);
//        $input = $this->waitForElement(\WebDriverBy::id('txtJPMilesPurchaseJPMiles'), $this->loadTimeout);
//        if (!$input){
//            return $this->fail('can\'t find field JPMiles Required');
//        }
//        $input->sendKeys($numberOfMiles);
//        $this->waitForElement(\WebDriverBy::id('MainBody_IWOVID_item_1_rdoMyAccount'), $this->waitTimeout)->click();
//
//        $this->waitForElement(\WebDriverBy::id('MainBody_IWOVID_item_1_btnBuy'), $this->waitTimeout)->click();
        sleep(20);

        return $this->fail('testing break');
    }

    /**
     * @return Purchase
     */
    protected function getSeleniumChecker()
    {
        $cookies = $this->http->GetCookies('.citruspay.com', '/', true);
        $this->http->Log('<pre>' . json_encode($cookies, JSON_PRETTY_PRINT) . '</pre>', null, false);
        $checker2 = clone $this;
        $this->http->brotherBrowser($checker2->http);
        $this->logger->notice("Running Selenium...");
        $checker2->UseSelenium();
        $checker2->http->SetProxy($this->http->GetProxy());
        $checker2->useChromium();
        $checker2->http->saveScreenshots = true;
        $checker2->http->start();
        $checker2->Start();

        foreach ($cookies as $key => $value) {
            $checker2->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".citruspay.com"]);
        }

        return $checker2;
    }

    protected function fail($message = null)
    {
        if (isset($message)) {
            $this->logger->info($message);
        }
        $this->saveResponse();

        return false;
    }
}
