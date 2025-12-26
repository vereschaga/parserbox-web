<?php

use AwardWallet\Common\Parser\Util\PriceHelper;

class TAccountCheckerParadores extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://paradores.es/en/perfil-amigo";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $this->useChromePuppeteer();
    }

    /*
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
    */

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://paradores.es/en/amigos-de-paradores");

        $access = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'friends-access']"), 15);
        $this->saveResponse();

        if (!$access) {
            return false;
        }

        $access->click();

        $loginInput = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'edit-user']"), 5);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "edit-password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "edit-submit-amigo"]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            return $this->checkErrors();
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $button->click();

        /*
        if (!$this->http->ParseForm("forms-login-amigos")) {
            return $this->checkErrors();
        }

        $this->http->FormURL = 'https://paradores.es/en/amigos-de-paradores?ajax_form=1&_wrapper_format=drupal_ajax';
        $this->http->SetInputValue('user', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('_triggering_element_name', 'op');
        $this->http->SetInputValue('_triggering_element_value', 'Login');
        $this->http->SetInputValue('ajax_page_state[dialogType]', 'ajax');
        $this->http->SetInputValue('_drupal_ajax', '1');
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')]"), 10);
        $this->saveResponse();
        /*
        $headers = [
            'accept'           => 'application/json, text/javascript, *
        /*; q=0.01',
            'x-requested-with' => 'XMLHttpRequest',
        ];

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog($this->http->FindSingleNode('//textarea'));

        if (isset($response[1]->data)) {
            $this->http->SetBody($response[1]->data);
        }

        if (isset($response[1]->command) && $response[1]->command == 'redirect') {
            $this->http->PostURL("https://paradores.es/amigos/getnamebyajax", "path=%2Fen%2Famigos-de-paradores-0");
        }
        */

        if ($this->loginSuccessful()) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            return true;
        }

        if ($message = $this->http->FindSingleNode("//h2[contains(text(),'Error message')]/following-sibling::p")) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'For your security, the new website requires you to renew your password.')
                || strstr($message, 'The data entered does not match.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Accumulated points
        $this->SetBalance($this->http->FindSingleNode("//div[contains(@class, 'bloque-amigo-informacion')]//div[contains(text(), 'Accumulated points')]/following-sibling::div[1]"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(@class, 'nombre-amigo')]")));
        // Card Number Amigo Gold
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//div[contains(text(), 'Card Number')]/following-sibling::div[1]"));

        // refs#23580
        $this->http->GetURL('https://paradores.es/en/mis-puntos');
        $expiringBalance = $this->http->FindSingleNode("//div[@id='validez-content']//div[@class='dato puntos']");
        $expiringDate = $this->ModifyDateFormat($this->http->FindSingleNode("//div[@id='validez-content']//div[@class='dato fecha-dato']"));
        $exp = strtotime($expiringDate);

        if ($expiringBalance > 0 && $exp) {
            // Expiring Balance
            $this->SetProperty("ExpiringBalance", $expiringBalance);
            // Expiration Date
            $this->SetExpirationDate($exp);
        }
    }

    public function ParseItineraries()
    {
//        $this->http->GetURL('https://www.frasershospitality.com/en/fraser-world/account/#!tab1');
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://paradores.es/en/mis-reservas");
        $this->http->RetryCount = 2;
        $items = $this->http->XPath->query("//div[@id='block-reservas-reservas-misreservas']/div[contains(@class,'card reservas')]");
        $this->logger->debug("Total {$items->length} itineraries were found");

        if ($items->length == 0 && $this->http->FindSingleNode("//div/p[contains(text(),'no reservations')]")) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }
        $browser = clone $this->http;

        foreach ($items as $item) {
            $date = $this->http->FindSingleNode("(.//div[contains(text(),'Departure')]/following-sibling::div)[1]", $item);

            if (!$this->ParsePastIts && strtotime($date) < time()) {
                $this->logger->notice("skip old stay #{$item->pms_confirmation_number}");
                $this->sendNotification('check past it // MI');

                continue;
            }
            $link = $this->http->FindSingleNode(".//a[contains(@href,'/en/reservas/modificar/')]/@href", $item);
            $this->http->NormalizeURL($link);
            $browser->GetURL($link);
            $this->parseItinerary($browser, $item);
        }

        return [];
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        return false;
    }

    private function parseItinerary($browser, $item): void
    {
        $this->logger->notice(__METHOD__);
        $h = $this->itinerariesMaster->createHotel();
        $confNo = $browser->FindSingleNode(".//div[contains(text(),'Locator:')]/span");
        $this->logger->info("Parse Hotel #{$confNo}", ['Header' => 3]);

        $h->general()
            ->confirmation($confNo, 'Locator', true)
            ->traveller(beautifulName($browser->FindSingleNode(".//div[contains(@id,'edit-client-name')]")))
        ;

        $h->hotel()
            ->name($browser->FindSingleNode(".//div[contains(@id,'edit-name')]//span"))
            ->address($browser->FindSingleNode(".//div[contains(@class,'address ico-info')]/span"))
            ->phone($browser->FindSingleNode(".//div[contains(@class,'phone ico-info')]/span"))
        ;

        $h->booked()
            ->checkIn2($this->ModifyDateFormat($browser->FindSingleNode(".//div[contains(text(),'Arrival:')]/span")))
            ->checkOut2($this->ModifyDateFormat($browser->FindSingleNode(".//div[contains(text(),'Departure:')]/span")))
            ->guests($browser->FindSingleNode(".//div[contains(text(),'Adults:')]/span"))
            ->kids($browser->FindSingleNode(".//div[contains(text(),'Children:')]/span"))
        ;

        $total = $browser->FindSingleNode(".//div[contains(text(),'Total price:')]/span/text()");
        $h->price()->total(PriceHelper::cost($browser->FindPreg('/^([\d.,]+)/u', false, $total), '.', ','));
        // 691,20 €
        $h->price()->currency($this->currency($browser->FindPreg('/^[\d.,]+\s+(.)/u', false, $total)));

        $cancellation = $browser->FindSingleNode('//p[contains(@class, "reserve-conditions")]');

        $h->general()
            ->cancellation($cancellation);

        if (preg_match("/Cancellations made more than (\d+ day)s before the arrival/ui", $cancellation, $m)) {
            $h->booked()
                ->deadlineRelative($m[1]);
        }

        $this->logger->debug('Parsed Hotel:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function currency($s)
    {
        if (preg_match('#^\s*([A-Z]{3})\s*$#', $s, $m)) {
            return $m[1];
        }
        $sym = [
            '€' => 'EUR',
            //'$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
