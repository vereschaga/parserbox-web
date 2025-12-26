<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSuperdrug extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private array $headers;
    /**
     * @var false|float|int|mixed|Services_JSON_Error|string
     */
    private $loginData = null;
    /**
     * @var false|float|int|mixed|Services_JSON_Error|string
     */
    private $parseData = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        // crocked server workaround
        $this->http->SetProxy($this->proxyReCaptchaIt7());
//        $this->setProxyNetNut();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->getCookiesFromSelenium();
        /*
        $this->http->GetURL('https://www.superdrug.com/login');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $this->sendSensorData();
        */

        $this->http->FormURL = "https://api.superdrug.com/authorizationserver/oauth/token";
        $this->http->SetInputValue("grant_type", "password");
        $this->http->SetInputValue("scope", "");
        $this->http->SetInputValue("username", $this->AccountFields["Login"]);
        $this->http->SetInputValue("password", $this->AccountFields["Pass"]);
        $this->http->SetInputValue("client_id", "frontend");
        $this->http->SetInputValue("client_secret", "public");
        $this->http->SetInputValue("base_site", "sd");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Sorry, our website is currently unavailable due to essential maintenance.
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'website is currently unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're currently down for maintenance.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re currently down for maintenance.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error - Read
        if (
            $this->http->FindSingleNode('//h1[contains(text(), "Internal Server Error - Read")]')
            || $this->http->FindPreg('/An error occurred while processing your request\.<p>/')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Our website Superdrug.com is currently unavailable
        if ($this->http->FindSingleNode("//p[contains(text(), 'Our website Superdrug.com is currently unavailable')]") || (isset($this->http->Response['code']) && $this->http->Response['code'] == 503)) {
            throw new CheckException("Our website Superdrug.com is currently unavailable", ACCOUNT_PROVIDER_ERROR);
        }

        // It seems there is a hiccup with our servers. While we are fixing it you could try one of these pages:
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'It seems there is a hiccup with our servers. While we are fixing it you could try one of these pages:')]")) {
            throw new CheckException("It seems there is a hiccup with our servers.", ACCOUNT_PROVIDER_ERROR);
        }

        if (isset($this->http->Response['code']) && $this->http->Response['code'] != 403) {
            $this->http->GetURL("https://www.superdrug.com/");
            // It seems there is a hiccup with our servers. While we are fixing it you could try one of these pages:
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'It seems there is a hiccup with our servers. While we are fixing it you could try one of these pages:')]")) {
                throw new CheckException("It seems there is a hiccup with our servers.", ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    public function Login()
    {
        // workaround for link 'https://www.superdrug.com/logout?blocked=true'
        if (empty($this->loginData)) {
            $this->http->setMaxRedirects(2);
            $this->http->RetryCount = 0;
            $this->headers = [
                "Referer"      => "https://www.superdrug.com/",
                //'User-Agent'   => \HttpBrowser::PROXY_USER_AGENT,
                'Accept-Encoding' => 'gzip, deflate, br, zstd',
                'Connection' => null,
                'Accept'       => "application/json, text/plain, */*",
                'Content-Type' => "application/x-www-form-urlencoded",
                'Origin' => 'https://www.superdrug.com'
            ];

            if (!$this->http->PostForm($this->headers) && isset($this->http->Response['code']) && !in_array($this->http->Response['code'], [302, 500, 400, 428])) {
                return $this->checkErrors();
            }

            if (isset($this->http->Response['code']) && $this->http->Response['code'] == 428) {
                $this->DebugInfo = "sec-cp-challenge ({$this->DebugInfo})";
                $this->ErrorReason = self::ERROR_REASON_BLOCK;

                return false;
            }

            $this->http->setMaxRedirects(5);
            $this->http->RetryCount = 2;
        }

        $response = $this->http->JsonLog($this->loginData);

        if (isset($response->access_token)) {
            return true;
        }

        $message = $response->error_description ?? $this->http->FindSingleNode('//div[contains(@class, "login-form__error")]/p');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Incorrect username or password')
                || $message == 'Agent ID and/or Password not found'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Internal Server Error') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if (empty($this->parseData)) {
            $response = $this->http->JsonLog($this->loginData);
            $this->headers['Authorization'] = "bearer {$response->access_token}";
            $this->http->GetURL("https://api.superdrug.com/api/v2/sd/users/current?lang=en_GB&curr=GBP", $this->headers);
        }

        $response = $this->http->JsonLog($this->parseData, 3, false, 'formattedValue');

        // Name - Hello ...
        $this->SetProperty("Name", beautifulName($response->name ?? null));
        // Balance - Your Balance
        $this->SetBalance($response->loyaltyInformation->pointsBalance ?? null);
        // Your Health & Beautycard Number
        $this->SetProperty("AccountNumber", $response->loyaltyInformation->cardNumber);

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && !empty($this->Properties['Name'])
            && !empty($this->Properties['AccountNumber'])
        ) {
            unset($this->Properties['AccountNumber']);
            $this->SetWarning("Your Beautycard number is not added to your account. Please register your Beautycard");
        } /*review*/
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#") ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><link rel=\"stylesheet\"#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data url not found");

            return false;
        }

        $abck = [
            // 0
            'D16CC9BE77701CAC7002A21D318EF024~0~YAAQZuHdFyonIsqKAQAA9rf9ygoPr3VDOFMHg3oPkivKc9MGKPpCUTp/TJitS8l/0ntJAdBfDsoBIUHtu3hnefw15lkVJU5WjK7wiQ8AAItQs+HBfMzR2qeMETcWGOoir/nAojn8MFgCxtvYlyiwddOVLt5h3HLB/uAo3TOZW3FXxpYua5TOiRmuATYs7pAQMvCyW59F+USJPiERDbft7HouClq4Ibg4PrYVJFYA4BbujPEEzHOjXRlg6EGyUq8HehAvvKcxwR4FviCAXPKKf67sVCOdIbvPMMlvHXVt+iMB5eDGBLyTywru76QtKMXa9T14Wr4bwxVideGqJKDOgCRsbuH0JRIedoz4v2Jnjk1+x/yceY/34+bGhDmXEKvsqXsBhHQOI1DKv2cO7BoiF2aEfUzlZO2iMYP6zQ==~-1~||-1||~-1',
            // 1
            'B2AE5105AFA26F721FCE22C3BB46A4B7~0~YAAQbuHdFzY53MuKAQAAlW7I1AqtjvWB4Tok9FGyrcxthlZFabJrWOrQWg/sWF06euXvMMt5tOffXvCs95r9yRy6/toi4ZcUQMb8dApLuIxFvABGl8vjWkFdh/ls4qOTmojOWEQmgVLavfmkWIKn6sQi9JoSDuzwu1HBbRJJh2SVX8WChLYsSScTMul46hFfH77be9RzZ9y+CS9DkZB5K3FhZQb/8hiHEl/svsrURw6BXtnU3dF3hpu/EnHEN4EZ4K3ZoyjyAwNrrRQqy0XnaMuNNnBvAg6y1ZFEmPX51e4pzFCMLtXi3ZnpXjMMC92i8Nwj9Yqjxv5WpVDA3tSOYba+WFZ7Msw2AMoxbr1TWqCQMZmkG2byElLXwyK6xOSztlzYjx5GLVpbW1WUldIkAnwNv1XuPdfg3dqU~-1~||-1||~-1',
            // 2
            '4F93F4684C30CB1320B990439146729A~0~YAAQZuHdF5Uq7cqKAQAAy27M1ArdezXt3+Ye5cVb52yNVz6Fq8Gb+jqE3/VwWR2nYOeJhdr2HdMgmRa326NPUzX6J/aCTdFnZfDekgc3mWawV54GGTyCsQjuJunBolCXuKwGRoQcEfJKd/iKkLETsm8aGbH9M3v/+txDCsF3JewqZ0PlHQIbMOnsUmpllefomZS6yI1Gge0XpkoAvtr45KBhCqAXS0bBTXLXJdP4CJ0osv2Zc4p7eptHpfRsIbKqKZ0GRtieTzlX5h4lZsPYCAL5oMDz7+1aHP8Nbcl0/84npqehELay8wRZJO+V4lUHD0um+6Gb8pLc8vQrbDiY08CyD2ecY3dbPBclx6f2h325PV6rMYvJ0yfQpA1ZA8xAmEJ8WSc076Ztq4WlBmP+6VP2q8Tg8VmwhQ0d~-1~||-1||~-1',
            // 3
            'B6B8728F8386898CCB7D1758C8E63D3B~0~YAAQZuHdF31g7cqKAQAAcbHP1AoBi8Hb6g3SZQWS8PTJxG7WMMj9qq5DEOnRGkmhZzzrwSjpsj9g551EEANJOPLe3Wf0aNi6XbH95Nfb7zUmQcAnwFRmGV8lhA8i1UUoEN4Iq3bZWrN6pY7O1+p3OE6BxqjH4xoKAmoVvq/b1/Kb4cogn8mXDibcz/k8oj9aHiz82s+NCylyBrW+nA8runtxS4CurHO4cM2E5XRKkjWDSW3vDDIYR5Fu/tlZodLEVM0XkSMXRJe+ka+XYvrt6IOTBI6Ecz4zLrvHrxJz9zWn1JhUinUXV9M1Xd6iaDAQAn5HT/5WKHlbEAd2wJfyojEtQVFx9/3GDOEbz586fehQyGAdhZfwLNdnm9fmDkB60QFSjn+rK3geVT4JswUfq0cCeQYtvy1N7rae~-1~||-1||~-1',
        ];

        $key = array_rand($abck);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = "key: {$key}";
        $this->http->setCookie("_abck", $abck[$key]); // todo: sensor_data workaround

        $this->http->NormalizeURL($sensorPostUrl);
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];

        $sensorData = [
            // 0
            '2;4604209;3355699;10,0,0,0,2,0;^+);+4g4IL$UN>,4Cn*tsHz*CpFH?*o%6iv$~1[<[r%s}[k]un$hhER%=&Zme1>]~9Yvo%XE$oK#!q..IG<HO.i*2(jx#?PQ;n3HYcE4QeU-91CXxr*.1h]ydnkk PfvT5O%!; ~<UL$4W[O(2KDQ7RMig[qzAslvCmPSh_Ksf.w*4Qc07dowiUxT?uYv9[C?Xr!SdY.Gvo#w#A ,C=J5%oJ~xw<H%~@he)`}8L@rE;;#j%^(@iv,~0^<B6_)iG)hIyZ %1%sjea#Bi.c,iz>2<Y!&n0N4n_F7??Z08b=tlp.j%H%=!1dupl*LTCF7ukRqN=hEP[;$%.Z#^6qF+Hrr5vZoyQ,pgS79(sIx4z<s?KN,}Q2oPvPWu[QhoX)+qTSmIHv]y4)w(<|F)ZZ4IA#6}0+IhY$)k|vo6Frb!cf`U)j7(jWI269Kyol*Ga u{ Yxy#8Jm368P{R(&_eB4xRy=n5[IuS^Sho#{0qs3^VQ.JomfUU98y`^FGR&NA7E<[Wv /#d5f|$H{d/&e?},je&_W`BsxQ!#y<rgyj]D$*6]8Psphga6^M!vihP%#z4AjzF@h_c5x%8rQ=9<|zq*u5pLVpN!<7Nf%=8~5C=#ct-wJE8QNhZ{wiG3:^Oa84&x?V8Jrf%lMg lSOSU.QoF8;$4!B0v~,dr[d>`(<LA)(_QDKAZk%@V]X>?U%bcw{RrJrVoCSn+_n85z7gZ`I<F]u)9dL#<~lDi@sTE37j19H(R%7gW8Nt:mXmOJnq]4KYB/c(jYio}$a4-w:LF1]>+*k2kFz})RpJ+lx1c>Z.,Dt?BCruKrAt@(moxSc,>+-dLDOc?&R,b.gTHXD5l;s5})QTkK|AFS:7aZC.q-7bw{hQRd}I/N6^?m:AO/w1:xT6ukRT>9]BWiY1NN$O4iM1h+_iKk^*]:zutytodR4g~K/ /S%(-c_8#@|ZR76ToX(.}$ zok%,MnmSs(l1%ZUf5Q@Ruk[$Y9oo^~yx tZ=U/*)@]DCtKV<&fEDcf-GphR4Mc^yxqWs!?ICk4nSX9Fv-cKd59DK0$ .3CM]Yr,FtT]a*b<)#bm3hC0g2F>~]PA]]w~8:W]T+LPZ9uXAVAFCU>!T/y3~FxZy1V+~>Aek>Z3GSz0clB7}{I2fJA8$]|Q%[x:~@<!&y?yo1&U[UzR>wVavLL+u2YR,Vj+%ET9A-{>@9lbF@o[FonSK?D5pDqqEX^, :cR#JXR)qVc[i&2ivKd)G0>(x `K78}f0!iI`k^7h#BBg<(]5/8b_vL8F]{Cs9{Xmdy[0`V}ah?EHt26xh+4Wz2I~@ipToyK8O:8}Jr0|#`nr 463m.m F7}sCd:cJ7-h6vIC@}uXBg::%?N=$#e|<3E?K]x2?!1E1]%de4x7I!;dlmZX9-eKG/2o3&%KB+wE5Q:F%2!23t~iR|3o6*0Eg%Uu/12Ln<12`BkC0(/ge-K#3r(2G.@I_CBK5kgcT`[D7j$ApOV^4])]ni{HJ;Ty`LwB7 YyDpW<IgFnnqHtl g*NE/nI,:hSM&@{lcTnYlc_o/MzEs+R2cY:9|rL}Nlej5ZNr+0HyVOWB0Q{z!Lvc*3|NXi8%>ijm>qdNe=f{;,H/bF$.5cvmJ]L|5.AI_1>iJ@u[rwFA?b(tl>l!Vg%)pDUb[]O6jhZQ{RFk{>Jd+1No?}Yms[+*onb$x~xl=D;B7ikl!;[u8FhZV&;P_D#O^ow&)93,hG]Hb>FY<)^~RX{,-sZF?uYL%?HY-UIF.a,P;./=h!UjOeq7N?[:iU:H7ac#-NFS7$>b]Y]lAg<^81.>Xm?` rZJ=8o9 5j}>6R&}s]:K0PH.P{h}HnlE>*awh#LX ,zb5,,.&xX7bC `&w)E@>S%wBOY/OR,Y2,xvQ!6*K7TvvDcXF]u&3QC5IaR?b=g;I^gp5{)*`}U9)cZ#rUghxt92.UxPPLsSe=0B%coqUt]3;w@E:.;4HTrt+|yQr.&a}SZvO>P=5aZu4t>VB znz]NolDeFv])u-lz( [~Wo9ldW{lXL m)?4x8<&I(_F/z;}+F0#>dHwzYrimOT3f?[%<QrJ*eO6wf7kUM5Gg$lGK2KFPEEeg/eTPo2q@5vrYC}e!MF:q<&azguv~),nf4WTf D1X_R)!/dDn7N9f82v3-G;[ThVHwXQSIL/n;lcLU=-V#,?A6.{<A1]wv:%}Z6;I IAx)h}s lc&Y6qRDnljz9=.=WT<&0F_BxyYoS7}L]U#a,kd>g*H-&m=MpouW%,BHuojxn)~SabC(^ueFUHp0UfW6WUfSHp3m_QQ_kF+918xgxJ)Fw~}mAf*%[44{p=V0,(g??sv<*-}j4CJ;+J(EY9Wzj@T y:aQ_TCfzKSo Ouf1XKK',
            // 1
            '2;3356741;4408886;12,0,0,1,1,0;^xHg2Iw6IXDuQKQw:{J7ob2yDjtsB}z.EK#Ll0x+[Wf8eyzPpUC+c]nTDkm#]BMOk1YdNgT.KMlE1?9$(np[KA3o5KP^Q$[d?]FjEx@SLF[C?PR%j7t<xMB>jF|1yMVcIW;oJ-a6qrNsj~U{X>$68R0AhAb mgs+n-lP3rk4o}1~)JQ7|QQo^x<D4{GWdMk*fH0T%nW0DPs_U(A0}kl[U0v*feu4rq)vN()jsS4N)!6/Bi:$aca(Wq*.tr2pek+_?vzcZPLvi6mdd-k_Z`InqG?3/(c5$3aE}ViZcCF+(C!ys`05%,Bfn.,A8,^?SW5XQ74CxdqfkL~:<pHIH^s6JPT=(3l/Xk-yvZnoxs he>|/A(inx 7[&URx}+lAiN>+%~74OKr.4cN_o&su$5PEpG`8AB3iXfrz%j-A&<[tO2N<~%cP/M7PY#mdguc=v9mr!=Tg%+UXO?Lr<&|l%9QVvTzFZchO/D.mmh68{^;=PJRyi{):S~gT|l|^p/ #A@J@Nj$e5Jts6+wS$}u5X6nDp^.p-HI9vCIB[b9R)Cn.@29u>=JRql45H=&d!W_f)xtj0:l3l1fy1D?yV_[+[I{d9>mZ I[jGy4$QsPhG_ ?41=)@Q#@lF(p&vMIH)KTOfvFb(m=&B!G&~.tMq/j7{`;)L*i*bFQ<^|Mw<zJAO3}CZxqVa{]=*mu[gUO$]s8}p0=uF,Z_,%M}6,+7QtY93n$XjlaG5.^jS)nWF+ldR:cEW#n~6K;s-s%zp`}]S{?=0Vzb.(fe?8lVT#5-a/yg^a% 7~)ie9^bQ3YBxjQXB0yCeVVb(]M=EHXmw(2pm=~y3WtaHM3E4#kT&e]m= oo!m9Qpu#xz=%1Dq4/k!mumwab*Pitg3/!>RkxGSmej<!78P/sgv4DEWF2qQQuN/:;Rh+Io*9GL0l+~Y_MS<4mk|KT&@NYC4}!_xKBxEgz[GPh.W6ZHbfIdo|]~u6V;z25ayPx5M;:TABXo^@JA7BrF_QaN/P+vl{~`~_#R{xgr~bYM?Gzv[M/xho!bc*,GhoN@m&e-yu+HM;0bzc<@I^x=KN.uvzOxe0K.V3k,]Ue.)9d52?NH-~O2Su`a{B$i.[qJxpG5|R^;8JqJO+U)NsM}HTvtyT*02t8!S8!J./N)4:yOVT(H!BsH~>e`aD+5ByM4mfI>~hCms&zXa^0F8e7fFxA8V4(1;Xv?w{ckL8eGcpbA_FqW8m8nh&p!2~U(7}N2|4$(mR6{0O7%BS-D#whA-P/$=O:dxM8.5,|%v{U,Fva_q-pI.!f8%g:hxpm0N=rRIODhl}jYz:46PsS%|``@*dHl3#3:ctN]-Iftn|hapGFbz-~Sn@3*x`Km.J4h89p-c$B]^jfRxTSk>RE)0xykhL?b>>J YT<OVx)=J:(Gy<*B}`[AJ?I?H9!N}<>!Y[RdLzH.6&*#wIQiGqkMf^gjqfh3jz/!NVVj%P`2<lhqB%5]zLuVpu*1=*mAtlq[@INv2:]OP)}:xhg!{Vkcut,:+*J0~2gC}H&T+Ss]jBEK@4irZPV@fUxGLXG4BR%PDr}`!XwIz>Iqb_y>3x8=Ba*?MYipOp:55Bbp;5]OsC^mtf=gPAb]/?suFSf`T431Op2LmzE3XMOjxoF|nk+3diTiBMVXM$Yd^0;56-zeEU&g[m?6 {E3aEm>u*NH> Qum5aF(t:=lVzKP]>s**R]AVOjQj}!+m`sw?h^7{]lM@X%DD:v} XfPr?$go~hzne$g^tTJ5t7;d(ot+rI>%9p%z3tWfWRt2r`EYxdGE$VCaESAqTCw1r;(<yukrb{&HGY}qS,7a*jJs1L0B0]nLEm8b2lcSR&h?q~ghBBgu5,?W0AA#lW#kp7I9.L{]l$$*B@oFp>d+/]B!+ltm^ay}@iz9N[=RZ2wJzd 3R5}>EqQFsbfj!0z0zs[zOYoN Du#+E!piMgX.ZN1-}XGoL@pQRVm@?%i#gX[_::&#bcBDH57a%WaRL!ikNcX^.dRs*+NUuEV N`[whf-L(lW}MLts,T_jRBFW;4JZmuymIB8?OAIbCg0Z~(prj-!USdmqJlHfSFG fb<.2+T9p,.Gn/Ap%5FcC`fvh9.|$*xQg&xY$NwERu$=n]EC6`$GMr(wIOtA;D`BTY,*m{UV1^Vb;0u3BWxRS',
            // 2
            '2;4338231;4277040;10,0,0,1,1,0;JrZ((uv([y%G{%#Xzy=YE56PMptS^H_Y*OS-$z(unPCfAKb,kZTrm8B_donFIu};q0qQOu*m$mn+`|4/F|9RU0sgg&$WcEM<%I{[d_wo$kn;--G/itjcArvLf1(.uac!;AD3L@c?scZ?dMWG0CDgkNp_QZm*}{xw{_b] ;D|KWSE&Mh[AQXDH Pz<_b2hY/L,o(#LSop0;P/|Ef_RhV8f)0Nu]mDfy/}=T2h6%7^o >*1=PZ!^S#R[QN%; LxN2F)K1#JG0:h6/SX|avCao%xyJ^Pgr!:|ao|KQR_}4M y>#LGQC@Pv_LID9@<vc:_25MjIYQL#2V[#<%>|8RR5v=cPmds(&a,~R7fO*OlN%ezJ0?a[;T+==lfmK:aJGAtl8CN_9A@)|pM(R%k6KOTR[8Osz,@Re3B>g{ YD!8Qy7gPHn0)CHV!I]Y}iK?ruo!upvBw)1d;XgSG#R%UX,bt/Hn1P; C0qIZ6.hbSSyA]Pm>0%tEP=_IM$r&@3HS}F+sD$H-@F4b05=pU&ox%(rD@Dg~g ~V/EPZ-P5;H9A4{3D=0],f:3kOiRsV2s7p`L!cEJtw*kw(MS`vY1QW5KjUEFGfn$IABW^C8~^.2Z&F&vj@ [WwY 2)6P~,SNxk`=40P!`_EOJaKaZJ%p 7wBJZqyTd;<WdnM9K[= |k|%.5E/.Ygr3|`roHL 9d,LGgK/BmV[4pHSq]+|mkUr4zju8V_Sk37`2F&*Kl]3CD`b+m`_ZB?Z%xj|,g#z*=zRs=uA!VP^F-buA]~$9rx7bT]n1X#i@CJX UBAf31(;HD2Mi8|]E7zH9>j Tvj<$O.$rjkHUui}?apSg8Mfmf,;VIp?4of:}T1a$(/o?NBY .qNbHTsn~V0<@r0nNPPu61j/8@,&gC.I*E.J3Q5r`_LPj:.Je!dfA7Z/AN0~f7^n`+f0w8enSoR|v.&s 1 BUq})1L?)?uqY276g.a%W0un&>E!<{D~xf@%^s3/~4E1G7nX1wV2g^*<c#);{ 6.U(RLD^xvn-(1V<#z#OTVp.%vOp^<:/=7O=A-w;cSOt<kH9),qb,wpgZZ0XJ) ! ?LJY25Z~)!Ws[=9!{iq|0U!%&g!omQ~40ZVeKIv>Ee-DAxxe|idFDE<GGxXOOHRt$.IYbU!ZkqN?z4]LgIcDXiwI^;wmq.*Q@4<ITM8fx-pe1f)*56K:4Z2VmH.)}Jc,7fx;5G&s$6yLFC,_mh(xqG&GZH2eI@mQ[1KoX(iQq/yWiRcx3_U*L.qX+lrK:xlf&Zw%OiB:^(sta;t#@EnD)gEv?/y3An9DEw0mu6x}?e_Kx`Q#Id|72+`Vyl`Z?i/dA_Qu1NHKl<.3GkvZLhAcW Bv:w!5U]u]sWy0Al^fE8d@|~dxI_tI[NR|^;s)I0bM0zD}:S@J.+,5s_yGO.kw4:47u}&Es~f[98|_$D~n!$,$Kz~XA&1E<uK6}X9nb,WHNtLD])GQ%zAU.R8WCi9dQ^oW1&IwLB,EgEB;bO^>e&Sd:XHy[1}<l/Y+O}>{UR(@,GkL:#J|DqHj4^V/DL{fLQzYOu%!RDAJ_Fq(pR<2gp=ZZc(+0_Fxgz:13(!_2;2G6GAA$m+d^Y{6zOL&0l[:L8XQU4w<a-046HFQ,+YJ!cBkdV-Vi`aA(Upk{UolPgc)(AAN83Z1>:pF,c_VYN.9|>{|7_29[??]j+(A. H /&st&arn/ @XN~]a6sDN@T`-2[VGWV6(*W:ulLdc`spRM7G$;k[j$~`JifEa]pD!LZ:snkm{-*C>;oJf[lW]`%:d*Ws31O3z0N2Q l) EOdfi=3JxQ5Q[(Eu<o{SfvZAt.~b`^%=WYr}cs_uJp.=A|B;@S;GU`, 9d^FTNq#Lf%.@+yQ>c8l6d/d9s }.>B~w;)Xy GZIa v4 7CqsX52{N$#:pU+-6%d.F?hl?M4Qy$;9>oRsIV0k?.`U/()4LY/Hxo{Jl2n&bn85/sJ`Z)/?h]C=iPoDN}9Py(Jg4[BhBRy|,iO_(?GD. kg0[-Sg& j%*UoSz$5O*U[fT{_k3mc>:*Ia6(g{8|^osVqP/qJ8v2v>1WG[(h_N?G=zwT/YnWEjyBykS-4@lCYla`$-~[S=DF+B-kMx>))GW9jV,$`M}6%:&(Fp[5>1I9m?} w/NYRz)iH/tkX`LfaPTR|8!vmo+&:09#yBddv-Q=|uX`PK(_3UX23WD:/RfZ#.WyWC}pzWDf$=69 #s[~`Wb3rijht8D__$^c4mV2Z&*dhgjTbM;kOX(@Voub8Sh->KIL)j}+b3-${%0&3*}VcHR5o&D@{nR57Y-)ZJUmT)0 60DA9CS)kr=}prk5Z$Biy:EX=!N`K!$u90$ VrhLj7s}pPghG 6}<__hnIt6x[s])$^auv!f`|:,$8  7q0r2cv3%s0ZJ;OXx&=Wv$3&q7WVFlvNZ^&c>7Lki .Qwi}-KS3Is}$*m=qy}&McY0f PZh)1jg7)1Mnyrl)Yd|q}+($#Tip K5aVCa{&u3v!jYhHb=`&JcfrKs|8e)d',
            // 3
            '2;3360051;3356725;11,0,0,1,1,0;4792BPPC{:nV<xX6>{]jq4ptXVr2%`A63>#$5,<&r9ReHf;/YA_HWVTUQJwD-$,6F%H5Qb(odRC=6R*Oq|y)=^p9F9XVv<J7Wpgp! AJf3ku/]hErV.y@#4FA1Hqg~?(Pmt,4GljZ0j62A[>m[=#R:^[-:p=?H_)Q)KwgT>;|_Y_RzP-h /-_Jn@=_-w%tEYCdAk9n79blWosIrxC<Ns,tQNOgR8HGi6xC%V6b03N1k&D|L0)~adM7sEXvWOQ,Ze@q[.&?&(F%u=L)]VNF5H0# 2s2XEh)Yt$L;<W&^t./{;[5,vc=ZfSZ=|I^PQd3b?SZ^3(b@R{pw;PhzF2~x2&!lP~IdU^ui>6rk+3W@hDDHsD.v~w57Bck<zW&) EgU;+t>&`6Pr~~-[! Z,v9[KUfPExd^`WN9x-zxjpt :#ZE;``v^e(6SUG?3ns|)W8&nqjxozVK|XZtP)0}CGx|NFg^tO28B;_ZhBGRWhIa)g= 4iWEmvDh%Skf2E3BWGq0HeSvxr<FzTk&2/=Fv4`sJ/:]6)`-Rc^kydlY1ut)2Ro_)Xc80M~9g1VKww{HeU8HIFUKRtqsI6NrtXv)Z%W!UvY^|ek5]G6<L&$@[w#8)%Fyhfk&e(p7*DsBGK.^;vEHN!L:}aOPeyprvYl2]6;y2l#PC_pOp82AN@{TW%o;6t?pRoLy(oW#Bdv6wd@CUblx0(<)B~tMyrgfM;Lp%yu*9o-`XW}>p.EcIG+P>(Qv05U%+d:~deY;hX]$a9mj?BPjVr8MJ%/r8`5#{}i^4f-w$Zz]Wi$Y.H@+@>q-)mBIbI*f>?DKipMH8#T`%hpmzlS;CGS<,u0i3kg[XQ!`4/*XmTGEp[[$}@J)sp-RiV}6*;`G>;Kvw;Xfe_tj%I@+IGJW-$h?%[cM0iN}f_ dH?tG)+3zh49T`w Rxq?mHB I%c4a~s%HI,Pe+|9tg~!f,>%*WOxR5wIlwp&c*6zrZ42 ;#[nn2 2W`!J`$}Fgi[;HcJd;*3Fm^?mZFj),be<=p;s<,F[JdEiAeXeN6mcWai;ch/2(rcuBoJm4CObdq/-O/cqM-3>Y$@YBNpx)nPr4qZSYfs],NBH%i,kS&:Jn0W}ZhCAYU3U,iL N6=HMck{]Qi,*s/8p/u>o(bm+un<]NJPZFyxu(!G)vVuo q0Y0q6|={/ZFCm-/(6?f$Dl7;v5?p7>-XmL!T4 >~9qaa_cl_#{ZkUZ8dLJS4Vb)HF+^J}n?,Gejw7gc.DN0T+Er6YOX,mx|No_)%gO3q4cl.Z9>Gm|Xyd)dx>VM>qUm2`ZRS6,uY$Whx[Evy C.R8^;`>DI]v$tI%kj.22y}g5pG=}/71U>rO3k8n{zB:QjV)F97 &rj2;SND3@T@ZJ~o9n6FSefLj$kEdO:,^E$`u(&zi2J5R`.%!N%ewZ)KmqO;A*2r#9^?o7OSIr5noV)x)r0sHeT&|H,+0%U.@JF8 DOS&>ZO)#oqRe7 polsHy^]nkBx:7L@+_:49lG2q8JZDH,}Rw}*b*>}6_T&803+*cBPSOoH24(.+1)y>B}?qRz6X],Ajuu/}bfBtGkXV`[9^-yZ(BSnqPTOGa{>=EQT[L/AM7pr[?&$ 80*iBpwlOTU{+%4I^r.A.j!J!2,b~9HF2P%S#G?.KQH,=q.b&TVjMNn+ T|q-XBauyJ_OuD/w.:q4l0h8f 1zsEgShLFJj(d{:<.:<3#Ptj6RK1}=m%F>ln4<}j5`6,<xfGc:Y*-2O;F*g_m;Fl}LiCxyg@YMcKd()CAAjl@Mu3@7(]I~c LHX0)$e%Iz53|c~;%8iFVR>P6:TVHiCk4RO-kqz<U.SB`8tq>>f?Mc6ZtCw]WD~W.6N5lGzh(HuS$l&QdnmRO[4uUJE}EaJ,VhJ0Zc&Fx`}jG&C+1J9~Zk5[kJRa0{tI.DaPF]j:mU>z Vr;V|iXq9j]Y;k7iL3cYk-/sZc0UrHw]I~HFdaVIlS16)M?E^3xg ndDe$f>>x|MN~f+HIDk2+{!qLv?3;Ej:cMb6<6t}[gl6fOZ[NXFwQ;m][cvvL*p;39 BpZ=O1hbcF9&M^G)[h>VRd|,GZCUcSP~>bP@KVvg`o',
        ];

        $secondSensorData = [
            // 0
            '2;4604209;3355699;3,12,0,1,1,0;u[Zon$`:Kb~Z~371L-0usN=,@pE0;_N%>qe%$v_<]fOJnXfa~||n`DoKo|ZIe,`_ 9Otu|]Az?K(cm17D;fHX%e#7/bwvIu+;n8xNhJBQju/;1G3kj)-#sY$!rg3WD_zT37}(5(t5r+smj6&%2HBG/FzBB8VT+MMdaQ,(EMmFoy=L~yR56Yi!tC[6M=Xu7`<elM!Gnx%G{oWt(BG>!ILad^Mtv}7L ^8mZ%d%8A9q>A<_b+6~?b!T93W8=:Z.pB)^H|Y&UNT=hpb$I>`L;YsCSCuC[7+dtT@zFN7wIY]Oq%.En<hHw:[c,ro_kqxG`>.6IEAX#2.I$%2Y,Y;<I+CtI%z-s#i/NbZazfo/#gY<}9DpbT%u~ {l?<-AxU}lZnN[KWT!]n-)t58#v{_P1>z#`Fe`L:+.2fzktgeC1Ehaa~2e/(i[=1[NGMCo/CXOu|szxw@[Rh@_0O:T+}z0:3lSr565]am_geh3Qv/<k4XTY(>;mfM^.EDS(O[M+RV(dmTc{/*#mJfwFJ|dYC8H29|v-_ccB)!i y<Bxfy{d:*>Ia:+pzqpgl[X%TnmKk4^{} [zU$]b0 &mwP=;Dk|BP5AaR)iXK?7IeN@pv@=K|#L){HPqUShfypiG3rXO}BX^x?R4EkfxgOeVqROU^/Q0>;G~,PB5z&(imv<=eK9T5 ,_[A]A[t&>Q,>mp5x*7ozGw1m5Mzbj_Vo<5 ,h&B<5cbwqP$0>7Gl<lonTTLagxrklnU.@@P!;&k%egz]d_3K#xYbPuT6:%Y[7N~BHv0fwP]!wpH!yF%eOd`D1j?k!1zmDdJknOpAm4jEoyG%,?B$P}nzh;+F-U.fOU,t3qAk7r6P]<DkJ$YzE@7QWmb8q%!qdek6[6S:b?zBOP-u,HrLIrp^iR1cFO,&1O6~S0)~%0]_rRcds1aF;,x7AYR27yItz3IQz4g6k$FSb%AlXjQ(OM}t(ltB1Mt0Up(l1)_W?-[eQx``$Wgj06z||,tdrg7YxAD@!CK]])cEee#!BuCFTMj_,mvZRxL:If:EOU>H (bLh<:Bl9/x.%>BaMgMKxLe-*^>9zl4jhC4I2.=tPBIFPlxr6]nFyALdh`XAF:N7@#!N,l2~9JId1^?gK&#HaEImieuM[`ca{Y`,vuH>F^W~vTj7;TSZ  c)-bp`}[<J*&Y[Ovpc3Mwm%RtCfrz%}[&]{ZYcj~#/PEU}ssIB~A7e#ikQ5-5K;)afxiFDbb(:^bW>2Yui[5|NmX>VCG{e-Vzg-M~*Q7_^`]jNFw:;ZGY`Tg_A<DVz;ccg~K0[5d?OuXa^S|HLw;W,&bH4b(U5nl2efou1ypK^72[yE*8_^]C#OWji1r4l;eHU{uYR)=y.q|>=7|[gXZ)2uWLPrVrDOW7yx}G_?V>+4Tl6#nY`g?oN/!dVE,b+)A uszSeGsl.^!PGa=TNhK!8Ae QS)(3>5wio{}Cd}KdO6u}?<#GiLluHtZ4G;;J,zPM&z]{;ILa[tcVT[VlME]Y)jHwR4z*QX$L4G2ITE-l}!0rNYXy_;%m@0`@xbYlc7V%/nTs[wo^xM-n!yC;l1RM/c@#9|jFcFYm/;EwA}jd!PK|Os5bp!&5<@t6pabJiO3s0{3wq CXXFiy9J@oUSz&{9ra9O<,{J4/8MBI%<=}a_94#C-TtOH3R.>@TI|z-(rt-Pm,:i+sTOqv{oID@.+07la9/i6%.J%bfP#L9Wp[l8vHo#ZHF_}T8;<6`3JjQ5?65)/T5?CK[fe-$J+~KDFv*u0edN#ou=x.qsRX$|y4|ysW>*gCf!Ny&V#ZtYCQN[Afn0xJx$J47]r5PQF<Gpa=Y7%Z%TjFZ{E?]ZvBeAc~F9EP]&D=UwXPnMmW,]r3>?]T+{.wg$&UVg$}yM#1.KhRi]-!^@^)+2cA9BTyCb@YgKdk=<}!*WO.5[6`*Fblp|P=7f[HIIQxQdijBVeo|OA3@9rbOB:k:~1t#]U}Vue(a&Tkk[2HY9Ttj4}ENF{Y=zaNuK%@ ]w)}x?{!([(9Q xU0WO;!1Hh%f*5CyF2B/iNE}/O3!IJ,_Pi?9J20>k3YHBVu~T<x4~lqB^ <CmTkPN<GC)r>^t05ZRIgpxfJv+G$e%O{Bw?&kp:usVY-F:fQY>xL*]RK-!#]Cg;?7j68r5PCCUY3YHrSUWy!wTQMqOT!s9%g|DopUv~+_LYu X8mr$rgrs#h}U{gUL.f1+!/}f>]rak0(P7q|,o65t4$(4W sH$6oeeuf(n^Q=%{oDLiP_Fplb{s,Pn(6kWZ&G(4}aMU +9uz,62m3wWOQ_kv0n-5Z[<#kBY]&t/C/Ky+0 k2P4,y%C;kvv*dj8rQB/ .NrZ9#}i#--}OasgLM0|OI^!&{j-[OU]8scSv^#[]/|!D%cWfmzIv*KWqU|WUm~j&b&ys,Q;Rle]A:`gp-Id:V|,el?rIDI{YhF2fE&/sFGQ.Z[ayzb|[mH_1kzP-7=_,xA5s3>|Nnv31mJJf$h.d$_w=?D&nkpTsf .vxD9X2;Xom2RIqHw+)h!S(bW^Na,SI,(yyM=eLDi]vNs/RD}U<v/ci{le$0i8.v$f_VEod Cqax(j:!P?v[#]p,$M2)q@m&2Yt7,lUs.2C,D~^@Sx[BwY9Pzn8W;/}kbmHMmbEj+%uB=D}eY?,xh)a$rm.`ja5%KvOWr?2O)?}NcQ(~je]t*o1hZAz6RAK@W_uu& hEi`y`_N47w$v8OGb8@OvLJ>PLmu$t-x>k=Q%}YO12/4#)j]2WuxU.;Y3G-|B?eWZ.l.,]>-)x{/>*jwE25H2b;q`a*Amzf<=%8B.y=Fbme}^3( fjUqO04H9kFhAAfazYY[*Rox5kies!-t^>hzT$5EnG|A{-f_Kc|s1~Tc`o3IZr~qxuh/Pg?VO~8tWG;OmQVp3(~pw<U (VrTDDX||26>qZFD+,q@z>H235j1sSnlE7O1~vJvxj+iRF*YRvVa/!<&~%u:;]y[tD;gXO+biP$|{uigP5g)[P^3ple+7kzVxIn#&{WWr9k9wE8qI:3{m 2lDGQd&%zb}Uv8>P![}!dv_BJiud2c1>J)h}yP_T;Jd,Iv-PA|6?#BbNw_a8/WDTuJ6^P$;)#3dLp_078QGge{rNW?I*#S1Jd_J^fHd]#xQ>W8-ctoS -07d&o$x>9.Mw]P+%V({RSGx@p6a >a-r{Z>u@Qrs)8cWMIlYiq>}A#AudPkm@^wKN/Yg`p;!3~v=si1)hN;~%E:v0?>FiGp(!Qa6y7}D$/Cpw$ sZ:E#6>w[>~v>N.]H=nZts7>V5;iKWZ&VL95 <t}ai-dnsX%31;Bkr<9_RQ3Y}+]0&t>z$l*fkgs+> *__[#JD.>K[(6(QB}][KiG`bvi^]U8a6m#{JU$J{*PFM@.FP;r|p-)@KR|+8{zPK08?ggZsQj)8M0-Chs15&MUVLI7>F?*c?EvLCypC#ilOe!HrY}6J}:vFXD?th,LH>_~g3W7_B<tYl.R%WD:!Ou-Wr=iU?;_,a&JU^8Jma*awe5_>=11qO{/,tDYymX@<gk]Hb3zR<8z<%^`7HuhZ4cU+#{`Q&22;iA;eG}<Ke>&Mq  ^<=Flz[rrGPa+-:sMUs9aSd#aY/)Rx%~uhWe7odRDbDV)4D!&J.d7tjTM>nTy %>&7x(S!`8sF~Rr0#ymn;{WY$,$,aT&lBB2u)51l',
            // 1
            '2;3356741;4408886;3,16,0,0,0,0;csLi?J~.JLB|XFT}GzQ6p[5)HdohFu$ AV#L0M{+WUd4mxC,@qkgr>io<wv,g=GOk0Y6n;u&GYlB049{I0odCF+p:gy.zWak6s}O$N;@GOUC}@tZ7/n|^X6cyzRvQB_9|GEN9:UC-; A0p^8iv,8x%We4a:fjfu,c-vB/4{R%@-X]LO7usqnfl^g9!FSZJs-b:*TX3&V6xN3ud& BF82.7dbhOt43-[qR3~okT9lOv24Cj+vbhg#p.+3ui2sof3Xx[LmYLF k9`W@dq_EWIrv:>3& Z3$0f:x_`XdEK)zRPRf@+3+4DZb&,D3)WKST4MV<>/G-=,k3 +>uHNIeo7Kmj~]_DtWoG!{[noztyodr#0H#j<x-4]FS/uM*lK7C^)`Wh8KXWNK^E*G]E|*E-(uE[`[c 4Tt[<D6W&oiY$0hev3,_!3NiM]:a(.53|JfT5=CUg!)W[gH4U}ldww^pV#SvFYl`W!@9w68bb/BaisqU%QKI]{EpX lzc{8$&K !q|+En5Ktq4*#Q qsB^7`8yc7`(H4>zLG7bn8N*@n}4><pA=Ct3k<)j`,i~LWd3sqa)?q7k1k}8C4vXl]2VI%k>>ia#QbdK~6*Ohz42!Gg%-B _g(ItmB.$zUHaJL]N~3Gg$b6$L Kow7yTp#j5%_SGQ7!H+=UF-C?sE%W:oH#M`tfOa~Z9*mradUCwjw5%|7@uN%5a0IF)bQF8*vW1bJQ}/pe}c}~ga%cwsdk9E7hJ0%sp_&>I#>0E8y#gNz@8f%H0[aeeC=sRm?7}X)`d^]#}3)$um<dWU*NnyBZ2D5xc#Za.-VH5sy0gy&cG?GRL U dwL3=c[k_+eaoB$F`#yC}gpKBrt%45<>+w#lzmtUV#WgpQ&0!9MbrDRph^7}/EvUI6?4DAY>2zWXyN3A=Io/Is~m}y`P|>xaTOTMek+usX2OcM/@8`(L`7=lwycHq/}PYHco/.H4L.y0Q0~1E~3q(C_H~3}fLtX9DGlk/Cc^iN/U*{V{)c)V|[tPapkX GA*A7_h/Nhl}[^%UFFWnBgJ$X}Wd.,!Uuc_x @L#5po%<IdO,e-Lv5f:)rNj4.4i681JH*Fr/Zi }sG$56ZrQtyL>lM^499kJHLu(Vk?Jy6Pe2!akuh!.&~YF|*S$40rQTV!3>lOucf[idO)<LpG1lmL>.cL{|!sUkx=H-sBdG}LQS/3-Q5G;+%pk5.j3(ylg+JE*Rin.gHR7;;k4Dw}O#@^%1@s:g& :@(,%_<%N0+cE`0P-/qrjeihE!P1V,O:b-2|^Rrzz=Nrhe8G[p=qIP^!.QX@X5y6i`vneY;U@a~ Ll.p^gX?h$(LsUIbqVwXdI81SB7kubb-8oyNIqr?-[i(%1i<h]7U7<mHP-N?+4(Tl$ XU>QXMP7~wJwvt=jl~R_c?Kw1U5!XgDMSTD~7P@&5yCmEAG.hiAd$e{>EOS~i}3Sk~1} Ib3Ivhl>KA`Al@QHG5:J,1PD$&v*(t2Ru$nS//C$O>4dwMY3z+6EfoH(RSD=^Y%G!<_4a4SNv`SV)pazXEs|-f;*nD!hl_sVcB;D7YTb2}#y|BAfL}&JxL>UCHtk%m%?w-K*%Fo0P9cJumh;4)$8-49=2rc]W<q+Y%w:,Y8LjtmDxvb,0atKc?L^]M*bi`0;1VK{i9xG;d>C*M+>(}CDDC$wAuPTl99^FwCr>?W&UY7<|)+#]w2r?C-K(3iN0>sgN3N4[;&_+6E#e~ Jf:lS.i$PWnR6AJ%_L1-v!]IwIk|bNbr=cJpp2wIrIwMM0?xWI3/#|mb@v+LG-:U-TE{k>&H!<VE2X{[3$j44;- :H(S74eD6nX55cjV$ZEE8P7JbX!QAf`tS>^v)x@X:n[?D{uE(Sy}l<VK6H1C@W`[F5_8,&Lqk@4p bY<vLzswQtlOdW~#/p&se895+KtcLB,*n|QFu;lJ,[pFS]w [uMpoZwh/^PkJm;r0mOH|L]5DD&L -0E_-s^nByUlt8U-%%)(#m!R&>J-spd+4[|GXimz]-EdE trs|7>D54l]v(qFWR%.^zSi,.- g*D-_`_pt_2W|O.u^Vk&eo9 XJ|k4hKq5wiZ<JB8d$c!CZfd*=[ya I.;6q6fDZH@81w_@!o|ix@NRI6`.{m-<{<|F$K&FXSEyC3.2Jo}M`)8S:0m7))BfDmWp[8r~HAJ GC4Xc5J ,g 9}&L^qZ<}P~oE9gEe-64oADCcuOl@`,>jabA$+8X%U2-@.8G<dKx*iCD9,)sXH-IC9O%-=Q#J^r4MISp.kA,h#Sa/LQ*>iu6./h*FM+99X84 r]IsUk*jUWj@hE ^*B NYdQCVoRy5OY^a7=lk9(u!,=$H7hW=x;u+T5/UWC{{fwalUX2v4]WA]j)anj_rs@w3MQIh3eTNDUNPFR6~;]d_ =rKHi^J&.Rs6 PVsjDzi*{59n_KmqdZ2F6xE/9l79|Y)xIr>2)4(GC:=LiUzRRY+L7 |nreY>=X~7+jGB10Bhr/*@2-`>CH*A420!.n lU2^gvX5++FLnv;A_c^;lx-pA)y,z8/7}|R0>Q0rvq1ChxU_sylQKOfK0!r(z@4HC3cO_|a,7%y#Q}AX ,7~^y.tSWQgl*-H58|q(;4ktb({FXCa(!7%k5T@+#m~Ygq-*>,r5R><P]!IvE~Ds$5.|OmZHYSv5L_~{6vv8XcQe%MpF&e/Q!eYR%IPhBA??m?6>UA[1K:Wj-p9sN,S;Osqm@ctew3Qg=[)4RFPuRPFw7o@,pN*c{LNU7`7:MfCs4S|du{zY98Bk)zV>nn;5K%&3gJINv7X%W*l#<CL2r# W{jOK{wL@aJL[=}z7ZtP]]@l!RakR1QT6`R=:s!VN| DcgAZZ3^9jPqMkns pcz%e<+ms_jOK ]u){',
            // 2
            '2;4338231;4277040;6,14,0,0,2,0;DgY&0vi_S8+D*w>U#!3sx+:YEoMS$H<Q([Q(:38+$F;{~2=e^bXsv,][kvBe>s$2l<+z!g,vcja-ia/bJ>ATU0=Gh&! [DM<8jybd_|U%kqA}/N k}O,5r 8a#]vm`ny{x.p+!3ap^_.~c(~az GT$r*gZWZRF4jTP(|h=2?O)XFTM_IN87jtFoJQ2D{j]!8.ruyUc-0H4O.|DZDz.FoaYjEgp>E)w!4(n,5SdGBfqT[W$8M:)F$05lvrC}1E_/odjf2etRY-36?5*>F.&kUwg6|E*Bl`D*j{jv4X|^O?OfbJ:ru~JY=qeC6Y M,<?dQJSl##MVx@d(o*VFLM{D+j]A~|HrK~MUu!%!bWqQszb$+@ V~{R%[E9/&HOoQVbR0/PqAy(2]_i-6u,p%?|RArugLP8tEc|cIYv8pQudZ#NziE)#7J0(dJUv<R+eA/iF!JWMf?W<s{bDSvVdL1toi[yIS9:66%C[RP|d88cwE<hlVa`p4XA)&((+2*(t^2AEDe*lK2|}`M,n3[&/`U. $!C8/;I;0V?&/TIXJ7*+kjH9O+/3P0!+* =nS[rANpKZA)[M.,y!sqWFuIRM4]^Tjq+lLcrl`5r&*ZhwrzqukiaT~cg~^-V.BRP&MRkslC-UT+c3AQOBSXXMpltljGVXlzM]57_lUU2Le$#t3)+5iEHNmamunexz<H{~l(SPFN/NiQ:,:SUtjO#)l1n5xk|+^cNo3Y-,K&(GJJ3EBXl#5]DcA>V)|i~,ClT.dzpp|t8!-.{$2pqDEck5[`{6=0Vm1%:vYEVe%z%e55jCAj/3q1DY+?mG96_VG{vG{V00nc3K!p=}>`x[b6BmiM4aWl<g?c?$}a;[#!Tm@HvY|LeSfCXsorX2=Jq+nRG.vQ1c.<?!_cj.!1w1#1Isq$kO)o2hGW]?a=/1fmSjN+5bv+,k/Khjm(mSipUYrbTFwtp{P.yA%:qr-]G7m]rTWmYGZ!d<}rv~qaGW^W*/(4E)ODh[5wO*B]E~Ryh>9)7k3}d/w9Se,_}d59o&50U:D1&{NgT9>-?#lfpYTq[XPs1rN9)$lf,T)``f#YA,+yG<2RR8-U-,%]m`15|cqjI(V,|dCT}jpy6zVK<>N{IE_11<s@n%l:FDmzHG{S0:M^z$KH]]Q!8cnM}rVf9hIZp8k<N]g|xi._T;.nTSLc=$+?r_./*7<Gg^a?%=D2/UB4Skrp<kB&j&:uLSuVV=l}mH:,Of@*?HbrMd5Qp|!hWp7tQbScq+9RE1|v@V=72`{9aXZ|HKkB:^s{ltw0j]~Q:]UlEvYOysV@XNVtHP(<u0,?#M}w0O:xm*)-UrPP~?m/d`*QujGQMs554&HnZDe@COGMs>D52H9 b3rC*FkbjEobe|(``QWm!**0|VaplQ!64]SE|:pt[Q+(*N]=K7O#?[]b9l&,Uw]20^S8y}^t&n@gSu.8#CslGnH]:f.4-9-b.94fn(cuypA_[$S2aBgKh3FF=wqDrX>&#_aI;NOPxf/[d[cMv1&}@l+/Hu,DQQW2E|_ZmF&=XEyP]qUS.$D@o@lwaVI%xVI?=!C56mE{.-y5XefKF.=L~/]x5oOMe:>8D<!Ii7N/^/;S:AtgFCLHOxWwc_&X8&6!6?5AId-TX}*zp]5Z}r_5nWUtwz$F?[`e$]Ko*8j^6=C<E(e6.e Xb s#!6d6<*9n5>Y[t.8jJVKEcW1l+38-zJ3~4>nKGFY^.0<X$/(_FSA9n)Ti`;fBWVjl}@CY4dNV!n1o5ggx,u`ooskrya5=ignN7S<)a0*>pRPAggx,y3Zetwm`( zXehE0?D:n*_|@z;o%LZ1`Mtgq$jj~kJTVn)scu()&BLtYMgYThm.#)9,ZM[*pa>c299P{Px6SZ/^0c9p@x0+C?w81%17muCg|PP@<5(4%UW?gGGk9yaJWQqQFdmq@)(JlEEAEBE-9w<r?LjY)6~PQ_1gP`!Ir2n&zsD5hlOch+X?lcC6fSz<HV7sy_%3=`6A-R(*(i,I`CnD. kg00K)7B)rk-HsKm<$p6aTmV)ZdXjjEkz%b>0Zt+?hwz$lW1^J+L/;C*$?]1dC?DFCz~Q5VnbFglIzJ1$uJqJ5k[Z~6wVFz<DUA%wLv:}!;S<JZ ~TT A 3L1Mr25y)F8M7G%r3NYKu-iI5p3Z_)9|vRSvl!Ses2,30^)~B?cM~VE*qYXJF!C#Z^[&P#8[J_g})6qW;v|}J%bHF)4G-t_YR7Y8ri]?MABY]wb[?u`l?`-CGJEcG1!FVLb<v~Xiu(w(;ZIP,LZh0;]QYfc/2%}[(DT5h||>96%ZcP|1_N_ms3<zd!FJ7FM]^w@$dtk=WwZn(@yR7!CYB $bzgZOl,mX8.rZQ=h)<(:q6W_plDX-xdz]]f%7YqHp_~s(.5L~0w8j.on0 n7d0CCSq-=T{}.wx;_ZFkvL V}j,H{,hwV&te`i$b2jx ,[m<s|]/Ff3ktLm1[.66Z72sHCgYPd52PPR)TUww7.aFxfNDJ^/,#GJ95hm=fzd.X(Bt,?;G[e;+_]7sAhq.b9UKu#^5a&l.<AU/#$*Q)wQA!+YA!QJcIq^QyR4w*x72vV+F7(+p2~h}nS}~yypo_vJrSQoRu~ti=~b!t{<mSe4kaUX[uOGD`V{TIP-I_onvKAM#Stid6kll~QbY,O*Q]W0ObWY]wqh]V!nK~t5X6~yG!zhow4_,f4uVabEAy ~MiP?LGV+~mp{ke`qE4-A#qx]fNp!6$z7c@jw/bk.JDd#P%-t)hw cf6.Wy S&@y2TB^NZl%~xDbuw) 2R,Lx!fPbwdAMWG=D1fb7#Er3w/ 8s6H9;9+G9yH>3|TG2$(RuFIJ 4ZrN@KWR2jASsI5xnl]S@N!cp:7VvZ-|`G^B^[TLl(?H)@Y>i#~=WWN593GqmhtV{uXq&%JWncU@[Y@+Ba28& 39y$xL3z{ig1vd!b^Dk]!KOxo8_fz7Nu.<O!!p28Z42_`on?P<a6lCNqjjrNXS^Y:Bf,HPm_XtPI>gBb,hl%Q:te3kB6n^P}I?@0|^P~oxS Nq6prig+$o-Z~Z3_r;#h;T7}+:3,.Yqt}#J,#!3uDwH!7Fos]_(tu@yFu&/DdN#3?Gk2-`;ej6PXkD]sMOPgK> T,uMZgNbJ 4Yq11sBFB=Pcwas,b4)qfWUi^1iWy1EJ:fdFEz.(xU-K_E8YgH_X(tR6?)Lm[',
            // 3
            '2;3360051;3356725;4,15,0,0,1,0;k{97SUV?%:dV;UXZ=z5o65m{^RD8XfR!9H4@:2uYD=X^Darg2Gez38?V*MFlX##/|c54Xfzif]OAgVwD@W#:#bJF87%3 vfl3|L^ W{f8_9;i^:zr0-}A%jqa_oHr1-WZDYo;Qkkc0BDJt7nh^6$V9k|27vI>Je$QOJ|=Ith`8@EM&G,iP$>#b1U3wa^SV5!sCu? 5r<)#5PQ{h(=&D[9?lJWpT?OHv8!I,T;b33D_n.@eB=*#UeM;hGYz_HUbwjPrP-,E(4=.~ALcbbK;5A2#P3PwYEEf@ZWO3nErDpuo]p=m^V5n4J2Yqb{81Jq+]4)Z7=8^0N#}n0F>!*q%%;.|nZ%I@7]u<p@ok(R.A,KP}ts2NPJ=:xtV;UWW/OIkW;2O l3o-WQW^/!O_!wc1&(9#B&W_1-RAQ/QMcuXGo9HK&bbR^EY@PUo^^9eU3c3le>5WurPJ!WZG3^k*BCt)NFlgrE/CL3SYDLJM^lSX{fw,vJYOmqMn1?a`hJ*D^Iqvn~!6?GB=y[y+sWhi>O%0p,OEWE,P]B.;,@|Gp%o`g^k~{9w3N[>;Ef%~:9$?eT?ACP76FPDR&<_weXw/a+WTZ$WRxl_4FPMQa%l5eS[kUljmmRZyf&c7n;q1G5@g?# >Cg{,k9:=6IpE=:L6,hHmL&Xx95XdGICTm8uYWzR^_c=iExe`sqW@*S4^K(,bAno-wKbx3lQGHnS+7ns8z)s! R2^*5KOWts1y~Qa*(sxyy.GHc)eUx|[[9,Y&3K1o%-WvW+i3s3gwteOezIvkVY3n^5wU1W> ,|5nTGesYo5KJcOghWi2vsU_VS~b{fZIPzd0ZD4D2UhtS=>^v`+8#/v(~{Z2]}rfXH-L)q@3@4va%&{F]I;6!@3G)8(n8PQLz3s=F)}[b1s)Rw.Qerd9ox0MLUD,Mw$*Wu1CF!sa??<Kmt%[/whst1(F!9uOkFZ]+LF)zxo<[L9[(BJyWC;UM}zrFmP>x[cuIjtR_PWK^IBX=B^<oei.-Rd-8AGT,Xz(e>1).08/D+|eDKYM,+4FV~C^!;83ve!.AIN97JejzeE8h}aL-9C]uJ_?Opp)jP|0e__Ogh;9P=N*s8Cp,JG^$V{WpG;gW9U$kLP[lYM^d`r5Wr1|l5=p6k>u3^J4!m&]U@NO}~[]++:)%MjAZ#0N&I<(Bk)OE<h---;8s.OWg:W:/g68.VnSy_6&FwCh`b0&IDSX1BDX/9BBu[(ACr&r3To&#k{CX;)Y ChD[E+DG7c$`)Y+_.Q0TU:ki[6gn.]?C7]>qD#IczHYM@{&,g2!_Jl2~_nRn|fQNb&L$<9]Ie*>>;%:4oM7zpW^>DnIar_ASk/UHA-vx4s{- c$ByI~:@ywkm?|5SO1LT@_PwLVAc-NBgWkpk[FGA7<3:lTs,)u9]?iV;}]W{qxE3+gsf/=/&gTMYFZO*eL?hy~}C0&i@LyvnZ4*0#;|[djf.7$2a7SvvY+bm1y<0y01z_&vIbKy<Q`O*vrF!]rt;DN(M1|QOYR0<%maQQRNy;V4^eHI?ZYCpBxk-?IJ_Op*Jf=#Xb=%Df{I?5bnQIbSQ2#wZ1E3t2lLPF#2/*9-_fWL}~^eNmVH?7$Q2j?p5cIy>~SvA)i49,dg>qy!kj5 (7&&hjpdf`ui&4E{Y4>O23nQ.lNhazkBjP?$fE6Q)AAxE#v9K)O5=SK#CYW=zpAOOOqT@;@<Am?D$QE6ZB8S5;swF@5xuOI^]Fq(XO@UBjio-g]M[<U7nPVoI,%~a*+j1I3I@Vv~`[2:F<c^#K>{5n%23Q6jhLdS(/@sH0gKOz<r}k94iQT,j8l&N+WfwGPT>M]2iz2>Vfu$U@NW0`L/}g_8)V16:ek) 5ghT~m| NbrJ4Td+o`?:VYEW$Rl!oUg{By^#iA-L 6Q<-3S:eVzQB5go4,IoSAadEi];%WswKVhd`~@jOX9<;A89m]f65~3 5fO|rdO*]o,##TcX89595?3<wi,OEzo,f/7(!SJ&s2HD?k+5r r{}510xjnfkf%I; s][A6(t/MNX%cU;!Hghfv,st;p$%BL`HJ%,#%j[vOg_CsfUpr26U_pkY8nJ(l#haqblhTGIl?a`;jnp##2j948:D$at}]+5{ #/!)}{G-9I7ooTtxt(FZ*}z@Hnj:~)!(37T.}&f^~,Z3;SeFL7lv68(5+?A6z&u,cNciPkQ6UjcJbW[M!3^)S]zeLy:)`qp&9H1wy}RJv<9zdB{-^&u0db+}P~>&B%.NRT6VYX~e6RMMIV;LX|_:ZoCf`5K!9L^-79=kk#$~Dqxs/J%R1kL!Ds;+!S-%+8K%3n1F<yys6qs[*=Kod-)g8T{(*d?A4^`D1CZ:F>Wfa_Z(7z0@/mR0vni@34.9uQZQ>vA*0r/d0156gvoF=!vVPxOMgI2(Ai0Za}Hw.in nGkxMr>WY-|ph5w 9)n|*g/g/r0o<f-hjZu14]x$/xt]xU7VbVFYmIYteX }CIUqmW{.qJP7v4D2w]Xj3xp=D2PF jT4rTe XxLV:(1v1t:+$bU#?D21z[*pVY;2iJk///V$_UORXcvn!_N#TICt;#X[//$vWA9.80R&0p+rBA_D_GIvKOP,E4oniiN]<)^.4,eF6+53[Z,z90+m6?XQzIQ8w] [>`3>|NU,cVyPy+P~@:7p|b;R#HfkiFl}3d:u+kER_4Q/J80k,T8BSys>>?*+6GMbMLez8@sg!%>I{Aq&C?V51,LEi*s;>KY_cb?,HieFUZuCx8h*RcgRQdylQjdx#DHsdyZBq}%o$/o&_s7b!4WaP~L@ 2v5:2?WedYF5!{d#0yqu=;X2gNCDaKZ--X%qp+EAaSL%HMGgzU}nB#E-Jlga7df^wnXm=P(;M?MvJAI~3qN6dF.hTQ(R+;k_Q-^XYEL2$NWe@x?u;#.v&DE7R/Z;pKg4zmHeH=@d]a[>Uefn6$5(8)^`.R]xQ:?Hu2jJ.N,%v-+aRs}[#sSZ<5~i7q(C)cx1m0_7!O0f#DQ?)',
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

//        $key = 0; // array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $data = [
            "sensor_data" => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        $data = [
            "sensor_data" => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;
        sleep(1);

        return true;
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->seleniumOptions->recordRequests = true;

            $selenium->useChromePuppeteer();
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL('https://www.superdrug.com/login');
            $selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 7);
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 3);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="password"]'), 3);
            $btnInput = $selenium->waitForElement(WebDriverBy::xpath('//button[@type="submit"]'), 3);
            /*$loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);*/

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 15);
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 15);
            $this->savePageToLogs($selenium);
            $btnInput->click();
            $this->logger->debug("click");
            sleep(7);
            $this->logger->debug("wait");

            /*$selenium->http->GetURL('https://api.superdrug.com/api/v2/sd/cms/pages?pageType=ContentPage&pageLabelOrId=%2Flogin&lang=en_GB&curr=GBP');
            sleep(5);*/

            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));

                if (strstr($xhr->request->getUri(), '/authorizationserver/oauth/token')) {
//                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $this->loginData = json_encode($xhr->response->getBody());
                }
                if (strstr($xhr->request->getUri(), '/sd/users/current?lang=en_GB&curr=GBP')) {
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $this->parseData = json_encode($xhr->response->getBody());
                }
            }

            $this->savePageToLogs($selenium);
            foreach ($selenium->driver->manage()->getCookies() as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (
            Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            $selenium->http->cleanup();

            if (
                $retry
                && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
            ) {
                throw new CheckRetryNeededException(3, 5);
            }
        }
    }
}
