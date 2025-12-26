<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerJcrew extends TAccountChecker
{
    /*
     * LIKE as vicsecrets
     */

    use SeleniumCheckerHelper;
    use ProxyList;

    private $questionIdCode = 'Please enter Identification Code which was sent to your email address. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $loyaltyplus = null;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], "jcrewRewards")) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        if ($this->AccountFields['Login2'] == 'CreditCard') {
            $redirectURL = 'https://d.comenity.net/ac/jcrew/public/home';
        } else {
            $redirectURL = 'https://www.jcrew.com/us/l/account/rewards';
        }

        $arg["RedirectURL"] = $redirectURL;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        $this->setProxyGoProxies();
    }

    public function IsLoggedIn()
    {
        return false;

        if ($this->AccountFields['Login2'] != 'ShoppingCard') {
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.jcrew.com/ajax/userDetails.jsp?user=true&cart=true&omniture=true&getAdditionalUserDetails=true&template=/index.jsp&uea=", [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->userDetails->userFirstName)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if ($this->AccountFields['Login2'] == 'CreditCard') {
            throw new CheckException("The J.Crew Credit Card program has ended, effective April 25, 2024.", ACCOUNT_PROVIDER_ERROR);
        }

        // Please enter a valid email address.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        // AccountID: 3458124
        if (strstr($this->AccountFields['Pass'], '&')) {
            $this->AccountFields['Pass'] = str_replace('&', '', $this->AccountFields['Pass']);

            if ($this->AccountFields['Login'] != 'thetuhtans@comcast.net') {
                $this->sendNotification("need to check credentials // RR");
            }
        }// if (strstr($this->AccountFields['Pass'], '&'))

        $this->http->GetURL("https://www.jcrew.com");

        if (strpos($this->http->Error, 'Network error 92 - HTTP/2 stream 0 was not closed cleanly: INTERNAL_ERROR (err 2)') !== false) {
            $this->http->SetProxy($this->proxyReCaptcha());
            $this->http->GetURL("https://www.jcrew.com");
        }

        if (!$this->http->FindPreg("/signin_link_globalnav/")) {
            return $this->checkErrors();
        }

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }

        $this->seleniumShoppingCard(); //$captcha
//        return true;

        // works cookies from ff
//        $this->http->setCookie("_abck", "B273A302CC2285010BD1A617F6003A4B~0~YAAQj2jcF/iwGKeLAQAAne+IrgoAcKjjYfw2FzoHiR5SV9df5Vd5VpjXbvtSdoBlT9fk6jZSgFYAHvOM8UfU9wtu18dAiUf35RV9vcBVQWoWAR8pbcwZomvZp+VA5s7+JpjYBlfjGToWgSkFHxrFLJ4r934EzNBECeYDB4ONfMgPp2pzymbqkEvXR8o8WV2cbzMA0rIoWEQQhqzgkPK+KUgJwxqecwioqTOFuhSB4wl59NATD2VkDZuwk47oiq3sdUSYJLZ5S8l3K/0h8ArgCoLIgxYEdWeuSNjEtfPfpanM/QfnSoa5B1x7KKd/s4FJ/zRiIsze+/XB+ZY+aiueqVXzWTlJRiys3MsZgbkuB5f0z0UaOiJdQHlcGgGC8crVBeiuXYOBkG2XY5+mfFzh85zZLrJ/QEhv~-1~||1-gqGrGjgslb-1-10-1000-2||~-1", ".jcrew.com"); // todo: sensor_data workaround

