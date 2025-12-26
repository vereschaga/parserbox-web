<?php

namespace AwardWallet\Engine\velocity\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers tcase/It5045494, kulula/FlightConfirm (in favor of tcase/It5045494)

class ETicket2 extends \TAccountChecker
{
    public $mailFiles = "velocity/it-166630136.eml";
    public $subjects = [
        'Virgin Australia e-Ticket',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@virginaustralia.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'This email is being sent to you by Virgin Australia Airlines')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Travel Details - Itinerary'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('View Trip in TripCase'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]virginaustralia\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Booking Reference is')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Your Booking Reference is'))}\s*([A-Z\d]{6})/"))
            ->travellers(array_unique($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Seat(s):')]/preceding::text()[normalize-space()][1]")));

        $tickets = array_filter(array_unique($this->http->FindNodes("//text()[contains(normalize-space(), 'Your ticket(s) is/are:')]/ancestor::table[1]/descendant::a", null, "/^(\d+)$/")));

        if (count($tickets) > 0) {
            $f->setTicketNumbers($tickets, false);
        }

        $xpath = "//img[contains(@src, 'ROOT/VA/asDynamicEmail')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $status = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Departure:')][1]/preceding::text()[normalize-space()][2]", $root);

            if (!empty($status)) {
                $f->general()
                    ->status($status);
            }

            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Flight Number')][1]/following::text()[normalize-space()][1]", $root, true, "/^([A-Z\d]{2})/"))
                ->number($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Flight Number')][1]/following::text()[normalize-space()][1]", $root, true, "/^[A-Z\d]{2}\s*(\d{2,4})/"));

            $date = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Departure:')][1]/preceding::text()[normalize-space()][1]", $root);

            $depText = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Departure:')][1]/ancestor::tr[1]/descendant::td[2]", $root);

            if (preg_match("/^\s*([A-Z]{3})\s+.+\s+(\d+\:\d+\s*A?P?M)\s*(?:TERMINAL\s*(.+))?$/su", $depText, $m)) {
                $s->departure()
                    ->code($m[1])
                    ->date(strtotime($date . ', ' . $m[2]));

                if (isset($m[3])) {
                    $s->departure()
                        ->terminal($m[3]);
                }
            }

            $arrText = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Arrival:')][1]/ancestor::tr[1]/descendant::td[2]", $root);

            if (preg_match("/^\s*([A-Z]{3})\s+.+\s+(\d+\:\d+\s*A?P?M)\s*(?:TERMINAL\s*(.+))?$/su", $arrText, $m)) {
                $s->arrival()
                    ->code($m[1])
                    ->date(strtotime($date . ', ' . $m[2]));

                if (isset($m[3])) {
                    $s->arrival()
                        ->terminal($m[3]);
                }
            }

            $cabin = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Class:')]/following::text()[normalize-space()][1]", $root);

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $duration = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Duration:')]/following::text()[normalize-space()][1]", $root);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $distance = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Distance (in Miles):')]/following::text()[normalize-space()][1]", $root);

            if (!empty($distance)) {
                $s->extra()
                    ->miles($distance);
            }

            $meal = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Meal:')]/following::text()[normalize-space()][1]", $root);

            if (!empty($meal)) {
                $s->extra()
                    ->meal($meal);
            }

            $aircraft = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Aircraft:')]/following::text()[normalize-space()][1]", $root);

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
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
