<?php

class TAccountCheckerPemmican extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        return true;
    }

    public function Login()
    {
        if (!$this->http->GetURL('http://pemmican.rewards.prizelogic.com/process.aspx?a=Login&email='
                            . urlencode($this->AccountFields['Login'])
                            . '&password='
                            . urlencode($this->AccountFields['Pass'])
                            . '&r=0.6431017639115453')) {
            return false;
        }
        $response_object = json_decode($this->http->Response['body'], true);

        if ($response_object['HasErrors']) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = $response_object['ErrorMessage'];

            return false;
        }

        return true;
    }

    public function Parse()
    {
        $response_object = json_decode($this->http->Response['body'], true);
        $this->SetProperty("MemberSince", $response_object['Result']['DateCreated']);
        $this->SetBalance($response_object['Result']['TotalPoints']);
    }
}