//        $this->sendSensorData();

        $headers = [
            "Accept"               => "*/*",
            "Content-Type"         => "application/json",
            "x-brand"              => "jc",
            "x-country-code"       => "US",
            "x-operation-name"     => "accountUser",
        ];
        $data = '{"operationName":"accountUser","query":"query accountUser {\n  accountUser {\n    userDetails {\n      anonUser\n      passwordHint\n      email\n      phone\n      userFirstName\n      userLastName\n      emailSignup\n      smsOptIn\n      countryCode\n      fromCookie\n      birthDate\n      id\n    }\n    omnitureVars\n    loyaltyPlusDetails {\n      tierName\n      nextTier\n      extCustomerId\n      balance\n      couponsCost\n      pointsExpDate\n      isJCCCHolder\n      mobilePhone\n      birthdate\n      coupons {\n        ...LoyaltyPlusCouponsFragment\n      }\n      offers {\n        ...LoyaltyPlusOffersFragment\n      }\n    }\n\n    cart\n    cartSize\n    showIntlSignup\n    customerCookieName\n    requestFromJCrewNetwork\n    birthdayDay\n    birthdayMonth\n  }\n}\n  \n  fragment LoyaltyPlusCouponsFragment on LoyaltyPlusCoupons {\n    id\n    expiration\n    displayName\n    cost\n  }\n\n  \n  fragment LoyaltyPlusOffersFragment on LoyaltyPlusOffers {\n    id\n    displayName\n    code\n    expiration\n    birthdayOffer\n    offerType\n  }\n\n","variables":{}}';
        $this->http->PostURL("https://www.jcrew.com/checkout-api/graphql", $data, $headers);
        $response = $this->http->JsonLog();

        if (!isset($this->http->Response['headers']['x-access-token'])) {
            $this->logger->error("x-access-token not found");

            return false;
        }

        $headers["x-access-token"] = $this->http->Response['headers']['x-access-token'];
        $data = '{"operationName":"accountLogin","query":"mutation accountLogin($email: String!, $password: String!, $remember: Boolean, $mergeCartType: String, $reCaptchaToken: String, $campaignId: String) {\n    accountLogin(email: $email, password: $password, remember: $remember, mergeCartType: $mergeCartType, reCaptchaToken: $reCaptchaToken, campaignId: $campaignId) {\n      accountUser {\n        id\n        firstName\n        lastName\n        email\n        role\n        customerNum\n        campaignData\n      }\n      cartCookieValue\n      loyaltyPlusValue\n      userMeta {\n        firstName\n        availablePoints\n        availableDollars\n        jcccHolder\n        tier\n        tierName\n        hasBirthdaySaved\n      }\n    }\n  }","variables":{"email":"' . $this->AccountFields['Login'] . '","password":"' . $this->AccountFields['Pass'] . '","remember":true,"mergeCartType":"auto","reCaptchaToken":"' . $captcha . '","campaignId":null}}';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.jcrew.com/checkout-api/graphql", $data, $headers);
        $this->http->RetryCount = 2;

        /*
        $this->http->FormURL = "https://www.jcrew.com/account/login.jsp";
        $this->http->Form = "LOGIN<>userid={$this->AccountFields['Login']}&LOGIN<>password={$this->AccountFields['Pass']}&rememberMe=true&signInFormCalled=true&sidecarSignIn=true&bmForm=frm_modal_signin";
        $this->http->PostURL($this->http->FormURL, $this->http->Form);
        */

        return true;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 5);
        // Please Update Your Password
        $shouldForcePasswordUpdate = $response->shouldForcePasswordUpdate ?? null;

        if ($shouldForcePasswordUpdate == true) {
            $this->throwProfileUpdateMessageException();
        }


        $token = $this->http->Response['headers']['x-access-token'] ?? $this->http->getCookieByName("checkout_jwt", "www.jcrew.com");

        /*
        if ($token) {
            $this->http->setDefaultHeader("x-access-token", $token);

            if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Account Locked')]")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            return true;
        }
        */

        if (($response->data->accountLogin ?? null) && $token) {
            $this->token = $token;
            $this->customerNum = $response->data->accountLogin->accountUser->customerNum;
            $this->loyaltyPlusValue = $response->data->accountLogin->loyaltyPlusValue;
            $this->cart = $response->data->accountLogin->cartCookieValue;

            $this->http->setDefaultHeader("x-access-token", $token);

            return true;
        }
        // Whoops, that's not right... Please try your email and password again.
        if (Html::cleanXMLValue($this->http->Response['body']) == '{"errors":[{"message":"GENERIC_ERROR","locations":[{"line":2,"column":5}],"path":["accountLogin"],"extensions":{"code":"INTERNAL_SERVER_ERROR","exception":{"errors":[{"message":"GENERIC_ERROR","locations":[],"path":["accountLogin"]}]}}}],"data":{"accountLogin":null}}'
            || Html::cleanXMLValue($this->http->Response['body']) == '{"errors":[{"message":"GENERIC_ERROR","path":["accountLogin"],"extensions":{"code":"INTERNAL_SERVER_ERROR"}}],"data":{"accountLogin":null}}'
            || strstr(Html::cleanXMLValue($this->http->Response['body']), '"message":"Unauthorized Invalid Credentials. SLAS AUTH LOGIN Failed",')
            || strstr(Html::cleanXMLValue($this->http->Response['body']), '{"errors":[{"message":"SLAS AUTH LOGIN Failed","path":["accountLogin"],"extensions":{"code":"INTERNAL_SERVER_ERROR"}}],"data":{"accountLogin":null}}')
        ) {
            throw new CheckException("Whoops, that's not right... Please try your email and password again.", ACCOUNT_INVALID_PASSWORD);
        }

        if (Html::cleanXMLValue($this->http->Response['body']) == '{"errors":[{"message":"ACCOUNT_LOCKED","locations":[{"line":2,"column":5}],"path":["accountLogin"],"extensions":{"code":"INTERNAL_SERVER_ERROR","exception":{"errors":[{"message":"ACCOUNT_LOCKED","locations":[],"path":["accountLogin"]}]}}}],"data":{"accountLogin":null}}') {
            throw new CheckException("Account Locked", ACCOUNT_LOCKOUT);
        }

        if ($message = $this->http->FindSingleNode("//span[contains(@class, 'js-invalid-msg')]")) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Whoops, that’s not right... Please try your email and password again.')) {
                throw new CheckRetryNeededException(2, 0, "Whoops, that's not right... Please try your email and password again.", ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if ($this->http->Response['code'] == 403) {
            $this->DebugInfo = "need to upd sensor_data worakround";
        }

        if ($this->attempt == 0 && $this->http->ParseForm(null, '//form[@class="signin-form"]')) {
            throw new CheckRetryNeededException(2, 0);
        }

        if (isset($response->branding_url_content) && strstr($response->branding_url_content, "/_sec/cp_challenge/crypto_message-")) {
            $this->DebugInfo = 'cp_challenge';

            return false;
        }

        /*
        $redirect = $response->accountHomeURL ?? null;
        $status = $response->status ?? false;
        if ($status && $redirect) {
            $this->http->GetURL($redirect);
            return true;
        }
        // Whoops, that's not right... Please try your email and password again.
        if ($this->http->ParseForm("frm_generic_signin"))
            throw new CheckException("Whoops, that's not right... Please try your email and password again.", ACCOUNT_INVALID_PASSWORD);
        */
        /*
        if ($this->http->FindSingleNode('//a[contains(@href, "logout")]')) {
            return true;
        }
        // Whoops, that's not right... Please try your email and password again.
        if ($message = $this->http->FindSingleNode('//span[contains(@class, "js-invalid-msg")]')) {
            $this->logger->error($message);
            if (strstr($message, "Whoops, that’s not right... Please try your email and password again.")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }
        */

        return false;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Parse()
    {
//        $this->http->GetURL("https://www.jcrew.com/l/account/details");
//        $this->http->GetURL("https://www.jcrew.com/l/account/rewards");
        $response = $this->http->JsonLog(null, 0);
        $accountLogin = $response->data->accountLogin ?? null;
        // Name
        $firstName = $accountLogin->accountUser->firstName ?? null;
        $lastName = $accountLogin->accountUser->lastName ?? null;
        $this->SetProperty('Name', beautifulName($firstName . " " . $lastName));
        // Points until next reward
        if (!isset($this->Properties['UntilNextReward'])) {
            $this->SetProperty('UntilNextReward', $this->http->FindSingleNode('//div[contains(@class, "loyalty_points__until_next")]/span', null, true, "/(.+)\s+point/ims"));
        }

        $headers = [
            "Accept"               => "*/*",
            "Content-Type"         => "application/json",
            "x-brand"              => "jc",
            "x-country-code"       => "US",
            "x-operation-name"     => "accountUser",
            "x-loyaltyplus"        => $this->loyaltyplus,
            "x-recognized-user-id" => $this->customerNum,
            "x-access-token"       => $this->token,
            "x-cart"               => $this->cart,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.jcrew.com/checkout-api/graphql", '{"operationName":"accountUser","query":"query accountUser($loyaltyPlusValue: String) {\n  accountUser (loyaltyPlusValue: $loyaltyPlusValue){\n    userDetails {\n      anonUser\n      passwordHint\n      email\n      phone\n      userFirstName\n      userLastName\n      emailSignup\n      smsOptIn\n      countryCode\n      fromCookie\n      birthDate\n      id\n    }\n    omnitureVars\n    loyaltyPlusValue\n    loyaltyPlusDetails {\n      tierName\n      nextTier\n      extCustomerId\n      balance\n      couponsCost\n      pointsExpDate\n      isJCCCHolder\n      mobilePhone\n      birthdate\n      coupons {\n        ...LoyaltyPlusCouponsFragment\n      }\n      offers {\n        ...LoyaltyPlusOffersFragment\n      }\n    }\n\n    cart\n    cartSize\n    showIntlSignup\n    customerCookieName\n    requestFromJCrewNetwork\n    birthdayDay\n    birthdayMonth\n  }\n}\n  \n  fragment LoyaltyPlusCouponsFragment on LoyaltyPlusCoupons {\n    id\n    expiration\n    displayName\n    cost\n  }\n\n  \n  fragment LoyaltyPlusOffersFragment on LoyaltyPlusOffers {\n    id\n    displayName\n    code\n    expiration\n    birthdayOffer\n    offerType\n  }\n\n","variables":{"loyaltyPlusValue":"'.$this->loyaltyPlusValue.'"}}', $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 5);
        // Name
        $accountLogin = $response->data->accountUser->userDetails ?? null;
        $firstName = $accountLogin->userFirstName ?? null;
        $lastName = $accountLogin->userLastName ?? null;
        $this->SetProperty('Name', beautifulName($firstName . " " . $lastName));
        // Your J.Crew Rewards
        $loyaltyplus = null;

        foreach (explode('.', $this->loyaltyplus) as $str) {
            $str = base64_decode($str);
            $this->logger->debug($str);

            if ($this->http->FindPreg('/"loyaltyData":/', false, $str)) {
                $loyaltyplus = $this->http->JsonLog($str);

                break;
            }
        }

        // Balance - You have ... points
        $this->SetBalance(
            $response->data->accountUser->loyaltyPlusDetails->balance
            ?? $loyaltyplus->loyaltyData->balance
            ?? null
        );

        $exp =
            $response->data->accountUser->loyaltyPlusDetails->pointsExpDate
            ?? $loyaltyplus->loyaltyData->pointsExpDate
            ?? null
        ;

        if ($exp) {
            $this->SetExpirationDate(strtotime($exp));
        }
        // Your tier
        $this->SetProperty('Tier', beautifulName(
            $response->data->accountUser->loyaltyPlusDetails->tierName
            ?? $loyaltyplus->loyaltyData->tierName
            ?? null
        ));

        $this->logger->info('J.Crew Rewards', ['Header' => 3]);
        $rewards =
            $response->data->accountUser->loyaltyPlusDetails->couponsCost
            ?? $loyaltyplus->loyaltyData->couponsCost
            ?? null
        ;
        if ($rewards !== null) {
            $this->AddSubAccount([
                'Code'        => 'jcrewRewards',
                'DisplayName' => "J.Crew Rewards",
                'Balance'     => $rewards,
            ]);
        }

        $coupons =
            $loyaltyplus->loyaltyData->coupons
            ?? $response->data->accountUser->loyaltyPlusDetails->coupons
            ?? []
        ;

        if ($coupons) {
            foreach ($coupons as $coupon) {
                $this->AddSubAccount([
                    'Code'           => 'jcrewRewards' . $coupon->id,
                    'DisplayName'    => $coupon->displayName,
                    'Balance'        => $coupon->cost,
                    'ExpirationDate' => strtotime($coupon->expiration),
                ]);
            }
        }

        $this->logger->info('J.Crew Offers', ['Header' => 3]);
        $offers =
            $response->data->accountUser->loyaltyPlusDetails->offers
            ?? $loyaltyplus->loyaltyData->offers
            ?? []
        ;

        if ($offers) {
            foreach ($offers as $offer) {
                $this->AddSubAccount([
                    'Code'           => 'jcrewOffer' . $offer->id,
                    'DisplayName'    => $offer->displayName,
                    'Balance'        => null,
                    'ExpirationDate' => strtotime($offer->expiration),
                ]);
            }
        }
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $this->http->RetryCount = 0;
        $referer = $this->http->currentUrl();

        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data URL not found");

            return;
        }

        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            '2;3617590;3223618;9,0,0,0,1,0;j.N,pM_P-H3qW{1pIub#6v[ LMoBq<0Bc04>}gz}A~fOB%{VCw@Z^o-ALz-~>Z0#H}%/$hq2_96iI<J=([CI}H5Q[!$metGbJFaeWBM1}P@t)5Z>Dyey@~7?wnI!hboo<3l0O#],tMJ;Q/0zCjZ(1.w<vvE>s+i[,s,C:6U2D8 u55dVO9zHmS=d(^a^ADtI)+trr!K^]@x$TVf7OJNoBK:n-#XN>1N#[Iv34DkI6G6/NSkgp;>2mgqCiV;H62r&bKpTT&s5Jtew?BbT%uk>xL/l6(lGD)jk54X:lmKlVVr4o]+|le_?uYH}[L0SJUMO!)Kad%]9A8Zi8O/.UQ4$z8F/[j]GO~ 0^e_n_}jPNd#=htJa488/o2V0|xFl&eGhgpC.Qqe:}Y*Jy8k!][slO|<-^|wa#Sm.d{lq$)&7KR7K7L/L$<F@0XurZom0Bj+F?!uym1x}0L%?5[9a5Th5A&]Ry;]%En!DMm`Z%SQEr,o4|OkK6a*~}TSepMDq7]tlXwxwC8}#Sm?wkI9WHl?:=hAMAm.^rSpJYw!%T4SKmG3XRw+Y$T*&m3b*$=FXRnEFWtL9kYWj#L*t!/3Dg|PD7==15GE#.b#XE.u>;q%u/!6<R:7,)R?/jFw;S=:@mKd;o5Lfa>[s,B1r8%DAa~!522V1T1cGLjk*Wvl)@3BNbJDf^jpKsba$wrIren{gHWV$n<S`nQR]v6 qj`D8Wu1&D7U^7oLV/CwG/KbJR#HuO<h`&p&e+LxJt]G:`It(R(5Xfe&Qaq)[_P#_LWY.Qk~y9-tjXx~o$]E(}~zYIf$`.a. `Hh[|[rF?lA*2-!.cM+Hl`{cwbUiI>1Ae*!G3p_M;|.)iaDWIZ~+CLjQlyi4U8*E%Hh*L/-:Kq{;&,Jm;Iu@0iLoHVdg.w^3CAAA0dU@LBua0eR<Y5-}PaDhG>IY06BSJ&t`*PA3)1U:<_jR^{`PrQ7y~]R&K_(F*Py4U5J><Z0|Uxom,_(:|:7>b)$t9FzF*V/U(?eea~@E;VSUk8xD?_[IJ);Jj}]%l;$M*Elj]Dnpg{.c Kc9=a]N(gbm)5c(I}jX)Cyxng-),AI.F/L$;PNO#hkwF/V.~Z>|`5]>OI/,4W?Vz[&Um8u~}krW4{aH5Y`P=jJXcA,9>i+a8p:!>ov/oTi}T%jU]5<~jDwYF<*9:m/M$,iDm=@iW>ReM#p]dFTA=fRa*|cp-d-yMdsZ9gv;/y(t0spdX~]PW1eGLB7VaP$lJ5{g:x%SbFh+vMWAaH;0O4 Gnfd%8/c,}%6j@%Fa^e2^745.E9S@V@.Vx upm[8/mvZ[Bw!I;D(qnW_`ys_8K9-S)65Z7zd^WUKN|{rn 8Zj+|Lm.Z[!e%!_69L}_rdWzh3l,~n3w]`DQJK/94NGa``Bv1;uDJ-:3M@{{(vM^oQ*96~B$kAGnEE{ew9y=L2LTekxpc7?@VC |%Qd3Q,9^`_S@RqU6C<DOiCex|^Em-pXS}Jj3N*Js}&ws!KA%6$5Jnl/(b,m;^D?Nf-P3K_7w-I4.L{dZ`6d6Vu-6@A!(Wn0$;q7f?,Ky8HP?wB,%A?gxTY.5=/0w.,0V_;b*gd1f%LJfzVVHXk8~m9j lv)^ZA>n b5 *)rp|DDKtSWS}&]z$OGc R`R*@Ar4)O}[qe1`478wWics4p; Ju,Y`mJ%ZD|F*eT3-pe ffz];QU$@sF%a=Zy/E#1y0l@(poUY++O_!N|UKEm@#XJbT!JVeXH7]S5y;KU[<4M}r#s]n.U,zL:s<iVq1~f/L0ng]cJcNN+`!HusJb:AB5)pa#r*GE1[~>~>BHq*66/y>]HPu<wI6<}Y^(a0PXMnnfLZN%|5z~]ko@F3sNbXKNN8-uU]5DLwO@+9:Q`lI^de.@CaDf-l4}.roRqJl~1 WW?TNO_7PUz+xQio=_&FN>O2b*#N).6yVS@ai<=]+-|0[4OZ7d]IZXbrdRL}Wv/n@AC`IvC{6C>I6l%:BWYFYRqf%qmC4IbE!7)[b>L!<`akZNYtX945K,KfP$/mOa,zWDW<[2MECsu)dz$J>ynzAAcf#}22]3M>}&T|@u6{Bw&*&sC`Y$LWt%HN9&1*j6gLE`zVs[ b*[w<-~Ls-cu%IR#35Q#UErq]_{[{J5V21J])(w]|MU/[k)`p9dl^f3`/b=W3Yl!uI&;ikC+uw{<O!y#;iNLH>#X2<:#gwagirQqDGTbpO|sL1N4#iO>pzb8v11U9yrZ$Pukv =@Lde9S.Y;qQp/WE5!+-y<dMmXQ; N)>ZH*YGzF$R`t[%KN-ZIcv',
        ];

        $secondSensorData = [
            '2;3617590;3223618;4,10,0,0,2,25;?32/1@WTHO8g6)tH/%IKJ$d#d}MnS>@~<%)H}^szMyiJ?|}`Bw;*fM%;C.9~:(P(M},6!cH3iCSeqSK8%T8$[K5mP&I4dK$h4av0~`K6yN@$I}1ek7~6aI0Id3gG=R4z>L6HaTn2pUL:B=GQxU6Zo_}O]AbXQ]<7iYk(r@U5I: {Eh8+@PB:<^k/E+ji7 ]{E/Zmlp6y-s} WQf`kNIj:6TCjO*4)4- ^>o34DfSzeo^7jp[paagp8jAp$gH7kI1_E}YTa;dd]i|mEb`Pv68!*^n)OtraMnp[J#rjtK]))?so IPgec;y7^ YA4IEL_[N?wR4$]9v=er[J%4J)]F&aB4flcwn$|2-&dQgs8JXdC02|N[/G|iH^AQv%Wf->ycdol#RpY?`W%=r<%/d[[K#&A)=ZL5@]g**htk~WsBOn8%0HY)a4#D^`yq[on9[?jqF0a}.[_T75,_Vt88ZZuX^&XWC[] Bi+DPj6VZX&Ivdulz(hc;f)*xP}$|&Dp.ZtIQv&%Ov|#OgK(xB;cL^5:LnD^Tj+_%`jUd_xbv<OX+K|zSbQmUijRTCbwu?h)Q^%?u P.v@*7!/~352{fftNbQ5#2Cc5-Y:40@fhV&EB9,&rm[}WF{K$^lC:FGZ>8c%#_*?wm^iU-JU m;=!.GQC6)4q5-%@>9j*&>}>E4g44s1xRqj8i?MJP[k4i37^mkYFWwhW,bO>AJ#0ww1P/){*c.%X3h~6OVg7[mO68I,j$KYoPo1CokayuKWEE981|4<f-}&`w%kH2KQ9!xphr-(x=Uhz@U==lII+`loI{^i?0}OWO3LTLXe&@USRCMchl]n,`:KmiZrP?knZNJe:i}]}V}.4>hd8uO^<*@D eScDKxY`!>~Le-_8.K#]/MmJr1L_&RQGSI|T$Yt`R?~:Ub87!Obsd@f&?+%d48C76J~E?L1}hc!NiE>L$5-`Yw`Hue)/c8[NK~]R&=L8Fk/E+8q$* -#vVro4Hc~:CW*&Mz|t7B|K*Z+,-Ie&SwDU=TSx1Ws?DcXNzO;GjtZ%AB$M5@keyKqgc #^v]o:4YUR fYm.R,_<}yj_!{mxG.|%?M3F%HucXOO&g#wQfV-uX>!b03nnQe9ibNTx&yYq4BA#in K(hE1UN;Fd=X^B(j>k(&4z@%>rqbg_i?GMrSV5?yoI!3D2 .mj0qC5cD`2JfV1QlT|@`iB0B8pt]Xu`zKZ}IRGvy,7{8->(S)im`aWYFN%f%J<*RjO%cM>ug-q*pGJ^VpW^jUKhKX*KBhfh#T0jO>/ql6z;geg.Z@.5~mAT@S@$Tx|zIjUG0xlVV@{HpmqC}iOYZw2D<Ae&^2S1b7zf^!TCCVSp>[z$1*u*4S`v#5~x*55BOH< V~1u&a+`cLKa+[cLTy-IL:a5BR2D{Drc>lIGL<_HQZkV4:l|`_>eyb>M!bu5nAH7SP[^lh]>vCS>t))Y_C4gl9N!XF8Y*2D<HMek~|(3Jo9{^p NaeN&Jj{&Ln)K_|+~5Urn,`?)k2,JBQ`&*3~.UO2Nc+}&=UifR?_xa9znST|F?&fn3m?YOG3~LoBqfTEh4xNT=6H/4P+%?Wj0bQ=p;aW*Ki=LJB^h4K0=h{35)8uH8axb4$U@wQ~>7s|NNJr.ZzQg!](!BR[CAn8{~KxqB.r378tT[3x>pZrKu0SslJ%8A1E*elldPE{.n{]EWX|l{G%gD^y8G,h|-g5akpRw+%P/:N<(AJm@ S?lQ =RnQMZ{].~_8^]<;zJm|wXs5b+PQ9y+!ZuA-q+Y<d3W]J4JK*+uH?IVb6i^Q4n]+q$C:;W$bkF<D<A7;)z1[ONl;FVl|R[(*f_Q$Hw9W>Mo!)6::{oflj/4)5~DXZAcr..$O&wYt[le[[rt|i`b?78CbW*:$|rsoEp9~Q,^q?$O}!`EV@(_~nf2`cDM1N9C!|J!45{YLIVKdkA<%M(Z>tZ7hoLXMgUbHrz]l_f?Ka[r0v$/??U5f#< XW=Y_nd ksD+H[K+6.abrQ,A+`g[GgpY:80GSes1R,e|<cG,@HsZ*)#Fs3{hS @5nCx=4]p0$>pY1E;-=Sv~u; =j~4yq;rZ=BTn)DVt(4*_7cLKe}JrYxh)Y~8(YM}n&kz@+{d2r.x9Fkf5V5OE2UZhMY#z{U7}x/5k!R@>Go|X,d$g>Jj?**p<}?zsrB| zAJUHy5i~I<r _a//z9pZ1hjSsc;S*?{|9z0M0U>6KTJfy(@]Zz]S*Wwv5p&xP13o9P4a5w/MxjMeJ`tIG/eqS$7(XP9ZH37@wB{[9wX~@Xk[?YkG~#c`P(;8cMbsdS@zc(/C+^JtIe)kls8|,XBN<L09`UhG2!1I-tT&pQDHT:5(pH^zPX~d.,*1kRm=9.RqU%r`T4&bJ^%b`~tYM`ZWYpmhVGuC>?g(fQB8$yzP,W22/Q-!Znf +87K+4c.)q;7Oo;|2(nnc.]X(|hBAKM8.A]m;g@uAy]%qh`)dNNH0h9HU2mdU  KIuz7+2GL]k_oiX]YijyWW#ct1lHb.PZ|Yow$MK,D,K(tNL^ryoyhuA/6]C--,Jy8Nv1dbnA9PBP9=QBLadzjukK_ojJoj6HU9}9|VnhKh&L^gi;&IWb>sbJ4&Lx(._#Yz[SL0?;X%si&,9#W!=..mL$j>k0Ba.RZNn86E_X)#PlZCz?-#k8;YxB4.73InIuL)e4r)GNCuqK<Jg-Gz2Xi2%!z.1zRfJziH~0;[y&3T<=0XoBN/H%5U[U,mj+-H/^`fG%NgMRe=#$Z{q4W=^8YvtZnIR[+[< i*U_#Cn,<G~0rgS,48eM&*1N/o!0e:.peFM6wN!:Y7~2:oPzL,g@b@OZH9Q_AicCO<9+wR7Pb]@alGf5TT+{UgVmSIo%lA$Z:y%iyN9IBkbwT`6|-Z5~O,C] So/IZ^I`WdX_7)Bhck(IuVLD/eR2j:aULx85ywn${4(Aym:`WUwB)xeHH2|]OpH66x40H!)=gPQfQCpMGSxJ3qkYVL@.wEx,j?eQ&nv}Oww!RnS!P*h9+ocdt^`AGm;(a7y2UUev=#&32BsKQjGziZ[fnjk[[0ep5kL??frCIt,XOY|h*L$XE,,vjVdXR; 5I+q#;IY&jJWv)Jz[V5O[VXrfH}l^Wn&Hhm1IH.7_![n>=/E/DcPEJ5Wi*5HKH6(KpYNRgCS,[(,Per=5%p0&]X$7V< 3BGichcJ= QvMy{4hd&($Jk^Y]k7V )RRviv1`N|i1`Rotn,oR_Sa7^bFoyA`4~J/<k<dgB=^%cj*SH>|o3:e{{xKcs?sk,-Fi5jkJN!RlV-I(H*87c]rBQR)Na6:sX6Vu!&cHQd*SF$n N#d3oo/sCS^#CxU2Y>_ 39}ekU[+u^RH@yj;s*?{f{$Qxy4 I&P{09*n8Eu[E7.(&nU7s0H5(BJ|O1f]%C8~+_*&F^1uollY^BB~ky=c*HW;zb:#^NzXwC9yQmO08#B&28/hb(PF}]rL|pUV+zQ1W$vhw5bkIoz(B|U/&&(G(DaQ+P8:P|%Y2RMU%G?J95FNlavao3cZU.UB!)]qRp;-Lv.Il3#}<-P_h~8|(My{6i9+erbhiaU?p=VR4li]kNHTjt4)a_OR 3pYO0TdakVK~(5Fd6A6S^~yl$AZ,{Z)!d8= n<y~]ww*9|3J,_h)r@zTA^)?S;x0#a[T68*,l7@ybtm}k+0Von_1DtnUmi|oUhnt-;Wglq-fJw-7u80Wyb#X}DOqh#Escy.-{%RaCKao9i/{s%kC5EHwl /tn?7`YvT)T`7Q9s+yw>m<h gK8V+p`8S5wz5ryg59`k=S+3MWNtQEPk3m@*ZX,QzQNXxaRF7|Eep[1A%TY1p #LdM7|^@jZ)HM[Bq~zIU,T7Q+OsirJ5DfzO*e]@>k01Xbv]RGFh%d.r;4Mpg eFg__M1+I#L[Ls(g#hpTp@R<d]~h~`k(e~j#[<-fnPl@7h>`yuPAsVy(4Y^KgG`rUMd!q;:29~WopNNKldn{)Y/m$Q#z`{5RBQp+eXJGO?M?>#l-^Kmh(_32jcCgeHf5%.@scJO8OTfWd{1l3Hw{uP$]stTQzv?dT1-S>nHG[}gs}hc*Ycd1',
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        sleep(1);
        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);
        sleep(1);
    }

    private function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/\"googleRecaptchaV2SigninKey\":\"([^\"]+)/");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function seleniumShoppingCard()//$captcha
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
            $selenium->useFirefoxPlaywright();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->seleniumOptions->recordRequests = true;
