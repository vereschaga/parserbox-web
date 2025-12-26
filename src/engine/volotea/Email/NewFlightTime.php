<?php

namespace AwardWallet\Engine\volotea\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NewFlightTime extends \TAccountChecker
{
    public $mailFiles = "volotea/it-157493631.eml, volotea/it-400601095.eml";
    public $subjects = [
        ', there has been a change to your flights',
        'Volotea •Notification of strike in',
        // it
        ', i Suoi voli hanno subito un cambio - ',
        // fr
        'Volotea •Avis de grève en',
    ];

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
            // 'Booking no.' => '',
            // 'Hello' => '',
            'We regret to inform you that we have been forced to reschedule your flight' => [
                'We regret to inform you that we have been forced to reschedule your flight',
                'we have been forced to cancel the following flight of the booking', ],
            'New time'        => 'New time',
            'StatusCancelled' => 'Cancelled',
            // 'Departure' => '',
            // 'Arrival' => '',
        ],
        "it" => [
            'Booking no.'                                                                => 'Prenotazione n.',
            'Hello'                                                                      => 'Gentile',
            'We regret to inform you that we have been forced to reschedule your flight' => 'Siamo spiacenti di informarla che siamo stati costretti a riprogrammare il suo volo',
            'Departure'                                                                  => 'Partenza',
            'Arrival'                                                                    => 'Arrivo',
        ],
        "fr" => [
            'Booking no.'                                                                => 'Réservation n°',
            'Hello'                                                                      => 'Bonjour',
            'We regret to inform you that we have been forced to reschedule your flight' => ', nous avons été contraints de reporter votre vol.',
            'Departure'                                                                  => 'Départ',
            'Arrival'                                                                    => 'Arrivée',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && (stripos($headers['from'], '@volotea.com') !== false
            || stripos($headers['from'], '@notifications.volotea.com') !== false)
        ) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Volotea')]")->length > 0) {
            foreach (self::$dictionary as $dict) {
                if (!empty($dict['We regret to inform you that we have been forced to reschedule your flight'])
                    && $this->http->XPath->query("//text()[{$this->contains($dict['We regret to inform you that we have been forced to reschedule your flight'])}]")->length > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]volotea\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking no.'))}]", null, true, "/{$this->opt($this->t('Booking no.'))}\s*([A-Z\d]{6})$/u"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/{$this->opt($this->t('Hello'))}\s*(\D+)\,/u"));

        $nodes = $this->http->XPath->query("//tr[count(*)=3][*[1]/descendant::text()[{$this->eq($this->t('Departure'))}]][*[3]/descendant::text()[{$this->eq($this->t('Arrival'))}]]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("preceding::td[not(.//td)][normalize-space()][2]", $root, true, "/^\s*(.+?)\s*:$/");

            $depText = $this->http->FindSingleNode("*[1]", $root);

            if (preg_match("/^\s*(?<time>\d{1,2}\.\d{2})h?(?:\s*\d{1,2}\.\d{2}h?)?\s*(?<name>.+)\s+{$this->opt($this->t('Departure'))}/u", $depText, $m)) {
                $time = str_replace('.', ':', $m['time']);
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $time))
                    ->name($m['name'])
                    ->noCode();
            }

            $s->airline()
                ->name($this->http->FindSingleNode("*[2]", $root, true, "/^([A-Z\d]{2})/"))
                ->number($this->http->FindSingleNode("*[2]", $root, true, "/^[A-Z\d]{2}\s*(\d{2,4})/"));

            $arrText = $this->http->FindSingleNode("*[3]", $root);

            if (preg_match("/^\s*(?:\d{1,2}\.\d{2}h?\s*)?(?<time>\d{1,2}\.\d{2})h?\s*(?<name>.+)\s+{$this->opt($this->t('Arrival'))}/u", $arrText, $m)) {
                $time = str_replace('.', ':', $m['time']);
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $time))
                    ->name($m['name'])
                    ->noCode();
            }

            $status = $this->http->FindSingleNode("preceding::td[not(.//td)][normalize-space()][1]", $root);
            $s->extra()
                ->status($status);

            if (preg_match("/^\s*{$this->opt($this->t('StatusCancelled'))}\s*$/", $status)
                || $this->http->XPath->query(".//text()[normalize-space()]", $root)->length === $this->http->XPath->query(".//text()[normalize-space()][ancestor::*[contains(@style, '#D3CBCC') or contains(@style, ':#B5A7A8')]]", $root)->length
            ) {
                $s->extra()
                    ->cancelled();
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Departure']) && !empty($dict['Arrival'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Departure'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Arrival'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->date = strtotime($parser->getHeader('date'));

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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        // $this->logger->debug('normalizeDate = ' . print_r($str, true));
        $in = [
            //08 Jun, Wednesday, 16:05
            "#^(\d+)\s*(\w+)\,\s*(\w+)\,\s*([\d\:]+)$#u",
        ];
        $out = [
            "$3, $1 $2 $year, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
