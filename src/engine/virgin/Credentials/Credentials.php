<?php

namespace AwardWallet\Engine\virgin\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "FlyingClub@email.virginatlantic.com",
            "flying.club@fly.virgin.com",
            "virginatlantic@email.virgin-atlantic.com",
            "do_not_reply@virgin-atlantic.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Account Details#i",
            "#welcome to a life#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'FirstName',
            'Login',
        ];
    }

    public function setHtmlTextFormat(\PlancakeEmailParser $parser)
    {
        $altCount = $parser->countAlternatives();

        for ($i = 0; $i < $parser->countAttachments() + $altCount; $i++) {
            $info = $parser->getAttachmentHeader($i, 'content-type');
            $finfo = finfo_open(FILEINFO_MIME_TYPE); // возвращает mime-тип

            $body = $parser->getAttachmentBody($i);
            $type = finfo_buffer($finfo, $body);
            finfo_close($finfo);

            if (preg_match('#text/html#i', $type)) {
                $this->http->SetBody($body);
                //return;
            }
        }
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        // set html/text body, otherwise it will not work
        $this->setHtmlTextFormat($parser);
        $text = text($this->http->Response['body']);

        if ($s = re('#Dear\s+(.*)\s*,#i', $text)) {
            $result['FirstName'] = $s;
        }

        if ($s = re('#Name\s*:\s+M[rs]+\s+((?s).*?)\s+MEMBERSHIP\s+(?:NUMBER|No\.)#i', $text)) {
            $result['Name'] = $s;
        }

        //		if ($login = $this->http->FindPreg('#YOUR\s+USERNAME\s*:(?:<[^>]+>|\s)*?([A-Za-z_\d\-]+)#'))
        //			$result['Login'] = $login;
        if ($login = $this->http->FindPreg('#Membership\s+(?:number\s*:|No\.)\s*(?:<[^>]+>|\s)*?([A-Za-z_\d\-]+)#i')) {
            $result['Login'] = $login;
        }

        return $result;
    }

    public function RetrievePassword($data)
    {
        return;
        $this->http->GetURL('https://www.virgin-atlantic.com/en/us/frequentflyer/forgot_password_details.jsp');

        if (!$this->http->ParseForm("forgot")) {
            return false;
        }

        $this->http->SetInputValue("login", $data["Login"]);
        $this->http->SetInputValue("membernumber", $data["Login2"]);
        $this->http->SetInputValue("email_check", $data["Email"]);
        $this->http->SetInputValue("information", 'Password');

        $this->http->PostForm();
        //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        if ($this->http->FindSingleNode("//*[contains(text(), 'We have sent an email message with instructions to create a new password')]")) {
            return true;
        } else {
            return false;
        }
    }

    public function ParseRetrievePasswordEmail(\PlancakeEmailParser $parser)
    {
        $result = [];

        // set html/text body, otherwise it will not work
        $this->setHtmlTextFormat($parser);

        if ($pass = $this->http->FindPreg("#YOUR\s+PASSWORD\s*:(?:<[^>]+>|\s)*?([A-Za-z_\d\-]+)#")) {
            $result['Password'] = $pass;
        }

        return $result;
    }
}
