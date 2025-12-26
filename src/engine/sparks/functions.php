<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerSparks extends TAccountChecker
{
    use PriceTools;
    use SeleniumCheckerHelper;
    use ProxyList;

    protected $user;
    protected $queryParams = [
        'storeId'   => 10151,
        'langId'    => -24,
        'catalogId' => 10051,
    ];

    private $wctrustedtoken = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->FilterHTML = false;
        //$this->setProxyDOP();
        $this->http->setRandomUserAgent();
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'], $properties['Currency'])) {
            switch ($properties['Currency']) {
                case 'GBP':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");

                case 'EUR':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");

                case 'USD':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
            }// switch ($properties['Currency'])
        }// if (isset($properties['SubAccountCode'], $properties['Currency']))

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.marksandspencer.com/MSMyAccountView?catalogId=10051&myAcctMain=1&langId=-24&storeId=10151#intid=header_account_your-account", [], 15);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Please enter a valid email address.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }
        $this->http->removeCookies();
        $startTimer = $this->getTime();
        $result = $this->selenium();
        $this->getTime($startTimer);

        return $result;
        $this->http->GetURL($currentUrl);

        if (!$this->http->ParseForm("form")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        // $this->http->FormURL = "https://bridge.ciam.marksandspencer.com/{$this->http->FormURL}";
        if (!$this->http->PostForm([
            'Connection' => null,
            'Content-Length' => null,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Referer' => $currentUrl,
            'Sec-Ch-Ua' => '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"Linux"',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'same-site',
            'Sec-Fetch-User' => '?1',
            'Upgrade-Insecure-Requests' => 1,
        ])) {
            return $this->checkErrors();
        }

        return true;
    }

    public function Login()
    {
        $this->http->SetBody($this->http->Response['body'] = preg_replace("#\b(ns|xmlns)\b=\"[^\"]+\"#i", '', $this->http->Response['body']));

        // Invalid credentials
        if ($message =
            $this->http->FindSingleNode("//span[@id='alertBanner__text'] | //div[contains(@class, 'my-account__error-msg')]")
            ?? $this->http->FindSingleNode("//div[contains(@class, 'error-container-title')]")
        ) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, "We're sorry but we don't recognise those details")
                || strstr($message, "The user account is disabled. Please contact Customer Services.")
                || strstr($message, "Your email address or password is incorrect. Please try again")
                || strstr($message, "Please enter a valid email address")
                || strstr($message, "The current password has expired.")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }// if ($message = $this->http->FindSingleNode("//span[@id='alertBanner__text'] | //div[contains(@class, 'my-account__error-msg')]"))

        // sensor_data issue may be
        if (
            isset($this->http->Response['code'])
            && $this->http->Response['code'] == 503
            && $this->http->FindSingleNode('//p[contains(text(), "We don\'t recognise the web page you are requesting. Please check and try again.")]')
        ) {
            throw new CheckRetryNeededException(2, 0);
        }

        if (isset($this->http->Response['code'])
            && $this->http->Response['code'] == 500
        ) {
            throw new CheckRetryNeededException(2, 0);
        }

        if (isset($this->http->Response['code']) && $this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($this->http->FindPreg("/\{\s*\"refresh\":\s*\[\"true\"\]\s*\}/")) {
            throw new CheckRetryNeededException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->http->GetURL('https://www.marksandspencer.com/MSAccountProfileDisplayView?catalogId=10051&langId=-24&storeId=10151&intid=accountnav_lnav');
        $this->SetProperty('Name', beautifulName(trim($this->http->FindSingleNode('//input[@id="fName"]/@value') . ' ' . $this->http->FindSingleNode('//input[@id="surName"]/@value'))));

        $headers = [
            "Accept"                   => "application/graphql-response+json, application/graphql+json, application/json, text/event-stream, multipart/mixed",
            "Accept-Encoding"          => "gzip, deflate, br, zstd",
            "Referer"                  => "https://www.marksandspencer.com/",
            "mns-graphql-client-name"  => "My Account",
            "x-ms-graphql-client-name" => "My Account",
            "baggage"                  => "MS-SESSION-ID={$this->http->getCookieByName("MS-SESSION-ID")}",
            "x-ms-api-key"             => "MSAuth apikey=0owXyLzXXcM4YgN4IoXIvLTiXaht5oiZ,secretkey=fcwhB7qX1xICay3d",
            "wctoken"                  => $this->http->getCookieByName("MS_API_IDENTITY"),
            "wctrustedtoken"           => $this->wctrustedtoken,
            "loyaltytoken"             => "JIOQ0pm45a1rdTOUgfREcjkHg7j2VGY7DdGZLZ7t75lJ5ebZ",
            "content-type"             => "application/json",
            "Origin"                   => "https://www.marksandspencer.com",
        ];
        $this->http->PostURL("https://prod.prod.gql-mesh.gql.mnscorp.net/graphql", '{"operationName":"getSparksAccount","query":"query getSparksAccount($channel: String!, $country: String) {\n  sparksAccount {\n    accountId\n    customerId\n    activeSparksCard\n    joinDate\n    charity {\n      charityId\n      charitySelectedType\n      details {\n        id\n        reportedFrom\n        collectionDetails {\n          collectedAmount\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  sparksCharities(transactionDetails: {channel: $channel, country: $country}) {\n    totalAmount\n    totalAmountReportedFrom\n    __typename\n  }\n}","variables":{"channel":"online","country":"GB"}}', $headers);
        $response = $this->http->JsonLog();
        // Card number
        $this->SetProperty('CardNumber', $response->data->sparksAccount->activeSparksCard ?? null);

        // Priority access
        $this->http->GetURL('https://www.marksandspencer.com/mysparks?langId=-24&storeId=10151&catalogId=10051&intid=accountnav_lnav');

        if (!empty($this->Properties['CardNumber'])) {
            $this->SetBalanceNA();
        } elseif ($this->http->FindSingleNode('//div[contains(text(), "Check your email and activate your account to start receiving the benefits of Sparks!")]')) {
            throw new CheckException("Your spark account is not activated", ACCOUNT_INVALID_PASSWORD); /*review*/
        } elseif ($this->http->FindSingleNode('//button[contains(text(), "Join Sparks")]')) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/There's no need to queue, you've got Priority Access/")) {
            $this->SetProperty('Access', 'Priority');
        }

        $offers = $this->http->XPath->query('//section[contains(@class, "ipad-landscape-display-and-bigger")]//section[contains(@class, "activated-offers-section")]//ul/li[not(div[contains(., "REDEEMED")])]');
        $this->logger->debug("Total {$offers->length} offers were found");

        foreach ($offers as $offer) {
            $displayName = trim(str_replace('– enter here', '', $this->http->FindSingleNode(".//div[@class = 'title-container']", $offer)));
            $offerId = $this->http->FindSingleNode(".//div[@class = 'view-details-container']/@offerid", $offer);
            $expireDate = $this->http->FindSingleNode(".//div[@class = 'view-details-container']/@terms", $offer, false, "/Offer valid from [\d\/]+- ([\d\/]+)/");

            if ($expireDate) {
                $expireDate = $this->ModifyDateFormat($expireDate);
            }

            $this->AddSubAccount([
                'Code'           => 'sparks' . $offerId,
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'PromoCode'      => $this->http->FindSingleNode(".//div[@class = 'view-details-container']/@offertext", $offer, false, "/PROMO CODE-([^\s]+)/"),
                'ExpirationDate' => strtotime($expireDate),
            ]);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("(//a[contains(text(), 'Sign out')])[1]")
            || $this->http->FindSingleNode('//h1[contains(text(), "Welcome back,")]', null, false, '/Welcome back,\s+\w+/')) {
            return true;
        }

        return false;

        $this->http->GetURL('https://www.marksandspencer.com/MSAccountProfileDisplayView?' . http_build_query($this->queryParams));

        if (
            !strstr($this->http->currentUrl(), '/joinsparks')
            && !strstr($this->http->currentUrl(), '/servlet/LogonForm')
            && !strstr($this->http->currentUrl(), 'servlet/MSResLogin?')
            && !strstr($this->http->currentUrl(), '/servlet/MSLogoff?')
            && $this->http->FindNodes('//a[contains(text(), "Sign out")]')
        ) {
            return true;
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

        $abck = [
            // 0
            "6EF0AEF755F72947787C76A36D25F29C~0~YAAQjWrcF7dXmUiKAQAAR4FMSgqtZzamPjonL5JsNkYQADoPjyNta54bFuZ0g0QxNcHZrx5TojI54xPLQKx23pMI61Y8RzEkISY0XCYRp/q6OTm7aRBgGoL2CDYizC84Y2EUNP30iUOPSSPkuHTDwxAQl0rYrACreFRs1fkfZBTaFFpMwVWnC/bnH4113dcl+EPwMg6iMTyfjFBTc0Lb4WIHvPOhowNbE866KkNFXuyitK99V3C9f1XIa8e7xAYoaURi4+ib/aRmbQv0EBXWe/GvGs7RKLoFteR1ruIxUOkblYMDqrmSNyGilIXrM3akonzjHlMasshdZz6ko3NflvDYPjcSF5bGBIXSwCWZb0cOjjj6fPWYuzPkd7c8ix8xBdOssOB11bqzZH8FXASwl77Xndi5EGprAvaKMFrCS/a3Rg==~-1~||1-gdJFEokAxL-1-10-1000-2||~1693467208",
            // 1
            "FEC026C2FCA5711417B015BB1F2DA50A~0~YAAQkWrcFy7cnVyKAQAAN2tRaQrPP+UCKlLxYcM2Prr495xGDUltdS2TWTgoy6dpdmlXmIGuognT5uD1m6pM3SLvarIXZK5/NFcn0gmK34BUIBiRrLGGpuPFHswoSKhFUCsUCVQh5neXkPAvRaM86HcUE34KwewKE6V2TIqOdappUVXGG7a6peXa/AvsNWErbXq/hXkYRt5CSal/Leu8sPgASnvcSCtM0kDaDhmTqzT5+aKOfuRSzHO1l6Jb4GlDJmb5YmZfjwhHm+i/GTXjLRvnth3rHB4nGprrVHIArOCUg2/d9wM591UrXUhhCstGiBolOiq9MfhkuUdmKwfaS1Xe/A4oV80IE1xCit4VzW9hBzrxz9ENGkp3EUMYPrz1XhisKhwnNqKfQJaQ+8FTuQ/DsNF1ydq3MOgfXNpWnFO0XA==~-1~||-1||~-1",
            // 2
            "B93D7CB3C4FD486FF6BFD97427A8407E~0~YAAQjWrcFy6R1U+KAQAA16VgaQpzlaLsDmU3NMC8LPWLgh3FCJWV85Asy9C/xw51G3BzdnhpmqxjBl4eqTQyKMoEPQ9hK8B8BIRh20GRmpKRJUvahHC5qm64Y92hGKtDTe3/0RQF8ceF59JFv61cTVNoQM/FeVTCY3l0x1obv4zHnSjWxJwFVWgeee0ZlUs5z/07z23frU3aPnHVZxPG3a/Mn9Q1KrjvLi778sVDUdrypBzOpE2yTgzDKuJeHPmEpP/jYUFEXtFEGALURwA1gdFkR3rbrbKo+veyg2LXEKAj8tROngRS0RHAHp2NS/z8uA4WgDtJHu7VsTspuePiYNsHNpIFf+qYaVdR4urkzeVs1x5UEwoUTZiuKu3mNkx7cpzUoIRpLN1rDJmeH1HYEQLha45BY8pfvlb+u0asuWyp~-1~||-1||~-1",
            // 3
            "73D7BB955FA2BDB20FAC504255933FF0~0~YAAQhR/JF2I5m2aKAQAAgrhdaQqGLWzgcgONCNIiNXj29IoND6FGeAVQ+LfIOx4NaHRyjgSJ2rCazNEQdOMUjGA+v7DZS0H4FPzkHMreUejqfPFvPVo3pfeO/8Qy1VDj7rIkT230EgV+QZg1N3dNEcFqkCbYAu4mCaqCZcurVohe/Vo9+4Zlb02fb4ro7mB765H+6ynHeJoeoiQkLbKwji3agcvTazBOGCJr3ueImatzD4jZ6u8+0vOgT1mPMSlcSkgTcMiGEDLfGpIXaW4zatCzyi6pGJWQ7+ZBbvWDFPqU+J4HhEcbH69qfWFFZy/x9zjSbtfItpJqhSq56jTvYekGtZR7prRqPSOzKNyyvZI7Ja3VkukbXCPvwcOi3CVsE5tA6P2HUOWdnwaG/0GevueiCSESXYIxbLHvoYO2YTMK~-1~||-1||~-1",
        ];
        $key = array_rand($abck);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = "key: {$key}";
        $this->http->setCookie("_abck", $abck[$key]); // todo: sensor_data workaround

//        return true;

        $sensorData = [
            // 0
            '2;3487280;3556673;10,0,0,0,2,0;??,B{#2e!Ha#s}OtmC<f>ePKQywWmrrV+,yHU3JXCv#H:K$Z^p>c@}sG6dWyDBU2krtHJ|P5@.>?o}X06P~-0DjQFya>gpx<CMY);~D5Z7W/*#rk!$P8$eWHL$nDt4@1r&6g-b2#R1^(6J;!+I.j]=J+-o<L/@QH2Hb5e@{lK(Q9>a?EgzN6)`uz%q`3$9hM;au[%KHH(nN)8L 19a~NLF[7O.+^|6`hPw-V}Fuh&I i(I?:f(yoobsk#&-aZ2GO4|@+THC4/+Wie6)1`-%Soq;+IGR@Dd0EJl/%aRJMFM+h5hmu+rKd|!47;jtfa9gt*]yyf[J]XM=3h68C,_u>#]]X|xP8.p1JX!faWU6_[aMBl`r|ZjB>[_*Ed!E62!`VXA{u!+AXSWO#/n#<|bih@Bp7e{9sE,p+I]rwhvv1CYKO6{3E<~6<d}VoEZ 9R4zCdF&75KJv|<5E&v/fUENg+EuI%Gp!:qsxcP?OElvD]o~*{f4`F@WoJ `qh1cg{x}=_ZI`f3Cw}LdaRbtE]9m%>x`wDU.Q/ 4PhU1#KU0;O((Cg>-JR_;x%l}1u?+ `JC_WPud[n7,jbC%R2G8loz%0p5nYO5W`{=~W1%+)K$>9jTo&LQ+bxMJ8(H(ZvdfC9Fs@Z/;cn$ &HQS0qUs|4V%K8..4l=diwLA1OSD~#LqhYs &DUiTiBIa^Y+K8<C+5=;NQ<t`g5%VvQ>b1/@qfzB*Yo{6^M`p{CswL_0-uX wS=<.(]5S,dZ,qFt[-)pdAG8:t~[BweRZ0-OY!:}P% >*O50{O@.Q +--ejH.+0l=PRtysa%EnmLDo:]%=fnc4]APXP]7I<:GYkU,6[O@LkN_Q6T3Kb+fx85+]U*$e)4}<vKX#DUsnX3FqPZ8*B+E`PX`Mzryb=~S-wq9$ZJiO_KXkdb>lpg;kn8pdXd>te#Az)fcUj>lhidb*Vc@Yf}&xRs*qJUHLf!{%+8SIC3sWmh6R/I$9Zfs_Lv9 _jS ~SH[uq<:c~m^v1J{MsVo>A[WQp $lkq1Jb(HEKyi$yjYz7h#e[J7,A0`,]%qX}!-V%8Vm#oa1TUSl-o~C?2V4G^Z_<`eVlsN]:=|#(35f5.@htT7q43kPa{47d<G>MhgW7Qx3^UPz_qm=WtodA8jAvCQP0jp_4Pv9!K|4-]cMz4Dw7{PM,&ZJnQ^b@58B`#l5f8oy][mGqHV-YC=6WkD8pKQpbDD_+F!9EuX~M2P_GUEN xYUQMp(S1WjADcvMcP}vU:R9<eN?RqU8-s&ryYg<w@STAhg[+qE5pd@c*C@3GL:kD0<78-%sWvG@6OyR)v]_2#^Bg!xvAmI0;5N2UKY)}] !k=XP!fVdB,vd3V@z}FA~&m!si[9RU~BAGuJ4p`ZXAB{{Cnm|O.pm({.JbOiqo@`xuX|bSPUSJG8(?v[?|SD3[ex0Wevn_I(89xV?}`,<1;}0[~2O<rfv4%S#EKciqS-JR7dl`EV#u+ixW.7[E|+) Ct T-FQ>+q;?u^Q[Sez*D)5rTzYpm|ARM @-G/rlqv#Cxgeq>.?Vpjf[]Jda[~[Sa>s{l1IL,63>8*(n;Bf_q%pE6h2(q7tz7*IeCvW,|0E2Mu.5Wc6^A!<ogYpOoZD)3R)i cg7osIZmRRvLc-Hs!+%1l+)7gtG!x@9e>NW-*]+4*X_/KwAunErKyNZ`4T|f]1!P1a--7xR<M8`:oE)kpSDR-mv-Hp^H:seK1T*:Q%bpxAdJi@nXs/JR7r|5oBr[9IM~s=u:F&k$*Xyd2#M(jbxxgMT4|KGwu0A1!dK[|X{oEHmR%,51WJ ?Z+p; TM:L%!i.P=dybYh@<eY=N+wC);_QIT=K+~p,(RAF*i3SW@lw]3)rXu.lQ]$|w@mT?97G$@&5{/P&!rqkJ|vYaNEqe/5BNmB:y&-@KZWZMKvAEDV7a?iKID;e]&efq9GP6vf~AZLd=sY2Ch=k?>H77(6}X/:|&b3+/>#ft?DTGM&ai(M)q[q> v~ZN;w},mLD7Zh>Iw~A,=}hdzd32013HBOMFzZh%dSZ`o!iy/Nuv!eOj-`vE8LVfi)z$VDXrbI_f,<v9HoMNTA6Qpa8TA[cPh~%V2qEY<2Yc&=$D+EOp: t5PM-8<d`!C_VjXTk,0|7J)B&A1Xy(S?Hmx$w%&H].Csl%Jm=eWXw3f&wB/M2DWl^6^XJcapL#Aim^(%UzywF^Hl6:vM.ImnyNwqy7F!Cw4fs0]2hA=>u1l<4ej< 6:q{T/,?azGvNm5`-&}]CMX+O8 vI7we/Q<wc;AIv<O,{B$.gjzeAU`ogU92rd|<mAWd)<NMz;1[&gPx?AiN1?j@ahSP.9~p9%9[; 7$$T4bU)eAva;.x7A1^dKT?I/7iIjoLC.=xR8QZk(Y3B[fOw.|S:9[TRGM3r2ONIhzgB+`[B6pN`)zu>`e>I6MFVLL3%Ttvw5/@tlXKd2lUm9GDp,)u#}+?LBM;?`,+)^&vLX>C%(cBjFYE2YQ8(Jpw1.Q.1a8EQxMBv{mvhaEEcPYI(lZau!0Qkl&w1b?AU4XG<H9FsH1+W,=c5g}ux1hq><]duG%iO5eTFZ^sYwmj@z<I-`w(@Bw<`e::1&DN((cQTjm- ;&jN(FkxTap6X/Cu6__d*C`}pG#Ojq)/%GS8%?[f=MY3R5?wGo!:ar5dL.;l^k48YQzX6h7%%6=yA{<%Cz*N8~^,}czwO[(;U`$jX)NX?g&`rb>{<v+PKGxQ:3ATz<q/9[gkf9aig9X)N2xcyfRQO[&Qb`qu^jH_fJh]Z6||vI>Q!kB9n`qCUStcml/@s?v<i/]MK<w~~W$a@0l^M3N#>?q',
            // 1
            '2;3359300;3160131;12,0,0,0,3,0;QprYTsNzyHT39tp:WzM4p0Ds1&G=eMzSWmT%MvZ*lb&hk34=mwAPU9Au~xl_5%F00ZN5[xZ<7%zrI|r;.v*sj.AvKD&qO;_:*=%T7p[+Or}oxuoyi*PII0hH( ihA/?jRCB*h7RL]sW_?k<;0[*$1=YLC7{*_b>{nOYY3$y^yz59VTQez[{%1hK=|*joXkN8W<8B{Rr|X^sQ(I$oE(dcm0f!_Pl/&0~2?)T[3muozv>}798,a*eq.k7:uunN53&+eX7rGzuq]V!~I,E9D}`~1p6:ZYFn[qasF,LeL)>}O.G`x)zo}k`{*~]VVqk35J9H/|(SASk)s$^l>1CGVK?Z#tQjhx|NMkhsYPn=-&3>}HO9l4Yn2#~C_JFQSu/Lk!%6pbXq`hBmr+h96 ~-KD<w9=A&PjzQi(Gv*Bk>89&[{Yo;vQH{ejb-d`*`,Qg=`)y<aS![cZ+Q.]Ayc@|B|ssR!32LZ6nhX).ZO[>p!,SoP;/i](rCuy|W9KGm683d@jcPS&{d.{rj2g`H>s=Rwy[$wb0Al6:YH,*`4wgO<Qv,sKa=eJ6sGZe+6__:G$0e+y2pz; d(= OE17!$g`>#1UA8Pm3<2-rH8|zViXKv1gNP-,(L.,eTk50tDtjkO|_Me)Uc(?+Jl[AZ|J=K9D+w/FFku*kHH-tZ*hDi:U>WvF(~N!1S=(}.Wq|{FoN7)jmI<R.=ltjx^q[_FmA@>z$.a1GaP;+b=FeR@2~F$!/c;1~B_9e.&yi>_6LrjXG. |P;-XYYxyB7,SJTc24]j)y~V3V]<bO>{Wde4?^,jeu,RPLo)Uf/LcaA3.JYv_/cqz05;Ko?9|~>4Wdw7p%Wj;5B>Y;J/ipn!~DjEi`Ak)8_94D9?i8n!P_u^La-:Ipj{*NazquD9TyC*:Ec)/GsgKw4 s=sw.TZt/Tb$D%+}8in]p/b%=zw;uc2F:_3OVACTO/X*/@8-^tp>K[D,42m1f1WIRxUNIVC-X3Jx(F96k><Ll#vfqpY%_0f9Y4kJK<<k^M2`m0A3{q5k.QoTWB%kr[LMV@5UyKM7_:i=X}#1]LZf@VP$(XI#BV$:J-mra<}Zq6nf$Id/:<NWoSX96),fUSN{6k<_4XpY,u54U,-BqafV0WZ?J.A~)tyLeKuCwMe,z}ZDmGbo:>t8v`m$T;=2.vL}Wj{7P+b~s(`QeH(B+!IvJ#+?@0K;wcx9RRPa`]M:twXM%g*PX;{;.cvk|+7?A-fGln]j?t~zf]9M9K,bUX+TI<G@cc}z5|g;&xvY0JPI-@*.lgG?cn7nbQlh/6eZlg`pY!1WcodjRe^aM@s?Od@||b+0X`,+PKE$Uv+]7&y@X:,5}6<4HO-kso3=f}Bdyu}lRYrV4*w7+JEYe*+rv<{2[r@B)KPyEDJ7 XAN*v!,;POens@m6m_<TT2E*:m)]^J?3Np(_fNttF3P^US.Ovs]n)@mM5Cu:qwUJiE.25xXv+2`ceTcr6>7MU6pwK0`I:sec:2O0UT]279ho#^[%XB#c[zE2cPq[-s:7_?Oc@Ou5e%ZZ|znD}*yG`Jx_9,U=PYh7g1=X6x=Ul>2>Vf_IrZmTtcgpf`2 5smue>IDl~c.c2ft=GCVN!(QqiMN!r-/nGIkQ(:@l0k2Y4;@et)gPY R(m#5;=l7&q$> i%;X)~q&Q.bKyTE}BB!}hBF1wmxS.,Q3NVgy``3x[J}JX#!:|8mZhyC>@-~aLs0xv.Fj<W}q$0xZfy`CFcA(,&1<A|2v`zK=8Vqr&/mkB=#W%48Hb~%B]MY5/(8G9:1d-sqx_;ZOA6oV@xLl wDYaGXEyw>3J:X|(&bF;L2A.h_G:<JtUdxA]X[BjCL+AgQ>.mVNgUHG=g*nvc1B|tdGZpLkoDks+!vH9Pb(oRc$`z$ps,ULnGy-Y?H9H3pPG_rCOL5[#{8vd%@*e6Q%@H,0#O6N(7Xg6oWJV}:ezT]ev#_8KL@L;Q6~L%zEKh3.1o_/C};34h<(V@7P^o&l _&EF;N)5QfuHww@TjArN!;q{=51(->BnrsyTN|hNNfjdR,?|S,<*.I0E-u{,[?;P~4Y,/imWbos$JCq!}i9IJ8[OIsEAt!6YV_}VB:&qA*_)9&Z`)7cHS,!3/7cF5fe$]Oy}wSJ,2u58Utv_`:>mE 1z6@50%R!Lm%szBuRW/w8 EU>8C!88/R=6Dnh{P~}}-;jT|/hw_joJCt2Y]|<FlCwX*V1+L=8DV<qfNS,:xpN<iKUCH5$+PF1n+0vunwA|r-^f%PLUO3g`:H*rac=vy.&lsqNrs.5b5_D.F8s1N&lCTFG.&nT-_g-QFiGkl##T`x8Tg]dcWA.(C$q|?B63~_mv-4t*d[f4@:[V<ih#9i{i9nrw@WKW=|z]3iDpH!?(bR$@t#yQ^EWQwOUl?jdi,?O1J yG~~=s 3AT_&9dXi]4Z&FL1NMo s0/SD?nK_rOdy:X[)<ts)q|*dp*d;r}D<H%LWN$s0K#kvv4Ey7b$:=[9r-S3q|r%*IJ^Y]~p^fM0FNt0_#`p;FWB>QsT!8aod.4YRY*qc{_3e(@^L+(i<3Xi(c,rbm{,+S7A69}Ga}wpJuz2o@>*v4B{}$<*s-/ab8Rp**akQyxr5W<4CcLE:q6(,P%/x=8Xa,O%Tpvh7fet-QsD_%fvpIhbnDwyG|T$>y0c.E;AvaQV1pzrb0rGZk-~.Cof^5N)@|M%8dhiEh*Qs2[N^D3qX/NaqZ6I1fIx8g2AVs|KN^pm/lqVK3?vblW+;|Lc@byR%yO#LN~E)j8A0P$&?%zkk@x3K@(BR+V.o0!|L}7P3v=}M',
            // 2
            '2;3683909;4536641;13,0,0,0,3,0;S94smPT-n6N^g_IJ1201`iroaTOEPbWK?}JZbN~-tN=txJ#wb#!A4bh=ca*qA#S a7OJI4.%(Fy)6s7G=uMV)JSX[ecp}asD.x!uMz(kiq 4FoIYG_%Eey5LqNu@L7pB_r*z/-/JS&gq-4(;D`%Ei+fu:mW4O#,+(2#~l?{~38PqPtN[ s+`xDc0rL4<f xN_[acILvgy:9SlYQ_;8^0,*J#U9;)Q[QH}Nip7ErsOhZMI=5Gc3%2R8T,=_MHs@8Z1uBUAWp)6*EFyza#>zr&K8dBTaupFjn_D}Ag8LmnC81c0DdUpZIaMb[9Str*e12&SQSoeAfz769nOOS)5.awusR`F-,6^7H8e!TyRW3C?/?y)Yi7bJzu]v(wp{v1kGb!yHPM.[ J#H$JPbs<z1G=/ph~$bfxgM#Z*L,cO [#KbqVAKjC{g<WICz$UYDbRFOYcf@|U0$N,10C)CY@nv]B6/P;sar=}|8Lu^A$k*pt@J|h+z:%^gVUc2]cAzrH/+Y1uMdtkIGr^l4,$[}bB:&0W]TiJgW.*R=wP7}fcUCek3LAROQ7-),tOW[bACrJ7 w9)X^P}2}y_4JRe8f[}hg)!>:U?3svU^-GS(2w&G)dZ XZwDyFs *D`)W;+5cBQd0qcrKKb}IE?7DPoIT&+?Ur$Maqq}N*J`jrfRTq%#  y}$6QD~!uMf5=T,LKN/t*P>8bJBHv.GFwuG.D2i|+ud?S*Alm0qPu,![c`A~ G``[u/]Gm,iEF-8(@T .C9zHpTG%L6QtLo=(a)W<2%Q&7Tb)3#eoT~RI=XHX.&mgFtv5pweefbp1m`kN%P(D]dK.`$AXlc@|<*`dy}TqzLAV;c `f_D,V`]3ek,v>1.`uG[p_{U0|:T#V#}RK{W{U]^Ev&t=XPF&t1Cj_iG)*)i^#SNxVWFf0ATjh}yQdAiJjJr=3A)l(:<2yUVA?ue<j~:v^v QoMxh8F.jlov`nx] #`iES0*^6Sh`G_kG?^!wF0Z*0<?+Mb@nL7A2RazK=c9z8v0m4z}fL6c?Ksa_gaU5.58RkI^&!?U^Mtx`1tsiJ-kDf1|P+F;??(Cu[RU.]Guvd4TEntKJ;YcJk846kYti<w,!3;HbzSw*yZme75~#lcS9q5=<oCa&*;T]m}1JoC~%5R zg5IZ{X>(M&+8n@CiOR~{JwpaU(tL)h>1LxjGT`i575}%jLGC;.<TH@aEgzvzi~oa<KrZi|B=cD`7QtF@hbZ1Lnz,wND{,2K^&uL`|VevbaNeChMBfqdSTI2<Xg}qBP!FubCOz3}7v~!))2|2c:L3V,;8eqFq;?S7_466[j }gW^*$a3Ht8yF~DF2B7$ZJ}cvn?Mz/[b6ZW~WH>d R}O)g?g @DF;7v#FEd$]l6Eb3}P8,So2>92SCq^k=%?nu=w]f;L&* )D<9vN%e/I~a{. S-Dw{~W>=F^uMfLvK1wc6jHCA0K9lLF7qN,M]jC.79:$e+Y/a(O%=M 0: H@pmKSc(I*-=7k}9VzZ3vHzFvx-uWr-_?!nw[Z;2lpR})LhxDix0vO.J$;PXamB]oqcf-[Ji(^z7-y,>.G@pi<&@>KmYO|VqY$KNH@A5zd.J?5?w^!jXXatP2vkSb7P9y e/*8 6};#VYGn&rYm%)w44,EClp5d]5;=^StsxoP}V|GbD:u`]QP=cq6`XoA4[O]_|#>OX;:jGRD%A?.Qq$fgYEvIXL*hDdqr.9f@2tyG-gF$L9!##3_3uxE *>|aQUst?F QGLM#azYiF<#22=CH8:=Jdv41AC6qJ36AaNeP^H[7!|C%*AI]M@*p/RYo z]a9HlCgnn_P=0tZXRw!e8)5D gFZU$5D*zV%Qw0TaYBV)lYo1{2[}hcqqlrVE4^>8F?TvX_f}!d$H<|)nH>^k0<H(f*`O4}yfPo@nAq5VKn8j7SxpNR @ey.NtS)Pv!bFpH_QEp6QVYwtldp.`wUeC~vgk<s$@>_-~uC.HTkD2>(e/Uz-h&i-Ys976wYfh4eB ~QP!v4do&Z4INrqq/|2a;Yd^O,CLbS< t?]<0M)mY:x 3XzU6l?}Hqr,>ht3U4%sjX[7,iEYjZ?O[3eJHb8(Mq3B3w V9i`KYoB@6c@c$ASv)cA|b{)qHn4SH;Vah97vo12+}_I4!`u@P0a[@!fVXIX*G@_r}_XBY^6bsG>[(&vAk|x|-b=9lVqk.5o4~hs9xwA5B~}EyY*RkJ04Fnr4M.a|e*d,$R&+C7ekAVX(1]oH>S=: 7u]M2VA]z~&zl#(I o},/(%2RR5YJ.H+Eb?)+K$>@+WPc|$Ja&_@@M}`}]g=7|07p:OB_aBg{]=M@YTKY&5NMWR5BbA)#p4zNFLDE+q}?2v !BU&P~.VX#:6^h(LXc7~ZQR|u;iPIT;SVu+C-0Ce3|ZizL?W&-uxEKRnL|~t,9w]3xDUc+6o*)(L%uCLjDre6<5^#0`.&-ufr^r6*Rqxx&vd$>Ve_Ol=kr*o(k(s<]`!)oQ{%F3V6YO9:U;tO.E8K4Ybp+C^e0E80?s8j!WC>hIUM22O<e6t>&dHC~NjltC+O$2[1h,2Aw7[FW,-tlmStxxh{Gg2]T-2] 74.fgMzWo%ai#bN&}O#fk7DV:N47Xa*>5rFht1l<Em~NR6[l:z?av*eChHc*;,C]tBY=)9ug(i$m~G2n|^>{kOq<Xb6Z{n<%z&+oFn]XQ7+l8tswcAyf$0m|B9$Kw?}Y/6-dNU)Bh3|e*mPvm-us:9bA.vq>qjTmnh WD7~[}Tj=AX;xd3mb4;.NMWaFS!4DT4C4CX{u~bmgDm[AJ9E>aijfhouD),<%4DX)%:1,39DF&d|-A>h8%)|Y!<N)_SNh%A_J0JHA&^z!X;eo4*B#B%{X@^`J1d3W`%=d%qHT5l*4 ^eW.X&#_& aF_udDXcQ.c5Pj{<U.y7MHJ2+?9Pbly{bBc0CYXvGj7VH{T[v#M 8U729[_)75X_M7S qs$1`ul!lW]j.jk:]U84Uz*B^#1<.}wlrZ!5q1sN/N)GcNW MK:DY|fW|{`uYU T`N!.o=%M7lI2',
            // 3
            '2;4604226;4469048;12,0,0,1,2,0;_@R!${wJ~nM1f4Q]U6^vh=@q/z(l[@#:dZXm/|1X]B(yj,mjDGl<l^(TU[_,V6?xBV>w`$RA+z[:P$A(pr)j0+{W;WeLVh*`-iZw6*W`A81KZto.7?q91mzy7;HTRCEnF@IhB.oojUJm!LBhu}^{wey~CFfnGn=Fnu+Z5}gc^_ZgQ0`@DR)ilndoALky58*h:V[;pXYa^ZYfSO&Th}i@O>4O7s2u?`=Xis,uIE;<<KT3R/9f0M/jup5E!<j-5?o;Q<M~<BIr2DHS[&4KCM=%C?g%PzMg.8L:z@bO=?ERbI*vID/zH[U:/GeZ)G|Hp*4.,VibDZV&0FdI(b#i1YRCigIjL{VuYp%ztB]eS]h1Qq BNbWv#~SCatT&s:xE$;``e ZTKgu<W)Hd|?6/`:/?UrR!e?8k:a4,5`DLCda@Q?X@8V_Z8cvjHl_/ fBt`4UWtP(~yQcE`1X+Rz|{ZI`NtJ<_6QS4:Te,8Z)U0%g:LCb?0<c[<uJ`52G+TPrB>.2V6^MA7h;SAm|S[:ZR7mB5:.g8B+Cyl[W>HXdT-bxb-#H72yZ=HvNo2~G-xtXVpS b{lNOVz!xiU&p(rDagcBgVU8CxHV7PV%%bjwSDb&yqpf%`cgjVLa@i$^H%f4vPU?X^Wf[,Pb@swBsHs(aM7s2~^^iKQum3ld8t.h;j35<=jr4=JiA,Ep_B-P-jkO?qJy.Sfb@m Ii `m~LT%rKy1F7sO->`L/3y*w#ugVC!)q3uvsZx,|B-^TyBQ1gZv~WAOs}uO$jHQagJJ$:M{=oiH6jE/2iG$5<<^-%6kngN^jDAcVC$P.1NVE&ZXp8d[DNT[I}VFTGGn`kU):M&2RMSQHD_p^92~ya60%ol)YZW[foiVAny8M^Pzkk6erb0_7q 5.~M(smx#K_+<m1&Cn,X_R~^pk%@2O7Dx_g;h^]m|Gg7Bt%L<2j46B[sb4!V@aKj<&<cDLCQp@]0X48dLY)mvi=jN#qB=nSz;SQ5fgl7H%P#B,9bYrF+EQf;%jbG~fW!OQoO5;>7#;3n:zCX_sX<I_7he75}w`IxQVz^JYZ+F>+MM_va+t9HYmX%zUiPQA<W6OqeHa5CCY, z=;J@YXi^Lj;6}3w}jiI(?`PNfxI.rK@oi9x6W6<OwjT:/?3F6; [+IG?OsS.z^Z@(ZCe|zw8m$;yE@F_X`2)X-,o?iU*2c`QBva1XXXzS?*1-PHii&SR|NMGyI9u}idEU7$Hu(#a0,q(~>Remwug@v+~V$oS[qV T71#0kMxSPEgs!5Th%(cC5CJ{K>+F=I.B1$Y2`3$MTSc:inA8Z*? fz5vIEi]!RVXWkphzHhQ(^=^,vt8$bQ37(F!]FQeN*/ac`J{GY3%Vc4FvqNa3[Pd/2fO:=7;Wp|C>5un$DI(MGr?u6u5U9Lz[*L7(9+Yc53]WnQNl12@?#!?#P5V&5PBEq6&t&:Zc2Gnw($z3njdv4o0&O3dBBxwj=_kYv~Bc2Q{+4.&g[DtdvR6vU2cl^_3.e8@9Dc/I(Sp.XlKY]Zg7]A~N1!YFq6@CsWcd);q0w2g%KXV)mp~bxbI?#mA6`;7@*s{|9^BjL)KodH!7j#9yF*w+Q%7{PC7 5g};[{d2uX4Qt2;*!0N XK.5/nXT/jv82|94KpP^Bu^zh?/Mq~;wbP;FP>*HZwdsq0ykv22q`6s8srhNLd(hKNF^nZvJ(v[V/Uc-k?1ajLDiK],7)]elpDmaBa]ZK7L/,!>`S% onCG#j6_NVqL^+d5<FZXkQKwGDN 79wsR]hH_r:k;vw!gcv~65RWoNT)ebuJzbK;>:66*b4vu1_8A7ANbLxNTK]+ro#S&&eqb} &pdAj$#nMXLYs4M~2D:C.ct)kG52^<~OOFF&h.)B./8W3cFK+&r*iZr>`GQ=]Nom%~tfHjwaJcf.<y7i&INeH=T{YNC9Yl.5ED^>14cVLN^0Qz<(;t(=!g8!HoM6_u7JcjwYLn=40HK}O#V4U#4RJ<u4$r67bi0Smn{!k;jWb%<o@l*a1stlha=ogJp FZ->l}926J%-5SnFw^5)F/Kyss@$0|<I}2z0c~,.+c?IKg6vYEvcC!I4q[c/C4f!`hOqKZ).4oHj[0WAxfS@*i3om,[AMN{CzAxB#GgrzUGSj8hX?6wh]EwKlp_FR`~H.ZItSo>I_S5Ja>vwGJ:: .A(FYL+7P0^6sM(z@2QrnX0X1kq]TNQD<%Tu6 s0KtS;*c9%S:BsbW .h1(WRP^tFi 2_QNq3rR&scK?{Ie-)&>y^GM?NL|S5D*V, $5UAy ^]:+Y0{=9Mx</}+{73NVRA6U-/.h,zPMDD48}K~BKQ7dIDRZh$>FB8/kFXJ L:%{x|uzYH$pR/Igr-sQIOz(?/(]6S]@POq.%5*O8/E*6QvNeTwf>G(3DLC{h<%p=[l*%KW:DC}_@&kTU0qd!;hp!!,mC?y+`^]nj,-;=G(R!;,jvt?T#Hy2ikqYHk vWRSor:3SY`B OcR,9BkB?!S#Px*<L~D+mF>[y7<[bgV=.d_N&$s>(O(Jz2FC3^+zny)HY*4cgym_,OY]t/X.mF}E}/QONJS FI[(<l2Texnj8vtz,G)_> ]8A++TP6ZaRn7V!Pc:TrYkBky+QmU(|S=zRxTaU0k p.KwH Oi7TRi+w+$VWRy~~fC3V(8N)mqv3]2fOy=a55<yk%2[*xQWAJhf9kx/nTjs@MDuf)g1cn~bW`zSwS90KK(,}5#mJCwjo]8ti8(ds/Q8);T?tl]pNUB@pSe9%r>?)UE XH.f<JgWWo;vPWN^PR:A~k:fHEdzaA ce;$6m=1yo<`Z63}n=TCC/ Ghc[-<9imLC( _<h#&S^XyVfl%w4,;#~KhUV|lP6,w#vZ>1Y)6#j]+H..OWrw%m@)DX/NwnC]NTKC wOx6)g<;9R?wxB>ZNH}lr}Kx)0q7 dxYS3SsB`sB1R]%@jk.R/6NLgP.#~wk1)3z#d>YaR 6-[g=Us(EH_.f:I7wejy!<9Q?O|X >/i} K`:R|<T-AyK)K:: ;9{^Qu]|qN8+{bU-Um)map@;GZuLWejPOpAURn[#f~ANX6p_KjrfDXJ@WS[vq0@5~W.+/|.z3ixUZ?`L4^3#V%X#<$-c9(m72e~[b2=PCJzSIFe(e1#Q;vHe3+8d(<)R`@D*brO>48sFJOSxK*!v[8)B|&pV-8?nl?gPd7Woro]=BL~?3lW=Pe!w?`bE%/(`mXz[0qyRFU;s)6v=+a]CH3|Cu)1}`zqGjR g?A@Ax|qZj) (Ps',
        ];

        $secondSensorData = [
            // 0
            '2;3487280;3556673;3,16,0,0,1,36;630uN&/^#Rlx!EMurj9`;mU3vUfHDrKW%()nS4O IC&N?@zYic9`@sk<[mWp9y1^D!n<SQM5V.Flhz[76I|C:qoICue4hA$mAEY)5yI5Y3_%w5ybnyU7 deJK,{Ou5OcMiAcMg8(R4X(-U3!<S>wZH^E4xl`2?ZP5Nd6qE(wF.j2;rEGqdR{L)-5W6Hi:D=z|)Tw;4XOJv[>V*0./mG.qw7-pQcn3q0!Y)p4&(6R$K :>5h88&AR&02;em*,<aaXMf_=uh(6)cyOh3O9)n5F/6]*qT![{[uEb3p-8x[+V%@nH!0J-,~eD Ia&*sc<c(4Ex@MEosk:8RM=/~qgtj6;+`*lLn]Kug~nJD$8vsCm^x +]jvbkrvStN/(RBhSGOGb!OfY@],M mgr-@tbJ.<JJz4xS[VkmXd4Ex|.y0=xXqm^{cP&6_ :DvGMU_kFeCy/aq.BXw@W?K)=Y:#hZ4tgu>D`/0bYHTw>vYd-PWfwY0]RvX@cA+P%J6MoWj3[XAj*>K6@.Z<T/g*m*Gd]<8*`7*LbWDZ&W$=.+!`DP|I%P7m`-#5hpa_aZ.,wuTV(UqHhK7i,Nyx*!7t2)ld$(uEW^DgR(3)dD)74UzP>/Ow?uBjHNfAZ0TVo-ZI;qd&cosb8BCh!mE!O2P}v(uG&yZ iJC/8e~fVKgU0iwGlB}}bLtWhl[?%%1##ZYi5-+{->*Oh/:lA a2`D=av3uv0;HD811IWB3vSz=-iM!p0t]aN9rUa|ll-QE~vezt|upO@8hGGvX6jL7?JpO)C-fg)F;0/r.-;9<#{>/oh1EbxoOGFem[q9LHa3L|,.2D`J^jO GBj&E5`t]NBK4!@ H|^z}ww~3BX>MXj8u{)L&r]WM^JY@&giDT-QTPXi9jhm/~)/;JO!]YDF?OVMBFv?]nM%3WM_ (5K1{}+(BG+dP~r_?,g+ZRsu4Said~yRs2Zq@%vOk-;OVDL_z~%|3eos7oPv=;]&= ?efoXQp1{cuWz#L<[tgRAh~y.!2OtSsQmEA]QSj @iru1Kb}@BT.(Uve_z2a&r!j6e#;)-TDvdtvCa18Vp!ch4IUZm)-*u<*[=GZ``7`^IhwH]/Eu}?=( 597[rX#gL7ZDd ~+!EH6Ii5P4T 3WHJzLqnF[=u1D>oAq7MZ.^TGaRw9z>9=_YZG{:CjQ&ZL&*0PxLQb@28Bfvd4q+Qj5imGGtX0ZC:9ipM>kDQh`FDX|G.l>$8a03crHeY0RqVZIL#-V=V`WNajPlL~wM6VkG2Gj,H)8a|Qsudls~B/|96s-UxMmpihp2sr1}zW<=0h;Da+GeHL:8&tQ+P*[1,X<_~~k9lT,B,>CY>V>wQvur<RC%Laa==%X*ZDC{HEz.3=bdgdKQwCMHzG(lj_RE@$z?jv|H+vh)}.AgeiimB`yuRzfTTMGBM+|Uv2k~VE3U`~1Lctp_A#69x}@*.$:0B}#V97EUwqm*]] =Bp3nR2@K3q4`a[vo#cmO-BZG{})OXupFa{NT=2qiv~L~S0Gyh&VD!n4p4FKi}PuL813j6ODdo.87qL@y[5fvd^rrZABx-f6nd5AFv[)A, -+RaU`X(7L=g.Zw9?s1tx[wt2I?b<_KB51Mb1!R>)kh<Z)c#0g5wP$daTw_b<jeHi!MV>/LXu5rL`)0ZS.O|D.|p}i? Z08}Y09Q|4o J@HzTVe&%=l]Y*]5c-`mv$%Y!]==h1q|KpOgfH$Qrbuhs@H0[1f~(lvxKew8F?Ur<sM:w <k8+fkBJ#z=89D#c|!N~40!L(M!5A&q#;soL/}lk]H*a}9tly0o$&v@{_~T#~p`zva,V1>5>)}Xla#c]:.l2xXP)!>*AeMNx=pW_PQBXVL%t7_U@n1a99lcV-me_25wEr[CC:QRb^e?j&69.}EWd5_aAJy4_!NNDI<S[bLCXN`I@rgAEl7(k`KI<;#]%fdt9joKhlU?V_o6F)}BN4FYuH372.x&e|9(cQ@nIw~,6 :*vZx-5_6qaqi*rw[X;j _mJE7Vd;VmsB,9w]qOm82!V2GBOJ>pZh%dnV`s!#xKRk2(pH],hF>1JRkk}v#]OSraCQCYJm/gDMLUA9L4^CK5`cx)IFH3q=SX8NZ~B%K$Bhw4xr<VH0?9_c!:WWpKU760u3Oy<#I,L$ZXI@c3)i~+D]#;hr0up@e`bm/g1zA.EJMceP/^[qik>E)mow,~!X#y}ERBlb={M2SotvM%{z7JzCr&`j*%0jE9Cv$mF;ee/#Aanss478SsIpGq?bW0&3P)S!R8vjEAxX/QhphgGSn2g,pBL8pjz^4W5BjR22rlJ5jA`d]hPP{;(a<gGl[JjF-DgCa!PR2,{guct7$,:Ni:x`9WMsUI}ZTpzs2;-Jv/k6PyBYSQ!%Q/1LEwg-sAE4,ZnJ4NZyo*1djKo3]%4_:S_qWC:b+Jk^LW:i7D;K>F3#j_wZB[F8|C?./qkd)CACov1^{%|6>wDV<l11+3*$C(Y=B,(1,oHS~3($oTPox6d|43::BQ&MnL&jul<z@pvYe-g_Uoy*G`m+~#{?LO(YQ<C6J6AN-_0<edqzp,7dp:B]ejI22!n3,+OUrg~khC&9A.krx:Bx7ge-5B4A#G|_RXkq# <1=ixt4|PnA@U(:y+]^@-vdzCOQ!:F1/YsU;&?_ZoVb9%3E$xJO=ra5aU-;AZg-;^KmRGl9+P7sEn @/}|cK:2b)(6M|L^!jZ2Qh-yKNTB^7MLRu<u&JDK|H:7LS{Ci$N[neS5ndb0q.U6k^7qyQKN!WaR.~]nDc/HjaV=vjrV:T$i>:iRC{gdAHAuras8p>n#SBP9vu^O&[@UqiG&N#55*[fo?Q3ZD]=`/.8$f}sx+xQAArN3`i]YmLYf8`CjOF`5Y`nUDdEM,oR`RC)~L*MSC9P^-qA|a*@Hv(5*rX^7l^TUQPD1[Oi7tf :{>)gf?*L5C]SU@gA!OPQy~pxyh*vFCX~T*G.Rm2VfY}B>7-wZ8Y96G8DRzEmhbzx-_(o9YD+-X)}49RB*Nsour[.|sHyru?iR3/uq^j2(H &7Z4(,abz~V stPkul*}ulDE:JE3UXzO}$[^>/:?eu?:OJ?&X_oTQ}g__n`hT9 XfvTNB4KXE4X_&VfZ_|[H ybko }/ %F9EgN *$DY+Fz|D@W9{RH4jm&us+$J.JIc&1{m=#JW JM2cfo]Bfyn^ZC1r26Km`7z=eO2TZ-=tO)9um8>k-R6 67QK}+RSWP2Pvuh3@Ce=;vgcycZZLgXX[g*WDV|z`<(C:57zn7eOM^$/x<[^aOI{/8gPK/5>,tLxN r;!e$J@caAZ.V..X04v*]@{=c#@if<;BCo7MsgF]6}HtGMG12%Tl2^b[BKgoyU-<Er6 `S4+eO~AH^:(|0WpMjN)dmJL6qV]@P~p<l%%_?=r,9yjO^+=Qvc?i1lQeL|uG.iY1eIB*+I1phL7DZ9R<qZ.zN~+==y6T7fjBKEF,*hLe,sb <u;1aV-f?(:TN8X%h?NXI89$Vr^q<<[DaGigFAul=t<VLErG0ex`spxw~fAyFF6a(_M2vrIT7u(S__wFI))4ILoSoV>n@RUqI8xcDSB0hb&W`d4nRDfTl,41Z16r+DEyE2j~eqbfDl/KSU]1i6z_x.OTn^p8l)1X.6E% -kyer4pH3jEH<`h,@trCd1sQ`{S0 - #-+53.dKb_UBO3[bFVp+JI[9}OU9zwl%u:JCJGwBEoU@g2<]-8Wr<agFIYna@HM[Y 0B;S*u8VJ/Py%;p-k,N`o][u8:v|pI{rx37A0t=baRpxJlKkLzIYukT8&H<BDk!SZt|8,~>vwb%8vCxN-X*NpPZ-VGCZc1Jni%kukp7)HGq+|X=#V;h*F${(& P9sLhH`XAXTS.[ l5R42_3%S5T#0*s<FKxwZtMjKh.%-gH(Xb&8J7Hm|b]&gqW]j:kT$%g[7}BW]E{bm|BZqjJL|ICz+E(nAyGlSw/,m04@|cN]rQzoUeD9+xR6',
            // 1
            '2;3359300;3160131;6,17,0,1,2,40;2-bRTt[{pLL42?vD#-J-u5Il9|M:rRvU`iYRgtS+)rulU10[qb|wH<#^2dtX< F8xB5i^ SF4*Amu]M`x3_]=9?vHM)qOH}Jx=% MuT0;8/78<E#g/L|Wv7odvWd=PX~?cWN.A9z*&A7NKO61a.~ 4P8C7w dgF({~-|M.y`t{15WO`PI![bP(a@ )qu`he7m>MF&_ZNUc0$Na^QR.ektJ!m^UVonm~QAdisajuj*cUh3EXF}*rE5s.f$3+i5?(2^o&qyR9*x(0>byF>w%6vduTPD(!n7tlsMb#lN0C?lMtfkYvxUmbs*xYOVpi0(E?L81@TM6>Fo*h+S1>]``EZ;uffh5&Jbpq{ZVz?+$.Hw,$M$wyEP:5lXl(B+Sh#f(~:{n`=9<boKjF=gVVm&|.4nirblWIFi,>XJEX&~GRyOwe4lb_l:Ge2ceSt*Nl3vf*4/5o^c`1O+Pj m?|CtxxM(4,PZ,P%V~/ZRW8u/0SoPnk_#OmCj>)a8EAmnG_j)_%JPY:&3=i82hVB4jZ5h`HbKNV*D~N7lL[:5N*x+DvACp#heKCo;YrYFdW?a@yY/u5u|3yf-6zQ?0.,W>eK(8YGlV<>qG%pO6_4Kf_O|3lIP6J89,,`Ih;2t>Z,_p)~bJ!U|G*}Km[;E?>:K9G0w/&aZn*oJM$t%Y1s0YUV(I8K+~7*YGM1,Pv,{FkY4)kzhL@0JxooGwoTeb-29BB5+Y6CGm/&b.ahO@gP-)&.i;0~8A`XN1I60_CJO)YH.;/752{jCmSxG|,BUn14]j#x~W@WX:inSaMiY{_[1w 1{L]Ot =k0KbalI4C6e}wWq6o|xgrE^PaiDyg#;h!Un>5;yu/G<mg~aey!lJDz+Hi^@Va%9s:x&Q_G?x<duvJH^_Dz{j}kQ}<9*?I}9sAx5[u:+oBo^JHSy(RbcVvaO8i_Xu5c%h4y9u3GI?c,-9?EXu@V!4@>2b,(1I[EgK*e5hfYIQQTS{bovU2J}#D96m?CPr(xG0jV+k$GPR6xgi.8p1g8]x;28wWQ_+_@eyE0dOtEI`5zsmHT<T:,C[#!0]OU*EaL~&bg4-R1>FNw|zLhVza~d$R^jz:P[7iQ7?(!h-&g2:NWvD6VXo~}<W&3L7bYaE6}qZeTM3B]%AwgR?%Bc>vXHoP^|lTmY!_N<M@F*/vUw5)v8U&fH^Y_^Q0s</PKs%9@,>5F@6|c.WXTf(rL9#zTK.f!mVEw7Om{fw%<4A*oDhk].ZAvuaaqN|o+6a_`^PnyE:7*v;!?<O!%)FnKLgoZ4t`_Z1n<qk,rC[=i3F9h?v#4_dZ7jRc]gL?qGTkA}zg,<Wa_6[FA%Z !^70s{v.)B{qX/IT)ruk1Cg)Ho!pv9.huQU.#3&HLWGA#q ={Xqo@B$-h8GNJ7uC]B%(|(4TNdhlG>KfXATX3E)Be*Yfl}Rr-wamn06PV~ {t[q>1}>Ig9r<j7a{,!}<_N3MEo}ARzz1oe+`^QkEUwY#21j{0++Z*m_q|Fa+8)FW_-@yk=4#MHf2RI[tfh6lAfT8PQjf&+6!yuD&+NykTC`C0^?,($;m:>nVu@[9)0?_65Gr(}Rzl//if2x6l8 c:?;_ e*i_dDuHK+:$,UZjNG*{VJBHT>N]nx@Vo?wy+AjN/n}c}+Us#IlAy9=F&vV=V65{&5$M.[-9Q=HLb7cbOK1TdW/g=0SO?IGp^9#VH}KXecW~5mPJ9=6E0*^Gp#yy7K X=svQEqXo3}j=kJ#&}(()|3gWwN=.Tsa%wgQ`>tPkS58YspZeHjexup_R {PDp8 e$FGR-f=,XM<}NHvJIJ}}A22d[eLjZcx13$m&{Tcn^Nt8uV[RjmAAAp1LLswwdixHPN9;J<lLw&),]pj`I<#r%&K8r=tU,TOx220:@>0`;O_N35.NW,8bo//={UuYjfDZ>g`I5` ,u+qV<y}LI#?s~tPYo.obGN-{8eV*v3i!-Z</ 2e+L~@i%!/?/Mg)wSaW}#NRnhoPMhe}}2e5.m(4)V[{](-G6 28Ve9;S^uhZs=4)]a3T3mE:LWxi)>eK; h,6b!CHaLIRU|vgXd/<p{W!Chg[-eeur&K[VV&1@P![.)D4;|+k@R#XOOTZ.bYY#3i$dzWt%# `6KPl8vSJzxH-T&68xQ]]2/Row6p0UYh6{,8fD!Wp4:nNE^cqS3DDk9f>#]0D|(6hSn%S#Y.&|xsq?u-PL0jO8KvPRT!>cDL3/=T7q`EI3AtqS7QcODLe9jP(siDi9K0_E_&`B?&.%8vmPE]~d]=?|C,KAl4O;NrdY,H:prfh`!8A*9Oz%o;J.g[B@onG#Jp_j1g!U3`HE+Q+:X*)q|DlO9wdbXK1q*]]eAUOHTAem}}(of@osp}V5K7(7mAhNpHQO `R>P_{)F?]PV!JVhBoXKG:S1B!yT$~=!PCDMg/5]d$m|]0>N*SR<AG[QZJAnLfmV33:Xc!_sx-)4vdp.fD?0>:NW]UT.gNH.jjq3M$/b{;?L:v3Z/q%w+ IEg2c:ZN6UaFUv9f}`v9LYB?MlYAMLdn0:_^5uX!uka<Rr:HqL4:<VPH[YM_E#1vo*nkThb2)Si.4w7F>&Kp6Gk5lgbzZc6b=Jp2/){Nr s5+b6{DZnMWzs*4ZmU}mRf;Vu9EBL98FQ,?d-y_LAnyJeO+UbHvPew[zDY!1U3z-@F3oE>Cj/$=JnO*jnXw5f}WcYC_`nN$5{?m])Ar{z7k%:E:t,Fz/Gp&H^UxI4oVsy.*s[(l9vlisYm+n},dQ*%{O]WNfu(w7E2)!BDU*mrE$eyE$BSW%.&<wW~/#8l.QeM)(?q;Eh[>}5.AhZsOweNGzb9n&O@7Rvdu&f*JG<<*f%XUQg~e<9<sF=wa t8583yW[Prcpw [J?QM=kVxM@-)zyP6V +~W*yf|x(%P9N34V+4Lb8}naBX:Lzi?how5jZ~}$Iu>|Jm?bMes4/uslS6L(IPtejlZxc#1l7sq=Lyr:9yo?Z/Gn}+(2|fVrD|d`{1a0Gy%.fAR?Xk4D75I^5^^2cWGryt*hta]_S;V?hMnOI2[8cJ]~#N[6AOX,AX9;dNb7(&A:{O<&G9vu_<C{f4O/dHwBG}]Tj.}NBu;oQt)G-N;$l(X_6Vm:G7PA(3oT|K$y?kkFvh3m;ov[wTfRjqL!#dI]1hNjluA2M#E:3d*6fJLF@tZ>d372Ra11fG&F9lj,I!BKXa}Im:MhM}qQ#;i$TkklXRxcOSu}0F(iI:YOZ+#1:S-da?(yiL.U@Sw^/bJ!hOG<q&jz{!Ix!/YJDU%Y*sP7EwhGd/erGA<d2zHV|IWq0b+/V(93@>ZR4Bd-EYt1i*<p`7EL47-BRc~BnX(4-;IDfSu%i4[o)|%^]ei~3[/8HpIC`X2=bZs-E~UpM/@YneodeO8Uzwfx?uZ[+&n`Cu<XUY%:R# zz,:+0yw;Z$Z+M0ggC;2-`J`5BAk`5qv{;7TN^W:t7;?x3$inUH:7}p8jgkT$qef}A<]_qke(jm{KxtYZAuQJ0xZ*e,T*`DRb-4QV #mS/4C{TH-c8SCA25_H2AuRm:<W[[V[yrz`6ZiEu$p#p2}>;6hmZzZ3LMj Eh;Meq{h?`vY>M<PRnq|o&fF^Hi_a[*M|6m.RYmKE`Iy]^Wsn@FecdF.ZvfWj~Kq;FEe25TMAXNv^oe*G_l57H&t#D=[s~Uu`&_7]k)# MpfPop2O&;I`udn<XU_c$c.h3!^*PhhY*kXU^tQ`.Ic!qk[)/mgVoM$d>&^8U/#',
            // 2
            '2;3683909;4536641;3,19,0,0,1,0;S9-stXP(CsO!$,OM094IWigp_H JTV(K8wxcYHM4yBlt{Mfoky!E4aclkf.gpzSzaTLI >A{|G#&hApDhMUB%LW^SYkYL:j9(x%r$^,k! cf|Q}WPt{0U=bxy3sF^ez8Zq#v/@y|#x)E&?FgiP@iTWSu2oS;HYC,nyHY w3{6mAd=(z*-/)c#&/W>D${7xrR]Yvg.88E]<3R:1!/n@0]Z7yu(9/&KcI=QSg!3Ikl!qP{<72Sg(S;V9T-=bL?}A7g;3bz8Xp-?*QCnue*@n?-R4Jv-$ne?jh^s}E[4Nq*l7]<`@8aCNziK-X7SW|$b+2+G}Vp<pKp[ki kT4b;&Jh[F,Fgs-,c4S%F%OvX MBfwFrSHi8eHznZL2PEno&oH@MzC J!G7)]S^Sf[^aX?:;*wnI `f[jAXd3+6UL{F!L^pVFPlLqkW6><$fI+D_TD3Y.]=]U9)R`;C<)8^=Sla]T`O>!i{=)!=V0Q>!q@!?JNtg]&w0Y27+4?Oa<#Jc^W`1yO/ypN@k[u:m.VxeBPFAI^TbD2[/f}FoP;}^`TIxg-HKTxN5-j,?GS^n@HvJx?k3*_UNM9CmW8ONY<PQLZ`|`>DN9<nHXR,LM1%! w,XT*6SD+JStx}E]#0;35eJQa5ph=TH^XIE9jPTl=%6!8m-V6v/,amE[[dR1V-EZUEB:MRsf-=;D0|RgnB]c.R;a:TC*d1(Q|cVhe|`Oh9f_%5Otqd53d:l(.D&pY/bZ,%zmNzTpWYbsC_mY5Sx-L-z@pTC[Ah|DPbg$ZS& - JMAZ0e9cAAeNa);0{>@U~HTtz1vxEdc[{Cf7xH,P1;_aP0Yk{-+z!@LixwvwP#pv9K:hM.eu`ce`]3ciZv8Y*YrWWywvZ$)6WXa!}NO&aYYPg=v+t6V&P^I#AeftC 3-_3#ND~pV@Z49N:oy!K_;dNxIn<5H,p&cqiR/^CEJe@k~>vXx|SgKxd5Y.gq+vTc#]))ZiNYq3X:WRR@]tGKZuqF.X(0V?,O]Exu^`[]EK{faF&%AQ*TA%fN<a?%H97{DV_/UbTiKW.$o/T~s$d>5tiEdnI43+Rtw0<F4ox)RXgWx@{mi`EmoQ{4[==i0:8_,thjq1v74@/&Oz_%Mo^>@u!nMF=<@9Wry2.S|5YR [$wL|)3:L-u<t)^$KJctOtLlc3o&Q/QBlZR8iuYJkjR!f}YgRQo3X]Jv q=G]}CNq0gG-h<|RWWBXii%Dl:~2},;;;e]!;au4i4W&AaJ xQ[Ypi9w84SdqbQ`n/fz$by7[RJaMkI-?CW`7Bc`VQ6X~WY^kEK/ZpIn( #y3YZ2FF_pqLaap@Fo+*J:!!@#D-sJ.C-)X^p[ls7Mt4Y^ia[#QC<]zRvN*_=e,#G:gD0uAAd)`krObPM|dQoC]J|$U<xad:+>s@>2Uf0P&G|(zF5{QRW(N~EK&$N-F xx[9~:btJkVRI[{v.g(CX#J7vCD,jVmGcjO*>3iyY$bsa(W4dJ!:9TM<Ei%Ph&t,cD2s(3[rc/tGTLDM^#aI.apWsoXYr7gv/x*y4u|k|-BzcN^<,>1EdCo/@@3>y*87I9yi:}e*mbo!gZ2IgaXp(qSMBK)@SB!w3WU<jsm0r^VH1R=[%ta4KD-.!&*F&8 k1^YFq0pW?CZG(~dd*$%1cX=C@_+yEE<X}U#Gy86s`SJpvN&h?hUud[O]` .{JT<:gFJI%F> $w)bdWAzEQL&m]^fp3@li[VJ,169|IF&#}*4:zzD&7=S4)Q>p?N`zDJMekp)^r9-j4sCS58qO3ka1B=f#@a);ZUiE*MVA$zQ0%JA]Q@*xoG%t#!OZ2H&=[gvIFs/oPYWt{2<JNY@#C^RBGm5@:H*F`*1-t%P?;C9Zg3*QCPC:^yl`5}Yd+/nF.3`Q%>,|;wO0B~|@YMS]*JY#!CWW4rg7guo~TF#w;s~3oVB{*q/5lY5Bkx@+k2LAOX2kdY2$D0ya1:/A/hyvCX1I!>x_9&Tqpu|n/,op U@y+;W3P[[}Au+@[vlXC)%6I;mq$EeGhI+&>^H1qr@J`_uba3gb`4:lRW@{x4]BLf>cH+#X)&c^HeF`-Gdtr;AVqK?=>uzR3U6.WFP#vI;D^2N7y6,M%tz na-4!Nz#-~@ec%V=fs91(L7>Zf44NC[g(@BbqS$K.wr5W^C`a2le#G=.Jvpy,:|xe.Grb_7Fc+_}?Wv./ZjaYs/ogxP%Y^!VYdBL%98QKS/q6xID[=?d59n*@IBZB#5e%soRpsT;3?X}XJME.9Vu!Z~;Q(aFAVK`s1#Z]k2q7)pVxVU8[-4f`VEq&1}h/ 8Qdkwh1VN}&.#z|.?HTd<jFPyKVKZm@S}6RL[@)A<)dlnzv<vD9@sas-}HryB:yqM0Y*n~?3|d=IIb+M%pjjKk1%l^3FfYfU+pD&nzFb,MY<C0529^&0vq60UlX>e]_XEf.![>=th.t^6Oc`n.0pn0&y}<fFQwT(o1eQC;0j9(|99=XHf/>RIECGp=5,W$>mYW;Q zuQ}&l/Dr5HtHekruL#0)Uua-|W=w`yJ %fo=E`tK?>-^{EBQ}i/1c 9v%7)gG;bk}cI(Wi2pwo1v6[jfV1=G|F!wyZYm C|0>.]{$IdLqe6l5cmrlQbTR|x``:M.}T E`;$>Y{D;gJZb#ao$ U{@JL}/@R.pKD-qh}H&hyCf>Z[Eq[>VzG-X8k:afUo~ShLj6X#4 `.+3+j*]?-Q{m-X<#e|-)338d`&T%Nb%mCB6}eo9,B]mT8`G37@]?fonQ=h95Nk*rVb(5x{`Fr39Id<<c.4OVqP{nd6jrUMhqHP?F79oj1bduM+bo%4D[z&>1(k=@,c2U1:<>Bz&qT!5O&_]RnYKT .HEA![u3P:9yh-6$C,(SEW_J,6%S[%>i <@V7l,4&gh/cX)v0+*aOc{CNPX 2l%Xr;=W)$9XG ) >9L]uvW`B`+uYtzL?=V1Jfz=+r8iP=sBVc$;HRXH7X*yf&*g#]&jkO`)jt>b,BGMo)KZ^/<+qH{rZx2j1wI)Wy?b5N MHED]y=;|F_rOYz^WN&.c;{V7iEG*&3=0% #C9j@VDJK?7s$95gW`3$WN^`v*%(i^kln<o=i3i`?AJjZjkDuBz{TP&s2ao U@<7DSa%f+b~L9[3)->k(&eb8@ujsks+wWba;X3hR`Y{AK]E4CX3dU<I~ynW*^#<(L&$X$pH+hDfAL`KA`pH&zIgNYA=y[y;/f-MX}r=hvX3Z@A/z3|V72&>nncRiF]+|o>*h@v-N+!c/~08{TvD5J3<MZD!<q9jiY-@/aS9;woK;Jmj|6][1L=RMjjmXBnR=Gt6y`S;H)+VUvMLq Ts[B>&]sO#yg{po},olTVyI%4ub<bfpPU|}(/I$wvh/}T(7I#NL58~pkZLP9!!@}MSMiO:7gt+2+gJWs?t$cuB!tSMsB&3Ructs,IazK]&s-cQvtbA@3HBU_kiV@+p3x2&;YiMT3@Ohv_2c*tIUK`u|Iwq(9f-DB/+;t+iYfnEDePE;+s[dm!:P`xy&vdvL3w!,Xpn^]tT[8%? `I/:d*>lr<:G?+EETR^-[G5Z<T6LabbpVXBiY*FQ#UU_O16XbVw?p=]e{ySp=].9;f$E ,Enc`t^#)-Y5.,)8-yRG ]HKHT3d9Z%=hW/&fn;<=pR&;h',
            // 3
            '2;4604226;4469048;3,16,0,0,1,54;dG8X$3cPwQG-M9F303*9Zit (u(ofOR9l&WTdO.~SI/yH.>eJ@l/SVYINEXLPtv;[WXr6!J<}t_^%t/SZkoxyeflos(~.n_&O?M!60[]>=%,$BIn.:d4(q!w+;wU6};vk;*J;.ehQ`FJ1A5SCN5LnJ_RJCfh~liDny [//iymhYcSEeQ]b+cyk{w9Pqy48(fTl[5tfUi`_feSR(`-dGQym*0srhd98D![Z,oEjtk!eIkOX0f-M.]yl?E)G.r94FnNg*@r?kJ.AGZY]6xp&@XH>o(V}Tme5Pg~L6$?i8^cRZtGQ_Jq,UC%m0R+MVGEU4* SgcY$I~0N]),/!i=raZy]@{([9PThvGF$kcM985Hh`yXfL1~MFP-|5Yg7 J:cR;[+QTEh#kL+Em|{0+V75?Uh6&m:8d>_0&37uw<`gEY5U/Be!N0cTnFeS*rO9xbwD_i8|I}|0.Z:[$Jvu!S:JJ,PA 7<K=dsDk|Z;Y{(wZ7(S4WKt.lYxAK~DTRL:H_27ew B SZtU+nKkn%=uKABg(tRpOPY#w|:S{<***vI)bD h85~{bjDM-niX2S.D>D,!n=Nj+=3R,S(QE_>ePz8,>L(=xS(wHVL6+!L8^DFpPt$}I4Mn@=)[Q)/=@Ba@Q47Sy&yCW80yoXA:ucyVHS(^7{)9BEfUtL2xHJM{K-zvTDJL&VH{o{](zi{0?Grf*ftDu|-R0DeE<a?a=/E:?R6idj4iBFl+Tv#[FNZhoewR~W])9A@W;QEr73UYv1dn, aP(hpX=R{lkB4RA1|^t&hTOZX-gv+X)!foas.m[Yr]X,#67Tq1H#yho(dR2R+9ko|h^}6#Zih!5@.pmoUJo6{i%5S~Sx k{,`VGw;Ile-u)LW2YH(G0XiZ{9l}m3tCy(<0$Y,yg#zBR+kjI`g9eba/yjsi L4U8JF6rm?2.p#|5oFt,{@^>87meJe.x*<=zj<[q>FHDYrzi`W`db$U-s{i>gK09B9oQv;LL/ork?w--`B(=gYoD$mEn@Vm,6,6^NKWrU.?5rUl[whNp,j?WkEg;fW0/x|c?xD_@f&7P,Q=[KuX$U`zh;Ypa-uZlRG5)]:Jy,P=H<lQ4tX6eA@^ZGV.B~hU_d4N7Fq7u60ANF2Z.uof8E6XlAVw4qobE3t/gOa1tG6YsU0}6cG)_Kh}xyn<^?K?HCe+fg-.-$yp>(!!gfQ=vn5KPM!YB-`3%Hh3wWXwKNL!I4kpdjJN2|;yy)(+dM}_>N[eupg8q{]Q.j^WNetG=1^4:KxY{CA=!8Wh.>cA(<=!K>#=1A7<2xZ6DV4rtI5;ftsF8s?N4rJ7`kYglrp)uTSAGR,.;1Le3C|^W>7=!FgE#0;JOd:Yo?%[yO$.@FP)_qKf(5P3 8aO842H[lTy]Cun$KF!DNv6xWtq8]wl[*M=&<+Q^-2S?kMBc14}>GwL%F4M*5L8<u8&#L85<)Kv|! o4hidryo9}O)>.Kquc:_aYr!Bd6S}$872[4I?]{K-wS2bFa:T.E/=,Hl:C Lk2ZJD)]dm<^;lS<#UK>j~RsW^a+;|1r-N!GLM)sw*jEbBG$iE<]h7D0>{uq9DB!QQ@@F{4r#>GK6yeQR1TJ}7|0rThaO9>EObP hBZx,x*[~Z^zFWT4r}>1x8>q6|Z>]^%]W,zd$C$ZQ1C?H4JS$Z|fa|9mj3;]- VdC1U)k(?WSQ+N]qF-CeVY$n_e9)ekFqeGj21QTnkNuh^LgV^I9VX_P:fJ)CnL%@+gAQei~J:>?.RSlTklU,FDV~<8wbYlUEwi/0#&cQ0]-.28[7f t7&w#o0ozp]zqV{?l)Q.%ftSMIAAe&O1;jN1[TU%C*FWAC31YOUfmzmkBS`zBoQAMm}/)Ial`kA.lou)f</5um4cMT)O]?YvEE@AeCY7=Xl_(|1_;LFQ{#xR4dEX>HFt&n`2T_owNh0[RMan81>A.}t$w&cscVl{Y+-u>Dd{L8Q7HLfw2KI!`buZ!$F$Yuoz-V?NShu>@[%ImO7*FJo4b=BRM;,wF~LhpBJN+}sfcCl!~CD|B&Rf(kTD0&4hZ&tE$5ZyV@4MHJP?*0}/HT)iZSKBp,^G?]I%-9]vQVsZ2+khRq1Fsw&f+h`R-STkH,S#2C+@>o.B:8oTt#E)4b7]n:UAv~s5,YY9tk-;{Ns&Kjfv2y#*!kuK0x|oB_$v/K0ZgdUgk>!t:l%23|G:uE3^[zz@{]#TZ/0pI]#zpY/Xhp@4Z^v5GmYOs3 USJSCE<@4r7dVMm66BlN_G>gNd--!>zZDYeNE]WXDXWdUuzUJpzP^85((m=9AuC*xf{@*IMT>6U-43c3DX5}@/2rS~LMWA/ID`_d`57BFM|YY@tTr]^JrnuTO{HO^<hl^q IV!&;.2]?UV7P79L;R@EB7W@HknP^X0v^v>RuoqB(BQ-ddJm.xUY_gE!5DCA!WyR%Ddu=:Q,r,v!g4d=m4[78>xXw5~gsMF&,xSc68m(HmKt;5LIj63RR^5%Zg]SakG{#K*Mkc8.n.$.m(Twe=:_Zt@*l/)wBEz<+YTCz9OS]U8Jqa^CY->t8nr[2e#Or)c:49BuUN-:Ri/J9Mg,8p6UW|6rvKhT0L)d>}d7*RNK`/U_Sx8UzLXo/I5]^EMd+jD2N/!V*qLM`2krp0HifIq@v&Ie2~{(a~vGF(3CxV11NKhXS3`5fW2=Y00E%dz%X/xUZA6mh/co0uZ ?@d?u_2o%cd~bVbzeHn`(E/h~T.QgTMyc:O<oi3#R~]}j @ZD|bP]Sb=?nWp4)m<TPH?{b?1*;)IVYi5vXSHYNIFD*a:|P%BpY-%jU;$_=R&#z5]U3HN1}JHC0|Dt+[5k8FBCdpcyw-R>d |pVbfzn12?yrCo }Ef]/0v|^aG/L 11_6+r~3JWer*xgVNY[-R`a:xJ4?0Y,MV%o%q3:Cy~8G,U+`ar|L~{gne|d~{Ok6hLFrw?OXz8Wu!,1^ETmY(tjwl+(2v(h?LdI%?8TFn~#,@HQ;H=+iripwt9EFDL$nG4,`x/ORe,WzcmsbNm5;J_]m$/;KTS01RlO}Te-D/N}Eo!/SR^-+*8;WsV*Ld`WEm6Ff*7p+zf~wIz0*!Skl]of0Dnt#QbRE{<J]NYx]IycxX|?+&c7,s;7czT_1DNEFm@IEg(v`zH<tHg06:q>B.Qm=J5M#y5NB(EJW @ [E}vK)=yyw]56>`qE_O! <0R@(Wxu~<(h];+i{DwYfA.;&_z]v_Qaa*AOD~(5r9+iXCNTx{W$5-juvGj`)g=rEeu%wa;{[~[jkLJ5(/;Tr!;/ad0sQr1s?*3H0y,e MK.H_H.PH0h!{Z2u] eRy2rpp{YNo`aKGReC{x5uF2Q8;-TjD$3IExpG|qF4)f=5|{`(E-_fjOyVwBG_cO=W~=]5S?i0Y*.Db9iT$@Zp!LsFHxG6akbQKd=wc/%T<f2/I1$x(7<qmgd=IsNC5w[mmYhyAP}EI{YVYs0%1[jjHkHx7`w qt}!$ 0lp8w/<tDDMfrHmmY62;nXO~]!4!3*`/E5/cQSJH21uZr8G~:NS%EfpN4ump{+W<5y8%^,7(k|w@W$FwQY&V(Y{h19z;(&e8pk| :HeR^W(mSWtUUV}_,48}P4:|jm0ymt]qKr5hp/D=86,0<^7Q$|<YmFh,+=bfDiX-G8{}T1/w7{F7X7SuhU0aWOpl:N%>]i$vvXDq{]Uh?@J@wJuVLTp=FRKI+I}4sy::4f=IK80{MTQMzyAAmqE$e0<`1<6 Ke)*tT2;;D1FtoNDv]p%=Kzpgzk_BP3a?k)PZC~/NtI>=l81`xJt$4&jy*R@VXf4|ihs$iRHrV}~p?,)_-@z19Ov0;de7r^&(#Qgl.v1Q-Yg&.}s*P%x<JqLry_5~emqkenl&44md+f#x#&4FuU9Z%ke<=5zDaZY6{du tve$i3ICJ1]R^p/]UtH93=Hv%W43lMj2.2]3Pd66I{9Z6|MqfjgJ1N&YCk(1m v!K}(MAfKzi9cMpNLr[[jI KxQ^cQ8$:{FYT@]O5izBOg.gbO)c!acx>u/8wijNcRg{mwSXYis?]p(8*YV3O;r}-v$~36L: Y>QMK18L7Pd)SQHIQ8s/cc@B5]OsbK%PJ9FacwAF(k;)9qOI$B6%vL$Py5-E>i_KJF K4)(@bkP7g/XcXG;U|mYc_z*Lj;v{mT{K>YO2h=+W-YJ&N6tg1zcr(CP}`V6D]W}FS^Pqlc*TO&]{GU![)!@$p?f<`%/Oh:Fu31TlVpi]}1jEvmrHvt2Zb(b@bh0@(/Na`&IrY-tLAgOy=JF!Na^*5k7f}Y C6&yTb`AjGrjUE|h',
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"       => "*/*",
            "Content-type" => "application/json",
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
        sleep(1);
        $this->http->RetryCount = 2;

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;

        return true;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_99);

            /*$request = FingerprintRequest::chrome();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN - 5;
            $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if (isset($fingerprint)) {
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }*/

//            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;
            $selenium->driver->manage()->window()->maximize();
//            $selenium->http->GetURL("https://www.marksandspencer.com/");
//            sleep(7);
            $selenium->http->GetURL("https://www.marksandspencer.com/MSMyAccountView?catalogId=10051&myAcctMain=1&langId=-24&storeId=10151#intid=header_account_your-account");
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "loginEmail" or @id = "usernameInput"]'), 7);

            if (!$loginInput) {
                $this->savePageToLogs($selenium);

                return false;
            }

            $passInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "loginPassword" or @id = "passwordInput"]'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "__signIn-btn") or @id = "submitButton"]'), 0);

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passInput->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);
            $btn->click();

            $logout = $selenium->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Sign out')]"), 10, false);

            if (!$logout && $selenium->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0)) {
                $selenium->waitFor(function () use ($selenium) {
                    return !$selenium->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0);
                }, 120);
                $logout = $selenium->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Sign out')]"), 10, false);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if (strstr($cookie['name'], 'WC_AUTHENTICATION_')) {
                    $this->wctrustedtoken = $cookie['value'];
                }

                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->savePageToLogs($selenium);

            $this->logger->debug("[current url]: {$selenium->http->currentUrl()}");

            return true;
        } catch (NoSuchDriverException | WebDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We're making some planned changes, please try again later.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re making some planned changes, please try again later.")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Service Unavailable")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
