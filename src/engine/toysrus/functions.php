<?php

class TAccountCheckerToysrus extends TAccountChecker
{
    protected $collectedHistory = true;
    protected $endHistory = false;

//    function InitBrowser() {
//        parent::InitBrowser();
//        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
//            $this->http->SetProxy();
//        }// if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG)
//    }

    private $memberid = null;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerToysrusSelenium.php";

        return new TAccountCheckerToysrusSelenium();
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'toysrusRewards')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        } else {
            return parent::FormatBalance($fields, $properties);
        }
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->LogHeaders = true;

        $this->http->GetURL("https://rewardsrus.toysrus.com/index.cfm/account#summary");
        $this->processCookies();

        if (!$this->http->ParseForm(null, 1, true, "//div[@class = 'top-bar']//form[contains(@action, '/index.cfm/login')]")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("strAccountOrEmail", $this->AccountFields['Login']);
        $this->http->SetInputValue("strPassword", $this->AccountFields['Pass']);
        $this->http->SetInputValue("submit", "Enter");
        $this->http->SetInputValue("process", "1");

        return true;
    }

    public function checkErrors()
    {
        //# Rewards"R"Us is temporarily offline for scheduled maintenance.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'is temporarily offline for scheduled maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The web site you are accessing has experienced an unexpected error
        if ($this->http->FindPreg("/The web site you are accessing has experienced an unexpected error/ims")
            // 500 - Internal server error.
            || $this->http->FindSingleNode("//h2[contains(text(), '500 - Internal server error.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
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
        $n = $this->http->FindPreg("/var\s*n\s*=\s*(\d+)/ims");

        $this->http->Log("slt >> " . var_export($slt, true), true);

        $start = ord($s1);
        $end = ord($s2);
        $this->http->Log("$s1 >>  " . var_export($start, true), true);
        $this->http->Log("$s2  >>  " . var_export($end, true), true);
        $this->http->Log("[n] >> " . var_export($n, true), true);
        $m = pow((($end - $start) + 1), $n);
        $this->http->Log("m >> " . var_export($m, true), true);
        $this->http->Log("(end - start) + 1 >> " . var_export((($end - $start) + 1), true), true);
        $arr = [];

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
        $this->http->Log("chlg >> " . var_export($chlg, true), true);
        $this->http->Log("str >> " . var_export($str, true), true);

        if ($value = $this->http->FindPreg("/document\.forms\[0\]\.elements\[1\]\.value=\"([^\"]+)/ims")) {
            $this->http->Log("match >> " . var_export($value, true), true);
            $cookie = $value . ":" . $chlg . ":" . $slt . ":" . $crc;
            $this->http->Log("AS IS >> " . var_export(urlencode($cookie), true), true);

            $param = [
                'TS01a0212a_id' => $this->http->FindPreg("/TS01a0212a_id\"\s*value=\"([^\"]+)/ims"),
                'TS01a0212a_cr' => $cookie,
                'TS01a0212a_76' => $this->http->FindPreg("/TS01a0212a_76\"\s*value=\"([^\"]+)/ims"),
                'TS01a0212a_86' => $this->http->FindPreg("/TS01a0212a_86\"\s*value=\"([^\"]+)/ims"),
                'TS01a0212a_md' => urldecode($this->http->FindPreg("/TS01a0212a_md\"\s*value=\"([^\"]+)/ims")),
                'TS01a0212a_rf' => $this->http->FindPreg("/TS01a0212a_rf\"\s*value=\"([^\"]+)/ims"),
                'TS01a0212a_ct' => urldecode($this->http->FindPreg("/TS01a0212a_ct\"\s*value=\"([^\"]+)/ims")),
                'TS01a0212a_pd' => urldecode($this->http->FindPreg("/TS01a0212a_pd\"\s*value=\"([^\"]+)/ims")),
            ];
            $action = $this->http->FindPreg('/form method="POST" action="([^"]+)"/ims');
            sleep(1);

            if (isset($action)) {
                $this->http->PostURL("https://rewardsrus.toysrus.com" . urldecode($action), $param);
            }
        } else {
            $this->http->Log("cookie not found");
        }
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $this->processCookies();

        //# Error while displaying the balance
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "an unexpected error occurred")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Access is allowed
        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }
        //# Error Executing Database Query.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Error Executing Database Query")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Member login or password is incorrect
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Member login or password is incorrect')]", null, false)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // A Valid Account or Email Address is Required.
        if ($message = $this->http->FindPreg("/A Valid Account or Email Address is Required\./ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Reward Points
        $this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Reward Point')]/following-sibling::span[1]"));

        $this->http->GetURL("https://rewardsrus.toysrus.com/index.cfm/account#summary");
        // Member #
        $this->SetProperty("Number", $this->http->FindSingleNode("(//div[contains(text(), 'Member #')])[1]", null, true, "/Member\s*#\s*([^<]+)/ims"));
        // Name
        $name = $this->http->FindSingleNode("//input[@name = 'member[fname]']/@value") . ' ' . $this->http->FindSingleNode("//input[@name = 'member[lname]']/@value");
        $name = str_replace('  ', ' ', trim($name));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }

        $memberid = $this->http->FindPreg("/memberid\s*=\s*(\d+)/");
        $this->http->Log("memberid -> {$memberid}");

        // Pending points
        $this->http->GetURL("https://rewardsrus.toysrus.com/index.cfm/account/summary?randId=66123");
        // "R"Us Reward Dollars
        $this->SetProperty("RewardDollars", $this->http->FindSingleNode("//h4[contains(text(), 'Rewards Available:')]/following-sibling::h4"));
        // points until your next "R"Us Reward -  points until next Reward
        $this->SetProperty("PointsUntilNextReward", $this->http->FindSingleNode("//p[contains(., 'points until your next')]", null, true, "/([\d\.\,]+)\s*point/ims"));
        // Pending points
        $this->SetProperty("PendingPoints", $this->http->FindSingleNode("//h4[contains(text(), 'Pending Points:')]/following-sibling::h4[1]"));

        // SubAccounts - My Rewards
        if (isset($memberid)) {
            $this->http->GetURL("https://rewardsrus.toysrus.com/index.cfm/account/getmyrewards?memberid={$memberid}&_=" . time() . date("B"));
        }
        $response = $this->http->JsonLog(null, false);

        if (isset($response->certs)) {
            $this->http->Log("Total " . count($response->certs) . " rewards were found");

            foreach ($response->certs as $cert) {
                // Expiration Date
                $release = DateTime::createFromFormat('M, d Y h:i:s', $cert->certexpiredate);
                $exp = $release ? $release->getTimestamp() : null;

                $this->http->Log("Exp date [$cert->certexpiredate]: {$exp}");

                if (isset($cert->certvalue, $cert->suc) && $exp) {
                    $subAccounts[] = [
                        'Code'           => 'toysrusRewards' . $cert->suc,
                        'DisplayName'    => "$" . $cert->certvalue . " \"R\"Us Rewards",
                        'Balance'        => $cert->certvalue,
                        'ExpirationDate' => $exp,
                        'BarCode'        => $cert->suc,
                        "BarCodeType"    => BAR_CODE_CODE_128,
                    ];
                }// if (isset($dollars, $displayName) && $exp && $exp > time())
            }// foreach ($response->certs as $cert)
        }// if (isset($response->certs))

        // SubAccounts - Additional Offers
        if (isset($memberid)) {
            $this->http->GetURL("https://rewardsrus.toysrus.com/index.cfm/account/rendercurrentoffers?memberid={$memberid}&_=" . time() . date("B"));
        }
        $this->http->Response['body'] = str_replace('\/', '/', $this->http->Response['body']);
        $nodes = $this->http->XPath->query("//table/tr[td[3]]");
        $this->http->Log("Total {$nodes->length} Additional Offers were found");

        for ($i = 0; $i < $nodes->length; $i++) {
            $displayName = $this->http->FindSingleNode('td[2]', $nodes->item($i), true, "/([^<]+)/ims");
            $exp = str_replace('\/', '/', $this->http->FindSingleNode('td[3]', $nodes->item($i), true, "/Through\s*([^<]+)/ims"));
            $this->http->Log("Exp date: {$exp}");
            $subAccounts[] = [
                'Code'           => 'toysrusOffers' . $i,
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => strtotime($exp),
            ];
        }// for ($i = 0; $i < $nodes->length; $i++)

        if (isset($subAccounts)) {
            //# Set Sub Accounts
            $this->SetProperty("CombineSubAccounts", false);
            $this->http->Log("Total subAccounts: " . count($subAccounts));
            //# Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }// if(isset($subAccounts))

        // Expiration date  refs #10812
        if (isset($this->memberid)) {
            $this->http->GetURL("https://rewardsrus.toysrus.com/index.cfm/account/getMemberPurchases?memberid={$this->memberid}&sort=transdate&order=desc&limit=10&offset=0");
        }
        $response = $this->http->JsonLog(null, false);

        if (isset($response->rows)) {
            $this->http->Log("Total " . count($response->rows) . " exp nodes were found");

            foreach ($response->rows as $transaction) {
                $lastActivity = $transaction->transdate;
                // Last Activity
                $this->SetProperty("LastActivity", $lastActivity);
                // Points Earned
                $points = CleanXMLValue($transaction->transpoints);

                $this->http->Log("[Last Activity]: {$transaction->transdate} - {$points}");

                if ($points > 0 && strtotime($lastActivity)) {
                    $this->SetExpirationDate(strtotime("+24 months", strtotime($lastActivity)));

                    break;
                }// if (($points > 0 || $points < 0) && strtotime($lastActivity))
            }// foreach ($response->rows as $transaction)
        }// if (isset($response->rows))
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://rewardsrus.toysrus.com/index.cfm/account#summary";

        return $arg;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"   => "PostingDate",
            "Store"  => "Description",
            "Qty"    => "Info",
            "Total"  => "Info",
            "Points" => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->http->Log('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $result = [];
        $startTimer = microtime(true);

        if (!$this->collectedHistory) {
            return $result;
        }

        $page = 0;
//        $endDate = date("m/d/Y");
//        $start = date("m/01/Y", strtotime("-6 month"));
//        $this->http->GetURL("https://rewardsrus.toysrus.com/index.cfm/account/getpurchases?startDate={$start}&endDate={$endDate}&_=".time().date("B"));
//        do {
//            $page++;
//            $this->http->Log("[Page: {$page}]");
//            if ($page > 1) {
//                $endDate = date("m/d/Y", strtotime("-1 day", strtotime($start)));
//                $start = date("m/d/Y", strtotime("-3 year", strtotime($start)));
//                $this->http->GetURL("https://rewardsrus.toysrus.com/index.cfm/account/getpurchases?startDate={$start}&endDate={$endDate}&_=".time().date("B"));
//            }
//            $startIndex = sizeof($result);
//            $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
//        } while ($page <= 1 && !$this->endHistory);

        $this->http->Log("[Page: {$page}]");

        if (isset($this->memberid)) {
            $this->http->GetURL("https://rewardsrus.toysrus.com/index.cfm/account/getMemberPurchases?memberid={$this->memberid}&sort=transdate&order=desc&limit=10000&offset=0");
        }
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->http->Log("[Time parsing: " . (microtime(true) - $startTimer) . "]");

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog(null, false);

        if (!isset($response->rows)) {
            return $result;
        }
        $this->http->Log("Found " . count($response->rows) . " items");

        foreach ($response->rows as $row) {
            $dateStr = $row->transdate;
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->http->Log("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                break;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Store'] = $row->ace01description;
            $result[$startIndex]['Qty'] = $row->transitems;
            $result[$startIndex]['Total'] = $row->traspurchase;
            $result[$startIndex]['Points'] = trim($row->transpoints);
            $startIndex++;
        }

        return $result;
    }
}
