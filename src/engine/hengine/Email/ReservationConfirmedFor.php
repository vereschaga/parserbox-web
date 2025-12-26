<?php

namespace AwardWallet\Engine\hengine\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmedFor extends \TAccountChecker
{
    public $mailFiles = "";

    public $lang;
    public static $dictionary = [
        'en' => [
            "You're all set!" => "You're all set!",
            'Room details'    => 'Room details',
            //            'Confirmation' => 'Confirmation',
            //            'Confirmation' => 'Confirmation',
        ],
    ];

    private $detectFrom = "noreply@engine.com";
    private $detectSubject = [
        // en
        ' reservation confirmed for ',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]engine\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a/@href[{$this->contains(['.engine.com'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Engine, LLC'])}]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["You're all set!"]) && $this->http->XPath->query("//*[{$this->contains($dict["You're all set!"])}]")->length > 0
                && !empty($dict['Room details']) && $this->http->XPath->query("//*[{$this->contains($dict['Room details'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CONFIRMATION NUMBER:'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*[\dA-Z\-]{5,}\s*$/");

        if (!empty($conf)) {
            $h->general()
                ->confirmation($conf, null, true);
        }
        $confs = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Confirmation #'))}]/following::text()[normalize-space()][1]",
            null, "/^\s*[\dA-Z\-]{5,}\s*$/"));

        foreach ($confs as $conf) {
            $h->general()
                ->confirmation($conf);
        }
        $h->general()
            ->cancellation(implode(" ", $this->http->FindNodes("//text()[{$this->eq($this->t('Refund policy'))}]/ancestor::*[position() < 7][descendant::text()[normalize-space()][1][{$this->contains($this->t('Refund policy'))}]]//text()[normalize-space()][not({$this->eq($this->t('Refund policy'))})]")), true, true)
        ;

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Location'))}][following::text()[normalize-space()][3][{$this->eq($this->t('Get directions'))}]]" .
                    "/following::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Location'))}][following::text()[normalize-space()][3][{$this->eq($this->t('Get directions'))}]]" .
                "/following::text()[normalize-space()][2]"))
        ;
        // Booked
        $h->booked()
            ->checkIn(strtotime(implode(" ", $this->http->FindNodes("//text()[{$this->eq($this->t('CHECK-IN'))}]/following::text()[normalize-space()][1]/ancestor::tr[1][not({$this->contains($this->t('CHECK-IN'))})]//text()[normalize-space()]"))))
            ->checkOut(strtotime(implode(" ", $this->http->FindNodes("//text()[{$this->eq($this->t('CHECKOUT'))}]/following::text()[normalize-space()][1]/ancestor::tr[1][not({$this->contains($this->t('CHECK-IN'))})]//text()[normalize-space()]"))))
        ;

        // Rooms
        $rXpath = "//text()[{$this->eq($this->t('Room details'))}]/ancestor::*[{$this->starts($this->t('Room details'))}][last()]"
            . "//*[{$this->starts($this->t('Room '))} or ({$this->contains($this->t(' room'))})][following::tr[not(.//tr)][normalize-space()][1][count(.//img[1]) = 1]][following::tr[not(.//tr)][normalize-space()][2][count(.//img[1]) = 1]]";
        $roomNodes = $this->http->XPath->query($rXpath);
        $travellers = [];

        foreach ($roomNodes as $room) {
            if (preg_match("/^\s*(?:{$this->opt($this->t('Room '))}\s*\d+|\d+\s*{$this->opt($this->t(' room'))})\s*$/", $room->nodeValue)) {
                $r = $h->addRoom();
                $type = $this->http->FindSingleNode("following::tr[not(.//tr)][normalize-space()][1][count(.//img[1]) = 1]", $room);

                if (strlen($type) < 250) {
                    $r->setType($type);
                } else {
                    $r->setDescription($type);
                }
                $travellers[] = $this->http->FindSingleNode("following::tr[not(.//tr)][normalize-space()][2][count(.//img[1]) = 1]", $room);
                $conf = $this->http->FindSingleNode("following::tr[not(.//tr)][normalize-space()][3][*[{$this->eq($this->t('Confirmation #'))}][1]]/*[2]",
                    $room, true, "/^\s*[\dA-Z\-]{5,}\s*$/");

                if (!empty($conf)) {
                    $r->setConfirmation($conf);
                }
            }
        }

        $h->general()
            ->travellers(array_unique($travellers));

        // Total
        $total = $this->http->FindSingleNode("//td[{$this->eq($this->t('Total charges'))}]/following-sibling::*[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $h->price()
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency'])
            ;
        } else {
            $h->price()
                ->total(null);
        }
        $tax = $this->http->FindSingleNode("//td[{$this->eq($this->t('Taxes & Fees'))}]/following-sibling::*[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $tax, $m)
        ) {
            $h->price()
                ->tax(PriceHelper::parse($m['amount'], $m['currency']));
        }

        $cost = $this->http->FindSingleNode("//td[{$this->eq($this->t('Total charges'))}]/preceding::td[{$this->contains($this->t('room'))}][{$this->contains($this->t('night'))}]/following-sibling::*[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $cost, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $cost, $m)
        ) {
            $h->price()
                ->cost(PriceHelper::parse($m['amount'], $m['currency']));
        }

        $this->detectDeadLine($h);

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
           // Fully refundable before Mar 7, 2025 at 12pm (EST).
           preg_match("/^\s*Fully refundable before (?<date>.{6,20}) at (?<time>\d+(?::\d{2})?(?:[ap]m)?)\s*\([A-Z]{3,4}\)\s*\./i", $cancellationText, $m)
       ) {
            $m['time'] = preg_replace("/^\s*(\d{1,2})\s*([ap]m)?\s*$/", '$1:00 $2', $m['time']);
            $h->booked()
               ->deadline(strtotime($m['date'] . ', ' . $m['time']));
        }
//
//        if (
//            preg_match("/This reservation is non-refundable/i", $cancellationText, $m)
//        ) {
//            $h->booked()
//                ->nonRefundable();
//        }
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
