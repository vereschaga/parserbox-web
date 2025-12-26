<?php

namespace AwardWallet\Engine\singaporeair\Transfer;

class Transfer extends \TAccountCheckerSingaporeair
{
    public static $providersMap = [
        'velocity' => 'VA',
    ];

    private $targetProviderCode;

    private $targetAccountNumber;

    private $numberOfMiles;

    private $previouslyAttachedAccountNumber = null;

    public function transferMiles($targetProviderCode, $targetAccountNumber, $numberOfMiles, $fields = [])
    {
        $this->targetProviderCode = $targetProviderCode;
        $this->targetAccountNumber = $targetAccountNumber;
        $this->numberOfMiles = $numberOfMiles;
        //$this->ArchiveLogs = true; // TODO: Move to base class
        try {
            $this->preliminaryParametersCheck();
            $this->attachAccount();
            $this->chooseTargetProvider();
            $this->setParameters();
            $this->submit();
            $this->cleanupAccount();

            return true;
        } catch (\CheckException $e) {
            $this->cleanupAccount();

            throw $e;
        } catch (\Exception $e) {
            $this->cleanupAccount();
            //$this->lastError = $e->getMessage();
            $this->http->Log($e->getMessage(), LOG_LEVEL_ERROR);

            return false;
        }
    }

    protected function preliminaryParametersCheck()
    {
        $this->http->Log('==BEGIN=> Preliminary rewards transfer parameters check');

        if (!isset(self::$providersMap[$this->targetProviderCode])) {
            $this->http->Log('Unsupported target provider', LOG_LEVEL_ERROR);

            throw new \UserInputError("Target provider is not supported");
        }

        if ($this->numberOfMiles < 5000) {
            throw new \UserInputError('Minimum miles for conversion = 5000 miles');
        }
        $this->http->Log('==END=> Preliminary rewards transfer parameters check succeeded');
    }

