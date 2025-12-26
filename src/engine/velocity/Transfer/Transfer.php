<?php

namespace AwardWallet\Engine\velocity\Transfer;

class Transfer extends \TAccountChecker
{
    use \SeleniumCheckerHelper;

    public $timeout = 30;

    private $targetProviderCode;

    private $targetAccountNumber;

    private $sourceRewardsQuantity;

    public function InitBrowser()
    {
        $this->UseSelenium();
        $this->keepCookies(false);
        $this->keepSession(false);
        $this->AccountFields['BrowserState'] = null;
    }

    public function LoadLoginForm()
    {
        if ('singaporeair' !== $this->TransferFields['targetProvider']) {
            throw new \UserInputError('Unsupported target provider');
        }

        if (5000 > $this->TransferFields['numberOfMiles']) {
            throw new \UserInputError('Unsupported source rewards quantity');
        }

        //todo move load login form here
        return true;
    }

    public function Login()
    {
        //todo move login here
        return true;
    }

    public function transferMiles($targetProviderCode, $targetAccountNumber, $numberOfMiles, $fields = [])
    {
        $this->targetProviderCode = $targetProviderCode;
        $this->targetAccountNumber = $targetAccountNumber;
        $this->sourceRewardsQuantity = $numberOfMiles;
        $this->http->driver->start();
        $this->Start();

        try {
            $this->transferInternal();

            return true;
        } catch (\CheckException $e) {
            $this->saveResponse();

            throw $e;
        } catch (\Exception $e) {
            $this->http->Log($e->getMessage(), LOG_LEVEL_ERROR);
            $this->saveResponse();

            return false;
        }
    }

    private function transferInternal()
    {
        $this->loginsel();
        $this->fillTransferParameters();
        //		here will be intermediate step
        $this->submit();
    }

    private function loginsel()
    {
        $this->http->Log('=BEGIN==> LOGIN END');
        $this->driver->get('http://www.velocityfrequentflyer.com/content/');
        $loginFields = [
            'customer_id'   => $this->AccountFields['Login'],
            'customer_pass' => $this->AccountFields['Pass'],
        ];

        foreach ($loginFields as $key => $value) {
            $this->driver->executeScript('document.getElementById("' . $key . '").value = "' . $value . '"');
        }
        $this->driver->findElement(\WebDriverBy::id('go'))->click();
        // TODO: Handle login errors more fully
        if (!$this->waitForElement(\WebDriverBy::xpath('//a/img[@alt="Logout"]'), $this->timeout)) {
            throw new \Exception('Login failed');
        }
        $this->http->Log('==END===> LOGIN END');
    }

    private function fillTransferParameters()
    {
        $this->http->Log('=BEGIN==> FILL TRANSFER PARAMETERS');
        $this->driver->get('https://www.velocityfrequentflyer.com/content/MyAccount/PointsTransferAirlines/Transfer/index.html?programCode=SQ');

        if ($elems = $this->driver->findElements(\WebDriverBy::xpath('//div[@id="formError" and string-length(normalize-space(.)) > 1]'))) {
            $errors = [];

            foreach ($elems as $e) {
                $errors[] = $e->getText();
            }

            throw new \ProviderError(implode($errors)); // Is it always provider error?
        }
        $this->driver->findElement(\WebDriverBy::id('velocityPointsScroll'))->sendKeys($this->sourceRewardsQuantity);
        $this->driver->executeScript('document.getElementById("velocityPointsScroll").onchange()');
        $this->driver->findElement(\WebDriverBy::xpath('//a/img[@alt="next" and not(contains(@src, "grey.png"))]'))->click();
        $this->http->Log('==END===> FILL TRANSFER PARAMETERS');
    }

    private function submit()
    {
        $this->http->Log('=BEGIN==> SUBMIT');
        // TODO: Implement
        $this->http->Log('==END===> SUBMIT');
    }
}
