<?php

namespace AwardWallet\Engine\priceline\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourItinerary extends \TAccountChecker
{
    public $mailFiles = "priceline/it-2159247.eml, priceline/it-28.eml, priceline/it-29.eml, priceline/it-3.eml, priceline/it-4395411.eml, priceline/it-4433332.eml, priceline/it-5.eml";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            "Your request number:" => ["Your request number:", "priceline trip number:"],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && (
                stripos($headers['from'], 'request@production.priceline.com') !== false
                || stripos($headers['from'], 'ItineraryAir@trans.priceline.com') !== false
            ) && isset($headers['subject']) && (
                preg_match('#\s+Trip to\s+.*\s+on\s+\d+/\d+/\d+#', $headers['subject'])
                || stripos($headers['from'], 'Your priceline.com Itinerary for ') !== false
            );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Passenger and Ticket Information') !== false
                && strpos($parser->getHTMLBody(), 'Responses to this e-mail will not go to a customer service representative') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@production.priceline.com') !== false
                || stripos($from, '@trans.priceline.com') !== false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function parseEmail(Email $email)
    {
        // Travel Agency
        $email->obtainTravelAgency();
        $conf = $this->http->FindSingleNode("(//text()[" . $this->eq(["Your request number:", "priceline trip number:"]) . "])[1]/following::text()[normalize-space()][1]");

        if (!empty($conf)) {
            $email->ota()
                ->confirmation($conf);
        }

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers($this->http->FindNodes("//*[contains(text(), 'Travelers')]/ancestor-or-self::tr[1]/following-sibling::tr/td[1]//b"))
        ;

        $nodes = $this->http->FindNodes('//td[contains(normalize-space(text()), "Confirmation #")]/preceding-sibling::td[3]');
        $locators = $this->http->FindNodes('//td[contains(normalize-space(text()), "Confirmation #")]', null, '/[A-Z\d]{5,6}/');

        foreach ($nodes as $key => $value) {
            $rls[$value] = $locators[$key];
        }

        // Issied
        $tickets = array_filter($this->http->FindNodes("//*[contains(text(), 'Travelers')]/ancestor-or-self::tr[1]/following-sibling::tr/td[3]", null, "#^\s*(\d{13})\s*Delivery:#"));

        if (!empty($tickets)) {
            $f->issued()->tickets($tickets, false);
        }

        // Price
        $total = $this->http->FindSingleNode("//td[" . $this->eq(["Airfare Subtotal:"]) . "]/following-sibling::td[1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $f->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        $xpath = "//tr[contains(., 'Flight') and contains(., 'From') and not(.//tr)]/following-sibling::tr[count(./td) = 4 and contains(., ':')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airline = implode(" ", $this->http->FindNodes("./td[1]//text()[normalize-space()]", $root));

            if (preg_match("#(.+)\s+Flight\s+(\d{1,5})#", $airline, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                if (!empty($rls[$m[1]])) {
                    $s->airline()->confirmation($rls[$m[1]]);
                }
            }

            $dateStr = $this->http->FindSingleNode('./preceding-sibling::tr[contains(., "Leaving")][1]', $root, true, '#\w+\s+\d+,\s+\d{4}#i');

            // Departure
            $departure = implode(" ", $this->http->FindNodes("./td[2]//text()[normalize-space()]", $root));

            if (preg_match("#(.+)\s*\(\s*([A-Z]{3})\s*\)\s*(.+)\s+(\d{1,2}:\d+(\s*[ap]m)?)#i", $departure, $m)) {
                $s->departure()
                    ->name($m[1] . ', ' . $m[3])
                    ->code($m[2])
                    ->date(strtotime($dateStr . ', ' . $m[4]))
                ;
            }

            // Arrival
            $arrival = implode(" ", $this->http->FindNodes("./td[3]//text()[normalize-space()]", $root));

            if (preg_match("#(.+)\s*\(\s*([A-Z]{3})\s*\)\s*(.+)\s+(\d{1,2}:\d+(\s*[ap]m)?)#i", $arrival, $m)) {
                $s->arrival()
                    ->name($m[1] . ', ' . $m[3])
                    ->code($m[2])
                    ->date(strtotime($dateStr . ', ' . $m[4]))
                ;
            }

            // Extra
            $extra = implode(" ", $this->http->FindNodes("./td[4]//text()[normalize-space()]", $root));

            if (preg_match("#(\d+\s*h\s*\d+\s*m)\s+(.+)#", $extra, $m)) {
                $s->extra()
                    ->duration($m[1])
                    ->cabin($m[2])
                ;
            }
        }
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '$' => 'USD',
            '€' => 'EUR',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
