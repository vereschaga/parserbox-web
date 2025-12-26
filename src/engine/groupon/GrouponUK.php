<?php

/**
 * GrouponUK.
 */
class GrouponUK extends GrouponAbstract
{
    public $urlProvider = 'www.groupon.co.uk';
    public $baseDomainCookie = '.groupon.co.uk';
    public $startCookies = [];
    public $app_id;

    public function LoadLoginForm()
    {
        global $sPath;
        $this->checker->http->FilterHTML = false;
        // reset cookie
        $this->checker->http->removeCookies();

        if (sizeof($this->startCookies)) {
            foreach ($this->startCookies as $cookie) {
                $this->checker->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path']);
            }
        }

        switch ($this->loginType) {
            case "basic": default:
                // getting the HTML code
                $this->checker->http->getURL('https://' . $this->urlProvider . '/login');
                // parsing form on the page
                if (!$this->checker->http->ParseForm('jLoginForm') && !$this->checker->http->ParseForm(null, 1, true, "//form[@data-bhw = 'LoginForm']")) {
                    $this->CheckErrors();

                    return false;
                }
                // enter the login and password
                $this->checker->http->Form['email'] = $this->login;
                $this->checker->http->Form['password'] = $this->password;

            break;

            case "facebook":
                throw new CheckException('Sorry, login via Facebook is not supported anymore', ACCOUNT_PROVIDER_ERROR); /*checked*/
                $this->checker->facebook = new FacebookConnect();

                try {
                    $obj = $this;
                    $this->checker->facebook->setAppId($this->app_id)
                         ->setRedirectURI('https://' . $this->urlProvider . '/login')
                         ->setChecker($this->checker)
                         ->setCredentials($this->login, $this->password)
                         ->setBaseDomain($this->baseDomainCookie)
                         ->setCallbackFunction(function ($session, $fc, $checker) use ($obj) {
                             if (isset($fc->userInfo)) {
                                 $url = "https://'.$this->urlProvider.'/Registration.action?facebookLogin=&" .
                                    "registerView.facebookId={$fc->userInfo->id}&" .
                                    "registerView.email={$fc->userInfo->email}&" .
                                    "registerView.userAddress.firstName={$fc->userInfo->first_name}&" .
                                    "registerView.userAddress.lastName={$fc->userInfo->last_name}&" .
                                    "incentiveRewardToken=&initialEmailForIncentive=&dotdId=&" .
                                    "returnJson=true&" .
                                    "facebookSecurityToken={$session['access_token']}";
                                 $checker->http->GetURL($url);
                             }
                             $checker->http->GetURL('https://' . $obj->urlProvider . '/');
                         })
                         ->PrepareLoginForm();
                } catch (FacebookException $e) {
                    return false;
                }

            break;
        }

        return true;
    }

