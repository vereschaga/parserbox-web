<?php

namespace AwardWallet\Engine\marketexplorer\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "worldmarket@emailworldmarketexplorer.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            " ",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Email",
            "FirstName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();

        if ($n = re('#Thanks\s+for\s+joining,\s+(.*?)!#i', $text)) {
            // credentials-1.eml
            $result['FirstName'] = $n;
        } elseif ($n = re('#Happy\s+(?:anniversary|birthday),\s+(.*?)[!.]#i', $subject)) {
            // credentials-{2,3,4}.eml
            $result['FirstName'] = $n;
        } elseif ($n = re('#\s*(.*),\s+We have received your password reset request#i', $text)) {
            // credentials-5.eml
            $result['FirstName'] = $n;
        } elseif ($n = re('#Explorer\s+(.*?)\s+ID:#i', $text)) {
            // credentials-6.eml
            $result['FirstName'] = $n;
        }

        return $result;
    }
}
