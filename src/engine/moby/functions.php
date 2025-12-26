<?php

class TAccountCheckerMoby extends TAccountChecker
{
    public $regionOptions = [
        ""       => "Select your country",
        "USA"    => "USA",
        "Italy"  => "Italy",
    ];

    private $siteUrl = "https://www.mobylines.com/moby-club/";
    private $ln = 'en';

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function LoadLoginForm()
    {
        $this->http->FilterHTML = false;
        $this->http->removeCookies();

        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->debug('Region => ' . $this->AccountFields['Login2']);

        // Define geo variables
        if ($this->AccountFields['Login2'] == 'Italy') {
            $this->siteUrl = "https://www.moby.it/moby-club/";
            $this->textBonusPoints = 'Totale punti bonus';
            $this->textPointsJustSpent = 'Totale punti già utilizzati';
            $this->ln = 'it';
        }
        $this->http->GetURL($this->siteUrl);

        $this->http->PostURL('https://www.moby.it/mds/widget/read.json', [
            'usr'       => $this->AccountFields['Login'],
            'pwd'       => $this->AccountFields['Pass'],
            'lingua'    => $this->ln,
            'compagnia' => 'moby',
        ], [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        return true;
    }

    public function checkErrors()
    {
        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        // Access is allowed
        if (isset($response->item->data->punti)) {
            return true;
        }

        // Invalid login or password
        if ($message = $this->http->FindPreg('/"errors":\[\{"label":"(.+?)","code":"1"/ui')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, false);

        // Card number
        $this->SetProperty("CardNumber", $response->item->data->id);
        // Current Points Balance
        $this->SetBalance($response->item->data->punti);
        // Name
        $this->SetProperty("Name", beautifulName("{$response->item->data->nome} {$response->item->data->cognome}"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        // $arg['CookieURL'] = $this->siteUrl . "/mds/web/mobyclub/wuser_load.xpd?mode=after_login&lang={$this->ln}";
        // $arg['SuccessURL'] = $this->siteUrl . "/mds/web/mobyclub/wuser_load.xpd?mode=after_login&lang={$this->ln}";

        return $arg;
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        if ($this->AccountFields['Login2'] == 'Italy') {
            $arg["RedirectURL"] = "https://www.moby.it/moby-club/";
        } else {
            $arg["RedirectURL"] = "https://www.mobylines.com/moby-club/";
        }
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions))) {
            $region = 'USA';
        }

        return $region;
    }
}
