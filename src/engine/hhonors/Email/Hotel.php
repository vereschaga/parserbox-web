<?php

namespace AwardWallet\Engine\hhonors\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Hotel extends \TAccountChecker
{
    public $mailFiles = "hhonors/it-32578027.eml, hhonors/it-32592380.eml";

    private $detects = [
        'Early check-in cannot be guaranteed. Contact the hotel to inquire about early check-in or late check-out',
    ];

    private $from = '/[@\.]res\.hilton\.com/i';

    private $provider = 'hilton';

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $cl = explode('\\', __CLASS__);
        $email->setType(end($cl) . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['from']) && preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (0 === $this->http->XPath->query("//node()[contains(normalize-space(.), '{$this->provider}')]")->length) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (0 < $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detect}')]")->length) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parseEmail(Email $email): void
    {
        $h = $email->add()->hotel();

        if ($conf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Reservation Confirmation #')][1]", null, true, '/\b(\d+)\b/')) {
            $h->general()
                ->confirmation($conf);
        }

        $hotelInfo = implode("\n", $this->http->FindNodes("//h3[normalize-space(.)='Hotel']/following-sibling::p[1]/descendant::text()[normalize-space(.)]"));

        if (preg_match('/(?<name>.+)\n(?<address>[\s\S]+)\nPhone\:[ ]*(?<phone>\+[\d\-]{10,})/', $hotelInfo, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address(preg_replace('/\s+/', ' ', $m['address']))
                ->phone($m['phone']);
        }

        if ($checkIn = $this->http->FindSingleNode("//td[normalize-space(.)='Arrival:' and not(.//td)]/following-sibling::td[1]")) {
            $h->booked()
                ->checkIn(strtotime($checkIn));
        }

        if ($checkOut = $this->http->FindSingleNode("//td[normalize-space(.)='Departure:' and not(.//td)]/following-sibling::td[1]")) {
            $h->booked()
                ->checkOut(strtotime($checkOut));
        }

        $node = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Hotel check-in time is')][1]");

        if (!empty($h->getCheckInDate()) && !empty($h->getCheckOutDate()) && preg_match('/check-in time is (\d{1,2}:\d{2} [ap]m) and check-out is at (\d{1,2}:\d{2} [ap]m)/', $node, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1], $h->getCheckInDate()))
                ->checkOut(strtotime($m[2], $h->getCheckOutDate()));
        }

        if ($name = $this->http->FindSingleNode("//td[normalize-space(.)='Guest name:' and not(.//td)]/following-sibling::td[1]")) {
            $h->general()
                ->traveller($name);
        }

        if ($rooms = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'room for') and not(.//td)]", null, true, '/(\d{1,2}) room for/')) {
            $h->booked()
                ->rooms($rooms);
        }

        if ($adults = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'adults') and not(.//td)]", null, true, '/(\d{1,2}) adults/')) {
            $h->booked()
                ->guests($adults);
        }

        $r = $h->addRoom();

        if ($type = $this->http->FindSingleNode("//tr[starts-with(normalize-space(.), 'DETAILS') and not(.//tr)]/following-sibling::tr[1]/td[1]")) {
            $r->setType($type);
        }

        if ($rate = $this->http->FindSingleNode("//tr[starts-with(normalize-space(.), 'DETAILS') and not(.//tr)]/following-sibling::tr[1]/td[2]")) {
            $r->setRate($rate);
        }
    }
}
