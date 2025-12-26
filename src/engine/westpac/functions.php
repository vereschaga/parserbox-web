<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerWestpac extends TAccountChecker
{
    use ProxyList;

    public static function GetAccountChecker($accountInfo)
    {
        if (!empty($accountInfo['ConfNo']) && !empty($accountInfo['Code'])) {
            return new static();
        } else {
            require_once __DIR__ . "/TAccountCheckerWestpacSelenium.php";

            return new TAccountCheckerWestpacSelenium();
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->SetProxy($this->proxyAustralia());
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setDefaultHeader("User-Agent", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/604.5.6 (KHTML, like Gecko) Version/11.0.3 Safari/604.5.6");
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false) {
            throw new CheckException('The details entered don\'t match those on our system. Please try again or reset your password online.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://banking.westpac.com.au/eam/servlet/getEamInterfaceData?_=" . date("UB"));
        $response = $this->http->JsonLog(null, 2);

        if (
            !isset($response->reference->guidId)
            || !isset($response->keymap->halgm)
            || !isset($response->keymap->malgm)
            || !isset($response->keymap->keys)
        ) {
            return $this->checkErrors();
        }

        $this->http->GetURL("https://banking.westpac.com.au/wbc/banking/handler?TAM_OP=login&segment=personal&logout=false");

        if (!$this->http->ParseForm("login")) {
            return $this->checkErrors();
        }

        $this->http->FormURL = 'https://banking.westpac.com.au/eam/servlet/AuthenticateHttpServlet';
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->encryptPassword($this->AccountFields['Pass'], $response));
        $this->http->SetInputValue('rememberme', "true");
        $this->http->SetInputValue('guidId', $response->reference->guidId);
        $this->http->SetInputValue('halgm', $response->keymap->halgm);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

//        $arg["CookieURL"] = "https://altituderewards.com.au/public/rewards_account.aspx";//todo

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;
        $this->http->setMaxRedirects(0);

        if (!$this->http->PostForm() && $this->http->Response['code'] != 302) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;
        $this->http->setMaxRedirects(5);
        // https://banking.westpac.com.au/eam/servlet/../../wbc/banking/handler?TAM_OP=auth_failure
        // ->
        //https://banking.westpac.com.au/wbc/banking/handler?TAM_OP=auth_failure
        $url = str_replace('eam/servlet/../../', '', $this->http->Response['headers']['location']);
        $this->http->GetURL($url);

        if ($redirect = $this->http->FindPreg("/var webSealLogoutUrl = '([^\']+)/")) {
            $this->http->GetURL($redirect);
            $this->http->GetURL("https://banking.westpac.com.au/wbc/banking/handler?TAM_OP=auth_failure&logout=false");
        }

        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentUrl}");

//        if ($this->loginSuccessful($currentUrl)) {
//            return true;
//        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'alert-error')]/div/span")) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'The details entered don\'t match those on our system.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        // me by account lock
        if ($currentUrl == 'https://banking.westpac.com.au/wbc/banking/initiatesecurelogin') {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - My Altitude Points balance
        $this->SetBalance($this->http->FindSingleNode("//span[@id = 'points-balance']"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(@class, 'memberName')]")));
    }

    /*
    function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://altituderewards.com.au/public/rewards_account.aspx", [], 20);
        $this->http->RetryCount = 2;
        if ($this->loginSuccessful($this->http->currentUrl())) {
            return true;
        }

        return false;
    }
    */

    private function encryptPassword($password, $response)
    {
        $this->logger->notice(__METHOD__);
        /*
         *
         * https://banking.westpac.com.au/secure/banking/scripts/desktop/westpac.administration/0001combined.js.1fbe21c2f44491dff615db2429230fd6e9cebd2f.js
         *
             var c = function(q, m, p, o) {
            if (q == undefined) {
                return ""
            }
            var r = q;
            var n = "";
            if (r.length <= m.inputRestrictions.password.maxLength) {
                a(q.split("")).each(function(s, u) {
                    if (p[u.toUpperCase()] != undefined) {
                        var t = m.keymap.malgm.charAt(p[u.toUpperCase()]);
                        n += t
                    } else {
                        r = g(r, u)
                    }
                })
            } else {
                var l = h(q);
                return this.encryptPassword(l, m)
            }
            o.val(n);
            return n
         */

        $split_word = str_split($password);
        $n = "";
        $keys = array_values($response->keymap->keys);

        $p = [];

        foreach ($keys as $key) {
            $p = array_merge($p, (array) $key);
        }
//        $this->logger->debug(var_export($p, true), ['pre' => true]);

        foreach ($split_word as $u) {
//            $this->logger->debug("[find value for]: $u");

            // '.' issue // AccountID: 5940892
            if ($u == '.') {
                $this->logger->notice("skip '.' in pass");

                continue;
            }

            $value = $p[strtoupper($u)];
            $this->logger->debug("[value]: $value");
            $n .= $response->keymap->malgm[$value];
        }

        $this->logger->debug("[Encrypted password]: {$n}");

        return $n;
    }

    private function loginSuccessful($currentUrl)
    {
        $this->logger->notice(__METHOD__);

        if (
            (
                $this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")
                || $this->http->FindSingleNode("//img[@alt = 'Sign Out With Cart']/@alt")
            )
            && !stristr($currentUrl, 'login.aspx')
        ) {
            return true;
        }

        return false;
    }
}
