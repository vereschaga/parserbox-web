<?php

namespace AwardWallet\Engine\limo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "limo/it-139433435.eml, limo/it-140185982.eml, limo/it-654175621.eml, limo/it-655205607.eml";

    public $dateFormat; // 'dmy' or 'mdy'
    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Passenger:'    => ['Passenger:', 'Client:', 'Guest:'],
            'cancelledText' => ['Cancelled Reservation Confirmation', 'This reservation has been cancelled'],
        ],
    ];

    private $detectBody = [
        'en' => [
            'Trip Routing Information:',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match("/Conf# \d+ For .* \[ *[\d\\/]+ *- *\d+[:.]\d+.*?\]/", $headers['subject'])) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[{$this->contains(['.mylimobiz.com', 'mylimowebsite.com'], '@src')}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->contains('Ascend in Motion')}]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->eq($detectBody)}]")->length > 0) {
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
        // date format
        $modDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Last Modified On:"))}]");

        if (preg_match("/:\s*(\d+)\\/(\d+)\\/(\d{4})\b/", $modDate, $m)) {
            if ($m[1] > 12 && $m[2] <= 12) {
                $this->dateFormat = 'dmy';
            }

            if ($m[1] <= 12 && $m[2] > 12) {
                $this->dateFormat = 'mdy';
            }
        }

        $t = $email->add()->transfer();

        // General
        $t->general()
            ->confirmation($this->http->FindSingleNode("//td[not(.//td)][{$this->contains($this->t("Reservation Confirmation #"))}]",
                null, true, "/{$this->opt($this->t("Reservation Confirmation #"))}\s*(\d{5,})\s*$/"))
            ->traveller($this->nextTd($this->t("Passenger:"), "/^\s*(.+?)(?:\s*\(.*)?\s*$/"), true)
        ;

        $type = $this->nextTd($this->t("ServiceType:"));

        if (in_array($type, ['Wedding', 'Domestic Airport Arrival Greet']) && !empty($t->getConfirmationNumbers())) {
            $email->removeItinerary($t);
            $email->setIsJunk(true);

            return $email;
        }

        if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("cancelledText")) . "])[1]"))) {
            $t->general()
                ->cancelled()
                ->status('Cancelled');
        }

        // Price
        $total = $this->nextTd($this->t("Reservation Total:"));

        if (!$t->getCancelled() && (
            preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        )) {
            $t->price()
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency'])
            ;
        } elseif (!$t->getCancelled() && (
            preg_match("#^\s*(?<amount>\d[\d\., ]*?)\s*$#", $total, $m)
        )) {
            $t->price()
                ->total(PriceHelper::parse($m['amount']))
            ;
        }

        $pudate = $this->normalizeDate($this->nextTd($this->t("Pick-up Date:"), "/^(.+?)\s*(?:Add to .*)?$/"));
        $putime = $this->nextTd($this->t("Pick-up Time:"));
        // 01:05 PM / 13:05 -> 13:05
        $putime = preg_replace("/^\s*\d{1,2}:\d{2}\s*[ap]m\s*\/\s*(\d{1,2}:\d{2})\s*$/i", '$1', $putime);
        $dotime = $this->nextTd($this->t("Estimated Drop-off Time:"));

        $startDate = null;

        if (!empty($pudate) && !empty($putime)) {
            $startDate = strtotime($putime, $pudate);
        }

        $endDate = null;

        if (!empty($dotime) && !empty($pudate)) {
            $endDate = strtotime($dotime, $pudate);

            if (!empty($startDate) && !empty($endDate) && $endDate < $startDate) {
                $endDate = strtotime("+1day", $endDate);
            }
        }

        $guests = $this->nextTd($this->t("No. of Pass:"));
        $carType = $this->nextTd($this->t("Vehicle Type:"));

        $tripText = implode("\n",
            $this->http->FindNodes("//td[not(.//td)][{$this->eq($this->t("Trip Routing Information:"))}]/following-sibling::td[normalize-space()][1]//text()[normalize-space()]"));

        $rows = $this->split("/(?:^|\n)\s*((?:PU:|ST:|DO:|WT:)\s+)/", $tripText);

        foreach ($rows as $i => $row) {
            if (preg_match("/^\s*(?:WT|ST)\s*:\s*--\s*:/", $row)) {
                unset($rows[$i]);

                continue;
            }
            $rows[$i] = preg_replace("/\s+Notes:\s+[\s\S]+/", '', $row);
        }

        $rows = array_values($rows);

        foreach ($rows as $i => $row) {
            if ($i == count($rows) - 1) {
                break;
            }

            $s = $t->addSegment();

            // Departure
            $r = $this->parseRow($row);

            if (!empty($r['code'])) {
                $s->departure()
                    ->code($r['code']);
            } else {
                if (strlen(preg_replace('/\D/', '', $r['address'])) < 3) {
                    $s->departure()
                        ->name($r['address']);
                } else {
                    $s->departure()
                        ->address($r['address']);
                }
            }
            $time = $r['time'];

            if ($i === 0 && !empty($startDate)) {
                $s->departure()
                    ->date($startDate);
            } elseif (!empty($pudate) && !empty($time)) {
                $s->departure()
                    ->date(strtotime($time, $pudate));
            } else {
                $s->departure()
                    ->noDate();
            }

            // Arrival
            $r = $this->parseRow($rows[$i + 1] ?? '');

            if (!empty($r['code'])) {
                $s->arrival()
                    ->code($r['code']);
            } else {
                if (strlen(preg_replace('/\D/', '', $r['address'])) < 3) {
                    $s->arrival()
                        ->name($r['address']);
                } else {
                    $s->arrival()
                        ->address($r['address']);
                }
            }
            $time = $r['time'];

            if (($i + 1 === count($rows) - 1) && !empty($endDate)) {
                $s->arrival()
                    ->date($endDate);
            } elseif (!empty($pudate) && !empty($time)) {
                $s->arrival()
                    ->date(strtotime($time, $pudate));
            } elseif (!empty($pudate) && !empty($r['flightTime'])
                && ((!empty($s->getDepCode()) && $s->getDepCode() === $s->getArrCode())
                    || (!empty($s->getDepName()) && $s->getDepName() === $s->getArrName())
                    || !empty($s->getDepDate()) && strtotime($r['flightTime'], $pudate) - $s->getDepDate() > 60 * 60 * 6) // 6 hour
            ) {
                $ft = strtotime('- 2 hours', strtotime($r['flightTime'], $pudate));

                if (empty($s->getDepDate()) || $ft > $s->getDepDate()) {
                    $s->arrival()
                        ->date($ft);
                } else {
                    $s->arrival()
                        ->noDate();
                }
            } else {
                $s->arrival()
                    ->noDate();
            }

            // Extra
            $s->extra()
                ->type($carType, true, true)
                ->adults($guests)
            ;
        }

        return true;
    }

    private function parseRow($row)
    {
        $result = [
            'code'          => null,
            'address'       => null,
            'time'          => null,
            'flightTime'    => null,
        ];

        if (preg_match("/^\s*\w+\s*:\s*([^\d:]+|\d{1,2}:\d+[^\d:]*)\s*:\s*(.+)/", $row, $m)) {
            // "PU: -- : JIA - Jacksonville International Airport / UA - United Airlines , Flt# 506" - JIA not iata code
            // if (preg_match("/^([A-Z]{3}) - /", $m[2], $mat)) {
            //     $result['code'] = $mat[1];
            // }
            $result['address'] = preg_replace('/^\s*([^,]+?)\s*,?\s*\/\s*[A-Z\d]{2} .+Flt# .+/', '$1', $m[2]);
            $result['address'] = preg_replace('/^\s*([^,]+? airport)\s*,?\s*\/\s*[A-Z\d]{2} - .+/i', '$1', $result['address']);

            if (preg_match("/^\d+:\d+/", $m[1])) {
                $result['time'] = $m[1];
            }

            if (preg_match("/ETA\/ETD: *(\d{1,2}:\d{2} *(?:[APap][Mm])?|\d{1,2}:\d{2}(?::\d{2})?)\s*\W?\s*$/", $m[2], $mat)) {
                $result['flightTime'] = $mat[1];
            }
        }

        return $result;
    }

    private function nextTd($name, $regexp = null)
    {
        return $this->http->FindSingleNode("//td[not(.//td)][{$this->eq($name)}]/following-sibling::td[normalize-space()][1]", null, true, $regexp);
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
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        if (preg_match("/^\s*(\d{2}\\/\d{2}\\/\d{4})\s*-\s*([[:alpha:]]+)\s*$/iu", $date, $m)) {
            if ($this->dateFormat == 'dmy') {
                $date = str_replace("/", '.', $m[1]);

                return strtotime($date);
            } elseif ($this->dateFormat == 'mdy') {
                $date = $m[1];

                return strtotime($date);
            } else {
                $w = WeekTranslate::number1($m[2]);
                $date1 = strtotime($m[1]);
                $date2 = strtotime(str_replace("/", '.', $m[1]));

                if (!empty($date1) && empty($date2) && $w == date("w", $date1)) {
                    $date = $m[1];
                    $this->dateFormat = 'mdy';

                    return strtotime($date);
                }

                if (empty($date1) && !empty($date2) && $w == date("w", $date2)) {
                    $this->dateFormat = 'dmy';
                    $date = str_replace("/", '.', $m[1]);

                    return strtotime($date);
                }
                $w1 = date("w", $date1);
                $w2 = date("w", $date2);

                if ($w === $w1) {
                    $date = $m[1];
                    $this->dateFormat = 'mdy';

                    return strtotime($date);
                }

                if ($w === $w2) {
                    $this->dateFormat = 'dmy';
                    $date = str_replace("/", '.', $m[1]);

                    return strtotime($date);
                }
                $date = $m[1];
            }
        }

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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
