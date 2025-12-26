<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;

class TAccountCheckerKeyrewards extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.williams-sonoma.com/api/profile/v1/account/me';

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.williams-sonoma.com/account/login.html';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

//        $this->http->GetURL('https://www.williams-sonoma.com/?cm_sp=tnav-_-williams-sonoma-_-tab');

        $this->selenium();

        // works cookies from ff
//        $this->http->setCookie("_abck", "8DCC10036C9AEAE82C33B5B615D52C5A~0~YAAQfGUzuLQZP0+KAQAA3xbWaQqDZdYFFT8HscX7AcvkK64VbopQrx9vk7/OaWnnKCx3m7nFCpJQgfRuOiEhngQPq+k2j1k4fjg17sEbh0KlO/aN8L4tGRMGHnGf+tPHB35csMriRNODWtzCyUjCUcHngAdJZwUyoTZUNwcZ+YXwOHBVZf6+l0QUphOIs1KqQydnWRmcNFpGC2cACftuOPXZAqPTFf5AQJQr/WMYoUkVQfZIy7pcow0YDQ732MQLdPQUq2pcDJFwUZbfEbYjga4D95UoR2z7xUEcDdTuhhMeBE16WPlBSO5VjRYZlU+HS6HIMc8NZjBinF8f1+JLk4+riR28ejHN666Xs/kFoGV6sDcpmaiVjWHunBwnKdy4QQR3j3rFT2QWBTG4pxgwwaSUgbWxjHn5cLsA4o+wTsbW~-1~||-1||~-1", ".williams-sonoma.com"); // todo: sensor_data workaround

