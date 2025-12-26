<?php

namespace AwardWallet\Engine\mommytalksurveys\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    use \StringTools;
    use \RegExpTools;

    public function getCredentialsImapFrom()
    {
        return [
            'support@surveyhelpcenter.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Welcome to MommyTalkSurveys#i",
            "#.+?[\s-]+New .*? Survey Now Available#i",
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
        $text = $this->htmlToPlainText($html);

        $result["Login"] = $parser->getCleanTo();
        $result["Name"] = beautifulName(orval(
            clear("#Fw:#i", $this->re("#^(.*?)[\s-]+New .*? Survey Now Available#i", $parser->getSubject())),
            $this->re("#(?:^|\n)\s*Hello\s+([^\n,:]+)#i", $text)
        ));

        return $result;
    }
}
