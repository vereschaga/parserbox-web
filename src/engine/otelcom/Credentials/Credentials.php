<?php

namespace AwardWallet\Engine\otelcom\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'no-reply@otel.com',
            'traveldeals@email.otel.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Your otel.com#i",
            "#Becoming an otel.com Member#i",
            "#Happy Weekend#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Name",
            "Password",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $html = $this->http->Response['body'];
        $text = text($html);

        $result["Login"] = $parser->getCleanTo();
        $result["Name"] = orval(
            re("#(?:^|\n)\s*Dear\s+([^\n,:!.]+)#i", $text),
            re("#^(?:Fw:\s*)?(.*?),\s*Happy Weekend#i", $parser->getSubject())
        );

        $result["Password"] = re("#(?:^|\n)\s*Password\s*:\s*([^\s]+)#i", $text);

        return array_filter($result);
    }
}
