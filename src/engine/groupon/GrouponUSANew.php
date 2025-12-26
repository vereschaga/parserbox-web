<?php

/**
 * GrouponUSANew.
 */

use AwardWallet\Common\Parsing\Html;

class GrouponUSANew extends GrouponAbstract
{
    use PriceTools;

    public $urlProvider = 'www.groupon.com';
    public $baseDomainCookie = '.groupon.com';
    public $startCookies = [];
    protected $apiId = '1b841d08fcabab506ab07fa89c4190001a558738';
    protected $grouponBucks;
    protected $xpathMyGrouponsLink = "//a[contains(text(),'My Groupons') and @id = 'user-groupons']/@href";
    protected $myAccountLink = null;
    protected $userId = null; // only UK and Australia

    public function GetRedirectParams($arg)
    {
        $arg = parent::GetRedirectParams($arg);
        $arg['CookieURL'] = 'https://' . $this->urlProvider . '/login';
        $arg['PreloadAsImages'] = true;

        return $arg;
    }

    public function LoadLoginForm()
    {
        $this->checker->http->setHttp2(true);
        $this->checker->logger->notice(__METHOD__);
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
            case "basic":
            default:
                return $this->checker->selenium();

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
        $this->checker->logger->notice(__METHOD__);
        $this->checker->logger->info(">>> checkErrors <<<");
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

        /*if ($this->checker->http->currentUrl() == 'https://www.groupon.com/') {
            $this->userId = $this->checker->http->getCookieByName('user_id');
            $this->myAccountLink = sprintf('https://www.groupon.com/users/%s/account', $this->userId);

            return $this->checker->selenium();
        }*/

        return false;
    }

