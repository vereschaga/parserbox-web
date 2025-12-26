<?php

namespace AwardWallet\Engine\moesgrill\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Statement extends \TAccountChecker
{
    public $mailFiles = "moesgrill/statements/it-79116248.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'detectBody' => [
                'Order a Chicken Club Quesadilla, Burrito or Bowl',
                'Order at moes.com or the Moe\'s app',
                'DOUBLE POINTS ON YOUR NEXT PURCHASE',
                'Welcome to Moe Rewards',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Moe Rewards')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('You are receiving this email because you signed up for Moe Rewards'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('detectBody'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]moes\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        if ($this->detectEmailByBody($parser)) {
            $st->setMembership(true);
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
}
