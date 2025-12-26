<?php

// bcdtravel + screenshot

namespace AwardWallet\Engine\marriott\Email;

class CarHtml2017 extends \TAccountChecker
{
    public $mailFiles = "marriott/it-56533093.eml";

    private $lang = '';
    private $subject = ['Your Trip Confirmation Number:'];
    private $body = [
        'en' => [
            'We are pleased to confirm your recent order of services,',
            'Thank you for booking your rental vehicle through Marriott Bonvoy Activities',
        ],
    ];

    private static $dict = [
        'en' => [],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && isset($headers['from'])
                && preg_match('/[@\.]marriott\.com/', $headers['from']) > 0 && $this->arrikey($headers['subject'], $this->subject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'marriott') !== false && $this->arrikey($parser->getHTMLBody(), $this->body) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@\.]marriott\.com/', $from) > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        if ($this->lang = $this->arrikey($parser->getHTMLBody(), $this->body)) {
            $its[] = $this->parseCar();
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'CarHtml2017' . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    /**
     * TODO: In php problems with "Type declarations", so i did so.
     * Are case sensitive. Example:
     * <pre>
     * var $reBody = ['en' => ['Reservation Modify'],];
     * var $reSubject = ['Reservation Modify']
     * </pre>.
     *
     * @param string $haystack
     *
     * @return int, string, false
     */
    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $key;
            }
        }

        return false;
    }

    private function parseCar(): array
    {
        $result = ['Kind' => 'L'];

        $company = $this->http->FindSingleNode("//table[ starts-with(normalize-space(),'Your') and following-sibling::*[normalize-space()][1][starts-with(normalize-space(),'Renter Name')] ]/descendant::img/@alt", null, true, "/(?:Hertz|National|Sixt|Thrifty|Europcar|Enterprise|Dollar|Budget|Alamo|Avis)/i");

        if ($company) {
            $result['RentalCompany'] = $company;
        }

        $result['RenterName'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Dear ')]", null, false, '/\s+(.{3,20}),/');

        if (empty($result['RenterName'])) {
            $result['RenterName'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Renter Name')]/ancestor::td[1]/descendant::text()[normalize-space()][2]");
        }

        $result['CarModel'] = $this->http->FindSingleNode("//div[contains(text(), 'Confirmation Number:')]/preceding-sibling::div[normalize-space(.)!=''][2]");

        if (empty($result['CarModel'])) {
            $result['CarModel'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Confirmation Number')]/following::td[normalize-space()][1]", null, true, '/(.+\s*OR\s*SIMILAR)/');
        }

        $result['Number'] = $this->http->FindSingleNode("//div[contains(text(), 'Confirmation Number:')]/following-sibling::div[1]", null, false, '/[\w-]+/');

        if (empty($result['Number'])) {
            $result['Number'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Confirmation Number')]/ancestor::td[1]/descendant::text()[normalize-space()][2]");
        }

        $result['TripNumber'] = $this->http->FindSingleNode("//text()[contains(., 'Your Order Number:')]", null, false, '/:\s*([\w-]+)/');

        foreach ($results = $this->http->XPath->query("//text()[contains(., 'Pick-up Location:')]/ancestor::tr[1]") as $root) {
            $result['PickupDatetime'] = strtotime($this->http->FindSingleNode("following-sibling::tr[1]/descendant::td[1]", $root), false);
            $result['DropoffDatetime'] = strtotime($this->http->FindSingleNode("following-sibling::tr[1]/descendant::td[2]", $root), false);
            $result['PickupLocation'] = $this->http->FindSingleNode("following-sibling::tr[2]/descendant::td[1]", $root);
            $result['DropoffLocation'] = $this->http->FindSingleNode("following-sibling::tr[2]/descendant::td[2]", $root);
        }

        $result['PickupPhone'] = $result['DropoffPhone'] =
                $this->http->FindSingleNode("//text()[contains(., 'Customer Care at')]/following-sibling::span[1]", null, false, '/[+\d\s()-]+/');

        if ($results->count() === 0) {
            // pick-up
            $pickUpLoc = $this->htmlToText($this->http->FindHTMLByXpath("//tr/*[normalize-space()='Pick-up:']/following-sibling::*[normalize-space()][1]"));
            $result['PickupLocation'] = preg_replace('/\s+/', ' ', $pickUpLoc);

            $pickUpDate = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ *[1][normalize-space()='Pick-up:'] ]/following-sibling::tr[normalize-space()][position()<5][ *[1][normalize-space()='Date:'] ]/*[2]"));
            $pickUpTime = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ *[1][normalize-space()='Pick-up:'] ]/following-sibling::tr[normalize-space()][position()<5][ *[1][normalize-space()='Time:'] ]/*[2]"));

            if ($pickUpDate && $pickUpTime) {
                $result['PickupDatetime'] = strtotime($pickUpTime, strtotime($pickUpDate));
            }

            $pickUpPhone = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ *[1][normalize-space()='Pick-up:'] ]/following-sibling::tr[normalize-space()][position()<5][ *[1][normalize-space()='Phone:'] ]/*[2]"));
            $result['PickupPhone'] = $pickUpPhone;

            $pickUpHours = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ *[1][normalize-space()='Pick-up:'] ]/following-sibling::tr[normalize-space()][position()<5][ *[1][normalize-space()='Hours:'] ]/*[2]"));

            if ($pickUpHours) {
                $result['PickupHours'] = preg_replace('/\s+/', ' ', $pickUpHours);
            }

            // drop-off
            $dropOffLoc = $this->htmlToText($this->http->FindHTMLByXpath("//tr/*[normalize-space()='Drop-off:']/following-sibling::*[normalize-space()][1]"));
            $result['DropoffLocation'] = preg_replace('/\s+/', ' ', $dropOffLoc);

            $dropOffDate = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ *[1][normalize-space()='Drop-off:'] ]/following-sibling::tr[normalize-space()][position()<5][ *[1][normalize-space()='Date:'] ]/*[2]"));
            $dropOffTime = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ *[1][normalize-space()='Drop-off:'] ]/following-sibling::tr[normalize-space()][position()<5][ *[1][normalize-space()='Time:'] ]/*[2]"));

            if ($dropOffDate && $dropOffTime) {
                $result['DropoffDatetime'] = strtotime($dropOffTime, strtotime($dropOffDate));
            }

            $dropOffPhone = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ *[1][normalize-space()='Drop-off:'] ]/following-sibling::tr[normalize-space()][position()<5][ *[1][normalize-space()='Phone:'] ]/*[2]"));
            $result['DropoffPhone'] = $dropOffPhone;

            $dropOffHours = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ *[1][normalize-space()='Drop-off:'] ]/following-sibling::tr[normalize-space()][position()<5][ *[1][normalize-space()='Hours:'] ]/*[2]"));

            if ($dropOffHours) {
                $result['DropoffHours'] = preg_replace('/\s+/', ' ', $dropOffHours);
            }
        }

        $total = $this->http->FindSingleNode("//text()[contains(., 'Estimated Total to')]/ancestor::td[1]/following-sibling::td[1]");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Estimated Total')]/following::text()[normalize-space()][2]");
        }
        $result['TotalCharge'] = preg_replace('/[^\d.]+/', '', $total);
        $result['Currency'] = preg_replace(['/[\d.,\s]+/', '/€/', '/^\$$/', '/^£$/'], ['', 'EUR', 'USD', 'GBR'], $total);

        return $result;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
