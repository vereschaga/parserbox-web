<?php

namespace AwardWallet\Engine\transavia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingChanged extends \TAccountChecker
{
    public static $dictionary = [
        "en" => [
            // 'Dear' => '',
            'mainMSG'    => 'in your booking',
            'New Flight' => ['New Flight', 'New'],
        ],
        "de" => [
            // 'Dear' => '',
            'mainMSG'        => 'in Ihrer Buchung',
            'Flight'         => 'Flug',
            'From'           => 'Von',
            'To'             => 'Nach',
            'Initial flight' => 'Ursprünglicher Flug',
        ],
        "it" => [
            'Dear'           => 'Gentile signor',
            'mainMSG'        => 'della tua prenotazione',
            'Flight'         => 'Volo',
            'From'           => 'Da',
            'To'             => 'A',
            'Initial flight' => 'Volo originario',
            'New Flight'     => 'Nuovo volo',
        ],
        "nl" => [
            'Dear'           => 'Beste ',
            'mainMSG'        => ['van je boeking', 'vlucht met boekingsnummer'],
            'Flight'         => ['Vlucht', 'vlucht'],
            'From'           => 'Van',
            'To'             => 'Naar',
            'Initial flight' => ['Eerste vlucht', 'Oorspronkelijke vlucht'],
            'New Flight'     => 'Nieuwe vlucht',
        ],
    ];
    public $mailFiles = "transavia/it-12233842.eml, transavia/it-5054444.eml, transavia/it-5063340.eml";
    public $reFrom = "transavia";
    public $Subj;
    public $reSubject = [
        "en" => "Your\s+booking\s+([A-Z\d]+)\s+has\s+been\s+changed",
        "de" => "Ihre\s+Buchung\s+([A-Z\d]+)\s+wurde\s+geändert",
        "it" => "La\s+sua\s+prenotazione\s+([A-Z\d]+)\s+è\s+stata\s+modificata",
        "nl" => "Je\s+boeking\s+([A-Z\d]+)\s+is\s+gewijzigd",
    ];
    public $reBody = 'www.transavia.com';
    public $reBody2 = [
        "en"   => "New flight details",
        "en2"  => "new flight details",
        "de"   => "Ihre neuen Flugdaten",
        "it"   => "Nuovi dati di volo",
        "nl"   => "Nieuwe vluchtgegevens",
        "nl2"  => "Dit zijn je nieuwe vluchtgegevens:",
    ];

    public $lang = "en";

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (preg_match("#{$re}#iu", $headers["subject"])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->Subj = $parser->getSubject();
        $this->http->FilterHTML = false;
        $itineraries = [];
        $NBSP = chr(194) . chr(160);
        $body = str_replace($NBSP, ' ', html_entity_decode($this->http->Response["body"]));

        foreach ($this->reBody2 as $lang => $re) {
            if (mb_strpos($body, $re, 0, 'UTF-8') !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
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

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s*\b([[:alpha:]][[:alpha:] \-]+)\,/");

        if (!empty($traveller)) {
            $f->general()
                ->traveller($traveller);
        }

        $confirmation = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t('mainMSG') . "')]", null,
            false, "#" . $this->t('mainMSG') . "\s+([A-Z\d]+)#");

        if (empty($confirmation) && preg_match("#" . $this->reSubject[$this->lang] . "#", $this->Subj, $m)) {
            $confirmation = $m[1];
        }

        $f->general()
            ->confirmation($confirmation);

        $xpath = "//tr[td[contains(text(),'" . $this->t('Flight') . "') and position()=1] and td[contains(text(),'" . $this->t('From') . "')] and td[contains(text(),'" . $this->t('To') . "')]]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//tr[td[contains(text(),'" . $this->t('Flight') . "') and position()=2] and td[contains(text(),'" . $this->t('From') . "')] and td[contains(text(),'" . $this->t('To') . "')]]/following-sibling::tr[./td[1][not(contains(.,'" . $this->t('Initial flight') . "'))]]";
            $nodes = $this->http->XPath->query($xpath);
            $num = 1;

            if ($nodes->length == 0) {
                $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
            }
        } else {
            $num = 0;
        }

        if ($nodes->length === 0) {
            $xpath = "//text()[{$this->eq($this->t('New Flight'))}]/ancestor::tr[1]";
            $nodes = $this->http->XPath->query($xpath);
            $num = 1;
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $i = $num;
            $i++;
            $node = $this->http->FindSingleNode("./td[{$i}]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $i++;
            $node = $this->http->FindSingleNode("./td[{$i}]", $root);

            if (preg_match("#(.+?)\s*\(\s*([A-Z]{3})\s*\)\s*(\d+:\d+)\s*\w*\s*(\d+\s*\S*\s*\d+)#", $node, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2])
                    ->date(strtotime($this->dateStringToEnglish($m[4] . ' ' . $m[3])));
            }
            $i++;
            $node = $this->http->FindSingleNode("./td[{$i}]", $root);

            if (preg_match("#(.+?)\s*\(\s*([A-Z]{3})\s*\)\s*(\d+:\d+)\s*\w*\s*(\d+\s*\S*\s*\d+)#", $node, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2])
                    ->date(strtotime($this->dateStringToEnglish($m[4] . ' ' . $m[3])));
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
