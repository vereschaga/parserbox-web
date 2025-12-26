<?php

namespace AwardWallet\Engine\itc\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "itc/it-680381220.eml, itc/it-681597708.eml, itc/it-681606124.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Confirmation Number -' => ['Confirmation Number -', 'Itinerary Number -'],
        ],
    ];

    private $detectFrom = "reservations@itc-hotels.com";
    private $detectSubject = [
        'Reservation Confirmation',
    ];
    private $detectBody = [
        'en' => [
            'Your Reservation Confirmation',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]itc-hotels\.com$/", $from) > 0;
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
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['.itchotels.com/'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['DOWNLOAD ITC HOTELS MOBILE APP', 'via ITC Hotels’ Guest Contact Centre'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
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

    private function parseEmailHtml(Email $email)
    {
        $hotels = [];
        $xpath = "//text()[{$this->eq($this->t('Guest Details'))}]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $i => $root) {
            $addXpathCond = "[count(preceding::text()[{$this->eq($this->t('Guest Details'))}]) = " . ($i + 1) . " and count(following::text()[{$this->eq($this->t('Guest Details'))}]) = " . ($nodes->length - $i - 1) . "]";

            $hotelSeg = [];

            // General
            $hotelSeg['travellers'][] = $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('NAME'))}]]{$addXpathCond}/*[2]",
                    null, true, "/^\s*(?:(?:Mr|Mrs|Miss|Mstr|Ms|Dr)\.?\s+)?(\D+)\s*$/");
            $hotelSeg['cancellations'][] = $this->http->FindSingleNode("//tr[not(.//tr)][{$this->eq($this->t('Cancellation Policy'))}]{$addXpathCond}/following-sibling::tr[normalize-space()][1]");

            // Program
            $hotelSeg['accounts'][] = $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('MEMBERSHIP NUMBER'))}]]{$addXpathCond}/*[2]",
                null, true, "/^\s*([A-Z\d]{4,})\s*$/");

            // Booked
            $hotelSeg['checkIn'] = $this->normalizeDate($this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('CHECK-IN'))}]]{$addXpathCond}/*[2]")
                    . ', ' . $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('CHECK-IN'))}]]{$addXpathCond}/following-sibling::tr[1]",
                        null, true, "/^\s*(\d{1,2}:\d{2})\s*\(/"));
            $hotelSeg['checkOut'] = $this->normalizeDate($this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('CHECK-OUT'))}]]{$addXpathCond}/*[2]")
                    . ', ' . $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('CHECK-OUT'))}]]{$addXpathCond}/following-sibling::tr[1]",
                        null, true, "/^\s*(\d{1,2}:\d{2})\s*\(/"));
            $hotelSeg['guests'] = $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('NUMBER OF GUESTS'))}]]{$addXpathCond}/*[2]",
                    null, true, "/^\s*(\d+)\s*{$this->opt($this->t('Adult'))}/");
            $hotelSeg['kids'] = $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('NUMBER OF GUESTS'))}]]{$addXpathCond}/*[2]",
                    null, true, "/\b(\d+)\s*(?:\([\d, ]+\))?\s*{$this->opt($this->t('Children'))}/");

            // Room
            $hotelSeg['rooms'][] = [
                'setType'     => $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('ACCOMMODATION TYPE'))}]]{$addXpathCond}/*[2]"),
                'setRateType' => $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('RATE PLAN BOOKED'))}]]{$addXpathCond}/*[2]"),
                'setRate'     => $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('AVG. DAILY RATE'))}]]{$addXpathCond}/*[2]"),
            ];

            // Price
            $cost = $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('TOTAL AMOUNT'))}]]{$addXpathCond}/*[2]");
            $spentAwards = 0.0;

            if (preg_match("/^\s*(\d+)\s*pts\s*$/", $cost, $m)) {
                $spentAwards = $m[1];
                $hotelSeg['spentAwards'] = $cost;
                $hotelSeg['cost'] = 0.0;
            } elseif (preg_match("/^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[., \d]*)\s*$/", $cost, $m)) {
                $hotelSeg['cost'] = PriceHelper::parse($m['amount'], $m['currency']);
                $hotelSeg['currency'] = $m['currency'];
            }
            $tax = $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('TOTAL TAX'))}]]{$addXpathCond}/*[2]");

            if (preg_match("/^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[., \d]*)\s*$/", $tax, $m)) {
                $hotelSeg['tax'] = PriceHelper::parse($m['amount'], $m['currency']);
            }
            $total = $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('TOTAL CHARGE'))}]]{$addXpathCond}/*[2]");

            if (preg_match("/^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[., \d]*)\s*$/", $total, $m)) {
                $hotelSeg['total'] = PriceHelper::parse($m['amount'], $m['currency']);

                if (!empty($hotelSeg['spentAwards']) && !empty($hotelSeg['tax']) && $hotelSeg['total'] === $spentAwards + $hotelSeg['tax']) {
                    $hotelSeg['total'] = $hotelSeg['tax'];
                }
                $hotelSeg['currency'] = $m['currency'];
            } elseif (preg_match("/^\s*(\d+\s*pts)\s*\+\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[., \d]*)\s*$/", $total, $m)) {
                // 3778 pts + INR 453.32
                $hotelSeg['total'] = PriceHelper::parse($m['amount'], $m['currency']);
                $hotelSeg['spentAwards'] = $m[1];
                $hotelSeg['currency'] = $m['currency'];
            }

            foreach ($hotels as $i => $hIt) {
                if ($hotelSeg['checkIn'] === $hIt['checkIn'] && $hotelSeg['checkOut'] === $hIt['checkOut']) {
                    $hotels[$i]['travellers'] = array_merge($hIt['travellers'], $hotelSeg['travellers']);
                    $hotels[$i]['cancellations'] = array_merge($hIt['cancellations'], $hotelSeg['cancellations']);
                    $hotels[$i]['accounts'] = array_merge($hIt['accounts'], $hotelSeg['accounts']);

                    $hotels[$i]['guests'] += $hotelSeg['guests'];
                    $hotels[$i]['kids'] += $hotelSeg['kids'];
                    // Room
                    $hotels[$i]['rooms'] = array_merge($hIt['rooms'], $hotelSeg['rooms']);

                    $hotels[$i]['spentAwards'] = implode(' + ', array_filter([$hIt['spentAwards'], $hotelSeg['spentAwards']]));

                    if ($hotelSeg['currency'] === $hIt['currency']) {
                        if ($hIt['cost'] !== null && $hotelSeg['cost'] !== null) {
                            $hotels[$i]['cost'] += $hotelSeg['cost'];
                        }

                        if ($hIt['tax'] !== null && $hotelSeg['tax'] !== null) {
                            $hotels[$i]['tax'] += $hotelSeg['tax'];
                        }

                        if ($hIt['total'] !== null && $hotelSeg['total'] !== null) {
                            $hotels[$i]['total'] += $hotelSeg['total'];
                        }
                    }

                    continue 2;
                }
            }
            $hotels[] = $hotelSeg;
        }

        foreach ($hotels as $hotel) {
            $h = $email->add()->hotel();

            // General
            $h->general()
                ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number -'))}]",
                    null, true, "/{$this->opt($this->t('Confirmation Number -'))}\s*([A-Z\d]{5,})\s*$/"))
                ->travellers(array_unique($hotel['travellers']), true)
                ->cancellation(implode("\n", array_unique($hotel['cancellations'])));
            // Program
            if (!empty(array_filter($hotel['accounts']))) {
                $h->program()
                    ->accounts($hotel['accounts'], false);
            }

            // Hotel
            $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('NAME'))}]/preceding::text()[{$this->starts($this->t('Thank you for choosing'))}][{$this->contains($this->t('The safety and well-being'))}][1]",
                null, true,
                "/{$this->opt($this->t('Thank you for choosing'))}\s*(.+?)\.\s*{$this->opt($this->t('The safety and well-being'))}/");
            $h->hotel()
                ->name($name)
                ->noAddress();

            // Booked
            $h->booked()
                ->checkIn($hotel['checkIn'])
                ->checkOut($hotel['checkOut'])
                ->guests($hotel['guests'])
                ->kids($hotel['kids']);

            // Room
            foreach ($hotel['rooms'] as $room) {
                $h->addRoom()
                    ->setType($room['setType'])
                    ->setRateType($room['setRateType'])
                    ->setRate($room['setRate'], true, true);
            }

            // Price
            if (!empty($hotel['spentAwards'])) {
                $h->price()
                    ->spentAwards($hotel['spentAwards']);
            }

            if ($hotel['cost'] !== null) {
                $h->price()
                    ->cost($hotel['cost']);
            }
            $h->price()
                ->tax($hotel['tax'])
                ->total($hotel['total'])
                ->currency($hotel['currency'])
            ;

            $this->detectDeadLine($h);
        }

        return true;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
            preg_match("/Reservation may be cancelled for no charge upto (?<prior>\d+ days?) prior to the check in date by (?<hour>\d+ [ap]m) hotel time\./i", $cancellationText, $m)
            || preg_match("/Free cancellation (?<prior>\d+ days?) prior to arrival hotel local time\./i", $cancellationText, $m)
        ) {
            $m['hour'] = !empty($m['hour']) ? preg_replace("/^\s*(\d+)\s*([ap]m)\s*$/", '$1:00 $2', $m['hour']) : null;
            $h->booked()
                ->deadlineRelative($m['prior'], $m['hour']);
        }
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

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            //            // Apr 09
            //            '/^\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1:43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$2 $1 %year%',
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
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
