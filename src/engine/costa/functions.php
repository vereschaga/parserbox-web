<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerCosta extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $transId = null;
    private $policy = null;
    private $tenant = null;
    private $csrf = null;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        $this->setProxyGoProxies(null, 'gb', null, null, 'https://www.costa.co.uk/costa-club/login');
//        $this->setProxyBrightData(null, 'static', 'uk');
    }

    public function IsLoggedIn()
    {
        if ($this->AccountFields['Login2'] == 'Ireland' || !isset($this->State['authorization'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if ($this->AccountFields['Login2'] == 'Ireland') {
            throw new CheckException("Sorry, the Ireland region is no longer supported for technical reasons.", ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->unsetDefaultHeader("Authorization");
        unset($this->State['Authorization']);
        unset($this->State['authorization']);

        $this->http->GetURL('https://www.costa.co.uk/costa-club/login');
        $this->http->GetURL('https://login.costa.co.uk/idccprd2.onmicrosoft.com/b2c_1a_signin_gam/oauth2/v2.0/authorize?client_id=c638ad8d-623b-4d2c-ba52-d6202093a632&scope=https%3A%2F%2Fidccprd2.onmicrosoft.com%2Fauthorizer-service%2Fread%20openid%20profile%20offline_access&redirect_uri=https%3A%2F%2Fwww.costa.co.uk%2Fcosta-club%2Faccount-home&client-request-id=8fb31cb6-de92-44d3-87e5-31a054dd799a&response_mode=fragment&response_type=code&x-client-SKU=msal.js.browser&x-client-VER=3.7.0&client_info=1&code_challenge=c32EWkw5BnVK0qLD_K5HuWViloH3psMfwJf6KjrHznM&code_challenge_method=S256&nonce=a1916ddc-5625-4d89-bc54-a344e1f9891f&state=eyJpZCI6IjQwNzA0N2ZmLTBiNzMtNGNmNC1hODJlLTA4M2JhN2E3ZjgzYiIsIm1ldGEiOnsiaW50ZXJhY3Rpb25UeXBlIjoicmVkaXJlY3QifX0%3D&lang=en&country=UK&region=UK');

        if (
            $this->http->Response['code'] == 403
            || strstr($this->http->Error, 'Network error 28 - Operation timed out after ')
        ) {
            throw new CheckRetryNeededException(3, 7);
        }

        $this->transId = $this->http->FindPreg("/\"transId\":\"([^\"]+)/");
        $this->tenant = $this->http->FindPreg("/\"tenant\":\"([^\"]+)/");
        $this->policy = $this->http->FindPreg("/\"policy\":\"([^\"]+)/");
        $this->csrf = $this->http->FindPreg("/csrf\":\"([^\"]+)/");

        if (!$this->transId || !$this->tenant || !$this->policy || !$this->csrf) {
            return $this->checkErrors();
        }

        $selenium = true;

        if ($this->attempt == 1) {
            $selenium = true;
        }

        if ($selenium === true) {
            $this->getCookiesFromSelenium();
        }

        if ($selenium === false && !$this->sendSensorData()) {
            return $this->checkErrors();
        }

        $this->http->FormURL = "https://login.costa.co.uk{$this->tenant}/SelfAsserted?tx={$this->transId}&p={$this->policy}";
        $this->http->SetInputValue('signInName', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('request_type', 'RESPONSE');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re just making a few changes. We\'ll be back with you as soon as possible.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/(Sorry, but something\'s wrong with the Costa Coffee website right now.\s*We\'re working hard to get it back online, so please bear with us\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are conducting some routine maintenance on our some areas of our website over the next few days. We apologise for the inconvenience.') or contains(text(), 'We would like to apologise that we have temporarily taken down the Coffee Club section of the Costa website for the next few days')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, there seems to be a problem
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Sorry, there seems to be a problem')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The Coffee Club is currently offline for maintenance.
        if ($message = $this->http->FindPreg("/<p[^>]+>(The Coffee Club is currently offline for maintenance\..+)<\/p>/")) {
            throw new CheckException(str_replace('<a ', '<a target = \'_blank\' ', $message), ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/>(We are currently undergoing maintenance[^<]+)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The website is experiencing technical difficulties
        if ($message = $this->http->FindPreg("/(The website is experiencing technical difficulties)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We’re so sorry but we’re having some issues with Costa Coffee Club at the minute
        if ($message = $this->http->FindPreg("/(We’re so sorry but we’re having some issues with Costa Coffee Club at the minute)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // retry
        // if ($this->http->currentUrl() == 'https://www.costa.co.uk/coffee-club/login/')
        //     throw new CheckRetryNeededException(3, 7);

        // hard code
        if ($this->http->ParseForm(null, "//form[@action = '/coffee-club/login/']")
            && isset($this->http->Form['ctl00$ctl00$ctl00$ContentPlaceHolderDefault$ContentPlaceHolderBody$LoginRegister_9$txtUsernameLoginForm'])
            && $this->http->Form['ctl00$ctl00$ctl00$ContentPlaceHolderDefault$ContentPlaceHolderBody$LoginRegister_9$txtUsernameLoginForm'] == $this->AccountFields['Login']) {
            throw new CheckException("Sorry, your username and password are incorrect.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Login()
    {
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-CSRF-TOKEN"     => $this->csrf,
            "X-Requested-With" => "XMLHttpRequest",
            "Origin"           => "https://login.costa.co.uk",
        ];
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status == 200) {
            $param = [
                'rememberMe' => 'true',
                'csrf_token' => $this->csrf,
                'tx'         => $this->transId,
                'p'          => $this->policy,
                'diags'      => '{"pageViewId":"9bbc3586-ba08-41bd-a559-97838a0facfe","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1724820788,"acD":1},{"ac":"T021 - URL:https://cdn.uk.identity.costacoffee.com/sign-in-web.html?lang=en&appId=c638ad8d-623b-4d2c-ba52-d6202093a632","acST":1724820788,"acD":916},{"ac":"T019","acST":1724820789,"acD":5},{"ac":"T004","acST":1724820789,"acD":3},{"ac":"T003","acST":1724820789,"acD":0},{"ac":"T035","acST":1724820790,"acD":0},{"ac":"T030Online","acST":1724820790,"acD":0},{"ac":"T002","acST":1724821925,"acD":0},{"ac":"T018T010","acST":1724821923,"acD":1963}]}',
            ];
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://login.costa.co.uk{$this->tenant}/api/CombinedSigninAndSignup/confirmed?" . http_build_query($param));
            $this->http->RetryCount = 2;

            $code = $this->http->FindPreg("/code=([^&]+)/", false, $this->http->currentUrl());

            if (!$code || $this->http->Response['code'] !== 200) {
                $this->logger->error("something went wrong, code not found");
                $response = $this->http->JsonLog();
                $detail = $response->errors[0]->detail ?? null;


                $this->DebugInfo = $detail;

                return false;
            }

            $this->logger->notice("Get token...");

            $data = [
                "client_id"                  => "c638ad8d-623b-4d2c-ba52-d6202093a632",
                "redirect_uri"               => "https://www.costa.co.uk/costa-club/account-home",
                "scope"                      => "https://idccprd2.onmicrosoft.com/authorizer-service/read openid profile offline_access",
                "code"                       => $code,
                "x-client-SKU"               => "msal.js.browser",
                "x-client-VER"               => "3.7.0",
                "x-ms-lib-capability"        => "retry-after, h429",
                "x-client-current-telemetry" => "5|865,0,,,|@azure/msal-react,2.0.9",
                "x-client-last-telemetry"    => "5|0|||0,0",
                "code_verifier"              => "JEVCblbY8xJg22UMsPGxz_Ays5gjpOS0bO7A_-fc0M8",
                "grant_type"                 => "authorization_code",
                "client_info"                => "1",
                "client-request-id"          => "8fb31cb6-de92-44d3-87e5-31a054dd799a",
                "X-AnchorMailbox"            => "Oid:581f717b-635a-4b88-9be5-2004224c3e06-b2c_1a_signin_gam@05278448-0b6c-4f5b-8793-d9364686fd45",
            ];
            $headers = [
                "Accept"          => "*/*",
                "Accept-Language" => "en-US,en;q=0.5",
                "Accept-Encoding" => "gzip, deflate, br, zstd",
                "Referer"         => "https://www.costa.co.uk/",
                "content-type"    => "application/x-www-form-urlencoded;charset=utf-8",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://login.costa.co.uk/idccprd2.onmicrosoft.com/b2c_1a_signin_gam/oauth2/v2.0/token", $data, $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (isset($response->access_token, $response->token_type)) {
                $this->State['authorization'] = "{$response->token_type} {$response->access_token}";

                if ($this->loginSuccessful()) {
                    return true;
                }

                if (
                    $this->http->FindPreg("#,\"message\":\"Email And Phone Not Present\"#")
                    || $this->http->FindPreg("#,\"message\":\"EmailId is invalid\"#")
                ) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }

            return false;
        }

        $errorCode = $response->errorCode ?? null;

        if ($status == '400' && in_array($errorCode, [
            'AADB2C90053',
            'AADB2C90054',
        ])) {
            throw new CheckException("Incorrect credentials.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->DebugInfo = $errorCode;

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 3, false, 'tentativePendingBalance');

        if (isset($response->profile->attributes->firstName, $response->profile->attributes->lastName)) {
            $this->SetProperty("Name", beautifulName($response->profile->attributes->firstName . " " . $response->profile->attributes->lastName));
        }

        $subLedgers = $response->loyalty->programs->Costa_Production_Loyalty_Profile->subLedgers ?? [];

        if (empty($subLedgers)) {
            return;
        }

        // Balance - Beans
        // Collect 10 beans and get a free cuppa!
        $this->SetBalance(
            ($subLedgers->standardBeans->currentBalance ?? 0)
            + ($subLedgers->registrationBeans->currentBalance ?? 0)
            + ($subLedgers->greenBeans->currentBalance ?? 0)
            + ($subLedgers->expressBeans->expressBeans ?? 0)
        );
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"           => "application/json",
            "Accept-Encoding"  => "gzip, deflate, br, zstd",
            "Referer"          => "https://www.costa.co.uk/",
            "Authorization"    => $this->State["authorization"],
            "Content-Type"     => "application/json",
            "Origin"           => "https://www.costa.co.uk",
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://web.costa-loyalty-platform.com/loyalty/v1/", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, 'email');

        $email = $response->profile->attributes->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if (strtolower($email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            return false;
        }

//        $this->http->setCookie("_abck", "93775F7160631E743D63E0036F7B4D4C~0~YAAQLEMVArt4TNmSAQAAeem6GgwAzhG/oYaC8Qla4bQvptHyv9XB+GIRCeq6MbSGrb12LMG686QS7jQ8T2gpAZpTbdBuJJSRfn0pHPkmokJl3+e8di7oRKaIYk/XLP6NpS+C1TFAJLllhO6GT0S+ojr8VvctEfjor0ILPB/p6Dn7uvJavOZeArF9tZhcY9T7NiuA8f1lk0Vq21UF6xzQOHuPdfet3RvXQlW2yISLHOPLDEwRb7mbNB5aCVxxaSX1Fsf/5cSarvHfYxXr8VnwKeqXM9zVPLOwVtdn8Z/o98WCgR+GZnaFonuinDrqTN8TmICLS/6ssxM0cGYuKuknHrMQQRlKfqUNdzCfAcP3SD50QX/gRgeh8YS9WDsrfuU4dMatajtSjADA7raAHTdphNq8Nt9AVcZb+b+WVipLGDx9QtKKW0IiHx6rFKZZGoyE+mO8i97KfsEx8QbFewbmCKrHbAMmN3DsTr7lYNiCCZyC72hd22hbXlzHaCfM/A==~-1~||-1||~-1", ".costa.co.uk");

        $this->http->RetryCount = 0;
        $this->http->NormalizeURL($sensorDataUrl);
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $data = [
            "sensor_data" =>
                '2;0;3162676;3360049;13,0,0,1,2,0;=)^N^2RL!| 1kzO5{KAV9YTGfe-/T_xf`4u _^.*q(bijQWOu]ijX-D,6Hr}ZJdoeQ$5O1_K8PLYBoWH*d*2bNVU*yPc2GgZnM5IaDuxox p7A=9qY{Cv]iw9M{=j*DQfO2PeE4n5DO@q%(~iQk1xf}&]u>eqa~( 8)?fS@>7Yt).TY`A-i/xa#Kc!N0gvId~iIj3M0BLb4IWh 5r{}fT?__b$iBVEl%~n#w3(33Ag{lN{r7~xh@;BLrqY;m`6r1DnXRXOEd(yO}Wbr`/R9iHuH#?lHF_7x+IW])q(8g SoBl-C9(9kL7=r5uRFWtM=TE8@#_om2I%O2Jz/KKRLT,7t:?r2FBuc$25H8y&$_pRq4`ImAB{eUliI|l[16>!tU}5O7}05q1}G;kNjC^ XBd(W.#(r]r1O!c,;.R!vYP7i4h#J)<UAK_pW;T %*5zvT<W+wg+E{XDwuFGlfm**qMoH0D(37N.>{v1PaUz&z$L$yng4x5r`N(MP, ,8;IbN6p&bn{ZUg1: ~&jE3/%ty_/{jgx34RK{N9v&PW[0kNXloeQH?d!BIwTM7HM{vDJ^G]]c_`W+fS]`WI^C8kFKb6mlr7%Wc,5mPB+m:)_:3-21@6xnbdBRj?@5#5^56j{I=;y^q:lkN<=TxhzoiB8U`@(<C[&~g-Ggu*n^n3mpSMdr+4MA[+m0SX*:Gq4B+ZcFUbY|X}fUxZp*}LckufQ}v)p,Cu6kwObln2x|YiR:QN%:Lje*6)&CfmYu3b0v?}8!CGDGP<*&B[n~[@70XG?gBDLUuN$o8r54CeYj0)lSq%Vu=[5P]N]1[Yg*Xa`E3OJ!5suwD5>}Qa4O&Rw2Za^2pr)Uk]1~yW0hItk.7G=Fw(X7L8g*NdU9#%4m7~V_AKvY3;o#mGSZ4,0M;?F`;KC.i1FzAF|pcWHHfR=pPFORDg=h_.r4p%8|c}>+FK9:#zz@Ey$1yq|T4cO|kH5b4KufYs bKR5PvE+&fSk>4u1I#`Y#&_]!Ut52$LpP;94B9KWH#rugVPvI~O@(2kW+Jn_%y&H@&H!7.LaE0)Lz+&IkN0<nm[Y,oNy#oL{xUZe)&+mT93c:@?pkf3F/^HM*%>t+I^./+44`9)/G6Gf:8U:PT34)6,9rn?3#7y,RBW^->Wq(y&[7S_.fp+`PZpuZH:p~CF)61v$3m&@BhcK Md6wv }v0.N/2u&n~eVU]+9] Pb%,M2u4H(h|p,*PImW_A,PeLAR_y<q3uhV_WVNo2}TdT08NKx|(nY}<;|>J~2v3yE`p-{1AdCCVNp+fb 1{2mAo[O!.d[n-lAo0FB7d>%r9;x}?b|>>4=m4<,Ea<eO1A@j0NhTx~Y%dlB0eS(]yosF?!|w*g;3hi c00OQ,r?k*Xnkd..Yp2~}(f`3V:y6%Rs%Ycsq;FQ>[^Yn9dqYZ05aL_k/VI3%XQF:K ?P3i &MoGua-g&dUrZ:S`@n_::,]lY0_*&k^o*V$e+#H+X KS<~?ZGnfPK7B{tZ<MlXFPeKf*F.u_<hp4n&Qw3<oIbHNOe*P(dp3w%K)xo}>Pdc$[tic&>(MM=wb^!79M&xQKV5.s<OygUlCj*aVkwBT9Rs 2l;B}RHL;}BBy<1*i5M15D0?{hlUguf[9bQ:HB0>mHAxa~wh$+y[i3+RzR)FHt`FI5 ZE%HT&v.@<uf^f)v/%w(U,hwl?[4OLX4,G).;.}reBUggQzLRvKjPYSWtdRG%q76;ATc3~{+rbrb+C~%{a_]=9_0t9>&z?zCoH^pX9WQ5Xw+g};}&fRMx{FQq#+=?WFbg7q-=Y7l<$JWFZVQJ|Tb5m=./K16UC@,_7.id@ld1k9Z8~}xbL47g+*9^E=!$j.Ye@_EC~-TSJ4N*-!^<CMU;*|;EH77 bu!(EU^F:M,IVmg*w}bBZ``RY`cbybnq,(]^L!%usiwBD<!>:X4=-n_HA<VuDGu oNR9TD6C,<djRxR#fm2DY!`z-Y$gJR|,;JgG2aHs6Bh~cAs@{Cfo~oTfz!(iHSB2kkEv&=$p{$O#r/}U7`)EPN(>TAdXU&8;,yh>OVXJYpKmP0|VVhBXnfU*,q2&rI6<$)tGa3Tu%U``EwvKCuTg%{a>dE),q(#:&3^J&S458wW2,WQ{m|Pg89/Q{vMWGssssTbL~NeOq1#da<G$vRGe(|At+!sy490QH^Y>sKFY/F,$Mdh];=$L$4fj/7iD6Ml)~A6/nEAJ([?.:$4%+dHy@}$W16-JBz,@M@D?~%+>w(E4>;>?L, pQO=/iikM[&oC@pl.C?Rf<6rp< 4SA# iZno/mXV|-S~Hj)(D4]+7GN%Tf8%/L>K(m):0t/^coV#>.O@uQKjXU<89Ye4$ EM<zNtYHd<3(Z.,]Y/loV* ;.f:3cfgegq8T}=F1_hcb:f<P}3V<spA:%@S1K[)_Fcq+V(w-GX^{Nyg!^&BO@gF_*MYIfX}ny/--H<$/u}D.sD(aV-[+%c3[`(qB]DU$T!#iFst|Rl|]B`Lzm4``fJ&86l$lrTvGu*%EmfymvsnJ2#%ihtU{kYS11X2G!d6eJQh,W0k|lZP:;t>TQ%Q|?FBUkAfG~%/p=e-)whr;~gD$5uof_OjvhUo)u=7&+tUkkkHe3Gt}8O=c3%, xX>]Gd]Q/m#]FBtp=o*UG/WM^L9<N6QPa)oA`bVu*lWM#u/I~g$jQgb4zilw@fmhdnf}asS1^|nePK+5&Z7=>>iB9Br^ yy~2qq,CPK#UFlwel_2V?Q%&+`,5 ?4U^}|bXO`*-Kysy&;Sf&C]zbrA!z=)F89kJ5(_x/5JH!,Ykbj?.,%pm>,:H.mrn))hoOTpaL?cy/b#}nlrYRi,Ofv`F~gH{GI(doL?oN7|=#n}#wSZNFZ^6-b 24kW$[BDNn(A`-]f{H?|lZ)FJXp&{yY75%bEGpj>+, dS$h#y82g:;M2(gS+}SB3=Vr[g(KRXY!MajD89Y@UR.xLnL}@cQ,12TJf%gnj2u41:2Ei=_!r=]ab9P YGGVrTXJu:&nr:m|`}LONQHCq4St2w0(v8:GMZMU4[0b^0+`Ij:>93n^f4b|;}F}>Y[aM#QCl3$nb=:Q`(fHwV3KXv`Tkgh',
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        /*
        sleep(1);

        $data = [
            "sensor_data" =>
                '',
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        */
        $this->http->RetryCount = 2;
        sleep(1);

        return true;
    }

    private function getCookiesFromSelenium($retry = false)
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://www.costa.co.uk/coffee-club/login/');
            $login = $selenium->waitForElement(WebDriverBy::id('longEmail'), 5);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return null;
    }
}
