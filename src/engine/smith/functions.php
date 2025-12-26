<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSmith extends TAccountChecker
{
    use ProxyList;

    public static function FormatBalance($fields, $properties)
    {
        $format = [
            'USD' => '&#36;%0.2f',
            'EUR' => '&euro;%0.2f',
            'AUD' => 'AUD %0.2f',
            'SGD' => 'SGD %0.2f',
            'HKD' => 'HKD %0.2f',
            'CAD' => 'CAD %0.2f',
            'SEK' => 'SEK %0.2f',
            'GBP' => 'GBP %0.2f',
        ];

        if (isset($properties['Currency']) && isset($format[$properties['Currency']])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], $format[$properties['Currency']]);
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser(): void
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyDOP());
    }

    public function IsLoggedIn(): bool
    {
        return $this->loginSuccessful();
    }

    public function LoadLoginForm(): bool
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.mrandmrssmith.com/login');

        if (!$this->http->ParseForm('LoginType')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('LoginType[email]', $this->AccountFields['Login']);
        $this->http->SetInputValue('LoginType[password]', $this->AccountFields['Pass']);

        return true;
    }

    public function Login(): bool
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($message = $this->http->FindSingleNode('//div[@class="FormErrors"]/ul/li[1]/p[1]')) {
            if (str_contains($message, 'Please review your username and password and try again')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->logger->error("[Error]: {$message}");
            $this->DebugInfo = $message;

            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse(): void
    {
        $userData = $this->http->FindPreg("/dataLayer\.push\(\{\W+user : \{(.+)},\W+env:/s");

        if (empty($userData)) {
            return;
        }
        // Currency
        $this->SetProperty('Currency', $this->http->FindPreg('/\WloyaltyCurrency: "([^"]+)/', false, $userData));
        // Balance - Available funds
        $this->SetBalance($this->http->FindPreg('/\WloyaltyAmount: "([^"]+)/', false, $userData));
        // Status
        $this->SetProperty('Status', $this->http->FindPreg('/\WmembershipCategory: "([^"]+)/', false, $userData));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindPreg('/name: "(\w+ \w+)/', false, $userData)));
        // Number
        $this->SetProperty('Number', $this->http->FindPreg('/\WmemberId: "([^"]+)/', false, $userData));
    }

    public function ParseItineraries(): array
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://www.mrandmrssmith.com/members/bookings/get?_format=json', [
            'Referer' => 'https://www.mrandmrssmith.com/members/bookings',
        ]);
        $response = $this->http->JsonLog();

        if (empty($response->blocks) || !is_array($response->blocks)) {
            return [];
        }
        $pages = array_column($response->blocks[0]->content->bookings ?? [], 'confirmation_page');

        foreach ($pages as $page) {
            $this->http->GetURL($page);
            $r = $this->itinerariesMaster->add()->hotel();
            $r->addConfirmationNumber($this->http->FindSingleNode('//p[@class="p-consultant-bookingRef"]', null, true, '/Order reference: (\w+)/'), 'Order reference');
            $cards = $this->http->XPath->query('//article[starts-with(@class, "c-card")]');

            if ($cards->length > 1) {
                $this->sendNotification('refs #9364 found more than 1 hotel card // BS');
            }
            // further parsing
        }

        return [];
    }

    private function loginSuccessful(): bool
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://www.mrandmrssmith.com/members/loyalty');

        if ($this->http->FindSingleNode('//a[contains(@href, "logout")]/@href | //span[contains(@class, "user-name")]')) {
            return true;
        }

        return false;
    }

    private function checkErrors(): bool
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
