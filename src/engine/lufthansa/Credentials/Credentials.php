<?php

namespace AwardWallet\Engine\lufthansa\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'OnlineServices@lufthansa.com',
            'onlineServices@lufthansa.com',
            'newsletter@newsletter.lufthansa.com',
            'mail@e.milesandmore.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome ",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);

        if ($this->http->FindSingleNode("//img[contains(@src,'/media_1081533292.gif')]/@src")) {
            $result['Login'] = $this->http->FindSingleNode("//img[contains(@src,'/media_1081533292.gif')]/ancestor::tr[1]/following-sibling::*[1]", null, true, "#(\d+)#");
            $result['Name'] = beautifulName(trim($this->http->FindSingleNode("//img[contains(@src,'/media_1081533292.gif')]/ancestor::tr[1]/following-sibling::*[1]", null, true, "#\d+(.+)#")));
        } else {
            $result['Login'] = $this->http->FindSingleNode("//*[contains(text(),'Card no')]", null, true, "#(\d+)#");
            $result['Name'] = beautifulName(re('#Welcome\s+to\s+Miles\s+&\s+More,\s+M[rs]+\.\s+(.*?)\s*!#i', $text));
        }

        return $result;
    }
}
