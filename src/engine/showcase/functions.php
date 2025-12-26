<?php

class TAccountCheckerShowcase extends TAccountChecker
{
    public $loginType = [
        'username' => 'User Name',
        'card'     => 'Rewards Card',
    ];

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->loginType;
    }

    public function LoadLoginForm()
    {
        // get login form
        $this->http->FilterHTML = false;
        $this->http->GetURL('http://www.star-pass.com/sp2/starpass_new/login.asp');
        // parse form
        if (!$this->http->ParseForm('enroll')) {
            return false;
        }
        // fill fields
        switch ($this->AccountFields['Login2']) {
            case 'username':
                $this->http->SetInputValue('user_name', $this->AccountFields['Login']);

                break;

            case 'card':
                $this->http->SetInputValue('card_no', $this->AccountFields['Login']);

                break;

            default:
                return false;
        }
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        // Post form
        if (!$this->http->PostForm()) {
            return false;
        }
        // Success login?
        if ($this->http->FindPreg('/Sign Out/')) {
            return true;
        }
        // Wrong login/pass
        if ($this->http->FindPreg('/The user name\/rewards card # entered is not on file/')) {
            throw new CheckException('The user name/rewards card # entered is not on file', ACCOUNT_INVALID_PASSWORD);
        }
        // Unknown error
        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//td[@class="loyalty_data"][contains(text(), "Name")][not(contains(text(), "User"))]/following-sibling::td[1]')));
        // Total Credits - Balance
        $this->SetBalance($this->http->FindSingleNode('//td[@class="loyalty_data"][contains(text(), "Total Credits")]/following-sibling::td[1]'));
        // Loyalty
        $this->SetProperty('Loyalty', $this->http->FindSingleNode('//td[@class="loyalty_data"][contains(text(), "Loyalty")]/following-sibling::td[1]'));
        // Member Since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode('//td[@class="loyalty_data"][contains(text(), "Member Since")]/following-sibling::td[1]'));
    }
}
