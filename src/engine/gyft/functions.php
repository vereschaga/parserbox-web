<?php
/**
 * Class TAccountCheckerGyft
 * Display name: Gyft (Gyft Points)
 * Database ID: 1171
 * Author: APuzakov
 * Created: 26.03.2015 12:51.
 */
class TAccountCheckerGyft extends TAccountChecker
{
    protected $accountData = [];

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $data = [
            "email"               => $this->AccountFields['Login'],
            "plain_text_password" => $this->AccountFields['Pass'],
            "fetch_profile"       => true,
            "y_timestamp"         => time(),
        ];

        $this->http->setDefaultHeader("Content-Type", "application/json; charset=UTF-8");
        $this->http->PostURL('https://api.gyft.com/api/users/get_authentication_details', json_encode($data));

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.gyft.com/";

        return $arg;
    }

    public function Login()
    {
        $result = $this->http->JsonLog();

        if (!$result) {
            return false;
        }

        if (isset($result->status) && $result->status != '0') {
            throw new CheckException('No matching user for login details.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->accountData['name'] = $result->details->profile->first_name . ' ' . $result->details->profile->last_name;

        $dt = new DateTime('UTC');
        $str = "GET\napplication/json; charset=UTF-8\n\n" . $dt->format('D, d M Y H:i:s \G\M\T') . "\n/api/me/currency/get/PT";
        $key = base64_encode(hash_hmac('sha1', $str, $result->details->authentication_key, true));

        $this->http->setDefaultHeader("Origin", "https://app.gyft.com");
        $this->http->setDefaultHeader("X-Date", $dt->format('D, d M Y H:i:s \G\M\T'));
        $this->http->setDefaultHeader("Authorization", "Gyft " . $result->details->id . ":" . $key);
        $this->http->setDefaultHeader("X-Authorization", "Gyft " . $result->details->id . ":" . $key);

        $this->http->GetURL('https://api.gyft.com/api/me/currency/get/PT');
        $result = $this->http->JsonLog();

        $this->accountData['balance'] = $result->details->available_amount;

        if ($result->status == '0') {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        // Balance -
        $this->SetBalance($this->accountData['balance']);
        // Name
        $this->SetProperty("Name", beautifulName($this->accountData['name']));
    }
}