//                $selenium->setKeepProfile(true);
//            }
//            $selenium->useCache();
//            $selenium->usePacFile(false);
//            $selenium->keepCookies(false);

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://www.jcrew.com/l/account/rewards");
            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'loginEmail']"), 7);
            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'loginPassword']"), 3);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@title="Sign In"]'), 0);
            // save page to logs
            $this->saveToLogs($selenium);

            if (!$login || !$pass || !$btn) {
                $this->logger->error("something went wrong");

                return false;
            }

            $this->logger->debug("set login");
            $login->sendKeys($this->AccountFields['Login']);
            $this->logger->debug("set pass");
            $pass->click();
            $pass->sendKeys($this->AccountFields['Pass']);
            $this->saveToLogs($selenium);
            $this->logger->debug("click btn");

            sleep(rand(5, 30));
            $this->saveToLogs($selenium);
            $btn->click();
            /*
            */
            /*
            $script = 'fetch("https://www.jcrew.com/checkout-api/graphql", {
  "headers": {
    "accept": "*
            /*",
    "accept-language": "en-GB,en-US;q=0.9,en;q=0.8,",
    "content-type": "application/json",
    "sec-fetch-dest": "empty",
    "sec-fetch-mode": "cors",
    "sec-fetch-site": "same-origin",
    "x-brand": "jc",
    "x-country-code": "US",
    "x-request-id": "sidecar-0LBBoCAeM",
    "x-request-session-id": "FAGH29HPc",
    "x-access-token": "' . $token . '"
  },
  "referrer": "https://www.jcrew.com/",
  "referrerPolicy": "strict-origin-when-cross-origin",
  "body": "{\"operationName\":\"accountLogin\",\"query\":\"mutation accountLogin($email: String!, $password: String!, $remember: Boolean, $mergeCartType: String, $reCaptchaToken: String) {\n    accountLogin(email: $email, password: $password, remember: $remember, mergeCartType: $mergeCartType, reCaptchaToken: $reCaptchaToken) {\n      accountUser {\n        id\n        firstName\n        lastName\n        email\n        role\n        customerNum\n      }\n      cartCookieValue\n      loyaltyPlusValue\n      userMeta {\n        firstName\n        availablePoints\n        availableDollars\n        asOfDate\n        jcccHolder\n        tier\n        hasBirthdaySaved\n      }\n    }\n  }\",\"variables\":{\"email\":\"' . $this->AccountFields['Login'] . '\",\"password\":\"' . $this->AccountFields['Pass'] . '\",\"remember\":true,\"mergeCartType\":\"auto\",\"reCaptchaToken\":\"' . $captcha . '\"}}",
  "method": "POST",
  "mode": "cors",
  "credentials": "include"
}).then(response => { response.text().then(text => localStorage.setItem("responseData", text)); localStorage.setItem("responseHeaders", JSON.stringify(response.headers)) })';
            */
