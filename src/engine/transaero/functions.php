<?php

// refs #2061

class TAccountCheckerTransaero extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->LogHeaders = true;
//        $this->http->setDefaultHeader("User-Agent", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.10; rv:33.0) Gecko/20100101 Firefox/33.0");
//        $this->http->GetURL('http://transaero.ru');
        $this->http->GetURL('http://transaero.ru/en/privilege/argo-login');
//        $this->setIncapCookie("navigator%3Dobject,navigator.vendor%3D,opera%3DReferenceError%3A%20opera%20is%20not%20defined,ActiveXObject%3DReferenceError%3A%20ActiveXObject%20is%20not%20defined,navigator.appName%3DNetscape,plugin%3Dplugin,webkitURL%3DReferenceError%3A%20webkitURL%20is%20not%20defined,navigator.plugins.length%3D%3D0%3Dfalse");

        $this->http->GetURL('http://transaero.ru/en/privilege/argo-login');

        if (!$this->http->ParseForm(null, 1, true, '//form[contains(@action, "/wps/portal/!ut/p/c1")]')) {
            $this->http->Log("NoPostForm");

            return $this->checkErrors();
        }
        $this->http->Form['FORM_LASTNAME'] = $this->AccountFields['Login'];
        $this->http->Form['FORM_CARDNO'] = $this->AccountFields['Login2'];
        $this->http->Form['FORM_PINCODE'] = $this->AccountFields['Pass'];
        $this->http->Form['ArgoPortletFormSubmit'] = 'Logon';

        return true;
    }

//    function getSessionCookies() {
//        $cookieArray = array();
//        $c = $this->http->GetCookies($this->http->getCurrentHost());
//        $this->http->Log("<pre>".var_export($c, true)."</pre>", false);
//        foreach ($c as $key => $value) {
//            if (preg_match('/^\s?incap_ses_/', $key))
//                $cookieArray[] = $value;
//        }
//        $this->http->Log("getSessionCookies <pre>".var_export($cookieArray, true)."</pre>", false);
//
//        return $cookieArray;
//    }
//
//    function setIncapCookie($vArray) {
//        $cookies = $this->getSessionCookies();
//        $digests = array();
//        for ($i = 0; $i < count($cookies); $i++) {
//            $this->http->Log("<pre>".var_export($cookies[$i], true)."</pre>", false);
//            $digests[$i] = $this->simpleDigest( $vArray.$cookies[$i] );
//        }
//        $res = $vArray . ",digest=" .implode(",", $digests);
//        $this->http->setCookie("___utmvc", $res, "transaero.ru", "/");
//    }
//
//    function simpleDigest($mystr) {
//        $res = 0;
//        $this->http->Log("simpleDigest <pre>".var_export($mystr, true)."</pre>", false);
//        for ($i = 0; $i < count($mystr); $i++)
//            $res += ord($mystr[$i]);
//
//        return $res;
//    }

    public function checkErrors()
    {
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'An error occurred.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'http://transaero.ru/en/privilege/argo-login';

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($message = $this->http->FindPreg('/(For continuation, please, enter a surname as it is specified on your card)/ims')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        //# Balance - points
        return $this->SetBalance($this->http->FindSingleNode('//div[@class="textheader"]/p[3]/span'));
    }

    public function Parse()
    {
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[@class="textheader"]/p[2]', null, true, '/Dear ([^!]*)/')));
        //# Account Number
        $this->SetProperty("Number", $this->http->FindSingleNode('//div[@class="textheader"]/p[3]', null, true, '/account ([^\.]*)/'));
    }
}
