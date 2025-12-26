<?php

namespace AwardWallet\Engine\spirit\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Notifications extends \TAccountChecker
{
    public $mailFiles = "spirit/statements/it-80102178.eml, spirit/statements/it-80585642.eml";
    public $subjects = [
        '/Confirming Your Free Spirit Account Change$/',
        '/Free Spirit Password Reset$/',
        '/Welcome to Free Spirit[!]$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@fly.spirit-airlines.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Spirit Airlines')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Points'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Hi'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Confirmation Number'))}]")->length == 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]fly\.spirit\-airlines.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]/preceding::tr[contains(normalize-space(), 'Points') and contains(normalize-space(), '#')][2]");
        $this->logger->error($node);

        if (preg_match("/^(\D+)\s*\|\s*([\d\.\,]+)\s*Points\s*\|\s*[#](\d+)\s*\|?$/", $node, $m)) {
            $st->addProperty('Name', $m[1])
                ->setBalance(str_replace(",", "", $m[2]))
                ->setNumber($m[3]);
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
