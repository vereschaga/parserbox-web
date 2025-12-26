<?php

namespace AwardWallet\Engine\winair\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TicketConfirmation2024 extends \TAccountChecker
{
    public $mailFiles = "winair/it-687419986.eml";
    public $detectSubjects = [
        'WINAIR Ticket Confirmation Mail',
        'You are Successfully Checked In',
    ];
    public $detectBody = [
        'your flight has been successfully ticketed',
        'You have successfully checked in for your flight',
        'changes have been made to your itinerary',
        'we look forward to welcome you at the gate for boarding shortly',
    ];

    public $lang = 'en';
    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'WINAIR') === false) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".fly-winair.sx/") or contains(@href,"//fly-winair.sx") or contains(@href,"www.fly-winair.sx")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"@fly-winair.com") or contains(.,"www.winair.sx")]')->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//text()[{$this->contains($dBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@fly-winair.com') !== false;
    }

    public function ParseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//tr[{$this->eq($this->t('Booking reference:'))}]/following-sibling::tr[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{5,7})\s*$/"));

        $allSeats = [];

        $trXpath = "//tr[*[1][{$this->eq($this->t('Name'))}]][*[2][{$this->eq($this->t('Ticket'))}]]/following-sibling::tr";

        foreach ($this->http->XPath->query($trXpath) as $trRoot) {
            $traveller = $this->http->FindSingleNode("*[1]", $trRoot, true, "/^\s*(?:[[:alpha:]]{1,4}\.(?:\/[[:alpha:]]{1,4}\.)?|Child )?\s*(.+?)\s*$/");

            if (!in_array($traveller, array_column($f->getTravellers(), 0))) {
                $f->general()
                    ->traveller($traveller, true);
            }
            $ticket = $this->http->FindSingleNode("*[2]", $trRoot, true, "/^\s*(\d{8,})\s*$/");

            if (!in_array($ticket, array_column($f->getTicketNumbers(), 0))) {
                $f->issued()
                    ->ticket($ticket, false, $traveller);
            }

            $seat = $this->http->FindSingleNode("*[3]", $trRoot, true, "/^\s*(\d{1,3}[A-Z])\s*$/");

            if (!empty($seat)) {
                $flight = $this->http->FindSingleNode("preceding-sibling::tr[last()]/preceding::tr[not(.//tr)][normalize-space()][1]/*[normalize-space()][1]", $trRoot, true, "/^\s*Flight ([A-Z\d]{3,})\s*$/");
                $allSeats[$flight][] = ['seat' => $seat, 'traveller' => $traveller];
            }
        }

        $xpath = "//tr[*[1][{$this->eq($this->t('Flights'))}]][*[2][{$this->eq($this->t('Departure'))}]]/following-sibling::tr[normalize-space()]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode('*[1]', $root);

            if (preg_match('/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d{1,5})\s*$/', $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);

                if (isset($allSeats[$m['name'] . $m['number']])) {
                    foreach ($allSeats[$m['name'] . $m['number']] as $v) {
                        $s->extra()
                            ->seat($v['seat'], true, true, $v['traveller']);
                    }
                }
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("*[2]/descendant::text()[normalize-space()][2]", $root, true, "/^\s*.+?\s*\(([A-Z]{3})\)\s*$/"))
                ->name($this->http->FindSingleNode("*[2]/descendant::text()[normalize-space()][2]", $root, true, "/^\s*(.+?)\s*\([A-Z]{3}\)\s*$/"))
                ->date(strtotime($this->http->FindSingleNode("*[2]/descendant::text()[normalize-space()][1]", $root)));

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("*[4]/descendant::text()[normalize-space()][2]", $root, true, "/^\s*.+?\s*\(([A-Z]{3})\)\s*$/"))
                ->name($this->http->FindSingleNode("*[4]/descendant::text()[normalize-space()][2]", $root, true, "/^\s*(.+?)\s*\([A-Z]{3}\)\s*$/"))
                ->date(strtotime($this->http->FindSingleNode("*[4]/descendant::text()[normalize-space()][1]", $root)));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
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
