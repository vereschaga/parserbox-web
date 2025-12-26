<?php

namespace AwardWallet\Engine\omnihotels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "omnihotels/it-6075836.eml, omnihotels/it-6141357.eml, omnihotels/it-68137530.eml";
    public static $dict = [
        'en' => [
            'Confirmation #'          => ['Confirmation #', 'CONFIRMATION #', 'confirmation number'],
            'Cancellation #'          => ['Cancellation #', 'CANCELLATION #'],
            'Status'                  => ['Status', 'STATUS'],
            'cancelledStatus'         => ['Cancelled', 'Canceled'],
            'Directions to the Hotel' => ['Directions to the Hotel', 'Directions to Hotel'],
            'taxes'                   => ['Taxes:', 'Taxes :', 'Taxes (room only):', 'Taxes (room only) :'],
            'feeNames'                => ['Resort Charge'],
        ],
    ];

    private $detectBody = [
        'en' => ['Directions to the Hotel', 'We are pleased to inform you that the following reservation'],
    ];
    private $detectSubject = [
        'en' => '/Omni.+?Reservation (?:Confirmation|Cancellation|Cancelation)/i',
    ];

    private $anchor = 'Omni Hotel';

    private $lang = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];
        $this->assignLang($body);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseHtml($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".omnihotels.com") or contains(@href,"www.omnihotels.com")]')->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->assignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->detectSubject)) {
            foreach ($this->detectSubject as $dSubject) {
                if (preg_match($dSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@omnihotels.com') !== false || stripos($from, '@omnihotels-cte.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseHtml(Email $email): void
    {
        $h = $email->add()->hotel();

        // General
        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation #'))}]/ancestor::td[1]");

        if (preg_match("/^({$this->opt($this->t('Confirmation #'))})[:\s]*(\d{5,})$/", $confirmation, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        if (!$confirmation) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('with confirmation number'))}]/ancestor::tr[1]");

            if (preg_match("/({$this->opt($this->t('Confirmation #'))})\s+(\d{5,})(?:[,.;:!?]|\D{2}|$)/", $confirmation, $m)) {
                $h->general()->confirmation($m[2], $m[1]);
            }
        }

        $cancellationNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation #'))}]/ancestor::td[1]", null, true, "/^{$this->opt($this->t('Cancellation #'))}[:\s]*(\d{5,})$/");
        $h->general()->cancellationNumber($cancellationNo, false, true);

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Status'))}]/ancestor::td[1]", null, true, "/^{$this->opt($this->t('Status'))}[:\s]*(.+)$/");
        $h->general()->status($status);

        if (preg_match("/^{$this->opt($this->t('cancelledStatus'))}$/i", $status)) {
            $h->general()->cancelled();
        }

        $travellers = array_filter($this->http->FindNodes("(//text()[{$this->eq($this->t('Guest'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]/p[1])[1]"));

        if (empty($travellers)) {
            $travellers = array_filter($this->http->FindNodes("(//text()[{$this->eq($this->t('Guest'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]//text()[normalize-space(.)])[1]"));
        }

        if (count($travellers) > 0) {
            $h->general()->travellers($travellers);
        }

        // Hotel
        $hotelName = $this->http->FindSingleNode("//a[{$this->contains($this->t('Directions to the Hotel'))}]/ancestor::td[1]/div[1]");

        if (empty($hotelName)) {
            // version 2021
            $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation #'))}]/preceding::div[ descendant::text()[normalize-space()][2] and descendant::img[contains(@src,'/07d98c88-8a43-4c38-9e13-458c05e39d0d.')][2] ]/preceding-sibling::div[normalize-space()][1]");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//div[{$this->contains($this->t('Phone:'))} and not(descendant::div)]/preceding-sibling::div[2]");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//a[{$this->contains($this->t('Directions to the Hotel'))}]/ancestor::td[1]/p[1]");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Phone')]/preceding::img[2]/preceding::text()[normalize-space()][1][contains(normalize-space(), 'Hotel')]");
        }

        $address = implode(' ', $this->http->FindNodes("//a[{$this->contains($this->t('Directions to the Hotel'))}]/ancestor::*[count(descendant::text()[normalize-space()])>1][1]/descendant::text()[normalize-space() and not({$this->contains($this->t('Directions to the Hotel'))})]"));

        if (empty($address)) {
            // version 2021
            $address = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation #'))}]/preceding::div[ descendant::text()[normalize-space()][2] and descendant::img[contains(@src,'/07d98c88-8a43-4c38-9e13-458c05e39d0d.')][2] ]/descendant::text()[normalize-space()][1]");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Phone')]/preceding::img[1]/preceding::text()[normalize-space()][1]");
            $this->logger->warning($address);
        }

        if (empty($address)) {
            $address = implode(' ', $this->http->FindNodes("//a[{$this->contains($this->t('Directions to the Hotel'))}]/ancestor::td[1]/p[normalize-space()][2]//text()[not({$this->contains($this->t('Directions to the Hotel'))})]"));
        }

        $phone = $this->http->FindSingleNode("(//a[{$this->contains($this->t('Directions to the Hotel'))}]/ancestor::td[1]/div[3]//text()[normalize-space(.)!=''][not({$this->eq($this->t('Phone:'))})])[1]", null, true, "#[\d-\(\)\s\+]+#");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//div[{$this->contains($this->t('Phone:'))} and not(descendant::div)]", null, true, '/:\s+([\d\-]+)/');
        }

        if (empty($hotelName) && empty($address) && empty($phone)) {
            $info = implode("\n", $this->http->FindNodes("//a[{$this->contains($this->t('Directions to the Hotel'))}]/ancestor::td[1]//text()"));

            if (preg_match("#\s*(.+)\s+([\s\S]+)\n.*{$this->opt($this->t('Directions to the Hotel'))}.*\s*{$this->opt($this->t('Phone:'))}\s*(.+)#", $info, $m)) {
                $hotelName = $m[1];
                $address = $m[2];
                $phone = $m[3];
            }
        }

        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone)
        ;

        // Booked
        $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Occupants'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]");
        $h->booked()
            ->checkIn(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check In:'))}]/ancestor::td[1]", null, true, "#{$this->opt($this->t('Check In:'))}\s*(.+)$#"))))
            ->checkOut(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check Out:'))}]/ancestor::td[1]", null, true, "#{$this->opt($this->t('Check Out:'))}\s*(.+)$#"))))
        ;

        $adult = $this->re("#(\d+)\s+{$this->opt($this->t('Adults'))},\s+\d+\s+{$this->opt($this->t('Children'))}#i", $guests);

        if (!empty($adult)) {
            $h->booked()
                ->guests($adult);
        }

        $kids = $this->re("#\d+\s+{$this->opt($this->t('Adults'))},\s+(\d+)\s+{$this->opt($this->t('Children'))}#i", $guests);

        if ($kids !== null) {
            $h->booked()
                ->kids($kids);
        }

        $rooms = $this->re("#\d+\s+{$this->opt($this->t('night'))}.*?(?:\(s\))?,\s+(\d+)\s+{$this->opt($this->t('room'))}\(s\)#i", $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Stay'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]"));

        if (!empty($rooms)) {
            $h->booked()
                ->rooms($rooms);
        }

        // Room
        $roomType = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Room Type'))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()][1]")
            ?? $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Room ∆'), 'translate(normalize-space(),"0123456789","∆∆∆∆∆∆∆∆∆∆")')}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()][1]");
        $roomDescription = implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('Room Type'))}]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space()!=''][position()>1]"));

        // Cancellation
        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy:'))}]/ancestor::td[1]", null, true, "#{$this->opt($this->t('Cancellation Policy:'))}\s*(.+)#s");

        if (!empty($cancellation)) {
            $h->general()->cancellation($cancellation);
            //  Cancel by 12PM on 09/30/2018 to avoid $257.66 penalty.
            if (preg_match("#Cancel by ([\dapm]+) on ([\d\/]+) to avoid#i", $cancellation, $m)) {
                $h->booked()->deadline(strtotime($this->normalizeDate($m[2] . ' ' . $m[1])));
            }
        }

        $rate = null;

        // Price
        $total = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Grand Total'))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()][1]", null, true, "/^.*\d.*$/");

        if (preg_match('/^(?<currency>[^\-\d)(]+?)?[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currencyCode>[A-Z]{3})$/u', $total, $matches)
            || preg_match('/^(?<currencyCode>[A-Z]{3})[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $total, $matches)
        ) {
            // 764.70 USD    |    $1,398.32 USD
            if (empty($matches['currency'])) {
                $matches['currency'] = '';
            }
            $h->price()->currency($matches['currencyCode'])->total(PriceHelper::parse($matches['amount'], $matches['currencyCode']));

            $subTotal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Sub-total'))} or {$this->starts($this->t('Sub-total ('))}] ]", null, true, "/^.*\d.*$/");

            if (preg_match('/(?:^|:\s*)(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currencyCode'], '/') . ')?$/u', $subTotal, $m)
                || preg_match('/(?:^|:\s*)(?:' . preg_quote($matches['currencyCode'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $subTotal, $m)
            ) {
                // Sub-total (3 nights(s)): 657.00 USD    |    Sub-total Nights (4 Nights) : $1,156.00 USD
                $costAmount = PriceHelper::parse($m['amount'], $matches['currencyCode']);
                $h->price()->cost($costAmount);

                if (preg_match("/(?<nights>\b\d{1,3})\s*{$this->opt($this->t('night'))}/i", $subTotal, $m2)) {
                    $rate = $costAmount / $m2['nights'] . ' ' . $matches['currencyCode'];
                }
            }

            $taxes = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('taxes'))}] ]/*[normalize-space()][2]", null, true, "/^.*\d.*$/")
                ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('taxes'))}]", null, true, "/^{$this->opt($this->t('taxes'))}[: ]*(.*\d.*)$/");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currencyCode'], '/') . ')?$/u', $taxes, $m)
                || preg_match('/^(?:' . preg_quote($matches['currencyCode'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $taxes, $m)
            ) {
                $h->price()->tax(PriceHelper::parse($m['amount'], $matches['currencyCode']));
            }

            $feeTexts = $this->http->FindNodes("//text()[{$this->starts($this->t('feeNames'))}]");

            foreach ($feeTexts as $feeText) {
                if (preg_match("/^(?<name>.{2,}?)\s*[:]+\s*(?<charge>.*\d.*)$/", $feeText, $feeMatches)) {
                    if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currencyCode'], '/') . ')?$/u', $feeMatches['charge'], $m)
                        || preg_match('/^(?:' . preg_quote($matches['currencyCode'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeMatches['charge'], $m)
                    ) {
                        $h->price()->fee($feeMatches['name'], PriceHelper::parse($m['amount'], $matches['currencyCode']));
                    }
                }
            }
        }

        if (!empty($roomType) || !empty($roomDescription) || $rate !== null) {
            $r = $h->addRoom();

            if (!empty($roomType)) {
                $r->setType($roomType);
            }

            if (!empty($roomDescription)) {
                $r->setDescription($roomDescription);
            }

            if ($rate !== null) {
                $r->setRate($rate);
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body): bool
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if (is_array($detectBody)) {
                foreach ($detectBody as $dBody) {
                    if (stripos($body, $dBody) !== false && stripos($body, $this->anchor) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            } elseif (is_string($detectBody) && stripos($body, $detectBody) !== false && stripos($body, $this->anchor) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        $in = [
            //07/12/2017 (after 4:00 PM)
            '#(\d+)\/(\d+)\/(\d+)\s*\(.+?(\d+:\d+\s+[ap]m)\s*\)#i',
            //09/30/2018 12PM
            '#(\d+)\/(\d+)\/(\d+)\s+(\d+)\s*([ap]m)\s*#i',
        ];
        $out = [
            '$2.$1.$3 $4',
            '$2.$1.$3 $4:00$5',
        ];

        return preg_replace($in, $out, $date);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
