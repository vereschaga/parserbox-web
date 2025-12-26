<?php

class TAccountCheckerRedlion extends TAccountChecker
{
    /*
     private $csrf = null;
    */
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.redlion.com/login');
        $headers = [
            "Accept"                     => "application/json",
            "Content-Type"               => "application/json",
            "Origin"                     => "https://myhellorewards.redlion.com",
            "X-Okta-User-Agent-Extended" => "okta-auth-js-1.8.0",
            "X-Requested-With"           => "XMLHttpRequest",
        ];
        /*
                $this->http->GetURL("https://www.redlion.com/rest/session/token", $headers);
                $this->csrf = $this->http->Response['body'];
                if (!$this->csrf)
                    return false;
        */
        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://rlhloyalty.okta.com/api/v1/authn", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $status = $response->status ?? false;

        if ($status == 'SUCCESS') {
            $this->http->GetURL("https://rlhloyalty.okta.com/oauth2/default/v1/authorize?client_id=0oa1ra0pt9Dcme2hE2p7&redirect_uri=https%3A%2F%2Fwww.redlion.com%2Flogin&response_type=id_token&response_mode=okta_post_message&state=Vs1qwE4sX3HGXjdsvj1DtKLEaJAqcC2bFwB2IooQkl4iNb6DY95Itv0nsCCzxkIH&nonce=02pT0KdsUviW8tW2QBqZ80JYoNkjCTReE2Y8oaUPMUXVTrEhC6TOswqKCRzaSlup&prompt=none&sessionToken={$response->sessionToken}&scope=openid%20email%20profile%20address");
            $token = $this->http->FindPreg("/data\.id_token\s*=\s*'([^\']+)/");
            $tokenPars = explode('.', $token);

            if (!$token || !isset($tokenPars[1])) {
                return false;
            }
            $profileInfo = json_decode(base64_decode($tokenPars[1]));
            $this->logger->debug(var_export($profileInfo, true), ["pre" => true]);

            if (!isset($profileInfo->GlobalProfileID)) {
                return false;
            }

            $this->http->GetURL("https://rlhloyalty.okta.com/api/v1/sessions/me");
            $this->http->JsonLog();
            // Name
            $this->SetProperty('Name', beautifulName($profileInfo->name ?? null));
            $data = [
                "token"  => str_replace('\x2D', '-', $token),
                "oktaId" => $profileInfo->sub,
            ];
            $headers = [
                "Accept"       => "application/json, text/plain, */*",
                "Content-Type" => "application/json;charset=utf-8",
                "Origin"       => "https://www.redlion.com",
                /*
                "X-CSRF-Token"     => $this->csrf,
                "X-Requested-With" => "XMLHttpRequest",
*/
                "api-key"      => "3bbd8f466bac4cfeab1d966865b5efac",
            ];
            $this->http->PostURL("https://core.rlhco.com/api/hello-rewards/fetchusercontact", json_encode($data), $headers);

            return true;
        }
        // The username or password entered are invalid. Please try again.
        $message = $response->errorSummary ?? null;

        if ($message == 'Authentication failed') {
            throw new CheckException("The username or password entered are invalid. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog();
        // Member Number
        $this->SetProperty('Number', $response->crmProfile->value[0]->membershipnumber ?? null);
        // Member Since
        if (isset($response->crmProfile->value[0]->joined_date)) {
            $this->SetProperty('MemberSince', $this->http->FindPreg("/([\d\/]+)/", false, $response->crmProfile->value[0]->joined_date));
        }

        // Hello Bucks
        $credits = $response->crmProfile->value[0]->bucks->count ?? null;
        $cards = $response->crmProfile->value[0]->bucks->rewards ?? [];

        if (!is_null($credits) || !empty($cards)) {
            $this->logger->debug("Total {$credits} Bucks were found");
            $bucksBalance = 0;
            $expirationList = [];
            $k = 0;

            foreach ($cards as $card) {
                $k++;
                $bucksBalance += $card->balance;
                $exp = strtotime(str_replace('-', '/', $card->expiration));

                if (isset($expirationList[$exp])) {
                    $expirationList[$exp]['ExpiringBalance'] = $expirationList[$exp]['ExpiringBalance'] + $card->balance;
                } else {
                    $expirationList[$exp]['ExpiringBalance'] = $card->balance;
                }
            }// foreach ($cards as $card)
            ksort($expirationList);

            if (!empty($expirationList)) {
                // Expiration Date
                $this->SetExpirationDate(key($expirationList));
                $this->SetProperty('ExpiringBalance', current($expirationList)['ExpiringBalance']);
            }// if (!empty($expirationList)
            $this->SetBalance($bucksBalance);
        }// if ($credits)
        elseif (
            strstr($this->http->Response['body'], '"bucks":{"status":"OK","payment_method_list":[]}}')
            || strstr($this->http->Response['body'], '"bucks":{"status":"ERROR","message":"FORBIDDEN_EXTERNAL_ID","code":9047}')
        ) {
            $this->SetBalance(0);
        } elseif (
            (strstr($this->http->Response['body'], '{"crmProfile":{"error":true,"message":{"error":" Provide valid contact id"},"code":400}}')
                && in_array($this->AccountFields['Login'], [
                    'toki@tokileephoto.com',
                ]))
            || ($this->http->FindSingleNode('//title[contains(text(), "Technical Difficulties")]')
                && $this->http->Response['code'] == 503)
        ) {
            throw new CheckException("An error occurred while loading your profile.", ACCOUNT_PROVIDER_ERROR);
        }

        // Hello Perks
        $perks = $response->crmProfile->value[0]->perks->rewards ?? null;

        if (!is_null($perks)) {
            $this->logger->debug("Total " . count($perks) . " perks were found");
            $this->SetProperty('CombineSubAccounts', false);
            $perksBalance = 0;

            foreach ($perks as $perk) {
                // Perk
                $displayName = $perk->title ?? null;
                // Expiration
                $exp = $perk->expiration;
                // Status
                $status = $perk->status_description;
                // Voucher #
                $voucher = $perk->voucher_number;

                if (strtolower($status) == 'issued') {
                    $balance = $perk->points ?? null;

                    if ($balance) {
                        $perksBalance += $balance;
                    }
                    $this->AddSubAccount([
                        'Code'           => "redlion{$voucher}",
                        'DisplayName'    => "{$displayName} (Voucher # {$voucher})",
                        'Balance'        => $balance,
                        'ExpirationDate' => strtotime($exp, false),
                        'VoucherNumber'  => $voucher,
                        // Issued
                        'Issued'         => $perk->date_issued,
                    ]);
                }// if (strtolower($status) == 'issued')
            }// foreach ($perks as $perk)

            if ($perksBalance == 0) {
                $this->AddSubAccount([
                    'Code'           => "redlionHelloPerks",
                    'DisplayName'    => "Hello Perks",
                    'Balance'        => $perksBalance,
                ]);
            }
        }// if (!is_null($perks))
    }
}
