<?php

namespace AwardWallet\Engine\rapidrewards\Email\Statement;

class SavedStatementText2016En extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/it-4445456.eml, rapidrewards/it-47014954.eml, rapidrewards/statements/it-66760626.eml, rapidrewards/statements/it-67121038.eml";

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        return (strpos($body, 'Rapid Rewards Member') !== false
            && strpos($body, 'Available Pts') !== false) || (strpos($body, 'Rapid Rewards') !== false
            && strpos($body, 'Member since') !== false);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = $this->cutToWord($parser->getHTMLBody(), 'Need help');
        $statement = $this->ParseStatement($this->text($text));

        return [
            'parsedData' => ['Properties' => $statement],
            'emailType'  => 'SavedStatements',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function ParseStatement($text)
    {
        $result = [];

        if (preg_match('/Hello,\s*(.*?)\s*Rapid/', $text, $matches)) {
            $result['Name'] = $matches[1];
        } elseif (preg_match('/Hi,\s*(\D*?)\s+RR\b/', $text, $matches)) {
            $result['Name'] = $matches[1];
        }

        if (preg_match('/R\.R\.\s*#\s*(\d+)/', $text, $matches)) {
            $result['Number'] = $result['Login'] = $matches[1];
        } elseif (preg_match('/RR\s+(\d+)\s+Member since/', $text, $matches)) {
            $result['Number'] = $result['Login'] = $matches[1];
        }

        if (preg_match('/opens popup\)\s*(.*)\s*Available Pts/', $text, $matches)) {
            $result['Balance'] = $result['Points'] = (int) preg_replace('/[^\d]+/', '', $matches[1]);
        } elseif (preg_match('/Last Activity:\s*\d+\/\d+\/\d+\s*(.*)\s*Available Pts/', $text, $matches)) {
            $result['Balance'] = $result['Points'] = (int) preg_replace('/[^\d]+/', '', $matches[1]);
        } elseif (isset($result['Name']) && preg_match("/Hi,\s+{$result['Name']}\s*(\d[\d ,]*)points/", $text, $matches)
        ) {
            $result['Balance'] = $result['Points'] = (int) preg_replace('/[^\d]+/', '', $matches[1]);
        } elseif (isset($result['Name']) && ($points = $this->http->FindSingleNode("//div[@class='availablePointsNumber'][./following-sibling::div[@class='availablePointsInfo']]"))
        ) {
            $result['Balance'] = $result['Points'] = (int) preg_replace('/[^\d]+/', '', $points);
        }

        if (preg_match('#Last Activity:\s*(\d+/\d+/\d+)#s', $text, $matches)) {
            $result['LastActivity'] = $matches[1];
        }

        return $result;
    }

    private function cutToWord($text, $word)
    {
        return substr($text, 0, strpos($text, $word));
    }

    private function text($string)
    {
        return preg_replace('/<[^>]+>/', ' ', str_replace(['<br>', '<br/>', '<br />', '&nbsp;'], ' ', $string));
    }
}
