<?php

namespace AwardWallet\Engine\regalhotels\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'regalrewards@regalhotel.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Regal Rewards",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $html = $this->http->Response['body'];
        $text = text($html);
        $result["Login"] = $parser->getCleanTo();

        if ($n = re("#your\s+membership\s+number\s+([A-Z0-9]+)#", $text)) {
            $result["Login!"] = $n;
        }

        if ($n = re("#Your\s+membership\s+number\s+is\s+([A-Z0-9]+)#", $text)) {
            $result["Login!"] = $n;
        }

        if ($n = re("#Member\s+No.:\s+([A-Z0-9]+)#", $text)) {
            $result["Login!"] = $n;
        }

        $result["Name"] = beautifulName(orval(
            re("#Dear\s+M[rsi]+\.?\s+(\w+\s+\w+)#", $text),
            re("#Dearest\s+(\w+\s+\w+)#", $text),
            re("#Name:\s+M[rsi]+\.?\s+(\w+\s+\w+)#", $text)
        ));

        return $result;
    }
}
