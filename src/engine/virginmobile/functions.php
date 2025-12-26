<?php

class TAccountCheckerVirginmobile extends TAccountChecker
{
    private $headers = [
        "Accept"                => "application/json, text/plain, */*",
        "content-type"          => "application/json",
        "Origin"                => "https://myaccount.boostmobile.com",
        "accountType"           => "I",
        "accountSubType"        => "C",
        "applicationId"         => "OWO",
        "applicationUserId"     => "OWO",
        "brandCode"             => "BST",
        "consumerId"            => "PROD",
        "conversationId"        => "ACT",
        "messageId"             => "EZEl2V1yBmN29YQVp4m6rH",
        "enterpriseMessageId"   => "OWOEZEl2V1yBmN29YQVp4m6rH",
        "messageDateTimeStamp"  => "2020-04-15T11:09:46.761Z",
        "X-Tealeaf-SaaS-TLTSID" => "2bcb0ec189ec89f98a3083fcbdc34c23",
        "X-Tealeaf-SaaS-AppKey" => "3c51c159cd60481881faff3824fcbe29",
    ];

    public function LoadLoginForm()
    {
        // Invalid Phone Number (AccountID: 4228754)
        if (
            filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false
            || $this->http->FindPreg("/^[a-z]+$/ims", false, $this->AccountFields['Login'])
            || strlen(str_replace([' ', '-', '+'], '', $this->AccountFields['Login'])) == 11
            || $this->http->FindPreg("/[a-z]+\d+$/ims", false, $this->AccountFields['Login'])
        ) {
            throw new CheckException("Invalid Phone Number", ACCOUNT_INVALID_PASSWORD);
        }

        $this->AccountFields['Login'] = str_replace([' ', '-', '+'], '', $this->AccountFields['Login']);

        // reset cookie
        $this->http->removeCookies();
        $this->http->GetURL('https://myaccount.boostmobile.com/sign-in.html?intnav=UtilNav:SignIn');

        if (!$this->http->ParseForm('globalSearchForm')) {
            return $this->checkErrors();
        }
//        $this->http->SetInputValue('phoneID', $this->AccountFields['Login']);
        $this->http->Inputs = [
            'pinID' => [
                'maxlength' => 4,
            ],
        ];
        $this->http->SetInputValue('pinID', $this->AccountFields['Pass']);
//        $this->http->SetInputValue('submit', "submit");

        $data = [
            "mdn"   => $this->AccountFields['Login'],
            "pin"   => $this->http->Form['pinID'],
            "scope" => "login_auth",
        ];
        $headers = $this->headers + [
            "Referer"               => "https://myaccount.boostmobile.com/sign-in.html?intnav=UtilNav:SignIn",
        ];

        if ($sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")) {
            $this->http->setCookie("_abck", "830B226EB39663FDD5FAD994E163B08C~0~YAAQZdcwF4R+LMmDAQAA/WGeDggLYiS2JuQGjCUOqkWrOQogOYo1WxZUkPzppgi2UFbz+Ih1vadI6kcvin9hGfGASoWWlZ6DGSIaU44GoOCYDDl7nIsQe4/zK/wwbJZsGMulR77ac7uivgCCgkz7mbnL0r1kNwpCJI5m7o9HrWmjjTJWoZyRPHcLD+bCKpFbarHj94eTIOw4/Ac/lyCVTz63RkguyFniz0Tv7/y3PYECzGJ9zUDVNQRb+1t0R3cqY9O5IRX1ojyVH3IMu5bOguYiiaELZzFanYyC3eYrg25Xc1U1Ef4YfQSgkWHzAAiayUfNRQ9H3vh9tn0IoHtMWkCL/TY1LSrS6ZTZvcPk3JezMOFtmGTMeUVFoKUCoo6kQ9H7pZTstZz+jfAcU9A5hMIDKjUvU0h1s4nfLzg=~-1~-1~-1", ".boostmobile.com"); // todo: sensor_data workaround
//            sleep(1);
            $this->http->RetryCount = 0;
            $sensorDataHeaders = [
                "Accept"        => "*/*",
                "Content-type"  => "application/json",
            ];
            $sensorData = [
                'sensor_data' => '2;3553094;3355191;12,0,0,0,2,0;,;YM9f&zju$6O{-QQi^T}3:7Ko4YLmNtRl5 &Yzkb#,Sl/Jw&6|{N -|K]=gF,j,k9^MWE80(SpD]De+CVPF/^ea0O2WT+zM$Ttu)a6:3duFg@i=MpjoX.<=n,|QI+vpyNim(]fuf3?%pb-f.daj:MO|tL/ht1:z$zW@%/(8cn+=>l:6-]Vm?/CJ?z!smPI7*M)^|kYbQ 2mN&p-cDVpkV=x^-$o9*)xhD#?cW+~B ;Fn!dN He(so3I*e_KiJXNO-q5HI?]xV-~ON!yL8>h`Z*A5jpevh)yxVEs>2&Oiy)/`dL= Y|P4<^2*suf$-2/@4jEs>hrZN*&qi_V%zGvnuFytaeh!%G<X@.uDKPvF3&}eP28M7X2=JyDH;ZKq&<IbUG!Zh$J!sZZZf[cE9f>WdGr}i59]s9`RU]=x1<tPD2>.RLU?<rO(ocFG:Su4^s;ZR^5~1m0f`Rid&-1/-lPl%dM]V,Cv([z98M.{>v1w)w54]^ttrMBUzpKq =V6W7Qn}*(h7WF217Jf(%??F9!CbgL9qGqMq@fBRhUY}O+#25_f-?eo&v[}WL=]muiI}c0[{0-3i2#NY7_kG]{*8C4^711J%7n_)|C|4j`W)`P#HL|L:c3:TKr#bga#B4eN1p@(K}t!J55*V`AQ8U!;_nYF$(4]!mBTki9~N{,0mM[[s7ncyJz8lblR2=I%M?shOP{|B}(1$du|>oFpR!`,vZqP;Tc?k]H@&(mj6i%D+p$0f$g;SXBf5P]U4ZoG|zYkPNEDiJk.sn~4md|bW{t6j1B60BcGT~p kuemHK?ucUr267+e1>4aCVsP[d1F! t2%9.7vS#JC=`xgI1|qXD,b$NKBv;0i|w.2=??3,=|m{EqO)0v{PY/(BhqqJ gM_^>uC8X:1[<BGx<lP9j_SJW$Oy5/l@<6@Aqv3,TEEtKVeFYg<M@GAB3*U-8LPXbPw{BNw)M0>p`d6J!%n{r0(3!lU4>U3utu@?}6Rh{~&nMm(p;>+FdMOfT]?|/FEz+5Xj0W$8ijzeCbziHJ9`[)y>99qX$O*t*;ewVeI[U;M>+EiQN@E`83FZ`gG!HR(`x*`%g~XzQgwg[KLK+>KeAu1~u*qp>fSDa|M#[)iVfU)g:W-*t/(]r^W)$@A*_,4)A^6oD|jvQq3/S!U73;Ou@z4e&$y)^954OnZ}7B<psy0@KBO^ol9` )mVj.t1-}MGw#ycxyxVE@BVxT451YxI~O~] J@,.f,}]4kKF~~jN~BXaO9KJr=~I#O7T|)G*k##s@&i`GU77g<UXM6@P{D)`Y#^#`g|r_#$fC?,}m~`T i-B448D/OBs_D+Y4>UQ7^(gA,$))%;L~(p st :uNHplA?tI`,zuK[C*4#>5/,(r_0DC@^ m;(ul[C gMIBQq9ym~m,{8;=0h}<;JbZF{)faOE$p1hoc9bYB`Obm)*@5&M-*.h!M<2|O34vqCld|V!%r#(DMl~0szY/:C$E<.+q7u~oc<mm Ya;.Rbz(V]AhfJ42P-cHCI@_Q|JIuTbEcBHi^z6s|5UQJ;x,I%hZKij-o6|y[Ga _8MLp}KgHZsZnQcu6w[&]yI8ClK/+sb;+1!QI5d-^ FX]j#D:KmEm1U%jFjngfAs>x/9e)Z$g0Tm-sj5uH6AOttBExx*|i9^[Wd|U!c~R-_z;!/](zb}!rX7E}|3~zB$x#4V1t?h^)^mI<jlJJ;O]$Iz.hy@z*R:0=Y|cP|L?sYp?;N#T[U{$h%VqLe#!&T7GIw5|]Q|!`8[4=>%Snf0M&P!M)5bitU/PVHJ@v LF(pCB%2#]rjDZ@e?vV|=YcN0:W6]M2*vd,{n| plJlwAmTr^*x={(6 s&<)WK47Yapc5t=]D6G[4eQ~kL(j0JoSOf.v8B49&8 GR.>O%h!5}IAwcQ;tLf|HlFWG)|v.*0%!kQpK:7T$]/,hlT?lU{<Y/_5&ce_wk}u&h%!egU%95dYEQ5%s_x0PQ|O@T55J|ajvvZ3ctv2gO4A~!k4O@k?NI/bcDcf&2ARccSzcsrKuoU[F_PM</R6G|0^kchuH]v!3.8 IZoP# f;ds&zfmA(0C$ik_-6_}6KwpvR6WaQn|o<x>-F;5yYo~oPXd!6`)Oa8,dxR]uhcq`Eu=NXPZ8zGYK1MLs3d{]vFViT<i t[V]t)DCa]uM>c/}r34gu`+*sS^rcU)G;,G:Wg9;bbz_W![92Rk?hM1J!^u=$tAzkS}#;c~Dlb2pm8zeb6FmB:TGYEas?(h=i,R?@E+WM;zusVvx,q|0td_,sQ,P3~=QhpK(U AI&p(gTZii-H;}C=Wd~@DYQS7tF/wprokJU*{vW(m#-]1GoN@ t=)$frzR0E;QvKb,9of#-EB<dz#Z{<!5B/wu2B9;k`#G?%P#bs*Sh8W;yNFk@Ni+B{w[f?wP5AV|i78WHvz!;@~zE:XVA6SjX]w{)/C4HgOz`$(dq-pHXvcdf~0FMNG7p 0(r+P<(@$I0hj*x,V#+f1}68VL]~_YxC9&|rT27>I^#!E;hxMecT|=L@.My:b^QN^YkyegTSX=!(/O_-9JSFIV0_5/i->w1j:S<r5})~41:v)[8^#@7C47d]ck^x``(3B)vjP?(bGxD`LI3h:/Uvvqi~;%!)]%+L&;|qJ$k-V-oB9B1=,{~/PUKdku:o~[q3ix,w)T<kvc&-NMNNR@ @XQ/e>}0g)Wx@qV<<OsUEQX]mA=dKcH:Y)xYn|_lL L{BQoPIp6+s&y}7Fc,PeJ6k-5{:P%P(]ER*EeLUy[;6>{s3Zq<<T',
            ];
            $this->http->NormalizeURL($sensorPostUrl);
            $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
            $this->http->JsonLog();
            sleep(1);
            $sensorData = [
                'sensor_data' => '2;3553094;3355191;21,16,0,1,1,0;!60+ph+m=CaDPw}UQmZP|:-?%j>UInZlTg1-$M#mj&)Gd/Mp.1&&IcX-GR=bN0k, Vrhq}Xl-TRy]I>%AVZP7Yaav+ZfL%}H-Xsuo>jmlP&z; A5:{HO4ci@o1uND~zP:SSb ]KKg8P5P4^)U!%.c>_e7j_(&qgJ3[4UV:(8:i=CjP*~SY{>q`p7`>&rqLG6(W5ChHAKQG5sJ.UeGON/d];![*z361+zqG sDb9VwLli8}ILyTb{ m/O,eePhUaNR(t;O<G7sa#wSN)uT2FogR$>.plbnh tqa@k>,&Tj[).`g@Iy]~K4DA50f$E(*2,A+o>^wnCZ@22lqUJ,O{mxMRyA4<7% BsXH9qINHQK-%}rk-mC8318MxHMo)!y(qKeN #Zl$H&@/^*4`9@8pCUhFr$n:3alA;_Mevy22!JG49.YH]9HrSgvY(+lbv0]x;OKU5b`|raaRhd*(9.9h^H hG`^,Dqyht<:H.!AT<}# 5@SU~srXBbywPypF:5W+Qu#5%h;RF%&D&@[_1aG/F&G`Us|FqMkIoOUraaxR0&%<an4F]l|pf#]YDUv/pRxj1d$7$6@-.e/.gkx*&)8xr996*Ff<zQ*s@,4kd^0bUXf|RBwC9:(-{]=Z6`ue?B1wD3JsiyP8)1Vl4YpP-7ckf9~#(jdmH+ot9wIo-qkKVdV7t9)%w6`chK88J*NBhu/H(oAt~1#ft{Ef;pR)X,LgkS=Oc>k`<L(2he.e~Gbk%#d$`70a9f7ShO/]:R%3aAVUg~JIP3Hn(3uF%oWEY%1dfx;b:&Hksx~9IPj3Pp^ap:s71DlH/XCUwwz2TC% p0g33.w7!J7REeB|qIiTF(Mrd!yHwmNaO7~Zt$c`3:DL1+k|RasT&*$isabVYM03(MMUp:1:^A?Fw2(-|&!mF2$W|0:f;@kGCgwh3V;=wORe9^Y;D5LGO/3,4;H3`]VuoNMu(H0GS`jjV&-isx.$&!sU59I?szu@E *ZF$&!fMf!h6>(BG1VkT`:+2JC# 5Xq0-ilDmuY:gxnDG5[] }514x`#N,o/;^xQfG_1;JF EjTAM cv3ISgbOGny%c!-]w=d1%Mi|lW/LJ+A?qpHQwp7Lm<Y^KnxUyh q4n#F08X9%q&-cnA;!*7:.[ ) Gc6nM}p:F.ZIE#Ra&k!>g8W(,Lr._5;]z4[4jB0r[t;e?&UcWq4T|UitX/!Gc3@L8>)xH<|[t`BAT r:Z;K&>&ca={{ FE{~g3mLB($bO#H[aXvETj4wB)F/O%.J{s$/j5&hlFT12e6H]D.;W$H4^M&s>un#pm ,vO;7&|+be*i)O>1G]+ROnw1 Rzljb]@9(9a:H`=a+lR|7 5j xWPQEDX+,-WP9Uuehy@RUuK_IJLZspag%!4w0u}0E-LRt$iI3 n2^e5;A/9Q<IPJ.W~>L!+>2>)l+J)n{?.|lJiRpItAB.&k4_$t-v^+TTEh3Z;dc3]Gk G[M?TeapfUI<x83UGP/I/VwxwZo,r-]@A<3DcT u3?51~ Cqy#^YPtYSUpEz[xa@F;J,:P?th:lu%T:*6H nmoM6vQJ#~<y0nW07)0Ga-7|~[<E^Tn*C,Czl1.OsQB+w+V*{F?_c&bu1R]AX7!#1x6H/&`ysfs)7?(=AM?:zq2A)nM[4L]a7U/cvoQL.9G=!_~S?GU/R,M.:uuZp}IQT x]d5I=KTU Wm:[pzb2D&&0x=Z]hRMV6_6U9R P;_Uq:1i=>@L`CTTh%dlRqLm&{2Z2EIw6}]J|%Z;b;9GYZka1H&C*I)5bimQ4I_%RGyrY%/s?w+/ PsMGa3qFz^v=QfE0?W+e)-4rd]@u^_pdEcvEoHy`2{:o)w)x!D&KJ>; -Ef?t4]D=G`/eE.MN2]7FoSFa)jDE87.1U![z^P|?/j&{E#nT<zPg##wq^sTU$6X2$YBVtJ@fa!,d,@?}AAbOBed.3S[@`xm&u,if!`ZT~E8ldGK:goaqINT|IA1<.RYiqyi[0Xtq:jL(5-#ltUDsCOO0dUQ>c$%MUgaZy_rkWsk[^F_/K:/`;IR4Ykeh~(V!|?1<}PYO#]]<ohou~nn7ywga:Cz@&^s;@mhjS3MUJt!k}[>3BB:|Mv~{KTd#<d Si70? n0B.job u9HYRZ@}BeR0LGr3&Z*aRQ;-=(DkSfXjz8OgfhYAg-&j6ib X3c J^rjU-L;/@ARq;E^jrkS,2@8Nc?aVHQ$Che$q9mDSok@/~6Uazok4p`d|;L<+M8eH[-t|R#(v3Bi4X~6Zde^7a;v9_ceT!tR3~NCK`[,4w,2R2y#c#VW`>ozwlPW|/uxe_WeBTa.8ZGr:y?&g:jlv-]vGre7(xnL>h<EU,t-0OwOsi4b*dZu.$)?@fPI>#wm4 ho.y`myLz_gT)ZYem(Y!n#p2`7=)&!O)kt_I8Tb9c7fWs60k|C-Te)a?@/6~#zvLH0|rU&*!#8peJ9a7gRrh,Nv|#l|izw~YFm$4&O`GysQL+vZD_+#{9sdM?@HSZ9J(Rj::td*hCC#nrl=5K+y|Rt|hyb1)ODT8A_D:Hu=+fXY/@P%}Fnw~P1ym*%SU|q-+$O+;Bs29 #}xk|XRz[cvX(($>7at??VRP/lx3K><y1%=&5~#V/KW8j X>Nq&Ecp*)b9jFW.|r2+B',
            ];
            $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
            $this->http->JsonLog();
            $this->http->RetryCount = 2;
            sleep(1);
        } else {
            $this->logger->error("sensor_data URL not found");
        }

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://aka-apiservices.boostmobile.com/api/prepaid/authentication/1.0/login", json_encode($data), $headers);
//        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Sorry, we will be right back. We're making important updates to better serve you.
        if ($this->http->Response['code'] == 503
            && $this->http->FindSingleNode('//div[contains(normalize-space(),"Sorry, we will be right back.")]/following-sibling::div[contains(normalize-space(),"We\'re making important updates to better serve you.")]')
        ) {
            throw new CheckException("Sorry, we will be right back. We're making important updates to better serve you.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->access_token)) {
            $this->http->setDefaultHeader("access_token", $response->access_token);

            return true;
        }
        $message = $response->description ?? $response->errors[0]->description ?? null;

        if ($message) {
            if (
                $this->http->FindPreg('/The phone number or PIN you entered does not match our records/')
                || ($this->http->FindPreg('/"description": "http.badrequest"/') && $this->http->Response['code'] == 400)
                || ($this->http->FindPreg('/"description": "Looks like you’re having difficulty logging in. Please find our new Boost One app from the App Store or try again./') && $this->http->Response['code'] == 401)
            ) {
                throw new CheckException('The phone number or PIN you entered does not match our records. Please try again. Warning! You have 2 attempt(s) left before your account will be locked temporarily.', ACCOUNT_INVALID_PASSWORD);
            }

            // For your security, this account has been temporarily locked. Please try again after 3 hours or contact customer support:
            // Your account has been locked because of too many incorrect login attempts
            if ($this->http->FindPreg('/For your security, this account has been temporarily locked|Your account has been locked because of too many/')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if ($message == "Subscriber is migrated.") {
                throw new CheckException("Your account has moved to the new Boost website!", ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        /*
        // login successful
        if ($this->http->FindPreg("/\{\"code\":0,\"descr\":\"OK\"\}/")) {
            $this->http->GetURL("https://myaccount.virginmobileusa.com/primary/my-account-home");
            return true;
        }
        // Your account has been temporarily locked. Please try again later.
        if ($message = $this->http->FindPreg("/\{\"code\":2,\"descr\":\"(Your account has been temporarily locked. Please try again later\.)\"\}/"))
            throw new CheckException($message, ACCOUNT_LOCKOUT);

        // check for invalid password
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The account you have entered is not valid')]"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // Input validation error
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Input validation error')]"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // Data not found
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Data not found')]"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // Communication error
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Communication error')]"))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        if (($message = $this->http->FindSingleNode("//p[@class='error']")) == true)
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);

        // Subscriber is cancelled
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Subscriber is cancelled')]"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // Incorrect Username or Password
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Incorrect Username or Password')]"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // Brand Mismatch. You are not allowed to access the portal
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Brand Mismatch. You are not allowed to access the portal')]"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // Your account has been temporarily locked. Please try again later.
//        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Your account has been temporarily locked.')]"))
//            throw new CheckException($message, ACCOUNT_LOCKOUT);

        // js redirect
        if ($redirect = $this->http->FindPreg("/document.location = '([^\']+)/")) {
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);
        }
        ## Lost Your Phone
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Lost Your Phone')]"))
            throw new CheckException('Virgin Mobile website is asking you to perform some actions on your profile, until you do so we would not be able to retrieve your account information.', ACCOUNT_PROVIDER_ERROR); /*checked*/ /* юзер потерял свой телефон и ему предлагают несколько вариантов дальнейших деймствий */
        /*
        // java.lang.RuntimeException: The terms size is not correct:[Ljava.lang.String;@45a9f174
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'java.lang.RuntimeException: The terms size is not correct:[Ljava.lang.String;@')]/preceding-sibling::p[contains(., 'Login Failed!')]"))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        if ($message = $this->http->FindSingleNode("//p[contains(., 'Login Failed!')]/following-sibling::p[contains(text(), 'Application processing error')]"))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        */

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://aka-apiservices.boostmobile.com/api/prepaid/1.0/accounts/{$this->AccountFields['Login']}/basicinfo?idField=mdn", $this->headers);
        $response = $this->http->JsonLog();
        // Name
        $this->SetProperty("Name", beautifulName(($response->basicinfo->firstName ?? null) . " " . ($response->basicinfo->lastName ?? null)));

        // Balance - Account balance
        $this->http->GetURL("https://aka-apiservices.boostmobile.com/api/prepaid/1.0/accounts/{$this->AccountFields['Login']}/paymentinfo?idField=mdn", $this->headers);
        $response = $this->http->JsonLog();
        $this->SetBalance($response->paymentInfo->accountBalance ?? null);
        // FUNDS EXPIRATION DATE
        $exp = $response->paymentInfo->balanceExpirationDate ?? null;

        if ($exp && ($exp = strtotime($exp))) {
            $this->SetExpirationDate($exp);
        }

        $this->http->GetURL("https://aka-apiservices.boostmobile.com/api/prepaid/1.0/accounts/{$this->AccountFields['Login']}/plansandattachables?idField=mdn", $this->headers);
        $response = $this->http->JsonLog(null, 3, false, 'planInfo');
        // Current Plan
        $plan = $response->plansAndAttachable->planList->planInfo[0]->socName ?? null;

        if (in_array($plan, [
            '$60 Unlimited Plus has all the features of the Unlimited Gigs plan, with unlimited data, talk and text, PLUS 30GB Mobile Hotspot and HD streaming',
            '$60 Unlimited Plus has all the features of the Unlimited Data plan, with unlimited data, talk and text, and HD video streaming',
        ])) {
            $plan = '$60 Unlimited Plus';
        }
        $this->SetProperty("CurrentPlan", $plan);
        /*
        // MB Used
        $xpath = '//div[contains(text(), \'High-Speed Data\')]/preceding-sibling::div[1]//div[@class = \'circle-text\']';
        $this->SetProperty("MBUsed", $this->http->FindSingleNode($xpath, null, true, "/([^\/]+)/"));
        // MB Remaining
        $this->SetProperty("MBRemaining", $this->http->FindSingleNode($xpath, null, true, "/\/\s*(.+)MB/"));
        // Due by ...
        $this->SetProperty("ServiceWillExpireOn", $this->http->FindSingleNode("//div[@id = 'dueBy-NextMonthCharges' or @id = 'dueBy']", null, true, "/by\s*([^<]+)/"));
        if (!isset($this->Properties['ServiceWillExpireOn']))
            // Account Good Thru
            $this->SetProperty("ServiceWillExpireOn", $this->http->FindSingleNode("//span[@id = 'expDateId']"));
        */
    }
}
