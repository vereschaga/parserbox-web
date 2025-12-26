<?php

class TAccountCheckerTunisair extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://myfidelys.tunisair.com/en/space';

    /** @var CaptchaRecognizer */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://myfidelys.tunisair.com/en/login', ['Referer' => 'https://www.tunisair.com/en-tn']);

        if (!$this->http->ParseForm(null, '//form[contains(@action, "/login")]')) {
            return false;
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $captcha = $this->parseCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }

        return true;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm() && $this->http->Response['code'] !== 500) {
            return false;
        }

        $this->http->RetryCount = 2;

        // AccountID: 4479075, 1450572
        if ($this->http->currentUrl() == self::REWARDS_PAGE_URL && $this->http->Response['code'] === 500) {
            $this->http->GetURL("https://myfidelys.tunisair.com/fr/space");
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }
        // Invalid credentials
        if ($message = $this->http->FindPreg("/(Invalid Identifier or Password|Identifiant ou Mot de passe incorrecte\.)/ims")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/(Données non trouvées)/ims")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/(Une erreur s\&\#039;est produite)/ims")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        // Balance - Total Miles Prime
        $this->SetBalance($this->http->FindSingleNode('//div[contains(text(), "Balance of Award Miles") or contains(text(), "Solde Miles") or contains(text(), "Total Miles Prime")]/following-sibling::div[contains(@class, "details-miles")]/strong'));
        // Number
        $this->SetProperty('Number',
            $this->http->FindSingleNode('//div[@class = "desc-user-carte"]', null, true, '/(\d{9})/', 0)
            ?? $this->http->FindSingleNode('//div[@class = "name-user-carte"]', null, true, '/(\d{9})/')
        );
        // Name
        $this->SetProperty("Name", beautifulName(
            $this->http->FindSingleNode('//div[@class = "desc-user-carte name-user-carte"]', null, true, '/([A-Z ]+)/')
            ?? $this->http->FindSingleNode('//div[@class = "name-user-carte"]', null, true, '/([A-Z ]+)/')
        ));
        // Total Miles Qualifiant
        $this->SetProperty("QualifyingMiles", $this->http->FindSingleNode('//div[contains(text(), "Qualifying Miles status") or contains(text(), "Total Miles Qualifiant")]/following-sibling::div[contains(@class, "details-miles")]/strong'));
        // Miles Reliquat
        $lastYearQualifyingMiles = $this->http->FindSingleNode('//div[contains(text(), "Qualifying Miles status") or contains(text(), "Total Miles Qualifiant")]/following-sibling::div[contains(@class, "details-miles")]', null, true, "/Miles Reliquat\s+:\s+(\d+)/ims");
        $this->SetProperty("LastYearQualifyingMiles", $lastYearQualifyingMiles);
        // Status
        $this->SetProperty("Level", ucfirst(strtolower(
            $this->http->FindSingleNode('//span[@class = "etat-carte"]') // word on card
            ?? $this->http->FindSingleNode('(//a[starts-with(@href, "https://fidelys.tunisair.com/fr/cartes/carte-")]/@href)[1]', null, true, '/carte-(\w+)/') // link to card
        )));

        // Il vous manque encore
        // ... MQ pour passer au SILVER
        $this->SetProperty('QualifyingMilesToNextLevel', $this->http->FindSingleNode('//div[@class = "desc" and contains(., "Il vous manque encore") and contains(., "passer")]/strong[1]', null, true, '/(\d+) MQ/'));
        // Ou ... vols
        $this->SetProperty('QualifyingFlightsToNextLevel', $this->http->FindSingleNode('//div[@class = "desc" and contains(., "Il vous manque encore") and contains(., "passer")]/strong[2]', null, true, '/(\d+) vol/'));

        // Expiration date  // refs #8621
        $latestVoyageDateText = $this->http->FindSingleNode('(//td[contains(., "Voyage: ")]/following-sibling::td[1])[1]');
        $this->logger->debug("[Last Activity]: {$latestVoyageDateText}");

        if (!$latestVoyageDateText) {
            return;
        }

        $this->SetProperty('LastActivity', $latestVoyageDateText);

        if ($latestVoyageDate = strtotime($this->ModifyDateFormat($latestVoyageDateText))) {
            $exp = strtotime('+3 years', $latestVoyageDate);

            if ($exp < time()) {
                $this->logger->notice('expiration date is in the past, skipping');

                return;
            }
            $this->SetExpirationDate($exp);
        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//form//div[@class = "g-recaptcha"]/@data-sitekey');

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            'pageurl' => $this->http->currentUrl(),
            'proxy'   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        return $this->http->FindSingleNode('//form[@id="logout-form"]/@id');
    }
}
