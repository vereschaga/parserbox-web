<?php

// case #10273

namespace AwardWallet\Engine\goldpassport\Transfer;

use AwardWallet\Engine\ProxyList;

class Transfer extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;
    public const TIME_OUT = 7;
    public const MIN_POINTS_FOR_TRANSFER = 5000;

    protected $providerMap = [
        'aeromexico'   => 'AM',
        'airchina'     => 'NA',
        'airfrance'    => 'KF',
        'klm'          => 'KF',
        'ana'          => 'NH',
        'aa'           => 'AA',
        'asia'         => 'CX',
        'asiana'       => 'OZ',
        'british'      => 'BA',
        'china'        => 'CI',
        'chinaeastern' => 'MU',
        'delta'        => 'DL',
        'skywards'     => 'EK',
        'etihad'       => 'EY',
        'hawaiian'     => 'HA',
        'japanair'     => 'JL',
        'jetairways'   => 'QJ',
        'korean'       => 'KE',
        'lanpass'      => 'LA',
        'lufthansa'    => 'LH',
        'qantas'       => 'QT',
        'qmiles'       => 'QR',
        'bruneiair'    => 'BI',
        'singaporeair' => 'SQ',
        'rapidrewards' => 'WN',
        'thaiair'      => 'TA',
        'mileageplus'  => 'UA',
        'virgin'       => 'VS',
    ];

    public function InitBrowser()
    {
        $this->UseSelenium();

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyDOP());
        } else {
            $this->http->SetProxy($this->proxyPurchase());
        }
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function transferMiles($targetProviderCode, $targetAccountNumber, $numberOfMiles, $fields = [])
    {
        $this->http->log('[INFO] ' . __METHOD__);

        //		if(!$this->IsLoggedIn()){
        //			$this->AccountFields['Login'] = $fields['Login'];
        //			$this->AccountFields['Pass'] = $fields['Pass'];
        //		}

        $this->http->GetURL('https://goldpassport.hyatt.com/content/gp/en/home.html');

        $this->http->Log('Login to get started');

        if (empty($this->AccountFields['Login']) || empty($this->AccountFields['Pass'])) {
            throw new \UserInputError('Empty field or filds: Login, Password');
        }

        $inputFieldsMap = [
            'username' => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
        ];

        $this->driver->executeScript("
			jQuery(\"a[class='hnav-item--signin loading']\").click();
		");

        foreach ($inputFieldsMap as $inputName => $inputValue) {
            if ($elem = $this->waitForElement(\WebDriverBy::xpath("//input[contains(@name, '" . $inputName . "')]"), self::TIME_OUT)) {  //xpath("//input[contains(@name, '".$inputName."')]")
                $elem->sendKeys($inputValue);
                $this->http->Log('Inputs filled');
            } else {
                $this->http->Log("Could not find input field for {$inputName}", LOG_LEVEL_NORMAL);

                return false;
            }
        }

        //		submit

        if ($element = $this->waitForElement(\WebDriverBy::xpath("//button[contains(@class, 'hnav-form-submit')]"), self::TIME_OUT)) { //className('hnav-form-submit')
            $element->click();
            $this->http->Log('Login form submited');
        } else {
            throw new \ProviderError('Login form not found');
        }

        //		errors
        //		$xpathErr = "//span[contains(@class, 'message-blank ng-binding')]";
        //		$el = $this->waitForElement(\WebDriverBy::xpath("//span[contains(@class, 'message-blank ng-binding')]/preceding-sibling::input/@placeholder"), self::TIME_OUT);
        //		$error = $this->waitForElement(\WebDriverBy::xpath($xpathErr), self::TIME_OUT);
        //		if($el && $error)
        //			throw new \UserInputError($el->getText() . ':' .  $error->getText());

        $xp = "//b[contains(text(), 'password you entered does not correspond with your Hyatt Gold Passport')]";
        $err = $this->waitForElement(\WebDriverBy::xpath($xp), self::TIME_OUT);

        if ($err) {
            throw new \UserInputError($err->getText());
        }

        $xp = "//text()[contains(normalize-space(.), 'Need help with password?')]";
        $err = $this->waitForElement(\WebDriverBy::xpath($xp), self::TIME_OUT);

        if ($err) {
            throw new \UserInputError("incorrect Login or Password");
        }

        $this->http->GetURL('https://goldpassport.hyatt.com/gp/en/awards/points_to_miles.jsp');

        $partnerCode = $this->providerMap[$targetProviderCode];

        if (!$partnerCode) {
            throw new \EngineError('Failed to find Hyatt target provider code');
        }

        $this->driver->executeScript("
			var el = jQuery(\"#partnerCode\");
			var code = jQuery(\"#partnerCode option[value ^= '" . $partnerCode . "']\").attr('value');
			el.val(code);
		");

        if ($programMembership = $this->waitForElement(\WebDriverBy::xpath("//input[@name='partnerNumber']"))) {
            $programMembership->sendKeys($targetAccountNumber);
        } else {
            throw new \UserInputError('partner number field is not filled. Value: ' . $targetAccountNumber);
        }

        //		Hyatt Gold Passport Point conversions can be made in 1,250 increments.
        $stepForPoints = 0;
        $numberOfMilesToPoint = 0;
        $elementForStep = $this->waitForElement(\WebDriverBy::xpath("//p[contains(text(), 'Hyatt Gold Passport Point conversions') or contains(text(), 'World Of Hyatt Point conversions')]"), self::TIME_OUT);

        if (preg_match('#.+\s+in\s+(?<Step>[\d,]+)\s+increments#i', $elementForStep->getText(), $m)) {
            $stepForPoints = (int) str_replace(',', '', $m['Step']);
        }

        if (self::MIN_POINTS_FOR_TRANSFER < $numberOfMiles) {
            for ($i = self::MIN_POINTS_FOR_TRANSFER; $i < $numberOfMiles; $i += $stepForPoints) {
                if ($i === $numberOfMiles) {
                    $numberOfMilesToPoint = $numberOfMiles;

                    break;
                } else {
                    throw new \UserInputError("Invalid value for transfer. Transfer step is {$stepForPoints}. The minimul number of points for transfer = " . self::MIN_POINTS_FOR_TRANSFER);
                }
            }
        } elseif (self::MIN_POINTS_FOR_TRANSFER === $numberOfMiles) {
            $numberOfMilesToPoint = $numberOfMiles;
        } else {
            throw new \UserInputError("The minimum number of points for transfer = " . self::MIN_POINTS_FOR_TRANSFER);
        }

        $this->driver->executeScript("
			var el = jQuery(\"#points\");
			var code = jQuery(\"#points option[value = '" . $numberOfMilesToPoint . "']\").attr('value');
			el.val(code);
		");

        if (!$btn = $this->waitForElement(\WebDriverBy::name('/goldpassport/droplet/PointsToMiles.convert'), self::TIME_OUT)) {
            $this->ErrorMessage = 'Button(Submit) not found';

            return false;
        }

        $btn->click();

        //		error
        $errs = null;
        $xpath = "//*[contains(@class, 'error-')]";

        if ($this->waitForElement(\WebDriverBy::xpath($xpath))) {
            $errors = $this->driver->findElements(\WebDriverBy::xpath($xpath));

            foreach ($errors as $error) {
                $errs .= $error->getText() . '; ';
            }

            throw new \UserInputError($errs);
        }

        //		succcess message
        $msg = 'Your Gold Passport points have been converted to miles';
        $xpathSuccessMsg = "//p[contains(text(), '" . $msg . "')]";
        $successMsg = $this->waitForElement(\WebDriverBy::xpath($xpathSuccessMsg));

        if ($successMsg) {
            $this->ErrorMessage = $msg;

            return true;
        }

        return false;
    }

    public function oldTransferMiles($targetProviderCode, $targetAccountNumber, $numberOfMiles, $fields = [])
    {
        $this->http->log('[INFO] ' . __METHOD__);

        $this->http->log('[DEBUG] all fields:');
        $this->http->log(json_encode([
            'targetProviderCode'  => $targetProviderCode,
            'targetAccountNumber' => $targetAccountNumber,
            'numberOfMiles'       => $numberOfMiles,
            'fields'              => $fields,
        ], JSON_PRETTY_PRINT));

        if (!isset($this->providerMap[$targetProviderCode])) {
            throw new \UserInputError('Unsupported target provider');
        }

        if (!$this->isLoggedIn()) {
            //			$this->logger->info(print_r($fields, true));
            $this->AccountFields['Login'] = $fields['Login'];
            $this->AccountFields['Pass'] = $fields['Pass'];
            $this->loadLoginForm();
            $this->login();
        }

        $this->http->getURL('https://goldpassport.hyatt.com/gp/en/awards/points_to_miles.jsp');

        $status = $this->http->parseForm('PointsToMiles');

        if (!$status) {
            throw new \EngineError('Failed to parse rewards transfer form');
        }

        $partnerCode = $this->awCodeToSiteCode($targetProviderCode);

        if (!$partnerCode) {
            throw new \EngineError('Failed to find Hyatt target provider code');
        }

        $this->http->setInputValue('partnerCode', $partnerCode);
        $this->http->setInputValue('partnerNumber', $targetAccountNumber);
        $this->http->setInputValue('points', $numberOfMiles);
        $this->http->setInputValue('/goldpassport/droplet/PointsToMiles.convert.x', 42);
        $this->http->setInputValue('/goldpassport/droplet/PointsToMiles.convert.y', 8);

        $status = $this->http->postForm();

        if (!$status) {
            throw new \EngineError('Failed to POST rewards transfer form');
        }

        return $this->checkResult();
    }

    protected function checkResult()
    {
        $this->http->log('[INFO] ' . __METHOD__);

        $successRe = '#Your\s+Gold\s+Passport\s+points\s+have\s+been\s+converted\s+to\s+miles#i';
        $success = $this->http->findPreg($successRe);

        if ($success) {
            $this->http->log('[INFO] ' . $success);
            $this->ErrorMessage = $success;

            return true;
        }

        $errors = $this->http->findNodes('//*[contains(@class, "error-")]');
        $errors = array_map(function ($e) { return trim($e); }, $errors);
        $errors = array_filter($errors);

        if ($errors) {
            $msg = implode(' ', $errors);

            throw new \UserInputError($msg);
        }
    }

    protected function awCodeToSiteCode($targetProviderCode)
    {
        $this->http->log('[INFO] ' . __METHOD__);

        $goldpassportTargetProviderCode = $this->providerMap[$targetProviderCode];

        if (!$goldpassportTargetProviderCode) {
            $this->http->Log('Empty Hyatt target provider code');

            return false;
        }

        $codes = $this->http->FindNodes('//option[contains(@value, "' . $goldpassportTargetProviderCode . ':")]/@value');

        if (count($codes) > 1) {
            $this->http->Log('Expected only one rewards transfer code per provider, several found', LOG_LEVEL_ERROR);

            return false;
        }

        if (!$codes) {
            $this->http->Log('None rewards transfer found', LOG_LEVEL_ERROR);

            return false;
        }

        return $codes[0];
    }
}
