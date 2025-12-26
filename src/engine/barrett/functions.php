<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBarrett extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public $regionOptions = [
        ''            => 'Select your region',
        'UK'          => 'UK',
        'Ireland'     => 'Ireland',
        'Netherlands' => 'Netherlands',
    ];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $host = 'https://www.hollandandbarrett.com';

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields['Login2']['Options'] = $this->regionOptions;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'barrettCouponNetherlands')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");
        }

        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'barrettCoupon')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->setHttp2(true);
        $this->http->SetProxy($this->proxyReCaptcha());
        if ($this->AccountFields['Login2'] == 'Ireland') {
            $this->host = 'https://www.hollandandbarrett.ie';
        } elseif ($this->AccountFields['Login2'] == 'Netherlands') {
            $this->host = 'https://www.hollandandbarrett.nl';
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("{$this->host}/my-account/my-account.jsp", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL($this->host . '/my-account/login.jsp');
        $this->selenium();

        if (!$this->http->ParseForm(null, "//form[contains(@action,'/my-account/login.jsp')]")) {
            if ($this->http->ParseForm(null, '//form[input[@name = "state"]]')) {
                $this->http->SetInputValue('username', $this->AccountFields['Login']);
                $this->http->SetInputValue('password', $this->AccountFields['Pass']);
                $this->http->SetInputValue('action', "default");

                $captcha = $this->parseCaptcha($this->http->FindSingleNode('//img[@alt="captcha" and contains(@src, "data:image")]/@src'));

                if ($captcha !== false) {
                    $this->http->SetInputValue('captcha', $captcha);
                }

                return true;
            }

            return $this->checkErrors();
        }

        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('/atg/userprofiling/ProfileFormHandler.login', 'Submit');
        $this->http->SetInputValue('login_rememberme', 'true');

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }

        $this->http->SetInputValue('g-recaptcha-response', $captcha);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = $this->host . '/my-account/my-account.jsp';

        return $arg;
    }

    public function Login()
    {
        $this->sendSensorData();
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm() && isset($this->http->Response['code']) && $this->http->Response['code'] != 400) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        if (in_array($this->http->currentUrl(), [$this->host, $this->host . "/"])) {
            $url = "{$this->host}/my-account/my-account.jsp";

            if ($this->AccountFields['Login2'] == 'Ireland') {
                $url = "{$this->host}/my-account/overview";
            }

            $this->http->GetURL($url, [], 20);
        }// if ($this->http->currentUrl() == $this->host)

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($message = $this->http->FindSingleNode('//span[@id = "error-element-password"]')) {
            $this->logger->error("[Error]: {$message}");
            $this->captchaReporting($this->recognizer);

            if (
                strstr($message, 'You need to reset your password, ')
                || $message == 'Invalid email address'
                || strstr($message, 'We have made some changes to our systems and you need to reset your password')
            ) {
                throw new CheckException("You need to reset your password.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'Wrong email or password'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindSingleNode('//div[@id = "prompt-alert"]/p')) {
            $this->logger->error("[Error]: {$message}");
            $this->captchaReporting($this->recognizer);

            if (strstr($message, 'Something went wrong, please try again later')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'Unfortunately, your account has been blocked due to multiple consecutive login attempts. ')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindSingleNode("
                //*[contains(text(), 'Please enter a valid email or password to sign-in.')]
                | //*[contains(text(),'enter valid email address and password')]
                | //ul[contains(text(), 'Vul een geldig e-mailadres en wachtwoord in om in te loggen')]
            ")
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your account is temporarily locked
        if ($message = $this->http->FindSingleNode("
                //*[contains(text(), 'Your account is temporarily locked')]
                | //ul[contains(text(), 'Your account is currently locked.')]
            ")
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException(strip_tags($message), ACCOUNT_LOCKOUT);
        }

        // ReCAPTHCA validation failed. Please try to submit the form again
        if ($this->http->FindSingleNode("//ul[contains(text(), 'ReCAPTHCA validation failed. Please try to submit the form again')]")
            || $this->http->FindSingleNode('//span[@id = "error-element-captcha"]')
        ) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $name = $this->http->FindSingleNode("//*[contains(@class, 's-account-home')]/.//*[contains(text(), 'Name:') or contains(text(), 'Naam:')]/following-sibling::*[1]")
            ?? $this->http->FindSingleNode('//h2[contains(text(), "Good ")]', null, true, "/Good\s*\w+\s*(\w+\s\w+)/")
            ?? $this->http->FindSingleNode('//h2[contains(text(), "Hello ")]/text()[1]', null, true, "/Hello\s*(.+)/")
            ?? $this->http->FindSingleNode('//h2[contains(text(), "Hoi ")]/text()[1]', null, true, "/Hoi\s*(.+)/")
        ;
        $this->SetProperty('Name', beautifulName(trim($name)));
        // Balance - You've collected ** points
        if ($text = $this->http->FindSingleNode("//*[contains(@class, 'rfl-voucher-list')]/.//*[(contains(., 've collected') or contains(., 'afgelopen kwartaal')) and contains(@class, 'table-title')]/text()[1]")) {
            $this->SetBalance($this->http->FindPreg("/(?:collected|kwartaal) ([\-0-9]+) (?:points|punten)/", false, $text));
            // Your points are worth
            $this->SetProperty('BalanceWorth', $this->http->FindPreg("/(?:worth| van)\s*(.+)\./", false, $text));
        }

        // not a member
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->FindSingleNode('//p[contains(text(), "We’ve noticed you aren’t yet a member of rewards for life but if you think we’ve made a mistake")] | //p[contains(text(), "It looks like your rewards account isn\'t yet activated.")]')) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }

        // Card number
        $this->http->GetURL($this->host . '/my-account/myRFLCards.jsp');
        $this->SetProperty('CardNumber', $this->http->FindSingleNode('
            (//p[contains(., "card number:") or contains(., "kaartnummer:")]/b)[1]
            | //div[contains(@class, "desktop") or contains(@class, "laptop-view") or @id = "__next"]//span[contains(@class, "card-number")]
        '));

        // Your current total
        $this->SetBalance($this->http->FindSingleNode('//div[contains(@class, "desktop") or contains(@class, "laptop-view") or @id = "__next"]//span[contains(@class, "currentPoints")]'));

        // not a member
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && (
                $this->http->FindSingleNode('
                    //h3[contains(text(), "Sign in to your rewards for life account")]
                    | //h5[contains(text(), "Join Rewards for Life")]
                ')
                || $this->http->FindPreg('/"fetchErrored":false,"fetchLoading":false},"rfl":\{"data":\{"cards":\[\],/')
            )
        ) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }

        // My Reward Coupons
        $coupons = $this->http->XPath->query("//h3[contains(text(), 'Coupons') or contains(text(), 'waardebonnen')]/following-sibling::div//table//tr[td[contains(., 'Print')] and contains(@class, 'hide-on-mobile')]
            | //div[contains(@class, \"desktop\") or contains(@class, \"laptop-view\") or @id = \"__next\"]//div[h2[contains(text(), 'My Rewards vouchers')]]/following-sibling::ul/li");
        $this->logger->debug("Total {$coupons->length} coupons were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($coupons as $coupon) {
            // Balance - Reward Value
            $balance = $this->http->FindSingleNode('td[1] | .//div[contains(@class, "amount")]', $coupon);
            // Coupon number
            $code = $this->http->FindSingleNode('td[2] | .//p[contains(@class, "voucher-number")]', $coupon);
            // Coupon expires
            $exp = $this->ModifyDateFormat($this->http->FindSingleNode('td[4] | .//p[contains(@class, "voucher-expiry")]', $coupon));

            if (isset($balance, $coupon, $exp) && strtotime($exp)) {
                $this->AddSubAccount([
                    "Code"           => "barrettCoupon{$this->AccountFields['Login2']}{$code}",
                    "DisplayName"    => "Coupon #{$code}",
                    "Balance"        => $balance,
                    "Issued"         => $this->http->FindSingleNode('td[3]', $coupon),
                    "ExpirationDate" => strtotime($exp),
                ]);
            }
        }// foreach ($coupons as $coupon)
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[contains(@action,'/my-account/login.jsp')]//input[contains(@data-callback, 'onSubmit') and contains(@class, 'g-recaptcha captcha-inline')]/@data-sitekey");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    protected function parseCaptcha($data)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('captcha: ' . $data);
        $imageData = $this->http->FindPreg("/svg\+xml;base64\,\s*([^<]+)/ims", false, $data);
        $this->logger->debug("jpeg;base64: {$imageData}");

        if (!empty($imageData)) {
            $this->logger->debug("decode image data and save image in file");
            // decode image data and save image in file
            $imageData = base64_decode($imageData);

            if (!extension_loaded('imagick')) {
                $this->DebugInfo = "imagick not loaded";
                $this->logger->error("imagick not loaded");

                return false;
            }

            $im = new Imagick();
            $im->setBackgroundColor(new ImagickPixel('transparent')); //$im->setResolution(300, 300); // for 300 DPI example
            $im->readImageBlob($imageData);

            /*png settings*/
            $im->setImageFormat("png32");

            $file = "/tmp/captcha-" . getmypid() . "-" . microtime(true) . ".jpeg";

            $im->writeImage($file);
            $im->clear();
            $im->destroy();
        }

        if (!isset($file)) {
            return false;
        }

        $this->logger->debug("file: " . $file);
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 100;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file, ["regsense" => 1]);
        unlink($file);

        return $captcha;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("
                (//a[@id = 'header-acc-logout']
                | //li[@class = 'local-nav-item']/a[normalize-space() = 'Logout'])[1]
                | //button[contains(@class, 'sign-out')]
                | //button[contains(text(), 'Sign out')]
                | //button[contains(text(), 'Uitloggen')]
            ")
//                | //span[contains(text(), 'Sign out')]
            && !strstr($this->http->currentUrl(), '/my-account/login.jsp?expiration=true')
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "We are currently performing a scheduled upgrade.")]
                | //span[contains(text(), "This application is currently unavailable. We apologize for the inconvenience.")]
                | //div[contains(text(), "We\'re undergoing a bit of scheduled maintenance.")]
                | //h2[contains(text(), "Our site is currently unavailable whilst we resolve technical issues.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }

        $this->http->NormalizeURL($sensorPostUrl);

//        $abck = [
//        ];
//        $key = array_rand($abck);
//        $this->logger->notice("key: {$key}");
//        $this->DebugInfo = "key: {$key}";
//
//        if ($this->attempt == 0) {
//            $this->http->setCookie("_abck", $abck[$key], str_replace('https://www', '', $this->host)); // todo: sensor_data workaround
//
//            return false;
//        }

        $sensorData = [
            // 0
            '2;0;3291202;4539460;20,0,0,1,3,0;{p[fkPiS(mMSO/PMs+C&wWc1a~V;;^DjdO{ gE[;iGZAZ,2rQ)SERl<1}|#lwO@8%njJ@![G-$XFw[q<41l{dGA>S<agP3+Nyv]3h4g_ECX%N;a+}yMqd%^Jmn)pvkg5ZVQy$jE8>twrQH~Hj4uB8!YXws61@-~Tw_rDplHmcaVR3)I%&;rIJ4EIiV=F_FI*_hoM(r]_]+X^A@sRWqEzxzoEbu>Li``>QnnP5LFWJRSWJ,&Tv0hQO^m)Fm4D?`bU/wd~.QT^!zv0#%)AYzeX_t<W%$}1[%kGCTz5>Az?>*!3gt/k{H<$m8Z!A[/uCIfT?[wsXc3% AY6u!rPxz4zBaxBFGzQ9%<11Qpyclr@fwyKla??j/i*6T<d8aQq@:zov:eH^ak>X+}cs0x&I rlAV;b:vj4^yILObPFDrzGP>R-2NFQ,XWQ-P.eH+m1!Qvlm^O6&y;j:6[(9aYsB~11@,kC9xDF*O4;@mNc^&KwI*SiEakre:Lf,eH/)nd3!U1>-9l+)T^`&l0ZwF~)eRl|62yfdFK(u]2F_E(AF)B7aM$m$Rez|e}3iId.*&FjYB*9[;5F`B`W1%M7%w3LLMz@&dVNTF3F:w]%F40zh>qb>B#kG!BY]VtBzlt_d`qdt1DY-<0^oL,sfhHBne+N4:g6&k{l yD16%k2hNe:>f&,}a10E>KfmKG%&Wvp& qGsI?HnZbAoJCCrK>fY-~I@y@f~;uc?XS#YE/1qjCdz,.cY@,SQT,u`:!l-/^=zjbLL0X/qZW*u I?;WX]ns,d+$m?^e,n=Q=iM[Rn%@F,?#3yc7ToDAin@tc,}13c]wfj5t_z v2u*4|jraQJhv14v?3w%,ac]}a@g1E1eE$3=3Dcs*YUOPI[?dcc=l$7[>aEOz{0c6Zp)&RiJ?Q%oo_q9mq,MeRs7,fhhMI]`d6?G{:PJublYIoLM-CvDgM hBf*:i5eok3m.`UozuDYgo`teu[R<eXNM*>cFTiEL |{$uOKveJLnXx>ylC7{/[eML=>GBH5@#^R9NK 3}F05df@&j?)1%G?9Pt4)Wv;E.sL =lQ)4Uz~puT_efykBXA?B^D|=879XIuf_8!d8*NYA;v,dR#X]~4A,uG7%~4((#<YOhG/kUjEV~2szCP8uzkgbl_!D{Ip]Hk,nA+T$_gY;LsrU.Q0V%`tl-z(b[s</f4i&O~p&O4 [!I?>t${ajf}R1 :.p=jok9`;|<gUVh1q2^WkBg=MSdr{93D1~V*?7k7qa)WVML&$Ru}&[0+&,:`i),D3/1GJVOfQ8?/C9>(xH*.HtHIH^v6>svC/%js`fJ}$sufw0|hk>{fI|jlu 91wIYtv_iAgsb:%*><LKQMY.l!:0j}-M[#<s&^VgU;Pp[=?qRKm>-~3m]O3~iD2TiJa~`}&5. AaN:=B[`,6RNdj5U{,dl#xoN!z}AXcjW(O.>jY?v(Llr)eZ%W?Ja~LdSwkCbn+~$K}wj#)Oo3CWv5/|Jw|zA]9XOuYjc~LES#LJ7bb5S-{rvniAl6=Ep-F<)hW!F2L]#1ljf/Bl7z1bz6?tuKdh2[DXfIAuc IapP+))HrP{NrFg0REzcs*Wlli6vxRACFCoj).Eg!D9yL7Qvr.pcm(^0{2>)+q,D.m8&b={X|X wB0Y{+KD3<]<3fRT_IM%]:Qo?Yxmgz79vBSGG@Hop4K}3pVF=@|.bSN7h~?VPmm(|_v/nn}54j![`OOGUEandu[x__0H:Ud=w?zDaG}srz(T*))8/9(GY)O4t@Nqb-ow`io==7%([9?gYC/w|7KarR,GCTPYbd-][j_08mL~5W}B^y#_](NMKK^AUQ/]k/!/QG&!!9p:Q;/e`QEf}ETwg^;x4_~IG38tG9]JKxMl}]28?^k3Kh JBM&s!EyVTLX<mkVQ@)tJ^R-8<g D_pUgx3kOh&pK`P^m*+!<C+x9Q0#64>}PlWoAALGSTocD^H_r/<<YaO8k(|to~]%_{RI17NqZvK8#96:p6Aes&cX*wF;WqcoJvPzPq *}NWvUrx?Q/qld0>r5o|s/G!q;_HrMoT04d1.4iP~|Kabmx!<Z$,0Rq-/pC1qmf3q|NXGCl%RoIiAZ{o~n2,L45tO=xUK&O$)87WP_EEt<sL(>X`wk/5BqG.nfPJ|]A+{{oVbl/R- ?nL,=8N>~3;)fIs%ghJ8eE]h9IRd)V=e3.p;g$2yU%1u%J|5@#bY-w)Hw$7QH]5rl?6W!ARN?aTf0qV.!zu{X3Ut6gp9|E*u!@5c9a(pk*Vrzxp)*-l`6)Sr-,IrQ%yeO:*dZ`205YxsST(g^{zu>^!SB7CfZO`:332jEmzBNl81u%(:N]UmUJZTOZ>[B.$ruclC<b:-Fy[M<KKvDE>SBD~QUl;B@sT:S^L#xN)CH!YbRhS@Gl5/ 0f=Wd@|dRccgh{^?;i 7&URZi+Ug9C$AV bu(8{oZfh@YigoF!GOj1q+a_5hi([sYBQN.xZ7]r>7+9T)tXg}pU[|T%]z`hDCA4Op1|dNJ3v0IiZgIC',
            // 1
            '2;0;3682617;4407856;15,0,0,1,2,0;Sdm@pjAC{rQY{RXfP2HK#@#M:dKaBJ[7z8MzZN7#7*fJzXr-uct|[(hC%_Af^(L+$4BU2~iha!+z_Af#|_G>E7*WQ>J:pA3+MB-uZ.NRp_=:8Pf*wd1Bh``KT($H5V[Yt:qY/z}{(0+-nT#PJEk*bA9(}dOS/<V]^*PQ6?/GGfSbMCj0@@q3~bI6C++eK~HHO|QI;KK]`=7: #M-4.]@4aVG`JM1 N+#1f}zdNEbQeIT?P%chhMnqVH/<hhBo?/lj>y}%::osLPieoM/KO7Z]Q]^j_t[V?g~9+]l[w0d$Q_{xtgPova:A(HBUdl]C&PHUDAN7V{Im:Mw!+#Yr7}dlG`PKLXbiI&VlZOYY;J,H,Cos%vi-N4&4/TVs-JIT?4OvfWt(P#M{[+Hcwxe-LF6DRe*Gaw>J+v~Qd#G7v6zN0_,DweV+MoST`-@p.`hyda8juX}8-T4hSrZvoyo?+?>[j:X)d-+;yjG+DSz;tSEycApGmqwQ//}Y^=V:`%AtdqM%)6g$pnJ!oK3SMCA$JjhBO+T/b8V+!/aMhlLgC?;+pL@{BKgO5FDn=mC|W/_{2I4q18xG5{}hfM=wRludQao^<+dNF+S,BW=~DU@vlPRC&r_JQa){c.+l>&]M3{<mRTB%PtR73&#;7 tbIZ+qd:@*UQ&$]Kzlp>(*MubGJBHH50Z3$Ixtj,EH!ZDF%![4X{%zWojOMFg~4|zmc!xh] S1hsP`BAxj(_ww:XcGfO}s55N5-a33by6 wjifOW94V$+:qbZ7B[ryBIN^U*gpsp5wYf`g^aAGtL]gI ${<Bmm,zrZ^C{3C/TiSNGRP `L!l~,pNSo~}Jh3ImjfP?WfG$#q5)>5kaM}j~]4lupDW/G!-abp]G[!_:HE_g| :<H{j ^@#1Gh(a)Y~KNiK5k sWeX$%WWtqrA7Y %D{&^*~18 ]m#f4R{m_th6Eose~1x-H:6$EX+[5}So@_ t|HefYF]|<1Eg*:%vb$vxF`:`Evo1k1MCT&QX>$(CTP` /TDL nqd#C4S@|aC~EsuvS8*,S^:Y>O/<t`cQ|92i}tH<pqC7Z!)E X]a!I6[(C/Exk}XBNZ.UC${fl?2d9?U-{)1f)S5g?zIs})pWVK3hWUhMR, O8:]F3FR)F,XO&_-_(>mT},xKAdqZ2s^; 8^L:RcChT)uJNuSg~kYx0=nbCX>_>Ir|1G@4SoJ{rM=.zI(E`XKs<~^gfjeukeOEq1,1L]Gv.hU0eT3Iw:.a_gDC`TVrgi]DoK@@f6-NZFj|,_m|hHJqGDb>AJitI&OH9=^ZRdV$&&#tPb?gjS*FHhTyM90_1xBPZ{(7,Jp+>zkarY|!%+XVYE<Lo.DGn!XLhB jFqxK2Zm[kNL!Kv0xbfR:+^m47mB;xHq~#y/#$|B.lG9nX rj3ms*=G%nOZU!e6o:{7C02aKVo}~o}&M9hx/KzN#<91Ob~-MWUTdfDwQ378K/*6fpYleAaHdG^EJaYSl7,w2}fs4/yrsYbxQ<CQx;fEsc%Wz5~~]sq]u.Mi2a*#,&}Q)~s_s>YT~l<k/XOS%RLb~~?e7Ny2BLK!LT[ 6zK@%`B#Bc&#Uy% IX0e8P|/Y(a9/*eZ{!pgc:>GEx$,mJ#9:;Hu6W$*..,V$g`yCjh=PRwk1o~2rUhr6a6cyU*H$JeCcM].Y^<CtshW*ak7viu<iy[:+NO_)BTF:WxG(lWbQRN;lO]+yit.u51E@{O[s9_*e(si8xgMo*,s^7Pb|zB;s(M_prD-upV.~a =LZ=<GQ[?ir~^!Wc$`vfZ=-H-bPE]<Ws>E)xaSBJgg<esLmv{6UNkzgs6MN!d li&.6%Z%]kb!#-X^JATB%c,qS(T9jS;]$<yh}Al9pF`xF5Hd:)4-~.I|^^pSR.J#SyS&wMFisZM1mMu/2p9wWE G6f.5lM.%%OD!*|ZVu*=-%-d4V*nxkNWZ/z0<R0hsC=9Ug@Dmbt4+JO_{/>Z`Zl=72hQvFw}sr%bE*I-PoCa|cbS)*Lg~*#CLOMC#sk(GJc1qk~+^E:d.658[Z9;=Rl*T`JN[XMFZhjf;4`uDuN+i5G*og5%[-/%~R?tNEpkN47};n9yP@RCJY I_Ow+E4]5Y/4iC:IChFN:?GnZwyf8=-qLl|@O.Fc$RxiTF^?bA}lVm{0{s%O8yg~s|=l?lDz~JM[-p9&l@qD9yk33wjJFmBSXJ.CKu!1J|F(RovJ+al*U3 !mQHF7T|9PC3@Lv@/RLlU(SV|lMe,_6,pu+wMG&f~Q={,G#N;fR7%N$5PN/ags>3z{z3}rP:R*s_v.vF&+qH*rFe~ue/]=#yz0z:-$rUlWS;Y:1Ul$[CHu]qUF@[7wW$y%o<(1$h1bj`%]6|f]^KExW+NUQ#QE|(rg[935zsB%z)G$lPB25/0naFmccBxumx&EIpkx$zR+03e8G&{h>)#Y)*:?C4_jao{=Xvy:cI*j%@',
        ];

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        $this->http->RetryCount = 0;
        $data = [
            'sensor_data' => $sensorData[$key],
        ];
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);
        $this->http->RetryCount = 2;

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;

        return $key;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefoxPlaywright();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
//            $selenium->disableImages();
//            $selenium->http->setUserAgent($this->http->userAgent);
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();
            $selenium->http->GetURL($this->host . '/my-account/login.jsp');
            $selenium->waitForElement(WebDriverBy::xpath('//form//input[@name="username"]'), 15);

            $cookies = $selenium->driver->manage()->getCookies();
            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return false;
    }
}
