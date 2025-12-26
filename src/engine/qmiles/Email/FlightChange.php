<?php

namespace AwardWallet\Engine\qmiles\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightChange extends \TAccountChecker
{
    public $mailFiles = "qmiles/it-60004407.eml, qmiles/it-60084492.eml, qmiles/it-63928056.eml, qmiles/it-74369914.eml, qmiles/it-74471858.eml, qmiles/it-75581408.eml, qmiles/it-75822070.eml, qmiles/it-75854895.eml, qmiles/it-75860646.eml, qmiles/it-83467189.eml";

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        'en' => [
            'Booking reference'          => ["Booking reference", "Booking reference (PNR)"],
            'Departure Date'             => ['Departure Date', 'Departure'],
            "Flight Cancellation Notice" => ["Flight Cancellation Notice", "Flight cancellation"],
        ],
    ];

    private $subjects = [
        'en' => [
            'Important: Changes to your Qatar Airways flight timings',
            'QATAR AIRWAYS – Flight Cancellation Notice',
        ],
    ];
    private $detectBody = [
        'en' => [
            'there has been a change to the original flight timings',
            'Flight Cancellation notification',
            'Changes to your upcoming flight timing',
            'Flight cancellation notification',
            'Changes to your flight details',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $flight = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference'))}]/following::text()[1]", null, true, "/([A-Z\d]{6})/");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference (PNR)'))}]", null, true, "/ - ([A-Z\d]{6})\s*$/");
        }
        $descConfirmation = preg_replace("/ -(?: .+)?$/", '', $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference'))}]"));
        $flight->general()
            ->confirmation($confirmation, $descConfirmation);

        if (!empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('Changes to your Qatar Airways flight timings'))}]"))
            || preg_match("/{$this->opt($this->t("Changes to your Qatar Airways flight timings"))}/", $parser->getSubject())
        ) {
            $flight->general()
                ->status('changed');
        }

        if (!empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('the flight mentioned below has been cancelled due to operational reasons'))}]"))
            || preg_match("/{$this->opt($this->t("Flight Cancellation Notice"))}/", $parser->getSubject())
        ) {
            $flight->general()
                ->cancelled()
                ->status('cancelled');
        }

        // Type 1
        // Flight:QR 774    Departure Date          Arrival Date
        // GRU ›› DOH	07      February 2021 02:15     07 February 2021 21:50
        $xpath = "//tr[td[1][{$this->starts($this->t('Flight:'))}] and td[2][{$this->starts($this->t('Departure Date'))}]]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length > 0) {
            // if cancelled go to qmiles/FlightCancellation
            foreach ($segments as $root) {
                $s = $flight->addSegment();

                // Airline
                $s->airline()
                    ->name($this->http->FindSingleNode("./td[1]", $root, true,
                        "/: *([A-Z][A-Z\d]|[A-Z\d][A-Z]) *\d{1,5}$/"))
                    ->number($this->http->FindSingleNode("./td[1]", $root, true,
                        "/: *(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) *(\d{1,5})$/"));

                // Departure
                $s->departure()
                    ->code($this->http->FindSingleNode("./following-sibling::tr[1]/td[1]", $root, true,
                        "/^\s*([A-Z]{3})\s\W{1,2}\s[A-Z]{3}\s*$/u"))
                    ->date($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $root)));

                // Arrival
                $s->arrival()
                    ->code($this->http->FindSingleNode("./following-sibling::tr[1]/td[1]", $root, true,
                        "/^[A-Z]{3} \W{1,2} ([A-Z]{3})$/u"))
                    ->date($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[3]", $root)));
            }

            return $email;
        }

        // Type 2
        // New time: QR905 02AUG MELDOH 2215 0545 / QR17 03AUG DOHDUB 0655 1225 / QR18 28AUG DUBDOH 1335 2250 / QR904 28AUG DOHMEL 2355 2050
        $segmentsText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('New time:'))}]/following::text()[normalize-space()][1]");
        $this->logger->debug('$segmentsText = ' . print_r($segmentsText, true));

        if (!empty($segmentsText)) {
            $segments = preg_split('/\s*\/\s*/', $segmentsText);

            foreach ($segments as $sText) {
                $segment = $flight->addSegment();

                if (preg_match("/^(?<flightName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<flightNumber>\d+)\s+(?<dateDep>\d{1,2}[[:alpha:]]+)\s+(?<depCode>[A-Z]{3})(?<arrCode>[A-Z]{3})\s+(?<depTime>\d{4})\s+(?<arrTime>\d{4})$/u",
                    $sText, $m)) {
                    // QR836 12AUG DOHBKK 0045 1200

                    $segment->airline()
                        ->name($m['flightName'])
                        ->number($m['flightNumber']);

                    $segment->departure()
                        ->code($m['depCode'])
                        ->date($this->normalizeDate($m['dateDep'] . ', ' . $m['depTime']));

                    $segment->arrival()
                        ->code($m['arrCode'])
                        ->date($this->normalizeDate($m['dateDep'] . ', ' . $m['arrTime']));
                }
            }
        }

        // Type 3
        // Flight   From    To      Departure Date          Arrival Date
        // SK 1208  AAL     CPH     04 January 2021 10:20   04 January 2021 11:10
        if ($this->http->XPath->query("//text()[normalize-space()='New flight time:']")->length === 0) {
            $xpath = "//tr[td[2][{$this->starts($this->t('From'))}] and td[4][{$this->starts($this->t('Departure Date'))}]]/following-sibling::tr";
            $segments = $this->http->XPath->query($xpath);
        } else {
            $xpath = "//text()[normalize-space()='New flight time:']/following::tr[td[2][{$this->starts($this->t('From'))}] and td[4][{$this->starts($this->t('Departure Date'))}]]/following-sibling::tr";
            $segments = $this->http->XPath->query($xpath);
        }

        if ($segments->length > 0) {
            foreach ($segments as $root) {
                $s = $flight->addSegment();

                // Airline
                $s->airline()
                    ->name($this->http->FindSingleNode("./td[1]", $root, true, "/^([A-Z\d]{2})\s*\d{2,4}/"))
                    ->number($this->http->FindSingleNode("./td[1]", $root, true, "/^[A-Z\d]{2}\s*(\d{2,4})/"));

                // Departure
                $s->departure()
                    ->code($this->http->FindSingleNode("./td[2]", $root, true, "/^([A-Z\d]{3})$/"))
                    ->date($this->normalizeDate($this->http->FindSingleNode("./td[4]", $root)));

                // Arrival
                $s->arrival()
                    ->code($this->http->FindSingleNode("./td[3]", $root, true, "/^([A-Z\d]{3})$/"))
                    ->date($this->normalizeDate($this->http->FindSingleNode("./td[5]", $root)));
            }

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]qatarairways\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(., 'qatarairways')]")->count() === 0
            && $this->http->XPath->query("//a[contains(@href, 'qatarairways')]")->count() === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
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
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)(\D+)\,\s(\d{2})(\d{2})$#", //12AUG, 1500
        ];
        $out = [
            "$1 $2 $year, $3:$4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
