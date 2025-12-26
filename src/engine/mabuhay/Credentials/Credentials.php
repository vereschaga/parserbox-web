<?php

namespace AwardWallet\Engine\mabuhay\Credentials;

class Credentials extends \TAccountCheckerExtended
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
            "Welcome to Mabuhay Miles",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'Name',
            'Login',
            'Password',
            'Number',
            'SecretQuestionId',
            'SecretAnswer',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $secretQuestions = [
            1 => "What's my mother's maiden name",
            2 => "What's my pet's name",
            3 => "What's my favorite movie",
            4 => "What's my favorite color",
            5 => "What's my favorite fruit",
            6 => "What's my favorite song",
        ];
        $text = text($this->http->Response['body']);
        $result['Email'] = $parser->getCleanTo();
        $result['Name'] = re('#Dear M[rs]+ (.*?),#i', $text);
        $result['Login'] = $result['Number'] = re('#Mabuhay Miles Member ID\s*:\s*00(\d+)#i', $text);
        $result['Password'] = re('#Password\s*:\s*(\S+)#i', $text);
        $secretQuestion = re('#Hint Question:\s+(.*)\?#i', $text);

        if ($key = array_search($secretQuestion, $secretQuestions)) {
            $result['SecretQuestionId'] = $key;
        } else {
            $this->http->Log('Unknown secret question "' . $secretQuestion . '", you should check provider\'s website to see if there was added some new question types');
        }
        $result['SecretAnswer'] = re('#Hint Answer\s*:\s*(.*)#i', $text);

        return $result;
    }
}