    public function CheckErrors()
    {
        //# We are improving Groupon for you
        if ($message = $this->checker->http->FindSingleNode("//span[contains(text(), 'We are improving Groupon for you')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Login()
    {
        switch ($this->loginType) {
            case "basic": default:
                // form submission
                if (!$this->checker->http->PostForm()) {
                    return false;
                }
                // check for invalid password
                if ($message = $this->checker->http->FindSingleNode("//div[@class='boxError' and not(@id)]/ul/li[1]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // login successful
                if ($this->checker->http->FindSingleNode("//a[@id='logoutLink']/@href")
                    || $this->checker->http->FindSingleNode("//a[@id='sign-out']/@href")) {
                    return true;
                }

                if ($message = $this->checker->http->FindPreg("/(Email address\s*\/\s*Password not correct!?)/ims")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Incorrect login.
                if ($message = $this->checker->http->FindPreg("/(Incorrect login\.)/ims")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

            break;

            case "facebook":
                try {
                    if ($this->checker->facebook->Login()->isLogIn("//a[@id='logoutLink']/@href")) {
                        return true;
                    }
                } catch (FacebookException $e) {
                    switch ($e->getCode()) {
                        case FacebookConnect::CODE_HTTP_ERROR:
                            return false;

                        break;

                        case FacebookConnect::CODE_INVALID_PASSWORD:
                            $this->checker->ErrorCode = ACCOUNT_INVALID_PASSWORD;
                            $this->checker->ErrorMessage = $e->GetMessage();

                            return false;

                        break;

                        case FacebookConnect::CODE_USER_INTERVENTION_REQUIRED:
                            $this->checker->ErrorCode = ACCOUNT_PROVIDER_ERROR;
                            $permissions = $this->checker->facebook->getPermissions();

                            if (!sizeof($permissions)) {
                                $this->checker->ErrorMessage = "Action Required. Please login to Groupon and respond to a message that you will see after your login.";
                            } else {
                                $this->checker->ErrorMessage = 'Need to allow access to the following information: ' . implode(', ', $permissions);
                            }

                            return false;

                        break;

                        case FacebookConnect::CODE_NOT_FOUND_SESSION:
                            $this->checker->ErrorCode = ACCOUNT_PROVIDER_ERROR;
                            $this->checker->ErrorMessage = 'Authorization Failed';

                            return false;

                        break;

                        case FacebookConnect::CODE_LOCK_ACCOUNT:
                            throw new CheckException($e->GetMessage(), ACCOUNT_PROVIDER_ERROR);

                        break;
                    }
                }

            break;
        }

        return false;
    }

    public function Parse()
    {
        $this->checker->http->FilterHTML = false;
        // Groupon Bucks
        $this->checker->SetProperty('GrouponBucks', $this->checker->http->FindSingleNode("//div[@id = 'jrafCredit']/b"));
        // Currency
        $this->checker->SetProperty('Currency', CleanXMLValue($this->checker->http->FindSingleNode("//div[@id = 'jrafCredit']/b", null, true, "/^([^\d\.]+)/ims")));
        // Name
        $name = CleanXMLValue($this->checker->http->FindSingleNode("//span[@id = 'jUserArea']"));
        $this->checker->SetProperty('UserName', beautifulName($name));
        // Coupons
        $this->checker->SetProperty("SubAccounts", $this->ParseCoupons());
        $this->checker->SetBalance($this->mainBalance);
    }

    public function MarkCoupon(array $ids)
    {
        return [];
    }

    public function ParseCoupons($onlyActive = false)
    {
        $coupons = [];

        // parsing
        $this->checker->http->GetURL($basePage = 'https://' . $this->urlProvider . $this->checker->http->FindSingleNode("//div[@id='jUserAreaSubMenu']//a[@id = 'jMyOrders']/@href"));
        $pages = $this->getUrlsPages();
        array_unshift($pages, $basePage);

        // for parsing deals
        $newDeals = [];
        // intermediate data
        $interData = [];

        foreach ($pages as $page) {
            if ($this->checker->http->currentUrl() != $page) {
                $this->checker->http->GetURL($page);
            }

            if (!$this->checker->http->FindNodes("//div[contains(@id, 'dealCount_')]")) {
                break;
            }
            $this->checker->http->Log("[Page: \"$page\"] <hr />", false);
            $nodes = $this->checker->http->XPath->query("//div[contains(@id, 'dealCount_')]");

            $this->checker->http->Log("[Total found deals]: " . $nodes->length);

            if ($nodes->length > 0) {
                for ($i = 0; $i < $nodes->length; $i++) {
                    $SingleDeal = [];
                    //# A unique code taken from links
                    $code = CleanXMLValue($this->checker->http->FindSingleNode("div[contains(@class, 'OrderDeal')]/span[contains(@class, 'OrderDealDescription')]/a/@href", $nodes->item($i), true, "/(\d+)\s*$/ims"));
                    $fetch = $this->fetchDeal($idDeal = "Groupon." . $this->urlProvider . "." . $code);

                    if ($fetch !== false) {
                        $this->checker->http->Log("[Found a cache for deal code=\"" . $code . "\"]");
                        $SingleDeal = $fetch;
                    } else {
                        $newDeals[$code]['Link'] = 'https://' . $this->urlProvider . $this->checker->http->FindSingleNode("div[contains(@class, 'OrderDeal')]/span[contains(@class, 'OrderDealDescription')]/a/@href", $nodes->item($i));
                        $newDeals[$code]['CacheID'] = $idDeal;
                    }
                    $SingleDeal = (isset($coupons[$code])) ? array_merge($coupons[$code], $SingleDeal) : $SingleDeal;
                    //# parsing coupons
                    $pdf = $this->checker->http->XPath->query("div[contains(@id, 'purchasedDeal_')]/table[contains(@class, 'accOrderCouponData') and not(contains(@class, 'accOrderCouponDataHistory'))]/tr[@class]", $nodes->item($i));

                    if ($this->urlProvider == 'www.groupon.ca') {
                        $price = $this->checker->http->XPath->query("div[contains(@class, 'accOrderDeal')]/table[contains(@class, 'accOrderDealData')]", $nodes->item($i));
                    }
                    $SingleDeal['Code'] = $code;
                    $SingleDeal['Kind'] = "C"; // coupon
                    $SingleDeal['UnableMark'] = true;
                    $ExpiredArray = [];
                    $NotExpiredArray = [];

                    if (isset($coupons[$code]['ExpirationDate']) && isset($interData[$code]['Expired'])) {
                        if ($interData[$code]['Expired']) {
                            $ExpiredArray[] = $coupons[$code]['ExpirationDate'];
                        } else {
                            $NotExpiredArray[] = ($coupons[$code]['ExpirationDate'] == DATE_NEVER_EXPIRES) ? 9999999999 : $coupons[$code]['ExpirationDate'];
                        }
                    }
                    //# Number of coupons (unused)
                    $SingleDeal['Quantity'] = (isset($coupons[$code]['Quantity'])) ? $coupons[$code]['Quantity'] : 0;
                    $this->checker->http->Log("Process '" . $SingleDeal['Code'] . "'...");
                    //# All coupons have the same expiration date
                    $expDate = 0;

                    if ($pdf->length > 0) {
                        $this->checker->http->Log("[Coupons]: " . $pdf->length);

                        for ($k = 0; $k < $pdf->length; $k++) {
                            $SingleCoupon = [];
                            //# Expires at
                            if ($expDate === 0) {
                                $expDate = strtotime($this->checker->http->FindSingleNode(".//div[contains(@class, 'accValidTime')]", $pdf->item($k), true, "/Valid .+to ([^<]+)/ims"));
                                /*
                                $linkToCoupon = 'https://'.$this->urlProvider.$this->checker->http->FindSingleNode("td[@class='col45']/a[contains(@class, 'addIconEye')]/@href", $pdf->item($k));
                                $returnLink = $this->checker->http->currentUrl();
                                $xpath = $this->checker->http->XPath;
                                $this->checker->http->GetURL($linkToCoupon);
                                $expDate = strtotime($this->checker->http->FindPreg("/Valid from (?:[\d\.]+) to ([\d\.]+)/ims"));
                                if ($expDate === false) {
                                    DieTrace("Unable to find the expiration date. ".$this->urlProvider.".", false);
                                    return $coupons;
                                }
                                $this->checker->http->GetURL($returnLink);
                                $this->checker->http->XPath = $xpath;
                                */
                            }
                            $SingleCoupon['ExpiresAt'] = $expDate;
                            //# Used or unused
                            $SingleCoupon['Used'] = false;
                            //# Registration for the unused
                            $isActive = ($SingleCoupon['Used'] === false && time() < $SingleCoupon['ExpiresAt']);

                            if ($isActive) {
                                $SingleDeal['Quantity']++;
                            }

                            //# Collect or not?
                            if (!$isActive && $onlyActive) {
                                continue;
                            }

                            //# Expiration
                            if ($isActive) {
                                $NotExpiredArray[] = $SingleCoupon['ExpiresAt'];
                            } else {
                                $ExpiredArray[] = $SingleCoupon['ExpiresAt'];
                            }

                            if ($this->urlProvider == 'www.groupon.ca') {
                                //# ID coupon
                                $SingleCoupon['Id'] = $this->checker->http->FindSingleNode("td[contains(@class, 'col2')]", $pdf->item($k), true);
                                //# Status
                                $SingleCoupon['Status'] = $this->checker->http->FindSingleNode("td[contains(@class, 'col3')]", $pdf->item($k));
                                //# PDF link
                                $SingleCoupon['File'] = $this->checker->http->FindSingleNode("td[contains(@class, 'col')]/div/a[contains(@href, 'pdf')]/@href", $pdf->item($k));

                                if (isset($price)) {
                                    //# Price
                                    $SingleDeal['Price'] = $this->checker->http->FindSingleNode("tr[contains(@class, 'accOrderDealPrice')]/td[contains(@class, 'col2')]", $price->item(0));
                                    //# $save
                                    $SingleDeal['Save'] = $this->checker->http->FindSingleNode("tr[contains(@class, 'accOrderDealSaving')]/td[contains(@class, 'col2')]", $price->item(0));
                                }
                            }// if ($this->urlProvider == 'www.groupon.ca'
                            else {
                                //# ID coupon
                                $SingleCoupon['Id'] = $this->checker->http->FindSingleNode("td[contains(@class, 'col')]//a[not(contains(@href, '.pdf'))]/@href", $pdf->item($k), true, "/([^\/]+)$/ims");
                                //# Status
                                $SingleCoupon['Status'] = "";
                                //# PDF link
                                $SingleCoupon['File'] = $this->checker->http->FindSingleNode("td[contains(@class, 'col')]//a[contains(@href, '.pdf')]/@href", $pdf->item($k));
                            }

                            //# Purchased at
                            $SingleCoupon['PurchasedAt'] = strtotime($this->checker->http->FindSingleNode("div[contains(@class, 'OrderDeal')]/span[contains(@class, 'OrderDealDate')]", $nodes->item($i), true, "/Date of purchase: (.+)/ims"));
                            //# Caption
                            $SingleCoupon['Caption'] = CleanXMLValue($this->checker->http->FindSingleNode("td[@class='col1']", $pdf->item($k)));

                            if ($this->urlProvider == 'www.groupon.ca') {
                                //# Terms of valid coupon
                                if (!($SingleCoupon['File'] == "" && $isActive)
                                    && is_numeric($SingleCoupon['ExpiresAt'])) {
                                    $SingleDeal['Certificates'][$SingleCoupon['Id']] = $SingleCoupon;
                                }
                            }// if ($this->urlProvider == 'www.groupon.ca')
                            else {
                                //# Terms of valid coupon
                                if (preg_match("/^[a-z0-9]+$/ims", $SingleCoupon['Id'])
                                    && !($SingleCoupon['File'] == "" && $isActive)
                                    && is_numeric($SingleCoupon['ExpiresAt'])) {
                                    $SingleDeal['Certificates'][$SingleCoupon['Id']] = $SingleCoupon;
                                }
                            }
                        }// for ($k = 0; $k < $pdf->length; $k++)
                    }// if ($pdf->length > 0)

                    if (!isset($SingleDeal['Certificates']) || !sizeof($SingleDeal['Certificates'])) {
                        unset($newDeals[$code]);

                        continue;
                    }
                    //# Expiration deal
                    if (sizeof($NotExpiredArray)) {
                        $SingleDeal['ExpirationDate'] = min($NotExpiredArray);

                        if ($SingleDeal['ExpirationDate'] == 9999999999) {
                            $SingleDeal['ExpirationDate'] = DATE_NEVER_EXPIRES;
                        }
                        $interData[$code]['Expired'] = false;
                    }// if (sizeof($NotExpiredArray))
                    else {
                        $SingleDeal['ExpirationDate'] = max($ExpiredArray);
                        $interData[$code]['Expired'] = true;
                    }
                    $this->checker->http->Log("[Total active coupons]: " . $SingleDeal['Quantity']);
                    //# parse deal?
                    if ($onlyActive) {
                        if ($SingleDeal['Quantity'] > 0) {
                            $coupons[$code] = $SingleDeal;

                            if ($fetch === false) {
                                $newDeals[$code]['Deal'] = &$coupons[$code];
                            }
                        } else {
                            if ($fetch === false) {
                                unset($newDeals[$code]);
                            }
                        }
                    }// if ($onlyActive)
                    else {
                        $coupons[$code] = $SingleDeal;

                        if ($fetch === false) {
                            $newDeals[$code]['Deal'] = &$coupons[$code];
                        }
                    }
                }// for ($i = 0; $i < $nodes->length; $i++)
            }// if ($nodes->length > 0)
        }

        foreach ($newDeals as $i => $deal) {
            $d = [];
            $this->checker->http->Log("<pre>" . var_export($deal, true) . "</pre>", false);
            //# Link
            $d['Link'] = $deal['Link'];
            //# Deal Details
            $this->checker->http->GetURL($deal['Link']);
            //# ShortName
            $d['ShortName'] = CleanXMLValue($this->checker->http->FindSingleNode("//h1[@id='contentDealTitle']"));
            //# Name
            $d['Name'] = $d['ShortName'];
            //# DisplayName
            $d['DisplayName'] = $d['Name'];
            //# Fine print (short description)
            $finePrint = $this->checker->http->XPath->query("//div[contains(text(), 'Fine Print')]/following-sibling::*");
            $d['FinePrint'] = "";

            if ($finePrint->length > 0) {
                $tmp_doc = new DOMDocument();

                for ($z = 0; $z < $finePrint->length; $z++) {
                    $tmp_doc->appendChild($tmp_doc->importNode($finePrint->item($z), true));
                }
                $d['FinePrint'] = $tmp_doc->saveHTML();
            }// if ($finePrint->length > 0)
            //# Long description
            $details = $this->checker->http->XPath->query("//div[@class='contentBoxNormalLeft']/p[position()=1 or position()=2]");
            $d['Details'] = "";

            if ($details->length > 0) {
                $tmp_doc = new DOMDocument();

                for ($z = 0; $z < $details->length; $z++) {
                    $tmp_doc->appendChild($tmp_doc->importNode($details->item($z), true));
                }
                $d['Details'] = $tmp_doc->saveHTML();
            }// if ($details->length > 0)
            //# Locations (links)
            //			$locations = $this->checker->http->FindNodes("//div[contains(@class, 'googleMap')]//img[contains(@id, 'MainDealMap')]/@src");
            $locations = $this->checker->http->FindNodes("//a[contains(@name, 'jGetDirectionsLink')]/@href");

            if (is_array($locations)) {
                foreach ($locations as $index => $url) {
                    $d['Locations'][$index]['Url'] = str_replace("/api/staticmap?center=", "?q=", $url);
                }
            }// if (is_array($locations))
            //# Currency (example, $)
            $d['Currency'] = CleanXMLValue($this->checker->http->FindSingleNode("//div[contains(@class, 'price') and contains(text(), 'Amount')]/span[1]", null, true, "/^([^\d\.]+)/ims"));

            if (trim($d['Currency']) == "") {
                $d['Currency'] = "?";
            }
            //# Price (You paid for a deal)
            $d['Price'] = CleanXMLValue($this->checker->http->FindSingleNode("//div[contains(@class, 'price') and contains(text(), 'Amount')]/span[1]", null, true, "/([\d\.]+)/ims"));
            //# Balance && Value (Before discount price)
            $save = CleanXMLValue($this->checker->http->FindSingleNode("//div[contains(@class, 'savings')]/span[2]/span", null, true, "/([\d\.]+)/ims"));
            // may be bug in site: price in details do not match with the price on the main page
            if ($this->urlProvider == 'www.groupon.ca' && isset($deal['Deal']['Price'], $deal['Deal']['Save'])) {
                if ($d['Price'] != $deal['Deal']['Price']) {
                    $d['Price'] = $deal['Deal']['Price'];
                    $save = $deal['Deal']['Save'];
                    unset($deal['Deal']['Price']);
                    unset($deal['Deal']['Save']);
                }// if ($d['Price'] != $deal['Deal']['Price'])
                $d['Balance'] = $d['Value'] = $save + $d['Price'];
            }// if ($this->urlProvider == 'www.groupon.ca' && isset($deal['Deal']['Price'], $deal['Deal']['Save']))
            else {
                $d['Balance'] = $d['Value'] = round($save + $d['Price'], 0);
            }
            //# Save (savings)
            $d['Save'] = round(100 - (($d['Price'] * 100) / $d['Value']));
            //# Deal ended at
            $d['DealEndedAt'] = "";
            //# How many people signed up for a deal
            $d['PeopleCount'] = CleanXMLValue($this->checker->http->FindSingleNode("//span[contains(@id, 'DealSoldAmount')]"));
            //# Downloaded image of a deal in multiple sizes
            $pics = $this->checker->http->FindNodes("//div[@id='contentDealDescription']/img/@src");

            if (sizeof($pics)) {
                foreach ($pics as $src) {
                    $d['Picture'][] = ['Url' => $src];
                }
            }

            //# Saving cache
            $this->storeDeal($deal['CacheID'], $d, 3600 * 24);
            $deal['Deal'] = array_merge($deal['Deal'], $d);

            sleep($this->sleepParse);
        }// foreach ($newDeals as $i => $deal)

        // Main Balance
        foreach ($coupons as $code => $deal) {
            $this->mainBalance += $deal['Quantity'] * $deal['Balance'];
        }

        return $coupons;
    }

    public function getUrlsPages()
    {
        return parent::getUrlsPages();
    }
}
