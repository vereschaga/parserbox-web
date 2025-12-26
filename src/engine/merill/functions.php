<?php

require_once __DIR__ . '/../bankofamerica/functions.php';

class TAccountCheckerMerill extends TAccountCheckerBankofamerica
{
    //class TAccountCheckerMerill extends TAccountChecker{

    public function TuneFormFields(&$arFields, $values = null)
    {
        $result = Cache::getInstance()->get('merrill_acct_type_countries');

        if (($result !== false) && (count($result) > 1)) {
            $arFields["Login2"]["Options"] = $result;
        } else {
            $arFields["Login2"]["Options"] = [
                "" => "Select your account type",
            ];
            $browser = new HttpBrowser("none", new CurlDriver());
            $browser->GetURL("https://card.ml.com/RWDapp/ns/home?mc=ml");
            $nodes = $browser->XPath->query("//select[@name = 'acct_type']/option");

            for ($n = 0; $n < $nodes->length; $n++) {
                $s = CleanXMLValue($nodes->item($n)->nodeValue);

                if ($s != "") {
                    $arFields['Login2']['Options'][$s] = $s;
                }
            }

            if (count($arFields['Login2']['Options']) > 0) {
                Cache::getInstance()->set('merrill_acct_type_countries', $arFields['Login2']['Options'], 3600);
            }
        }
    }

    public function SaveForm($values)
    {
        // we will business version through bank of america
        if (stristr($this->account->getLogin2(), 'business')) {
            $this->account->setLogin3("Merrill");
            $this->account->setProviderid(getRepository('Provider')->findOneBy(['code' => 'bankofamerica']));
        }
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->FilterHTML = false;

        $this->AccountFields['Login3'] = 'WorldPoints';
        $this->http->GetURL("https://card.ml.com/RWDapp/ns/home?mc=ml");

        if (!$this->http->ParseForm("login_form")) {
            return $this->checkErrorsOfWorldPoints();
        }
        $this->http->SetInputValue("acct_type", $this->AccountFields["Login2"]);
        $this->http->SetInputValue("x", '18');
        $this->http->SetInputValue("y", '18');
        $this->http->PostForm();
        $this->loginUrl = $this->http->currentUrl();

        return true;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->LogHeaders = true;
    }
}
