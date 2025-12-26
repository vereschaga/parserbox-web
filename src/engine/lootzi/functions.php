<?php

class TAccountCheckerLootzi extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->GetURL("http://lootzi.com");

        return true;
    }

    public function Login()
    {
        $data = [
            'email'    => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
            'remember' => 0,
        ];
        $this->http->GetURL("http://lootzi.com/api/signin/?" . http_build_query($data));

        $data = json_decode($this->http->Response['body']);

        if (isset($data)) {
            if ($data->success) {
                return true;
            } else {
                $message = '';

                foreach ($data->errors as $err) { // do stuff like javascript /*checked*/
                    $message .= $err . "\n";
                }

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }// if (isset($data))

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL("http://lootzi.com/account/");
        //# Balance - Available cash back (My cash back)
        $this->SetBalance($this->http->FindSingleNode("//td[@id = 'available1']", null, true, "/[\d\.\,]+/ims"));
        //# Pending cash back (My cash back)
        $this->SetProperty("PendingCashback", $this->http->FindSingleNode("//td[@id = 'pending1']"));
        //# Total lifetime (My cash back)
        $this->SetProperty("TotalLifetime", $this->http->FindSingleNode("//td[@id = 'total1']"));

        //# Available cash back (My referral cash back)
        $this->SetProperty("ReferralAvailable", $this->http->FindSingleNode("//td[@id = 'available2']"));
        //# Pending cash back (My referral cash back)
        $this->SetProperty("ReferralPending", $this->http->FindSingleNode("//td[@id = 'pending2']"));

        //# Name
        $this->http->GetURL("http://lootzi.com/account/my-profile/");
        $name = $this->http->FindSingleNode("//input[@name = 'first_name']/@value") . ' ' . $this->http->FindSingleNode("//input[@name = 'last_name']/@value");

        if (strlen(CleanXMLValue($name)) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }
}
