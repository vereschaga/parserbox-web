<?php

namespace AwardWallet\Engine\limo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "limo/it-138087190.eml, limo/it-138104974.eml, limo/it-141369373.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $dateFormat; // 'dmy' or 'mdy'
    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Passenger:'    => ['Passenger:', 'Client:'],
            'cancelledText' => ['Cancelled Reservation Confirmation', 'This reservation has been cancelled'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                $this->parseEmailPdf($email, $text);
            }
        }

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
        // TODO check count types
        return count(self::$dictionary);
    }

    public function detectPdf($text)
    {
        if (
            stripos($text, "Trip Routing Information:") !== false
            && stripos($text, "ServiceType:") !== false
            && stripos($text, "Vehicle Type:") !== false
        ) {
            return true;
        }

        return false;
    }

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
//        $this->logger->debug('Pdf text = ' . print_r($textPdf, true));

        // date format
        if (preg_match("/{$this->opt($this->t("Last Modified On:"))} *(\d+)\\/(\d+)\\/(\d{4})\b/", $textPdf, $m)) {
            if ($m[1] > 12 && $m[2] <= 12) {
                $this->dateFormat = 'dmy';
            }

            if ($m[1] <= 12 && $m[2] > 12) {
                $this->dateFormat = 'mdy';
            }
        }

        // Transfer
        $t = $email->add()->transfer();

        // General
        $t->general()
            ->confirmation($this->re("/\n *(?:\w+ )?{$this->opt($this->t("Reservation Confirmation #"))} {0,5}(\d{4,})\s+/", $textPdf))
            ->traveller($this->re("/\n *{$this->opt($this->t("Passenger:"))} *(.+)\s+/", $textPdf), true)
        ;
        $type = $this->re("/\n *{$this->opt($this->t("ServiceType:"))} *(.+)/", $textPdf);

        if (in_array($type, ['Wedding', 'Domestic Airport Arrival Greet']) && !empty($t->getConfirmationNumbers())) {
            $email->removeItinerary($t);
            $email->setIsJunk(true);

            return $email;
        }

        if (preg_match("/" . $this->opt($this->t("cancelledText")) . "/", $textPdf)) {
            $t->general()
                ->cancelled()
                ->status('Cancelled');
        }

        // Price
        $total = $this->re("/\n *{$this->opt($this->t("Reservation Total:"))} *(.+)/", $textPdf);

        if (!$t->getCancelled() && (
            preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        )) {
            $t->price()
                ->total(PriceHelper::parse($m['amount']))
                ->currency($m['currency'])
            ;
        }

        $pudate = $this->normalizeDate($this->re("/\n *{$this->opt($this->t("Pick-up Date:"))} *(.+)/", $textPdf));
        $putime = $this->re("/\n *{$this->opt($this->t("Pick-up Time:"))} *(.+)/", $textPdf);
        $dotime = $this->re("/\n *{$this->opt($this->t("Estimated Drop-off Time:"))} *(.+)/", $textPdf);
        $guests = $this->re("/\n *{$this->opt($this->t("No. of Pass:"))} *(.+)/", $textPdf);
        $carType = $this->re("/\n *{$this->opt($this->t("Vehicle Type:"))} *(.+)/", $textPdf);

        $tripText = $this->re("/\n *{$this->opt($this->t("Trip Routing Information:"))}\s+(.+(?:\n* {20,}.*)+)\n\n/", $textPdf);

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
                $s->departure()
                    ->address($r['address']);
            }
            $time = $r['time'];

            if ($i === 0 && !empty($putime)) {
                $time = $putime;
            }

            if (!empty($pudate) && !empty($time)) {
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
                $s->arrival()
                    ->address($r['address']);
            }
            $time = $r['time'];

            if ($i === count($rows) - 2 && !empty($dotime)) {
                $time = $dotime;
            }

            if (!empty($pudate) && !empty($time)) {
                $s->arrival()
                    ->date(strtotime($time, $pudate));
            } else {
                $s->arrival()
                    ->noDate();
            }

            // Extra
            $s->extra()
                ->type($carType)
                ->adults($guests)
            ;
        }

        return $email;
    }

    private function parseRow($row)
    {
        $result = [
            'code'    => null,
            'address' => null,
            'time'    => null,
        ];

        if (preg_match("/^\s*\w+\s*:\s*([^\d:]+|\d{1,2}:\d+[^\d:]*)\s*:\s*(.+)/", $row, $m)) {
            if (preg_match("/^([A-Z]{3}) - /", $m[2], $mat)) {
                $result['code'] = $mat[1];
            } else {
                $result['address'] = $m[2];
            }

            if (preg_match("/^\d+:\d+/", $m[1])) {
                $result['time'] = $m[1];
            }
        }

        return $result;
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

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
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
