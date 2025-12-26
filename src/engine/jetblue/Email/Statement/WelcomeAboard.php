<?php

namespace AwardWallet\Engine\jetblue\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class WelcomeAboard extends \TAccountChecker
{
    public $mailFiles = "jetblue/it-510022892.eml, jetblue/statements/it-689142200.eml";
    public $subjects = [
        'Welcome aboard,',
        'TrueBlue statement has landed',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.jetblue.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'JetBlue Airways')]")->length > 0
            && $this->http->XPath->query("//img[contains(@src, 'TrueBlueLogo_White_RGB')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('to your address book to ensure delivery to your inbox'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.jetblue\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Add'))}]/preceding::img[contains(@src, 'TrueBlueLogo_White_RGB')][1]/following::text()[{$this->starts('#')}][1]", null, true, "/^[#](\d{9,})$/");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[normalize-space()='View in a web browser']/following::text()[normalize-space()][1][starts-with(normalize-space(), '#')]", null, true, "/^[#](\d{9,})$/");
        }

        if (!empty($number)) {
            $st->setNumber($number);

            $login = $this->http->FindSingleNode("//text()[normalize-space() = 'This e-mail was sent to']/following::text()[normalize-space()][1]");

            if (!empty($login)) {
                $st->setLogin($login);
            }

            $balance = $this->http->FindSingleNode("//text()[normalize-space()='TrueBlue points balance:']/following::text()[normalize-space()][2]", null, true, "/^([\d\,]+)$/");

            if ($balance !== null) {
                $st->setBalance(str_replace(',', '', $balance))
                   ->setBalanceDate(strtotime($this->http->FindSingleNode("//text()[normalize-space()='TrueBlue points balance:']/following::text()[normalize-space()][1]", null, true, "/\(as\s*of\s*(\d+\/\d+\/\d+)\)\./")));

                $tiles = $this->http->FindSingleNode("//text()[normalize-space()='Tile balance:']/following::text()[normalize-space()][2]", null, true, "/^(\d+)$/");

                if ($tiles !== null) {
                    $st->addProperty('Tiles', $tiles);
                }
            } else {
                $st->setNoBalance(true);
            }
        } else {
            return $email;
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
