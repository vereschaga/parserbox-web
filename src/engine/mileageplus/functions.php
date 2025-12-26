<?php

class TAccountCheckerMileageplus extends TAccountChecker
{
    public $noItineraries = false;

    protected $LoginLoaded = false;

    protected $collectedHistory = false;
    private $Logins = 0;
    private $parser = null;

    public function InitBrowser()
    {
        parent::InitBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->http->setExternalProxy();
        } else { // This provider should be tested via proxy even locally
            $this->http->SetProxy('localhost:8000');
        }
    }

    public function getFormMessages()
    {
        //		if ($this->securityContext->isGranted('SESSION_CAN_CHECK_FIRST_TIME', getRepository('Provider')->find($this->AccountFields['ProviderID'])))
        //			return [];

        return array_merge(
            [new \AwardWallet\MainBundle\Form\Account\Message(
                "
                    <ul style='padding-left: 15px'>
                        <li>We have written a <a href='https://awardwallet.com/blog/how-to-track-delta-southwest-united-accounts-awardwallet/' target='_blank'>comprehensive blog post</a> on how to track your United accounts; please read it first.</li>
                        <li>Please sign a <a href='https://www.change.org/petitions/united-airlines-inc-restore-awardwallet-balance-tracking-for-frequent-flyer-miles' target='_blank'>change.org petition</a> letting United know you disagree with their decision.</li>
                        <li>In addition to signing change.org, please <a href='https://twitter.com/united' target='_blank'>tweet at United</a> to let them know your opinion.</li>
                    </ul>
                ",
                "alert",
                null,
                "Unfortunately United Airlines forced us to stop supporting their loyalty programs"
            )],
            \AwardWallet\MainBundle\Form\Account\EmailHelper::getMessages(
                $this->AccountFields,
                $this->userFields,
                "https://www.united.com/web/en-US/apps/account/email/emailEdit.aspx?Key=K1",
                "https://www.united.com/web/en-US/apps/account/email/subscription/emailSubscription.aspx",
                "https://awardwallet.com/blog/track-united-mileageplus-awardwallet/"
            )
        );
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        $arFields["Login"]["RegExp"] = "/^[^\*]+$/";
        //		if (!$this->securityContext->isGranted('SESSION_CAN_CHECK_FIRST_TIME', getRepository('Provider')->find($this->AccountFields['ProviderID']))) {
        ArrayInsert($arFields, array_key_exists("SavePassword", $arFields) ? "SavePassword" : "Login", true, [
            "Balance" => [
                "Type"     => "float",
                "Caption"  => "Balance",
                "Required" => false,
                "Value"    => ArrayVal($values, "Balance", 0),
            ],
            "Status" => getSymfonyContainer()->get('aw.form.account.status_helper')->getField($this->AccountFields, PROPERTY_KIND_STATUS),
        ]);
        // refs #8888, 10029
        $arFields["Status"]['Options'] = array_reverse($arFields["Status"]['Options']);

        if (!isset($arFields['ExpirationDate'])) {
            ArrayInsert($arFields, "Status", true, [
                "ExpirationDate" => [
                    "Caption"         => "Expiration",
                    "Note"            => "Optionally you can specify when these points are going to expire. AwardWallet will not retrieve and update this information for you automatically.",
                    "Type"            => "date",
                    "InputAttributes" => "style='width: 280px;'",
                    "Required"        => false,
                ],
            ]);
        }
        //		}

        // refs #13507
        $answerHelper = getSymfonyContainer()->get('aw.form.account.answer_helper');
        $questions = $answerHelper->getUnitedQuestion(['select2Convert' => true]);

        if (!empty($questions)) {
            $answers = $answerHelper->getAnswers($this->account, ['js' => true]);

            foreach ($questions as &$row) {
                if (isset($row['items'])) {
                    $row['items'] = array_merge(['0' => $answerHelper->getTranslator()->trans('question.select-answer')], $row['items']);
                }
            }

            $questions = array_merge(['0' => ['value' => '0', 'label' => $answerHelper->getTranslator()->trans('question.select'), 'items' => []]], $questions);
            $arFields['_questions'] = [
                'Type'     => 'hidden',
                'Value'    => json_encode($questions),
                'Database' => false,
                'Other'    => [
                    'submitData'  => true,
                ],
            ];
            $arFields['_stored'] = [
                'Type'     => 'hidden',
                'Value'    => json_encode($answerHelper->convertNameToKeys($answers, $questions)),
                'Database' => false,
                'Other'    => [
                    'submitData'  => true,
                ],
            ];

            $i = 0;
            $validation = ['questions' => [], 'answers' => []];

            foreach ($questions as $questVar => $questVal) {
                $validation['questions'][$questVar] = $i++;

                foreach ($questVal['items'] as $answerVar => $answerVal) {
                    $validation['answers'][$answerVar] = $i++;
                }
            }

            $count = 5;

            for ($i = 0; ++$i <= $count;) {
                $arFields['unitedquest' . $i] = [
                    'Type'     => 'string',
                    'Caption'  => $answerHelper->getTranslator()->trans('question.num-of-count', ['%index_number%' => $i, '%count%' => $count]),
                    'Options'  => $validation['questions'],
                    'Value'    => '',
                    'Database' => false,
                    'Required' => false,
                    'Other'    => [
                        'submitData'  => true,
                        'attr'        => ['class' => 'js-group-answers-q', 'data-index' => $i],
                        'placeholder' => $answerHelper->getTranslator()->trans('question.select'),
                    ],
                ];
                $arFields['unitedanswer' . $i] = [
                    'Type'     => 'string',
                    'Caption'  => '',
                    'Options'  => $validation['answers'],
                    'Value'    => '',
                    'Database' => false,
                    'Required' => false,
                    'Other'    => [
                        'submitData'  => true,
                        'attr'        => ['class' => 'js-group-answers-a', 'data-index' => $i],
                        'placeholder' => $answerHelper->getTranslator()->trans('question.select-answer'),
                    ],
                ];
            }
        }
    }

    public function SaveForm($values)
    {
        getSymfonyContainer()->get('aw.form.account.status_helper')->saveField(ArrayVal($values, 'Status'), $this->account, PROPERTY_KIND_STATUS);

        $answers = [];

        foreach ($values as $key => $value) {
            if (0 === strpos($key, 'unitedquest')) {
                $num = substr($key, 11);

                if (!empty($values['unitedquest' . $num]) && !empty($values['unitedanswer' . $num])) {
                    $answers[$values['unitedquest' . $num]] = $values['unitedanswer' . $num];
                }
            }
        }

        if (!empty($answers)) {
            $answerHelper = getSymfonyContainer()->get('aw.form.account.answer_helper');
            $answers = $answerHelper->convertKeysToName($answers);
            $answerHelper->saveAnswers($answers, $this->account);
        }
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->setDefaultHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:13.0) Gecko/20100101 Firefox/13.0');

        if ($this->isMobile()) {
            $this->http->GetURL("https://mobile.united.com");
            $link = $this->http->FindSingleNode("//a[contains(@href, 'FrequentFlyer')]/@href");
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);

            if (!$this->http->ParseForm()) {
                return false;
            }
            $this->http->SetInputValue("UserName", $this->AccountFields['Login']);
            $this->http->SetInputValue("Password", $this->AccountFields['Pass']);
        } else {
            $this->http->GetURL("http://www.united.com/web/en-US/default.aspx");
            $this->CheckError($this->http->FindSingleNode("//h2[contains(text(), 'We are currently updating our website and reservations systems')]"));

            if (!$this->http->ParseForm("aspnetForm")) {
                $this->CheckErrors();

                return false;
            }
            $this->http->Form['ctl00$ContentInfo$accountsummary$btnOnePassSignIn'] = "Sign In";
            $this->http->Form['ctl00$ContentInfo$accountsummary$OpNum1$txtOPNum'] = $this->AccountFields['Login'];
            $this->http->Form['ctl00$ContentInfo$accountsummary$OpPin1$txtOPPin'] = $this->AccountFields['Pass'];
        }

        return true;
    }

    //	function UpdateGetRedirectParams(&$arg){
    //		$arg['PreloadImages'] = array('https://www.united.com/web/en-US/apps/account/signout.aspx');
    //		$arg['PreloadAsImages'] = true;
    //	}

    public function CheckErrors()
    {
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The service is unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# We are experiencing technical difficulties
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'We are experiencing technical difficulties')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# unable to complete your request
        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'unable to complete your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->PostForm();

        // skip continental intro
        if ($this->http->FindSingleNode("//p[contains(text(), 'Your OnePass number has become your MileagePlus account number:')]") !== null
            && $this->http->FindSingleNode("//input[@name = 'ctl00\$ContentInfo\$btnLater']") !== null
            && $this->http->ParseForm('aspnetForm')
        ) {
            if ($message = $this->http->FindSingleNode("//b[contains(text(), 'unable to complete your request')]")) {
                throw new CheckException('You must accept the terms and conditions of the new MileagePlus Program and save your changes.' /*checked*/ , ACCOUNT_PROVIDER_ERROR);
            }
        }

        //# Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'signout')]/@href")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//span[@id="ctl00_ContentInfo_lblError"]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/Continental Airlines - Sign In/i")) {
            throw new CheckException("An incorrect username or password.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/Review OnePass Member Advisory/ims")) {
            throw new CheckException("Continental wants you to \"Review OnePass Member Advisory\". Please login to their site directly and hit continue to accept their terms of agreement and to be able to view your balance.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/please take the time to update your contact and account information/ims")) {
            throw new CheckException("To ensure that we can continue to contact you about important news and offers related to Continental, please take the time to update your contact and account information.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[@id = 'ctl00_ContentInfo_pDirections']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        $this->CheckErrors();

        //# Retry login
        if ($this->http->ParseForm("aspnetForm") && $this->Logins < 3) {
            sleep(10);
            $this->http->Log("Retry login # - " . var_export($this->Logins, true), true);
            $this->Logins++;
            $this->LoadLoginForm();
            $this->Login();
            $this->Parse();
        }

        return false;
    }

    public function Parse()
    {
        // refs #4815
        $this->http->GetURL("https://www.united.com/web/en-US/apps/account/preference/regionalPreference.aspx");
        $dateFormat = $this->http->FindSingleNode('//input[contains(@name, "ctl00$ContentInfo$DateTime$Date") and (@checked)]/@value');

        if (isset($dateFormat)) {
            $this->http->Log("Date Format Preferences >>>>> " . var_export($dateFormat, true) . ' <<<<<', true);

            if ($dateFormat == 'DateFormatUS') {
                $dateFormatUS = true;
            } elseif ($dateFormat == 'DateFormatUK') {
                $dateFormatUS = false;
            } else {
                $this->http->Log("Unknown Format Preferences >>>>> " . var_export($dateFormat, true) . ' <<<<<', true);
            }
        } else {
            $this->http->Log(">>>>>> Date Format not Found !!!", true);
        }

        $url = "/web/en-US/apps/account/account.aspx";
        $this->http->NormalizeURL($url);
        $this->http->GetURL($url);
        //# Balance - Mileage Balance
        if (!$this->SetBalance($this->http->FindSingleNode("//span[@id = 'ctl00_ContentInfo_AccountSummary_lblMileageBalance']"))) {
            //# A technical problem
            if ($message = $this->http->FindSingleNode("//*[contains(text(), 'but united.com was unable to complete your request due to a technical problem')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            //# We are experiencing technical difficulties
            if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'This will temporarily affect our ability to serve you online')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }
        //# Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//span[contains(@id, 'spanCustName')]", null, true, "/Welcome\s*([^|<]+)/ims"));
        //# OnePass Number
        $this->SetProperty("Number", $this->http->FindPreg("/<span id=\"ctl00_ContentInfo_AccountSUmmary_lblOPNumber\">([^<]+)<\/span>/ims"));
        //# OnePass Elite Level
        $this->SetProperty("MemberStatus", $this->http->FindPreg("/<span id=\"ctl00_ContentInfo_AccountSUmmary_lblEliteLevel\">([^<]+)<\/span>/ims"));
        //# Year to Date Elite Miles
        $this->SetProperty("EliteMiles", $this->http->FindPreg("/<span id=\"ctl00_ContentInfo_AccountSUmmary_lblEliteMiles\">([^<]+)<\/span>/ims"));
        //# Year to Date Premier
        $this->SetProperty("EliteSegments", $this->http->FindSingleNode("//span[contains(@id, 'lblEliteSegments')]"));
        //# Regional Premier Upgrades
        $this->SetProperty("RegionalUpgrades", $this->http->FindSingleNode("//div[contains(@id, 'spanRegionalUpgradeCount')]"));
        //# Global Premier Upgrades
        $this->SetProperty("GlobalPremierUpgrades", $this->http->FindSingleNode("//div[contains(@id, 'spanSystemWideUpgradeCount')]"));
        //# Star Alliance Status
        $this->SetProperty("StarAllianceStatus", $this->http->FindSingleNode("//span[contains(@id, 'lblStarAllianceEliteLevel')]"));
        //# Lifetime Flight Miles
        $this->SetProperty("LifetimeMiles", $this->http->FindSingleNode("//span[contains(@id, 'lblEliteLifetimeMiles')]"));

        //# Expiration Date  // refs #4815
        $exp = $this->http->FindSingleNode("//span[contains(@id, 'ExpireDate')]");

        if (isset($dateFormatUS) && $dateFormatUS) {
            $this->http->Log(">>>>>> Expiration Date (American format) $exp - " . var_export(strtotime($exp), true), true);
            //# Bug in site
            if (!strtotime($exp) && isset($exp)) {
                $exp = $this->ModifyDateFormat($exp);
                $dateFormatUS = false;
                $this->http->Log("</br> >>>>>> Bug in site <<<<<<", false);
                $this->http->Log(">>>>>> Expiration Date (European format) $exp - " . var_export(strtotime($exp), true), true);
            }
        } elseif (isset($dateFormatUS) && !$dateFormatUS && isset($exp)) {
            $exp = $this->ModifyDateFormat($exp);
            $this->http->Log(">>>>>> Expiration Date (European format) $exp - " . var_export(strtotime($exp), true), true);
        }
        // Set Expiration Date
        if ($exp = strtotime($exp)) {
            $this->SetExpirationDate($exp);
        }

        // SubAccounts  // refs #4257

        //# Regional Premier Upgrades
        $nodes = $this->http->XPath->query("//div[@id = 'spanRegionalUpgradeBreakout']/div/div");
        $subAccounts = [];

        if (isset($nodes)) {
            $this->http->Log("Regional Premier Upgrades. Total nodes: " . $nodes->length);

            for ($i = 0; $i < $nodes->length; $i++) {
                $balance = $this->http->FindSingleNode("//div[@id = 'spanRegionalUpgradeBreakout']/div/div[" . ($i + 1) . "]", null, true, "/(\d*)\s*Regional/ims");
                $expiarationDate = $this->http->FindSingleNode("//div[@id = 'spanRegionalUpgradeBreakout']/div/div[" . ($i + 1) . "]", null, true, "/Expiring\s*([^<]+)/ims");
                // refs #4815
                if (isset($dateFormatUS) && !$dateFormatUS && isset($expiarationDate)) {
                    $expiarationDate = $this->ModifyDateFormat($expiarationDate);
                }

                if (isset($balance)) {
                    $subAccounts[] = [
                        'Code'           => 'RegionalPremierUpgrades' . $i,
                        'DisplayName'    => "Regional Premier Upgrades",
                        'Balance'        => $balance,
                        'ExpirationDate' => strtotime($expiarationDate),
                    ];
                }
                // if (isset($balance))
            }
            // for ($i = 0; $i < $nodes->length; $i++)
        }
        //# Global Premier Upgrades
        $nodes = $this->http->XPath->query("//div[@id = 'spanSystemUpgradeBreakout']/div/div");

        if (isset($nodes)) {
            $this->http->Log("Global Premier Upgrades. Total nodes: " . $nodes->length);

            for ($i = 0; $i < $nodes->length; $i++) {
                $balance = $this->http->FindSingleNode("//div[@id = 'spanSystemUpgradeBreakout']/div/div[" . ($i + 1) . "]", null, true, "/(\d*)\s*Global/ims");
                $expiarationDate = $this->http->FindSingleNode("//div[@id = 'spanSystemUpgradeBreakout']/div/div[" . ($i + 1) . "]", null, true, "/Expiring\s*([^<]+)/ims");
                // refs #4815
                if (isset($dateFormatUS) && !$dateFormatUS && isset($expiarationDate)) {
                    $expiarationDate = $this->ModifyDateFormat($expiarationDate);
                }

                if (isset($balance)) {
                    $subAccounts[] = [
                        'Code'           => 'Global Premier Upgrades' . $i,
                        'DisplayName'    => "Global Premier Upgrades",
                        'Balance'        => $balance,
                        'ExpirationDate' => strtotime($expiarationDate),
                    ];
                }
                // if (isset($balance))
            }
            // for ($i = 0; $i < $nodes->length; $i++)
        }

        if (count($subAccounts) > 0) {
            $this->SetProperty("CombineSubAccounts", false);
            $this->http->Log("Total subAccounts: " . count($subAccounts));
            //# Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }
        // SubAccounts

        //#No Itineraries
        if ($this->http->FindSingleNode("//tr[@id='ctl00_ContentInfo_trNoCurrentReservations']/td[contains(text(), 'No Current Reservations')]")) {
            $this->noItineraries = true;
        }
        // recent activity
        $accountPageForm = $this->http->Form;
        $accountPageFormURL = $this->http->FormURL;
        $statementDates = $this->http->FindSingleNode("//select[@name='ctl00\$ContentInfo\$drpStatementDates']/option[1]/@value");
        $url = "/web/en-US/apps/onepass/statement/recentActivity.aspx";
        $this->http->NormalizeURL($url);
        $this->http->GetURL($url);
        $lastDate = $this->http->XPath->query("//h3[contains(text(), \"Activity\")]/following::table[2]/tr[3]/td[1]");

        if ($lastDate->length > 0) {
            $this->Properties["LastActivity"] = CleanXMLValue($lastDate->item(0)->nodeValue);
        }

        if (!isset($this->Properties["LastActivity"]) && isset($statementDates)) {
            $url = "/web/en-US/apps/account/account.aspx";
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);
            $accountPageForm["ctl00\$ContentInfo\$drpStatementDates"] = $statementDates;
            $accountPageForm["ctl00\$ContentInfo\$btnViewStatement"] = "Go";
            $url = "/web/en-US/apps/account/account.aspx";
            $this->http->NormalizeURL($url);
            $this->http->PostURL($url, $accountPageForm);
            $lastDate = $this->http->XPath->query("//span[contains(@id, 'ctl00_ContentInfo_ActivityInformation_rptActivity_ctl00_rptDetailRows_ctl') and contains(@id, '_span1')]");

            if ($lastDate->length > 0) {
                $this->Properties["LastActivity"] = CleanXMLValue($lastDate->item($lastDate->length - 1)->nodeValue);
            }
        }
    }

    public function ParseAirItinerary($receiptUrl = null)
    {
        $result = [];
        $result['Kind'] = 'T';
        // ConfirmationNumber
        $nodes = $this->http->XPath->query("//span[contains(@id, 'ConfirmItineraryNumbers1_spPNR')]");

        if ($nodes->length > 0) {
            $result["RecordLocator"] = CleanXMLValue($nodes->item(0)->nodeValue);
        }
        $nodes = $this->http->XPath->query("//span[contains(@id, 'ConfirmItineraryNumbers1_spanPNR')]");

        if ($nodes->length > 0) {
            $result["RecordLocator"] = CleanXMLValue($nodes->item(0)->nodeValue);
        }

        // Passengers
        $passengers = $this->http->FindNodes('//div[contains(@class,"traveler") and not(@id)]/h4');

        if (isset($passengers[0])) {
            $result["Passengers"] = beautifulName(implode(", ", $passengers));
        }
        // TotalCharge
        $result['TotalCharge'] = $this->http->FindSingleNode('//td[contains(text(),"Total Price")]/following-sibling::td[@class="currency"]', null, true, '/([\d\s\.,]+)/');

        if (empty($result['TotalCharge'])) {
            $result['TotalCharge'] = $this->http->FindSingleNode('//td[b[contains(text(),"Total Price")]]/following-sibling::td[@class="currency"]');
        }

        if (empty($result['TotalCharge'])) {
            $result['TotalCharge'] = $this->http->FindSingleNode('//td[contains(text(),"Total")]/following-sibling::td[@class="currency"]');
        }
        $result['TotalCharge'] = preg_replace('/Conversion/ims', '', $result['TotalCharge']);
        // Tax  refs #5225
        $result['Tax'] = $this->http->FindSingleNode('//td[span[a[contains(text(),"Tax")]]]/following-sibling::td[@class="currency"]', null, true, '/([\d\s\.,]+)/');

        if (empty($result['Tax'])) {
            $result['Tax'] = $this->http->FindSingleNode('//td[span[a[contains(text(),"tax")]]]/following-sibling::td[@class="currency"]', null, true, '/([\d\s\.,]+)/');
        }
        // BaseFare
        $result['BaseFare'] = $this->http->FindSingleNode('//td[contains(text(),"Adult")]/following-sibling::td[@class="currency"]', null, true, '/([\d\s\.,]+)/');
        // Currency
        $result['Currency'] = $this->http->FindSingleNode('//td[contains(text(),"Total Price")]/following-sibling::td[@class="currency"]', null, true, '/([A-Z]{3})/');

        if (empty($result['Currency'])) {
            $result['Currency'] = $this->http->FindSingleNode('//td[b[contains(text(),"Total Price")]]/following-sibling::td[@class="currency"]', null, true, '/([A-Z]{3})/');
        }

        if (empty($result['Currency']) && isset($result['TotalCharge']) && preg_match('/([$]?)\d+/', $result['TotalCharge'])) {
            $result['Currency'] = 'USD';
        }
        // ReservationDate
        $resDate = preg_replace('/at /', '', $this->http->FindSingleNode('//span[@id="ctl00_ContentInfo_ViewRes_spanTicketed"]', null, true, '/\,(.*) Central Time/'));

        if (strtotime($resDate) !== false) {
            $result['ReservationDate'] = strtotime($resDate);
        }

        $nodes = $this->http->XPath->query("//table/tbody/tr[td/div[contains(text(),'Depart:')]]");
        $it = [];

        for ($n = 0; $n < $nodes->length; $n++) {
            $node = $nodes->item($n);
            $arSegment = [];

            // AirlineName
            $arSegment['AirlineName'] = trim($this->http->FindSingleNode('td[@class="tdSegmentDtl"]//div[@class="ocMsg"]', $node, true, '/Operated by(.+)/'), '. ');
            // Duration
            $arSegment['Duration'] = $this->http->FindSingleNode("(td[contains(@class, 'tdTrvlTime')]//span[1]/text()[2])[1]", $node);

            if (empty($arSegment['Duration'])) {
                $arSegment['Duration'] = $this->http->FindSingleNode("td[contains(@class, 'tdTrvlTime')]//span[2]", $node);
            }

            $n1 = $this->http->XPath->query("td[1]/div[2]/strong/text()", $node);
            $n1 = ($n1->length == 0) ? $this->http->XPath->query("td[1]/div[2]/strong/span[@class='fError']/text()", $node) : $n1;
            $n2 = $this->http->XPath->query("td[1]/div[3]/b/text()", $node);

            if (($n1->length == 1) && ($n2->length == 1)) {
                $arSegment["DepDate"] = $this->StrToDate($n2->item(0)->nodeValue . " " . $n1->item(0)->nodeValue);
            }
            // dep date 2 (euro format)
            $n1 = $this->http->XPath->query("td[1]/div[2]/strong/text()", $node);

            if (($n1->length == 1) && ($n2->length == 1) && (!isset($arSegment['DepDate']) || ($arSegment['DepDate'] === false))) {
                $arSegment["DepDate"] = $this->StrToDate($n2->item(0)->nodeValue . " " . $n1->item(0)->nodeValue);
            }
            // dep code
            $n1 = $this->http->XPath->query("td[1]/div[last()]/text()", $node);

            if ($n1->length > 0) {
                $s = CleanXMLValue(GetXMLNodesText($n1));

                if (preg_match("/^([^\(]+)\((\w{3})[^\)]*\)/ims", $s, $arMatch)) {
                    $arSegment["DepName"] = trim($arMatch[1]);
                    $arSegment["DepCode"] = trim($arMatch[2]);
                }
            }
            // arrive
            // arr date
            $n1 = $this->http->XPath->query("td[2]/div[2]/strong/text()", $node);
            $n1 = ($n1->length == 0) ? $this->http->XPath->query("td[2]/div[2]/strong/span[@class='fError']/text()", $node) : $n1;
            $n2 = $this->http->XPath->query("td[2]/div[3]/b/text()", $node);

            if (($n1->length == 1) && ($n2->length == 1)) {
                $arSegment["ArrDate"] = $this->StrToDate($n2->item(0)->nodeValue . " " . $n1->item(0)->nodeValue);
            }
            // arr date 2 (euro format)
            $n1 = $this->http->XPath->query("td[2]/div[2]/strong/text()", $node);

            if (($n1->length == 1) && ($n2->length == 1) && (!isset($arSegment['ArrDate']) || ($arSegment['ArrDate'] === false))) {
                $arSegment["ArrDate"] = $this->StrToDate($n2->item(0)->nodeValue . " " . preg_replace("/\+\s*\d+\s+day/ims", "", $n1->item(0)->nodeValue));
            }

            // arr code
            $n1 = $this->http->XPath->query("td[2]/div[last()]/text()", $node);

            if ($n1->length == 1) {
                $s = CleanXMLValue(GetXMLNodesText($n1));

                if (preg_match("/^([^\(]+)\((\w{3})[^\)]*\)/ims", $s, $arMatch)) {
                    $arSegment["ArrName"] = trim($arMatch[1]);
                    $arSegment["ArrCode"] = trim($arMatch[2]);
                }
            }

            // flight number
            $n1 = $this->http->XPath->query("td[4]/div[contains(text(),'Flight')]/b[1]", $node);

            if ($n1->length > 0) {
                $arSegment["FlightNumber"] = CleanXMLValue(GetXMLNodesText($n1));
            } else {
                $n1 = $this->http->XPath->query("td[5]/div[contains(text(),'Flight')]/b[1]", $node);

                if ($n1->length > 0) {
                    $arSegment["FlightNumber"] = CleanXMLValue(GetXMLNodesText($n1));
                }
            }

            if (empty($arSegment["FlightNumber"])) {
                $arSegment["FlightNumber"] = $this->http->FindSingleNode('.//div[contains(text(), "Flight:")]/b', $node, false, '/^([\dA-Z]+)$/');
            }

            // OnePass miles
            $n1 = $this->http->XPath->query(".//td[span[contains(text(),'OnePass Miles')]]/span", $node);

            if ($n1->length > 0) {
                for ($i = 0; $i < ($n1->length - 1); $i++) {
                    if (preg_match("/OnePass Miles/ims", CleanXMLValue($n1->item($i)->nodeValue))) {
                        $arSegment["OnePass Miles/Elite Qualification"] = CleanXMLValue($n1->item($i + 1)->nodeValue);

                        break;
                    }
                }
            }
            // BookingClass
            $arSegment["BookingClass"] = $this->http->FindSingleNode(".//span[contains(text(),'Fare Class:')]/b", $node, false, '/\(([A-Z]+)\)/');

            if (empty($arSegment["BookingClass"])) {
                $arSegment["BookingClass"] = $this->http->FindSingleNode(".//div[contains(text(),'Fare Class:')]/b", $node, false, '/\(([A-Z]+)\)/');
            }
            // aircraft
            $n1 = $this->http->XPath->query(".//div[contains(text(),'Aircraft:')]/b", $node);

            if ($n1->length > 0) {
                $arSegment["Aircraft"] = CleanXMLValue($n1->item(0)->nodeValue);
            }
            // meal
            $n1 = $this->http->XPath->query(".//div[contains(text(),'Meal:')]/b/text()", $node);

            if ($n1->length > 0) {
                $arSegment["Meal"] = CleanXMLValue(GetXMLNodesText($n1, ". "));
            }
            // seats
            if (isset($arSegment['DepCode']) && isset($arSegment['ArrCode'])) {
                if (preg_match_all("/{$arSegment['DepCode']} \- {$arSegment['ArrCode']}: ([^<]+)</ims", $this->http->Response['body'], $arMatches, PREG_PATTERN_ORDER)) {
                    $arMatches[1] = array_diff($arMatches[1], ['---']);
                    $arSegment["Seats"] = implode(", ", $arMatches[1]);
                }
            }

            if (isset($arSegment['FlightNumber'])) {
                if (preg_match("/([^\d]*)(\d*)/ims", $arSegment['FlightNumber'], $matches)) {
                    //echo "match {$matches[1]}&nbsp;&nbsp;{$matches[2]}<br/>";
                    if (preg_match("/Flight\s*{$matches[1]}\s*{$matches[2]}\s*is\s*operated\s*by([^\.]*)\./ims", $this->http->Response['body'], $aiMatches)) {
                        //echo "<pre>".htmlspecialchars(print_r($arMatch, true))."</pre>";
                        $arSegment['Airline'] = $aiMatches[1];
                    }
                }
            }
            // save
            if (isset($arSegment["ArrDate"]) && !isset($arSegment["ArrCode"]) && !isset($arSegment["ArrName"])) {
                continue;
            }

            if (isset($arSegment["DepDate"]) && !isset($arSegment["DepCode"]) && !isset($arSegment["DepName"])) {
                continue;
            }
            $it[$n] = $arSegment;
        }

        // Segments
        if (count($it) > 0) {
            $result["TripSegments"] = $it;
        }
        // AccountNumber
        $n1 = $this->http->XPath->query("//td[contains(text(),'Frequent Flyer:')]/following::td[2]");

        if ($n1->length > 0) {
            $ar = [];

            for ($n = 0; $n < $n1->length; $n++) {
                $ar[] = preg_replace("/^CO\-/ims", "", CleanXMLValue($n1->item($n)->nodeValue));
            }
            $result["AccountNumbers"] = implode(", ", $ar);
        }

        // schedule change
        if (preg_match("/There has been a schedule change to your reservation/ims", $this->http->Response['body'])) {
            $result["ScheduleChange"] = "There has been a schedule change to your reservation";
        }

        // Pricing details
        if ((!isset($result['TotalCharge'])
                || !isset($result['Tax'])
                || !isset($result['Currency']))
            && isset($receiptUrl)
        ) {
            // TODO: how to calculate
            //$this->http->GetURL($receiptUrl);
        }

        //$result['Cancelled'] = $result['Hidden'] = false;
        return $result;
    }

    public function ParseCarItinerary()
    {
        $result = [];
        $result['Kind'] = 'L';
        // Confirmation Number
        $result['Number'] = $this->http->FindSingleNode("//span[contains(@id, 'ConfirmReservationNumber_lblReserveNumber')]");
        // Pickup
        $pickup = $this->http->FindSingleNode("//span[contains(@id, 'ConfirmCarDetails_lblPickup')]");

        if (preg_match("/[a-z]{3}\.,\s*[a-z]{3}\.\s*\d+,\s*\d+/ims", $pickup, $match)) {
            $temp = strtotime($match[0]);

            if ($temp !== false) {
                $result['PickupDatetime'] = $temp;
            }
        }
        $result['PickupLocation'] = preg_replace("/[a-z]{3}\.,\s*[a-z]{3}\.\s*\d+,\s*\d+/ims", "", $pickup);
        // Dropoff
        $dropoff = $this->http->FindSingleNode("//span[contains(@id, 'ConfirmCarDetails_lblReturn')]");

        if (preg_match("/[a-z]{3}\.,\s*[a-z]{3}\.\s*\d+,\s*\d+/ims", $dropoff, $match)) {
            $temp = strtotime($match[0]);

            if ($temp !== false) {
                $result['DropoffDatetime'] = $temp;
            }
        }
        $result['DropoffLocation'] = preg_replace("/[a-z]{3}\.,\s*[a-z]{3}\.\s*\d+,\s*\d+/ims", "", $dropoff);
        // CarType
        $result['CarType'] = $this->http->FindSingleNode("//span[contains(@id, 'ConfirmCarDetails_lblCarType')]");
        // RenterName
        $result['RenterName'] = $this->http->FindSingleNode("//span[contains(@id, 'ConfirmDriverName_lblConfirmDriverName')]");
        // Rental Rate
        $result['RentalRate'] = $this->http->FindSingleNode("//span[contains(@id, 'ConfirmCarDetails_lblRentalRate')]");
        // Duration
        $result['Duration'] = $this->http->FindSingleNode("//span[contains(@id, 'ConfirmCarDetails_lblDuration')]");

        if ($href = $this->http->FindSingleNode('//a[contains(@id, "ManageOptions_linkPrint")]/@href')) {
            $this->http->NormalizeURL($href);
            $this->http->GetURL($href);
            $result['RentalCompany'] = $this->http->FindSingleNode('//div[contains(@id, "displayInfo_divCarCompany") and not(contains(@id, "Label"))]');
        }

        return $result;
    }

    public function ParseItineraries()
    {
        if ($this->noItineraries) {
            return $this->noItinerariesArr();
        }

        $this->http->FilterHTML = false;
        $url = "/web/en-US/apps/reservation/default.aspx";
        $this->http->NormalizeURL($url);
        $this->http->GetURL($url);
        $result = [];

        // parse cancelled reservation
        // TODO: car or air cancelled reservations
        // $this->ParseCancellerReservations($result);

        $its = $this->http->XPath->query("//div[contains(@id, 'panelCurrent')]//tr[position()>1]");
        $this->http->Log("Found {$its->length} current reservations' nodes");
        $airUrls = [];
        $carUrls = [];
        $viewPattern = ".//span[starts-with(normalize-space(text()), '%s')]/following-sibling::span[1]//a[text()='%s']/@href";

        for ($n = 0; $n < $its->length; $n++) {
            $it = $its->item($n);
            // Air
            $additionally = [];
            $nodes = $this->http->XPath->query(sprintf($viewPattern, 'Flight:', 'View Receipt'), $it);

            for ($i = 0; $i < $nodes->length; $i++) {
                $additionally[] = "http://www.united.com/web/en-US/apps/reservation/" . $nodes->item($i)->nodeValue;
            }
            $nodes = $this->http->XPath->query(sprintf($viewPattern, 'Flight:', 'View'), $it);

            for ($i = 0; $i < $nodes->length; $i++) {
                $url = ['common' => "https://www.united.com/web/en-US/apps/reservation/" . $nodes->item($i)->nodeValue];

                if (isset($additionally[$i])) {
                    $url['receipt'] = $additionally[$i];
                }
                $airUrls[] = $url;
            }
            // Car
            $nodes = $this->http->XPath->query(sprintf($viewPattern, 'Car:', 'View'), $it);

            for ($i = 0; $i < $nodes->length; $i++) {
                $carUrls[] = "https://www.united.com/web/en-US/apps/reservation/" . $nodes->item($i)->nodeValue;
            }
        }
        // Full Air Search
        $nodes = $this->http->XPath->query("//div[contains(@id, 'panelCurrent')]//span[contains(@id, 'ItinRecap_ManageOptionsFlight_spanOptions')]");
        $this->http->Log("Found " . $nodes->length . " additional air reservations");

        foreach ($nodes as $node) {
            // ends-with($str1, $str2) ===
            // $str2 = substring($str1, string-length($str1)- string-length($str2) +1)
            $endsWith = './/span[%2$s = substring(%1$s, string-length(%1$s) - string-length(%2$s) + 1)]//a/@href';

            $common = $this->http->FindSingleNode(sprintf($endsWith, '@id', '"ItinRecap_ManageOptionsFlight_spanView"'), $node);
            $found = false;

            foreach ($airUrls as $url) {
                if (strpos($url['common'], $common) !== false) {
                    $found = true;
                }
            }

            if (!$found) {
                $receipt = $this->http->FindSingleNode(sprintf($endsWith, '@id', '"ItinRecap_ManageOptionsFlight_spanViewReceipt"'), $node);
                $airUrls[] = [
                    'common'  => "https://www.united.com/web/en-US/apps/reservation/{$common}",
                    'receipt' => "https://www.united.com/web/en-US/apps/reservation/{$receipt}",
                ];
            }
        }

        // Air
        $this->http->Log("Found " . sizeof($airUrls) . " air reservations");

        foreach ($airUrls as $url) {
            //$this->http->NormalizeURL($url);
            $this->http->GetURL($url['common']);

            if (preg_match("/\/reservation\/(hotel|car|trip)\//ims", $this->http->currentUrl())) {
                //# It isn't working with Guzzle
                //			if (preg_match("/\/reservation\/(hotel|car|trip)\//ims", $this->http->currentUrl()))
                continue;
            }

            $receipt = (isset($url['receipt'])) ? $url['receipt'] : null;
            $itinerary = $this->ParseAirItinerary($receipt);

            if (count($itinerary) > 0) {
                $result[] = $itinerary;
            }
        }
        // Car
        $this->http->Log("Found " . sizeof($carUrls) . " car reservations");

        foreach ($carUrls as $url) {
            //$this->http->NormalizeURL($url);
            $this->http->GetURL($url);
            /*if (preg_match("/\/reservation\/(hotel|car|trip)\//ims", $this->http->currentUrl()))
                continue;*/

            $itinerary = $this->ParseCarItinerary();

            if (count($itinerary) > 0) {
                $result[] = $itinerary;
            }
        }
        //$result[] = array('Cancelled' => true, 'ConfirmationNumber' => 'A8V0JT', 'RecordLocator' => 'A8V0JT');
        //DieTrace(print_r($result));
        return $result;
    }

    public function ParseCancellerReservations(&$result)
    {
        $its = $this->http->XPath->query("//div[contains(@id, 'panelCancel')]//tr[position()>1]//span[text() = \"Confirmation Number:\"]/following::span[1]");
        $this->http->Log("Found {$its->length} cancelled reservations");

        for ($n = 0; $n < $its->length; $n++) {
            $it = $its->item($n)->nodeValue;
            //$number = $this->http->XPath->query('//span[text() = "Confirmation Number:"]/following::span[1]', $it);
            //if($number->length > 0 && $value = $number->item(0)->nodeValue)
            $result[] = ['Kind' => 'R', 'Cancelled' => true, 'RecordLocator' => $it];
        }
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "http://www.united.com/web/en-US/apps/reservation/import.aspx";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm("aspnetForm")) {
            $this->sendNotification("mileageplus - failed to retrieve itinerary by conf #", 'all', true,
                "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['LastName']}");

            return null;
        }
        $this->http->Form[urldecode("ctl00%24ContentInfo%24FindRes%24ConfNum%24txtPNR")] = $arFields["ConfNo"];
        $this->http->Form[urldecode("ctl00%24ContentInfo%24FindRes%24LastName%24txtLName")] = $arFields["LastName"];
        $this->http->Form[urldecode("ctl00%24ContentInfo%24FindRes%24ConfimationOptions")] = "rdofight";
        $this->http->Form[urldecode("ctl00%24ContentInfo%24FindRes%24Button1")] = "Find";
        unset($this->http->Form['ctl00$CustomerHeader$btnSearch']);
        unset($this->http->Form['ctl00$CustomerHeader$ChangeBtn']);

        if (!$this->http->PostForm()) {
            return null;
        }

        if (preg_match("/<\!\-\-ErrCode\:[^>]*><br\/><br\/>([^<]+)<\!\-\-ErrCode/ims", $this->http->Response['body'], $arMatch)) {
            return $arMatch[1];
        }

        if (preg_match("/class=\"fError\" style=\"color:#CC0000;\">([^<]+)</ims", $this->http->Response['body'], $arMatch)) {
            return $arMatch[1];
        }
        $it = $this->ParseAirItinerary();

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function GetHistoryColumns()
    {
        return [
            "Activity Date"                 => "PostingDate",
            "Description"                   => "Description",
            "Activity Type"                 => "Info",
            "Premier Qualifying / Miles"    => "Info",
            "Premier Qualifying / Segments" => "Info",
            "Premier Qualifying / Dollars"  => "Info",
            "Award Miles"                   => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->http->Log('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $result = [];
        $startTimer = microtime(true);
        $page = 0;

        $params = ['', '?MP=1', '?OPHist=1'];

        foreach ($params as $param) {
            $this->http->GetURL('http://www.united.com/web/en-US/apps/mileageplus/statement/statement.aspx' . $param);
            $timespans = $this->http->FindNodes('//select[@name="ctl00$ContentInfo$drpStatementDates"]/option/@value');

            for ($i = 0; $i < count($timespans) && !$this->collectedHistory; $i++) {
                if ($i > 0) {
                    $this->http->ParseForm();
                    $date = strtotime($timespans[$i]);

                    if ($date !== false && isset($startDate) && $date < $startDate) {
                        $this->http->Log("breaking, date {$timespans[$i]} lower than startDate");

                        break;
                    }
                    $this->http->Form['ctl00$ContentInfo$drpStatementDates'] = $timespans[$i];
                    $this->http->PostForm();
                }

                $page++;
                $this->http->Log("[Page: {$page}]");
                $startIndex = sizeof($result);
                $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
            }
        }
        $endTimer = microtime(true);
        $this->http->Log("[Time parsing: " . ($endTimer - $startTimer) . "]");
        // Sort
        usort($result, function ($a, $b) {
            if ($a['Activity Date'] == $b['Activity Date']) {
                return 0;
            }

            return ($a['Activity Date'] < $b['Activity Date']) ? 1 : -1;
        });
        $this->http->Log("[Time sorting: " . (microtime(true) - $endTimer) . "]");

        $keys = array_keys($this->GetHistoryColumns());
        $count = count($result);

        for ($i = 0; $i < $count - 1; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $found = isset($result[$i]) && isset($result[$j]);

                for ($k = 0; $k < count($keys) && $found && isset($result[$i][$keys[$k]]) && isset($result[$j][$keys[$k]]); $k++) {
                    $found = $found && ($result[$i][$keys[$k]] == $result[$j][$keys[$k]]);
                }

                if ($found) {
                    unset($result[$i]);
                    $this->http->Log("Duplicate removed: $i equals to $j");
                }
            }
        }

        return array_values($result);
    }

    public function getLinkNextPage()
    {
        return false;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        // Airline Activity
        $nodes = $this->http->XPath->query("//table/tr[td/span[text()='Airline Activity']]/following-sibling::tr");
        $this->http->Log("Airline Activity rows: " . $nodes->length);
        $airlineActivity = 0;

        if ($nodes->length > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                if (sizeof($this->http->FindNodes("td", $nodes->item($i))) == 1) {
                    $this->http->Log("Airline Activity. Found " . $airlineActivity . " items");

                    break;
                }

                if (!$this->http->FindSingleNode("td[1]/span", $nodes->item($i))) {
                    continue;
                }

                $firstCell = $this->http->FindSingleNode("td[1]/span", $nodes->item($i));

                if (!preg_match("/\d+\/\d+\/\d+/ims", $firstCell)) {
                    continue;
                }

                $postDate = strtotime($this->http->FindSingleNode("td[1]/span", $nodes->item($i)));

                if ($postDate === false) {
                    continue;
                }

                $result[$startIndex]['Activity Type'] = 'Airline Activity';
                $result[$startIndex]['Activity Date'] = $postDate;
                $desc = $this->http->FindNodes("td[2]/span/text()", $nodes->item($i));

                if (!is_array($desc)) {
                    $desc = [$desc];
                }
                $result[$startIndex]['Description'] = implode(' ', $desc);
                $result[$startIndex]['Regional Upgrades'] = '';
                $result[$startIndex]['System-wide Upgrades'] = '';
                $result[$startIndex]['Award Miles'] = $this->http->FindSingleNode("td[5]/span[1]", $nodes->item($i), true, "/[\d\,\.\-]+/ims");
                $result[$startIndex]['Bonus'] = $this->http->FindSingleNode("td[6]/span[1]", $nodes->item($i));
                $result[$startIndex]['Total'] = $this->http->FindSingleNode("td[7]/span[1]", $nodes->item($i), true, "/[\d\,\.\-]+/ims");
                $result[$startIndex]['Premier Qualifying / Miles'] = $this->http->FindSingleNode("td[9]/span[1]", $nodes->item($i), true, "/[\d\,\.\-]+/ims");
                $result[$startIndex]['Premier Qualifying / Segments'] = $this->http->FindSingleNode("td[10]/span[1]", $nodes->item($i), true, "/[\d\,\.\-]+/ims");

                $startIndex++;
                $airlineActivity++;
            }
        }

        // Non-Airline Activity
        $nodes = $this->http->XPath->query("//table/tr[td/span[text()='Non-Airline Activity']]/following-sibling::tr");
        $this->http->Log("Non-Airline Activity rows: " . $nodes->length);
        $nonAirlineActivity = 0;

        if ($nodes->length > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                if (sizeof($this->http->FindNodes("td", $nodes->item($i))) == 1) {
                    $this->http->Log("Non-Airline Activity. Found " . $nonAirlineActivity . " items");

                    break;
                }

                if (!$this->http->FindSingleNode("td[1]/span", $nodes->item($i))) {
                    continue;
                }

                $firstCell = $this->http->FindSingleNode("td[1]/span", $nodes->item($i));

                if (!preg_match("/\d+\/\d+\/\d+/ims", $firstCell)) {
                    continue;
                }

                $postDate = strtotime($this->http->FindSingleNode("td[1]/span", $nodes->item($i)));

                if ($postDate === false) {
                    continue;
                }

                $result[$startIndex]['Activity Type'] = 'Non-Airline Activity';
                $result[$startIndex]['Activity Date'] = $postDate;
                $desc = $this->http->FindNodes("td[2]/span/text()", $nodes->item($i));

                if (!is_array($desc)) {
                    $desc = [$desc];
                }
                $result[$startIndex]['Description'] = implode(' ', $desc);
                $result[$startIndex]['Regional Upgrades'] = '';
                $result[$startIndex]['System-wide Upgrades'] = '';
                $result[$startIndex]['Award Miles'] = $this->http->FindSingleNode("td[5]/span[1]", $nodes->item($i), true, "/[\d\,\.\-]+/ims");
                $result[$startIndex]['Bonus'] = $this->http->FindSingleNode("td[6]/span[1]", $nodes->item($i));
                $result[$startIndex]['Total'] = $this->http->FindSingleNode("td[7]/span[1]", $nodes->item($i), true, "/[\d\,\.\-]+/ims");
                $result[$startIndex]['Premier Qualifying / Miles'] = $this->http->FindSingleNode("td[9]/span[1]", $nodes->item($i), true, "/[\d\,\.\-]+/ims");
                $result[$startIndex]['Premier Qualifying / Segments'] = $this->http->FindSingleNode("td[10]/span[1]", $nodes->item($i), true, "/[\d\,\.\-]+/ims");

                $startIndex++;
                $nonAirlineActivity++;
            }
        }

        // Award Activity
        $nodes = $this->http->XPath->query("//table/tr[td/span[text()='Award Activity']]/following-sibling::tr");
        $awardActivity = 0;

        if ($nodes->length > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                if (sizeof($this->http->FindNodes("td", $nodes->item($i))) == 1) {
                    $this->http->Log("Award Activity. Found " . $awardActivity . " items");

                    break;
                }

                if (!$this->http->FindSingleNode("td[1]/span", $nodes->item($i))) {
                    continue;
                }

                $firstCell = $this->http->FindSingleNode("td[1]/span", $nodes->item($i));

                if (!preg_match("/\d+\/\d+\/\d+/ims", $firstCell)) {
                    continue;
                }

                $postDate = strtotime($this->http->FindSingleNode("td[1]/span", $nodes->item($i)));

                if ($postDate === false) {
                    continue;
                }

                $result[$startIndex]['Activity Type'] = 'Award Activity';
                $result[$startIndex]['Activity Date'] = $postDate;
                $desc = $this->http->FindNodes("td[2]/span/text()", $nodes->item($i));

                if (!is_array($desc)) {
                    $desc = [$desc];
                }
                $result[$startIndex]['Description'] = implode(' ', $desc);
                $result[$startIndex]['Regional Upgrades'] = $this->http->FindSingleNode("td[3]/span[1]", $nodes->item($i));
                $result[$startIndex]['System-wide Upgrades'] = $this->http->FindSingleNode("td[4]/span[1]", $nodes->item($i));
                $result[$startIndex]['Award Miles'] = $this->http->FindSingleNode("td[5]/span[1]", $nodes->item($i), true, "/[\d\,\.\-]+/ims");
                $result[$startIndex]['Bonus'] = $this->http->FindSingleNode("td[6]/span[1]", $nodes->item($i));
                $result[$startIndex]['Total'] = $this->http->FindSingleNode("td[7]/span[1]", $nodes->item($i), true, "/[\d\,\.\-]+/ims");

                $startIndex++;
                $awardActivity++;
            }
        }

        /*$nodes = $this->http->XPath->query("//table[tr/td/span[contains(text(), 'Reward Activity')]]//tr[contains(@style, 'vertical-align:top')]");
        if ($nodes->length > 0) {
            $this->http->Log("Found {$nodes->length} items");
            for ($i = 0; $i < $nodes->length; $i++) {
                $postDate = strtotime($this->http->FindSingleNode("td[1]", $nodes->item($i)));
                if ($postDate === false)
                    continue;
                if (isset($startDate) && $postDate < $startDate)
                    break;
                $result[$startIndex]['Activity Date'] = $postDate;
                $desc = $this->http->FindNodes("td[2]/span/text()", $nodes->item($i));
                if (!is_array($desc))
                    $desc = array($desc);
                $result[$startIndex]['Description'] = implode(' ', $desc);
                $result[$startIndex]['Regional Upgrades'] = $this->http->FindSingleNode("td[3]/span[1]", $nodes->item($i));
                $result[$startIndex]['System-wide Upgrades'] = $this->http->FindSingleNode("td[4]/span[1]", $nodes->item($i));
                $result[$startIndex]['Miles'] = $this->http->FindSingleNode("td[5]/span[1]", $nodes->item($i), true, "/[\d\,\.]+/ims");
                $result[$startIndex]['Bonus'] = $this->http->FindSingleNode("td[6]/span[1]", $nodes->item($i));
                $result[$startIndex]['Total'] = $this->http->FindSingleNode("td[7]/span[1]", $nodes->item($i), true, "/[\d\,\.]+/ims");
                $startIndex++;
            }
        }*/
        return $result;
    }

    public function StrToDate($sDate)
    {
        $sDate = trim(preg_replace("/[.\,]/ims", "", CleanXMLValue($sDate)));

        return strtotime($sDate);
    }

    /* didn't find emails
    function ParseBrokenETicket() {
        $result = ["Kind" => "T"];
        $result["RecordLocator"] = $this->http->FindSingleNode("//*[text()[contains(., 'Confirmation:')]]", null, true, "/Confirmation: ([A-Z\d]{6})/");
        //passengers block
        $block = $this->http->FindSingleNode("(//*[contains(., 'eTicket Number') and contains(., 'Seats') and not(contains(., 'INFORMATION')) and not(contains(., 'FLIGHT'))])[1]");
        if ($block && preg_match_all("/([A-Z]+\/[A-Z]+) \d{7,}/", $block, $m))
            $result['Passengers'] = $m[1];
        // segments block
        $block = $this->http->FindSingleNode("(//*[contains(., 'FLIGHT') and contains(., 'INFORMATION') and contains(., 'Departure') and not(contains(., 'eTicket Number'))])[1]");
        if ($block && preg_match_all("/(?P<date>\d{1,2}[A-Z]{3}\d{2}) (?P<airline>[A-Z]{2})(?P<number>\d+) (?P<class>\S+) (?P<depname>[^\)]+) \((?P<depcode>[A-Z]{3})[^\)]*\) (?P<depdate>\d{1,2}\:\d{2} [AP]M) (?P<arrname>[^\)]+) \((?P<arrcode>[A-Z]{3})[^\)]*\) (?P<arrdate>\d{1,2}\:\d{2} [AP]M)/", $block, $m, PREG_SET_ORDER)) {
            $result["TripSegments"] = [];
            foreach ($m as $info) {
                $segment = [
                    "AirlineName" => $info["airline"],
                    "FlightNumber" => $info["number"],
                    "DepName" => $info["depname"],
                    "DepCode" => $info["depcode"],
                    "DepDate" => strtotime($info["date"]." ".$info["depdate"]),
                    "ArrName" => $info["arrname"],
                    "ArrCode" => $info["arrcode"],
                    "ArrDate" => strtotime($info["date"]." ".$info["arrdate"]),
                    "BookingClass" => $info["class"],
                    ];
                if ($segment["DepDate"] && $segment["ArrDate"] && $segment["DepDate"] > $segment["ArrDate"])
                    $segment["ArrDate"] = strtotime("+1 day", $segment["ArrDate"]);
                $result["TripSegments"][] = $segment;
            }
        }
        // fair info
        $block = $this->http->FindSingleNode("(//*[contains(., 'eTicket Total') and contains(., 'airfare you paid') and not(contains(., 'FLIGHT'))])[1]");
        if ($block) {
            if (preg_match("/eTicket Total: ([\d\.\,]+)/", $block, $m))
                $result["TotalCharge"] = str_ireplace(",", "", $m[1]);
            if (preg_match("/The airfare you paid on this itinerary totals: ([\d\.\,]+)/", $block, $m))
                $result["BaseFare"] = str_ireplace(",", "", $m[1]);
            if (preg_match("/The taxes, fees, and surcharges paid total: ([\d\.\,]+)/", $block, $m))
                $result["Tax"] = str_ireplace(",", "", $m[1]);
            if (preg_match("/The airfare you paid on this itinerary totals: [\d\.\,]+ (\w{3})/", $block, $m))
                $result["Currency"] = $m[1];
        }
        return ["Itineraries" => [$result]];
    }
    */
}
