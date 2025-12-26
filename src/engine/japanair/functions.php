<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\japanair\QuestionAnalyzer;

class TAccountCheckerJapanair extends TAccountChecker
{
    use SeleniumCheckerHelper;

    use ProxyList;
    private $country = null;

    public function TuneFormFields(&$arFields, $values = null)
    {
        $arFields['Login2']['Options'] = TAccountCheckerJapanair::JapanAirRegions();
        ArrayInsert($arFields, "Login2", true, ["Login3" => [
            "Type"      => "string",
            "Required"  => false,
            "Caption"   => "Web Password",
            "InputType" => 'password',
            "Note"      => 'You need to fill in this information if you want AwardWallet to track your Expiration Date for this program.', ]]); /*review*/
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['NoCookieURL'] = true;

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
//        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        if (in_array($this->AccountFields['Login'], [
            '406940274',
            '324085484',
            '316398933',
            '407211233',
            '319885185',
            '400373906',
            '406993018',
            '406998438',
            '407456496',
            '314793579',
            '316074771', // hk
            '407246040', // ar
            '407376098', // ar
            '407376112', // ar
            '310584255', // ar
            '407470098', // ar
            '407470083', // ar
            '408989051', // ar
            '0371130', // America
            '690037895', // ca
            '408335930', // id
            '336506412', // cn
            '405815162', // ar
            '200297550', // uk
            '405177483', // ar
            '408770633', // ar
        ])
        ) {
            $this->AccountFields['Login2'] = 'ja';
            $this->logger->notice('fixed Region => ' . $this->AccountFields['Login2']);
        }

        if (in_array($this->AccountFields['Login2'], [
            'hu',
            'br',
        ])
            || in_array($this->AccountFields['Login'], [
                '408371921',
                '408309315',
                '324085484',
                '407211233',
                '401829115',
                '201753550',
                '303887401',
                '404967370', //tw
                '337815099', //ja
                '408100767', //ja
                '208294792', // cn
                '202237637', // ja
                '408475484', // ja
                '408380950', // hk
                '409348965', // ja
                '406619996', // ja
                '407034301', // ja
                '409359529', // ja
                '409692651', // ja
                '0371130', // America
                '410150875', // es
                '405634124', // ja
                '402740132', // ja
                '407479145', // ja
                '405316059', // ja
            ])
        ) {
            $this->AccountFields['Login2'] = 'ar';
            $this->logger->notice('fixed Region => ' . $this->AccountFields['Login2']);
        }

        if (in_array($this->AccountFields['Login'], [
            '407300922',
            '409182817', // dk
            '203887927', // tw
        ])
            || $this->AccountFields['Login2'] == 'dk'
        ) {
            $this->AccountFields['Login2'] = 'nl';
            $this->logger->notice('fixed Region => ' . $this->AccountFields['Login2']);
        }

        if (in_array($this->AccountFields['Login'], [
            '405783561', //uk
            '207727963', //ja
            '405046427', // ar
            '409219480', // ja
            '407377726', // ar
            '208412612', // ja
            '405644721', // ja
            '207738800', // ja
        ])
        ) {
            $this->AccountFields['Login2'] = 'au';
            $this->logger->notice('fixed Region => ' . $this->AccountFields['Login2']);
        }

        switch ($this->AccountFields['Login2']) {
            case 'Europe'://deprecated
                $url = 'https://www.de.jal.co.jp/er/en/jmb/';

                break;

            case 'br':
                $url = 'https://www.br.jal.co.jp/brl/pt/';
//                $url = 'https://www.jal.co.jp/br/pt/?city=BSB';

                break;

            case 'be':
                $url = 'https://www.nl.jal.co.jp/nll/en/?country=be';

                break;

            case 'cz':
                $url = 'https://www.at.jal.co.jp/atl/en/?country=cz';

                break;

            case 'dk':
                $url = 'https://www.nl.jal.co.jp/nll/en/?country=dk';

                break;

            case 'es':
                $url = 'https://www.es.jal.co.jp/esl/en/?country=es';

                break;

            case 'Japan'://deprecated
            case 'ja':
                $url = 'https://www.jal.co.jp/en/jmb/';

                break;

            case 'Asia'://deprecated
            case 'hl':
                $url = 'https://www.ar.jal.co.jp/arl/en/jmb/?country=hl';

                break;

            case 'ie':
                $url = 'https://www.uk.jal.co.jp/ukl/en/?city=DUB';

                break;

            case 'mx':
                $url = 'https://www.ar.jal.co.jp/arl/en/?city=MEX';

                break;

            case 'pl':
                $url = 'https://www.at.jal.co.jp/atl/en/?country=pl';

                break;

            case 'pt':
                $url = 'https://www.jal.co.jp/es/en/?city=LIS';

                break;

            case 'ru':
                $url = 'https://www.uk.jal.co.jp/ukl/en/?country=ru';

                break;

            case 'se':
                $url = 'https://www.nl.jal.co.jp/nll/en/?country=se';

                break;

            case 'sg':
                $url = 'https://www.sg.jal.co.jp/sgl/en/';

                break;

            case 'America'://deprecated
            case 'ar':
            case 'ca':
            case '':
                $url = 'https://www.ar.jal.co.jp/arl/en/jmb/';

                break;

            case 'hk':
            case 'au':
                $url = "https://www.{$this->AccountFields['Login2']}.jal.co.jp/{$this->AccountFields['Login2']}l/en/";

                break;

            default:
                $url = "https://www.{$this->AccountFields['Login2']}.jal.co.jp/{$this->AccountFields['Login2']}l/en/";

                break;
        }// switch ($this->AccountFields['Login2'])

        return $this->parseForm($url);
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        switch ($this->AccountFields['Login2']) {
            case 'Japan'://deprecated
            case 'ja':
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'We are currently experiencing heavy traffic on our website and unable to process your request. ')]")) {
                    throw new CheckRetryNeededException(2, 7);
                }

                break;
        }

        if ($message = $this->http->FindSingleNode("//div[@class=\"errorMessageBlockA01\"]//li[2]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("//p[
                contains(text(), 'server is temporarily unable')
                or contains(text(), 'Sorry, this service is temporarily unavailable due to system maintenance.')
            ]
            | //img[@alt = \"Sorry, the server is currently busy. Please try again later.\"]/@alt
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The proxy server received an invalid response from an upstream server.
        if ($message = $this->http->FindPreg("/The proxy server received an invalid\s*response from an upstream server\./ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function tryToloadPage()
    {
        $this->logger->notice(__METHOD__);

        if (in_array($this->http->Response['code'], [504, 404])) {
            sleep(5);
            $this->http->GetURL($this->http->currentUrl());

            if ($this->http->Response['code'] == 504) {
                sleep(5);
                $this->http->GetURL($this->http->currentUrl());

                if ($this->http->Response['code'] == 504) {
                    throw new CheckRetryNeededException(2, 10);
                }
            }
        }
    }

    public function Login()
    {
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        if (!$this->http->PostForm() && $this->http->Response['code'] != 504) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();

        if (!isset($response->authenticated)) {
            return $this->checkErrors();
        }

        if (isset($response->error_message)) {
            $message = $response->error_message;
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'The JMB membership number or password you entered is incorrect. Please check and try again.')
                || strstr($message, 'Your current password cannot be used')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Your account is locked. Please reset your password and try again.')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if (
            $response->authenticated == '/jsp/response_for_react.jsp'
            && isset($response->maskmail)
        ) {
            $this->State['FormURL'] = $formURL;
            $form['ORG_ID'] = "undefined";
            $form['ID'] = "undefined";
            $form['lowLevelSessionFlg'] = "undefined";
            $this->State['Form'] = $form;

            $question = "The one-time password was sent to {$response->maskmail}.";

            if (!QuestionAnalyzer::isOtcQuestion($question)) {
                $this->sendNotification("need to check QuestionAnalyzer");
            }

            $this->AskQuestion($question, null, "Question");

            return false;
        }

        $this->http->GetURL($response->authenticated);

        $this->tryToloadPage();

        if ($this->http->getCookieByName("memNo")) {
            return true;
        }
        // fix for new regions
        if ($this->http->FindSingleNode("//script[contains(@src, 'getTopJSON_en')]/@src")) {
            $this->sendNotification("deprecated // RR");
            $language = 'en';

            if ($this->AccountFields['Login2'] == 'br') {
                $language = 'pt';
            } elseif ($this->AccountFields['Login2'] == 'cz') {
                $language = 'er/en';
            }
            $link = "https://www.{$this->country}.jal.co.jp/{$language}/jmb/";
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);

            $this->tryToloadPage();

            return true;
        }// if ($this->http->FindSingleNode("//script[contains(@src, 'getTopJSON_en')]/@src"))

        $errorCode = $this->http->FindPreg("/errorCode=([^\&]+)/", false, $this->http->currentUrl());
        // JMB membership number or PIN is not correct.
        if ($errorCode == 'DZ00021' || $this->http->FindSingleNode("//li[contains(text(), 'JMB membership number or PIN is not correct.')]")) {
            throw new CheckException("JMB membership number or PIN is not correct.", ACCOUNT_INVALID_PASSWORD);
        }
        // The information you entered could not be validated. In order to safeguard your personal information, we will terminate the service.
        if ($errorCode == 'DZ00022') {
            throw new CheckException("The information you entered could not be validated. In order to safeguard your personal information, we will terminate the service.", ACCOUNT_INVALID_PASSWORD);
        }
        // The JAL Mileage Bank enrollment process is not complete.
        if ($errorCode == 'DZ00024') {
            throw new CheckException("The JAL Mileage Bank enrollment process is not complete.", ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Members who are not registered in the [...] Region cannot log in from this screen.
         * More information on using the JAL website is available by clicking This page will open in a new windowthe link below.
         */
        $error = $this->http->FindSingleNode('//*[@class="s4" and (@color="#990000" or @style = "color:#990000;")]');
        $this->logger->error("[Error]: {$error}");

        if ($errorCode == 'DZ00023' || $this->http->FindPreg("/Members who are not registered in the [\w\s\-\/]+ Region cannot log in from this screen/ims", false, $error)) {
            throw new CheckException("To update this Japan Airlines (JMB) account you need to select the country in the ‘Region’ field. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/

        if ($error == 'System error. Please check the system maintenance page or try from the top page.') {
            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
        }

        $this->DebugInfo = $errorCode;

        return false;
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $this->http->Form = $this->State['Form'];
        $this->http->SetInputValue("PWD", $answer);
        $this->http->FormURL = $this->State['FormURL'];

        $this->http->PostForm();
        $response = $this->http->JsonLog();

        if (!empty($response->error_message)) {
            $message = $response->error_message ?? null;

            if ($message == 'Your one-time password does not match. Please check and try again.') {
                $this->AskQuestion($this->Question, $message, "Question");
            } else {
                $this->DebugInfo = $message;
            }

            return false;
        }

        $this->http->GetURL($response->authenticated);

        return true;
    }

    public function Parse()
    {
        // fixed 'ja' region
        if ($this->http->currentUrl() == 'https://www.jal.co.jp/jp/en/') {
            $this->http->GetURL("https://www.jal.co.jp/jp/en/jmb/");
        }

        // Open "DETAILS" - refs #12452
        $detailsLink = $this->http->FindSingleNode('(//a[
                (
                    contains(text(), "DETAILS")
                    or contains(text(), "More information")
                    or contains(text(), "Más información")
                    or contains(text(), "View recent miles transactions")
                )
                and contains(@href, "CnfMlg")]/@href)[1]
        ');

        if ($this->http->ParseForm("mileDetailFrm")) {
            $detailsForm = $this->http->Form;
            $detailsFormURL = $this->http->FormURL;
        }

        $locale = $this->http->FindSingleNode('//input[@name = "locale"]/@value');

        if (!$locale) {
            if (
                strstr($this->http->currentUrl(), 'esl')
                || strstr($this->http->currentUrl(), 'del')
                || strstr($this->http->currentUrl(), '/en/er/jmb/')
            ) {
                $locale = 'ER';
            } elseif (strstr($this->http->currentUrl(), 'arl')) {
                $locale = 'AR';
            } elseif (
                strstr($this->http->currentUrl(), 'aul')
                || strstr($this->http->currentUrl(), 'twl')
                || strstr($this->http->currentUrl(), 'ukl')
                || strstr($this->http->currentUrl(), 'sr/jmb/')
            ) {
                $locale = 'SR';
            }
        }

        if (
            $details = $this->http->FindSingleNode("//script[contains(@src, 'getTopJSON_en')]/@src")
                ?? $this->http->FindPreg("/var src = '(https:\/\/www121\.jal\.co\.jp\/JmbWeb\/' \+ urlRegion \+ '\/getTopJSON_en[^\']+)';/")
                ?? $this->http->FindPreg("/script.src = '(https:\/\/www121\.jal\.co\.jp\/JmbWeb\/JR\/getTopJSON_en[^\']+)';/")
        ) {
            if (stristr($details, 'urlRegion') && !$locale) {
                return;
            }

            $details = str_replace("' + urlRegion + '", $locale, $details);

            $this->http->NormalizeURL($details);
            $this->http->GetURL($details);
//            $this->logger->debug(var_export($this->http->Response['body'], true), ['pre' => true]);
            // Sorry, the server is currently busy. Please try again later.
            if ($message = $this->http->FindSingleNode("//em[contains(text(), 'Sorry, the server is currently busy.')]")) {
                throw new CheckRetryNeededException(2, 10, $message);
            }

            if ($this->http->Response['code'] != 200) {
                $this->logger->error("something went wrong");

                return;
            }

            // provider bug fix
            if ($this->http->FindPreg("/^\s*JLJS_121SRTop\(\);\s*$/")) {
                throw new CheckRetryNeededException(3, 10);
            }// if ($this->http->FindPreg("/^\s*JLJS_121SRTop\(\);\s*$/"))
        }// if ($details = $this->http->FindSingleNode("//script[contains(@src, 'getTopJSON_en')]/@src"))

        $status = $this->http->FindPreg("/jmbStatus:\"([^\"]+)/");

        if (isset($status)) {
            $this->logger->debug("Status: {$status}");
            $statusArray = [
                '0'  => 'Member',
                '1'  => 'Crystal',
                '2'  => 'JMB Crystal',
                '3'  => 'JAL Global Club Crystal',
                '4'  => 'Sapphire',
                '5'  => 'Sapphire',
                '6'  => 'Diamond',
                '7'  => 'JAL Global Club Diamond',
                '8'  => 'JGC Premier',
                '9'  => 'Member', // AccountID: 5483425: JAL Card //'JGC Premier',
                '10' => 'JAL Card',
                '11' => 'JAL Club Est Diamond',
                '12' => 'JMB Sapphire',
                '13' => 'JAL Club Est Crystal',
            ];

            if (isset($statusArray[$status])) {
                $this->SetProperty('Status', $statusArray[$status]);
            } else {
                $this->sendNotification("japanair. Unknown status {$status}");
            }
        }// if (isset($status))
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg("/dispMemName:\"([^\"]+)/")));
        // Current mileage balance
        $balance = $this->http->FindPreg("/mileBalance:\"([^\"]+)/");

        if (isset($balance) && empty($this->AccountFields['Login3'])) {// todo: do not touch this check
            $this->SetBalance($balance);
        }
        $this->logger->debug("Balance: {$balance}");
        // FlyOn points
        $this->SetProperty("FlyOnPoints", $this->http->FindPreg("/flyonPoint:\"([^\"]+)/"));
        // FlyOn flights
        $this->SetProperty("Flights", $this->http->FindPreg("/flyonCount:\"([^\"]+)/"));
        // Jal group Flyon Points
        $this->SetProperty("JalGroupFlyonPoints", $this->http->FindPreg("/jalFlyonPoint:\"([^\"]+)/"));
        // Jal group Flyon Flights
        $this->SetProperty("JalGroupFlyonFlights", $this->http->FindPreg("/jalFlyonCount:\"([^\"]+)/"));
        // JMB Membership Number
        $this->SetProperty("AccountNumber", $this->http->FindPreg("/member_no:\"([^\"]+)/"));

        if (!$detailsLink && isset($detailsForm, $detailsFormURL)) {
            $this->http->Form = $detailsForm;
            $this->http->FormURL = $detailsFormURL;
            $this->http->setMaxRedirects(10);
            $this->http->PostForm();
            $this->http->setMaxRedirects(7);

            $detailsBalance = $this->http->FindNodes('//em[contains(text(), "Current Mileage Balance")]');

            if (!$detailsBalance) {
                if (// very strange accounts, not working only in parser
                    $this->http->FindSingleNode("//span[contains(text(), 'This service is not available to members who are not registered in this region.')]")
                    && in_array($this->AccountFields['Login'], [
                        '336510061',
                        '003503274',
                        '408808426',
                    ])
                ) {
                    $this->logger->notice("This service is not available to members who are not registered in this region.");
                    $this->SetBalance($balance);
                }

                $this->http->GetURL("https://www.jal.co.jp/en/?city=TYO");
                $detailsLink = $this->http->FindSingleNode('//a[contains(text(), "miles / FLY ON Points")]/@href');
            }
        }

        // Open "DETAILS" - refs #12452
        if ($detailsLink || isset($detailsBalance)) {
            if (!isset($detailsBalance)) {
                $this->http->NormalizeURL($detailsLink);
                $this->http->GetURL($detailsLink);
            }

            if ($this->http->ParseForm("mileDetailFrm")) {
                $this->http->PostForm();
            }

            // provider bug fix
            if (
                $this->http->Response['code'] == 404
                && $this->http->FindSingleNode('//em[normalize-space(text()) = "Sorry, the server is currently busy. Please try again later."]')
            ) {
                sleep(5);
                $this->http->GetURL($detailsLink);
            }

            // refs #14741
            if ($message = $this->http->FindSingleNode("//h1[contains(normalize-space(text()), 'Set your Web Password')]")) {
                $this->logger->notice($message);
                $this->SetBalance($balance);
            }

            // refs #3455
            if ($this->http->ParseForm("frmSecondLoginRF_en") && !empty($this->AccountFields['Login3'])) {
                $this->sendNotification("web password is needed // RR");

                $this->http->SetInputValue("comSecondLoginParam", $this->AccountFields['Login3']);
                $this->http->SetInputValue("submit12.x", "34");
                $this->http->SetInputValue("submit12.y", "5");
                $this->http->PostForm();

                // refs #14741
                if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Please enter \"Web Password\" in the correct format.')]")) {
                    $this->logger->error($message);
                    $this->SetBalance($balance);
                }
            }// if ($this->http->ParseForm("frmSecondLoginRF_en") && !empty($this->AccountFields['Login3']))

            $exp = $this->http->XPath->query('//h2[@class="blueBox" and contains(text(), "Individual Account")]/following-sibling::*[1]//table[@class = "tableA02 termmile"]/tbody/tr[1]/td');
            $mls = $this->http->XPath->query('//h2[@class="blueBox" and contains(text(), "Individual Account")]/following-sibling::*[1]//table[@class = "tableA02 termmile"]/tbody/tr[2]/td');
            $this->logger->debug("Total {$exp->length} exp nodes were found");
            $this->logger->debug("Total {$exp->length} miles nodes were found");

            if ($exp->length > 0 && $mls->length > 0 && $exp->length == $mls->length) {
                $today = strtotime(date('m/d/y'));

                for ($i = 0; $i < $exp->length; $i++) {
                    $date = DateTime::createFromFormat('Y/m/d', preg_replace('/[^0-9\/]/ims', '', $exp->item($i)->nodeValue));
                    $date = strtotime($date->format('m/d/y'));

                    if ($date < $today) {
                        continue;
                    }

                    if ($m = $this->http->FindPreg('/([\d\,\.\s]+)mil/', false, $mls->item($i)->nodeValue)) {
                        if ($m != 0) {
                            // ExpiringBalance
                            $this->SetProperty("ExpiringBalance", $m);
                            // Exp date
                            $this->SetExpirationDate($date);

                            break;
                        }// if ($m[1] != 0)
                    }// if (preg_match('/(\d*)mil/', $mls->item($i)->nodeValue, $m))
                }// for ($i = 0; $i < $exp->length; $i++)
            }// if ($exp->length > 0 && $mls->length > 0 && $exp->length == $mls->length)

            // refs #14741

            // Lifetime Mileage
            $this->SetProperty('LifetimeMileage', $this->http->FindSingleNode("//dt[em[contains(text(), 'Lifetime Mileage')]]/following-sibling::dd[1]/span"));
            // Balance - Individual Account: Current Mileage Balance
            $this->SetBalance($this->http->FindSingleNode("//h2[@class=\"blueBox\" and contains(text(), \"Individual Account\")]/following-sibling::*[1]//dt[em[contains(text(), 'Current Mileage Balance')]]/following-sibling::dd[1]/span"));

            // Family account

            $familyBalance = $this->http->FindSingleNode("//h2[@class=\"blueBox\" and contains(text(), \"Family Account\")]/following-sibling::*[1]//dt[em[contains(text(), 'Current Mileage Balance')]]/following-sibling::dd[1]/span");

            if ($familyBalance) {
                $subAccount = [
                    'Code'        => 'japanairFamilyAccount',
                    'DisplayName' => 'Family account',
                    'Balance'     => $familyBalance,
                ];
                $exp = $this->http->XPath->query('//h2[@class="blueBox" and contains(text(), "Family Account")]/following-sibling::*[1]//table[@class = "tableA02 termmile"]/tbody/tr[1]/td');
                $mls = $this->http->XPath->query('//h2[@class="blueBox" and contains(text(), "Family Account")]/following-sibling::*[1]//table[@class = "tableA02 termmile"]/tbody/tr[2]/td');
                $this->logger->debug("Total {$exp->length} exp nodes were found");
                $this->logger->debug("Total {$exp->length} miles nodes were found");

                if ($exp->length > 0 && $mls->length > 0 && $exp->length == $mls->length) {
                    $today = strtotime(date('m/d/y'));

                    for ($i = 0; $i < $exp->length; $i++) {
                        $date = DateTime::createFromFormat('Y/m/d', preg_replace('/[^0-9\/]/ims', '', $exp->item($i)->nodeValue));
                        $date = strtotime($date->format('m/d/y'));

                        if ($date < $today) {
                            continue;
                        }

                        if ($m = $this->http->FindPreg('/([\d\,\.\s]+)mil/', false, $mls->item($i)->nodeValue)) {
                            if ($m != 0) {
                                // ExpiringBalance
                                $subAccount["ExpiringBalance"] = $m;
                                // Exp date
                                $subAccount['ExpirationDate'] = $date;

                                break;
                            }// if ($m != 0) {
                        }// if (preg_match('/(\d*)mil/', $mls->item($i)->nodeValue, $m))
                    }// for ($i = 0; $i < $exp->length; $i++)
                }// if ($exp->length > 0 && $mls->length > 0 && $exp->length == $mls->length)

                $this->SetProperty("CombineSubAccounts", false);
                $this->AddSubAccount($subAccount);
            }// if ($familyBalance)
        }// if ($detailsLink)
    }

    public function ParseItineraries()
    {
        //$this->http->SetProxy($this->proxyReCaptcha());
        // Domestic Flights
        $headers = [
            'Accept'        => '*/*',
            'Ama-Client-Ref'=> '7cccbd54-1518-4953-9f83-0e08f35d30a9--eae5a997-7a6c-4201-a722-0a7b57b787a0',
            'X-Api-Key'     => 'JZWuY6OJ5M2IfvIgZVRMA7dhbjk7jTtga0lclevt',
            'Referer'       => 'https://booking.jal.co.jp/',
            'Origin'        => 'https://booking.jal.co.jp',
        ];
        $this->http->PostURL('https://api.dom.jal.co.jp/rmweb-api/authorize/token?linkId=01&appType=pbkg&dateKey=undefined&hashValue=undefined&grant_type=client_credentials', '', $headers);
        $authorize = $this->http->JsonLog();

        if (!isset($authorize->data->access_token)) {
            return [];
        }
        $headers['Authorization'] = "Bearer {$authorize->data->access_token}";
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://api.dom.jal.co.jp/rmweb-api/purchase/orders', $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $domesticNoIt = $this->http->FindPreg('/,"bookingListItem":\[\]\}\}/');

        foreach ($response->data->bookingListItem ?? [] as $item) {
            $this->ParseItineraryMin($item);
        }

        // International Flights
        $headers = [
            'Accept'           => '*/*',
            'x-requested-with' => 'XMLHttpRequest',
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.jal.co.jp/cgi-bin/jal/common_rn/getEnc1A.cgi?_=' . date("UB"), $headers);
        $this->http->RetryCount = 2;
        $data = [
            'SITE'         => 'J019J019',
            'LANGUAGE'     => 'GB',
            'COUNTRY_SITE' => 'JAL_JR_JP',
            'ENC'          => $this->http->getCookieByName('enc1A'),
            'ENCT'         => '2',
            'DEVICE_TYPE'  => 'DESKTOP',
        ];
        $this->http->PostURL('https://book-i.jal.co.jp/JLInt/dyn/air/postBooking/myBookings', $data);

        $url = $this->http->FindPreg('#var url = "(https://jallogin.jal.co.jp/sso/AuthorizationEndpoint.+?)"#');
        $this->http->GetURL($url);

        $response = $this->http->FindPreg('#<script type="application/json" id="clientSideData">(.+?)</script>#');
        $response = $this->http->JsonLog($response);

        if (empty($response->PAGE->DATA->outputs->jlBookings->bookings)
            && ($this->http->FindPreg('/"outputs":\{"jlBookings":\{"@c":"\d+"\}\},/') || $this->http->FindPreg('/"outputs":\{\},/'))
            && $domesticNoIt) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        if (!isset($response->PAGE->DATA->outputs->jlBookings->bookings, $response->jsessionid)) {
            return [];
        }

        foreach ($response->PAGE->DATA->outputs->jlBookings->bookings ?? [] as $booking) {
            $arFields = [
                'FirstName'     => $booking->firstName,
                'LastName'      => $booking->lastName,
                'FlightNumber'  => $booking->firstFlight->flightNumber,
                'AirlineCode'   => $booking->firstFlight->marketingAirline,
                'ConfNo'        => $booking->recordLocator->code,
                'DepartureDate' => date('Ymd0000', $booking->firstValidDate / 1000),
            ];
            $bookingResult = $this->postBooking($response->jsessionid, $arFields);

            if (isset($bookingResult->PAGE->DATA->jlPnrRecap->airRecap->flightBounds)) {
                $this->ParseItinerary($bookingResult);
            }elseif (isset($bookingResult->dictionaries->flight)) {
                $this->ParseItineraryV2($bookingResult);
            }
            sleep(random_int(1,3));
        }

        return [];
    }

    public function GetConfirmationFields()
    {
        // trips/addConfirmation.php
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "AirlineCode" => [
                "Type"     => "string",
                "Size"     => 2,
                "Required" => true,
            ],
            "FlightNumber" => [
                "Type"     => "string",
                "Size"     => 40,
                "Required" => true,
            ],
            "DepartureDate" => [
                "Type"     => "date",
                "Size"     => 40,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
            "FirstName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('FirstName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.jal.co.jp/ar/en/";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.jal.co.jp/cgi-bin/jal/common_rn/getEnc1A.cgi?_=' . date("UB"), $headers);
        $this->http->RetryCount = 2;

        $data = [
            'SITE'         => 'J019J019',
            'LANGUAGE'     => 'GB',
            'COUNTRY_SITE' => 'JAL_AR_US',
            'ENC'          => $this->http->getCookieByName('enc1A', '.jal.co.jp'),
            'ENCT'         => '2',
            'DEVICE_TYPE'  => 'DESKTOP',
        ];
        $this->http->PostURL('https://book-i.jal.co.jp/JLInt/dyn/air/postBooking/myBookings', $data);

        $response = $this->http->FindPreg('#<script type="application/json" id="clientSideData">(.+?)</script>#');
        $response = $this->http->JsonLog($response);

        if (empty($response->jsessionid)) {
            $this->logger->error('Failed to parse first conf form');

            return null;
        }

        $bookingResult = $this->postBooking($response->jsessionid, $arFields);
        if (isset($bookingResult->PAGE->DATA->jlPnrRecap->airRecap->flightBounds)) {
            $this->ParseItinerary($bookingResult);
        } elseif (isset($bookingResult->dictionaries->flight)) {
            $this->ParseItineraryV2($bookingResult);
        }

        return null;
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip(TAccountCheckerJapanair::JapanAirRegions()))) {
            $region = 'ar';
        }

        return $region;
    }

    private function postBooking($jsessionid, $arFields)
    {
        $this->logger->notice(__METHOD__);

        $departureDate = date('Ymd0000', strtotime($arFields['DepartureDate']));
        $data = [
            'COUNTRY_SITE'           => 'JAL_JR_JP',
            'LANGUAGE'               => 'GB',
            'SITE'                   => 'J019J019',
            'DDS_CURRENT_REQUEST_ID' => '0',
            'DEVICE_TYPE'            => 'desktop',
            'FLOW_MODE'              => 'REVENUE',
            'DDS_FROM_PAGE'          => 'BKGL',
            'STREAM'                 => 'postbooking',
            'inputPnr'               => '{"@c":"pnr.input.JLPnrInput","passengerOfPnr":{"@c":"pnr.input.JLPnrIdentityInformation","firstName":"' . $arFields['FirstName'] . '","lastName":"' . $arFields['LastName'] . '","middleName":""},"isComingFromBookingList":true,"flightCode":"' . $arFields['FlightNumber'] . '","departureDate":"' . $departureDate . '","airlineCode":"' . $arFields['AirlineCode'] . '","bookingRef":"' . $arFields['ConfNo'] . '"}',
        ];
        $this->http->PostURL("https://book-i.jal.co.jp/JLInt/dyn/air/postBooking/retrievePnr;JAL_SESSION_ID=$jsessionid", $data);
        $response = $this->http->FindPreg('#<script type="application/json" id="clientSideData">(.+?)</script>#', true, null, false);

        if (!isset($response)) {

            $this->http->PostURL("https://api-des.jal.co.jp/v1/security/oauth2/token", [
                'client_id' => '6U8xrBPBzqfDthlMLhqAoKNzcaMcrF2x',
                'client_secret' => 'tuJP28rK0I7OkRBo',
                'grant_type' => 'client_credentials',
                'guest_office_id' => 'TYOJL08SR',
            ]);
            $token = $this->http->JsonLog();

            $headers = [
                'Accept' => 'application/json',
                'Authorization' => "Bearer $token->access_token"
            ];
            $this->http->GetURL("https://api-des.jal.co.jp/airlines/JL/v2/purchase/orders/{$arFields['ConfNo']}?lastName={$arFields['LastName']}&showOrderEligibilities=true&checkServicesAndSeatsIssuanceCurrency=false", $headers);

        }


        return $this->http->JsonLog($response ?? null);
    }

    private function ParseItineraryMin($item)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Parse Itinerary #{$item->pnrReference}", ['Header' => 3]);

        $f = $this->itinerariesMaster->add()->flight();
        $f->general()->confirmation($item->pnrReference);
        $f->general()->status(beautifulName($item->purchaseStatus));

        foreach ($item->names as $traveller) {
            $f->general()->traveller(beautifulName($traveller->firstName . ' ' . $traveller->lastName));
        }

        foreach ($item->segmentsInfo as $item) {
            $s = $f->addSegment();
            $s->airline()->name($this->http->FindPreg('/^([A-Z]{2,3})/', false, $item->flightNumber));
            $s->airline()->number($this->http->FindPreg('/^[A-Z]{2,3}\s*(\d+)/', false, $item->flightNumber));
            $s->departure()->code($item->originAirportCode);
            $s->arrival()->code($item->destinationAirportCode);
            // 2024-07-05T10:45:00+09:00
            $s->departure()->date2($this->http->FindPreg('/^(\d+-.+?)\+\d+:\d+/', false, $item->dateOfTravel));
            $s->arrival()->date2($this->http->FindPreg('/^(\d+-.+?)\+\d+:\d+/', false, $item->dateOfArrival));

            $s->extra()->cabin($item->cabin);
        }
    }

    public function ParseItineraryV2($data)
    {
        $this->logger->notice(__METHOD__);
        $f = $this->itinerariesMaster->createFlight();
        $f->general()->confirmation($data->data->id);
        $this->logger->info("Parse Itinerary #{$data->data->id}", ['Header' => 3]);

        foreach ($data->data->travelers as $traveler) {
            foreach ($traveler->names as $name) {
                $f->general()->traveller("$name->firstName $name->lastName");
            }
        }

        foreach ($data->data->frequentFlyerCards ?? [] as $frequent) {
            $f->program()->account($frequent->cardNumber, false);
        }

        $travelDocuments = $data->data->travelDocuments ?? [];
        foreach ($travelDocuments as $travel) {
            $f->issued()->ticket($travel->id, false);
            /*$total += $travel->price->total;
            $currency = $travel->price->currencyCode;*/
        }
        /*$f->price()->total($total);
        $f->price()->currency($currency);*/

        foreach ($data->data->air->bounds as $bound) {
            foreach ($bound->flights as $bFlight) {
                $flight = $data->dictionaries->flight->{$bFlight->id};
                $s = $f->addSegment();
                $s->airline()->name($flight->marketingAirlineCode);
                $s->airline()->number($flight->marketingFlightNumber);
                $s->departure()->code($flight->departure->locationCode);
                $s->departure()->terminal($flight->departure->terminal ?? null, false, true);
                $s->departure()->date2($this->http->FindPreg('/^(\d{4}-.+?\d+:\d+)/', false,
                    $flight->departure->dateTime));
                $s->arrival()->code($flight->arrival->locationCode);
                $s->arrival()->terminal($flight->arrival->terminal ?? null, false, true);
                $s->arrival()->date2($this->http->FindPreg('/^(\d{4}-.+?\d+:\d+)/', false, $flight->arrival->dateTime));

                $s->extra()->aircraft($data->dictionaries->aircraft->{$flight->aircraftCode});
                $s->extra()->bookingCode($flight->meals->bookingClass ?? null, false, true);
                if ($flight->meals->mealCodes ?? null) {
                    $s->extra()->meals($flight->meals->mealCodes ?? null);
                }

                $seats = $data->data->seats ?? [];
                foreach ($seats as $seat) {
                    if ($seat->flightId != $bFlight->id) {
                        continue;
                    }

                    foreach ($seat->seatSelections as $seatSelection) {
                        $s->extra()->seat($seatSelection->seatNumber);
                    }
                }
            }
        }
        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }
    private function ParseItinerary($data)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Parse Itinerary #{$data->PAGE->DATA->context->jlPnrInput->bookingRef}", ['Header' => 3]);

        $f = $this->itinerariesMaster->add()->flight();
        $f->general()->confirmation($data->PAGE->DATA->context->jlPnrInput->bookingRef);
        $tickets = $travellers = [];

        if (!isset($data->PAGE->DATA->jlPnrRecap)) {
            if (isset($data->PAGE->DATA->errorMessages[0]->text)) {
                $this->logger->error($data->PAGE->DATA->errorMessages[0]->text);
                $this->itinerariesMaster->removeItinerary($f);

                return $data->PAGE->DATA->errorMessages[0]->text;
            }

            return [];
        }

        if (isset($data->PAGE->DATA->jlPnrRecap->tickets)) {
            foreach ($data->PAGE->DATA->jlPnrRecap->tickets as $ticket) {
                $tickets[] = $ticket->ticketNumber;
                $travellers[] = "{$ticket->associatedTraveller->identityInformation->firstName} {$ticket->associatedTraveller->identityInformation->lastName}";
            }

            $f->issued()->tickets(array_unique($tickets), false);
        }

        if (!empty($travellers)) {
            $f->general()->travellers(array_unique($travellers));
        }

        $seats = [];

        foreach ($data->PAGE->DATA->jlPnrRecap->travellersInformation->travellers as $traveller) {
            if (isset($traveller->seatInformations)) {
                foreach ($traveller->seatInformations as $seatInformations) {
                    $seats[$seatInformations->segmentId] =
                        $seatInformations->seatAssignment
                        ?? $seatInformations->dcsSeatAssignment
                        ?? null
                    ;
                }
            }
        }

        foreach ($data->PAGE->DATA->jlPnrRecap->airRecap->flightBounds as $flightBounds) {
            foreach ($flightBounds->flightSegments as $flightSegment) {
                if ($flightSegment->isDisrupted === true) {
                    $this->logger->notice('Skip: disrupted segment');

                    continue;
                }
                $s = $f->addSegment();
                $s->airline()->name($flightSegment->flightIdentifier->marketingAirline);
                $s->airline()->number($flightSegment->flightIdentifier->flightNumber);

                $code = $this->http->FindPreg('/T\w*_([A-Z]{3})/', false, $flightSegment->originLocation);
                $terminal = $this->http->FindPreg('/T(\w*)_[A-Z]{3}/', false, $flightSegment->originLocation);
                $s->departure()->code($code);
                $s->departure()->date($flightSegment->flightIdentifier->originDate / 1000);
                $s->departure()->terminal($terminal, true);
                $code = $this->http->FindPreg('/T\w*_([A-Z]{3})/', false, $flightSegment->destinationLocation);
                $terminal = $this->http->FindPreg('/T(\w*)_[A-Z]{3}/', false, $flightSegment->destinationLocation);
                $s->arrival()->code($code);

                if ($s->getDepDate() === $flightSegment->destinationDate / 1000) {
                    $s->arrival()->date($flightSegment->destinationDate / 1000 + 60);
                } else {
                    $s->arrival()->date($flightSegment->destinationDate / 1000);
                }
                $s->arrival()->terminal($terminal, true);

                if (isset($flightSegment->numberOfStops) && $flightSegment->numberOfStops > 0) {
                    $s->extra()->stops($flightSegment->numberOfStops);
                }
                $s->extra()->aircraft($flightSegment->equipment, true, true);

                $hours = floor($flightSegment->duration / 1000 / 60 / 60);
                $minutes = $flightSegment->duration % 60;
                $s->extra()->duration(sprintf('%01dh%02dm', $hours, $minutes));

                foreach ($flightSegment->cabins as $cabin) {
                    $s->extra()->cabin($cabin->name);

                    foreach ($cabin->rbds as $rbd) {
                        $s->extra()->bookingCode($rbd->code);
                    }
                }

                if (!empty($seats[$flightSegment->id])) {
                    $s->extra()->seat($seats[$flightSegment->id]);
                }
            }
        }

        if (empty($f->getSegments())) {
            $this->logger->error('Skip: Reservation has no segments');
            $this->itinerariesMaster->removeItinerary($f);
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function parseForm($url)
    {
        $this->logger->notice(__METHOD__);
        $this->http->NormalizeURL($url);
        $this->http->GetURL($url);

//        $this->tryToloadPage();

        if (
            !$this->http->ParseForm(null, '//form[contains(@id, "memberLogin") or contains(@action, "JMBmemberTop_en")]')
            && ($loginLink = $this->http->FindSingleNode('//a[@class = "login-Judg" and @href != "javascript:void(0)"]/@href'))
        ) {
            $this->sendNotification("deprecated // RR");
            $this->http->NormalizeURL($loginLink);
            $this->http->GetURL($loginLink);
        }

        if (
            $this->http->ParseForm('JS_spLogin')
        ) {
            $this->http->PostForm();
        }

        if ($this->http->ParseForm(null, '//form[contains(@id, "memberLogin") or contains(@action, "JMBmemberTop_")]')) {
            $this->logger->debug("FormURL {$this->http->FormURL}");
            // Japan
            if (
                $this->http->FormURL == 'https://www121.jal.co.jp/JmbWeb/JR/{0}Top_en.do'
            ) {
                $this->sendNotification("deprecated // RR");
                $this->http->FormURL = "https://www121.jal.co.jp/JmbWeb/JR/DomTop_en.do";
                $this->logger->debug("Change FormURL to {$this->http->FormURL}");
                $this->sendNotification("deprecated thread // RR");
            }
            // USA
            elseif ($this->http->FormURL == 'https://www.ar.jal.co.jp/arl/en/jmb/javascript:void(0);'
                || $this->http->FormURL == 'https://www.ar.jal.co.jp/arl/en/javascript:void(0);'
                || $this->http->FormURL == 'https://www.ar.jal.co.jp/arl/en/jmb/'
                || $this->http->FormURL == 'https://www.ar.jal.co.jp/arl/en/jmb/?country=hl'
            ) {
                $this->sendNotification("deprecated // RR");
                $this->http->FormURL = "https://www121.jal.co.jp/JmbWeb/AR/JMBmemberTop_en.do";
                $this->logger->debug("Change FormURL to {$this->http->FormURL}");
                $this->http->SetInputValue("country", "ar");
                $this->http->SetInputValue("language", "en");
                $this->sendNotification("deprecated thread // RR");
            }
            // Europe
            elseif ($this->http->FormURL == 'https://www.de.jal.co.jp/er/en/jmb/javascript:void(0);'
                    || $this->http->FormURL == 'https://www.de.jal.co.jp/er/en/jmb/'
            ) {
                $this->sendNotification("deprecated // RR");
                $this->http->FormURL = "https://www121.jal.co.jp/JmbWeb/ER/JMBmemberTop_en.do";
                $this->logger->debug("Change FormURL to {$this->http->FormURL}");
                $this->http->SetInputValue("country", "de");
                $this->http->SetInputValue("language", "en");
                $this->sendNotification("deprecated thread // RR");
            } else {
                $this->logger->notice("main thread");
                $countryPrefix = $this->http->FindSingleNode("(//a[contains(text(), 'Flight Mileage') or contains(@href, '/en/jmb/news-seeall/#promotion') or contains(@href, '/en/jmb/jmb-login/info/')]/@href)[1]", null, true, "/\/(\w{2})\/en\/jmb/ims");

                if ($this->AccountFields['Login2'] == 'br') {
                    $countryPrefix = 'AR';
                    $language = "pt";
                } else {
                    $language = 'en';

                    if (in_array($this->AccountFields['Login2'], ['es', 'pt'])) {
                        $countryPrefix = 'ER';
                    }
                }

                if (
                    !strstr($this->http->currentUrl(), '?country=')
                    || !($this->country = $this->http->FindPreg("/www\.([^\.]+)\.jal\.co\.jp/ims", false, $this->http->currentUrl()))
                ) {
                    $this->country = $this->AccountFields['Login2'];
                }

                if ($this->AccountFields['Login2'] == 'ie') {
                    $this->country = 'uk';
                }

                if (in_array($this->AccountFields['Login2'], ['Asia', 'mx', 'America', 'ca', 'hl', 'gu'])) {
                    $this->country = 'ar';
                }

                if (in_array($this->AccountFields['Login2'], ['cz', 'pl'])) {
                    $this->country = 'at';
                }

                if ($this->AccountFields['Login2'] == 'Europe') {
                    $this->country = 'de';
                }

                if (in_array($this->AccountFields['Login2'], ['be', 'se'])) {
                    $this->country = 'nl';
                }

                if (in_array($this->AccountFields['Login2'], ['pt'])) {
                    $this->country = 'es';
                }

                if (in_array($this->AccountFields['Login2'], ['ja', 'Japan'])) {
                    $countryPrefix = 'JR';
                }

                if (in_array($this->AccountFields['Login2'], ['tw', 'id', 'in', 'hk', 'au', 'cn', 'sg', 'th', 'kr', 'my', 'vn', 'ph', 'nz'])) {
                    $countryPrefix = 'SR';
                }

                if (in_array($this->AccountFields['Login2'], ['nl', 'de', 'es', 'uk', 'Europe', 'ch', 'ru', 'it', 'at', 'se', 'fr', 'cz', 'be', 'eg', 'pl', 'ie'])) {
                    $countryPrefix = 'ER';
                }

                if (in_array($this->AccountFields['Login2'], ['mx', 'gu'])) {
                    $countryPrefix = 'AR';
                }

                if (!$countryPrefix) {
                    return false;
                }
                $this->country = $this->country == '' ? 'ar' : $this->country;
                $this->logger->info("country => {$this->country}");

                if (in_array($this->AccountFields['Login2'], ['ja', 'Japan'])) {
                    $this->http->FormURL = "https://www121.jal.co.jp/JmbWeb/" . strtoupper($countryPrefix) . "/JmbTop_en.do";
                } else {
                    $this->http->FormURL = "https://www121.jal.co.jp/JmbWeb/" . strtoupper($countryPrefix) . "/JMBmemberTop_en.do";
                }
                $this->logger->debug("Change FormURL to {$this->http->FormURL}");
                $this->http->SetInputValue("country", $this->country);
                $this->http->SetInputValue("language", $language);
                $this->http->PostForm();
            }
        }

        parse_str(parse_url($this->http->currentUrl(), PHP_URL_QUERY), $output);

        if (!isset($output['AUTH_TYPE'])) {
            $this->logger->error("AUTH_TYPE not found");

            if (
                strstr($this->http->Error, 'Network error 28 - Connection timed out after')
                || $this->http->Error == 'Network error 52 - Empty reply from server'
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(4, $this->attempt * 5);
            }

            return $this->checkErrors();
        }

        $this->http->Form = [
            "AUTH_TYPE"     => $output['AUTH_TYPE'],
            "SITE_ID"       => $output['SITE_ID'],
            "MESSAGE_AUTH"  => $output['MESSAGE_AUTH'],
            "AUTHENTICATED" => $output['AUTHENTICATED'],
            "Fingerprint"   => "f5a2d31cc9d59abcfbb3c26ac12fafc1",
        ];
        $this->http->FormURL = 'https://jallogin.jal.co.jp/account/login';
        $this->http->PostForm();
        $response = $this->http->JsonLog();

        if (!isset($response->authenticated)) {
            return $this->checkErrors();
        }

        // AccountID: 4111152
        if (is_numeric($this->AccountFields["Login"]) & strlen($this->AccountFields["Login"]) == 7) {
            $this->AccountFields["Login"] = '00' . $this->AccountFields["Login"];
        }

        $this->http->FormURL = 'https://jallogin.jal.co.jp/account/login';
        $this->http->SetInputValue("ORG_ID", "jmbNo");
        $this->http->SetInputValue("ID", $this->AccountFields["Login"]);
        $this->http->SetInputValue("PWD", $this->AccountFields["Pass"]);
        $this->http->SetInputValue("lowLevelSessionFlg", "false");
        $this->http->SetInputValue("Fingerprint", "f5a2d31cc9d59abcfbb3c26ac12fafc1");

        return true;
    }

    private static function JapanAirRegions()
    {
        return [
            ""   => "Select a region",
            "au" => "Australia",
            "at" => "Austria",
            "be" => "Belgium",
            "br" => "Brazil",
            "bg" => "Bulgaria",
            "ca" => "Canada",
            "cn" => "China",
            "hr" => "Croatia",
            "cz" => "Czech Republic",
            "dk" => "Denmark",
            "ee" => "Estonia",
            "fi" => "Finland",
            "fr" => "France",
            "de" => "Germany",
            "gu" => "Guam",
            "hk" => "Hong Kong",
            //            "hu" => "Hungary",
            "is" => "Iceland",
            "in" => "India",
            "id" => "Indonesia",
            "ie" => "Ireland",
            "it" => "Italy",
            "ja" => "Japan",
            "kr" => "Korea",
            "lv" => "Latvia",
            "lt" => "Lithuania",
            "lu" => "Luxembourg",
            "my" => "Malaysia",
            "mx" => "Mexico",
            "eg" => "Middle East & Africa",
            "nl" => "Netherlands",
            "nz" => "New Zealand",
            "no" => "Norway",
            "ph" => "Philippines",
            "pl" => "Poland",
            "pt" => "Portugal",
            "ro" => "Romania",
            "ru" => "Russia",
            "sg" => "Singapore",
            "si" => "Slovenia",
            "es" => "Spain",
            "se" => "Sweden",
            "ch" => "Switzerland",
            "tw" => "Taiwan",
            "th" => "Thailand",
            "uk" => "U.K.",
            "ar" => "U.S.A.(Continental)",
            "hl" => "U.S.A.(Hawaii)",
            "vn" => "Vietnam",
        ];
    }

    private function selenium($url)
    {
        $this->logger->notice(__METHOD__);
        $allCookies = array_merge($this->http->GetCookies(".al.co.jp"), $this->http->GetCookies(".al.co.jp", "/", true));
        //$allCookies = array_merge($allCookies, $this->http->GetCookies("card.starbucks.com.sg"), $this->http->GetCookies("card.starbucks.com.sg", "/", true));


        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefoxPlaywright();
            $selenium->disableImages();

            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();

            try {
                $selenium->http->GetURL("https://book-i.jal.co.jp/J");
                foreach ($allCookies as $key => $value)
                    $selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".al.co.jp"]);
                $selenium->http->GetURL($url);
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            sleep(6);
            //$login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'sign-in-customer-login-email']"), 5);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");

        } catch (
        NoSuchDriverException
        | StaleElementReferenceException
        | Facebook\WebDriver\Exception\WebDriverCurlException
        $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 5);
            }
        }

        return true;
    }
}
