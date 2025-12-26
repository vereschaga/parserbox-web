<?php

namespace AwardWallet\Engine\ytc\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TravelItineraryHotel extends \TAccountChecker
{
    public $mailFiles = "ytc/it-203433552.eml, ytc/it-706673794.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Hotel Name :'   => 'Hotel Name :',
            'arrival'        => ['Arrival'],
        ],
    ];

    private $detectSubjects = [
        // en
        'Confirmation Hotel Booking',
    ];

    private $detectors = [
        'en' => [
            'Travel Itinerary detail', 'Travel itinerary Detail', 'Travel Itinerary Detail', 'Travel itinerary detail',
            'Passenger Details', 'Passenger details',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@web-fares.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query("//tr[normalize-space()='' and count(descendant::img[contains(@src,'ytc.wfares.com') and contains(@src,'/logo')])=1]")->length === 0
            && $this->http->XPath->query('//*[contains(.,"@web-fares.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $email->obtainTravelAgency();

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('TravelItinerary' . ucfirst($this->lang));

        $xpath = "//text()[{$this->starts('CONFIRMATION NUMBER')}]/ancestor::*[{$this->contains('RoomType :')}][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $confs = $this->http->FindSingleNode(".//text()[{$this->starts("CONFIRMATION NUMBER")}]", $root, true,
                "/{$this->opt("CONFIRMATION NUMBER")}\s*:\s*([\w \\/\-]{5,})\s*$/");
            $confs = preg_split("/\s*\\/\s*/", trim($confs));

            foreach ($confs as $conf) {
                $h->general()
                    ->confirmation($conf);
            }
            $h->general()
                ->cancellation($this->http->FindSingleNode(".//*[self::td or self::th][{$this->eq("Cancellation :")}]/following-sibling::*[normalize-space()][1]", $root));

            $travellers = [];
            $txpath = ".//tr[*[1][{$this->eq($this->t('Guest Name'))}] and *[2][{$this->eq($this->t('Type'))}]]/following-sibling::tr";
            $tnodes = $this->http->XPath->query($txpath);

            foreach ($tnodes as $troot) {
                $values = $this->http->FindNodes("*", $troot);

                if (count($values) == 2 && stripos($values[0], ':') === false) {
                    $travellers[] = $values[0];
                } else {
                    break;
                }
            }

            $h->general()
                ->travellers($travellers);

            // Hotel
            $address = implode(" ",
                $this->http->FindNodes(".//*[self::td or self::th][{$this->eq("Hotel Address :")}]/following-sibling::*[normalize-space()][1]//text()[normalize-space()]", $root));
            $h->hotel()
                ->name($this->http->FindSingleNode(".//*[self::td or self::th][{$this->eq("Hotel Name :")}]/following-sibling::*[normalize-space()][1]", $root))
                ->address($this->re("/^(.+?)\s*(?:Ph :|Fax : )/", $address))
                ->phone($this->re("/\bPh\s*:\s*(.+?)\s*(?:Fax\s*:\s*|$)/", $address), true, true)
                ->fax($this->re("/\bFax\s*:\s*(.+?)\s*$/", $address), true, true);

            // Booked
            $h->booked()
                ->checkIn(strtotime($this->http->FindSingleNode(".//*[self::td or self::th][{$this->eq("Check-In :")}]/following-sibling::*[normalize-space()][1]", $root)))
                ->checkOut(strtotime($this->http->FindSingleNode(".//*[self::td or self::th][{$this->eq("Check-Out :")}]/following-sibling::*[normalize-space()][1]", $root)))
                ->rooms($this->http->FindSingleNode(".//*[self::td or self::th][{$this->eq("Room Details :")}]/following-sibling::*[normalize-space()][1]",
                    $root, true, "/Room\(s\)\s*:\s*(\d+)\D/"))
                ->guests($this->http->FindSingleNode(".//*[self::td or self::th][{$this->eq("Room Details :")}]/following-sibling::*[normalize-space()][1]",
                    $root, true, "/Guest\(s\)\s*:\s*(\d+)(?:\D|$)/"));

            // Rooms
            $r = $h->addRoom();

            $r
                ->setType($this->http->FindSingleNode(".//*[self::td or self::th][{$this->eq("RoomType :")}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root))
                ->setDescription(implode(" ",
                    $this->http->FindNodes(".//*[self::td or self::th][{$this->eq("RoomType :")}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()][position() > 1]", $root)))
                ->setRate($this->http->FindSingleNode(".//*[self::td or self::th][{$this->eq("Per Night Rate :")}]/following-sibling::*[normalize-space()][1]", $root));

            // Price
            $total = $this->http->FindSingleNode(".//*[self::td or self::th][{$this->eq("Approx Total Price :")}]/following-sibling::*[normalize-space()][1]", $root);

            if (preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)) {
                $h->price()
                    ->total(PriceHelper::parse($m['amount'], $m['currency']))
                    ->currency($m['currency']);
            }
        }

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

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $phrases) {
            if (empty($phrases['Hotel Name :'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Hotel Name :'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }
}
