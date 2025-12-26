<?php

namespace AwardWallet\Engine\kestrelflyer\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationFor extends \TAccountChecker
{
    public $mailFiles = "kestrelflyer/it-527127729.eml, kestrelflyer/it-539208724.eml, kestrelflyer/it-546302575.eml";
    public $subjects = [
        'Confirmation for reservation ',
    ];

    public $lang = '';
    public $lastDate;

    public $detectLang = [
        "en" => ["AIRLINE"],
        "fr" => ["COMPAGNIE"],
    ];

    public static $dictionary = [
        "en" => [
        ],

        "fr" => [
            'AIRLINE'                => 'COMPAGNIE :',
            'TOTAL DURATION'         => 'DURÉE TOTALE',
            'AIRCRAFT:'              => 'APPAREIL :',
            'Reservation number:'    => 'Numéro de réservation :',
            'Adult'                  => 'Adulte',
            'E-ticket number:'       => 'Numéro de billet Electronique :',
            'Frequent Flyer number:' => 'Frequent Flyer number:',
            'CABIN:'                 => 'CABINE',
            'DURATION'               => 'DURÉE',
            //'terminal' => '',
            'Total for all travellers' => 'Total pour tous les voyageurs',
        ],
    ];

    public function detectLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@airmauritius.com') !== false) {
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
        $this->detectLang();

        return $this->http->XPath->query("//text()[contains(normalize-space(), 'reservations_mru@airmauritius.com')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('AIRLINE'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('TOTAL DURATION'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('AIRCRAFT:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airmauritius\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation number:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Reservation number:'))}\s*([A-Z\d]{6})/su"))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Adult'))}]/following::text()[normalize-space()][1]"));

        $tickets = $this->http->FindNodes("//text()[{$this->eq($this->t('E-ticket number:'))}]/following::text()[normalize-space()][1]");

        if (count($tickets) > 0) {
            $f->setTicketNumbers(array_filter(array_unique($tickets)), false);
        }

        $accounts = $this->http->FindNodes("//text()[{$this->eq($this->t('Frequent Flyer number:'))}]/following::text()[normalize-space()][1]");

        if (count($accounts) > 0) {
            $f->setAccountNumbers(array_filter(array_unique($accounts)), true);
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('AIRLINE'))}]/ancestor::table[3]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $text = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            if (preg_match("/{$this->opt($this->t('AIRLINE'))}\n.+\s+\((?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\)/", $text, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $aircraft = $this->re("/{$this->opt($this->t('AIRCRAFT:'))}\n(.+)/", $text);

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            $cabin = $this->re("/{$this->opt($this->t('CABIN:'))}\n(.+)/", $text);

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $duration = $this->re("/{$this->opt($this->t('DURATION'))}\n(.+)/", $text);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $date = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);

            if (!preg_match("/.+\s\d{4}/", $date)) {
                $date = $this->lastDate;
            } else {
                $this->lastDate = $date;
            }

            if (preg_match("/(?<depTime>\d+\:\d+).*\((?<depCode>[A-Z]{3})\)\s*.*\n(?<arrTime>\d+\:\d+).*\((?<arrCode>[A-Z]{3})\)/su", $text, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $m['depTime']))
                    ->code($m['depCode']);

                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $m['arrTime']))
                    ->code($m['arrCode']);
            }

            $depTerminal = $this->re("/\d+\:\d+.+{$this->opt($this->t('terminal'))}\s*(\S+)\s+.*\d+\:\d+/su", $text);

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $arrTerminal = $this->re("/\d+\:\d+.*\d+\:\d+.+{$this->opt($this->t('terminal'))}\s*(\S+)/su", $text);

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total for all travellers'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]");

            if (preg_match("/^(?<currency>[A-Z]{3})\s+(?<total>[\d\.\,\s]+)/", $price, $m)) {
                $f->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

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
        return count(self::$dictionary);
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^\w+\s*(\d+\s+\w+\s+\d{4}\,\s*\d+\:\d+)$#u", //mardi 19 décembre 2023, 14:25
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);
        //$this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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
