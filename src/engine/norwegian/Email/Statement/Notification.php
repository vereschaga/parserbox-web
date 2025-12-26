<?php

namespace AwardWallet\Engine\norwegian\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Notification extends \TAccountChecker
{
    public $mailFiles = "norwegian/statements/it-70309055.eml, norwegian/statements/it-70366170.eml, norwegian/statements/it-77254631.eml";
    public $subjects = [
        '/^Norwegian has received an inquiry for a new password$/',
        '/^Profile Changed Notification/',
        '/we have some news regarding your Norwegian Reward membership$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Best regards from Norwegian Customer Care' => [
                'Best regards from Norwegian Customer Care',
                'Your Norwegian profile has changed',
                'Simple and easy. As it always has been, and as it always will be',
            ],
            'Your new password is:' => [
                'Your new password is:',
                'Password updated',
                'We would just like to inform you about an adjustment made to our Privacy Policy',
            ],
            'Norwegian has received a inquiry for a new password' => [
                'Norwegian has received a inquiry for a new password',
                'If you do not recognize this change, please contact Norwegian',
                'You have received this email because you are a registered member of Norwegian Reward',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@norwegian.no') !== false) {
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
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Best regards from Norwegian Customer Care'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Norwegian has received a inquiry for a new password'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your new password is:'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]norwegian\.no$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        if ($name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/{$this->opt($this->t('Hi'))},?\s+([[:alpha:]\s.\-]{3,})!/")) {
            $st->addProperty('Name', $name);
            $st->setNoBalance(true);
        } else {
            $st->setMembership(true);
        }
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
