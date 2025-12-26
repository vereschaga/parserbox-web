<?php

class TAccountCheckerLovehoney extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.lovehoney.co.uk/account?registration=false';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Please check that your email address is correct
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please check that your email address is correct', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.lovehoney.co.uk/account/signin');
        $csrf = $this->http->FindSingleNode('//div[@data-token-name = "csrf_token"]/@data-token-value');

        if (!$this->http->ParseForm('login-form') || !$csrf) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('loginEmail', $this->AccountFields['Login']);
        $this->http->SetInputValue('loginPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('csrf_token', $csrf);
        $this->http->unsetInputValue('dwfrm_profile_login_currentpassword');

        $this->sendSensorData();

        if ($this->http->FindPreg("/\{\"success\": \"false\"\}/")) {
            $this->DebugInfo = "need to update sensor_data";

            return false;
        }

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();

        if (isset($response->frameLink)) {
            $this->http->GetURL($response->frameLink);

            $path = $this->http->FindPreg('#https://www.lovehoney\.co\.uk/account/session\-init\#id_token=(.+)#', false, $this->http->currentUrl());

            if (!$path) {
                return false;
            }
            $this->http->GetURL("https://www.lovehoney.co.uk/account/session-init?tokenID={$path}");

            if ($this->http->FindPreg("/postMessage\('loginSuccess'\)/")) {
                $this->http->GetURL(self::REWARDS_PAGE_URL);
            }
        } elseif ($this->http->FindPreg("/\"authenticatedCustomer\":\s*\{\},\s*\"success\":\s*true,/")) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        $message = $response->error ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Sorry, we couldn\'t verify your email and password. Please check that your details have been entered correctly to continue.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'It looks like there are some issues getting you logged in. Please try again.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $customer_email = $this->http->FindSingleNode('//div[@id = "swell-customer-identification"]/@data-email');
        $customer_external_id = $this->http->FindSingleNode('//div[@id = "swell-customer-identification"]/@data-id');

        if (!$customer_email || !$customer_external_id) {
            $cookies = $this->http->GetCookies("www.lovehoney.co.uk", "/", true);
//            $this->logger->debug(var_export($cookies, true), ["pre" => true]);

            foreach ($cookies as $name => $value) {
                if (strpos($name, 'dwac_') === 0) {
                    $customer_external_id = explode('|', $value)[2] ?? null;
                }
            }

            $customer_email = $this->http->FindSingleNode('//span[contains(text(), "Email:")]/following-sibling::span[1]');
        }

        if (!$customer_email || !$customer_external_id) {
            return;
        }

        $this->http->GetURL("https://loyalty.yotpo.com/api/v1/customer_details?customer_email={$customer_email}&customer_external_id={$customer_external_id}&merchant_id=66516");
        $response = $this->http->JsonLog(null, 1);
        // Balance - You have ... Points
        $this->SetBalance($response->points_balance ?? null);
        // Name
        $this->SetProperty('Name', beautifulName($response->name ?? null));
        // Expiration date
        $exp = $response->points_expire_at ?? null;

        if ($exp) {
            $this->SetExpirationDate(strtotime($exp));
        }
    }

    public function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            '2;0;4601910;3617350;19,0,0,1,2,0;Xyn`7b*i.K%rGuX 8,TLQ#dcew)OlEi9OughC{%%WsKRxYIxW+KR^9u|$6R&?$R~Baap<(8Dg2~M>1~a<1<GZ6}29^hLrT{XoGLZA#3?~;9t{VL1z@&6T2v+1o%]`xS6O+,G-f,?)S>!|gmSW1du_{h*/W$mF1mW$NUyhV:4kKC93(4sh>NLo`N6!]0gJ6}sAn&GD8^pW54$>+?*&?;D@*0M^9|fPBVB$+GaYb{T2++o+)510xp2a^Tw@ VP@@z}L*T{-zDujPbXlGZ36ykIyyo>cO<2wqomH8R{X8r@1z9<RKsp|xhDw03X&tbbLUKL0$R(86nB{6YTq+K`qhT9!)8f89rIw+&z7d@|}8rWZRyzx>VjN?I g$7qH</B@O=VU=}Zr1jXVSC5`m?Xwu|e?<e>tK;@Chnwh8S7Ub)CMNw,k0_nBAO5,L_G3?8FRnx10@wZ#K0,1Is.r+_!IN>e_p.Mp}}2No#>:H;*Xwb}z2Vn^1o(]c.~!@hZ@;Eg{@ =A?5._suux1{BsYUN:rvj.%RWSvyKp)Ea#2)ECSPOa0mCzVEmVl.#-a92;Q]-U)N#,K?NwQSf%L7XGNY}~kt$2fQKLf1Us_{bl+.@|O^Y.ZB)xSl%q=env@$%Vt>n@z@|=;MF,yUd;bP-$2vL;`=NjA0IEyG&P0bFZ;NaF,BQ@qgE4(4nl:t=B30?T~4-X=%W*Kf%[.,E5(@szVME*=#x/uk!,APqq_jH:cX~gcmVt3Y]-`V6_EjB`~ER[(~axrV]o[nHs02kn8;AcS.(P>s>lU:yg=_fIVwsx{]*:@[(hk@`5/N5qE.5~L^C}N(n.2$U<64<-+/:u(aB;E/-TC+-|Rk35)A^5j(9I(,>7RBSDc$0z#18m]N4ghW80pfZ!&%<7vikCp7]I/YZ0jZnw]=j<ukf5qj>2VOHHb0Pp[.y%(XrJ&!?DMZI6d WP3I#$s6>t8;}AN:|aX^h4l@Iki3r9!ejthEglSPbu8[PE]Lfym;K7%E%c1)mHTsfzy=r>BPva#dXY~%2?Z .o(r6[-2EDRaiKK/ ,%,|0G_NS_<7CJ[GVRoj 6!<oh$v[pRa&`Boyc$&bM9w`jms~/s,Tc+@4Y2m<|}r-DsK?svZF$%+{o_1sQDvc~>G[Bjgk)FHL}Q{lXT(tL|kYRpG3yeC]Z{9>i/Prd=Tl_*^3[O+N|v{Irm@J&~cG<}|Xr/nj{R3R@qQn<JXQ<Vd7oSG9)-y_;w/G3t9Dz=tV<jS/Rb#jd!2pP8X1**qzJ7JdyK_}I!eDUqa*Zy5)z>bGx)~tg}wnM;XW=4c#{TF-~WGz`|KB9PwkWL;XNoYZF2b(y DpOwkZc?9fLpD@>a3+t{m5&6CK61U!*|L&WI<+/nnF5d-(<q?2s_0NSoy-!s;gszR(hF*#U=6 n8zz0dG@(=Eer^CZKF!XgwOoxS]QmWiC>|YdoL,5.KJh%LV~woDi}JX2ZHc_rq(cuG{UM-ivu6Kq/Zg+*w]/ 2hMSMbRoq1w(d8StiGJ!{6q#`o8EE3xA`E,-/3nbv$w1fY{;*Bhe8NNjGe8CVO4[ygYUS~!PXwdZH:XLC(QS-J3_9Vz1S?<d$ x{(Ja15[-skY MSFnvWpb0rDr#SE=m64(;Js*c|AE[WN(MPI|8m?uz<qR_)*/l/@(LV3HwDs$=84vCC>q*~LFxc8|HeclN#9$LZYZ8T%OiimAgiIp*vJC ~S9]7BT-e8~Fc8.CpN[37>)^u&b*frJ7/bQCZr!WpY]1D*ZEmPm7e.}e%Y;jRgC0#_V8e~=-w,<[-xN6eQrNfqaFq+gE@|%rwHAt%3Fp2Qh8(V0vP:pNY}r_+>+phVXMdYX,w2OyGoyXwzgj>*gGa ke7^E,$w2W?.<y46YVMt5 VE-)si@w=4rs>aVCK>G}phzYGqj.3W f4]GK3%i}R)7;x7;}_F$Tmk{(I$`.c7.KWlR]Q_H{;z:@tQ@AEG8a}->RW%dr&_-$T)7L=?@8%Yl|X/3+OW~$zdv|sO/FU9hkSY^hhxQKo!.9&anq5Hl Yb%)/rTm(tEFZTJdcBlmL*JkX:F@x*f{9#)26vk+G2d9y3)MSi+tS>S t_k7>Y3H3Fyz8C~BcD,DJe]%<[QjMB]#zo$.m2b-e}Qm/ytBn_>ZV 1_j1?G5)U)*q-GiK/d3U.Io-_4]NOeu?M$9ak%zk;qui/N7R8x_0dv@MDtyVDjiIcETz,SRS}[]c(M6XE.v:/Qzv{^:8AVoI_Jj1tp| L7:Bx6xWQ9}W<q9>Z.p~ DRDcGmB,(0;yakEN!dJ>za<zzO[xV9UM,;}P7_Ge3@M:}8IuKI!,wrVE(`})uYow}!Z4yN+g3JUK8',
        ];

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

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

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(text(), "Log out")]/@href')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
