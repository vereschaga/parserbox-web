<?php

namespace AwardWallet\Engine\mirage\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "mirage/it-19432665.eml, mirage/it-20154409.eml, mirage/it-26661144.eml";

    public $reBody = [
        'en'  => ['For full details please visit www.mgmresorts.com', 'Booking Reference'],
        'en2' => ['MGM Resorts International', 'Acknowledgement'],
        'en3' => ['MGM Resorts International', 'Room Confirmation'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [],
    ];
    private static  $headers = [
        'gcampaigns' => [
            'from' => ['pkghlrss.com'],
            'subj' => [
                'Booking Confirmation',
                'Your Mirage Reservation Confirmation',
                'Your Reservation Confirmation',
            ],
        ],
        'mirage' => [
            'from' => ['mgmresorts.com'],
            'subj' => [
                'Booking Confirmation',
                'Your Mirage Reservation Confirmation',
            ],
        ],
    ];

    private $bodies = [
        'gcampaigns' => [
            'groupcampaigns@pkghlrss.com',
            '//a[contains(@href,"passkey.com")]',
        ],
        'mirage' => [
            '//a[contains(@href,"mgmresorts.com")]',
        ],
    ];

    private $code;

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();
        $its = $this->parseEmail();
        $result = [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "BookingConfirmation" . ucfirst($this->lang),
        ];

        if (null !== ($prov = $this->getProvider($parser))) {
            $result['providerCode'] = $prov;
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(.),'MGM Resorts')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'mirage') {
                return null;
            } else {
                return $this->code;
            }
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\Hotel $it */
        $it = ['Kind' => 'R'];
        $it['ConfirmationNumber'] = $this->nextText([$this->t('Booking Reference'), 'Acknowledgement #']);

        if (empty($it['ConfirmationNumber'])) {
            $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[contains(.,'Room Acknowledgement') or contains(.,'Room Confirmation:')]", null, true, "#(?:Room Acknowledgement|Room Confirmation)[\s:]+([\w\-]{5,})#");
        }

        // HotelName
        // Address
        $it['HotelName'] = $this->http->FindSingleNode("//text()[contains(.,'Your') and contains(.,'Hotel Confirmation')]", null, true, "#Your\s+(.+?)\s+Hotel Confirmation#");

        if (empty($it['HotelName'])) {
            $it['HotelName'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Thank you for choosing ')]", null, true, "#Thank you for choosing (.+?)\.#");
        }
        $rule = $this->contains(['Privacy Policy', 'PRIVACY POLICY']);

        if (empty($it['HotelName'])) {
            $nameAddr = $this->http->FindNodes("(//a[{$rule}]/ancestor::div[1]/following::p[1]/descendant::strong | //a[{$rule}]/ancestor::span[1]/following-sibling::span[3]/descendant::text()[normalize-space(.)!=''] | //a[{$rule}]/ancestor::span[1]/following-sibling::span[2]/descendant::text()[normalize-space(.)!='']) | //a[{$rule}]/following::strong[normalize-space(.)!=''][last()]/descendant::text()[normalize-space(.)!='']");

            if (empty($nameAddr)) {
                // it-19432665.eml
                $nameAddr = $this->http->FindNodes("//a[{$rule}]/following::text()[normalize-space(.)!=''][1]/ancestor::p[1]/descendant::text()[normalize-space(.)!='']");
            }

            if (empty($nameAddr)) {
                // it-20154409.eml
                $nameAddr = $this->http->FindNodes("//a[{$rule}]/ancestor::tr[1]/preceding::text()[normalize-space(.)][1]/ancestor::*[self::p or self::td or self::th][1]/text()[normalize-space(.)]");
            }

            if (count($nameAddr) > 1) {
                if (false !== stripos($nameAddr[0], 'All rights reserved')) {
                    array_shift($nameAddr);
                }
                $it['HotelName'] = array_shift($nameAddr);
                $it['Address'] = implode(', ', $nameAddr);
            }
        } else {
            $address = implode(", ", $this->http->FindNodes("//a[{$rule}]/following::text()[starts-with(normalize-space(), '" . $it['HotelName'] . "')]/ancestor::*[1]//text()[normalize-space()]"));

            if (preg_match("#" . $it['HotelName'] . "[,\s]+(.+)#", $address, $m)) {
                $it['Address'] = trim(preg_replace("#([\s,]*,[\s,]*)#", ', ', $m[1]), ' ,');
            }

            if (empty($it['Address'])) {
                $it['Address'] = $it['HotelName'];
            }
        }

        $it['GuestNames'] = array_filter([$this->nextText('Reservation Name')]);

        if (empty($it['GuestNames']) && ($guests = $this->http->FindNodes("//td[contains(., 'First Name') or contains(., 'Last Name')]/following-sibling::td[1]")) && count($guests) > 0) {
            $it['GuestNames'] = array_filter([trim(implode(' ', $guests))]);
        }

        if (empty($it['GuestNames'])) {
            $it['GuestNames'] = array_filter([$this->http->FindSingleNode("(//text()[starts-with(normalize-space(.),'Dear ') or starts-with(normalize-space(.),'Hello ')])[last()]", null, false, "#(?:Dear|Hello)\s+(.+?)(?:,|$)#")]);
        }// 'last()' Hello - in forwarding could be twice or more
        $it['Guests'] = trim($this->http->FindSingleNode("//text()[contains(.,'No. of Guests')]", null, true, "#(?:No\. of Guests)[\s:]+(\d+)#"));

        if (empty($it['Guests'])) {
            $it['Guests'] = $this->nextText('Number of Guests');
        }
        $it['CheckInDate'] = strtotime($this->nextText('Arrival Date'));
        $it['CheckOutDate'] = strtotime($this->nextText('Departure Date'));

        if (empty($it['CheckInDate']) && empty($it['CheckOutDate'])) {
            $node = $this->http->FindSingleNode("//text()[contains(.,'Arrival') and contains(.,'Departure')]");

            if (preg_match("#Arrival:\s+(.+)\s*\|\s*Departure:\s+(.+)#", $node, $m)) {
                $it['CheckInDate'] = strtotime($m[1]);
                $it['CheckOutDate'] = strtotime($m[2]);
            }
        }
        $it['Rooms'] = $this->nextText('Number of Rooms');
        $it['RoomType'] = trim($this->http->FindSingleNode("//text()[contains(.,'Room Type')]", null, true, "#(?:Room Type)[\s:]+(\S.*?)(?:\||$)#"));

        if (empty($it['RoomType'])) {
            $it['RoomType'] = $this->nextText('Room Type');
        }
        $it['RoomTypeDescription'] = $this->nextText('Special Requests');
        $it['Rate'] = $this->nextCol('Room Rate');
        $it['CancellationPolicy'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Cancellation Information')]/following::p[normalize-space(.)!=''][1]");

        return [$it];
    }

    private function nextText($field)
    {
        return $this->http->FindSingleNode("//text()[{$this->starts($field)}]/following::text()[string-length(normalize-space(.))>0][1]");
    }

    private function nextCol($field)
    {
        return $this->http->FindSingleNode("//text()[{$this->starts($field)}]/ancestor::td[1]/following-sibling::td[string-length(normalize-space(.))>0][1]");
    }

    private function starts($field)
    {
        $field = (array) $field;
        $field = array_map(function ($el) {
            return "starts-with(normalize-space(.),'{$el}')";
        }, $field);

        return '(' . implode(" or ", $field) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0 && $this->http->XPath->query("*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }
}
