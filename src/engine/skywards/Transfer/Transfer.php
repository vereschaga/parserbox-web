<?php

namespace AwardWallet\Engine\skywards\Transfer;

require_once __DIR__ . "/../TAccountCheckerSkywardsSelenium.php";
class Transfer extends \TAccountCheckerSkywardsSelenium
{
    //todo: check if user has enough miles
    public $idle = false;

    public function transferMiles($targetProviderCode, $targetAccountNumber, $numberOfMiles, $fields = [])
    {
        $numberOfMiles = intval($numberOfMiles);

        if ($numberOfMiles < 1000 || $numberOfMiles % 6000 !== 0) {
            throw new \UserInputError('The amount of points must be a multiple of 6000');
        } /*review*/
        $msg = "Transferring $numberOfMiles rewards to $targetProviderCode account $targetAccountNumber";
        $this->http->Log($msg);
        $this->http->GetURL('https://www.emirates.com/account/english/redeem-miles/heathrow-rewards.aspx?login=true');

        if (!$this->http->ParseForm('aspnetForm')) {
            return $this->checkErrors();
        }

        preg_match('/(\d{4})(\d{4})(\d{4})(\d{4})/i', $targetAccountNumber, $parts);

        if (count($parts) !== 5) {
            throw new \UserInputError('Invalid Target Account Number');
        } /*review*/

        $targetRewardsQuantity = $numberOfMiles / 3;
        $this->http->SetInputValue('ckbHRViewAgree', 'on');
        $this->http->SetInputValue('ctl00$MainContent$ctl00$btnHRViewSubmit', 'Submit');
        $this->http->SetInputValue('HRViewQtyText', $targetRewardsQuantity);
        $this->http->SetInputValue('HRViewQtyDropdown', $targetRewardsQuantity);
        $this->http->SetInputValue('HRViewText2', $parts[2]);
        $this->http->SetInputValue('ctl00$MainContent$ctl00$HRViewText3', $parts[3]);
        $this->http->SetInputValue('ctl00$MainContent$ctl00$HRViewText4', $parts[4]);

        if (!$this->http->PostForm()) {
            return false;
        }
        //throw new \Exception('Failed to post "Submit" form');

        if ($errMsg = $this->http->FindSingleNode("//div[@class='errorPanel']")) {
            throw new \ProviderError($errMsg);
        } // Is it always provider error?

        if (!$this->http->ParseForm('aspnetForm')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('ckbHRViewAgree', 'on');
        $this->http->SetInputValue('ctl00$MainContent$ctl00$btnHRReviewSubmit', 'Submit');

        if ($this->idle) {
            return true;
        }

        if (!$this->http->PostForm()) {
            return false;
        }
        //throw new \Exception('Failed to post "Submit" form');

        if ($id = $this->http->FindPreg('/Transaction\s+ID:\s*\d+/i')) {
            $this->ErrorMessage = $id;

            return true;
        }

        return false;
    }
}
