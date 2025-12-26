<?php

namespace AwardWallet\Engine\limo\Email;

use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class PaymentReceipt2 extends \TAccountChecker
{
    public $mailFiles = "limo/it-143993503.eml, limo/it-144712170.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Passenger:'    => ['Passenger:', 'Client:'],
            'cancelledText' => ['Cancelled Reservation Confirmation', 'This reservation has been cancelled'],
            "PU Time:"      => ["PU Time:", "PU:"],
            "DO Time:"      => ["DO Time:", "DO:"],
        ],
    ];

    private $detectBody = [
        'en' => [
            'Reservation Receipt',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match("/Payment\s+Receipt\s*\[\s*For\s+Conf#\s*\d+\s*\]/", $headers['subject'])) {
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
            ->confirmation($this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t("CONF #"))}] and *[2][{$this->eq($this->t("DATE & TIME(S)"))}]]/following-sibling::tr[normalize-space()][1]/*[1]",
                null, true, "/^\s*(\d{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t("Passenger:"))}]/following::text()[normalize-space()][1]"), true)
        ;

        $pudate = $this->normalizeDate($this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t("CONF #"))}] and *[2][{$this->eq($this->t("DATE & TIME(S)"))}]]/following-sibling::tr[normalize-space()][1]/*[2]/descendant::text()[normalize-space()][1]"));
        $putime = $this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t("CONF #"))}] and *[2][{$this->eq($this->t("DATE & TIME(S)"))}]]/following-sibling::tr[normalize-space()][1]/*[2]//text()[{$this->starts($this->t("PU Time:"))}]/following::text()[normalize-space()][1]");
        $dotime = $this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t("CONF #"))}] and *[2][{$this->eq($this->t("DATE & TIME(S)"))}]]/following-sibling::tr[normalize-space()][1]/*[2]//text()[{$this->starts($this->t("DO Time:"))}]/following::text()[normalize-space()][1]");
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

        $carType = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Vehicle Type:"))}]/following::text()[normalize-space()][1]");

        $tripText = implode("\n",
            $this->http->FindNodes("//tr[*[1][{$this->eq($this->t("CONF #"))}] and *[2][{$this->eq($this->t("DATE & TIME(S)"))}]]/following-sibling::tr[normalize-space()][1]/*[3]//text()[{$this->starts($this->t("PU:"))}]/ancestor::td[1]//text()[normalize-space()]"));

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
                $s->arrival()
                    ->address($r['address']);
            }

            $time = $r['time'];

            if (($i + 1 === count($rows) - 1) && !empty($endDate)) {
                $s->arrival()
                    ->date($endDate);
            } elseif (!empty($pudate) && !empty($time)) {
                $s->arrival()
                    ->date(strtotime($time, $pudate));
            } else {
                $s->arrival()
                    ->noDate();
            }

            // Extra
            $s->extra()
                ->type($carType)
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
