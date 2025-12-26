<?php

class TAccountCheckerSeamless extends TAccountChecker
{
    protected $jsonResult;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        return true;
    }

    public function Login()
    {
        // json params
        $jsonParams = [
            'first_name' => $this->AccountFields['Login'],
            'last_name'  => $this->AccountFields['Login2'],
            'email'      => $this->AccountFields['Pass'],

            'registration_state'          => null,
            'offers'                      => [],
            'extra_params'                => [],
            'extra_url_query_string'      => null,
            'email_review'                => null,
            'facebook_share_api_key'      => null,
            'purl'                        => null,
            'full_offers'                 => [],
            'secondaryOptOut'             => null,
            'additionalDisclaimer'        => null,
            'requireOptIn'                => null,
            'optIn'                       => null,
            'optInChecked'                => null,
            'omitPBExtole'                => null,
            'loginSuccessMessageDuration' => 1000,
            'custom_fields'               => [],
            'name'                        => 'login',
            'src'                         => '/core/inline_topnav/plugins/login.js',
            'size'                        => ['width' => 330, 'height' => 530],
            'opt_in'                      => null,
        ];

        // headers for json-query
        $this->http->setDefaultHeader('Accept', 'application/json, text/javascript, */*; q=0.01');
        $this->http->setDefaultHeader('X-Requested-With', 'XMLHttpRequest');
        $this->http->setDefaultHeader('Content-Type', 'application/json; charset=UTF-8');
        $this->http->setDefaultHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:17.0) Gecko/20100101 Firefox/17.0');

        // send json-query
        $this->http->PostURL('http://seamless.extole.com/offers/2082943378/pronto/signup', json_encode($jsonParams));

        // check for errors
        return (bool) strlen($this->http->Response['body']); // no errors available
    }

    public function Parse()
    {
        // get JSON response
        $this->http->GetURL('http://seamless.extole.com/me/summary');

        // failed to login?
        if (!strlen($this->http->Response['body'])) {
            return false;
        }

        // get JSON data
        $this->jsonResult = json_decode($this->http->Response['body'], true);

        // Balance - Rewards Earned
        $this->safeSetProperty('balance', 'stats', 'earned');
        // Pending
        $this->safeSetProperty('Pending', 'stats', 'pending');
        // Consumer ID
        $this->safeSetProperty('ConsumerId', 'consumer_id');
        // Sent emails
        $this->safeSetProperty('Sent', 'stats', 'sent');
        // Viewed
        $this->safeSetProperty('Viewed', 'stats', 'viewed');
        // Clicked
        $this->safeSetProperty('Clicked', 'stats', 'clicked');

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->http->Response['code'] == 403) {
            throw new CheckException("You'll need to verify your account on seamless.extole.com", ACCOUNT_PROVIDER_ERROR);
        }/*checked*/
    }

    public function safeSetProperty($name, $key1, $key2 = null)
    {
        // exists?
        if ($key2) {
            if (!isset($this->jsonResult[$key1][$key2])) {
                $this->http->Log('Property "' . $name . '" not found');

                return false;
            } else {
                $tmp = $this->jsonResult[$key1][$key2];
            }
        } else {
            if (!isset($this->jsonResult[$key1])) {
                $this->http->Log('Property "' . $name . '" not found');

                return false;
            } else {
                $tmp = $this->jsonResult[$key1];
            }
        }

        // set property/balance
        $name == 'balance' ? $this->SetBalance($tmp) : $this->SetProperty($name, $tmp);

        return true;
    }
}
