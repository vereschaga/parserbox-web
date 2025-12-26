<?php

namespace AwardWallet\Engine\priceline\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flights extends \TAccountChecker
{
    public $mailFiles = "priceline/it-96519499.eml";
    public $subjects = [
        '/Your priceline itinerary for/',
    ];

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@priceline.com') !== false) {
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
        if ($this->http->XPath->query('//a[contains(@href,".priceline.com/") or contains(@href,"www.priceline.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thanks for booking your flight with priceline.com via") or contains(normalize-space(),"Priceline Trip Number")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//text()[{$this->contains($this->t('Passengers'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Depart'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]priceline\.com$/', $from) > 0;
    }

    public function ParseFlights(Email $email): void
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Airline Confirmation Numbers'][1]/following::text()[normalize-space()][1]/ancestor::tr[1]", null, true, "/\:\s*([A-Z\d]+)/"), 'Airline Confirmation Numbers')
            ->travellers($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Passengers')]/following::text()[starts-with(normalize-space(), 'Ticket Number:')]/preceding::text()[normalize-space()][1]"), true);

        $tickets = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Passengers')]/following::text()[starts-with(normalize-space(), 'Ticket Number:')]", null, "/{$this->opt($this->t('Ticket Number:'))}\s*(\d+)/");

        if (count($tickets) > 0) {
            $f->setTicketNumbers($tickets, false);
        }

        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Depart:')]/ancestor::table[2]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineText = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Flight')][not(contains(normalize-space(), 'Overnight Flight'))]", $root);

            if (preg_match("/^(\D+)\s+Flight\s*(\d+)$/u", $airlineText, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $distanceText = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'h ') or contains(normalize-space(), 'miles')]", $root);

            if (preg_match("/^(.+)\,\s+([\d\,]+)\s*miles$/", $distanceText, $m)) {
                $s->extra()
                    ->duration($m[1]);

                if (isset($m[2])) {
                    $s->extra()
                        ->miles($m[2]);
                }
            }

            $dayMonth = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[normalize-space()][1]", $root);
            $timeText = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Flight')]/preceding::text()[normalize-space()][1][not(contains(normalize-space(), 'Overnight Flight'))]", $root);

            if (preg_match("/^([\d\:]+\s*A?P?M)[\s\-]+([\d\:]+\s*A?P?M)$/", $timeText, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($dayMonth . ' ' . $m[1]));

                $s->arrival()
                    ->date($this->normalizeDate($dayMonth . ' ' . $m[2]));
            }

            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Depart:')]/ancestor::tr[1]/descendant::td[2]", $root, true, "/\(([A-Z]{3})\)/"));

            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Arrive:')]/ancestor::tr[1]/descendant::td[2]", $root, true, "/\(([A-Z]{3})\)/"));

            $cabin = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Class')]", $root, true, "/^(\D+)\s*Class/");
            $s->extra()
                ->cabin($cabin);

            $aircraft = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Class')]", $root, true, "/^\D+\s*Class\s*\-\s*(.+)$/");
            $s->extra()
                ->aircraft($aircraft);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $otaConfNumber = $this->http->FindSingleNode("//text()[normalize-space()='Flights']/following::text()[normalize-space()='Priceline Trip Number:'][1]/following::text()[normalize-space()][1]");

        if (!empty($otaConfNumber)) {
            $email->ota()
                ->confirmation($otaConfNumber, 'Priceline Trip Number');
        }

        $this->ParseFlights($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
        $year = isset($this->date) ? date("Y", $this->date) : date("Y");
        $in = [
            "#^\w+\s*(\w+)\s*(\d+)\s*([\d\:]+\s*A?P?M)$#", //Thu Dec 19 08:25 PM
        ];
        $out = [
            "$2 $1 $year, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
