<?php

namespace AwardWallet\Engine\transavia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingChanged2 extends \TAccountChecker
{
    public $mailFiles = "transavia/it-813452271.eml, transavia/it-813530274.eml";
    public $subjects = [
        '/Your booking [A-Z\d]{6} has been changed/',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['Flight number:'],
        'fr' => ['Numéro de vol:'],
    ];

    public static $dictionary = [
        "en" => [
        ],

        "fr" => [
            'Flight schedules are local times' => 'Les horaires sont indiqués en heures locales',
            'Your original flight'             => 'Votre vol initial',

            'Hello'               => 'Bonjour',
            'Reservation number:' => 'Numéro de réservation:',
            'Your new flight'     => 'Votre nouveau vol',
            'Flight number:'      => 'Numéro de vol:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@notification.transavia.com') !== false) {
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
        $this->assignLang();

        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Transavia')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your new flight'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight schedules are local times'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight number:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your original flight'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]notification\.transavia.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/{$this->opt($this->t('Hello'))}\s*(\D+)\,/"))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation number:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Reservation number:'))}\s*([A-Z\d]{6})/"));

        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Your new flight'))}]/ancestor::table[2]/following::table[1]/descendant::text()[{$this->contains($this->t('Flight number:'))}]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            if (preg_match("/{$this->opt($this->t('Flight number:'))}\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{4})/", $this->http->FindSingleNode(".", $root), $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $depInfo = implode("\n", $this->http->FindNodes("./ancestor::table[1]/preceding-sibling::table/descendant::tr/td[1]", $root));
            $this->logger->debug($depInfo);

            if (preg_match("/^\w+\s+(?<date>\d+\s*\w+\s*\d{4})\n+(?<depTime>[\d\:]+)\n(?<depName>.+)\n(?<depCode>[A-Z]{3})/u", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['depTime']));
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./ancestor::table[1]/preceding-sibling::table/descendant::tr/td[last()]", $root));

            if (preg_match("/^\w+\s+(?<date>\d+\s*\w+\s*\d{4})\n+(?<arrTime>[\d\:]+)\n(?<arrName>.+)\n(?<arrCode>[A-Z]{3})/u", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['arrTime']));
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^[\w\-]+\,\s*(\d+)\.?\s*(?:de\s+)?(\w+)(?:\s+de)?\s*(\d{4})$#u", //Miércoles, 19 de mayo de 2021
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
