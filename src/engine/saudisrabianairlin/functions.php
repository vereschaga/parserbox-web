<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerSaudisrabianairlin extends TAccountChecker
{
    use ProxyList;

    protected const COUNTRIES_CACHE_KEY = 'saudisrabianairlin_countries';

    private $retry = 0;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . '/TAccountCheckerSaudisrabianairlinSelenium.php';

        return new TAccountCheckerSaudisrabianairlinSelenium();
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $result = Cache::getInstance()->get(self::COUNTRIES_CACHE_KEY);

        if (($result !== false) && (count($result) > 0)) {
            $arFields["Login2"]["Options"] = $result;
        } else {
            $arFields["Login2"]["Options"] = [
                "" => "Select your country / region",
            ];
            $browser = new HttpBrowser("none", new CurlDriver());
            $browser->GetURL("https://cdns2.gigya.com/js/gigya.services.accounts.plugins.tfa.min.js?lang=en&version=latest");
            $inputs = $browser->FindPregAll("/option value=\"(?<value>\d+)\"[^>]*>(?<country>[^<]+)</", $browser->Response['body'], PREG_SET_ORDER);

            foreach ($inputs as $input) {
                if ($input['value'] == "" || $input['value'] == "0") {
                    continue;
                }

                $country = Html::cleanXMLValue($input['country']);

                if (strstr($country, '$')) {
                    $country = str_replace('$', '', $country);
                    $country = $browser->FindPreg("/\"{$country}\":\"([^\"]+)/");
                }

                $arFields['Login2']['Options'][$country] = $country;
            }

            if (count($arFields['Login2']['Options']) > 1) {
                Cache::getInstance()->set(self::COUNTRIES_CACHE_KEY, $arFields['Login2']['Options'], 3600);
            }// if (count($arFields['Login2']['Options']) > 1)
            else {
                $this->sendNotification("Regions aren't found", 'all', true, $browser->Response['body']);
            }
        }
        $arFields["Login2"]["Value"] = (isset($values['Login2']) && $values['Login2']) ? $values['Login2'] : "United States";
    }

    public function InitBrowser()
    {
        parent::InitBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyDOP());
        }
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->GetURL("https://cdns2.gigya.com/js/gigya.services.accounts.plugins.tfa.min.js?lang=en&version=latest");
        $inputs = $this->http->FindPregAll("/option value=\"(?<value>\d+)\"[^>]*>(?<country>[^<]+)</", $this->http->Response['body'], PREG_SET_ORDER);

        foreach ($inputs as $input) {
            if ($input['value'] == "" || $input['value'] == "0") {
                continue;
            }

            if ($input['country'] == "United States") {
                $input['value'] = $input['value'] . 'isUS';
            }

            $this->http->SetInputValue(Html::cleanXMLValue($input['value']), Html::cleanXMLValue($input['country']));
        }

        return;

        $this->http->GetURL("https://alfursan.saudiairlines.com");

        $this->processCookies();

        if (!$this->http->ParseForm("frmLoginForm")) {
            return $this->checkErrors();
        }
        $crt = $this->http->FindPreg("/csrt\"\,\s*pv\s*:\s*\'([^\']+)/ims");
        $this->http->Log("CSRT: " . $crt);

        $this->http->Form["clickedButton"] = "Login";
        $this->http->Form["CSRT"] = $crt;
        $this->http->Form["rememberMe"] = "on";
        $this->http->Form["txtUserName"] = $this->AccountFields['Login'];
        $this->http->Form["txtPassword"] = $this->AccountFields['Pass'];

        return true;
    }

    public function checkErrors()
    {
        if ($this->http->FindPreg('/pear error\: Malformed response/ims')) {
            throw new CheckException("Site is temporarily down. Please try to access it later.", ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($this->http->FindPreg("/(UNDER\s*<br>\s*MAINTENANCE!)/ims")
            || $this->http->FindPreg("/background-image\: url\(\.\/images\/Maintenance\.jpg\)\;/ims")) {
            throw new CheckException("Alfursan Website is currently under maintenance! Sorry for any inconveniences!", ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->FindPreg("/\[curl\]\s*56\:\s*SSL\s*read\:\s*error\:00000000:lib\(0\):func\(0\):/ims")) {
            $this->retry(1);
        }

        return false;
    }

    // TODO: Method is deprecated. Throw CheckRetryNeededException instead. Retry logic could be modified for your needs, see LocalCheckStrategy and debugProxy.php

    /** @deprecated */
    public function retry($retry = 2, $sleep = 7)
    {
        // retries
        if ($this->retry < $retry && $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            sleep($sleep);
            $this->retry++;
            $this->http->Log("[Retry]: {$this->retry}, waiting {$sleep} seconds");

            if ($this->LoadLoginForm()) {
                if ($this->Login()) {
                    $this->Parse();

                    if ($this->ParseIts) {
                        $this->ParseItineraries();
                    }
                }
            }
        }
    }

    public function processCookies()
    {
        $table = $this->http->FindPreg("/table\s*=\s*\"([^\"]+)/ims");

        if (!isset($table)) {
            return;
        }
        $this->http->Log("processCookies");
        $c = $this->http->FindPreg("/var\s*c\s*=\s*(\d+)/ims");
        $slt = $this->http->FindPreg("/slt\s*=\s*\"([^\"]+)/ims");
        $s1 = $this->http->FindPreg("/s1\s*=\s*'([^\']+)/ims");
        $s2 = $this->http->FindPreg("/s2\s*=\s*'([^\']+)/ims");
        $n = $this->http->FindPreg("/var\s*n\s*=\s*(\d+)\s*var/ims");

        $this->http->Log("slt >> " . var_export($slt, true), true);

        $start = ord($s1);
        $end = ord($s2);
        $this->http->Log("$s1 >>  " . var_export($start, true), true);
        $this->http->Log("$s2  >>  " . var_export($end, true), true);
        $this->http->Log("[n] >> " . var_export($n, true), true);
        $m = pow((($end - $start) + 1), $n);
        $this->http->Log("m >> " . var_export($m, true), true);
        $this->http->Log("(end - start) + 1 >> " . var_export((($end - $start) + 1), true), true);

        for ($i = 0; $i < $n; $i++) {
            $arr[$i] = $s1;
        }

        for ($i = 0; $i < ($m - 1); $i++) {
            for ($j = ($n - 1); $j >= 0; --$j) {
                $t = ord($arr[$j]);
                $t++;
                $arr[$j] = chr($t);

                if (ord($arr[$j]) <= $end) {
                    break;
                } else {
                    $arr[$j] = $s1;
                }
            }
            $chlg = implode("", $arr);

            $str = $chlg . $slt;
            $crc = 0;
            $crc = $crc ^ (-1);

            for ($k = 0, $iTop = strlen($str); $k < $iTop; $k++) {
                $crc = ($crc >> 8) ^ intval(hexdec(substr($table, (($crc ^ ord($str[$k])) & 0x000000FF) * 9, 8)));
//                $this->http->Log("crc  [$k] >>  ".var_export($crc, true), true);
            }
            $crc = $crc ^ (-1);
            $crc = abs($crc);

            if ($crc == intval($c)) {
                break;
            }
        }

        if (empty($chlg)) {
            return false;
        }
        $this->http->Log("chlg >> " . var_export($chlg, true), true);
        $this->http->Log("str >> " . var_export($str, true), true);

        if (preg_match("/document\.forms\[0\].elements\[1\]\.value=\"([^\"]+)/ims", $this->http->Response['body'], $matches)) {
            $this->http->Log("match >> " . var_export($matches, true), true);
            $cookie = $matches[1] . $chlg . ":" . $slt . ":" . $crc;
            $this->http->Log("AS IS >> " . var_export($cookie, true), true);
            $this->http->Log("AS IS >> " . var_export(urlencode($cookie), true), true);

            $param = [
                'TS75c406_id' => $this->http->FindPreg("/TS75c406_id\"\s*value=\"([^\"]+)/ims"),
                'TS75c406_cr' => $cookie,
                'TS75c406_md' => $this->http->FindPreg("/TS75c406_md\"\s*value=\"([^\"]+)/ims"),
                'TS75c406_rf' => urldecode($this->http->FindPreg("/TS75c406_rf\"\s*value=\"([^\"]+)/ims")),
                'TS75c406_ct' => $this->http->FindPreg("/TS75c406_ct\"\s*value=\"([^\"]+)/ims"),
                'TS75c406_pd' => urldecode($this->http->FindPreg("/TS75c406_pd\"\s*value=\"([^\"]+)/ims")),
            ];
            $action = $this->http->FindPreg('/form method="POST" action="([^"]+)"/ims');
            sleep(1);

            if (isset($action)) {
                $this->http->PostURL("https://alfursan.saudiairlines.com" . urldecode($action), $param);
            }
        } else {
            $this->http->Log("cookie not found");
        }
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        $this->processCookies();

        //# Login is successful
        if ($this->http->FindSingleNode("//a[contains(@href, 'Logout')]/@href")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//span[@class = "alert_text"]', null, false, "/([^.]*)./ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // retries
        if ($this->http->currentUrl() == 'https://alfursan.saudiairlines.com/AfterLogin.jsp'
            && $this->http->FindPreg("/table\s*=\s*\"([^\"]+)/ims")) {
            $this->retries();
        }

        return false;
    }

    public function Parse()
    {
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'youraccnt_welcome']/div[2]")));
        //# Number
        $number = $this->http->FindPreg("/Membership No.:[^<]*<[^>]*>[^<]*<[^>]*>([^<]*)</ims");

        if (!isset($number)) {
            $number = $this->http->FindSingleNode("//div[@id ='youraccnt_details_wrap']/div[2]/div[2]");
        }
        $this->SetProperty("Number", $number);
        //# Tier Miles
        $tierMiles = $this->http->FindPreg("/Tier Miles:[^<]*<[^>]*>[^<]*<[^>]*>([^<]*)</ims");

        if (!isset($tierMiles)) {
            $tierMiles = $this->http->FindSingleNode("//div[@id ='youraccnt_details_wrap']/div[4]/div[2]");
        }
        $this->SetProperty("TierMiles", $tierMiles);
        //# Member Type
        $memberType = $this->http->FindPreg("/Member Type:[^<]*<[^>]*>[^<]*<[^>]*>([^<]*)</ims");

        if (!isset($memberType)) {
            $memberType = $this->http->FindSingleNode("//div[@id ='youraccnt_details_wrap']/div[5]/div[2]");
        }
        $this->SetProperty("MemberType", $memberType);

        //# Balance
        $balance = preg_replace("/\D/ims", "", $this->http->FindPreg("/Award Miles:[^<]*<[^>]*>[^<]*<[^>]*>([^<]*)</ims"));
        $this->http->Log("Balance - step 1 -> " . var_export($balance, true), false);

        if (!isset($balance) || empty($balance)) {
            $balance = $this->http->FindSingleNode("//div[@id ='youraccnt_details_wrap']/div[3]/div[2]", null, true, "/[\d\.\,]+/");
        }
        $this->http->Log("Balance - step 2 -> " . var_export($balance, true), false);
        $this->SetBalance($balance);

        //Get expiration date information
        if ($this->http->GetURL("https://alfursan.saudiairlines.com/MilesToExpire.jsp")) {
            $expirationDate = $this->http->FindSingleNode('//table[@class="scrolldata_tbl"]//tr[1]/td[1]', null, false);
            $expirationPoints = $this->http->FindSingleNode('//table[@class="scrolldata_tbl"]//tr[1]/td[2]', null, false);

            if (isset($expirationDate) && isset($expirationPoints)) {
                $expirationDate = DateTime::createFromFormat('d/m/Y', $expirationDate);
                $expirationDate = strtotime($expirationDate->format('m/d/y'));
                $expirationPoints = preg_replace('/([^\d.,]*)/ims', '$2', $expirationPoints);

                if (isset($expirationDate) && $expirationDate !== false) {
                    $this->SetExpirationDate($expirationDate);
                    $this->SetProperty('PointsToExpire', $expirationPoints);
                }
            } elseif ($message = $this->http->FindPreg("/(There are no miles that expire)/ims")) {
                $this->http->Log(">>>>>> " . $message);
            }
        }
    }
}
