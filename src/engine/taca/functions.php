<?php

class TAccountCheckerTaca extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        return true;
    }

    public function Login()
    {
        //$this->http->PostForm();
        $this->http->getURL('http://www.lifemiles.com/lib/ajax/ENG/getSession.aspx?user=' . $this->AccountFields['Login'] . '&pass=' . $this->AccountFields['Pass']);
        $error = $this->http->FindPreg("/<script>ejecutarError\('(.*)'\);<\/script>/ims");

        if (isset($error) && !empty($error)) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = $error;

            return false;
        }

        return true;
    }

    public function Parse()
    {
        $this->http->GetURL("http://www.lifemiles.com/eng/myb/est/estmai.aspx#");
        $this->SetProperty('Name', $this->http->FindSingleNode('//span[@id="ctl00_cphContent_spanMiembro"]'));
        $this->SetProperty('Number', $this->http->FindSingleNode('//span[@id="ctl00_cphContent_spanFtnum"]'));
        $this->SetBalance($this->http->FindSingleNode('//span[@id="ctl00_cphContent_spanMillasDisponibles"]'));
        $exp = $this->http->FindSingleNode('//div[@id="ctl00_cphContent_fecExp"]/span');

        if (isset($exp)) {
            $exp = strtotime($exp);

            if ($exp != false) {
                $this->SetExpirationDate($exp);
            }
        }
        $this->SetProperty('MemberSince', $this->http->FindSingleNode('//span[@id="ctl00_cphContent_spanMiembroDesde"]'));
        $this->SetProperty('Tier', $this->http->FindSingleNode('//span[@id="ctl00_cphContent_spanNivelAvTa"]'));
        $this->SetProperty('Historicearnedmiles', $this->http->FindSingleNode('//span[@id="ctl00_cphContent_spanMillasHistoricas"]'));
        $this->SetProperty('Historicredeemedmiles', $this->http->FindSingleNode('//span[@id="ctl00_cphContent_spanMillasRedimidas"]'));
    }

    /*function GetRedirectParams($targetURL = NULL){
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'http://www.lifemiles.com/eng/myb/est/estmai.aspx#';
        $arg["RequestMethod"] = "GET";
        $arg["CookieURL"] = "http://www.lifemiles.com/lib/ajax/ENG/getSession.aspx?user=".urlencode($this->AccountFields['Login']).
                                        "&pass=".urlencode($this->AccountFields['Pass']);
        $arg["URL"] = "http://www.lifemiles.com/lib/ajax/ENG/getSession.aspx?user=".urlencode($this->AccountFields['Login']).
                                        "&pass=".urlencode($this->AccountFields['Pass']);
        $arg["RedirectURL"] = "http://www.lifemiles.com/lib/ajax/ENG/getSession.aspx?user=".urlencode($this->AccountFields['Login']).
                                        "&pass=".urlencode($this->AccountFields['Pass']);
        return $arg;
    }*/
}
