<?php

namespace AwardWallet\Engine\gcampaigns\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Hotel extends \TAccountChecker
{
    public $mailFiles = "gcampaigns/it-16999475.eml, gcampaigns/it-47576898.eml, gcampaigns/it-55705749.eml, gcampaigns/it-57926559.eml";

    private $detects = [
        'Thank you for making your hotel reservation',
        'Your hotel room is confirmed',
        'Your Hotel Reservation',
        'We look forward to welcoming you to the',
        'Thank you for choosing the',
        'Your Passkey Acknowledgement',
        'Thank you for your recent hotel reservation at',
        'Your reservation with Mohegan Sun has been cancelled',
    ];

    private $from = '/[@.]pkghlrss[.]com/';
    private $subject = [
        'Your Hotel Reservation',
        'Cancelled Reservation Confirmation',
        'Reservation Confirmation',
        'Mohegan Sun Reservation Cancellation',
    ];

    private $prov = '.passkey.com';

    private $lang = 'en';

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->parseEmail($email);
        $ns = explode('\\', __CLASS__);
        $class = end($ns);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && preg_match($this->from, $headers['from']) > 0) {
            if (isset($headers['subject'])) {
                foreach ($this->subject as $subject) {
                    if (stripos($headers['subject'], $subject) != false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email): Email
    {
        $h = $email->add()->hotel();

        $confTitles = ["HOTEL RESERVATION ACKNOWLEDGEMENT", "HOTEL RESERVATION ACkNOWLEDGEMENT", "HOTEL RESERVATION MODIFICATION ACKNOWLEDGEMENT", "CONFIRMATION NUMBER:", 'Acknowledgement NumBER IS', 'Confirmation #:', 'ONLINE RESERVATION ACKNOWLEDGEMENT NUMBER is'];

        $confNoStr = $this->http->FindSingleNode("//*[self::div or self::tr or self::pre][" . $this->contains($confTitles) . " and not(.//*[self::div or self::tr or self::pre])][1]");

        if (empty($confNoStr)) {
            $confNoStr = $this->http->FindSingleNode("//text()[" . $this->contains($confTitles) . " and not(.//*[self::div or self::tr or self::pre])][1]");
        }


        if (preg_match('/[#:]+\s*([A-Z\d]{5,})\b/', $confNoStr, $m) || preg_match('/NUMBER is\s+([A-Z\d]{5,})\b/i', $confNoStr, $m)) {
            $h->general()->confirmation($m[1]);
            $addedConfInfo = true;
        } elseif ($confNoStr === 'HOTEL RESERVATION ACKNOWLEDGEMENT') {
            $h->general()->noConfirmation();
            $addedConfInfo = true;
        }
        // Cancelled Reservation Confirmation
        $confTitles = ["HOTEL RESERVATION CANCELLATION ACKNOWLEDGEMENT", 'YOUR RESERVATION HAS BEEN CANCELLED'];

        if ($confNoStr = $this->http->FindSingleNode("//*[self::div or self::tr or self::pre][" . $this->contains($confTitles) . " and not(.//*[self::div or self::tr or self::pre])][1]")) {
            $h->general()
                ->status('CANCELLATION')
                ->cancelled();

            if (preg_match('/[#:]+\s+([A-Z\d]{5,})$/', $confNoStr, $m)) {
                $h->general()->cancellation($m[1]);
            }

            if (!isset($addedConfInfo)) {
                $h->general()->noConfirmation();
            }
        }

        $hotelName = $this->http->FindSingleNode("//*[self::div or self::tr][contains(normalize-space(),'HOTEL INFORMATION') and not(.//*[self::div or self::tr])]/following-sibling::*[self::div or self::tr][1]");

        if (!$hotelName) {
            $hotelName = $this->http->FindSingleNode("//p[normalize-space()='HOTEL INFORMATION']/following-sibling::p[normalize-space()][1]");
        }

        if (!$hotelName) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->starts(['Thank you for choosing the', 'We look forward to welcoming you to the'])}]", null, true,
                "/(?:Thank you for choosing the|We look forward to welcoming you to the)\s+([^\.!]+?)\s*(?:\.|!)/");
        }

        if (!$hotelName) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->starts(['Thank you for your recent hotel reservation at', 'Your reservation with '])}]", null, true,
                "/reservation \w+\s+(.+?)[.]?\s*(?:We|has been)/");
        }

        $addressRows = $this->http->FindNodes("//*[self::div or self::tr][contains(normalize-space(),'HOTEL INFORMATION') and not(.//*[self::div or self::tr])]/following-sibling::*[self::div or self::tr][position()>1]");

        if (count($addressRows) === 0) {
            $addressRows = $this->http->FindNodes("//p[normalize-space()='HOTEL INFORMATION']/following-sibling::*[normalize-space()][2]/descendant::tr[not(.//tr) and normalize-space()]");
        }

        if (count($addressRows) > 2) {
            if (preg_match("/^[+(\d][-. \d)(]{5,}[\d)]$/", end($addressRows))) {
                $phone = array_pop($addressRows);
            }
        }
        $address = implode(' ', $addressRows);

        $h->hotel()
            ->name($hotelName);

        if (!empty($address)) {
            $h->hotel()->address($address);
        } elseif (!empty($hotelName)) {
            $h->hotel()->noAddress();
        }

        if (isset($phone)) {
            $h->hotel()->phone($phone);
        }

        $room = $this->getNode('Room Name:');

        if (empty($room)) {
            $room = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Room Name:')]/following::text()[normalize-space(.)][1][not(contains(.,':'))]");
        }

        if (empty($room)) {
            $room = $this->http->FindSingleNode("//text()[{$this->eq(['Room Type Request:', 'Room Type:'])}]/following::text()[normalize-space()][1]");
        }
        if (empty($room)) {
            $room = $this->http->FindSingleNode("//text()[{$this->starts(['Room Type Request:', 'Room Type:'])}]", null, true, '/[:]\s*(.+)/');
        }
        $s = $h->addRoom();
        $s->setType($room);

        $inDate = $this->getNode('Check-in:');

        if (empty($inDate)) {
            $inDate = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Check-in:')]/following::text()[normalize-space(.)][1][not(contains(.,':'))]");
        }

        if (empty($inDate)) {
            $inDate = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Check-in Date:')]", null, true, '/[:]\s*(.+)/');
        }
        $h->booked()
            ->checkIn(strtotime($inDate));

        $outDate = $this->getNode('Check-out:');

        if (empty($outDate)) {
            $outDate = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Check-out:')]/following::text()[normalize-space(.)][1][not(contains(.,':'))]");
        }

        if (empty($outDate)) {
            $outDate = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Check-out Date:')]", null, true, '/[:]\s*(.+)/');
        }
        $h->booked()
            ->checkOut(strtotime($outDate));

        $traveller = $this->getNode(['Share-withs:', 'Share-with:', 'Roommates:']);

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[" . $this->contains(['Share-withs:', 'Roommates:', 'Your Mohegan Sun Room Reservation:', 'The following reservation has been cancelled:']) . "]/following::text()[normalize-space(.)][1][not(contains(.,':'))]");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[" . $this->eq(["GUEST INFORMATION"]) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z][a-z]{0,30}(?: [A-Z][a-z]{0,30})*)\s*$#");
        }
        $h->general()
            ->traveller($traveller);

        $rates = array_unique(array_filter(array_map(function ($el) { return (int) $el; }, $this->http->FindNodes("//node()[contains(normalize-space(.), 'Rate') and not(.//tr)]/following-sibling::text()[normalize-space(.)]", null, '/(?:confirmed)\s+([\d\.]+)/i'))));
        $guests = array_unique(array_filter(array_map(function ($el) { return (int) $el; }, $this->http->FindNodes("//node()[contains(normalize-space(.), 'Rate') and not(.//tr)]/following-sibling::text()[normalize-space(.)]", null, '/(\d{1,4})\s*(?:confirmed)\s+[\d\.]+/i'))));

        if (1 === count($rates)) {
            $s->setRate(array_shift($rates));
            $h->booked()
                ->guests(array_shift($guests));
        } elseif (1 < count($rates)) {
            $min = min($rates);
            $max = max($rates);
            $rate = $min . '-' . $max;
            $s->setRate($rate);
            $guests = array_sum($guests);
            $h->booked()
                ->guests($guests);
        }

        if (false !== stripos($this->http->Response['body'], 'Confirmed') && (null === $h->getCancelled() || $h->getCancelled() !== true)) {
            $h->general()
                ->status('Confirmed');
        }

        $cancellation = preg_replace("/^Please Note:\s*/", '', trim(implode("\n", $this->http->FindNodes("//*[self::div or self::tr][contains(., 'CANCELLATION POLICY') and not(.//*[self::div or self::tr])]/following::*[self::div or self::tr][1]/*[not(contains(.,'Copyright'))]")), '* '));

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        if (!empty($node = $h->getCancellation())) {
            $this->detectDeadLine($h, $node);
        }

        return $email;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        if (preg_match("#You must cancel your reservation at least [-[:alpha:] ]+? \((\d{1,3})\) (hours?) prior to your scheduled arrival date to receive#i", $cancellationText, $m)
            || preg_match("#All hotel cancellations must be made at least [-[:alpha:] ]+? \((\d{1,3})\)(?: business)? (days?) prior to the scheduled arrival date#i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1] . ' ' . $m[2]);
        } elseif (preg_match("#Deposits are refundable if reservations are cancelled by (\w+ \d+, \d{4})\s*(?:\.|\*|$)#i",
            $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1]));
        } elseif (preg_match("#Reservations must be cancelled by (\d{1,2}:\d{1,2}[APMapm]{0,2}) [A-Z]{3,4} (\d{1,2} hours) prior to the day of arrival to avoid cancellation fee equal to one night of room & tax\.#i",
            $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[2], $m[1]);
        } elseif (preg_match("#Cancellations made within (?<priorH>\d+) hours prior to (?<time>.+?) day of arrival will forfeit one night\'s room and tax.#i",
            $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['priorH'] . ' hours', $m['time']);
        } elseif (preg_match('/Deposit fully refundable provided reservation is cancelled at least (\d{1,2} hours) prior to arrival/', $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1]);
        }
    }

    private function getNode($s): ?string
    {
        return $this->http->FindSingleNode("//*[(name() = 'th' or name() = 'td') and not(.//td) and not(.//th) and " . $this->contains($s) . "]/following-sibling::td[normalize-space(.)][1]");
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
