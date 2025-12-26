<?php

namespace AwardWallet\Engine\statravel\Email;

use AwardWallet\Schema\Parser\Email\Email;

class FinalConfirmation extends \TAccountChecker
{
    public $mailFiles = "statravel/it-46839500.eml, statravel/it-47748110.eml";

    public $reFrom = ["@eventsairmail.com"];
    public $reBody = [
        'en' => ['Your booking has been booked with your Passport name'],
    ];
    public $reSubject = [
        'Final Confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Flights'               => 'Flights',
            'Flight Number'         => 'Flight Number',
            'Accommodation Booking' => 'Accommodation Booking',
            'Address'               => 'Address',
        ],
    ];
    private $keywordProv = 'smartraveller';
    private $pax;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'/production-traveledge-public/')] | //a[contains(@href,'.smartraveller.gov.au')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers["subject"]) > 0)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 2; // flight | hotel
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function parseEmail(Email $email)
    {
        $this->pax = trim($this->http->FindSingleNode("//text()[{$this->contains($this->t('Passport name provided at registration'))}]/following::text()[normalize-space()!=''][1]"),
            '.');

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Flights'))}]")->length > 0) {
            if (!$this->parseFlights($email)) {
                return false;
            }
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Accommodation Booking'))}]")->length > 0) {
            $this->parseHotels($email);
        }

        return true;
    }

    private function parseFlights(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('Departure Date'))}]/ancestor::tr[{$this->contains($this->t('Arrival Date'))}][1]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPath-flights] " . $xpath);

        if ($nodes->length === 0) {
            $email->add()->flight();
            $this->logger->debug('check format. can\'t find flights');

            return false;
        }

        $airs = [];

        foreach ($nodes as $root) {
            $rl = $this->nextTd($this->t('Booking Reference'), $root);
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $roots) {
            $otaConfNos = [];

            $r = $email->add()->flight();

            $r->general()
                ->traveller($this->pax)
                ->confirmation($rl);

            foreach ($roots as $root) {
                $s = $r->addSegment();

                $s->airline()
                    ->name($this->nextTd($this->t('Flight Number'), $root, "/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+$/"))
                    ->number($this->nextTd($this->t('Flight Number'), $root,
                        "/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/"));

                $ref = $this->nextTd($this->t('Airline Reference'), $root);

                if (!empty($ref) && !in_array($ref, $otaConfNos)) {
                    $r->ota()->confirmation($ref, $this->t('Airline Reference'));
                    $otaConfNos[] = $ref;
                }

                $s->departure()
                    ->noCode()
                    ->name($this->nextTd($this->t('Departure Port'), $root))
                    ->date(strtotime($this->nextTd($this->t('Departure Time'), $root),
                        strtotime($this->nextTd($this->t('Departure Date'), $root))));

                $s->arrival()
                    ->noCode()
                    ->name($this->nextTd($this->t('Arrival Port'), $root))
                    ->date(strtotime($this->nextTd($this->t('Arrival Time'), $root),
                        strtotime($this->nextTd($this->t('Arrival Date'), $root))));
            }
        }

        return true;
    }

    private function parseHotels(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('Address'))}]/ancestor::tr[{$this->contains($this->t('Check-In Date'))}][1]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPath-hotels] " . $xpath);

        if ($nodes->length === 0) {
            $email->add()->hotel();
            $this->logger->debug('check format. can\'t find hotels');

            return false;
        }

        foreach ($nodes as $root) {
            $r = $email->add()->hotel();

            if ($this->http->XPath->query("./descendant::tr[normalize-space()!=''][1]/following-sibling::tr[normalize-space()!='']",
                    $root)->length !== 5
            ) {
                $this->logger->alert('hotel has more information to parse. fix it');

                return false;
            }

            $r->general()
                ->traveller($this->pax)
                ->noConfirmation();

            $r->hotel()
                ->name($this->nextTd($this->t('Hotel'), $root))
                ->address($this->nextTd($this->t('Address'), $root))
                ->phone($this->nextTd($this->t('Phone'), $root));

            $r->booked()
                ->checkIn(strtotime($this->nextTd($this->t('Check-In Date'), $root)))
                ->checkOut(strtotime($this->nextTd($this->t('Check-Out Date'), $root)));

            $room = $r->addRoom();
            $room->setType($this->nextTd($this->t('Room'), $root));
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            // flights
            if (isset($words['Flights'], $words['Flight Number'])) {
                if ($this->http->XPath->query("//*[{$this->eq($words['Flights'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Flight Number'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
            // hotels
            if (isset($words['Accommodation Booking'], $words['Address'])) {
                if ($this->http->XPath->query("//*[{$this->eq($words['Accommodation Booking'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Address'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function nextTd($field, $root = null, $regExp = '/(.+)/')
    {
        return $this->http->FindSingleNode("./descendant::text()[{$this->eq($field)}]/ancestor::*[self::td or self::th][1]/following-sibling::*[self::td or self::th][normalize-space()!=''][1]",
            $root, false, $regExp);
    }
}
