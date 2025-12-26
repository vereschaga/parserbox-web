<?php

namespace AwardWallet\Engine\zipcar\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TripSummary extends \TAccountChecker
{
    public $mailFiles = "zipcar/it-60464325.eml, zipcar/it-61017229.eml, zipcar/it-212073954.eml, zipcar/it-210161130.eml";

    public $lang = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = '';

        // $year = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'©') and {$this->contains(['Zipcar, Inc', 'Zipcar UK Ltd'])}]", null, true, "/©\s*(\d{4})\s*(?:Zipcar, Inc|Zipcar UK Ltd)/iu");
        // $dateRelative = $year ? strtotime($year . '-01-01') : strtotime($parser->getHeader('date'));

        $r = $email->add()->rental();

        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        ];

        //Traveller
        $traveller = $this->http->FindSingleNode("//text()[{$this->contains(['this is a summary of your trip', 'you cancelled your booking', 'you canceled your booking', "you've cancelled your booking", "you've canceled your booking"])}]", null, true, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*,.{2,}/u");

        $r->general()
            ->traveller($traveller)
            ->noConfirmation();

        //Price
        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][starts-with(normalize-space(),'Cost')] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // £42.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $r->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        // Mar 14, 10:41 AM
        $patterns['dateTime'] = "/^(?<date>.{3,}?)\s*,\s*(?<time>{$patterns['time']})$/";

        //PickUp|DropOff Date
        $datePickUp = $dateDropOff = $timePickUp = $timeDropOff = null;
        $datePickUpVal = $dateDropOffVal = null;

        $pickUp = $this->http->FindSingleNode("//tr[not(.//tr) and starts-with(normalize-space(),'Pick up:')]", null, true, "/^Pick up:\s*(.{4,}{$patterns['time']})$/i")
            ?? $this->http->FindSingleNode("//tr[not(.//tr) and starts-with(normalize-space(),'Pick up:')]/following::text()[normalize-space()][1]", null, true, "/^[^:]{4,}{$patterns['time']}$/i")
        ;

        if (preg_match($patterns['dateTime'], $pickUp, $m)) {
            $datePickUpVal = $this->normalizeDate($m['date']);
            $timePickUp = $m['time'];
        }

        $dropOff = $this->http->FindSingleNode("//tr[not(.//tr) and starts-with(normalize-space(),'Drop off:')]", null, true, "/^Drop off:\s*(.{4,}{$patterns['time']})$/i")
            ?? $this->http->FindSingleNode("//tr[not(.//tr) and starts-with(normalize-space(),'Drop off:')]/following::text()[normalize-space()][1]", null, true, "/^[^:]{4,}{$patterns['time']}$/i")
        ;

        if (preg_match($patterns['dateTime'], $dropOff, $m)) {
            $dateDropOffVal = $this->normalizeDate($m['date']);
            $timeDropOff = $m['time'];
        }

        // it-61017229.eml
        $tripDates = $this->http->FindSingleNode("//tr[not(.//tr) and starts-with(normalize-space(),'Actual:')]", null, true, "/^Actual:\s*(.{4,}{$patterns['time']})(?:\s+[A-Z]{3,})?$/i")
            ?? $this->http->FindSingleNode("//tr[not(.//tr) and starts-with(normalize-space(),'Booking:')]", null, true, "/^Booking:\s*(.{4,}{$patterns['time']})(?:\s+[A-Z]{3,})?$/i")
        ;

        // Jun 21, 10:37 AM PDT - Jun 22, 9:30 AM
        // Oct 21, 12:17 - 3:28 AM
        if (preg_match("/^(.{7,}?)(?:\s+[A-Z]{3,})?\s+-\s+(\b.+)$/", $tripDates, $matches)) {
            if (preg_match($patterns['dateTime'], $matches[1], $m)) {
                $datePickUpVal = $this->normalizeDate($m['date']);
                $timePickUp = $m['time'];
            }

            if (preg_match($patterns['dateTime'], $matches[2], $m)) {
                $dateDropOffVal = $this->normalizeDate($m['date']);
                $timeDropOff = $m['time'];
            } elseif (preg_match("/^{$patterns['time']}$/", $matches[2])) {
                $dateDropOffVal = $datePickUpVal;
                $timeDropOff = $matches[2];
            }

            if ($timePickUp !== null && $timeDropOff !== null) {
                $pattern = '/\d([ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)$/'; // 3:28 AM

                if (!preg_match($pattern, $timePickUp) && preg_match($pattern, $timeDropOff, $m)) {
                    $timePickUp .= $m[1];
                } elseif (preg_match($pattern, $timePickUp, $m) && !preg_match($pattern, $timeDropOff)) {
                    $timeDropOff .= $m[1];
                }
            }
        }

        if ($datePickUpVal && !preg_match('/\b\d{4}\s*$/', $datePickUpVal)) {
            $datePickUp = EmailDateHelper::calculateDateRelative($datePickUpVal, $this, $parser, '%D% %Y%');
        } elseif ($datePickUpVal) {
            $datePickUp = strtotime($datePickUpVal);
        }

        if ($dateDropOffVal && !preg_match('/\b\d{4}\s*$/', $dateDropOffVal)) {
            $dateDropOff = EmailDateHelper::calculateDateRelative($dateDropOffVal, $this, $parser, '%D% %Y%');
        } elseif ($dateDropOffVal) {
            $dateDropOff = strtotime($dateDropOffVal);
        }

        $r->pickup()->date(strtotime($timePickUp, $datePickUp));
        $r->dropoff()->date(strtotime($timeDropOff, $dateDropOff));

        //PickUp|DropOff Location
        $locationPickUp = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'You drove:')]/ancestor::tr[1]/following::text()[contains(normalize-space(), ',')][1]");

        if (!empty($locationPickUp)) {
            $r->pickup()
                ->location($locationPickUp);
        }

        $locationDropOff = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'You drove:')]/ancestor::tr[1]/following::text()[contains(normalize-space(), ',')][2]");

        if (!empty($locationDropOff)) {
            $r->dropoff()
                ->location($locationDropOff);
        }

        // it-61017229.eml
        if (empty($locationPickUp) && empty($locationDropOff)) {
            $locTexts = $this->http->FindNodes("//text()[{$this->starts(['Number Plate', 'Plate'])}]/ancestor::tr[1]/following-sibling::tr[normalize-space()]");

            if (count($locTexts) !== 0) {
                $location = implode(', ', $locTexts);
                $r->pickup()->location($location);
                $r->dropoff()->same();
            }
        }

        //Car
        $r->car()
            ->model($this->http->FindSingleNode("//text()[{$this->starts(['Number Plate', 'Plate'])}]/preceding::text()[normalize-space()][1]"))
            ->image($this->http->FindSingleNode("//text()[{$this->starts(['Number Plate', 'Plate'])}]/preceding::img[1]/@src"));

        if ($this->isCancelled()) {
            $r->general()->cancelled();
        }

        if (!empty($r->getCancelled()) && !empty($r->getDropOffDateTime()) && !empty($r->getCarModel()) && !empty($r->getDropOffLocation())) {
            // it-210161130.eml
            $this->logger->debug('This email is junk because empty confirmation/cancellation numbers!');
            $email->removeItinerary($r);
            $email->setIsJunk(true);
            $type = 'junk';
        }

        $email->setType('TripSummary' . ucfirst($type));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".zipcar.com/") or contains(@href,"link.zipcar.com")]')->length === 0) {
            return false;
        }

        return $this->http->XPath->query("//tr[not(.//tr) and normalize-space()='Your trip']")->length > 0 || $this->isCancelled();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@]zipcar\.com/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Trip Summary') !== false;
    }

    private function isCancelled(): bool
    {
        return $this->http->XPath->query("//tr[not(.//tr) and (normalize-space()='Canceled trip' or normalize-space()='Cancelled trip')]")->length > 0;
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^([[:alpha:]]+)\s+(\d{1,2})$/u', $text, $m)) {
            // Oct 13
            $month = $m[1];
            $day = $m[2];
            $year = '';
        } elseif (preg_match('/^(\d{1,2})\s+([[:alpha:]]+)$/u', $text, $m)) {
            // 13 Oct
            $day = $m[1];
            $month = $m[2];
            $year = '';
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }
}
