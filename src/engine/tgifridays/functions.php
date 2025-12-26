<?php

class TAccountCheckerTgifridays extends TAccountChecker
{
    public const REWARDS_PAGE_URL = 'https://tgifridays.com/loyalty-portal/dashboard.php';
    private $headers = [
        'Accept'         => '*/*',
        'Content-Type'   => 'application/json',
        'origin-cookies' => '%7B%7D',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setMaxRedirects(10);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->resumeSession()) {
            return $this->loginSuccessful();
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL(self::REWARDS_PAGE_URL);
        $authorizeUrl = $this->http->currentUrl();
        $companyId = $this->http->FindPreg('#auth.pingone.com/([^/]+)/as/authorize#', false, $authorizeUrl);
        $policyId = $this->http->FindPreg('/"policyId":"([^"]+)"/');
        $authToken = $this->http->FindPreg('/"accessToken":"([^"]+)"/');

        if (!isset($companyId, $policyId, $authToken)) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://auth.pingone.com/$companyId/davinci/policy/$policyId/start", '', $this->headers + [
            'Authorization' => 'Bearer ' . $authToken,
        ]);

        foreach (['email' => $this->AccountFields['Login'], 'password' => $this->AccountFields['Pass']] as $key => $credential) {
            $authParams = $this->http->JsonLog();
            $fields = $authParams->screen->properties->formFieldsList->value ?? [];
            $fields = array_column($fields, 'propertyName');

            if (in_array('password2', $fields)
                && in_array('password', $fields)
            ) {
                throw new CheckException('Please choose a new password to access your existing loyalty account.', ACCOUNT_INVALID_PASSWORD);
            }

            if (in_array('firstName', $fields)
                && in_array('birthday', $fields)
            ) {
                throw new CheckException('Create Your Profile', ACCOUNT_INVALID_PASSWORD);
            }

            if (!isset($authParams->id, $authParams->connectionId, $authParams->interactionId, $authParams->interactionToken)) {
                $message = $authParams->message ?? null;

                if ($message) {
                    $this->logger->error(">>>>>>>>>>>>> check account status");
                    $this->logger->error("[Error]: {$message}");

                    if ($message == "This account has been deactivated") {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                return $this->checkErrors();
            }
            $data = [
                'id'        => $authParams->id,
                'nextEvent' => [
                    'constructType' => 'skEvent',
                    'eventName'     => 'continue',
                    'params'        => [],
                    'eventType'     => 'post',
                    'postProcess'   => new StdClass(),
                ],
                'parameters' => [
                    'buttonType'  => 'form-submit',
                    'buttonValue' => 'submit',
                    $key          => $credential,
                ],
                'eventName' => 'continue',
            ];
            $this->http->PostURL("https://auth.pingone.com/$companyId/davinci/connections/$authParams->connectionId/capabilities/customHTMLTemplate", json_encode($data), $this->headers + [
                'Referer'          => $authorizeUrl,
                'interactionId'    => $authParams->interactionId,
                'interactionToken' => $authParams->interactionToken,
            ]);
        }// foreach (['email' => $this->AccountFields['Login'], 'password' => $this->AccountFields['Pass']] as $key => $credential)
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $message = $response->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                stripos($message, 'The provided password did not match provisioned password') !== false
                || stripos($message, 'Password does not meet validation requirements.') !== false
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $this->http->GetURL($authorizeUrl, ['Referer' => null]);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("
                //p[contains(text(), 'The server is temporarily unable to service your')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("
                //div[contains(text(), 'We are currently updating our site to better serve you, and we should be up shortly!')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->resumeSession()) {
            return $this->loginSuccessful();
        }

        // no errors, no auth // AccountID: 7225260
        if (strstr($this->http->currentUrl(), 'redirect_uri=https://rewards.tgifridays.com/loyalty-portal/api/request.php?type=auth&scope=openid%20profile%20p1:read:user')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json;charset=utf-8',
            'X-CT-APP'     => 'widget',
        ];
        $data = '{"model_data":{"user":{"me":{"properties":["country","date_created_iso","email_address","encrypted_ct_id","facebook_user_id","fan_rank","first_name","last_name","language","mobile_phone_number","photo_url","redeemable_points","segments","third_party_id","tier","total_points","username"],"query":{"type":"me"}}},"client":{"current":{"properties":["fan_levels"],"query":{"type":"current"}}}}}';
        $this->http->PostURL('https://ct-prod.tgifridays.com/request?widgetId=20500', $data, $headers + ['Referer' => 'https://ct-prod.tgifridays.com/widgets/t/account-overview/20500/']);
        $user = $this->http->JsonLog()->model_data->user->me ?? null;

        if (empty($user) || !is_array($user)) {
            return;
        }
        // Balance - Redeemable Points
        $this->SetBalance($user[0]->redeemable_points ?? null);
        $firstName = $user[0]->first_name ?? null;
        $lastName = $user[0]->last_name ?? null;
        // Name
        $this->SetProperty('Name', beautifulName("$firstName $lastName"));

        $data = '{"model_data":{"user":{"me":{"properties":["total_points"],"query":{"type":"me"}}},"client":{"current":{"properties":["fan_levels"],"query":{"type":"current"}}},"reward":{"redeemed_rewards":{"properties":["date_created","digital_reward_url","num_points","title","order_quantity","is_digital_download","is_automatic_campaign","trigger_type","event_type","event_title","coupon_value","coupon_type_id","date_used_at_pos","date_to_expire"],"query":{"type":"redeemed_rewards_me","args":{"row_start":1,"row_end":25}}}}}}';
        $this->http->PostURL('https://ct-prod.tgifridays.com/request?widgetId=21240', $data, $headers + ['Referer' => 'https://ct-prod.tgifridays.com/widgets/t/reward-history/21240/']);
        $rewardsData = $this->http->JsonLog(null, 3, true)['model_data']['reward'] ?? null;

        if (is_array($rewardsData) && !empty($rewardsData)) {
            foreach ($rewardsData as $category => $rewards) {
                if ($category != 'redeemed_rewards') {
                    $this->sendNotification('found rewards // BS');

                    break;
                }
            }
        }

        $data = '{"model_data":{"user":{"me":{"properties":["fan_rank","total_points"],"query":{"type":"me"}}},"client":{"current":{"properties":["fan_levels"],"query":{"type":"current"}}},"activity":{"newest_activities":{"properties":["id","date_completed","date_created","notes","num_points","points_withheld_by_cap","title","is_automatic_campaign","show_activity_point_text","trigger_type","event_type","event_title","additional_points"],"query":{"type":"user_activities_me","args":{"row_start":1,"row_end":25}}}}}}';
        $this->http->PostURL('https://ct-prod.tgifridays.com/request?widgetId=21238', $data, $headers + ['Referer' => 'https://ct-prod.tgifridays.com/widgets/t/activity-history/21238/']);
        $history = $this->http->JsonLog()->model_data->activity->newest_activities ?? [];

        foreach ($history as $row) {
            if (!is_numeric($row->num_points ?? null) || $row->num_points == 0 || !$date = strtotime($row->date_created ?? '')) {
                continue;
            }
            $this->SetProperty('LastActivity', date('m/d/Y', $date));
            $exp = strtotime("+1 year", $date);

            if ($exp > time()) {
                $this->SetExpirationDate($exp);
            }

            break;
        }
    }

    private function loginSuccessful(): bool
    {
        $this->logger->notice(__METHOD__);

        return $this->http->FindPreg("/postCTID\('{$this->AccountFields['Login']}'\)/ims");
    }

    private function resumeSession(): bool
    {
        $authorizeUrl = $this->http->currentUrl();
        $companyId = $this->http->FindPreg('#auth.pingone.com/([^/]+)/as/authorize#', false, $authorizeUrl);
        $policyId = $this->http->FindPreg('/"policyId":"([^"]+)"/');
        $authToken = $this->http->FindPreg('/"accessToken":"([^"]+)"/');

        if (!isset($companyId, $policyId, $authToken)
            || !$this->http->ParseForm('pingOneDaVinciResponseForm')
        ) {
            return false;
        }
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $this->http->PostURL("https://auth.pingone.com/$companyId/davinci/policy/$policyId/start", '', $this->headers + [
            'Authorization' => 'Bearer ' . $authToken,
        ]);
        $response = $this->http->JsonLog();

        if (!isset($response->dvResponse, $response->success) || $response->success !== true) {
            return false;
        }
        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->SetInputValue('dvResponse', $response->dvResponse);

        if (!$this->http->PostForm(['Referer' => $authorizeUrl])
            || !$redirectURL = $this->http->FindPreg('/window\.location\.href\s*=\s*"([^"]+)"/')
        ) {
            return false;
        }
        $this->http->GetURL($redirectURL);

        return true;
    }
}
