<?php

/**
 * GrouponUSA.
 */
class GrouponUSA extends GrouponAbstract
{
    public $urlProvider = 'www.groupon.com';
    public $baseDomainCookie = '.groupon.com';
    public $startCookies = [];
    protected $apiId = '1b841d08fcabab506ab07fa89c4190001a558738';
    protected $grouponBucks;
    protected $xpathMyGrouponsLink = "//a[contains(text(),'My Groupons') and @id = 'user-groupons']/@href";
    protected $myAccountLink = null;

    public function GetRedirectParams($arg)
    {
        $arg = parent::GetRedirectParams($arg);
        $arg['CookieURL'] = 'https://' . $this->urlProvider . '/login';
        $arg['PreloadAsImages'] = true;

        return $arg;
    }

    public function LoadLoginForm()
    {
        global $sPath;
        $this->checker->http->FilterHTML = false;
        // reset cookie
        $this->checker->http->removeCookies();
        // set cookie
        if (sizeof($this->startCookies)) {
            foreach ($this->startCookies as $cookie) {
                $this->checker->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path']);
            }
        }

        switch ($this->loginType) {
            case "basic": default:
                // getting the HTML code
                $this->checker->http->getURL('https://' . $this->urlProvider . '/login');

                if ($this->checker->http->Response['code'] == 403) {
                    $sleep = 5;
                    $this->checker->http->Log("Sleep");
                    sleep($sleep);
                    $this->retry();
                }

                // parsing form on the page
                if (!$this->checker->http->ParseForm("master_form")
                    && !$this->checker->http->ParseForm(null, 1, true, '//form[@data-bhw = "LoginForm"]')) {
                    return $this->checkErrors();
                }
                $this->checker->http->Form['email'] = $this->login;
                $this->checker->http->Form['password'] = $this->password;
                $this->checker->http->Form['remember_me'] = 'on';

            break;

            case "facebook":
                throw new CheckException('Sorry, login via Facebook is not supported anymore', ACCOUNT_PROVIDER_ERROR); /*checked*/
                $this->checker->facebook = new FacebookConnect();

                try {
                    $obj = $this;
                    $this->checker->facebook->setAppId('7829106395')
                         ->setRedirectURI('https://' . $this->urlProvider . '/login')
                         ->setChecker($this->checker)
                         ->setCredentials($this->login, $this->password)
                         ->setBaseDomain($this->baseDomainCookie)
                         ->setCallbackFunction(function ($session, $fc, $checker) {
                             $data = json_encode([
                                 'access_token' 	=> $session['access_token'],
                                 'signed_request'=> $session['signed_request'],
                             ]);
                             $checker->http->setDefaultHeader('Content-Type', 'application/json; charset=utf-8');
                             $checker->http->setDefaultHeader('X-Requested-With', 'XMLHttpRequest');
                             $checker->http->PostURL('https://' . $this->urlProvider . '/facebook_session', $data);
                             $checker->http->GetURL('https://' . $this->urlProvider . '/login');
                         })
                         ->PrepareLoginForm();
                } catch (FacebookException $e) {
                    return false;
                }

            break;
        }

        return true;
    }

