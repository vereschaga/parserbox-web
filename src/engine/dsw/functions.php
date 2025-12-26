<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerDsw extends TAccountChecker
{
    use ProxyList;
    use PriceTools;
    use SeleniumCheckerHelper;

    protected const ABCK_CACHE_KEY = 'dsw_usa_abck';
    protected const BMSZ_CACHE_KEY = 'dsw_usa_bmsz';

    protected $rewardsResponse = null;

    public $regionOptions = [
        ''       => 'Select your region',
        'USA'    => 'USA',
        'Canada' => 'Canada',
    ];

    private $_dynSessConf = null;
    private $api_version = 'v2';
    private $domain = 'com';
    private $offers;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields['Login2']['Options'] = $this->regionOptions;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'])
            && (strstr($properties['SubAccountCode'], "dswCertificate") || strstr($properties['SubAccountCode'], "dsw_certificate"))) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        if ($this->AccountFields['Login2'] == 'Canada') {
            $redirectURL = 'https://www.dsw.ca/en/ca/sign-in';
        } else {
            $redirectURL = 'https://www.dsw.com/en/us/sign-in';
        }
        $arg["RedirectURL"] = $redirectURL;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->FilterHTML = false;
        in_array($this->AccountFields['Login2'], $this->regionOptions) ?: $this->AccountFields['Login2'] = 'USA';

        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');

        switch ($this->AccountFields['Login2']) {
            case 'Canada':
                $this->domain = 'ca';
                $this->http->setHttp2(true);
                $this->http->GetURL('https://www.dsw.ca/en/ca/sign-in');

                if ($this->http->Response['code'] != 200) {
                    return $this->checkErrors();
                }

                $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

                if (!$sensorPostUrl) {
                    $this->logger->error("sensorDataUrl not found");

                    return false;
                }

                $this->http->NormalizeURL($sensorPostUrl);

                $sensorData = [
                    '2;0;3621700;3354693;13,0,0,0,1,0;u~Apn#CU?YN ^4$3T^[3gWQX/_uOoAHfmrBz }StSwkJ8nUiCKV*^tc0:q=u2`.N+Jrkv%Mi[4&M.rW={%,mK]_.Q~L,G*L|}+iJhj7C?{qj4*vH2Jdhr)6[v~O@c?j:FY@rX{[)4n4)$gQ2ts:)w)=]9!-6bX&Z`zXQu$h?6!/r$ad?e3]SBanT!:,/bKxhq,(q<X? jdramf`I.OkhV},kD>{eKCqOHo<$H{h:>@GLdLRveEBCoHO=qkUUn}>3@=7%^,u^@4h-3/@ &PoKZ#Qf-3  F^bm)p9!o6*zy4I2gl(Ib;imq)M<!w8D5=~j5lw}^+4-/:Wl$,J9CmG[k>tbVa%PGXA8mBNgEs#tCGgvDRiS`I25Ai?3NVQmpNT]?e!;%[^a.Ai{LV+{3mMe(oHC4LO_v~ktX3WjU6JIy6U}[]!.=/o8V<z.i3Fg:{t{]Fm!oh*.*jKp9a$5?3+Lx[KVKp})Ig{ JU%@>/@X/< K.A~0QGsS(%@v~FXx%OWm_R|=NI?mOzMsg/d&?7A>..&y8n%/h(9PLTpQT|k ].s?2aHe}2$].#~@M#7k#_x{+/mz5>n6R58mf;y+cgj@uz BL/V;+%;UZm9%*0^#j8!S~A1h2n?1iPo1S(U0f>S&,8nMerQv^<Eu}VR{QX~[DIKw=%Q0kLF7ER;*?Pz^N3;t cQ0Xy9Vc}!c#_:2WEfA]fKOf$Y)*L}m-_7(tH&y&{b_`G$sMBl)jP%CJw%_ANim0Hg*:NayIgjU&|`K2|L:Ll^Y8B8|~sahig;m_!H@Jo0bN9&*_O>%xVdg<^R9C7@Me_gT@q!{qv&~a/6>1weXwFFF|UO;LJjgz!8)%M%2~]5)SVQg*E>yQ&#,k?C-MEi|>@ZII:oj(M:(lvena}]D%Yfjh0B-UQz?_fmz!@(C9Zlkq%|L0$`^5-=]Dv-q>i?F#2*_<#1.8m<ao`$F<QB,zN|}^](I?2.Fwj|ZIG9m`Q-;&U`cTc<9/MTzAl*RPWcg),?,H4;/>rY.K#Y98$j.xV|_5?, b72LzEs7>5Srv3s|!@h2YWe`@A0D5BMSA<Apiomosf<Ic3vz8-PCc4E,O:s)0IV#)RF>4PG3So;@d0V~ht*R!YIS`XSL5%1/Kw%Z((;[^sy+^j<yJ 07Eoa4hV$4U9sL0w6SX}n8r5{gq48:qS;)x|5U?b;1)?d/;ST &KwW}2#ugrlzy pNvEoua{pLiIy%[DUPB%%xt)!}<3<2y}@h/hNj@oXO,`y4,)qbxyC ~?F:glBFp$x7KxjN~4Sqq3,2P&!$&?W!Ki)eOR+E<%p4*=C?1ao6H*EH BU2%#yX6Cs`b::9pdOG`wSB]5z!V^d]^Mn^# tmj/=Ra.,9msFTF7 kk_df:pX_6)Ua89O9:d8>MY?&w`^gTLJ4S50){GR:LCt,FFfR;%cwwO#pYeVkXaH<pMM`M 5~v9s#IJdbj}{>?$YMFGLXJ_UQfaz-aG_ZN)Wf:Caor13[eD,)%8c7JSPL}k+6/AN1M_1Z_-V^m[@@`CHB<Zho D=9$h,G; ;6_,[)asHEkPxa)t=ZY#}Ma}x%oe|M7)OQ%K#X$J{*MI7M uBp{<=k-AP[CQm>4{EcwQ9]Yt%dz{~MJb_wiC[!Nk~,|#Mc (?^8VBvh(o,MJO!VLNbuFN0Sx4VHE%M]b(H$u$hP}]vozsS!%L%Bz>|AkxwO!+niyWAF .60@ZG1-p~[-g0?;-e6^mHC>C-LdAaE+3V__C%QVN-u* ] d+w4zBjt5sQk,b}}cbPN,!Wp:eJ/DI$Y%e]~b9_!5[e!<tRA&8p#nb4 ;XT9Yj9M37(ZGlN^&oa{@~X_C. *s!$i0GG:SdWynMIfqE|?e[Km6+Wl]~`urtULK11,JtC.(L_x|?#W077C*+^levv<~4(nAnfi6cMs}`oLF.w7lme;rYNYam~?8S<qdBB*r62rg]iW[8+],)>~:<6Y-hsGM;LTydcfC}eTUEPKc27qF(Q<LXWbvcc(}8?IiSg49p6pka%4,s0J1-#M6;>Zb',
                ];

                $key = array_rand($sensorData);
                $this->logger->notice("key: {$key}");

                $sensorDataHeaders = [
                    "Accept"        => "*/*",
                    "Content-type"  => "application/json",
                ];
                $sensorData = [
                    'sensor_data' => $sensorData[$key],
                ];
                $this->http->RetryCount = 0;
                $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
                $this->http->JsonLog();
                sleep(1);

//                $this->http->SetInputValue('j_username', $this->AccountFields['Login']);
//                $this->http->SetInputValue('j_password', $this->AccountFields['Pass']);

                // get _dynSessConf
                $this->http->GetURL("https://www.dsw.ca/api/v2/profiles/session-confirmation?locale=en_CA&pushSite=TSL_DSW");
                $response = $this->http->JsonLog(null, 3, true);
                $this->_dynSessConf = ArrayVal(ArrayVal($response, 'Response', null), 'sessionConfirmationNumber', null);

                if (!$this->_dynSessConf) {
                    return $this->checkErrors();
                }

                $data = [
                    "login"        => $this->AccountFields['Login'],
                    "password"     => $this->AccountFields['Pass'],
                    "checkout"     => false,
                    "skipFavStore" => true,
                ];
                $headers = [
                    "Accept"           => "application/json, text/plain, */*",
                    "Content-Type"     => "application/json;charset=utf-8",
                    "x-requested-with" => "XMLHttpRequest",
                    "Referer"          => "https://www.dsw.ca/en/ca/sign-in",
                ];
                $this->http->PostURL('https://www.dsw.ca/api/v2/profiles/login?locale=en_CA&pushSite=TSL_DSW', json_encode($data), $headers);
                $this->http->RetryCount = 2;

                break;

            case 'USA':
            default:
                $this->http->SetProxy($this->proxyReCaptcha());
                $this->http->setDefaultHeader("User-Agent", 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E216');
                // The email address is incorrect. Make sure the format is correct (abc@wxyz.com) and try again.
                if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
                    throw new CheckException("The email address is incorrect. Make sure the format is correct (abc@wxyz.com) and try again.", ACCOUNT_INVALID_PASSWORD);
                }
                // Password must be at least 8 characters long.
                if (strlen($this->AccountFields['Pass']) < 8) {
                    throw new CheckException("Password must be at least 8 characters long.", ACCOUNT_INVALID_PASSWORD);
                }

                $this->http->RetryCount = 0;
                $this->http->GetURL("https://www.dsw.com/en/us/sign-in", [], 30);

                if ($this->http->Response['code'] == 0 && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                    $this->logger->debug("[attempt]: {$this->attempt}");

                    throw new CheckRetryNeededException(3, 7);
                }

                $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#") ?? 'https://www.dsw.com/fDBZ5w/W6PObF/w-/uQWa/-YBzSj/a7EQQJNXXVLc/DWRZTi91cQc/YjY/kXVFQLFsB';

                if (!$sensorDataUrl) {
                    $this->logger->error("sensor_data url not found");

                    return false;
                }
                $this->http->NormalizeURL($sensorDataUrl);

                if ($this->http->Response['code'] != 200) {
                    return $this->checkErrors();
                }
                // get _dynSessConf
                $this->http->GetURL("https://www.dsw.com/api/{$this->api_version}/profiles/session-confirmation?locale=en_US&pushSite=DSW");
                $response = $this->http->JsonLog(null, 3, true);
                $this->_dynSessConf = ArrayVal(ArrayVal($response, 'Response', null), 'sessionConfirmationNumber', null);

                if (!$this->_dynSessConf) {
                    return $this->checkErrors();
                }

                if ($sensorDataUrl) {
                    $abck = Cache::getInstance()->get(self::ABCK_CACHE_KEY);
                    $bmsz = Cache::getInstance()->get(self::BMSZ_CACHE_KEY);
                    $this->logger->debug("_abck from cache: {$abck}");
                    $this->logger->debug("bm_sz from cache: {$bmsz}");

//                    if (!$abck || !$bmsz || $this->attempt > 0) {
                        $this->getSensorDataFromSelenium();
                        $abck = Cache::getInstance()->get(self::ABCK_CACHE_KEY);
                        $bmsz = Cache::getInstance()->get(self::BMSZ_CACHE_KEY);
//                    }

                    $this->http->setCookie('_abck', $abck);
                    $this->http->setCookie('bm_sz', $bmsz);

                    /*
                    if ($this->attempt == 1) {
                        $abck = [
                            // 0
                            "21CBD6E2A0B9086B465BA3A49D71E576~-1~YAAQUg80F09C9RGRAQAA33SCIgya+a86Qss8ZKiBDdxHnPKtEfsV+v2f1LRtApa0vZNzeVow9gs8JXQgEIA40WI0tTA87Mnu0dc935WtrYcVsOYrSHXWL1c7sHOQHD7tqlSe3tb683PHPaH7xGjDaHj/qBm0LBQ9cYyZiSUoVmEx9C1rv86/IF9e5IcCKcU46Wd8fXvLPzDG0QBfoBbBx5fi71SqYL7PXSxvcaq+ZyECP4oxR+pw28xlBL074boTid4mbX6V3I9q43upXh5ONuiwipBX0lycLFaLb9PpovcbyMSY8ETWW+9gyXOZyY5CwMDj6txdDjcrTPUnuAJ7cI63xYuwYdWvE3axIo716hvpdJyJ6jGDdAiLtqaBY5Y6SuN96x4F4voPMQmayKJLZsplhA==~-1~||0||~-1",
                            // 1
                            "F967DCA11FD46070F25792053D7C2290~0~YAAQUg80F/uZ+RGRAQAA2naHIgy2xDcKWRNf1BB4g9dwKwAunz2g5E3CsQZMoyCNwoOCJGDNLdE1JKtX6hH8TR8EM+5/Iz/UeTLtYj168/+BeR730y37y5NpcfJti+dd3jehEyw6aWQHQpugcAW3GX29x17NnXeSrfhksFzDidZe9BwITXWOXJrU7NxbRI0Vqzd4zFMeGKrUHVXEXnLSC1V4wwScpj42pK/H0uV3ANw+vKIrrz6f1LQuLm6HZK4odgMUtgHi3ZHdY0A5gbBhHgH6MzX5IfRL9m3ji9FqIhMSsNUrJqHuvpqHgQqu38J9dYlSfd4pYPGYyI0B98b24KsZVuLJlE22OD76b30GFmRoAxZ7rv8p3f6Ll/PsKOCBvo8opwQSj3lH4Al4h9zEsmkqsrgTWKPFXKaXNus0sZJZfA==~-1~||0||~-1",
                            // 2
                            "A3E0572D9DD8F3C662BCCF6EB48277A3~-1~YAAQUQ80Fz170wqRAQAAX+KBIgyKMvAGfEzdJg+izVYkK753XLtHWMFEV/igKw+VqlCEHnR04X7Fh0JIKggSZX7OsP1k0/iTfq5J7tNp7VTLGsa24uvkfFqSPwE946kcRunOWjQWHedI7nPAqDcULf7BoQhFkAcIjazQUcrgP2tVWmrxEwjEpKUsO0q0L8AK9d2lwFjEtcGmtjV2FvnRBI+IetadX8hIt9qq0rbBvchUqNmOsmqyWZ0ZzJmBnYjHtUhsACnfveIHiqW32jKXG1G8hBRK5xKVxCoKVIhFTx5obalv9/tLR0Tp2Wa4eMpXo/qfksEXy3w1CPq69F+L5zlMJiJt3ilcifuMdxh+f0SutJCFXEA1bKcG0OXp2hyuKY6l6HCFKFvSrMwtVZsSEdcD2GQ=~-1~||0||~-1",
                            // 3
                            "EDCA81EE475DC574E4076A3F1CB481B2~-1~YAAQpBDeF27G/SGRAQAAjG+EIgyaqPp1tNn1aKjWqSz8zDEM53LZzAlmcda0p6Rb1y8cfAEGc256Shzg9Ff1Md64eMOZYxUxTaGAoF5xTqbXPBL3aYlJVv7sKGURxNv1n8lJIa5U4a5ag4GAg51yrM0VWPf8wo1SyjZPR34Z65qTsXOIuOSJ0NQafEf112plqkL4MYluQ7EPxYQWAMOCGcoO7T4Ng4IzJcXsdGYUZsfZATFrZJlr1rY+an6k+f3MjFLRwAE+96yWE23AZ0bGvvntpXowdWPv0bHYjm63B265vb+owN3944O2ep/OSkrHQ9QRDTyL2WhqKZKescUQbRBtQ8/dc4is8LSu1Bsp9sWK5BTmf7Y13u+OWHUCjAloYP2mm/1XIMP25SH7ndRuVzkjhA==~-1~||0||~-1",
                            // 4
                            "A3B72CFD4C342936F263A30C28038631~-1~YAAQUQ80F3Xx0gqRAQAA9S+AIgzw/KFR0hqOu4mG5XodACwwsDiGnAV1pUVcUXo2tvwzkfWLldJ8HcV9ZA+gPogb0wCYjwlC8oInDxlXVqOMcyn6ElZ9IKavX0XciH1RX04uONBcDucWmQE0VqYzgILbv8NP+pi6uUTTcq+Rj/ysWa4Yg7Vzr2j1idP/VnYyUmTGu6dj8chETOuYssIJv4vm2CV0rTl2OgUhoIAIMPIRgY66DYYRufC8hDuTQuligVb5YKpCnSZnIr7fhGf04q2+Bx+ZBzpasV210JASSVBHaaOsXMP0oMsOfuoM0xNrxYr+H/KW+gw1avDx9DlKoBZSRroztwRAhez7bEZZEewXUrUYFgnZTJOecgl6AwVY3w1bocskyEW+4MOGsQkOeWen+g==~-1~||0||~-1",
                            // 5
                            "AB3E6E8FB8B6EB39A5F6BB7EDDBC030B~-1~YAAQUQ80Fz0M0wqRAQAAvYaAIgwx8Bp4SJZsxVqQcuFMshc7b1FKbQo6kAIDn2WYzeWjm4oI18WeQGAc7PYv0TB3Xfe+GpV29/KxwBf3rqkfeiS//9a7WtHGvjnKD4rxEpq+LhWpydcjfCfz2dexwWO+0BJ3GaCu3hHcA+/VHck4TAP1PJ8D7Zl/XTtV9NcvgB6xE9zP3p2PJnjBY2vFmls0+zM1/StNCQNuwLuBGOVziJuj+G7HEOkIbxNsd+BU2uZ/1MunK2Hh/e+ZyI6Qyw+FLwyZww5B261sj3iMHZzBr47SriNpRg+l7pmtw22p8Jbg1CBWKtaqJbxaTRFTCwhoeyEFl1WTU83TgCzj+Iem4MYEV6ViocP9qzHD2yEyQ21bJJ4+0fbOk2U7PHtC4kljbzM=~-1~||0||~-1",
                        ];

                        $key = array_rand($abck);
                        $this->logger->notice("key: {$key}");
                        $this->DebugInfo = "key: {$key}";
                        $this->http->setCookie("_abck", $abck[$key]); // todo: sensor_data workaround
                    } else {
                        $this->sendSensorData($sensorDataUrl);
                    }
                    */
                }

            $data = [
                "login"        => strtolower($this->AccountFields['Login']),
                "password"     => substr($this->AccountFields['Pass'], 0, 15),
                "checkout"     => false,
                "skipFavStore" => false,
                "skipSaving"   => false,
            ];
                $headers = [
                    "Accept"           => "application/json, text/plain, */*",
                    "Content-Type"     => "application/json;charset=utf-8",
                    "x-requested-with" => "XMLHttpRequest",
                    "Referer"          => "https://www.dsw.com/en/us/sign-in",
                ];
                $this->http->RetryCount = 0;
//                $this->http->PostURL("https://www.dsw.com/api/{$this->api_version}/profiles/login?locale=en_US&pushSite=DSW", json_encode($data), $headers);
                $this->http->RetryCount = 2;

                break;
        }

        return true;
    }

    public function Login()
    {
        if (($message = $this->http->FindSingleNode("//div[@class='errorMessageBox']"))
            || ($message = $this->http->FindSingleNode('//div[contains(text(), "username or password was incorrect")]'))) {
            throw new CheckException(trim($message), ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//div[@id='nonMemberRewardsZone']")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Loyalty program disable
        if ($message = $this->http->FindSingleNode('//div[@id="loyaltyInactiveZone"]/img[@src = "https://a248.e.akamai.net/f/248/9086/10h/origin-d2.scene7.com/is/image/DSWShoes/MYREWARDS_tab-DISABLED?fmt=png-alpha"]/@src')) {
            throw new CheckException('Loyalty program disable', ACCOUNT_PROVIDER_ERROR);
        }
        // Loyalty program undergoing maintenance
        if ($message = $this->http->FindSingleNode("//div[@id='loyaltyInactiveZone']/img[@src = 'https://a248.e.akamai.net/f/248/9086/10h/origin-d2.scene7.com/is/image/DSWShoes/EDW-msg?wid=857&hei=130&fmt=gif']/@src")) {
            throw new CheckException('DSW Rewards is currently under maintenance.', ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        switch ($this->AccountFields['Login2']) {
            case 'Canada':
                $response = $this->http->JsonLog(null, 4);

                if ($this->http->FindPreg('/"userStatus":"LOGGED_IN"/')) {
                    return true;
                }

                $message = $response->Response->formExceptions[0]->localizedMessage ?? null;
                $this->logger->error("[Error]: {$message}");

                if ($message) {
                    if (
                        $message == "Login information is incorrect. Please check your email address and password and try again."
                        || strstr($message, "For your protection, we have locked your account for the next 30 minutes. ")
                        || $message == "This combination of user name and password is invalid."
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    if (
                        $message == "Account will be locked with one more invalid attempt. You can reset your password anytime using the link below."
                    ) {
                        throw new CheckException("Account will be locked with one more invalid attempt.", ACCOUNT_INVALID_PASSWORD);
                    }

                    // todo: need to check extension
                    // UPDATE PASSWORD
                    if (strstr($message, "Your account currently has generated password. Please change the same to access the website or contact Customer Service")) {
                        throw new CheckException("Welcome back! We've made some changes to our website since you last visited. We ask that you create a new secure password in order to access all our improved features.", ACCOUNT_PROVIDER_ERROR);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                break;

            case 'USA':
            default:
                $this->http->JsonLog();
                // Access is allowed
                if ($this->http->FindPreg('/"userStatus":"LOGGED_IN"/')) {
                    return true;
                }
                // Login information is incorrect. Please check your email address and password and try again.
                if ($message = $this->http->FindPreg("/\"localizedMessage\":\"((?:Login information is incorrect. Please check your email address and password and try again\.|This combination of user name and password is invalid\.))\"/")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message = $this->http->FindPreg("/\"localizedMessage\":\"(Account will be locked with one more invalid attempt\.) You can reset your password anytime using the link below\.\"/")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message = $this->http->FindPreg("/\"localizedMessage\":\"(For your protection, we have locked your account for the next 30 minutes\.) You can reset your password at any time using the link below\. Please contact Customer Service at 1.866.681.7306 if you have any issues\.\"/")) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                if ($message = $this->http->FindPreg("/\"localizedMessage\":\"(Generic error, unable to get errors details from the source initiating the error)\"/")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if ($message = $this->http->FindPreg("/\"localizedMessage\":\"(Sorry, your Account cannot be created at this time. Please contact Customer Service at \d+\.\d+\.\d+\.\d+ \(\d+\.DSW\.SHOES\)\.)\"/")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // Login information is incorrect. Please check your email address and password and try again.
                if (isset($this->http->Response['code']) && $this->http->Response['code'] == 409) {
                    throw new CheckException("Login information is incorrect. Please check your email address and password and try again", ACCOUNT_INVALID_PASSWORD);
                }
                // Sorry, we are unable to access your DSW Rewards account at this time. Please try again or contact Customer Service (1.866.DSW.SHOES) for assistance
                if (isset($this->http->Response['code']) && $this->http->Response['code'] == 403) {
                    $this->DebugInfo = "Need to update sensor_data {$this->DebugInfo}";

                    throw new CheckRetryNeededException(3, 7, "Sorry, we are unable to access your DSW Rewards account at this time. Please try again or contact Customer Service (1.866.DSW.SHOES) for assistance", ACCOUNT_PROVIDER_ERROR);
                }

                if (strpos($this->http->Error, 'Network error 92 - HTTP/2 stream 0 was not closed cleanly: INTERNAL_ERROR (err 2)') !== false) {
                    $this->DebugInfo = "Need to update sensor_data {$this->DebugInfo}";

                    return false;
                }

                // For your protection, we have locked your account. Please contact Customer Service at 1.866.379.7463 (866.DSW.SHOES).
                if ($message = $this->http->FindPreg('/"localizedMessage":"(For your protection, we have locked your account. Please contact Customer Service at .+?)",/')) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }
                // If this is a valid account, we've sent you a temporary password. Please check your email.
                if ($message = $this->http->FindPreg("/\"localizedMessage\":\"(Your account currently has generated password. Please change the same to access the website)\"/")) {
                    throw new CheckException("We've sent you a temporary password. Please check your email.", ACCOUNT_PROVIDER_ERROR);
                }

                if ($message = $this->http->FindSingleNode("//div[contains(@class, 'inline-server-error')]")) {
                    $this->logger->error("[Error]: {$message}");

                    if (strstr($message, 'This combination of user name and password is invalid')) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                // no auth, no errors (AccountID: 4686545)
                if ($this->AccountFields['Login'] == 'steve@arkayz.com') {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

            break;
        }// switch ($this->AccountFields['Login2'])

        return $this->checkErrors();
    }

    public function Parse()
    {
        switch ($this->AccountFields['Login2']) {
            case 'Canada':
            case 'USA':
            default:
                $response = $this->http->JsonLog(null, 3, true);
                $response = ArrayVal($response, 'Response');
                // Name
                $this->SetProperty("Name", beautifulName(ArrayVal($response, 'firstName') . " " . ArrayVal($response, 'lastName')));
                // Status
                $this->SetProperty("Status", ArrayVal($response, 'loyaltyTier'));
                // Member Number
                $this->SetProperty("Number", ArrayVal($response, 'loyaltyNumber'));

                $profileId = ArrayVal($response, 'profileId');

                if (!$profileId) {
                    $this->logger->error("profileId not found");

                    return;
                }// if (!$profileId)

                $headers = [
                    "Accept" => "application/json, text/plain, */*",
                    //"Content-Type" => "application/json;charset=utf-8",
                    "Referer"          => "https://www.dsw.com/en/us/",
                    'Pragma'           => 'no-cache',
                    'Cache-Control'    => 'no-cache',
                    'X-Requested-With' => 'XMLHttpRequest',
                ];
                // v1.0 instead $this->api_version
                $this->http->RetryCount = 0;

                if ($this->domain == 'ca') {
                    $this->http->GetURL("https://www.dsw.ca/api/v1/rewards/details?startDate=01%2F27%2F2021&endDate=07%2F27%2F2021&filters=offers%2Ccerts%2CbirthdayOffer%2CcertDenominations%2CrewardsDetails%2CprofileSummary%2CcertsHistory%2Cshopfor%2Cincentives%2CpersonalBenefits&locale=en_CA&pushSite=TSL_DSW", $headers);
                } else {
//                    $this->http->GetURL("https://www.dsw.com/api/v1/rewards/details?filters=offers,certs,birthdayOffer,certDenominations,rewardsDetails,profileSummary,certsHistory&locale=en_US&pushSite=DSW", $headers);
                }
                $this->http->RetryCount = 2;
                $response = $this->http->JsonLog($this->rewardsResponse, 3, true);

                $response = ArrayVal($response, 'Response');
                $rewardsPointsHistory = ArrayVal($response, 'rewardsPointsHistory');
                // Balance - Current Balance
                $this->SetBalance(ArrayVal($rewardsPointsHistory, 'currentBalancePoint', null));

                $rewardsDetails = ArrayVal($response, 'rewardsDetails');
                // Or 86 points to go.
                $this->SetProperty("PointsNeeded", ArrayVal($rewardsDetails, 'pointsNextReward'));

                if ($this->domain == 'ca') {
                    $this->SetBalance(ArrayVal($rewardsDetails, 'currentPointsBalance', null));
                }

                // Available Certificates
                $rewardsPerks = ArrayVal($response, 'rewardsPerks');
                $certificates = ArrayVal($rewardsPerks, 'RewardCertificates', []);
                $this->logger->debug("Total " . count($certificates) . " certificates were found");
                $this->SetProperty("CombineSubAccounts", false);
                $i = 0;

                foreach ($certificates as $certificate) {
                    $code = ArrayVal($certificate, 'markdownCode');
                    $balance = ArrayVal($certificate, 'value');
                    $exp = ArrayVal($certificate, 'expirationDate');

                    if (strtotime($exp) && isset($code, $balance)) {
                        $this->AddSubAccount([
                            'Code'           => 'dswCertificate' . $code . ($i++),
                            'DisplayName'    => "Certificate Code # " . $code,
                            'Balance'        => $balance,
                            'ExpirationDate' => strtotime($exp),
                        ]);
                    }
                }// foreach ($certificates as $certificate)

                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    // Your Rewards information is unavailable. Please try again later.
                    if ($this->http->FindPreg("/\"genericExceptions\":\[\{\"localizedMessage\":\"00001: Bts Rewards viewCertificateHistory Error - INTEGRATION_SERVICE_RESPONSE\",\"errorCode\":\"00001\"/")) {
                        throw new CheckException("Your Rewards information is unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
                    }

                    if ($this->http->FindPreg("/\{\"Response\":\{\"genericExceptions\":\[\{\"localizedMessage\":\"Your session expired due to inactivity.\",\"errorCode\":\"HTTP_409\"\}\],\"formError\":false\}\}/")) {
                        throw new CheckRetryNeededException(2, 0);
                    }
                }

                break;
        }
    }

    protected function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The requested page cannot be displayed
        if ($message = $this->http->FindSingleNode("//img[contains(@alt, 'The requested page cannot be displayed')]/@alt")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The requested page is currently unavailable
        if ($message = $this->http->FindSingleNode("//img[contains(@alt, 'The requested page is currently unavailable')]/@alt")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //Site down error
        //# Sorry for the hold up. Great shoes are so distracting!
        if ($message = $this->http->FindSingleNode("//img[contains(@src, 'sitedown_error')]/@src")) {
            throw new CheckException("Sorry for the hold up. Great shoes are so distracting!", ACCOUNT_PROVIDER_ERROR);
        }
        //# HTTP Status ...
        if ($this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status')]")
            || (isset($this->http->Response['code']) && in_array($this->http->Response['code'], [500, 503]))) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# An error occurred while processing your request.
        if ($message = $this->http->FindPreg("/An error occurred while processing your request\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg('/"formExceptions"\s*:\s*\[\{\s*"localizedMessage"\s*:\s*"Site Down",/')) {
            throw new CheckException("Looks like we're experiencing some serious shopping. Don't worry, we're on it.", ACCOUNT_PROVIDER_ERROR);
        }

        // maintenance
        if ($this->http->currentUrl() == 'https://www.dsw.com/dsw_shoes/user/loginAccount.jsp') {
            $this->http->GetURL("https://www.dsw.com/");

            if ($message = $this->http->FindSingleNode("//img[contains(@src, 'site-down-')]/@src")) {
                throw new CheckException("Apologies for the hold up, but something big is coming your way!", ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->http->currentUrl() == 'https://www.dsw.com/dsw_shoes/user/loginAccount.jsp')

        return false;
    }

    private function sendSensorData($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data url not found");

            return null;
        }

        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            // 0
            '2;0;4474178;3556917;12,0,0,0,1,0;i_LES?#GSI%+Z@R+}~=v;>CAWP_cuL8et:&P|3M;>-DlHO8[}bZQ#_ip{-Ya.$a;03[}UQmifqc)>E9?{1N&-{)+FQnt)y|N|32p:7lnkzdt]J)dnfNb)q1H^c4JUEZrrl.[63*)6xyu`R)%LH4dr-`oDX48^M&OZ5Q}hv:_P0`i3mqXm1wSfOm*|$Ud)72u$fvcOhmty:9WVZ$:u5wi$1l/f$Mi0BjH7NxBNAJns+@XYcH9Z|`]m/PpGr)k1)6-Rl5$O|}>Nz#KJcT:c>7z@1!7,P2]*=i[E#OJ!FRr*!BmrcF;k|5%`nCB:WSAl%OTW1GTQZb:a04bzE.#hy>6X![*zTGpPZ27LwD@Ro(PeGiIW~i}N2%3d m?>bb0$<hy-g9I&s&[-k*]x<DW4bt-d|j(H |a?n;GdD137nbg~bqF:e^$mPLE:wZ@42%eR-M{-|?uuHfZ&u<#Lz*? p,E!YG4w- sg?$.Uw,@Le/@4$8Y,Ol1<k6A/l}PH@ONT>.I`^Sc)Y(vTPzvT(0YRGh,yKTMW_XMVC Bzn^{=.R=,jpO6%>ZVNyG<utq)399|.hCJr_2]8qobIC9xS*PJF&%K)>zUOebx_f,M5A_cH..v!ebEo:g>;vJG=8E^a/8K:1j+RH ^D./x:_3ce<lv&j87 1{B_{PmdhCl1vxF@e=(=7@4 Q2Md7q*|>Gl!TZ~(6nLWvS. |4%L3OEIzUz/uM]A/8h%No )A(j$;,_daSp-/E>I q+M{+*5W|:nB^5.B5$A]<7?7WKz)G:I4&0cSEWfCN5M2-$+L=0ZiZFP[#)Ph#{>5}=i_WU}UoW a-bv30.O#WdjxNe}:qs yp<YD:_Bt3*`>c=_~muUvld+;U~2a4DaYK#Vdc+9w[E|^-w3TIe7qme2Bm%`!Oo,03mAFKs9yUN!oJ5Q$)CxKNZy7h/h-kwKG]9u!<m7vSK$xZB(MS~C/<;h}k-nCtJg!+v=gnAqgD80#NJ~D91yosq+:duCR%V:?;?w-4&(G#EQ~8(II8UkJTHL$gbEh,gl3W.d_5)DLK**?]F=sYfpbrr@v)dsjU>;H;q`$&T@VKIqU1?kmV)RN`6iD>h^D2hzxmq5m<YUThM[7+eKKsXwnlCYB@?5{$8]q|D?#(Y]m.Q,6-G@yzp.=m6<&!U@,#BBQibn?j+Rn3RI(c@wcQd~9sIp]^^TcvQ+P1R.wR!?: ?R`ss;7{&ln%1zAC PtRr_`gk&PT3Syq;Ca V|6<f8wfO@7YMDfyWl<$ZIZ}5sXxT!@8w_N1uNLGr1CIs89UIhOl5{~|q<IkM9N{PS>@[`?agzqO:pr@o`x)0bc:bkSMJPl9gk7L2 aXtagnvdNBrF(eEh0M>j9zBmy3gryk0pmobrvCuv?;-,mbqu8[p$-]VQwW6xC}1.0*i>GdxRNPS5{vl%P.sgB9NLTT=s-<hk;hQ;Ig.kubz(*/hNII#C{dW,)FAh2-9sLa[}Bl.{3bvi%~{,z2h(pJ5+[P;c9JZ:u,6P$<}_+g-X[eQ>JL0[H$2ii@8wNbpYA[Y)P3]R%i!Vf}^GmsL6 ecVY<-hQXy[TrxQuj:3RDmLZojZv~mSC77)<!%MF9{aOq/k=1cXm)H/GoWS}3wU`M[K]0T2!HgjWsAxLK-{X_,]*MB7ixx_hk|m%l{[]`KAHqw~JF6<~kY:b$)-^|{Gsc)%A,@?+[|9v`%nKOCI:|eW~SNi<kXRkj|2QLQNL#Ym^5o`@9)8/AqxBzs.nH93WNu*XWoYX5r)1l40$-!;&DQN(n30s8}c|8c|6NimiMtFS?joZfa u,[{O-M]F+:tbb-P*9XSfNDQiH01&8<]XonvI1ElfEPS~.s6`r<KdO(Hk]BAw9t(.NR[E%EBaD Q`VE?EUD5e,Ni/B_nvYPymy9h4?H:n9%VXIcLgxo{[B/[}7f<w{Sw5+d1m{-SZy!R rg?/29qtsXVQtWe1_^}:[UsqdJuOC e4c^gu<&GpldM& w5Nb5h-ou:#6o>MDq?|SG2mL<M/#7TRBJs(k0a._sGJM*lqvRz]A#qk<)I*<B(c XDbArJ!Pm>6[@~->oLure]L7)N4-O6y:0stu;5U7U4@R,*>Q!^5:1+,wa< ,U.&@N,>0vc$zKx(<<)JKDymNk_gE=|r}K3(I[9WSgvV;w[l*wf:WEyooyuqm|S3d+(s+NAb^-.ua8t6XWc}>Bytx/(=*6[r-i^a#?,lR=O.vYAj&7<[*]?~j));;R7)^77/[6{RV<H-2te!WWf:[`>[b!$CDbJ>tKVj4QXq5#:,,pV,j{|O,,|56do0Fa@)p?N[e)$V`KoMYV^dP_4R)/)dO#Oy-GE?(f&?v?E>LBYadTSlu!=+lkb:zn&Z',
            // 1
            '2;0;3488067;3294774;12,0,0,1,1,0;;^#.; ?5hJ4^=kfGX;]M)8-i@,R:q|E%.?Qp@i]cr| 4nVIy(yGWgMP [o7qO[]y|P`8M^izYoGzN.m}1Zu1gLG.t+K0s*)]d<]5z]jTE$e2vP)U%w=vXA5wW7oQF4YxvXyk&YpRYs~Kk#|&G{au@7F6$@aVKjoRnUqCrIOqR<c(9~4tDH7EH+15j5kh$is3bJ!p=~([C?vBi*%7X1aPHO/YRwM^QB,p7gL8jBb)9(Wjt=JL)=~jjuT-7bG@=FmO<_J1pz*XfY@y$R3Y0KX@a2Qe`0||1Ww`=7ygH)I  7kLf^,8!H)P-Imm$cLNJ0aN3 ^[@(h2K=Fu6ADv]5u9!z!,NrXM9aP-tp9F=.izNaWGW46bErg;EDx`C*:,)h:]@P;;+ Yq<QeKiyS$W.np9y@{WdH&l>cdLmq%>Y:DIwC[q<MRC4kLpsL4,#X1BSN@5!8FE@ch2scX[My;3m/zuGWDS?w:[4|$:WZmofTT}zFEPC$y]L%?3(RvJ$O|8yp+{`CmY.{&!{&E[7XeYM&LHQ|bs3mi56,~>ej/:TA?~R]={SJ/]ET.;+21~-UlXVifj/E/t4JN6It+fEC0;7}MZ[9&XmY_;CLnC6Z&pFGVG_,L5xs=mymz{7|e9j0]D@dw.N2i[RTx70-wZK1-Q,ck(&chVol8vB0stOHQV2t]@VDkfL(Poy:v]aMlS,*1$n;h,w8C7Dgi~5E|BTSFol_mlFPe(U}|y6NZV)v:b@Lfcd9fUIal,I -hkvltF:k`&|f**n--fA2y&N8x%5[4.5>4x!urkE$QuXn`/wB=fwCDm[z*YNT9I4-#(O``ig}W_y!,<h^q%EYffmBUPM5 6~$$-eay)%JN+Q or[R.{>~2zxl,(]PStZ:wa^dNfC|h%]BWIo|]qCS?KOq9;yF&V3jX&,an?YgAX0:!&wz_Y(u+]?-yZ-hA~JR&p2UPcMq)jXF<Gr:./n:g<*%HW~gQpzKT,pBGO}e$N=7@uSf4l>d/OLT8]7-cZ(8zVAf)(7MwLYPU?v%@}p`&,YX-, oeLPL:|f_RQ^:>EBQOdj&0>-PG&eA^GnTfHLKC!0L}z2v8B$SCnt.>X:@nbXVJeVLKI^9A+mR33s=w j5mwnn/7>BI|$Ci<R,JK?j{$K4we;^D0>j0DWkD{:HypYu-)eAA%Wd[JI;G4_5LBs;MPF@{dXV#xKRH6oxKfj-(!:o:SQry;D.X=nO/[ BDhzoKSO+6_8!C0Ww&P *Ru[=xFQarLjm/sAacE.S|`Sh*K}l}xfXdcOceHd!,ZNc0$m6%bg7![L;GA]Y(]s*TVVP+U!2d/}tCsI^newPSceKr!_NfQJ~}/12]e!zrv4yE7|EFNo)ULwts *!&B>Fya, J;hC!(y55?$cuf_37U%#};Qs]s[cs2|/x8!oJZFhYO;ONlazrYYq1,^,lR|y p%J}lP/`X )B4K6!+aN@_n<^yY[@lPNnXy!+bm&;`kGaq8j&q{XJ*w}RdB9c9QYfSs;162<NwB6oY-lJs@YXo2O[.C|4?A4&tmv8{0a!3.D%jBVZtUcY{hqOB -yPjXIsUb~ze*&pp^ ZYN]4hrkS`H>vf.Lb`9RN?L!fa,,js NY|+PZ$Nu?+#son7}m!0&tz$)N/##U!=[;}XvQAe}e/~,yD4q+0eP+-Yd.nRj$Y_/<K3Kk.TBALg8=DfIff Vdau+yFp CLQ:m[]aenHbqVp*.g=QK}v^es8Sg#J1HMP;1FU`ab`}>iiwr}]Lb~+>SR~G#O3Nq$9wo1.N-M*L[|vB>b7)+mRd`s&s>J/D12K)Yl82#%bDu,>5`oXO|x?5Id>a?k..Q%xbXxf>8h=]Qfp1 !nh?m~j%E&N8`ITlv^5{P{3,S4(>{/_vL%/=B-2e -4{X$fS|D$D)]^T~*`-dRQh9k.m@V:$F5n@3&XHrB}S6N*z^W8:HjWV*V>rnVjErW2Fjm!9a2M^jn2ic&w~n:Z-_vXpU}qf}w9`.vcJA%]G416pi3C3y[.nz`lSEUwXN]`B.(EsP2yO~$I[4xl$#) 1rwI8_;ZBW)v>atlC1v:mZzj-g7QSP8 R5Gj__~;Ks#L nxIa,:e;bwMC1`uTSD<5JArj-[qqUg1hc~vecS(RP+wC.?S=M6pgK5BR+,AA82;:[rScMd1fjs{^[I::l&#J@_K[4>o!Yhw1kR[~d6-oz!_-QZlA!e%EIIbX(AXhJ4:jO$8^W0}h5a?xs{9(D9H.1kF+aC!h~IV0:1nwust`I:wl=8IOmZPG:=d%93u-09b?J~Bmjta%i*(;FBV/rxk5;6[~C}n[AS-@rFR(3A3zAQI=26N?seIFXz)Pt?hwFGnSny`3KrVR[C( *)Gp*=@cftLy%J+,BiV>CPmbSszqqecM@b8e|=9ps?=h@DT0;r}/N8bA_&_#Wz6{>.z)9c!X|plyr4zIBFta4$d6f&0hq:H3`zFeCWDHqDpG[uoZ~d<2m<3/By[:^WTPQ9d(h7q) =8v$)2EvN`Y4kjk+Q{ k QMj(eV,^JV1{I)#*7QK1wuc)01T2GdK:GT)C(nLy`+anLcVqyh92q!IcQZ~lV@&n1@HD*j4zuDpEFCmXbf#6R,lz`gz%E$aZU4`I{g/4$U4NEq[JB%b!YvN ldN=d2]rDa8WO+J(TlEuuu~KA[BJN(MikxKIzHy<BvUO-}pvW7{]ovmW(#z*T*>*g^vFmT2O Fh$z*aV5D/w*2luE_,F?9|5SBA9rxTW0eN<N.Ej@#x>apwiNR}Ma-?Df&o~V1GO0BqUtGWUjNL(aW$fB}Wq69] 7ZT/2S~}$>6Te[#zbqz]BMe(LKlfsRfAYKm*rTH(w#cO~i}iT7h',
            // 2
            '2;0;3485744;4469316;10,0,0,1,2,0;0IainO? $@f`5LY2t^y[Q<C6^XpZ$P@&LQ%cjXAQ/FGB~ySJSu)LS;qS^Qa9`Nijd2W.eYG5WwL&Al#xAkZABFZZ~ZmV,pA@Dl:tYN:9}zcIc<<7jH<};$=yC]D~YGdoh8eT@5.Bu![RzPV[/h`Wmg2?Y[0dl9t!8j/+`m$usk}SzU vX##t9:px=KjYh@1+:~PR@CD]PVEB~R_*sB?Kk!LZ 7[_X6 9^9a|Srg#42$4oLHlf:xg4Ur$65E7#?Pd_V$4s;;f`sZ$7@|PcTi!;A*pn__|T,dI{=IBH3NwT2kMRF:c`nx>+-qicv=H#ObO;o.F1^|O5[,,qgYNLQF+v%QaX~mN?d{F~S%uhb_79C~yysjr*R6.6_2Nx0vn=qs`!T5E^~Z{~sSzFPvv?#)~[y=m[2) R9oU*gM1%^LBwX#M4<rfopP3,*YRIL6uVN$r-;A7tGNXY>a+p$>v(PvmOZj!JKXo-TCsxJX^:k}lM ayxptvjcW8$XT0YisAe,N7#<eL4<Ggu^-s{#9Ah6LCwaPz]29(A(Bjf3T;tkha-l?3q$L%>eQt O8Io&BY>-fTV#N!nAG6U#cKBoG)NdP^=2|0VzUk0j@?FUN5*6]N6M4RczG7u9zp!No4@I*2h:3A0Kt@r]`6S)Qmf;!*b wvpy13[NB[C}QSU&O?^&qnW^H<_]4CzhGFCRqX<CUB}!R^vDLfJ^EYTI-5g182%erRqF>YxlskjXOZvV$}kws*1{xwE|9~errhpuivG  vseCqK;kaVe4Q<[9:>BXzeKRRrRjKQaw#IYTrToJyT/BYf~r%C}L,r]js?M{kUuIP/lf$B8r&%0]J^ncwfk-ZE4Jb(J$Q2K@4R>.Y~{X}o3@lt<>S?u_#Cy8:AG+ITU-7_PX!J9-AQuefnIE^]E2MH@^&Ysr6eW$BoDs7tqf.(Y{T]/@w1(X;Xb$J4V`p%7&1(bsHw)$Ef*.QpYdHdT(ZmkB^C)mmG([!bgnA)63hjOlPq)h,/<y|QL2/eI= o4>hBSRL%8MiGa9hG {pu3xS{t^&QXRG3]l8Tntl|2624z8#x@u:Nc.CKQ(L}[`pM6*hId4h^p!R.r-?pZ,7t.!k-R:fgc_VrFl!r~gE7KQ)RqG-=cBu1IGAl$ EHFURRQOs&:s^X$H}RY=5,crdvoX}&]C)0)=6C@n4.K1*]Sbd3est`X::<pvTQqe^b:y{65~>%]#=9>4nvcRTLM !1dxv^)+w-?CNt#19|j.A6<<7y%oU#%c/)dq$]^h,mry~w#iZeG~*g{)Hg?8@Dxk4;KWwLad7Ra%FkHp5o(a%fuEE=BL`QucAEvm*mOm*Oi1^t-_VpC9_mW751H#gk|<+fAc%y_|p9a8N#Kl#Cq-,N6| *9)kl--8|c*KM:ZP^#V+k>t ?0~QphXdb{2dn-3C1+Pw|a~Ah0r:w&v%LF<fl6u0@`WLT+*)=0$4/!KV>b-!W0w1PR;CER9Zn;i|Gi~B#3^m84$#_*?ZMrVrB<<DRWRjyVyVkQWV&wX5;Hj,oLD{-C/M$6;0RLX ?fSo<udBEF$,+@[|nV&$|q5i@i[4z5MS s>8Z4j# J#C!%e1]pbJFl%S):TAd7=u>xQ;ITsIOfN<RdO,%fwMO<:sQ|xk$s/^)y,As/f8s*%r1U,[:&>PPEczmuf8*JSCob1:qd8`/D=ATo^@L;#IE@L#|1WZQ&+,B?o6XIvv.GG:v24to}qq@)62m;g9f(n8phQP?EfYtLAH{G;.>-QLpm9ag*jmlQ^R4+0t*tWAj>Ajx]D;OSv(D:ZSN$Ybp2Z`MKU<@?`yE{*= [ONc`5Dhz &~WHCN=jb]MIZTdIG![K(T?GGxm<:s0IKL  ,.yLLI[Nik}zeB5JZQ 21$Hn94;[2MeBQ~]rF.QONtw#&k]]Ix]d/EW%O&}opGiis>+q1t2:gr!u/s`$HC*:T#7yEzCm|.~i/`NdOMn&@6zy/r_WZYAYwROU|b(Xq;uz@?iacBCW~~A>*l`A_Dew-m70Y<l?e#^}I!qv/9n|.s9Sa;c#}_d/L6aYq&A~3lqjRy}x0>6T0(#>R|[A&;uSft1od_6%K|_dWt8>i,J5B/PM7^43N4s8$;9/`B:)gM:T0O)?L!G6pKgyb#mB&,NpY{64aVS/h=,R=n;<$AZX1kUURRnY>rkNrlhY,P3T`FRpk]oqP dI,d_{w]zab#:3NTGTD`,,iJj.{s;H9KHu$U%vRf. u/PF&f}%x5!-]Jl>5,Zgk${UR2h^;?jT!?fU@',
        ];

        $secondSensorData = [
            // 0
            '2;0;4474178;3556917;9,16,0,0,2,0;-y4<WF{_`0c[-C@cwsDo^@Cn(6iWpM@Y6</PA7,=!O<OK$nz}%Vx|bifw-;=L;)q027g)(ce[W=X|n;gjM*f*p-3!Pd>bZyC!@wr69wufE:^bJ)bkfIyAVtx6Ea@SGS e0_5hhhl[q:.=T<[%<QEO_N`ZC?WxqzcLFO16(}.y>97XyT5N2xN[^+Br$Dzdyn[LyB)qiitt7BVRZ/#E5E/?Bs1c~Sp8;_?/[UaEBDfq+c&9mH2RudXq!JtLm@2j%aTcm9~ls(@Ju$QDWMGC8,~B1~7+G5b25d[My(%YLIg.Iz94[G;0os0RjC*Q!v8h*Hs*lQLr+K9S*42M -#a@{=WK0l&L;jUv-6K[KQQv`ReKbq5MM!E(ShM$^;2!a4(Ag|2c9FIr&o$e1]p6VO2iq}],GF?@zU=g;JZS,?Qh!i.l@%9jg wRd!MmnI94.hW8mg.vK<-X.k}Vs7DzCY=>KK?c]lnC7q}#WG/0)a34)FQzxg)e@{q=ny[gK#0 (SAmU*w{/)8].|pyZMvjNg(]KD1WLk.2w`1uGaY&& a83{-jL)DCoo7>-V9[..MFCnQJuDFMB4@xVG$RbK8-CZK(3aZAK`-W/g>z@;-Z{faoYhwC~a]G16WUSdQ_]Vd1P_%Vv%{3(KU*PfDtSw>`eA;awSx853.3gyMW6BM<u`hZ`jf_?(s*hpmjxz-#o#X61]k%Uj!@Hme%2EyE2&%(b<1@*ml5d|I=.~+G}b6)RQAn<<S[*rX]UBhqzHxsq=[~k:Iabp%jqXvzit/y:G@2d2T]KBH8Sjpnp!QV1W2Vuc@@bO,8.6!a.RT-j<x:ALt:@31|)D-uW>^9XZBCN0~N;Sw?B:C}HKCv<NyA#wGfD7~>s=@aFc&=r)[9W6g{:+bSG,LL$7Hh0gD4:C;l)>g$x|S?,1`6~MI!w?,G){TsOTZu6uuk-7R+MP2~&5`/rKD$![DmJTLE(hy9Xc#dXq7bnniBZqFdUDy~yO/bERXG)=B&p7Xe.j9q{%xgy?n8:*(dvh7(`Brk}/Qy9-[=yb2i{ad]?J.oPSaL`,B{GwCG+HT3dW]_FSzCao2#9B/Xag-Qdk*`Ox?68TnH!vcY9LhAd*$#ST[?ABVP`YZQ8f//>;zUC?zyhd9{=tSm;*YDQLhw+1.?uHpywqCDF=&-x}aHuHU]7_RG*4c2=]]N?wMab4e`@F%G(=|y@|,/+?B0PIBT,Uhbq:{$/HNJ*7Psx@.1c8@-oU~oNKg#DPry!e;ubEy{lEPv;?I+eHZqv=HVo*jh8TwucQU<Lot%%hQ:|3&0bKx5xYn&>W8<(B,s!pZqGkFUM#WRW6nybPhd]]45)-e2h|Q_i.*dQ42,5Ze*^17sKEj99s^O}H%H6[,7|ORrI9z1;e|Kf)2E}Y5jU|@c+[BxV(W$G7+c./!7C{0jwOPkfZ62?V:KS7-(i= HdXfHx!t6p co]mL@.cr}JN#rW6R.*JAb43bnR+aT?r`{4kNL!Zi{U+iQD}m*zQBf>L5rL]6PxX{d#k-U[aQyQKX2./*2?*Bl`]tU9Qb,K(-!`i!Vdz^@mQS+vhKULO2eITm`MioIu2fo]CVLS}dMq~lP>27(2&wUFXrbSr6cN+gT,}I,@oWev1~ZWD^4XG+yRGcg[o=x`C&$UQ%i&J>7f=w_akZt mzb_wBFMj:$J^,7ypN6c+~~W#v_:>e!74D8 W*60u+nRM4D;ekRI)8h/ ^Kee|HIEXNG([h^LeZ;>)8/Vvt<ts%eJ /JHu&V7jY]@ry*yy2$@x5..v}Q33J8go_+4Ur;Gbc*G2DX4ioam_x$%{ Z0H(wr9.]osPxNQWmK7Jv*((RyBSM?BPJ$s@@FDW*tr)sw9C`C)Lf]Gvw.r^8MI[B1E23N)gAW&Xa*}&B3%ErYq_VnVY.!L^#f1.Tm^jCfJyZKUV%hd<cHLQ^w:e|LAoC@p@1[.KJjM/k+*(I=;f]x%,1_pwt-hrpqI$`s7_;kR+vI}<tHkFv#o5qTsr%dyt+1jF0KqQ{WN+msf.-#0y2IJCXG/a.jUFAJ jxzEv[?0pk6O$j79 gzxHl:iMzP6q}^3MpmJ%/9@>P/|tetX1Diwr.p;2S5UVl26|;R)RQ66$%thB#-P5&8L&E/p_$?wW251}BX$sbRp:cE ZG$D3~>R1WM~.[?sSb2~n3gAqvilo~JvIa+F]8Vu!{2Rx4=tWW7#GN{Jc:/=!`hIR/E80zOMk2COIRA{=#hE6tB+l4IF0W=nsWkG1^4CPu<Q|Z03^Q1c@OqmOGVjzCj0)Hpy*F3Qidccvz&q?3brqn-0%66~t,@Z@xa>rrc.(nxgy{uid:w&1*NUKhp?Ov+&@?,q#2pL!8BJ][X]S.lPocm_!48l,Vu/Wu`i$;yXgUh3ZZ#B<tTn8,it{MF<T%IMw~kY{4 _ilt(JQogs5Lb=YBR`?3Ojq@|JZKDC0kcqx,tCYsnH/~,OIN?b8z1]@,DEYUvxF`bdi0>j&OXdQR{N<<XoBmYeu2o1PE3V/1{/5Zr9jaOWu`?}Zz6h8fP~ghTCeg_f{Hp/BA+hWRa-M%4j9G:2CMdHe6!>ntt39Cq^yTm$<F3[/^mr/3;`r$pRIzv7khM?&TNJm`CyT2N}vOB7nnNvfN;!+oWvdo)qYSl:gDoU0I+Wg#HMi$Y-|ar0]3;cEQvaOeuLVY/wW=Y6Qa]?^o&gk&v*x[`<rl,W=MrLdCi5rNq7~9)(UdGg>(,Y6# `FCe1Yt_G@9M< zK+|d3caE9z)E]*q4@}oo=eW-D(m*m>T%V@6):+`#wTem |AG/,(U(,%TudNF6BX#QL|#BBZ%1;GLvx//Jc7cI@& CV`Zh]jK[M!VU]X3EHGgFZ:7]]3kLp~u$8L*QeIqG>pJMkH}6.HVx$v9` {dCWfB&_cjv5`2|{]>RxD(@5Zt=V+/V^cH0Lh# wddf~9X3=i-?ZA]9=h!1sO2(6sTy]}:u-,tC=i.T]sd|u`R79ic^W_ySTAY E6(:LR;NxzHE~p[Tue[v*a.V$k0ay5!tBgeQci8Bs/JLiWAxCL4weAwP a2c$L<@q 1*DRx:pNv}J_c7>@:gK)Tmrc+m.H{$)/iTH,[IzflvEh@?y*!SE]3%VD}*&P{9@<OI/U22d0Y@DDT}.q}Sk!NL0&h/L)wk=q8,Xz7^7YbSv!?n/+',
            // 1
            '2;0;3488067;3294774;19,15,0,0,1,0;AGF*7~H5jG^n=omJYEWLL>9MsIdow|~7oam^)7]<8I/8v^N<Cj(U=*n&8Sp gUr>BuDzq;v#P@,wJ 2UF$@M^TQ)p1EpW[2Rp%5-:ot}YQNilNTbus9ubA]uU7B$s3(x9r@t0On~fezF-#) G}|uAsV+}/eWUeoOoUoCyJSuY^wP{249^uAAr|5wj6jdQi7Gek:u?.([OHlF2b&~Ts]y7OmeX~IWHp1w9&7{JP_!4jm2)r/Blz^l;Du<v:@@&;2t<3s@tW_hDB(J+dyA_Q!Gv1[ADyK#1V#c5@WC&WVRVvO!hc(sfv,N/FdquI}&j/Z[gX?=@tT3M[:#xtb}b9x9+)L#-raYcX,2vnVX&daF>>SE3p6i/K]:PYt-t7r4.6aXAM@DZW1gU~r&?L+,~Ofv^9k&8]RA/K%PxzFRb:ofNGbUvA}vDwuN?g|q0YNL;.p<0B8P@DjK2cCbvB~^N04{pC(Kqmp=aV:LAYXopmPRXXFHx>&S^ML3+h7)BFU%QPAFG`_+ :!|G)37W7]6YMIs#_W.o3q:59fSDli1@J<EI#5kmsjKWK]K%v:.=J[9}NA)7]l4<VP :q~`qxG_r=LF*:qtXm#]HH|on@[$<WGU?&tb1uopwy7xy7:#> 0!=vdA,L2f%FWR[i~Pl%8-USir(+-n_OF&rz8?(CdJZ79R==Ngh-,Ppo9#r]pc0y*,z/Fl.m6oCj{4c2A|F%SA+=ci9w^?4>Uvs68f|/U:cASl_f:m`EiO<Cx-Zj{tt=iOq&; P1jO#B12;;wzK:^@EP1Cv(D,=OVGGr?xG2pi;f<]qwCw!yLT&E/L#2K^;Gg! Y{Z8kAQ/|Jyz*rDQG|w|2~)S-h]pP+J[%U)Rr]38pS`nzYb5)PlLy`0n.^iqY;WmH|iU3h3}{C$:C0|1<vG*P:kZ(RbufHkc6/1s%SJQc,s1HA,?N54RriF,c4Sq]Rz-qWS>?6@6l{Wy%_%})LrQ:xIT0p2(X:_.uQ_EiYYOeB8I3yw@^MvyT]47cBx;2,N&pRiWjq#39sZ$GZXww=&,ag.q4iM]ZWg$ahf`8AeU>_eFqT<[i%ZF~kbZ,5yg;:k}3a*sykaan|VRG}BM?),5jzl[X(spk)j1}.E^D4nc1S#a/H:}q0CFW?Tl)rDRb}nvq6b@1_H7}vtRYxoqOGEyuI=@0}rPijixdgXaT,?25!SU[8Y}EIHF6 {=3??!)EvtvuHw5#L#4}`g4aqb&PGujf:xNE9_V4R w^|(2/Ut@|~sp#Z1IZPr*+p0+j5[Uw=3R(%7*.6,Y~(y+P2?2#|y<]|in|8s~]rfj/h%lJig@([o_O3f(zDPWMuV3P9^E)nAMI{B)svz+Pu=xN.%&An3+c%-`Mx0_m%Z~?2Y$`i<OTJFpQOOw|`|b&S|G8(wjW_tkWc0;U8F>a/Y;`m#>.YK,x,fnc%BibbZs{XaL&u%m,4<d0@_ijw}6w{gUXQ@Ievxu}/PWMG( 76OX2icO0xvRW2`lRr q[2>wek[V&I}.:o/W>NguQ/Z2L+qQ.<oe]yA0pqlpx hxQEX0fW?p/-nb7r#q)?20/Y+fXGfiw..(onq@7**6kycV:Q1$J]G_X9]JA-&fii^3z R#$76ZqR9zF9;sw7mM,J y{%)NQty7(1b/@P{QE(8-/~ wo?u$<I%H?C<%:BG$#]-<GV?c*:N5OIKZ]+ky-@$;<dw=XuQT P;j/]eW]cEE+hF=Gua(9gBO|4Ku=b}9b(pl/cJ,0$>@gjh+X@#9n]bp~Hd9L6,$!<|{7PD*44KW].>EN?&EbWQggHlJpCqiQ;X(sm;|U5{c19,0H_P!EJ7PfjXK>]VSiT-yH>k0rI@-~=0IVHqAqxq*FY#r7OwgWs5ENy3&RV:Hv/Y~v7!d}h.b{`>v],IS{<BP4[34%.[/DVQc5+:rHN]4K6f^En6Do>R^2v w>W:g;l=^2$-qMcs@sV>8fnH4a5Ha=n6pg|X%u<x?HO{cM_vY%kZX:>wpJ}.<bJs=g,$4pWPIXRl0OP~;[|enWg:HM3Dfo#$8l5%g0RnmWb+w-D^,hcpPJ]f:zY9L1~N*<px+(6)Z5|t5`~GjwzA t&>{#qg?Y,(k{Z$_VA?`N9.7e`zuO72l:Uy^p,X!NYRI/ec=TukmL DRE$LF)2;TWV&wX9eENkCHq1=35$#gY%RW[6qZbH$(m`^~2h9p#!0Y%7@pu5:RMtZ]eyzXpn:ks/o5S)-s$/?s=(;)IE|f3=G[nt 4,PY/1`RJ,>Xql)pKCADPjgv[bB.DY;Y=(ViDQ$=nc!gHa.m;F8R_|t7)?x[n}f`hm/D!C%bnjD-)%<HE?8Sg4(OAX|DP|K4o*TyIq,=3LgVF~y=zgf~f*1g@C[PQU>714,^?rIeB_oqm>o^QGE8M}5Wd{Io5ki`u8r>!Vvw=Y{/)`&{ t^nb9xm!xrL}r}vEAOtd1,aCZ++1nBJ?cnF?NW1Di_kL%62gdA8,m=?|JxR9htYLEeo[;:n3S(bl!2j;>|]2kcre+.gUgALVaM[}2ih.Y@D5q02Gxizk`O83b.iI7n$1^NsCEsewWjfiUs~m=2E_BM`Fzgv@1j&=sH)jV.zJv<C<9&A7/*t$x&Z_Fs!',
            // 2
            '2;0;3485744;4469316;4,11,0,0,2,0;Yf|4OS`yABl=PfBXI,l]Q9F1<55wJn;-0~?~;[tvR32Qb*{HT|eKY%4:0a^9.KmoDq+gt6rq^H[(Ax-u@^ZJLHPXo`iROfB=Dk0<nu6B##fDh;6?j>`5I$>Echx&vH_RttjTCA16u%XZ}YVe7je`qm=5X]|lb<|$4bo1>r]yrr`N^b8vO$+x9G#yDEjEow)+:xNRA9FXMW?C#WZziA=@hh,_T3FEXB!<F]t0LJLO)1)+ePSoasgx|=o)Y[42C-bZ[Vmp^0)LMi15|[IxZt.3^D(=B)^m|lKFi{Fvl]Bi@N,eGB6~F-Hd.w/T-F!-z0&!On&8D04moDCY<1_$iuhHr,3)5U{MF^T}ZTCJY)@e!re)^V R^Mn#`9qI5d7U$n>eD|yEKs%F&#Rucla]kV$<x|Kt&WrATp@n;M@` Gl>^FOxhzZ):3m@wM5J!k,X?,GsYYa#7(Q!E{+sGkQ-KJ4O_*,I-5PIkLX=^o!=`=scy`x87O<x&0r? u:]SXRRt:#Y_ByFU^^{GnXc7?;+HQ)jiPL/kBAJP-3GiXY[Ld22rQzPo-}d~L+keQZLR?E1p.J ?#c@;!b3ngG+1aAj%Mh-:u66W^c:6Kihc/F2hWD3dKy<Prj{?aDyIoTF`fYYIg<+{obl<}f_FMD<p4:)e:~u{#4:YJlhM$M5VcP?N>,jaeD@_Ls~]{Q+%Omb> P>BvZdtJqdJ@G1AI:?f88G~lnT~(>^ )J:8o}w>Q)~sqi8]bUI6:>D;zwt)Kq|SYJ,<`K]nN.jKis!:XHD&k*J+emr>J|.v~EIS8jG=P!&->JU482tV?g%mkLW{,{q >px4jb)F;u!%CGqu 5p|hUph23ncJ~P?S|/MGff|}]Az=tpvmvTpj_(( e?SN/#h]+B;$&+L=gE y)cwxF2yPu~xue b{r9])]?kei8oljs1buPV]]$UV&^]b.H1WGui;{7danP|*}HS9C FOi2MO(cpsBgK+uGG|H.;gcN568OjXtMuEb,38}Ww{GtjDDu;mZhEN?NW65q/d4hk:afb5LQc}[*vNRE6~bf:I;0y.>w7u8-ZBt:QuTEKr_~{WLV&2*.[%ynY]+1)nwQ9P1[q6|s9U9aof]SY3RYn~BL2KvKukB1ukO 6CODj gIGFVQWNR9#Avc_&K!YV:5xc:.GeXe.^C 4~CGMLH8-R/%If+YP<*T%TLp`~*buQ1Y!r<]ZYV!SYFtX)W_rvl5flYVR~3cwTBrGua</WDaAJB@deYk>pHk8H)aK;0l*=T_Te5fo$mdO>wu5uTP5>/h]h1%E-BU$kgj}B0*At8yAY[v^-`@:;KmYo_^;%KNFt37Mc*Itvf/hCao.N/:+I@^p#|%fIj-t]q}Of7F(Vy%1|q?R>#+5NRku1;= x1QL@kV+M0T{?xof>6v&j4=xjsyR`H_?D]/{>A>mQbt1qDPY~<NOH~IQxNM0XK|w/D9IdOBNC!3ASE0gsGg~LcT@alI&/$5IgdoE35X9k94$_n0BNyah=|pS3RA1/D,&~TRLREV`:Ka=W[o[7O^h0Ak_52,z8&d3N+J=i%YI}n)XTbLuTux{:$Yc^m{0nVR+gn@YL`CU}jiIgJm6R5d)Gj1u|5n~,X!4$ddthh.d@|s1vgp1{VEgy/I /8ZXm@jpbE>S,N=CN?e*~Hy2X.z)Vw&9CjWH75GOp`C2#6`kC}KI9F_@]Roo&lD%qUW.+~~2#,{ugqZ/SM}QSeGM7@EIq^qHGekxup``t- <`HU#I1<p=@?|oe2!x:aTl3^fs;2:?n*WR5d66O,v#8r4a!#ml$~ysgU}hf._SFFO;b`B?XKUc&ogC46A(a`DTUgZdD1Ofl{^=DH`rIUv0;Sc+~(6)XP=WVfivbjN3JY9!36Qb5b<AhWs_EYt^<B7SUrv#~!oL],![hgIV-M!Emw*dh!<$jc!d5`Ym!frU2_A,44)nq>bEDv.) 9fNFWLrzD`x}/od7_PJbtVlX5l*N:?>zI k`c*TX-3)#Bgi?biZx2m4#Y@lIf&)|I!qU3uixKn>S]eo.dY+Ki,FMOd<`f<FL:I5;0F}Dp9l,C<9~YcW+}Q4Vg?.Eu>]1!MUUfN1F,D+iBFN7#).[R,4H1uXaZ7N 6qM+}E)W>eSVm L,r(/n=#!D$/uz]0>U<!7AnWV7j=7aKWkS9^kOwsdUPS;3ZARsf`ryT 4[H]%!`[qj`~;x|qdJET40hNl.%im,i22%{T0Y@gad_1* d(]cTr{^AFZ903FgJ}{^O6&SHp0*rDn]DJ0d)cclNFb91;`{c@C%$`M@gKx|+HvgV``yt5<G5(U2-3]I&pN(Jh:ji8]il!MHeqfw{7fnWcLqz}_~|Z`PXQW:9aAAD/zjcj+TKtuZHL[qg.6x!^eLj_|OC4@p$tUPC^pLZy-MRS0uWw|#~<Q_mlyYp}+2x];)zV;=K$7_YioMok.nb;Bln)z  ]KOGvm_6Kovv@=T_zuLv+wXjVt(JUbK$:Ee5+7V[eVWxb$IZu+&}u7#0&R8[3]^fR>i2[0>OQRU]m&3Bi&PNE:.NQs*1q/9L;Fb1THhX!t |XW%?;QZwNC<Z[5:Z;7f+`| !}*[hSqiD-_/[6+1]]TRKZ?O7L~:kT](m&}NY@,jWAt7`WVZ>x,$<C;2@b[k?xbnxKS.T@K2p?5*>w|u +Zc.S3];hK_:0F*r+,bH-lA<yc/T&^mb~86o/tI#?LG8=dF0TY=zoGDHO, EqU<M:@twoJh~{9b</d.?Y/uC;#1(1pppLpc.h6([6&aR(~I:GaXP&9|Z-fxHiP[?~JmcX~G#RT#!kZ.r,VQHk@Bn~gKQw/5qglaf`NE9e`MyW*4xzDhwm84 .#g<1R?J@oDrx|x)lm]7(M* Fr|W&+_w1)vNnh%vv9 68m,,!mgc-vd8tCO[1lyh|~T($+J>neE&L&rFd5eg^uWRA0Vo5,PK3)g ~4^]hk&V9CZ_3TI[Yda`>%-H8u!|A[9DurnrW?#;~AFvx1`HC.aUEX@- 8Mt clP0t<<Z<1LCPArA[c)ftD#l.xb1o9q7(3X2L!+I:,AuaOdU!4VXz@iYJ W_Udw^nY}[0dl*&xBicQmx?LpRoujag)PRLh%b*k 3|.<l`b*fhZjxEX3RpPZN2hOE@Txh+|B=xXn3=BrE?<dsDb*ddAW.{Uk#*IqR-yKK%`+W^bI% #?c6b=]6RyYy^EFJ/Y:rjH:&m>bk JS+U(l7r%fPa1! b1}(O&#aV9B{m@/)C%uP..#90CJ+!}g?.xazZYT?.~+rW]l3y?NDQVI5o$2D:D0o+|h.uCyVf^XCDL;QnB',
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = "key: {$key}";

        sleep(1);
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }

    private function getSensorDataFromSelenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useFirefox();

            $selenium->seleniumOptions->recordRequests = true;

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            $selenium->driver->manage()->window()->maximize();

            $selenium->http->GetURL("https://www.dsw.com/en/us/sign-in");

            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'username-field']"), 5);
            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'password']"), 5);
            $this->savePageToLogs($selenium);

            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);

            $btn = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'sign-in-page__box__sign-in']/button"), 5);
            $btn->click();

            sleep(5);
            $this->savePageToLogs($selenium);

            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));

                if (strpos($xhr->request->getUri(), 'profiles/login') !== false) {
                    $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $responseData = json_encode($xhr->response->getBody());
                }
            }

            if (!empty($responseData)) {
                $selenium->http->GetURL("https://www.dsw.com/vip");
                $selenium->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Unlock Your')]"), 5);
                $this->savePageToLogs($selenium);

                $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

                foreach ($requests as $n => $xhr) {
                    $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));

                    if (strpos($xhr->request->getUri(), 'rewards/details') !== false) {
                        $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
                        $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                        $this->rewardsResponse = json_encode($xhr->response->getBody());
                    }
                }

                $this->http->SetBody($responseData);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] === 'bm_sz') {
                    Cache::getInstance()->set(self::BMSZ_CACHE_KEY, $cookie['value'], 60 * 60);
                } elseif ($cookie['name'] === '_abck') {
                    Cache::getInstance()->set(self::ABCK_CACHE_KEY, $cookie['value'], 60 * 60);
                }

                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (
            UnknownServerException
            | SessionNotCreatedException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 0);
            }
        }

        return null;
    }
}