    public function Login()
    {
        $this->checker->logger->notice(__METHOD__);

        switch ($this->loginType) {
            case "basic": default:
                return true;
                // form submission
                //if (!$this->checker->http->PostForm(['content-type' => 'application/x-www-form-urlencoded; charset=UTF-8']))
                //    return $this->checkErrors();
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
                    // throw new CheckRetryNeededException(2, 7, $message, ACCOUNT_INVALID_PASSWORD);
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Something failed. Please try again in a few minutes.
                if ($message = $this->checker->http->FindSingleNode("//div[contains(@class, 'error')]", null, true, '/(Something failed\.\s*Please try again in a few minutes\.)/ims')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                // reCAPTCHA verification
                if ($message = $this->checker->http->FindSingleNode("//div[contains(@class, 'error')]", null, true, '/(reCAPTCHA verification failed, please make sure you select right images and try again\.)/ims')) {
                    throw new CheckRetryNeededException(2, 7, TAccountChecker::CAPTCHA_ERROR_MSG);
                }

                if (preg_match("/users\/new/ims", $this->checker->http->currentUrl())) {
                    throw new CheckException("Action Required. Please login to Groupon.com and respond to a message that you will see after your login.", ACCOUNT_PROVIDER_ERROR);
                }

                // login successful
                if ($this->checker->AccountFields['Login2'] === 'USA') {
                    if ($this->checker->http->getCookieByName('user_id')
                            && $this->checker->http->getCookieByName('c')) {
                        $this->userId = $this->checker->http->getCookieByName('user_id');

                        return true;
                    }
                } elseif ($this->checker->AccountFields['Login2'] === 'UK'
                            || $this->checker->AccountFields['Login2'] === 'Australia'
                            || $this->checker->AccountFields['Login2'] === 'Canada') {
                    if ($this->checker->http->getCookieByName('c_s')
                            && $this->checker->http->getCookieByName('c')
                            && $this->checker->http->getCookieByName('e')) {
                        if ($this->checker->AccountFields['Login2'] === 'UK') {
                            $grouponsLink = 'https://www.groupon.co.uk/mygroupons';
                        } elseif ($this->checker->AccountFields['Login2'] === 'Australia') {
                            $grouponsLink = 'https://www.groupon.com.au/mygroupons';
                        } elseif ($this->checker->AccountFields['Login2'] === 'Canada') {
                            $grouponsLink = 'https://www.groupon.ca/mygroupons';
                        }
                        $this->checker->http->GetURL($grouponsLink);
                        $this->userId = $this->checker->http->FindSingleNode('//select[@id = "filter"]', null, true, '/users\/(\d+)\/groupons/i');

                        return true;
                    }
                }

                if ($this->checkErrors()) {
                    return true;
                }

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
        $this->checker->logger->notice(__METHOD__);
        $this->checker->http->FilterHTML = false;
        //		try {
        $this->checker->SetProperty("SubAccounts", $this->ParseCoupons());
        //		} catch (Exception $e) {
        //			$this->checker->ErrorCode = $e->GetCode();
        //			$this->checker->ErrorMessage = $e->GetMessage();
        //			return false;
        //		}
        $this->checker->SetBalance($this->mainBalance);
        $login2 = $this->checker->AccountFields['Login2'];
        // Groupon Bucks
        if (isset($this->grouponBucks)) {
            $this->checker->logger->info('Groupon Bucks', ['Header' => 3]);
            $this->checker->AddSubAccount([
                "Code"        => "groupon{$login2}GrouponBucks",
                "DisplayName" => "Groupon Bucks",
                "Balance"     => $this->grouponBucks,
            ]);
        }

        // refs #
        $this->checker->logger->info('Cash Back Earned', ['Header' => 3]);

        if ($login2 == 'USA') {
            $this->checker->http->GetURL('https://www.groupon.com/mylinkeddeals');
            // Cash Back Earned
            $this->checker->SetProperty('CashBackEarned', $this->checker->http->FindSingleNode('//div[@class = "total-reward-flash"]'));
        }

        $this->checker->logger->info('Name', ['Header' => 3]);
        //# Name
        $this->setMyAccountLink();

        if (isset($this->myAccountLink)) {
            $this->checker->http->GetURL($this->myAccountLink);
        }
        $name = trim(Html::cleanXMLValue(
            (
                $this->checker->http->FindSingleNode("//input[@id = 'user_firstName']/@value")
                ?? $this->checker->http->FindPreg('/,"firstName":"([^\"]+)",/')
            )
            . ' ' .
            (
            $this->checker->http->FindSingleNode("//input[@id = 'user_lastName']/@value")
            ?? $this->checker->http->FindPreg('/,"lastName":"([^\"]+)"/')
            )
        ));
        $this->checker->SetProperty('Name', beautifulName($name));
    }

    /*
     * Open page with all deals
     */
    public function getGrouponsPage()
    {
        $this->checker->logger->notice(__METHOD__);
        $this->checker->logger->info("Open page with all deals");
        // $myGrouponsLink = $this->checker->http->FindSingleNode($this->xpathMyGrouponsLink);

        $login2 = $this->checker->AccountFields['Login2'];

        switch ($login2) {
            case 'UK':
                $myGrouponsLink = 'https://www.groupon.co.uk/mygroupons?sort_by=expires_at_desc';

                break;

            case 'Australia':
                $myGrouponsLink = 'https://www.groupon.com.au/mygroupons?sort_by=expires_at_desc';

                break;

            case 'Canada':
                $myGrouponsLink = 'https://www.groupon.ca/mygroupons?sort_by=expires_at_desc';

                break;

            default: // USA
                $myGrouponsLink = 'https://www.groupon.com/mygroupons?sort_by=expires_at_desc';

                break;
        }

        $this->checker->logger->notice("[Page]: My Groupons page with all deals");
        $this->checker->http->NormalizeURL($myGrouponsLink);
        $this->checker->http->GetURL($basePage = $myGrouponsLink);

        return $basePage;
    }

    public function MarkCoupon(array $ids)
    {
        // we probably no longer need it
        $this->checker->logger->notice(__METHOD__);
        $oldMaxRedirects = $this->checker->http->_maxRedirects;
        // Open page with all deals
        $basePage = $this->getGrouponsPage();
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
                $this->checker->logger->debug("Page \"{$page}\": not found coupons");

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

        $this->checker->logger->debug("Return MarkCoupon: " . var_export($return, true));

        return $return;
    }

    public function parseSingleCoupon($couponNode)
    {
        $this->checker->logger->notice(__METHOD__);
        $http2 = clone $this->checker->http;

        $pdfUrl = $this->checker->http->FindSingleNode('.//a[contains(@href, ".pdf")]/@href', $couponNode);
        // redeemed
        $redeemed = $this->checker->http->FindSingleNode(
            './/span[@class = "status redeemed" and contains(text(), "Redeemed")]', $couponNode);

        if (!$pdfUrl || $redeemed) {
            return false;
        }
        $this->checker->http->NormalizeURL($pdfUrl);

        $orderId = $this->checker->http->FindSingleNode('./@data-groupon', $couponNode);
        $detailsUrl = $this->checker->http->FindSingleNode('.//a[normalize-space(text())="View Details"]/@href', $couponNode);

        if (empty($detailsUrl)) {
            return [];
        }

        $this->checker->http->NormalizeURL($detailsUrl);

        $dealUrl = $this->checker->http->FindSingleNode('.//a[contains(@href, "/deals/")]/@href', $couponNode);
        $this->checker->http->NormalizeURL($dealUrl);

        $result = [];
        $result['Link'] = $pdfUrl;

        // parse details
        $http2->GetURL($detailsUrl);
        $result['Quantity'] = $http2->FindSingleNode('//td[contains(text(), "Number Ordered:")]/following-sibling::td[1]');
        $price = trim($http2->FindSingleNode('//td[contains(text(), "Unit Price:")]/following-sibling::td[1]'));
        $result['Currency'] = $http2->FindPreg('/([^\d.]+)/', false, $price);
        $result['Price'] = $http2->FindPreg('/([\d.]+)/', false, $price);
        $value = trim($http2->FindSingleNode('//div[contains(text(), "Groupon Value:")]/following-sibling::div[1]'));
        $result['Value'] = $http2->FindPreg('/([\d.]+)/', false, $value);
        $result['Balance'] = ($result['Value'] - $result['Price']) * $result['Quantity'];

        if ($result['Value'] > 0) {
            $result['Save'] = round(100 - (($result['Price'] * 100) / $result['Value']));
        }
        $result['ShortName'] = $http2->FindSingleNode('//div[contains(@class, "item-details-container")]/preceding-sibling::div[1]/div/h1');
        $result['Code'] = sprintf('groupon%s', $orderId);
        $expirationDate = $http2->FindSingleNode('//div[contains(text(), "Expires:")]/following-sibling::div[1]');

        if (preg_match('/,\s*(\w+\s+\d+,\s+\d{4}|\d+\s+\w+\s+\d{4})/i', $expirationDate, $m)) {
            $expirationDate = $m[1];
        } else {
            $expirationDate = null;
        }
        $result['ExpirationDate'] = strtotime($expirationDate);

        // parse deal
        $http2->GetURL($dealUrl);
        $result['DisplayName'] = $http2->FindSingleNode('//h1[@id = "deal-title"]');

        return $result;
    }

    public function parseCouponsPerOrder($orderNode)
    {
        $this->checker->logger->notice(__METHOD__);
        $couponNodes = $this->checker->http->XPath->query('.//div[contains(@class, "voucher") and @data-groupon]', $orderNode);
        $result = [];

        foreach ($couponNodes as $couponNode) {
            if ($coupon = $this->parseSingleCoupon($couponNode)) {
                $result[] = $coupon;
            }
        }

        return $result;
    }

    public function ParseCoupons($onlyActive = false)
    {
        $this->checker->logger->notice(__METHOD__);
        $coupons = [];
        // Open page with all deals
        $basePage = $this->getGrouponsPage();
        $pages = $this->getUrlsPages();
        array_unshift($pages, $basePage);
        $this->checker->logger->debug('Groupons Pages All:');
        $this->checker->logger->debug(var_export($pages, true));

        // Get Groupon Bucks
        $this->grouponBucks = Html::cleanXMLValue($this->checker->http->FindSingleNode("//span[contains(@class, 'bucks-balance')]"));

        $finished = false;

        foreach ($pages as $page) {
            if ($this->checker->http->currentUrl() != $page) {
                $this->checker->http->GetURL($page);
            }

            if (!$this->checker->http->FindSingleNode("//ul[contains(@class, 'orders')]")) {
                break;
            }
            $this->checker->logger->debug("[Page: \"$page\"]");
            $orderNodes = $this->checker->http->XPath->query("//ul[contains(@class, 'orders')]/li");

            $this->checker->logger->debug("[Total found orders]: " . $orderNodes->length);

            foreach ($orderNodes as $orderNode) {
                if ($this->checker->http->FindPreg('/Expired On/i', false, $orderNode->nodeValue)) {
                    $finished = true;

                    break;
                }

                if ($this->checker->http->FindPreg('/Expires On/i', false, $orderNode->nodeValue)
                    // They do not have a combustion date, they should be collected without a balance.
                    || (
                        !$this->checker->http->FindSingleNode(".//div[contains(@class, 'expires_at')]", $orderNode, true)
                        && $this->checker->http->FindSingleNode('.//a[contains(@href, ".pdf")]/@href', $orderNode)
                    )) {
                    $coupons = array_merge($coupons, $this->parseCouponsPerOrder($orderNode));
                }
            }

            if ($finished) {
                break;
            }

            sleep($this->sleepParse);
        }

        // Main Balance
        foreach ($coupons as $coupon) {
            $this->mainBalance += $coupon['Balance'];
        }
        $this->checker->logger->debug('Main balance: ' . $this->mainBalance);

        return $coupons;
        // $fixedCoupons = [];
        // foreach ($coupons as $coupon) {
        //     if (!ArrayVal($coupon, 'DisplayName'))
        //     	$coupon['DisplayName'] = 'DisplayName';
        //     if (!ArrayVal($coupon, 'Code'))
        //     	$coupon['Code'] = 'Code';
        // 	$fixedCoupons[] = $coupon;
        // }
        //return $fixedCoupons;
    }

    public function getUrlsPages()
    {
        $this->checker->logger->notice(__METHOD__);

        $pages = $this->checker->http->FindNodes("//div[contains(@class, 'pagination-pages')]/a[position()>1]/@href");

        foreach ($pages as &$page) {
            $this->checker->http->NormalizeURL($page);
        }

        return $pages;
    }

    protected function setMyAccountLink()
    {
        $this->checker->logger->notice(__METHOD__);
        $login2 = $this->checker->AccountFields['Login2'];

        switch ($login2) {
            case 'UK':
                $this->myAccountLink = 'https://www.groupon.co.uk/myaccount';

                break;

            case 'Australia':
                $this->myAccountLink = 'https://www.groupon.com.au/myaccount';

                break;

            case 'Canada':
                $this->myAccountLink = 'https://www.groupon.ca/myaccount';

                break;

            default: // USA
                $this->myAccountLink = 'https://www.groupon.com/myaccount';

                break;
        }
        $this->checker->http->NormalizeURL($this->myAccountLink);
    }
}