    protected function chooseTargetProvider()
    {
        $this->http->Log('==BEGIN=> Choosing target provider for conversion');

        $this->http->GetURL('https://www.singaporeair.com/en_UK/ppsclub-krisflyer/my-statement/convert-miles/');

        if ($errors = $this->http->FindNodes('//*[@class="alertMsg"]')) {
            throw new \CheckException(implode(' ', $errors), ACCOUNT_PROVIDER_ERROR);
        }

        $status = $this->http->ParseForm('kfOFFPConvertMilesForm');

        if (!$status) {
            throw new \Exception('Failed to parse rewards transfer preparation form');
        }
        $this->http->SetInputValue('offPForConversion', self::$providersMap[$this->targetProviderCode]);
        $this->http->PostForm();

        if (!$status) {
            throw new \Exception('Failed to post rewards transfer preparation form');
        }

        if ($errors = $this->http->FindNodes('//*[@class="alertMsg"]')) {
            throw new \CheckException(implode(' ', $errors), ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->Log('==END=> Choosing target provider for conversion');
    }

    protected function setParameters()
    {
        $this->http->Log('==BEGIN=> Setting parameters for conversion');

        $status = $this->http->ParseForm('kfOFFPConvertMilesForm');

        if (!$status) {
            throw new \Exception('Failed to parse rewards transfer convertion form');
        }

        $this->http->SetInputValue('milesToConvert', $this->numberOfMiles);
        $this->http->PostForm();

        if (!$status) {
            throw new \Exception('Failed to post rewards transfer convertion form');
        }

        $this->http->Log('==END=> Setting parameters for conversion');
    }

    protected function submit()
    {
        $this->http->Log('==BEGIN=> Submitting');
        $status = $this->http->ParseForm('kfOFFPConvertMilesForm');

        if (!$status) {
            throw new \Exception('Failed to parse rewards transfer confirmation form');
        }

        if (isset($this->idle) && $this->idle) {
            return;
        }

        $this->http->PostForm();

        if (!$status) {
            throw new \Exception('Failed to post rewards transfer confirmation form');
        }

        if ($this->http->FindPreg('#Successful\s+Miles\s+Conversion#i')) {
            $this->http->Log('Rewards transfer succeeded');
        } else {
            throw new \Exception('Unknown response');
        }
        $this->http->Log('==END=> Submitting');
    }

    protected function attachAccount($accountNumber)
    {
        $this->http->Log("==BEGIN=> Attaching FF account $accountNumber");

        $this->http->GetURL('https://www.singaporeair.com/otherFrequentFlyer.form');

        $providerCode = $providersMap[$this->targetProviderCode];
        $deleteXpath = sprintf('//a[contains(@href, "otherFrequentFlyer.form?deleteCode=%s&deleteNumber=%s")]/@href',
            $providerCode,
            $accountNumber
        );
        $deleteLink = $this->http->findSingleNode($deleteXpath);

        if (preg_match('/offpLinkStatus=Y/', $deleteLink)) {
            return true;
        }

        $linkXpath = sprintf('//a[contains(@href, "otherFrequentFlyer.form?linkOFFPCode=%s&linkNumber=%s")]/@href',
            $providerCode,
            $accountNumber
        );
        $linkLink = $this->http->findSingleNode($linkXpath);

        $status = $this->http->ParseForm('otherFrequentFlyer');

        if (!$status) {
            throw new \Exception('Failed to parse frequent flyer account attach form');
        }

        $providersMap = [
            'velocity' => 'VA',
        ];

        if (!isset($providersMap[$this->targetProviderCode])) {
            throw new \Exception("Unsupported target provider {$this->targetProviderCode}. It should be checked if provider site support it and update transferer code if so, or remove from providers list otherwise");
        }

        $inputFields = [
            'otherFrequentFlyerIdty' => 'otherFrequentFlyer',
            'operationType'          => '',
            'linkedOFFCode'          => '',
            'linkedOffpNumber'       => '',
            'editOffpNumber'         => '',
            'rowIdentifier'          => '',
            'editOffpCode'           => '',
            'offPrograms'            => $providersMap[$this->targetProviderCode],
            'addOffpNumber'          => $accountNumber,
        ];

        foreach ($inputFields as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }

        $status = $this->http->PostForm();

        if (!$status) {
            throw new \Exception('Failed to post frequent flyer account attach form');
        }

        if (!$this->http->FindPreg('#Your\s+partner\s+frequent\s+flyer\s+membership\s+number\s+has\s+been\s+updated\s+successfully#i')) {
            throw new \Exception('Failed to attach FF account');
        }

        $this->http->Log("==END=> Attaching FF account $accountNumber");
    }

    protected function linkAccount($accountNumber)
    {
        $this->http->Log("==BEGIN=> Attaching FF account $accountNumber");

        $this->http->GetURL('https://www.singaporeair.com/otherFrequentFlyer.form?linkOFFPCode=' . $providersMap[$this->targetProviderCode] . '&linkNumber=' . $accountNumber);

        $this->http->FormURL = 'https://www.singaporeair.com/otherFrequentFlyer.form';
        unset($this->http->Form);
        $inputFields = [
            'otherFrequentFlyerIdty' => 'otherFrequentFlyer',
            'operationType'          => '',
            'linkedOFFCode'          => '',
            'linkedOffpNumber'       => '',
            'editOffpNumber'         => '',
            'rowIdentifier'          => '',
            'editOffpCode'           => '',
            'offPrograms'            => '-1',
            'linkOFFPCode'           => $providersMap[$this->targetProviderCode],
            'linkNumber'             => $accountNumber,
            'ajaxian'                => 'true',
        ];

        foreach ($inputFields as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }

        $status = $this->http->PostForm();

        if (!$status) {
            throw new \Exception('Failed to post frequent flyer account link verification form');
        }

        $status = $this->http->ParseForm('otherFrequentFlyerLnkForm');

        if (!$status) {
            throw new \Exception('Failed to parse frequent flyer account link form');
        }

        $this->http->FormURL = 'https://www.singaporeair.com/otherFrequentFlyer.form';
        $inputFields = [
            'otherFrequentFlyerIdty' => 'otherFrequentFlyer',
            'linkRegisterUrl'        => 'NO',
            '_linkRegisterUrl'       => 'on',
            'operationType'          => 'LNK',
            'linkedOffpNumber'       => $accountNumber,
            'linkedOFFCode'          => $providersMap[$this->targetProviderCode],
            'action'                 => 'OFFP',
        ];

        foreach ($inputFields as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }
        $status = $this->http->PostForm();

        if (!$status) {
            throw new \Exception('Failed to post frequent flyer account link form');
        }

        if ($errors = $this->http->FindNodes('//*[@class="alertMsg"]')) {
            throw new \ProviderError(implode(' ', $errors));
        } // Is it always provider error?

        if (!$this->http->FindPreg('#Your\s+partner\s+frequent\s+flyer\s+membership\s+number\s+has\s+been\s+successfully\s+linked\s+with\s+your\s+KrisFlyer\s+account#')) {
            throw new \Exception('Failed to link FF account');
        }

        $this->http->Log("==END=> Attaching FF account $accountNumber");
    }

    protected function deleteAccount($oldAccountNumber)
    {
        $this->http->Log("==BEGIN=> Detaching FF account $oldAccountNumber");

        $this->http->GetURL('https://www.singaporeair.com/en_UK/ppsclub-krisflyer/my-personal-details/other-frequent-flyer-prog/');

        $this->http->GetURL('https://www.singaporeair.com/otherFrequentFlyer.form?deleteCode=VA&deleteNumber=2120072011&offpLinkStatus=Y');

        $this->http->FormURL = 'https://www.singaporeair.com/otherFrequentFlyer.form';
        unset($this->http->Form);
        $fields = [
            'otherFrequentFlyerIdty' => 'otherFrequentFlyer',
            'operationType'          => '',
            'linkedOFFCode'          => '',
            'linkedOffpNumber'       => '',
            'editOffpNumber'         => '',
            'rowIdentifier'          => '',
            'editOffpCode'           => '',
            'offPrograms'            => '-1',
            'deleteCode'             => self::$providersMap[$this->targetProviderCode],
            'deleteNumber'           => $oldAccountNumber,
            'offpLinkStatus'         => 'Y',
            'ajaxian'                => 'true',
        ];

        foreach ($fields as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }
        $status = $this->http->PostForm();

        if (!$status) {
            throw new \Exception('Failed to post account detaching preliminary form');
        }

        $status = $this->http->ParseForm('otherFrequentFlyerDelForm');

        if (!$status) {
            throw new \Exception('Failed to parse account detaching confirmation form');
        }
        $fields = [
            'otherFrequentFlyerIdty' => 'otherFrequentFlyer',
            'operationType'          => 'Delete',
            'rowIdentifier'          => self::$providersMap[$this->targetProviderCode],
            'partnerLinkStatus'      => 'Y',
            'action'                 => 'OFFP',
            'editOffpNumber'         => $oldAccountNumber,
        ];

        foreach ($fields as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }
        $status = $this->http->PostForm();

        if (!$status) {
            throw new \Exception('Failed to post account detaching confirmation form');
        }

        if (!$this->http->FindPreg('#Your\s+partner\s+frequent\s+flyer\s+membership\s+number\s+has\s+been\s+deleted\s+successfully#i')) {
            throw new \Exception('Failed to detach account');
        }

        $this->http->Log("==END=> Detaching FF account $oldAccountNumber");
    }

    protected function cleanupAccount()
    {
        $this->http->Log("==BEGIN=> Cleanup");

        if ($this->previouslyAttachedAccountNumber) {
            $this->detachAccount($this->targetAccountNumber);
        }
        $this->http->Log("==END=> Cleanup");
    }
}
