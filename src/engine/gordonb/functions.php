<?php

class TAccountCheckerGordonb extends TAccountChecker
{
    public function LoadLoginForm()
    {
        // Invalid login. Please check your credentials and try again
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Invalid login. Please check your credentials and try again', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://rewards.gordonbiersch.com/');

        if (!$this->http->FindSingleNode("//title[contains(text(),'Gordon Biersch')]")) {
            return $this->checkErrors();
        }

        $this->http->GetURL('https://rewards.gordonbiersch.com/config.bundle.js');
        $response = $this->http->JsonLog($this->http->FindPreg('/id:\s*(\[.+?\])/s'));

        if (empty($response) || count($response) !== 2) {
            return $this->checkErrors();
        }

        $this->http->setDefaultHeader('Accept', 'application/vnd.stellar-v1+json');
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://app.gordonbiersch.com/oauth/token', [
            'grant_type'    => 'password',
            'client_id'     => $response[0],
            'client_secret' => $response[1],
            'email'         => $this->AccountFields['Login'],
            'password'      => $this->AccountFields['Pass'],
        ], [
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        ]);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        $this->http->GetURL("https://gordonbiersch.com/");

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re doing some updates, we\'ll be back with something special!")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->access_token)) {
            $this->http->setDefaultHeader('Authorization', 'Bearer ' . $response->access_token);

            return true;
        }
        // Invalid login. Please check your credentials and try again
        if ($message = $this->http->FindPreg('/"message":"(Invalid login. Please check your credentials and try again)"/')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://app.gordonbiersch.com/api/profile.json?_=' . time());
        $response = $this->http->JsonLog();

        if (!isset($response->data->first_name)) {
            return;
        }
        $this->SetProperty('Name', beautifulName($response->data->first_name . ' ' . $response->data->last_name));

        // Member Since
        if (isset($response->data->member_since)) {
            $this->SetProperty('MemberSince', date('m/d/Y', strtotime($response->data->member_since)));
        }

        // Balance - Points
        $this->http->GetURL('https://app.gordonbiersch.com/api/summary.json');
        $response = $this->http->JsonLog();

        if (isset($response->data->metrics->point->balance)) {
            $this->SetBalance($response->data->metrics->point->balance);
        }

        // Giftcards
        $this->http->GetURL('https://app.gordonbiersch.com/api/giftcards.json?layout=medium_rectangle&sort_by=usage_end&sort_dir=asc&dataKey=datakey%3A%7B%22layout%22%3A%22medium_rectangle%22%2C%22sort_by%22%3A%22usage_end%22%2C%22sort_dir%22%3A%22asc%22%7D&with_balance=true&active=true&usable=true&_=' . time());
        $response = $this->http->JsonLog();

        if (!empty($response->data->giftcards)) {
            $this->SetProperty("CombineSubAccounts", false);

            foreach ($response->data->giftcards as $item) {
                if (isset($item->card_number, $item->balance, $item->usage_end_date, $item->active) && $item->active === true) {
                    $this->AddSubAccount([
                        'Code'           => 'gordonbGiftcards' . $item->card_number,
                        'DisplayName'    => 'Reward #' . $item->card_number,
                        'Balance'        => $item->balance,
                        'ExpirationDate' => strtotime($this->http->FindPreg('/\d+\-\d+\-\d+/', false, $item->usage_end_date), false),
                    ]);
                }
            }
        }
    }
}
