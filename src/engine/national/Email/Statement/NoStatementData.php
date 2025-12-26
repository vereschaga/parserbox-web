<?php

namespace AwardWallet\Engine\national\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NoStatementData extends \TAccountChecker
{
    public $mailFiles = "national/statements/it-63094753.eml";
    public $from = '/[@.]nationalcar\.com$/';

    public $subject = [
        'en' => [
            'Your Emerald Club profile has been updated',
            'Your Emerald Club password has been reset',
            'Reset your Emerald Club password',
            'Your Emerald Club credit card information has been updated',
            'Your Emerald Club coverages and rental preferences have been updated',
        ],
    ];

    public $provDetect = 'National Car Rental';

    public $lang = '';

    public static $dictionary = [
        "en" => [
            "Member Number:" => 'Member Number:',
            'Your username:' => 'Your username:',
        ],
        "pt" => [
            "Member Number:" => 'Número de membro:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@nationalcar.com') !== false) {
            foreach ($this->subject as $lang => $reSubject) {
                foreach ($reSubject as $subject) {
                    if (preg_match("/{$subject}/", $headers['subject'])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectLang($parser->getSubject()) == true) {
            if ($this->http->XPath->query("//text()[contains(normalize-space(), '{$this->provDetect}')]")->length > 0) {
                if (
                    $this->http->XPath->query("//text()[contains(normalize-space(), 'Emerald Club')]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($this->t('Member Number:'))}]")->length == 0
                    && $this->http->XPath->query("//text()[{$this->contains($this->t('Your username:'))}]")->length == 0
                    && $this->http->XPath->query("//text()[{$this->contains($this->t('PICK UP'))}]")->length == 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectLang($parser->getSubject()) !== true) {
            $this->logger->error('Lang - Not Found!');

            return false;
        }

        $st = $email->add()->statement();

        $st->setMembership(true);

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

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function detectLang($subject)
    {
        $body = $this->http->Response['body'];
        $this->logger->debug($body);

        foreach ($this->subject as $lang => $reSubject) {
            foreach ($reSubject as $subject) {
                if (preg_match("/{$subject}/i", $subject)) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
