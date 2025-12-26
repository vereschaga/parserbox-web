<?php

namespace AwardWallet\Engine\thrifty\Email;

use AwardWallet\Schema\Parser\Email\Email;

class RentalConfirm extends \TAccountChecker
{
    public $mailFiles = "thrifty/it-61447260.eml, thrifty/it-1.eml, thrifty/it-1932048.eml, thrifty/it-2.eml, thrifty/it-2263405.eml, thrifty/it-3.eml, thrifty/it-3178076.eml, thrifty/it-3298322.eml, thrifty/it-3298323.eml, thrifty/it-4.eml, thrifty/it-5516745.eml, thrifty/it-48491626.eml";

    public $reBody = [
        'en' => ['Confirmation', 'Vehicle Type'],
    ];
    public $reSubject = [
        'Your Thrifty Car Rental Confirmation',
        'Confirmation of Change in Your Thrifty Car Rental Reservation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'RecordLocator' => 'Confirmation',
        ],
    ];

    public $dateTimeToolsMonths = [
        "en" => [
            "january"   => 0,
            "february"  => 1,
            "march"     => 2,
            "april"     => 3,
            "may"       => 4,
            "june"      => 5,
            "july"      => 6,
            "august"    => 7,
            "september" => 8,
            "october"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
    ];
    public $dateTimeToolsMonthsOutMonths = [
        "January",
        "February",
        "March",
        "April",
        "May",
        "June",
        "July",
        "August",
        "September",
        "October",
        "November",
        "December",
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        foreach ($this->reBody as $lang => $reBody) {
            if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $email->setType('RentalConfirm');
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'thrifty.com') or contains(@href,'thrifty.rsys2.net')]")->length > 0) {
            $body = $parser->getHTMLBody();

            foreach ($this->reBody as $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "thrifty.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = $this->translateMonth($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function translateMonth($month, $lang)
    {
        $month = mb_strtolower(trim($month), 'UTF-8');

        if (isset($this->dateTimeToolsMonths[$lang]) && isset($this->dateTimeToolsMonths[$lang][$month])) {
            return $this->dateTimeToolsMonthsOutMonths[$this->dateTimeToolsMonths[$lang][$month]];
        }

        return false;
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->rental();

        $rePhone = "/(?:(?:\d{1}|\+\d{1})[\- ]?)?(?:\(?\d{3}\)?[\- ]?)?[\d\- ]{7,}/";

        $confNo = $this->http->FindSingleNode("//*[starts-with(normalize-space(text()),'" . $this->t('RecordLocator') . "')]",
            null, true, "/\#\s*:?\s*([A-Z\d]+)/");

        if (!empty($confNo)) {
            $r->general()
                ->confirmation($confNo, $this->t('RecordLocator'));
        } else {
            $confNo = $this->http->FindSingleNode("//*[starts-with(normalize-space(text()),'" . $this->t('RecordLocator') . "')]/ancestor::td[1]",
                null, true, "/\#\s*:?\s*([A-Z\d]+)/");

            if (!empty($confNo)) {
                $r->general()
                    ->confirmation($confNo, $this->t('RecordLocator'));
            } else {
                $r->general()
                    ->noConfirmation();
            }
        }

        $node = $this->nextText('Rewards Program:');

        if (preg_match("/Blue\s+Chip\s+Rewards\s+\/\s+([A-Z\d]+)/", $node, $m)) {
            $account = str_replace("N/A", "", $m[1]);

            if (!empty($account)) {
                $r->program()
                    ->account($account, false);
            }
        } else {
            $str = trim(str_replace("N/A", "",
                str_replace(":", "", str_replace("#", "", $this->nextText('Blue Chip')))));

            if (!empty($str)) {
                $r->program()
                    ->account($str, false);
            }
        }

        $renterName = $this->nextText('Name:');

        if (!empty($renterName)) {
            $r->general()
                ->traveller($renterName, true);
        }

        $pickupDatetime = strtotime($this->normalizeDate($this->nextText('Pick-up Date/Time:')));

        if (!empty($pickupDatetime)) {
            $r->pickup()
                ->date($pickupDatetime);
        }

        $addr = "";
        $phone = "";
        $str = $this->nextText('Pick-up Location:');
        $i = 1;

        while (stripos($str, 'Map it') === false && $i < 5) {
            $addr .= ' ' . $str;
            $i++;
            $str = $this->nextText('Pick-up Location:', $i);

            if (preg_match("/^[\(\)\d\s-]+$/", $str)) {
                $phone = $str;

                break;
            }
        }

        $addrPickup = trim($addr);

        if (!empty($addrPickup)) {
            $r->pickup()
                ->location($addrPickup);
        }

        if (!empty($phone)) {
            if (preg_match($rePhone, $phone,
                $m)) {
                $r->pickup()
                    ->phone($m[0]);
            }
        }

        if (empty($addrPickup)) {
            $root = $this->http->XPath->query("//*[contains(text(),'VEHICLE PICKUP INFO')]/following::table[contains(.,'Location')][1]");

            if ($root->length == 0) {
                $root = $this->http->XPath->query("//*[contains(text(),'VEHICLE PICKUP INFO')]");
            }

            if ($root->length > 0) {
                $root = $root->item(0);
                $str = $this->http->FindSingleNode("(./following-sibling::*[starts-with(normalize-space(.),'Date/Time:')]/following-sibling::text())[1]", $root);

                if (!$str) {
                    $str = $this->nextText('Date/Time:', 1, $root);
                }

                $pickupDatetime = strtotime($this->normalizeDate($str));

                if (!empty($pickupDatetime)) {
                    $r->pickup()
                        ->date($pickupDatetime);
                } else {
                    $r->pickup()
                        ->noDate();
                }

                $pickupLocation = $this->http->FindSingleNode("(.//following-sibling::*[starts-with(normalize-space(.),'Location:')]/following-sibling::text())[1]", $root);

                if (!$pickupLocation) {
                    $pickupLocation = $this->nextText('Location:', 1, $root);
                }

                if (!empty($pickupLocation)) {
                    $r->pickup()
                        ->location($pickupLocation);
                } else {
                    $r->pickup()
                        ->noLocation();
                }

                if (!empty($pickupPhone)) {
                    if (preg_match($rePhone, $pickupPhone,
                        $m)) {
                        $r->pickup()
                            ->phone($m[0]);
                    }
                }
            } else {
                $r->pickup()
                    ->noDate()
                    ->noLocation();
            }
        }

        $dropoffDatetime = strtotime($this->normalizeDate($this->nextText('Return Date/Time:')));

        if (!empty($dropoffDatetime)) {
            $r->dropoff()
                ->date($dropoffDatetime);
        }

        $addr = "";
        $phone = "";
        $str = $this->nextText('Return Location:');
        $i = 1;

        while (stripos($str, 'Map it') === false && $i < 5) {
            $addr .= ' ' . $str;
            $i++;
            $str = $this->nextText('Return Location:', $i);

            if (preg_match("/^[\(\)\d\s-]+$/", $str)) {
                $phone = $str;

                break;
            }
        }

        $addrDropoff = trim($addr);

        if (!empty($addrDropoff)) {
            $r->dropoff()
                ->location($addrDropoff);
        }

        if (!empty($phone)) {
            if (preg_match($rePhone, $phone,
                $m)) {
                $r->dropoff()
                    ->phone($m[0]);
            }
        }

        if (empty($addrDropoff)) {
            $root = $this->http->XPath->query("//*[contains(text(),'VEHICLE RETURN INFO')]/following::table[contains(.,'Location')][1]");

            if ($root->length == 0) {
                $root = $this->http->XPath->query("//*[contains(text(),'VEHICLE RETURN INFO')]");
            }

            if ($root->length > 0) {
                $root = $root->item(0);
                $str = $this->http->FindSingleNode("(./following-sibling::*[starts-with(normalize-space(.),'Date/Time:')]/following-sibling::text())[1]", $root);

                if (!$str) {
                    $str = $this->nextText('Date/Time:', 1, $root);
                }
                $dropoffDatetime = strtotime($this->normalizeDate($str));

                if (!empty($dropoffDatetime)) {
                    $r->dropoff()
                        ->date($dropoffDatetime);
                } else {
                    $r->dropoff()
                        ->noDate();
                }

                $dropoffLocation = $this->http->FindSingleNode("(.//following-sibling::*[starts-with(normalize-space(.),'Location:')]/following-sibling::text())[1]", $root);

                if (!$dropoffLocation) {
                    $dropoffLocation = $this->nextText('Location:', 1, $root);
                }

                if (!empty($dropoffLocation)) {
                    $r->dropoff()
                        ->location($dropoffLocation);
                } else {
                    $r->dropoff()
                        ->noLocation();
                }

                $dropoffPhone = $this->nextText('Phone:', 1, $root);

                if (!empty($dropoffPhone)) {
                    if (preg_match($rePhone, $dropoffPhone, $m)) {
                        $r->dropoff()
                            ->phone($m[0]);
                    }
                }
            } else {
                $r->dropoff()
                    ->noDate()
                    ->noLocation();
            }
        }

        if (empty($r->getPickUpDateTime()) && empty($r->getDropOffDateTime())) {
            $r->pickup()->date(strtotime($this->normalizeDate($this->nextText('Date/Time:', 1, null))));
        }

        $node = $this->nextText('Vehicle Type:');

        if (preg_match("/(?:((.*?)\s+-\s+(.*))|(.+?or.*?))\s*$/i", $node, $m)) {
            if (!empty($m[4])) {
                $r->car()
                    ->type($m[4]);
                $carImg = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Vehicle Type:')) . "]/ancestor::td[1]/following::td[1]/descendant::img[contains(@src,'thrifty/vehicles')]/@src");

                if (!empty($carImg)) {
                    $r->car()
                        ->image($carImg);
                }
            } else {
                $r->car()
                    ->type($m[2])
                    ->model($m[3]);

                if ($this->http->XPath->query('//img[@alt = "' . $m[1] . '"]/@src')->length > 0) {
                    $carImageUrl = $this->http->XPath->query('//img[@alt = "' . $m[1] . '"]/@src')->item(0)->nodeValue;

                    if (!empty($carImageUrl)) {
                        $r->car()
                            ->image($carImageUrl);
                    }
                }
            }
        }

        $totalTaxAmount = $this->getTotalCurrency($this->nextText('State Tax:'))['Total'];

        if (!empty($totalTaxAmount)) {
            $r->price()
                ->tax($totalTaxAmount);
        }

        $tot = $this->getTotalCurrency($this->nextText('Estimated Grand Total'));

        if (empty($tot['Total'])) {
            $tot = $this->getTotalCurrency($this->nextText('Estimated Grand Total', 0));
        }

        if (!empty($tot['Currency'])) {
            $r->price()
                ->currency($tot['Currency']);
        }

        if (!empty($tot['Total'])) {
            $r->price()
                ->total($tot['Total']);
        }

        $discount = $this->nextText('Discount:');

        if (!empty($discount)) {
            if (preg_match('/(?:[$]|[A-Z]{3})([\.\d]+)/', $discount, $m)) {
                $r->price()
                    ->discount($m[1]);
            }
        }

        return $email;
    }

    private function nextText($field, $n = 1, $root = null)
    {
        if ($n > 0) {
            return $this->http->FindSingleNode("(.//text()[starts-with(normalize-space(.),'{$field}')]/following::text()[normalize-space(.)][{$n}])[1]", $root);
        } else {
            return $this->http->FindSingleNode("(.//text()[starts-with(normalize-space(.),'{$field}')])[1]", $root);
        }
        //		return $this->http->FindSingleNode("(.//*[starts-with(normalize-space(text()),'{$field}')]/following::text()[normalize-space(.)][{$n}])[1]", $root);
    }

    private function normalizeDate($date)
    {
        $in = [
            // Monday, January 23, 2017 @ 3:30 PM
            '/\S+\s+(\S+)\s+(\d+),\s+(\d+)\s+\@\s+(\d+:\d+\s*[ap]m)/i',
        ];
        $out = [
            '$2 $1 $3 $4',
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $date));
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("/(?<c>([A-Z]{3}|[\$]))\s*(?<t>\d[\.\d\,\s]*\d*)/", $node,
                $m) || preg_match("/(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>([A-Z]{3}|[\$]))/", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(' ', '', $m['t']);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }
}
