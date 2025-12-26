<?php

class TAccountCheckerJackinbox extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.eatandearn.com/");

        if (!$this->http->ParseForm(null, 1, "//form[@action = '/login']")) {
            //# Jack’s Rewards and Jack’s Eat & Earn Club programs are no longer available
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Earn Club programs are no longer available')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }
        $this->http->SetInputValue("session[id]", $this->AccountFields['Login']);
        $this->http->SetInputValue("session[password]", $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }
        //# Login successful
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }
        //# Sorry, the email address or password was incorrect.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'address or password was incorrect')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        //# Balance - Total Points
        $this->SetBalance($this->http->FindSingleNode("//div[@class = 'current-points']"));
        //# You will no longer accrue points on your purchases, but will have until 12/31/2012 to redeem any points/rewards left on your cards
        $this->SetExpirationDate(strtotime('12/31/2012'));

        //# POINTS UNTIL YOUR NEXT REWARD
        $this->SetProperty("PointsUntilNextReward", $this->http->FindSingleNode("//p[@class = 'points-left']/span"));

        // SubAccounts - Awards
        $nodes = $this->http->XPath->query("//thead[tr/th[contains(text(), 'Award')]]/following-sibling::tbody/tr");
        $this->http->Log("Total nodes found " . $nodes->length);

        if ($nodes->length > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                $displayName = $this->http->FindSingleNode('td[1]', $nodes->item($i));
                $exp = $this->http->FindSingleNode('td[2]', $nodes->item($i));

                $subAccounts[] = [
                    'Code'           => 'JackinboxAward' . $i,
                    'DisplayName'    => $displayName,
                    'Balance'        => null,
                    'ExpirationDate' => strtotime($exp),
                ];
            }// for ($i = 0; $i < $nodes->length; $i++)

            if (isset($subAccounts)) {
                //# Set Sub Accounts
                $this->SetProperty("CombineSubAccounts", false);
                $this->http->Log("Total subAccounts: " . count($subAccounts));
                //# Set SubAccounts Properties
                $this->SetProperty("SubAccounts", $subAccounts);
            }// if(isset($subAccounts))
        }// if ($nodes->length > 0)

        //# Name
        $this->http->GetURL("http://www.eatandearn.com/my-account/profile");
        $name = CleanXMLValue($this->http->FindSingleNode("//input[@name = 'user[first_name]']/@value")
            . ' ' . $this->http->FindSingleNode("//input[@name = 'user[last_name]']/@value"));

        if (strlen(trim($name)) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
        //# Card I.D.
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//input[@name = 'user[card_id_0]']/@value")
            . $this->http->FindSingleNode("//input[@name = 'user[card_id_1]']/@value")
            . $this->http->FindSingleNode("//input[@name = 'user[card_id_2]']/@value")
            . $this->http->FindSingleNode("//input[@name = 'user[card_id_3]']/@value"));
    }
}
