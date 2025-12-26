<?php

namespace AwardWallet\Engine\woodfield\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "woodfield/it-11216457.eml, woodfield/it-9015162.eml";

    private $detects = [
        'All La Quinta Returns program terms and conditions apply',
    ];

    private $subject = "#La\s+Quinta\s+Hotel\s+Reservation#i";

    private $from = "#reservations@laquinta\.com#i";

    private $provider = "laquinta";

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $cl = explode('\\', __CLASS__);
        $email->setType(end($cl) . $this->lang);
        $text = $parser->getHTMLBody();
        $this->parseEmail($email, $text);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['from']) && preg_match($this->from, $headers['from']) > 0
            && !empty($headers['subject']) && preg_match($this->subject, $headers['subject']) > 0;
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

    private function parseEmail(Email $email, $text = null): void
    {
        $h = $email->add()->hotel();
        $confNo = [];

        if (preg_match('#Your\s+Reservation\s+Confirmation\s+No:\s+((?:[\w\-]+\s+)+?)La\s+Quinta#', $text, $m)) {
            $nums = explode("\n", $m[1]);

            foreach ($nums as &$n) {
                $n = nice($n);
            }
            $nums = array_values(array_filter($nums));

            if (count($nums) > 1) {
                $confNo = $nums;
            }
        }

        if (!isset($res['ConfirmationNumber'])) {
            if ($this->re("#cancellation\s+notice\s+has\s+been#i", $text)) {
                $confNo[] = CONFNO_UNKNOWN;
            }
        }

        if (!isset($res['ConfirmationNumber'])) {
            $confNo[] = orval(
                $this->http->FindSingleNode("//text()[normalize-space(.)='Confirmation #']/following::text()[normalize-space(.)!=''][1]"),
                $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Confirmation #')]", null, true, '/Confirmation #\s+(\d+)/')
            );
        }

        if (!empty($confNo)) {
            foreach ($confNo as $conf) {
                $h->general()
                    ->confirmation($conf);
            }
        }

        $res = [];
        $node = implode("\n", $this->http->FindNodes("//td[starts-with(normalize-space(.), 'Confirmation #') and not(.//td)]/descendant::node()[normalize-space(.)]"));
        $regex = '#';
        $regex .= '(?<HotelName>La\s+Quinta\s+Inn\s+(?:&\s+Suites\s+)?[^\n]+)\n';
        $regex .= '\s*(?<Phone>[\(\) \d\-]{10,})\n';
        $regex .= '\s*(?<Address>.+\s+.+)\s+';
        $regex .= '#i';

        if (preg_match($regex, $node, $m)) {
            $m['Address'] = nice($m['Address'], ', ');
            copyArrayValues($res, $m, ['HotelName', 'Address', 'Phone']);
        } else {
            $regex = '#';
            // assuption is no numbers in name
            // (?P<HotelName>La\s+Quinta\s+Inn\s+(?:&\s+Suites\s+)?[^\n]+)\n\s*(?P<Address>.+\s+.+)\s+(?P<Phone>[\(\) \d\-]{10,})
            $regex .= '(?<HotelName>La\s+Quinta\s+Inn\s+(?:&\s+Suites\s+)?[^\n]+)\s+';
            $regex .= '(?<Address>.*?)\s+';
            $regex .= '(?<Phone>\(\d{1,3}\) [\d\-]{7,})';
            $regex .= '#is';

            if (preg_match($regex, $node, $m)) {
                $m['Address'] = nice($m['Address'], ', ');
                $res = copyArrayValues($res, $m, ['HotelName', 'Address', 'Phone']);
            }
        }

        $h->hotel()
            ->name($res['HotelName'])
            ->address($res['Address'])
            ->phone($res['Phone']);

        $res = [];

        foreach (['CheckIn' => 'Check-In', 'CheckOut' => 'Check-Out'] as $key => $value) {
            $date = $this->getNode($value . ' Date:');
            $time = $this->getNode($value . ' Time:');
            $res[$key . 'Date'] = strtotime($date . ', ' . $time);
        }

        $h->booked()
            ->checkIn($res['CheckInDate'])
            ->checkOut($res['CheckOutDate']);

        $name = $this->re('#Your\s+Name:\s+(.*)#', $text);

        if (empty($name)) {
            $name = $this->re('#\s*([^\n]+)\n\s*Check-in\s+date:#i', $text);
        }

        if (!empty($name)) {
            $h->addTraveller($name);
        }

        if (($rooms = (int) $this->getNode('# of rooms:')) || ($rooms = (int) $this->getNode('Number of rooms:'))) {
            $h->booked()
                ->rooms($rooms);
        }

        if ($cancel = orval(
            nice($this->re('#IF\s*YOU\s*HAVE\s*TO\s*CANCEL\s*(.*?)\s*Cancel\s*this\s*reservation#is', $text)),
            nice($this->re("#Need to cancel\?\s+We're sorry to hear that\.\s+(.+?)\n\n#is", $text)),
            $this->http->FindSingleNode("//span[contains(., 'Change of Plans')]/following-sibling::text()[1]")
        )) {
            $h->general()
                ->cancellation($cancel);
        }

        $room = $h->addRoom();

        if ($rate = str_replace("*", "", str_replace("]", "", str_replace(',(', ' (', nice($this->re('#Nightly\s+Rate:\s+([\d\.]+ [A-Z]{3}).*Does\s+not\s+include.*?\n#s', $text), ', '))))) {
            $room->setRate($rate);
        }

        if ($type = $this->getNode('Room type:')) {
            $room->setType($type);
        }

        $h->booked()
            ->guests($this->getNode('# of guests:'), true, true);

        $subj = $this->re('#(?:Estimated\s+Total\s+w\/Tax:|Est(?:\.|imated)\s+total\s+\(with\s+tax\))\s+(.*)#', $text);

        if (!empty($subj)) {
            $h->price()
                ->total($this->amount($subj))
                ->currency($this->currency($subj));
        }

        if (!empty($this->re("#cancellation\s+notice\s+has\s+been#i", $text))) {
            $h->setCancelled(true);
        }

        if (!empty($h->getCancellation()) && preg_match('/Update or Cancel by (\d{1,2}[AP]M) (\d{2,4}-\d{1,2}-\d{2}) local property time to avoid penalties/i', $h->getCancellation(), $m)) {
            $h->parseDeadline($m[2] . ', ' . $m[1]);
        } elseif (!empty($h->getCancellation()) && preg_match('/Update or Cancel by (\d{1,2}[AP]M) (\d{1,2})([A-Z]+)(\d{2,4}) local property time to avoid penalties/i', $h->getCancellation(), $m)) {
            $h->parseDeadline($m[2] . ' ' . $m[3] . ' ' . $m[4] . ', ' . $m[1]);
        }
    }

    private function getNode(string $s, ?string $re = null): ?string
    {
        $upp1 = strtoupper($s);
        $low1 = strtolower($s);

        return $this->http->FindSingleNode("((//text()[contains(translate(normalize-space(.),'{$upp1}','{$low1}'),'{$low1}') and not(.//td)])[1]/following::text()[normalize-space(.)][1])[1]", null, true, $re);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        $amount = str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", str_replace(" ", "", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (!preg_match('/^\d[,.\'\d]*$/', $amount)) {
            $amount = null;
        }

        return (float) $amount;
    }

    private function currency($s)
    {
        if ($code = $this->re("#\(([A-Z]{3})\)#", $s)) {
            return $code;
        }
        $sym = [
            '€'   => 'EUR',
            'R$'  => 'BRL',
            '$'   => 'USD',
            '£'   => 'GBP',
            '₽'   => 'RUB',
            'S/.' => 'PEN',
            'Ft'  => 'HUF',
            'Kč'  => 'CZK',
            '₺'   => 'TRY',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        if (mb_strpos($s, '￥') !== false) {
            if ($this->lang = 'zh') {
                return 'CNY';
            }

            if ($this->lang = 'ja') {
                return 'JPY';
            }
        }

        return null;
    }
}