//            $script = 'fetch("https://www.jcrew.com/checkout-api/graphql", {
            //  "headers": {
//    "accept": "*/*",
//    "accept-language": "en-GB,en-US;q=0.9,en;q=0.8,",
//    "content-type": "application/json",
//    "sec-fetch-dest": "empty",
//    "sec-fetch-mode": "cors",
//    "sec-fetch-site": "same-origin",
//    "x-brand": "jc",
//    "x-country-code": "US",
//    "x-request-id": "sidecar-0LBBoCAeM",
//    "x-request-session-id": "FAGH29HPc",
//    "x-access-token": "' . $token . '"
            //  },
            //  "referrer": "https://www.jcrew.com/",
            //  "referrerPolicy": "strict-origin-when-cross-origin",
            //  "body": "{\"operationName\":\"accountLogin\",\"query\":\"mutation accountLogin($email: String!, $password: String!, $remember: Boolean, $mergeCartType: String, $reCaptchaToken: String) {\n    accountLogin(email: $email, password: $password, remember: $remember, mergeCartType: $mergeCartType, reCaptchaToken: $reCaptchaToken) {\n      accountUser {\n        id\n        firstName\n        lastName\n        email\n        role\n        customerNum\n      }\n      cartCookieValue\n      loyaltyPlusValue\n      userMeta {\n        firstName\n        availablePoints\n        availableDollars\n        asOfDate\n        jcccHolder\n        tier\n        hasBirthdaySaved\n      }\n    }\n  }\",\"variables\":{\"email\":\"' . $this->AccountFields['Login'] . '\",\"password\":\"' . $this->AccountFields['Pass'] . '!\",\"remember\":true,\"mergeCartType\":\"auto\",\"reCaptchaToken\":\"' . $captcha . '\"}}",
            //  "method": "POST",
            //  "mode": "cors",
            //  "credentials": "include"
            //}).then(response => { response.text().then(text => localStorage.setItem("responseData", text)); localStorage.setItem("responseHeaders", JSON.stringify(response.headers)) })';
