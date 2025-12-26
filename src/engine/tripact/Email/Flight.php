<?php

namespace AwardWallet\Engine\tripact\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "tripact/it-100961704.eml, tripact/it-83831462.eml, tripact/it-84266025.eml";
    public $subjects = [
        '/eTicket \- Flight to/',
        '/Canceled \- Flight to/',
    ];

    public $lang = 'en';

    public $date;

    public static $dictionary = [
        "en" => [
            'Flight Summary' => ['Flight Summary', 'Flights Summary', 'Your upcoming trip could be impacted'],
            'View Itinerary' => ['View Itinerary', 'Check in'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@tripactions.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'TripActions')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Flight Summary'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('View Itinerary'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tripactions\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confNo = $this->http->FindSingleNode("//text()[normalize-space()='Confirmation Number:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation Number:'))}\s*([A-Z\d]{6})/");

        if (!empty($confNo)) {
            $f->general()
                ->confirmation($confNo);
        } else {
            $f->general()
                ->noConfirmation();
        }

        $travellers = $this->http->FindSingleNode("//text()[normalize-space()='Passenger:']/following::text()[normalize-space()][1]");

        if (!empty($travellers)) {
            $f->general()
                ->traveller($travellers);
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'You canceled your Flight')]")->length > 0) {
            $f->general()
                ->status('cancelled')
                ->cancelled();
        }

        $accountNode = array_filter(array_unique($this->http->FindNodes("//text()[normalize-space()='Loyalty Program:']/ancestor::div[1]", null, "/\s*\-\s*(\d+)/")));
        $f->setAccountNumbers($accountNode, false);

        $cost = $this->http->FindSingleNode("//text()[normalize-space()='Subtotal']/following::text()[normalize-space()][1]/ancestor::tr[1]", null, true, "/([\d\,\.]+)/");
        $fee = $this->http->FindSingleNode("//text()[normalize-space()='Trip Fee']/following::text()[normalize-space()][1]/ancestor::tr[1]", null, true, "/([\d\,\.]+)/");
        $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes']/following::text()[normalize-space()][1]/ancestor::tr[1]", null, true, "/([\d\,\.]+)/");
        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total']/following::text()[normalize-space()][1]/ancestor::tr[1]", null, true, "/([\d\,\.]+)/");
        $currency = $this->http->FindSingleNode("//text()[normalize-space()='Total']/following::text()[normalize-space()][1]/ancestor::tr[1]", null, true, "/([A-Z]{3})/");

        if (!empty($total) && !empty($currency)) {
            $f->price()
                ->total(cost($total))
                ->currency($currency);
        }

        if (!empty($cost)) {
            $f->price()
                ->cost(cost($cost));
        }

        if (!empty($tax)) {
            $f->price()
                ->tax(cost($tax));
        }

        if (!empty($fee)) {
            $f->price()
                ->fee('Trip Fee', cost($fee));
        }

        $ticket = $this->http->FindSingleNode("//text()[normalize-space()='E-Ticket:' or normalize-space()='e-Ticket:']/following::text()[normalize-space()][1]", null, true, "/^([\d\/]+)$/");

        if (!empty($ticket)) {
            $f->issued()
                ->ticket($ticket, false);
        }

        $account = $this->http->FindSingleNode("//text()[normalize-space()='Known Traveler Number:']/following::text()[normalize-space()][1]");

        if (!empty($account)) {
            $f->ota()
                ->account($account, true);
        }

        $xpath = "//img[contains(@src, 'connection.png')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $i => $root) {
            $i = $i + 1;
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), 'Duration')][1]/preceding::text()[normalize-space()][1]", $root);

            $airline = $this->http->FindSingleNode("./preceding::text()[normalize-space()][2]/ancestor::tr[1]/preceding::tr[string-length()>2][1]", $root);

            if (preg_match("/^(?<operator>\D+)\s(?<name>[A-Z\d]{2})\s(?<number>\d{2,4})\s*\S\s(?<cabin>\D+)\s*$/u", $airline, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number'])
                    ->operator($m['operator']);

                $s->extra()
                    ->cabin($m['cabin']);
            }

            $departInfo = $this->http->FindNodes("./preceding::text()[normalize-space()][2]/ancestor::tr[1]/descendant::text()[normalize-space()]", $root);

            if (count($departInfo) == 3) {
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $departInfo[0]))
                    ->code($departInfo[1]);
            }

            $arrivInfo = $this->http->FindNodes("./following::text()[normalize-space()][2]/ancestor::tr[1]/descendant::text()[normalize-space()]", $root);

            if (count($arrivInfo) == 3) {
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $arrivInfo[0]))
                    ->code($arrivInfo[1]);
            }

            $seat = $this->http->FindSingleNode("//text()[normalize-space()='Flight Summary']/following::text()[normalize-space()='Seats:'][{$i}]/following::text()[normalize-space()][1]", null, true, "/^(\d{1,2}\D{1})$/");

            if (!empty($seat)) {
                $s->extra()->seat($seat);
            }
        }

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
        $in = [
            "#^\w+\,\s*(\d+\s*\w+)\,\s*([\d\:]+\s*A?M?)$#", //Sat, 20 Mar, 7:20 AM
        ];
        $out = [
            "$1 $year, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
