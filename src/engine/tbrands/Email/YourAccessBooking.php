<?php

namespace AwardWallet\Engine\tbrands\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourAccessBooking extends \TAccountChecker
{
    public $mailFiles = "tbrands/it-730845778.eml, tbrands/it-731252299.eml, tbrands/it-733518922.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Booking Summary'   => 'Booking Summary',
            'Passenger Details' => 'Passenger Details',
        ],
    ];

    private $detectFrom = "donotreply@travelbrands.com";
    private $detectSubject = [
        // en
        'Your Access booking',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]travelbrands\.com$/", $from) > 0;
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
            $this->http->XPath->query("//a[{$this->contains(['travelbrands.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['booking with TravelBrands'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Booking Summary']) && $this->http->XPath->query("//*[{$this->contains($dict['Booking Summary'])}]")->length > 0
                && !empty($dict['Passenger Details']) && $this->http->XPath->query("//*[{$this->contains($dict['Passenger Details'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // $this->assignLang();
        // if (empty($this->lang)) {
        //     $this->logger->debug("can't determine a language");
        //     return $email;
        // }
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
            if (!empty($dict['Booking Summary']) && $this->http->XPath->query("//*[{$this->contains($dict['Booking Summary'])}]")->length > 0
                && !empty($dict['Passenger Details']) && $this->http->XPath->query("//*[{$this->contains($dict['Passenger Details'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        // Travel Agency
        $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking number :'))}]/ancestor::div[1]",
            null, true, "/{$this->opt($this->t('Booking number :'))}\s*(\d{5,})\s*$/");
        $email->ota()
            ->confirmation($conf);

        // Price
        $currency = null;
        $currencyStr = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Pricing Summary'))}]/following::tr/*[1][{$this->starts($this->t('Total'))}]",
            null, "/^\s*{$this->opt($this->t('Total'))}\s*\(\s*([A-Z]{3})\s*\)\s*:\s*$/")));

        if (count($currencyStr) === 1) {
            $currency = array_shift($currencyStr);
        }

        if (empty($currency)) {
            $currencyStr = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Pricing Summary'))}]/following::tr[not(.//tr)]/*[1][.//text()[{$this->eq($this->t('Amount'))}]][contains(., '(') and contains(., ')')]",
                null, "/^\s*{$this->opt($this->t('Amount'))}\s*\(\s*([A-Z]{3})\s*\)\s*$/")));

            if (count($currencyStr) === 1) {
                $currency = array_shift($currencyStr);
            }
        }

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pricing Summary'))}]/following::tr[*[1][{$this->eq($this->t('Price:'))}]]/*[2]",
                null, true, "/^(\D{1,3})\s*\d+/");
        }

        $email->price()
            ->currency($currency);

        $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pricing Summary'))}]/following::tr[*[1][{$this->eq($this->t('Price:'))}]]/*[2]",
            null, true, "/^\D{0,5}(\d[\d,. ]*?)\D{0,5}$/");

        if (empty($cost)) {
            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pricing Summary'))}]/following::text()[normalize-space()][1][{$this->contains($this->t('Rooms X'))}][{$this->contains($this->t('Night'))}]/ancestor::tr[1]/*[2]",
                null, true, "/^\D{0,5}(\d[\d,. ]*?)\D{0,5}$/");
        }

        $email->price()
            ->cost(PriceHelper::parse($cost, $currency));

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pricing Summary'))}]/following::tr[*[1][{$this->eq($this->t('Total Gross:'))}]]/*[2]",
            null, true, "/^\D{0,5}(\d[\d,. ]*?)\D{0,5}$/");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pricing Summary'))}]/following::tr[*[1][{$this->starts($this->t('Total ('))}]]/*[2]",
                null, true, "/^\D{0,5}(\d[\d,. ]*?)\D{0,5}$/");
        }
        $email->price()
            ->total(PriceHelper::parse($total, $currency));

        $feeXpath = "//text()[{$this->eq($this->t('Pricing Summary'))}]/following::tr[*[1][{$this->eq($this->t('Price:'))}]]/following-sibling::tr[following-sibling::tr[{$this->starts($this->t('Total Gross:'))}]]";
        $feeNodes = $this->http->XPath->query($feeXpath);

        if ($feeNodes->length === 0) {
            $feeXpath = "//text()[{$this->eq($this->t('Pricing Summary'))}]/following::tr[not(.//tr)][*[1][{$this->contains($this->t('Rooms X'))}]]/following-sibling::tr[following-sibling::tr[{$this->starts($this->t('Total ('))}]]";
            $feeNodes = $this->http->XPath->query($feeXpath);
        }

        foreach ($feeNodes as $fRoot) {
            $name = $this->http->FindSingleNode("*[1]", $fRoot);
            $value = $this->http->FindSingleNode("*[2]", $fRoot, true, "/^\D{0,5}(\d[\d,. ]*?)\D{0,5}$/");
            $email->price()
                ->fee(trim($name, ':'), PriceHelper::parse($value, $currency));
        }

        $allTravellers = array_unique(array_filter(preg_replace('/^\s*(Mr\.|Mrs\.|Ms\.|Dr\.)\s+/', '',
            $this->http->FindNodes("//text()[{$this->starts($this->t('Passenger'))}]/ancestor::tr[1][count(.//text()[normalize-space()]) = 2]",
                null, "/^\s*{$this->opt($this->t('Passenger'))}\s*\d+:\s*(.+)/"))));

        // Flights
        $xpathTime = 'translate(normalize-space(),"0123456789","dddddddddd") = "dd:dd"';
        $xpath = "//*[count(*) = 2][*[1]/descendant::text()[normalize-space()][1][{$xpathTime}]][*[2]/descendant::text()[normalize-space()][1][$xpathTime]]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0 || !empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight Details'))}]"))) {
            $f = $email->add()->flight();

            $f->general()
                ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight Details'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('Confirmation Number:'))}]/following::text()[normalize-space()][1]",
                    null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
                ->travellers($allTravellers)
            ;
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flightInfo = implode("\n", $this->http->FindNodes("preceding::tr[not(.//tr)][normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*Flight #(\d{1,4})\n(.+) Class/", $flightInfo, $m)) {
                $s->airline()
                    ->number($m[1]);
                $s->extra()
                    ->cabin($m[2]);
            }

            // https://travel-img-assets.s3-us-west-2.amazonaws.com/flights/carrier-48x48/pd.png
            $airline = $this->http->FindSingleNode("preceding::tr[not(.//tr)][normalize-space()][1]//img/@src[contains(., '')]",
                $root, true, "/\/flights\/carrier[^\/]+\/([a-z\d]{2})\.png/");

            if (!empty($airline)) {
                $s->airline()
                    ->name($airline);
            } else {
                $s->airline()
                    ->noName();
            }

            $re = "/^\s*(?<time>\d{2}:\d{2}.*)\n(?<code>[A-Z]{3})\n(?<name>\S.+)\n(?<date>\S.+)\s*$/";
            // Departure
            $dep = implode("\n", $this->http->FindNodes("*[1]//text()[normalize-space()]", $root));

            if (preg_match($re, $dep, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date(strtotime($m['date'] . ', ' . $m['time']))
                ;
            }

            // Arrival
            $arr = implode("\n", $this->http->FindNodes("*[2]//text()[normalize-space()]", $root));

            if (preg_match($re, $arr, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date(strtotime($m['date'] . ', ' . $m['time']))
                ;
            }
        }

        // Hotels
        $h = $email->add()->hotel();
        $h->general()
            // ->noConfirmation()
            ->travellers($allTravellers);

        $cancellation = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Cancellation Policy'))} or {$this->starts($this->t('Cancellation Policy Room #'))}]/following::text()[normalize-space()][1]/"
            . "ancestor::*[not(.//text()[{$this->eq($this->t('Cancellation Policy'))}]) and not(.//text()[{$this->starts($this->t('Cancellation Policy Room #'))}])][last()]"));

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation(implode('; ', $cancellation));
        }

        // hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Details'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('Confirmation Number:'))}]/ancestor::tr[1]/following::tr[not(.//tr)][normalize-space()][1]"));

        $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Details'))}]/following::tr[*[1][{$this->eq($this->t('Address:'))}]]/*[2]");

        if (!empty($address)) {
            $h->hotel()
                ->address($address);
        } else {
            $h->hotel()
                ->noAddress();
        }

        // booked
        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Details'))}]/following::tr[*[1][{$this->eq($this->t('Check In:'))}]]/*[2]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Details'))}]/following::tr[*[1][{$this->eq($this->t('Check Out:'))}]]/*[2]")))
            ->rooms($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Details'))}]/following::tr[*[1][{$this->eq($this->t('Number of rooms:'))}]]/*[2]"), true, true)
        ;
        $hConfText = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Hotel Details'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('Confirmation Number:'))}]/ancestor::td[1]//text()[normalize-space()]"));
        $confs = [];
        $hConfText = preg_replace("/^\s*{$this->opt($this->t('Confirmation Number:'))}\s*/", '', $hConfText);

        if (preg_match("/^\s*([\w\-]{5,})\s*$/", $hConfText, $m)) {
            $h->general()
                ->confirmation($m[1]);
        } elseif (preg_match_all("/^\s*\W*\s*{$this->opt($this->t('Room '))}\s*\d+\s*-\s*([\w\-]{5,})\s*$/m", $hConfText, $m)) {
            $confs = $m[1];
            $h->general()
                ->noConfirmation();
        }

        $types = $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger Details'))}]/following::td[{$this->starts($this->t('Room '))}][following::text()[{$this->eq($this->t('Hotel Details'))}]]",
            null, "/^\s*{$this->opt($this->t('Room '))}\s*\d+:\s*(.+)/");

        if (!empty($confs) && !empty($types)) {
            if (count($confs) === count($types)) {
                foreach ($confs as $i => $conf) {
                    $h->addRoom()
                        ->setType($types[$i])
                        ->setConfirmation($conf);
                }
            } else {
                foreach ($confs as $i => $conf) {
                    $h->addRoom()
                        ->setConfirmation($conf);
                }
            }
        } elseif (!empty($confs)) {
            foreach ($confs as $i => $conf) {
                $h->addRoom()
                    ->setType($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Details'))}]/following::tr[*[1][{$this->eq($this->t('Room Type:'))}]]/*[2]"), true, true)
                    ->setConfirmation($conf);
            }
        } elseif (!empty($types)) {
            foreach ($types as $type) {
                $h->addRoom()
                    ->setType($type);
            }
        }

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
