<?php

namespace AwardWallet\Engine\searsshop\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "sears@account.sears.com",
            "sears@value.sears.com",
            "order@customerservice.sears.com",
            "sywr@value.sears.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            '#.#',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($s = re('#\s*(.*)\s+Member\s*\#\s*:#i', $text)) {
            // credentials-{1,2}.eml
            $result['Name'] = $s;
        } elseif ($s = re('#Dear\s+(.*)\s*,#i', $text)) {
            // credentials-3.eml
            $result['Name'] = $s;
        } elseif ($s = re('#Hello\s+(.*)\s*!\s+Member#i', $text)) {
            // credentials-4.eml
            $result['Name'] = $s;
        }

        return $result;
    }
}
