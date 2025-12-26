<?php

namespace AwardWallet\Engine\limo\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmation2 extends \TAccountChecker
{
    public $mailFiles = "limo/it-139590675.eml, limo/it-163549996.eml";

    public $dateFormat = null;
    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            //            'Reservation Confirmation #' => 'Reservation Confirmation #',
        ],
    ];

    private $detectBody = [
        'en' => [
            'Passenger & Routing Information',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match("/Conf# \d+ For .* \[ *[\d\\/]+ *- *\d+[:.]\d+[APMapm ]*\]/", $headers['subject'])) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[{$this->contains(['.mylimobiz.com', 'mylimowebsite.com'], '@src')}]")->length === 0) {
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
        $t = $email->add()->transfer();

        // General
        $t->general()
            ->confirmation($this->nextTd($this->t("Reservation#"), "/^\s*(\d{4,})(?:\s+.*)?$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t("Primary Passenger:"))}]/following::text()[normalize-space()][1]"), true)
        ;

        // Price
        $currency = $this->http->FindSingleNode("//td[not(.//td)][{$this->starts($this->t("Total Due ("))}]",
            null, true, "/\(([A-Z]{3})\)/");

        if (!empty($currency)) {
            $t->price()
                ->total(PriceHelper::parse($this->http->FindSingleNode("//td[not(.//td)][{$this->starts($this->t("Total Due ("))}]/following-sibling::td[normalize-space()][1]"),
                    $currency))
                ->currency($currency);
        }

        $pudate = $this->normalizeDate($this->nextTd($this->t("Pick-up Date:")));
        $putime = $this->nextTd($this->t("Pick-up Time:"));
        $guests = $this->http->FindSingleNode("//tr[not(.//tr)][td[1][{$this->starts($this->t("# of Pax"))}]]/following-sibling::tr[normalize-space()][1]/td[1]",
            null, true, "/^\s*(\d+)\s*$/");
        $carType = $this->http->FindSingleNode("//tr[not(.//tr)][td[3][{$this->starts($this->t("Vehicle Type"))}]]/following-sibling::tr[normalize-space()][1]/td[3]");

        $tripText = implode("\n",
            $this->http->FindNodes("//text()[{$this->starts($this->t("PU:"))}]/ancestor::td[1]//text()[normalize-space()]"));

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

        return true;
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

        $in = [
            //            // 02/25/2022 - Friday
            '/^\s*(\d{2}\\/\d{2}\\/\d{4})\s+-\s+[[:alpha:]]+\s*$/iu',
        ];
        $out = [
            '$1',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date end = ' . print_r( $date, true));

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