    public function checkErrors()
    {
        $this->checker->http->Log(">>> checkErrors <<<");
        //# Service Unavailable
        if ($message = $this->checker->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Site unavailable
        if ($message = $this->checker->http->FindPreg("/(Sorry, we are currently updating Groupon and until we finish the site will be unavailable\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Technical difficulties
        if ($message = $this->checker->http->FindSingleNode("//p[contains(text(), 'It appears that we are currently experiencing technical difficulties')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Technical difficulties
        if ($message = $this->checker->http->FindSingleNode("//h1[contains(text(), 'something broke')]/parent::*")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Provider error
        if ($message = $this->checker->http->FindSingleNode("//h2[contains(text(), 'please check back in a few minutes.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Groupon is temporarily unavailable, either because we're updating the site or because someone spilled coffee on it again.
        if ($message = $this->checker->http->FindPreg("/(Groupon is temporarily unavailable, either because we\'re updating the site or because someone spilled coffee on it again\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# 405 Not Allowed
        if ($message = $this->checker->http->FindSingleNode("//h1[contains(text(), '405 Not Allowed')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# We are currently experiencing technical difficulties
        if ($message = $this->checker->http->FindSingleNode("//p[contains(text(), 'we are currently experiencing technical difficulties')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Provider Error
        if ($this->checker->http->FindSingleNode("//span[contains(text(), 'Internal Cat Error')]")
            // An error occurred while processing your request.
            || $this->checker->http->FindPreg("/An error occurred while processing your request\./ims")) {
            throw new CheckException(TAccountChecker::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        switch ($this->loginType) {
            case "basic": default:
                // form submission
                if (!$this->checker->http->PostForm()) {
                    return $this->checkErrors();
                }
                //Updating site message
                if ($message = $this->checker->http->FindSingleNode('//div[contains(text(), "updating the site")]', null, false)) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // check for invalid password
                if ($message = $this->checker->http->FindNodes("//li[@class='error']/div[1]/text()")) {
                    throw new CheckException($message[0], ACCOUNT_INVALID_PASSWORD);
                }
                // Oops! There was a problem processing your request
                if ($message = $this->checker->http->FindSingleNode("//div[contains(@class, 'error')]", null, true, '/(Oops\!\s*There was a problem processing your request\.\s*Please try again or contact us\.)/ims')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Incorrect login. Try again or reset your password. (new login form)
                if ($message = $this->checker->http->FindSingleNode("//div[contains(@class, 'error')]", null, true, '/(Incorrect login\.\s*Try again\s*or reset your password\.)/ims')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Something failed. Please try again in a few minutes.
                if ($message = $this->checker->http->FindSingleNode("//div[contains(@class, 'error')]", null, true, '/(Something failed\.\s*Please try again in a few minutes\.)/ims')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if (preg_match("/users\/new/ims", $this->checker->http->currentUrl())) {
                    throw new CheckException("Action Required. Please login to Groupon.com and respond to a message that you will see after your login.", ACCOUNT_PROVIDER_ERROR);
                }
                // 403
                if ($this->checker->http->Response['code'] == 403
                    && $this->checker->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
                    throw new CheckException(TAccountChecker::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                $this->checker->http->GetURL("https://" . $this->urlProvider . "/login");
                // login successful
                if ($this->checker->http->FindSingleNode("//a[contains(@href,'/logout')]")) {
                    return true;
                }

                $this->checkErrors();

            break;

            case "facebook":
                try {
                    if ($this->checker->facebook->Login()->isLogIn("//a[contains(@href,'/logout')]")) {
                        return true;
                    }
                    //# Your account was deactivated
                    if ($message = $this->checker->http->FindPreg("/(Your account was deactivated\.)/ims")) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }
                } catch (FacebookException $e) {
                    switch ($e->getCode()) {
                        case FacebookConnect::CODE_HTTP_ERROR:
                            return false;

                        break;

                        case FacebookConnect::CODE_INVALID_PASSWORD:
                            throw new CheckException($e->GetMessage(), ACCOUNT_INVALID_PASSWORD);

                        break;

                        case FacebookConnect::CODE_USER_INTERVENTION_REQUIRED:
                            $permissions = $this->checker->facebook->getPermissions();

                            if (!sizeof($permissions)) {
                                $message = "Action Required. Please login to Groupon and respond to a message that you will see after your login.";
                            } else {
                                $message = 'Need to allow access to the following information: ' . implode(', ', $permissions);
                            }

                            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);

                        break;

                        case FacebookConnect::CODE_NOT_FOUND_SESSION:
                            throw new CheckException('Authorization Failed', ACCOUNT_PROVIDER_ERROR);

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
        //		try {
        $this->checker->SetProperty("SubAccounts", $this->ParseCoupons());
        //		} catch (Exception $e) {
        //			$this->checker->ErrorCode = $e->GetCode();
        //			$this->checker->ErrorMessage = $e->GetMessage();
        //			return false;
        //		}
        $this->checker->SetBalance($this->mainBalance);

        if (isset($this->grouponBucks)) {
            $this->checker->SetProperty('GrouponBucks', $this->grouponBucks);
        }
        //# Name
        if (isset($this->myAccountLink)) {
            $this->checker->http->GetURL($this->myAccountLink);
        }
        $name = CleanXMLValue($this->checker->http->FindSingleNode("//input[@id = 'user_firstName']/@value") . ' ' . $this->checker->http->FindSingleNode("//input[@id = 'user_lastName']/@value"));
        $this->checker->SetProperty('UserName', beautifulName($name));
    }

    /*
     * Open page with all deals
     */
    public function getGrouponsPage()
    {
        $this->checker->http->Log("Open page with all deals");
        $myGrouponsLink = $this->checker->http->FindSingleNode($this->xpathMyGrouponsLink);

        if (!$myGrouponsLink) {
            return false;
        }

        if ($this->urlProvider != 'www.groupon.com') {
            $this->checker->http->Log("[Page]: My Groupons");
            $this->checker->http->NormalizeURL($myGrouponsLink);
            $this->checker->http->GetURL($myGrouponsLink);
            $myGrouponsLink = str_replace(':filter', '', $this->checker->http->FindSingleNode("//select[@id = 'filter']/@data-url"));
        }
        $this->checker->http->Log("[Page]: My Groupons page with all deals");
        $this->checker->http->NormalizeURL($myGrouponsLink);
        $this->checker->http->GetURL($basePage = $myGrouponsLink . '/all');

        return $basePage;
    }

    public function MarkCoupon(array $ids)
    {
        $oldMaxRedirects = $this->checker->http->_maxRedirects;
        // Open page with all deals
        $basePage = $this->getGrouponsPage();

        if ($basePage === false) {
            return false;
        }
        $pages = $this->getUrlsPages();
        array_unshift($pages, $basePage);

        //# array to return. Default values
        $return = [];

        foreach ($ids as $k=>$v) {
            $return[$k] = false;
        }

        foreach ($pages as $page) {
            if (sizeof($ids) == 0) {
                break;
            }

            if ($this->checker->http->currentUrl() != $page) {
                $this->checker->http->GetURL($page);
            }

            if (!$this->checker->http->FindSingleNode("//ul[contains(@class, 'orders container')]")) {
                $this->checker->http->Log("Page \"$page\": not found coupons");

                break;
            }

            foreach ($ids as $id=>$used) {
                if ($this->checker->http->FindSingleNode("//form[contains(@action, '/" . $id . "/customer_redeem')]/@action")) {
                    //# real mark
                    $rUsed = false;

                    if ($used != $rUsed) {
                        $this->checker->http->_maxRedirects = 0;
                        $currentURL = $this->checker->http->currentUrl();

                        if ($this->checker->http->ParseForm(null, 1, true, "//form[contains(@action, '/" . $id . "/customer_redeem')]") && $this->checker->http->PostForm()) {
                            $return[$id] = true;
                        }
                        $this->checker->http->GetURL($currentURL);
                        $this->checker->http->_maxRedirects = $oldMaxRedirects;
                    } else {
                        $return[$id] = true;
                    }
                    unset($ids[$id]);
                }
            }
        }

        $this->checker->http->Log("Return MarkCoupon: " . var_export($return, true));

        return $return;
    }

    public function ParseCoupons($onlyActive = false)
    {
        $coupons = [];
        // Open page with all deals
        $basePage = $this->getGrouponsPage();

        if ($basePage === false) {
            return false;
        }
        $pages = $this->getUrlsPages();
        array_unshift($pages, $basePage);

        // Get Groupon Bucks
        $this->grouponBucks = CleanXMLValue($this->checker->http->FindSingleNode("//span[@class = 'bucks-balance']"));
        // Get Name
        $this->myAccountLink = $this->checker->http->FindSingleNode("//a[@id = 'user-account']/@href");

        if (isset($this->myAccountLink)) {
            $this->checker->http->NormalizeURL($this->myAccountLink);
        }

        // for parsing deals
        $newDeals = [];
        // intermediate data
        $interData = [];

        foreach ($pages as $page) {
            if ($this->checker->http->currentUrl() != $page) {
                $this->checker->http->GetURL($page);
            }

            if (!$this->checker->http->FindSingleNode("//ul[contains(@class, 'orders')]")) {
                break;
            }
            $this->checker->http->Log("[Page: \"$page\"] <hr />", false);
            $nodes = $this->checker->http->XPath->query("//ul[contains(@class, 'orders')]/li");

            $this->checker->http->Log("[Total found deals]: " . $nodes->length);

            if ($nodes->length > 0) {
                for ($i = 0; $i < $nodes->length; $i++) {
                    $SingleDeal = [];
                    //# A unique code taken from links
                    if ($this->urlProvider != 'www.groupon.com') {
                        $code = CleanXMLValue($this->checker->http->FindSingleNode(".//div[contains(@class, 'voucher')][1]//*[contains(@class, 'deal-image')][1]/a/@href", $nodes->item($i), true, "/\/deals.+\/([^\/]+)$/ims"));
                        $fetch = $this->fetchDeal($idDeal = "Groupon." . $this->urlProvider . "." . $code);

                        if ($fetch !== false) {
                            $this->checker->http->Log("[Found a cache for deal code=\"" . $code . "\"]");
                            $SingleDeal = $fetch;
                        } else {
                            $newDeals[$code]['Link'] = $this->checker->http->FindSingleNode(".//div[contains(@class, 'voucher')][1]//*[contains(@class, 'deal-image')][1]/a/@href", $nodes->item($i));
                            $newDeals[$code]['CacheID'] = $idDeal;
                        }
                    }// if ($this->urlProvider != 'www.groupon.com')
                    else {// if ($this->urlProvider == 'www.groupon.com')
                        $code = CleanXMLValue($this->checker->http->FindSingleNode(".//div[contains(@class, 'voucher')][1]//*[contains(@class, 'deal-image')][1]/a/@href", $nodes->item($i), true, "/\/deals\/([^\/]+)$/ims"));
                        $fetch = $this->fetchDeal($idDeal = "Groupon.USA." . $code);

                        if ($fetch !== false) {
                            $this->checker->http->Log("[Found a cache for deal code=\"" . $code . "\"]");
                            $SingleDeal = $fetch;
                        } else {
                            $newDeals[$code]['Link'] = 'http://api.groupon.com/v2/deals/' . $code . '.json?client_id=' . $this->apiId;
                            $newDeals[$code]['CacheID'] = $idDeal;
                        }
                    }// if ($this->urlProvider == 'www.groupon.com')
                    $SingleDeal = (isset($coupons[$code])) ? array_merge($coupons[$code], $SingleDeal) : $SingleDeal;
                    //# parsing coupons
                    $pdf = $this->checker->http->XPath->query("div[contains(@class, 'voucher')]/div/div/div[contains(@class, 'voucher')]", $nodes->item($i));
                    $SingleDeal['Code'] = $code;
                    $SingleDeal['Kind'] = "C"; // coupon
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

                    if ($pdf->length > 0) {
                        $this->checker->http->Log("[Coupons]: " . $pdf->length);

                        for ($k = 0; $k < $pdf->length; $k++) {
                            $SingleCoupon = [];
                            //# Expires at
                            $expiresAt = $this->checker->http->FindNodes("div[contains(@class, 'expires_at')]/p[1]/text()", $nodes->item($i));

                            if (!sizeof($expiresAt)) {
                                $expiresAt = $this->checker->http->FindSingleNode("div[contains(@class, 'expires_at')]/p/span[@class='adjusted_expiration']", $nodes->item($i));
                            } else {
                                $expiresAt = $expiresAt[0];
                            }
                            //# Never Expires
                            if (preg_match("/Never Expires/ims", $expiresAt)) {
                                $SingleCoupon['ExpiresAt'] = 9999999999;
                            } else {
                                $SingleCoupon['ExpiresAt'] = $this->checker->getTimestamp($expiresAt);
                            }
                            $this->checker->http->Log("ExpiresAt: " . $SingleCoupon['ExpiresAt'] . " - " . date('M d, Y', $SingleCoupon['ExpiresAt']));
                            //# Used or unused
                            $query = $this->checker->http->FindSingleNode(".//li[1]/a[contains(text(), 'View Voucher')]/@href", $pdf->item($k), null, 0);
                            $SingleCoupon['Used'] = ($query != "") ? false : true;
                            //# Registration for the unused  // TODO: refs #7707
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

                            //# Purchased at
                            $SingleCoupon['PurchasedAt'] = $this->checker->getTimestamp($this->checker->http->FindSingleNode("div//p[@class='joined_at']", $nodes->item($i), true, "/Purchased\s*(.+)/ims"));
                            //# Status
                            $SingleCoupon['Status'] = "";
                            //# ID coupon
                            $SingleCoupon['Id'] = $this->checker->http->FindSingleNode(".//a[contains(@data-button, 'view-voucher')]/@href", $pdf->item($k), true, "/\/([\d\-A-Za-z]+)\./ims");

                            if (empty($SingleCoupon['Id'])) {
                                $this->checker->http->Log("skip redeemed voucher " . $SingleDeal['Code'] . " (without id)");

                                continue;
                            }

                            //# PDF link
                            $file = $this->checker->http->FindSingleNode(".//a[contains(@data-button, 'view-voucher')]/@href", $pdf->item($k));

                            if (isset($file)) {
                                $this->checker->http->NormalizeURL($file);
                            }
                            $SingleCoupon['File'] = $file;

                            //# Caption
                            $SingleCoupon['Caption'] = (isset($SingleCoupon['Id'])) ? '#' . $SingleCoupon['Id'] : '';

                            //# Terms of valid coupon
                            if (/*is_numeric($SingleCoupon['Id']) &&*/ !($SingleCoupon['File'] == "" && $isActive)
                                && is_numeric($SingleCoupon['ExpiresAt'])) {
                                $SingleDeal['Certificates'][$SingleCoupon['Id']] = $SingleCoupon;
                            }
                            $this->checker->http->Log("<pre>" . var_export($SingleCoupon, true) . "</pre>", false);
                        }
                    }

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
                    } else {
                        $SingleDeal['ExpirationDate'] = max($ExpiredArray);
                        $interData[$code]['Expired'] = true;
                    }

                    if ($SingleDeal['ExpirationDate'] < time()) {
                        $SingleDeal['UnableMark'] = true;
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
                    } else {
                        $coupons[$code] = $SingleDeal;

                        if ($fetch === false) {
                            $newDeals[$code]['Deal'] = &$coupons[$code];
                        }
                    }
                }
            }
        }

        foreach ($newDeals as $i=>$deal) {
            $d = [];
            //# Deal Details
            $this->checker->http->GetURL($deal['Link']);

            if ($this->urlProvider != 'www.groupon.com') {
                $this->checker->http->Log("<pre>" . var_export($deal, true) . "</pre>", false);
                //# Link
                $d['Link'] = $deal['Link'];
                //# Deal Details
                $this->checker->http->GetURL($deal['Link']);
                //# ShortName
                $d['ShortName'] = CleanXMLValue($this->checker->http->FindSingleNode("//h2[@class='deal-page-title']"));
                //# Name
                $d['Name'] = $d['ShortName'];
                //# DisplayName
                $d['DisplayName'] = $d['Name'];
                //# Fine print (short description)
//                $finePrint = $this->checker->http->XPath->query("//h3[contains(text(), 'Fine Print')]/following-sibling::p[1]/text()");
                $d['FinePrint'] = $this->checker->http->FindHTMLByXpath("//h3[contains(text(), 'Fine Print')]/following-sibling::p[1]", "/<br>([^<]+)<strong>/ims");
//                $d['FinePrint'] = "";
//                if ($finePrint->length > 0) {
//                    $tmp_doc = new DOMDocument();
//                    for ($z = 0; $z < $finePrint->length; $z++)
//                        $tmp_doc->appendChild($tmp_doc->importNode($finePrint->item($z),true));
//                    $d['FinePrint'] = $tmp_doc->saveHTML();
//                }// if ($finePrint->length > 0)
                //# Long description
                $details = $this->checker->http->XPath->query("//h3[contains(text(), 'Fine Print')]/following-sibling::p[1]");
                $d['Details'] = "";

                if ($details->length > 0) {
                    $tmp_doc = new DOMDocument();

                    for ($z = 0; $z < $details->length; $z++) {
                        $tmp_doc->appendChild($tmp_doc->importNode($details->item($z), true));
                    }
                    $d['Details'] = $tmp_doc->saveHTML();
                }// if ($details->length > 0)
                //# Locations (links)
                $locations = $this->checker->http->FindNodes("//a[contains(@data-bhw, 'GetDirections')]/@href");

                if (is_array($locations)) {
                    foreach ($locations as $index => $url) {
                        $d['Locations'][$index]['Url'] = str_replace("/api/staticmap?center=", "?q=", $url);
                    }
                }// if (is_array($locations))
                //# Currency (example, $)
                $d['Currency'] = CleanXMLValue($this->checker->http->FindSingleNode("//div[@id = 'deal-hero-price']/span[@class = 'price']", null, true, "/^([^\d\.]+)/ims"));

                if (trim($d['Currency']) == "") {
                    $d['Currency'] = "?";
                }
                //# Price (You paid for a deal)
                $d['Price'] = CleanXMLValue($this->checker->http->FindSingleNode("//div[@id = 'deal-hero-price']/span[@class = 'price']", null, true, "/([\d\.]+)/ims"));
                //# Balance && Value (Before discount price)
                $save = CleanXMLValue($this->checker->http->FindSingleNode("//td[@id = 'discount-value']", null, true, "/([\d\.]+)/ims"));
                $d['Balance'] = $d['Value'] = round($save + $d['Price'], 0);
                //# Save (savings)
                if (!empty($d['Value'])) {
                    $d['Save'] = round(100 - (($d['Price'] * 100) / $d['Value']));
                }
                //# Deal ended at
                $d['DealEndedAt'] = "";
                //# How many people signed up for a deal
                $d['PeopleCount'] = CleanXMLValue($this->checker->http->FindSingleNode("//div[@class = 'deal-status']", null, true, "/\d+/"));
                //# Downloaded image of a deal in multiple sizes
                $pics = $this->checker->http->FindNodes("//ul[@class='gallery-thumbs']/li//img/@src");

                if (sizeof($pics)) {
                    foreach ($pics as $src) {
                        $d['Picture'][] = ['Url' => $src];
                    }
                }
            }// if ($this->urlProvider != 'www.groupon.com')
            else {
                $response = json_decode($this->checker->http->Response['body']);

                if (!isset($response->deal)) {
                    $this->checker->http->GetURL(str_replace("/v2/", "/v1/", $deal['Link']));
                    $response = json_decode($this->checker->http->Response['body']);
                }

                if (!isset($response) || $this->existsVar($response->error) || $this->existsVar($response->error->message)) {
                    unset($newDeals[$i]);
                    unset($coupons[$i]);

                    continue;
                }

                //# Link
                if ($this->existsVar($response->deal->dealUrl)) {
                    $d['Link'] = CleanXMLValue($response->deal->dealUrl);
                } else {
                    $d['Link'] = CleanXMLValue($response->deal_url);
                }
                //# Name
                if ($this->existsVar($response->deal->title)) {
                    $d['Name'] = CleanXMLValue($response->deal->title);
                } else {
                    $d['Name'] = CleanXMLValue($response->title);
                }
                //# ShortName
                if ($this->existsVar($response->deal->announcementTitle)) {
                    $d['ShortName'] = CleanXMLValue($response->deal->announcementTitle);
                } elseif ($this->existsVar($response->short_title)) {
                    $d['ShortName'] = CleanXMLValue($response->short_title);
                } else {
                    $d['ShortName'] = $d['Name'];
                }
                //# DisplayName
                $d['DisplayName'] = $d['Name'];
                //# Fine print (short description)
                if ($this->existsVar($response->deal->options[0]->details[0]->description)) {
                    $d['FinePrint'] = CleanXMLValue($response->deal->options[0]->details[0]->description);
                } elseif ($this->existsVar($response->conditions->details[0])) {
                    $d['FinePrint'] = CleanXMLValue($response->conditions->details[0]);
                }
                //# Long description
                if ($this->existsVar($response->deal->pitchHtml)) {
                    $d['Details'] = CleanXMLValue($response->deal->pitchHtml);
                } elseif ($this->existsVar($response->pitch_html)) {
                    $d['Details'] = CleanXMLValue($response->pitch_html);
                }
                //# Locations (links)
                if (isset($response->deal->options[0]->redemptionLocations) && is_array($response->deal->options[0]->redemptionLocations)) {
                    foreach ($response->deal->options[0]->redemptionLocations as $index=>$cord) {
                        if (isset($cord->lat, $cord->lng)) {
                            $d['Locations'][$index]['Url'] = 'http://maps.google.com/maps?q=' . $cord->lat . ', ' . $cord->lng;
                        }
                    }
                }
                //# Currency (example, $)
                if ($this->existsVar($response->deal->options[0]->price->currencyCode)) {
                    $d['Currency'] = CleanXMLValue($response->deal->options[0]->price->currencyCode);
                } elseif ($this->existsVar($response->price)) {
                    if (preg_match("/([^\d\,\.]+)/ims", $response->price, $match)) {
                        $d['Currency'] = CleanXMLValue($match[1]);
                    } else {
                        $d['Currency'] = "$";
                    }
                } else {
                    $d['Currency'] = "$";
                }
                //# Price (You paid for a deal)
                if ($this->existsVar($response->deal->options[0]->price->formattedAmount) && preg_match("/([\d\.\,]+)/ims", $response->deal->options[0]->price->formattedAmount, $match)) {
                    $d['Price'] = CleanXMLValue($match[1]);
                } elseif (preg_match("/([\d\.\,]+)/ims", $response->price, $match)) {
                    $d['Price'] = CleanXMLValue($match[1]);
                }
                //# Balance && Value (Before discount price)
                if ($this->existsVar($response->deal->options[0]->value->formattedAmount) && preg_match("/([\d\.\,]+)/ims", $response->deal->options[0]->value->formattedAmount, $match)) {
                    $d['Balance'] = $d['Value'] = CleanXMLValue($match[1]);
                } elseif (preg_match("/([\d\.\,]+)/ims", $response->value, $match)) {
                    $d['Balance'] = $d['Value'] = CleanXMLValue($match[1]);
                } else {
                    throw new Exception($this->checker->ErrorMessage, ACCOUNT_PROVIDER_ERROR);
                }
                //# Save (savings)
                if (intval($d['Value'])) {
                    $d['Save'] = round(100 - (($d['Price'] * 100) / $d['Value']));
                }
                //# Deal ended at
                if ($this->existsVar($response->deal->tippedAt)) {
                    $d['DealEndedAt'] = CleanXMLValue(str_replace(["T", "Z"], " ", $response->deal->tippedAt));
                } elseif ($this->existsVar($response->tipped_date)) {
                    $d['DealEndedAt'] = CleanXMLValue(str_replace(["T", "Z"], " ", $response->tipped_date));
                }
                //# How many people signed up for a deal
                if ($this->existsVar($response->deal->soldQuantity)) {
                    $d['PeopleCount'] = CleanXMLValue($response->deal->soldQuantity);
                } elseif ($this->existsVar($response->quantity_sold)) {
                    $d['PeopleCount'] = CleanXMLValue($response->quantity_sold);
                }
                //# Downloaded image of a deal in multiple sizes
                if ($this->existsVar($response->deal->largeImageUrl)) {
                    $d['Picture'][0]['Url'] = CleanXMLValue($response->deal->largeImageUrl);
                } elseif ($this->existsVar($response->large_image_url)) {
                    $d['Picture'][0]['Url'] = CleanXMLValue($response->large_image_url);
                }
            }

            //# Saving cache
            $this->storeDeal($deal['CacheID'], $d, 3600 * 24);
            $deal['Deal'] = array_merge($deal['Deal'], $d);

            sleep($this->sleepParse);
        }

        // Main Balance
        foreach ($coupons as $code => $deal) {
            $this->mainBalance += $deal['Quantity'] * $deal['Balance'];
        }
        $this->checker->http->Log('Main balance: ' . $this->mainBalance);

        return $coupons;
    }

    public function getUrlsPages()
    {
        $pages = [];

        if ($link = $this->checker->http->FindSingleNode("//div[@class='pagination'][1]/a[not(@class) and contains(@href, 'all?page=') and not(contains(text(), 'Next')) and not(contains(text(), 'Prev'))][position()=last()]/@href")) {
            if (preg_match("/all\?page=(\d+)/ims", $link, $matches)) {
                for ($i = 2; $i <= $matches[1]; $i++) {
                    $_link = preg_replace("/all\?page=(\d+)/ims", 'all?page=' . $i . '', $link);
                    $this->checker->http->NormalizeURL($_link);
                    $pages[] = $_link;
                }
            }
        }
        $this->checker->http->Log("[Pages: " . var_export($pages, true) . "] <hr />", false);

        return $pages;
    }
}