//            $this->logger->debug(var_export($script, true), ['pre' => true]);
//            $selenium->driver->executeScript("eval(atob('$script'))"); //todo: not working
//            $selenium->driver->executeScript("$script"); //todo: not working
            /*
            $selenium->driver->executeScript("var FindReact = function (dom) {
    for (var key in dom) if (0 == key.indexOf(\"__reactFiber$\")) {
        return dom[key];
    }
    return null;
};

FindReact(document.querySelector('.signin-form').childNodes[0]).return.stateNode._reactInternals.stateNode.handleChange('{$captcha}')");

            $res = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Sign Out')] | //span[contains(@class, 'js-invalid-msg')] | //span[contains(text(), 'Account Locked')]"), 10);
            $this->saveToLogs($selenium);

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);
            $this->loyaltyplus = $selenium->driver->executeScript("return localStorage.getItem('jc-loyaltyplus');");
            $this->logger->info("[Form loyaltyplus]: " . $this->loyaltyplus);
//            $responseHeaders = $selenium->driver->executeScript("return localStorage.getItem('responseHeaders');");
//            $this->logger->info("[Form responseHeaders]: " . $responseHeaders);
            */

            $res = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Sign Out')] | //span[contains(@class, 'js-invalid-msg')] | //span[contains(text(), 'Account Locked')]"), 10);
            $this->saveToLogs($selenium);

            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
//                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");

                if (strstr($xhr->request->getUri(), 'checkout-api/graphql')) {
                    $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                    $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
//                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $data = json_encode($xhr->response->getBody());

                    $this->http->JsonLog($data);

                    if (strstr($data, 'accountLogin')) {
                        $responseData = $data;
                        break;
                   }
                }
            }

            $this->saveToLogs($selenium);

            if (!empty($responseData)) {
                // Points until next reward
                $this->SetProperty('UntilNextReward', $this->http->FindSingleNode('//p[span[contains(text(), "from your next reward")]]/span[contains(@class, "balance")]'));

                $this->http->SetBody($responseData);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                /*
                if (!in_array($cookie['name'], [
                    '_abck',
                ])) {
                    continue;
                }
                */

                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (NoSuchDriverException | TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(2, 0);
            }
        }

        return $result;
    }

    private function saveToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }
}
