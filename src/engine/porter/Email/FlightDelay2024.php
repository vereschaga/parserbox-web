<?php

namespace AwardWallet\Engine\porter\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightDelay2024 extends \TAccountChecker
{
    public $mailFiles = "porter/it-769641998.eml, porter/it-786017999.eml, porter/it-787146252.eml";
    public $subjects = [
        'IMPORTANT: Your flight is delayed',
        'IMPORTANT: Change to your flight',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your flight is delayed.'         => ['Your flight is delayed.', 'Change to your flight'],
            'Confirmation NO.'                => ['Confirmation NO.', 'CONFIRMATION NO.'],
            'Passengers(s)'                   => ['Passengers(s)', 'PASSENGER(S)'],
            'New Departure time'              => 'New Departure time', // hidden
            'New Arrival time'                => 'New Arrival time', // hidden
            'Your updated flight information' => 'Your updated flight information', // hidden
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@notifications.flyporter.com') !== false) {
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
        if ($this->http->XPath->query("//a/@href[{$this->contains('flyporter.com')}]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Your flight is delayed.']) && !empty($dict['Your updated flight information'])
                && $this->http->XPath->query("//node()[{$this->contains($dict['Your flight is delayed.'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Your updated flight information'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]notifications\.flyporter\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your flight is delayed.'])
                && $this->http->XPath->query("//node()[{$this->contains($dict['Your flight is delayed.'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $confs = $this->http->FindNodes("//text()[{$this->contains($this->t('Confirmation NO.'))}]/following::text()[normalize-space()][1]");

        foreach ($confs as $conf) {
            $confDesc = $this->http->FindSingleNode("//text()[{$this->eq($conf)}]/preceding::text()[normalize-space()][1]");
            $f->general()
                ->confirmation($conf, $confDesc);
        }
        $traveller = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Passengers(s)'))}]/following-sibling::tr[1]",
            null, true, "/^(.+?)(?:[+]\d+)?\s*$/");

        $f->general()
            ->traveller($traveller, true);

        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Depart:'))}]/ancestor::tr[normalize-space()][1][{$this->contains($this->t('Arrive:'))}][not(preceding::text()[{$this->eq($this->t('ORIGINAL FLIGHT'))}])]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $airlineText = $this->http->FindSingleNode("preceding-sibling::tr[2]", $root);

            if (preg_match("/^\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\s*$/", $airlineText, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $route = $airlineText = $this->http->FindSingleNode("preceding-sibling::tr[1]", $root);

            if (preg_match("/^\s*(?<dName>.+?)\s*\(\s*(?<dCode>[A-Z]{3})\s*\)\s*{$this->opt($this->t(' to '))}\s*(?<aName>.+?)\s*\(\s*(?<aCode>[A-Z]{3})\s*\)\s*$/", $route, $m)) {
                $s->departure()
                    ->code($m['dCode'])
                    ->name($m['dName'])
                ;
                $s->arrival()
                    ->code($m['aCode'])
                    ->name($m['aName'])
                ;
            }

            $dates = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));
            // $this->logger->debug('$dates = '.print_r( $dates,true));
            if (preg_match("/^\s*(?<date>\S.+?)\s*\W*\s*{$this->opt($this->t('Depart:'))}\s*(?:{$this->opt($this->t('New Departure time'))})?\s*(?<dTime>\d{1,2}:\d{2}.*)\n[\s\S]*?"
                . "{$this->opt($this->t('Arrive:'))}\s*(?:{$this->opt($this->t('New Arrival time'))})?\s*(?<aTime>\d{1,2}:\d{2}.*?)(?<overnight>[-+] *\d+)?(?:\n|\s*$)/", $dates, $m)
            ) {
                $s->departure()
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['dTime']))
                ;
                $s->arrival()
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['aTime']))
                ;

                if (!empty($s->getArrDate()) && !empty($m['overnight'])) {
                    $s->arrival()
                        ->date(strtotime($m['overnight'] . ' day', $s->getArrDate()));
                }
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

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // mar. sept. 10, 2024, 15:15
            // jeu. août 08, 2024, 18:50
            // "/^\s*[[:alpha:]]+[.]\s+([[:alpha:]]+)[.]?\s+(\d{1,2})\s*,\s*(\d{4})\s*,\s*(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$/ui",
        ];
        $out = [
            // "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }
}
