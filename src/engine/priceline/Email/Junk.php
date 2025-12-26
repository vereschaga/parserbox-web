<?php

namespace AwardWallet\Engine\priceline\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Junk extends \TAccountChecker
{
    public $mailFiles = "priceline/it-860139374.eml, priceline/it-860372450.eml";

    public $lang = 'en';

    public $detectSubjects = [
        'en' => [
            'Your Recent Stay',
        ],
    ];

    public $detectBody = [
        'en' => [],
    ];

    public static $dictionary = [
        'en' => [
            'Stay'       => ['We recently invited you to participate in', 'I hope you enjoyed your recent stay with us at'],
            'Feedback'   => ['We truly value your feedback and would appreciate', 'Please take a few minutes to share your feedback'],
            'Recommends' => ['How likely would you be to recommend', 'How satisfied were you with your overall experience'],
            'Not Likely' => ['Not At All Likely', 'Not at all satisfied'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]priceline\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        // detect Provider
        if (empty($headers['from']) || stripos($headers['from'], 'fwd.priceline.com') === false) {
            return false;
        }

        // detect Format
        foreach ($this->detectSubjects as $detectSubjects) {
            foreach ($detectSubjects as $dSubjects) {
                if (stripos($headers['subject'], $dSubjects) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a/@href[{$this->contains(['priceline.com'])}]")->length === 0
            && stripos($parser->getHeader('subject'), 'Priceline') !== false
        ) {
            return false;
        }

        // detect Format
        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Stay'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('Feedback'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('Recommends'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Not Likely'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByHeaders($parser->getHeaders()) && $this->detectEmailByBody($parser)) {
            $email->setIsJunk(true, 'Not reservation (offer to share feedback)');
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }
}
