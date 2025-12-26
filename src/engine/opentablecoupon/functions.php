<?php

class TAccountCheckerOpentablecoupon extends TAccountChecker
{
    public $sleepParse = 0;
    public $mainBalance = 0;

    public function __call($method, $params)
    {
        if (method_exists(CouponHelper::class, $method)) {
            return call_user_func_array([CouponHelper::class, $method], $params);
        }
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://spotlight.opentable.com/login");

        if (!$this->http->ParseForm('aspnetForm')) {
            return false;
        }
        $this->http->FormURL = 'https://spotlight.opentable.com/login';
        $this->http->Form['ctl00$ctl00$ctl00$content_body$content_left$content_left$id_login$TextBoxLoginEmail'] = $this->AccountFields['Login'];
        $this->http->Form['ctl00$ctl00$ctl00$content_body$content_left$content_left$id_login$TextBoxLoginPassword'] = $this->AccountFields['Pass'];
        $this->http->Form['ctl00$ctl00$ctl00$content_body$content_left$content_left$id_login$CheckBoxRememberMe'] = 0;
        $this->http->Form["__EVENTTARGET"] = 'ctl00$ctl00$ctl00$content_body$content_left$content_left$id_login$ButtonLogin';

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@id, 'login_message')]")) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = $message;

            return false;
        }
        //error (OpenTable Spotlight is acting up.))
        if ($error = $this->http->FindPreg("/OpenTable Spotlight is acting up/ims")) {
            $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;
            $this->ErrorMessage = "OpenTable Spotlight is acting up. Please, try again later.";

            return false;
        }

        if ($this->http->FindSingleNode("//a[contains(@href, '/logout')]")) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        $this->http->FilterHTML = false;
        // Name
        $this->SetProperty("UserName", beautifulName($this->http->FindPreg("/Welcome\s*([^<\!\.]+)/ims")));
        // Coupons
        $this->SetProperty("SubAccounts", $this->ParseCoupons());
        $this->SetBalance($this->mainBalance);
        $purchases = $this->http->FindSingleNode("//div[contains(text(), 'You have not purchased any Tickets yet.')]");

        $coupons = $this->http->XPath->query("//table[@class='repeater_table']/tr[@class]");
        $expired = $this->http->XPath->query("//table[@class='repeater_table']/tr[@class and td/span[contains(text(), 'EXPIRED')]]");

        if (!isset($purchases) && ($coupons->length != $expired->length)) {
            $this->sendNotification("opentablecoupon, There are coupons in account");
        }
        $this->http->Log("Purchases: {$purchases}");
    }

    /**
     * Mark coupon (as used or unused).
     *
     * @param array $ids array["id"] = "used" (true or false)
     *
     * @return array The result for each coupon
     */
    public function MarkCoupon(array $ids)
    {
        $oldMaxRedirects = $this->http->_maxRedirects;
        $this->http->GetURL($basePage = "https://spotlight.opentable.com/my-purchases");
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

            if ($this->http->currentUrl() != $page) {
                $this->http->GetURL($page);
            }
            $nodes = $this->http->XPath->query("//table[@class='repeater_table']/tr[@class]");

            if ($nodes->length == 0) {
                $this->http->Log("Page \"$page\": not found coupons");

                break;
            }

            foreach ($ids as $id=>$used) {
                $phId = $this->http->FindPreg("/ToggleUsedPurchase\((\d+),\s*" . $id . "\)/ims");
                $nodeCoupon = $this->http->XPath->query("//div[@class='aligned_link print_links_" . $phId . "_" . $id . "']/a");

                if ($phId && $nodeCoupon->length == 1) {
                    //# real mark
                    if ($nodeCoupon->item(0)->getAttribute("href") != "") {
                        $rUsed = false;
                    } else {
                        $rUsed = true;
                    }

                    if ($used != $rUsed) {
                        $this->http->_maxRedirects = 0;
                        $currentURL = $this->http->currentUrl();
                        $requestStr = json_encode(
                            [
                                'bcId' => $id,
                                'phId' => $phId,
                            ]
                        );
                        $this->http->Log(var_export($requestStr, true));
                        $this->http->setDefaultHeader('Content-Type', 'application/json; charset=utf-8');
                        $this->http->PostURL("https://spotlight.opentable.com/service/hotdeal.asmx/ToggleUsedPurchase", $requestStr);
                        $authResponse = json_decode($this->http->Response['body']);

                        if (isset($authResponse->d) && $authResponse->d) {
                            $return[$id] = true;
                        }
                        $this->http->_maxRedirects = $oldMaxRedirects;
                        $this->http->GetURL($currentURL);
                    } else {
                        $return[$id] = true;
                    }

                    unset($ids[$id]);
                }
            }
        }

        $this->http->Log("Return MarkCoupon: " . var_export($return, true));

        return $return;
    }

    /**
     * Parsing coupons.
     *
     * @return array coupons
     */
    public function ParseCoupons($onlyActive = false)
    {
        $coupons = [];

        // parsing
        $this->http->GetURL($basePage = "https://spotlight.opentable.com/my-purchases");
        $pages = $this->getUrlsPages();
        array_unshift($pages, $basePage);

        // for parsing deals
        $newDeals = [];
        // intermediate data
        $interData = [];

        foreach ($pages as $page) {
            if ($this->http->currentUrl() != $page) {
                $this->http->GetURL($page);
            }

            if (!$this->http->FindSingleNode("//div[@id='id_my_purchases']")) {
                break;
            }
            $this->http->Log("[Page: \"$page\"] <hr />", false);
            $nodes = $this->http->XPath->query("//table[@class='repeater_table']/tr[@class]");

            $this->http->Log("[Total found deals]: " . $nodes->length);

            if ($nodes->length > 0) {
                for ($i = 0; $i < $nodes->length; $i++) {
                    $SingleDeal = [];
                    //# A unique code taken from links
                    $code = CleanXMLValue($this->http->FindSingleNode("td[1]//a/@href", $nodes->item($i), true, "/\/coupon\/(\d+)/ims"));
                    $fetch = $this->fetchDeal($idDeal = "OpenTable." . $code);

                    if ($fetch !== false) {
                        $this->http->Log("[Found a cache for deal code=\"" . $code . "\"]");
                        $SingleDeal = $fetch;
                    } else {
                        $newDeals[$code]['Link'] = 'http://spotlight.opentable.com' . $this->http->FindSingleNode("td[1]//a/@href", $nodes->item($i));
                        $newDeals[$code]['CacheID'] = $idDeal;
                    }
                    $SingleDeal = (isset($coupons[$code])) ? array_merge($coupons[$code], $SingleDeal) : $SingleDeal;
                    //# parsing coupons
                    $pdf = $this->http->XPath->query("td[8]/div/a", $nodes->item($i));
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
                    $this->http->Log("Process '" . $SingleDeal['Code'] . "'...");

                    if ($pdf->length > 0) {
                        $this->http->Log("[Coupons]: " . $pdf->length);

                        for ($k = 0; $k < $pdf->length; $k++) {
                            $SingleCoupon = [];
                            // Expired coupon
                            $query = $this->http->XPath->query("td[9]/div[" . ($k + 1) . "]/input/@disabled", $nodes->item($i));

                            if ($query->length == 1) {
                                continue;
                            }
                            //# Expires at
                            $expiresAt = $this->http->FindNodes("td[6]/text()|td[6]/div/text()", $nodes->item($i));
                            $expiresAt = implode(" ", $expiresAt);
                            //# Never Expires
                            if (preg_match("/Never Expires/ims", $expiresAt)) {
                                $SingleCoupon['ExpiresAt'] = 9999999999;
                            } else {
                                $SingleCoupon['ExpiresAt'] = strtotime($expiresAt);
                            }
                            //# Used or unused
                            $query = $this->http->XPath->query("td[9]/div[" . ($k + 1) . "]/input/@checked", $nodes->item($i));
                            $SingleCoupon['Used'] = ($query->length > 0) ? true : false;
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

                            //# ID coupon
                            $SingleCoupon['Id'] = $this->http->FindSingleNode("td[9]/div[" . ($k + 1) . "]/@class", $nodes->item($i), true, "/^\s*aligned_link print_links_\d+_(\d+)/ims");
                            //# Purchased at
                            $purchasedAt = $this->http->FindNodes("td[5]/text()|td[5]/div/text()", $nodes->item($i));
                            $SingleCoupon['PurchasedAt'] = strtotime(implode(" ", $purchasedAt));
                            //# Status
                            $SingleCoupon['Status'] = CleanXMLValue($this->http->FindSingleNode("td[7]/div", $nodes->item($i)));
                            //# PDF link
                            if ($pdf->item($k)->getAttribute("href") != "") {
                                $SingleCoupon['File'] = "https://spotlight.opentable.com" . $pdf->item($k)->getAttribute("href");
                            } else {
                                $SingleCoupon['File'] = "https://spotlight.opentable.com" . $pdf->item($k)->getAttribute("disablehref");
                            }
                            //# Caption
                            $SingleCoupon['Caption'] = CleanXMLValue($pdf->item($k)->nodeValue);

                            //# Terms of valid coupon
                            if (is_numeric($SingleCoupon['Id'])
                            && !($SingleCoupon['File'] == "" && $isActive)
                            && is_numeric($SingleCoupon['ExpiresAt'])) {
                                $SingleDeal['Certificates'][$SingleCoupon['Id']] = $SingleCoupon;
                            }
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

                    $this->http->Log("[Total active coupons]: " . $SingleDeal['Quantity']);
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
            //# Link
            $d['Link'] = $deal['Link'];
            //# Deal Details
            $this->http->GetURL($d['Link']);
            //# Name
            $d['Name'] = CleanXMLValue($this->http->FindSingleNode("//div[@class='container-deal-headline']/div[@class='text_headline_big']/h1"));
            //# DisplayName = ShortName = Name
            $d['DisplayName'] = $d['ShortName'] = $d['Name'];

            //# Fine print (short description)
            $finePrint = $this->http->XPath->query("//div[div[@class='coupon_description_long']/h2]/div/ol[@class='list_disc_outside']/li");
            $d['FinePrint'] = "";

            if ($finePrint->length > 0) {
                $tmp_doc = new DOMDocument();

                for ($z = 0; $z < $finePrint->length; $z++) {
                    $tmp_doc->appendChild($tmp_doc->importNode($finePrint->item($z), true));
                }
                $d['FinePrint'] = $tmp_doc->saveHTML();
            }
            //# Long description
            $details = $this->http->XPath->query("//div[@class='coupon_description_long']");
            $d['Details'] = "";

            if ($details->length > 0) {
                $tmp_doc = new DOMDocument();

                for ($z = 0; $z < $details->length; $z++) {
                    $tmp_doc->appendChild($tmp_doc->importNode($details->item($z), true));
                }
                $d['Details'] = $tmp_doc->saveHTML();
            }
            //# Locations (links)
            $locations = $this->http->FindNodes("//div[@class='container-merchant-details']/div[2]/div[3]/a/@href");

            if (is_array($locations)) {
                foreach ($locations as $index=>$url) {
                    $d['Locations'][$index]['Url'] = $url;
                }
            }
            //# Currency (example, $)
            $d['Currency'] = $this->http->FindSingleNode("//div[@class='container-purchaseprice-buybtn']/div/b", null, true, "/\s*([^\d])/ims");

            if (trim($d['Currency']) == "") {
                $d['Currency'] = "$";
            }
            //# Price (You paid for a deal)
            $d['Price'] = $this->http->FindSingleNode("//div[@class='container-purchaseprice-buybtn']/div/b", null, true, "/([\d\.\,]+)/ims");
            //# Balance && Value (Before discount price)
            if ($value = $this->http->FindSingleNode("//div[@class='container-ot-value-save']/div[@class='deal-value']/em", null, true, "/([\d\.\,]+)/ims")) {
                $d['Balance'] = $d['Value'] = $value;
            } elseif (preg_match("/^\s*\\" . $d['Currency'] . "\s*" . $d['Price'] . " for \\" . $d['Currency'] . "\s*([\d\.\,]+)/ims", $d['Name'], $match)) {
                $d['Balance'] = $d['Value'] = $match[1];
            }
            //# Save (savings)
            if ($save = $this->http->FindSingleNode("//div[@class='container-ot-value-save']/div[@class='deal-savepercent']/em", null, true, "/([^%]+)/ims")) {
                $d['Save'] = $save;
            } elseif (isset($d['Value']) && isset($d['Price'])) {
                $d['Save'] = round(100 - (($d['Price'] / $d['Value']) * 100), 0);
            }
            //# Deal ended at
            $dealEndedAt = $this->http->FindNodes("//div[@class='stats_box stats_round_top container-countdown']/div[2]/div[position()=1 or position()=3]");
            $d['DealEndedAt'] = implode(" ", $dealEndedAt);
            //# How many people signed up for a deal (empty)
            $d['PeopleCount'] = "";
            //# Downloaded image of a deal in multiple sizes
            if ($pic = $this->http->FindNodes("//div[@id='image_div_ctl00_ctl00_ctl00_content_body_content_left_content_left_ctl00_coupon_image']/img/@src")) {
                foreach ($pic as $index=>$src) {
                    $d['Picture'][$index]['Url'] = $src;
                }
            }
            //# Saving cache
            $this->storeDeal($deal['CacheID'], $d, 3600 * 24);
            $deal['Deal'] = array_merge($deal['Deal'], $d);

            sleep($this->sleepParse);
        }

        // Main Balance
        foreach ($coupons as $code=>$deal) {
            $this->mainBalance += $deal['Quantity'] * $deal['Balance'];
        }

        return $coupons;
    }

    /**
     * Parsing pagination.
     *
     * @return array Array urls except the first page
     */
    protected function getUrlsPages()
    {
        return [];
    }
}