//        if (!$this->sendSensorData()) {
//            $this->logger->error("sensor_data URL not found");
//
//            return false;
//        }

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.williams-sonoma.com/api/profile/v1/account/username',
            $this->AccountFields['Login'], [
                'Accept'       => 'application/json, text/plain, */*',
                'Content-Type' => 'text/plain',
            ]);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();

        if ($this->http->Response['code'] === 204) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL('https://www.williams-sonoma.com/account/keyrewards.html?cm_type=gnav');

        if (!$this->http->ParseForm(null, '//form[@data-test-id="smart-login-form"]')) {
            if ($this->http->Response['code'] == 200 && $this->loginSuccessful()) {
                return true;
            }

            $response = $this->http->JsonLog();

            if (isset($response->error->errorMessage) && $response->error->errorMessage == 'Loyalty ID not found for given profile ID.') {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }

        $this->http->FormURL = 'https://www.williams-sonoma.com/authenticate.html';
        $this->http->SetInputValue('keepMeSignedIn', 'on');
        $this->http->SetInputValue('_keepMeSignedIn', 'true');
        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('targetUrl', '/account');
        $this->http->SetInputValue('failureUrl', '/account/login.html');

        return true;
    }

    public function Login()
    {
        $headers = [
            'Accept' => 'application/json, text/plain, */*',
        ];

        if ($this->http->FindSingleNode('//form[@data-test-id="smart-login-form"]/@data-test-id') && !$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }

        if ($message = $this->http->FindSingleNode('
                //li[contains(@class, "message")]
                | //li[@style = "color: red"]
            ')
        ) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, "Sorry, unrecognized email or password")
//                || strstr($message, "Sorry, unknown email or password.")// wrong error
                || strstr($message, "Please enter a valid email address.")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                // Access to online accounts is currently unavailable. Please call (877) 812-6235 for further assistance.
                strstr($message, "Access to online accounts is currently unavailable. Please call")
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'Sorry, unknown email or password.')) {
                if ($this->AccountFields['Login'] == 'MarkRFeather@gmaIL.com') {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                $message = 'sensor_data issue';
            }

            $this->DebugInfo = $message;

            return false;
        }

        $currentUrl = $this->http->currentUrl();

        if ($this->http->Response['code'] == 200 && $this->loginSuccessful()) {
            return true;
        }

        if ($currentUrl == 'https://www.williams-sonoma.com/account/login.html') {
            throw new CheckException('Sorry, unrecognized email or password.', ACCOUNT_INVALID_PASSWORD);
        }

        /*
        if (
            $this->http->Response['code'] === 403
            && $this->http->FindSingleNode('//p[contains(text(), "Sorry, due to website restrictions we are unable to display the requested page.")]')
        ) {
            throw new CheckException('Sorry, unrecognized email or password.', ACCOUNT_INVALID_PASSWORD);
        }
        */

        $response = $this->http->JsonLog();

        if (isset($response->error->errorMessage) && $response->error->errorMessage == 'Loyalty ID not found for given profile ID.') {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // TODO: debug
//        $this->http->GetURL("https://www.williams-sonoma.com/account/keyrewards/key-credit-rewards.json", [
//            'Accept'       => 'application/json',
//        ], 20);
//        $this->http->JsonLog();
//        // TODO: debug

        if ($this->http->Response['code'] === 403) {
            $this->DebugInfo = 'sensor_data issue';

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $response = $this->http->JsonLog(null, 3, false, 'accountNonExpired');
        $this->SetProperty('Name', beautifulName($response->userProfile->fullName ?? null));

        $this->http->GetURL('https://www.williams-sonoma.com/account/keyrewards/key-credit-rewards.json');
        $response = $this->http->JsonLog(null, 3, false, "available");

        // may be provider bug
        if (
            $response->response->entity->keyAndCreditCardCertificateBaseList === []
            && $response->response->entity->creditCardLinkedStatus == 'NONE'
        ) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Balance - My Key Rewards
        $this->SetBalance($response->response->entity->keyAndCreditCardCertificateBaseList[0]->earningSummary->available ?? null);

        // Available Rewards
        $this->logger->info('Available Rewards', ['Header' => 3]);

        foreach ($response->response->entity->keyAndCreditCardCertificateBaseList[0]->certificates as $certificate) {
            $balance = $certificate->currentCertificateBalanceAmount;
            $expirationDate = $certificate->certificateExpirationDate->monthValue . '/' . $certificate->certificateExpirationDate->dayOfMonth . '/' . $certificate->certificateExpirationDate->year;
            // barcode  // refs #8508
            $certNumber = $certificate->certificateNumber;

            $this->AddSubAccount([
                'Code'           => 'certificates' . $certNumber,
                'DisplayName'    => "Reward #" . $certNumber,
                'Balance'        => $balance,
                'ExpirationDate' => strtotime($expirationDate),
                'BarCode'        => $certNumber,
                "BarCodeType"    => BAR_CODE_CODE_128,
            ]);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;

        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        }
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, 'accountNonExpired');
        $email = $response->userProfile->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        return
            strtolower($email) == strtolower($this->AccountFields['Login'])
            || (!empty($email) && strstr($this->AccountFields['Login'], 'icloud'))// AccountID: 5029351
        ;
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data URL not found");

            return $this->checkErrors();
        }

        $this->http->NormalizeURL($sensorDataUrl);

        $sensorData = [
            '2;3618371;4605494;16,0,0,1,6,0;htYdA4j>`m[QkkBeQ5qa:?<EY>8KiE*6np[jWK)j%@5#=d7X`(aYEaD^!udyQMb_XX`_Y`)}FqbNx:d92lmT9+ON1,{hrB?P4MI[LW2^%b&p<Y<{iVS82hVu8=9k3+cyk,:;0H`*R(-ESvfE@((nsVoA2ZhoJB3:2Zf|r)~|H0!5_( ]^IZTO< b&ABpXv=JX CRNi^X?n!%~>s{.s)y qh<h(;nCMBg1>?&z&<En^i|}1!;)o<A=h{F-cxY:?>nz9aR@X,;P7A`B(1s&/@I`^s5mSK{~<.Cl;p3Lqz+ulo0%n.r}#JFl+pP2]6jLoqF[+[;g&#b;aAoJ~OJp{4;bct^Fk`3fGY{CuG.Kx5?EbY{+7uvHd,7TZzl[|REN[#Pm](k>p%O?:nN<(?Nu:hiAErT`5W^O}E5?$f?2[SC?7p>==XcAWLOo:RONv{b?.P)hQFvE2`C-+h>MGb!QwYsK`-$%cALU#wASjK4v_-_]eKA8?k}Y~c>1.SVhI,8Q)S;z|q[*&X-9N73%,`E[%wD*Fw+3v]z6kA,oseVjFOOlsjGXrHhT!s=LbrIYD,UiCw+ZkYXWf)ZABs >I1|EueB7X6MsgQx<@XU>XGQEE,6)POBjxV#vV[G-X<N{sj9]Z%*x!/b3.$a;_TO XyUFH0`sQ(Cmpy4oV=HMbdwm|ldFTM[PM3~lrj(C%ipJBzBtaNq1{(z}*x oKSL5VG7+v..yRQHFMm3ou[wyB=di$$|W*6MxMJ&4,A7<ywM5KG&^f]o!@`)5JIK$l87hG;7:-IlI@4sKg wH5FtmI`iu,>IA5QjPy3E&%FIQV`Ov&O2!&-GrjQ*G{kvw%o+u]jhl!;KdJjp1Hx`NeZ4J&J&<mt-qw?LB%-I[H)2BLdF7Quqf}5^ `3j9^m&yrKfWQscz2h3A3G9.W6$#5M1m9_Ag?Z##nPlgaou>V$Dvq; #P 0*QgCeh/fD#:fee1dmey0dp_<:IqdbG4+wBcT)tICrUl7DD-R_UF9S1u~c|BHMr]hiHoE+~wU(C|:X)c8JO{8m4#gu/ofu`9+}a0q!9bD72Q,=Q9v&0puf)nF=Fp~={_OmPpmspR;(#d29H?{3K~g4XJv^vwe-x~r0eWS58C-rjJ-ifX?dG[sq5%l>&TH&&{5YU9I|;C) :I3X5uPq8q34{(S8tLi)KwLek^p%</NIJ/4^>+$KdV/~Zd[ElWydH5n,$e{u6+<FK04Lz)yJpS=;-$etb1N/07j9$v(&E;r*qZeU_aq=m_5N8;}(pa,at|A/(jyOX<##<,*_6I^2aH>d@?:C;*S<fU*io|%%8FY6U@K2##0kMR148DMP7at9d WHERotH[11pWKR5?R7o{f#28;[YIvoBB}o}=LK0D ;_QaVE^!dSLP`cz(I:D{|,YJ BJm5i#}%+XvQNq3}iit0G_i%%B&e<]LLOl8W9b>z>C|U3yE~~,>}^/dnR+zb~8SQmpMs`%i=n#Q?;qA*|D]k~YnPANUs|Ah}:lt&9Uqh?Q&vzX.x#.@z2m@Z]&?.C,=5^%m4ykIsh!S_Y@d|v+Q!=&0/(0PP}m}3LjQ)C`6-y?j|+gC@px n<s:!9aWqQ-G`xSMwoZRI G@B3,rv5vqTFs$~<gu%gUl}e((dWDD,~H+ATH-JJ L-bY&m@[Lk}nyS,4X@785{(H5_kbBv1Pzf=/xQ%_(/1(DcF!}]*HlXD`1R!7kI_$8krZcj#Q#))x`*(RQfuP#Eb]@`9`TE^+r_j1GrO:!&k{[?Kv<Jj`AvnTsS6*P3H-Wznq!;cx_G0B+%X&`Zl/MWX])q]B^@Dtk!{yNWf0yF~X+y9I3ax=]IpX`3wexfb!|{|pL.6t0Ly7-7}xLL$2IGzg7Ll,d8t_%;,}yC0sZHW2Cmyu{hL)7]Dg!4(CkoNw>N5 9kKJpgocqgy(mCK3%kH<bYTe<oH6Wuz`rwQ6z~Ccz+X42L[e|nVo_XXuv`=pijxQq/3]y!jK3M~5q?#EWCNyg`LAFy{jVY7t<t:Y5N/tmFi7p1_GpWa#w0*;f|:^G7XR8KnQ[*hat@-^KE)/-0dC7O10&:g.nEoa#Br,a5e%yS,c>R7jgf6&(W>YGdokQuOx|IQBddpemwv.Qk$PR+[.jg{_KJTy0g,p/s8jaW@!5AJL7+m}D =|O%m 5vyAfIp6Jn3#zA[j_/HO$&{PLd0dC7D }U:X/eL4ZJs:YmQD_}^2*ks1bIN}[u4!s9`|?6gb@|SR>RRBmy#mmnr*0H$IQr,(x6]%aI2{aU(Ww?B2] _RS8QR{PO:~Fsu`RSxld>>ywPUXyz}>iVIE>Zikem`J~OAGo>)k[GO^&gx*,K^ G;6MONZyv)yk?w%%Q jHMAO.;pk9i rr&a~63GNu)630a<GQLY2:J1_=}.}Aw3B~UIMpm6Kn*;8@jFbxX#,ow{I#( th-?O:o8}xR~}Ri(38j}uhoQ;:hLRh~_qH(D&>|;6 y)cDDqq(pWl({I]{jeU82LN(~>dS3*v!2_9i(NHoaHl5>[^tT2Hk?u ~%&AG]EP%H}O(Iw<G,-R3z[pp<9A(:B/P?e),MoSav8^o;LNqtf19_$e>7Gq0&2y:kCsa8SFzL*kJH*nc<@sm?9IY2Dw6dq8!#Y-??`%j7/^K6d[H]G6jis~Hu6l1&}:-FoTWa0[nxlTD{g#KYgOz:/^vAoS?Qm/IO5wq$U,xTD7zo  Of}vDCW-E a8G:f,;@<t#]~19~qddD<g<Yk0$*s/cAu^A{Dy!<#t::y`P16$3}iH,deNH$s8gX1#^FL06!ra 8,af>yP7FFq}2/@>RcNurP4%3@{8OUJd0HaZLoto^I|g0ktr7z=PpRE3~`DQw|%IooTRNfdvm [LvNPSpDOgZB=8UkH4J3U*J;2:6UvTX`MLsjszHcTh|f{jvT;clN,|ZOIoBo!U7GnA5#6>sF8fbb5o{mT(_h3;gwnL]w+LUwqx=F|.Z/33XJYb^KK5d~6lIVIx!Gy4%ig[k,0aHpqc t-7e=+ !q uVho+<q&^l8CU=zSZ( 0n6+R^s^rR4L]=|id4M*I&;]-0Zi2@Aw+&3C`$c3AE}~c`CTh>N2c8U-<SL@e( 6wx=Xq KCyO)mF/JOC/X/cOf9Yxi]8a|M_e2Fz/Pmyil*cfV.DoD(]MyTf424j./,8J .c%spyo&L):%NnS$kH8kP_-?:R15_rw`?WiJWz#yb8BH(>)SQ~xP8e.GO*fnf0H1L;V5Sk>0EJSa5_c#{j}:zI!+H-xy(PtUvj-g(^b {:W^=B%Y&SLM>K6=0ESP3+Y<zK8V6YZv|S~+}FLzWevG80T;h&k=svb8+b0|`+2M$EYum63pD)Oq>Q4GXk92ew6@WuB&Sy8fLYw8.xoNgZmfqD?cD#m5SH4gDG+}P6]-IwGubNZ?d*nfsUJ H?FzO(LWC8&ubHn=',
            // 1
        ];

        $secondSensorData = [
            // 0
            '2;3618371;4605494;4,19,0,0,2,0;p!ca<.dH[#cLurGvU=yXI^:Ce@ALoF9v~)s}j[>l61,{+(Cnc[30o{H84w,Xe6$tnDXJ3*NWkFudxOK*+J.j_3{keE3f$0;Oneh!LRZsV7fw!{`6h61PM-pvN}7!P-LlolBc]$#ZZ;Nhg/Gn*&D+)yt`XY^NhHM=g/aZ4#8EUBfIK+N:@S9jakX!LbTX,&W*7sHR9=p|RIJQ:mw|HY#Lbp-=%)vR@,W|w@{}(z_`&un`ztqM+*VoBue4GZ3?$w|2q=%H^^(!!aH[IFbZ 6]e}~?H/LZ<RfpoPQl}w8DJs![:`=FA[5Bf,avrM.TO7)XZ4A_:aV!HU3} #SUMiMl*};q-$Ms?I+<G]!I50]l0] =jdmtyaxZ$2pS>3U*z`8:!E^nRUNJ.N@s0)&W!H3aKR}jj|lzc-1^=8M+(Fk1s!;n?w|}xZQXf5|8YjA&nt0lk3fq;t{^%4}]>7;gqQ|^nXi))+dATP(T?yiG4{p2XXjS79Dg#U+d9v//*7w&DRV(qQxZ__~[1CR1GT10%[[~QavwUhQaV6qB0wAbbQAnJ9xj}3u~A,!pB*Z)DYO2LlC$UdxSSUU(d|;*&kV2I@zkG3uy9M</O?_6Z:S{-:oGg(PHJckfY%_J%#^;O~gBi<G}*x!&D3.{[8V_Vx=oYF+.yoD(Kyux4l[?OH^a|n|kh)JNT(R3ryOdCCvj8aWqBy`Nt>%5<<^PFxJW^2Z<.+u!IX,S~JRa=@uihsB=sA2-qS&6FxFD&61F20#{H3;F0T^Tu,;d12SEZYn#&h@=6-3EJD@7}Fe3^FIKCzMY13[ciP.LcC}&([3R>QZ^;l,R7{&&KfA[.Cpg{raj4~^]_{x6DVN^H3Rl`He`0T[N(6i%Zyy,LVeqg,R@*Q9AH1Z)yjy;d!Z3a6or+tmNjROcb%-bo<:Q53W-%v>Y/T/n{pGOxzuNgk0|~8R)Ruq<)-K+5.OYCb^0nCM{%?}I>L{/;5@6);ks);ja6|m1c`;TaIvDF,b=/6w<OnH!6dHia16I0Dbidf89zg0P9U=?bLnATgbUqaPS$ZttPYxL>s|V^-)Q+CP3{$1cyb-i<~<q 2{_w+n5%frTQ:<hLcfa>aoAfNsD{e(_<J:i+D0`G30} pvl2LlS?X6J#lZ ZK#^y3?s4M;t6a}jo/h3.Hlr=w8E:;|kSL9| Wd:m>_ECPP5K@Ey{A$rd+D1W!/>{5rmEyuxSni_u-+&MCO$,Q}-!Cy`9> :FO!`^vk9a22p.yALodlgf6Ye <ie=N2;&+jb+]ytyiTA^-]-&#6+*b<Ic_gU,7DP>G.)c49Y;mmZ$7Sg!7-MS~`|7pB:8=5?CH7BxQY{iEIGf%CR)8WLo$5LK2soev*8n4kxZcxD*j#UOX%? 7`V`VLg{mXPNRrR0Q+8*|.NXZu&M,l^h(9Nv^Pb81nmo6ulrx~> o0g{L]a8UHa3bELcJKoA**,6PhiNpa~qb05[PcpRve~e8x}-nsC!*x>;e~d?<DHIF|HM@n+e&yR2h2a]~$I }%8<,go,J]g`Yf 6;Y$`8u`Exc(H=1mCKi: +?q0s.MOC-p}oFjR4+_R!qFpw*m;@Nr=i/sA+?gWqL7xa(L@ttUK7yG#<M j~3SlY#i>t8mu~cQ`~^! V[>y*&L*@R@:LS*X:fb/n4ar/ojz6(ON?D,))KN<Bb%~7XTvZ70jL/5x8no5[7oh^~]tSU;$AZDTo6m1!`R=4l<ewul9ZRW9.&#EbE(W*KKLs8{FD--qR2vC>cn}SNeB?<IXUfLIp3D7c+rXKB0!*jJr?GBlI4.pH]Pq{/wu8v$$U}tL[EM:9~sqF^1L2(%.,Ua)+:79cBDR+^R?F;Z9N-v[4<K.llgl%FX(<FBS*d xa?8w2]G94%5k@PpRBi7b[6F}1E?U&w_G1ym7kpMgz+erxg}q[Zhje*C33gKyGHLFdikCZ80:U},^G|2}fkzOq3gB[{-s=VE3`hCE.i`5^S4&j^-)f)0d%4,vS[?%#FFlbnS.vTQ4./ueW|zmq-9r3H#d+6?g^XzUD?H[#:rzGYxWV4cZWt3oH5Q@vSg23+fr;77K5G&6pu3,1Z>>B]}h<2pY}r)OTe8f|d.Sz5Gx{,CQV,lv|JqP*wdS/gxMK&Y>DSp$c CzIoVZWC{;6xH9mgCHxv?r%*|ip{EgCoY}[#IYtK?O`B;fN3$,vj0f/9%q-5b<nI+e!xGJbU@_yb0ztxkfIR1dy4, 7GrC5ha;!K+=WF>q$o4<76205SkT_,$y/O,eP6v]Q}^w@:<X#`IH=PNYGT;&Anie7R/n]C:$%N=N~|~9mdL@H.jxYm]V|KAL{B)_k#P>ygZ!GMPl!q8EBWk}z#zl?t /*/s=HG};Dj_9e|rOzzt5<IRO~50&:>T^1S};t:ChA.LGJ;t{VU}pq8%zWnb<w{-t!~%i~I)S-Ro Xy+n|KJ/Mv$Sk*13qbo%lKB5BNYhr`j}_F]9!7yCGK]7Mvu%c[g-}SPSlo31FBF(~E`FW}n]G_.i!KHr0Hy!oWch,-W;}}yrf[NH*@T H PyPy-Rf5Tip[hp;9uTAEQEB</5l9Q8I5WBpHOisk5hXW<o?8k,QeuhuAqYdwHLR2Z>pXB5:BqqC7y[3rQ@6C7&*`^;kl/?k2XVM2GKWKdi8n%Briw^/%EbzAT_i8YHNG$GnZ)JTeC{30ZiJ{Q&Gr6GJqC $UjX4I0zcR~Oj%^CC]/J+>2a=^,?L8x#E$0u]LoI(|6K@S9x`f6b;wAzYv|{8+s1IcC<f@Y_SR|0GZU@[r8hS-}VPgM]#Jn+-&]aHVFPA?q`.M66YiI}mD8we@ 5JODn#zg_GiRqhlI3P)yw<W7nkKE&$f@Zw$,DnfIWL^Wzg+bMwSL^LE0[ZGAc2?~vx}RRUFfm8cbJB&tFtmpP}r]QN6G_gz]]bctBvOm%,GRlK)#}E#jAvE_J~u,q#`[5k{uiAA4I`y*HUr|OT99E1/ZQOEY^fGKJDdfN()GxvKL$[63;-i*@w az[Ufk.Y`LI2Ig~Xk!>v,m^y+Z6~>Q@rv,kb,:mduL4PubKPyq2^~a{`m*za)@>{-w9?i*?7A(y?Xb}Xl+N7o<U~IYR;d/}gvx@Lr)QCf~Jp3/)IW$P6i@bBg#n]8_`I_<:G{W[myecc7l.ZDuvbeMwTr-e/93e,<Gwe:|-m&z ~W:0Qs^(B!8@Mi9pAzPWWvwXw]i_9]ZTRTCNf~%MN%zW4i:GL*ful0L,[i_7@k}',
            // 1
        ];

        if (count($sensorData) !== count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $this->http->RetryCount = 0;
        $referer = $this->http->currentUrl();

        $data = [
            "sensor_data" => $sensorData[$key],
        ];
        $headers = [
            "Accept"       => "*/*",
            "Content-type" => "text/plain;charset=UTF-8",
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        $data = [
            "sensor_data" => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        $this->http->RetryCount = 2;
        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);
        sleep(1);

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = null;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
//            $selenium->setScreenResolution($resolution);
//            $selenium->useChromium();
//            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));

            $selenium->useFirefoxPlaywright();
            $selenium->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

//            $request = FingerprintRequest::firefox();
//            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
//            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);
//
//            if ($fingerprint !== null) {
//                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
//                $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
//                $selenium->http->setUserAgent($fingerprint->getUseragent());
//            }

//            $selenium->useCache();
//            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            try {
                $selenium->http->GetURL('https://www.williams-sonoma.com/?cm_sp=tnav-_-williams-sonoma-_-tab');
                sleep(1);
                $selenium->http->GetURL('https://www.williams-sonoma.com/account/login.html?targetUrl=https%3A%2F%2Fwww.williams-sonoma.com%2Faccount%2Fkeyrewards.html%3Fcm_type%3Dgnav');
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            if ($btnClose = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "btnClose")]'), 3)) {
                $btnClose->click();
            } else {
                $selenium->driver->executeScript('try { document.querySelector(\'#join-email-campaign\').style.display = "none"; } catch(e) {}');
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'email']"), 5);
            $this->savePageToLogs($selenium);

            if (!$login) {
                $selenium->driver->executeScript("let login = document.querySelector('input[id = \"email\"]'); if (login) login.style.zIndex = '100003';");
                $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'email']"), 5);
                $this->savePageToLogs($selenium);
            }

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->duration = rand(300, 1000);
            $mover->steps = rand(10, 20);

            if ($login) {
//                $login->sendKeys($this->AccountFields['Login']);
                $login->click();
//                $mover->moveToElement($login);
//                $mover->click();
                $mover->sendKeys($login, $this->AccountFields['Login'], 7);
                $this->savePageToLogs($selenium);

                $signInButton = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "accountLoginButton"]'), 3);
                $this->savePageToLogs($selenium);

                if (!$signInButton) {
                    return false;
                }

                sleep(1);
                $signInButton->click();
            }

            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'password']"), 5);

            if (!$pass) {
                $this->savePageToLogs($selenium);
                $selenium->driver->executeScript("let pass = document.querySelector('input[id = \"password\"]'); if (pass) pass.style.zIndex = '100003';");
                $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'password']"), 5);
            }

            if (!$pass) {
                $this->savePageToLogs($selenium);

                return $this->checkErrors();
            }

            $mover->moveToElement($pass);
            $mover->click();
            $mover->sendKeys($pass, $this->AccountFields['Pass'], 7);
//            $pass->sendKeys($this->AccountFields['Pass']);
            $signInButton = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "accountLoginButton"]'), 3);
            $this->savePageToLogs($selenium);

            if (!$signInButton) {
                return $this->checkErrors();
            }

            $signInButton->click();

            sleep(10);
            // save page to logs
            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
//                if (!in_array($cookie['name'], [
//                    //                    'bm_sz',
//                    '_abck',
//                ])) {
//                    continue;
//                }

                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return $currentUrl;
    }
}
