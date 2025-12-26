<?php

namespace AwardWallet\Engine\mabuhay\Credentials;

class Password extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'mabuhaymiles_email@mabuhaymiles.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your personal details from Mabuhay Miles",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Name",
            "Password",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Name'] = re('#Dear M[rs]+ (.*?),#i', $text);
        $result['Password'] = re('#Password\s*:\s*(\S+)#i', $text);

        return $result;
    }

    public function GetRetrieveFields()
    {
        return [
            'Number',
            'SecretAnswer',
            'SecretQuestionId',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL('https://www.mabuhaymiles.com/home/forgot_password.jsp');

        if (!$this->http->ParseForm('forgot')) {
            return false;
        }

        $this->http->SetInputValue('answer', $data['SecretAnswer']);
        $this->http->SetInputValue('id', $data['Number']);
        $this->http->SetInputValue('question', $data['SecretQuestionId']);

        $this->http->PostForm();

        if ($this->http->FindPreg('#Your password will be emailed to you shortly#i')) {
            return true;
        } else {
            return false;
        }
    }
}
