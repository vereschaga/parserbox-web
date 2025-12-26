<?php

namespace AwardWallet\Engine\limo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class PaymentReceipt extends \TAccountChecker
{
    public $mailFiles = "limo/it-138020097.eml, limo/it-140343358.eml, limo/it-140546383.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            //            'Reservation Confirmation #' => 'Reservation Confirmation #',
            'Passenger:' => ['Customer:', 'Passenger:'],
        ],
    ];

    private $detectBody = [
        'en' => [
            'Routing Information:',
        ],
    ];

    // Main Detects Methods
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
            ->confirmation($this->nextTd($this->t("Trip Confirmation#"), "/\b\d{5,}\b/"))
            ->traveller(preg_replace('/\s*\/\/.*/', '', $this->nextTd($this->t("Passenger:"))), true)
        ;

        // Price
        $total = $this->nextTd($this->t("Reservation Total:"));

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $t->price()
                ->total(PriceHelper::parse($m['amount']))
                ->currency($m['currency'])
            ;
        }
        $duration = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Per Hour"))}]", null, true,
            "/{$this->opt($this->t("Per Hour"))}.*\s+(\d+[\d\.]*) x \d+[\d ,.]*$/");

        // Departure
        $locations = array_filter($this->http->FindNodes("//text()[{$this->starts(["Pick-up Location:", "Drop-off Location:", 'Stop:', 'Wait:'])}]/following::text()[normalize-space()][1][not({$this->starts(['As directed,'])})]"));
        $startDate = $this->normalizeDate($this->nextTd($this->t("Trip Date & Time:")));

        foreach ($locations as $i => $row) {
            if (preg_match("/^\s*AS DIRECTED/", $row)) {
                unset($locations[$i]);

                continue;
            }
        }
        $locations = array_values($locations);

        foreach ($locations as $i => $row) {
            if ($i == count($locations) - 1) {
                break;
            }

            $s = $t->addSegment();

            // Departure
            if (preg_match("/^([A-Z]{3})\s*(?:,|$)/", $row, $m)) {
                $s->departure()
                    ->code($m[1]);
            } else {
                $s->departure()
                    ->address(preg_replace("/ - Ph:[\d \(\)\-\+]+$/", '', $row))
                ;
            }

            if ($i == 0) {
                $s->departure()
                    ->date($startDate);
            } else {
                $s->departure()
                    ->noDate();
            }

            // Arrival
            $rowNext = $locations[$i + 1] ?? '';

            if (preg_match("/^([A-Z]{3})\s*(?:,|$)/", $rowNext, $m)) {
                $s->arrival()
                    ->code($m[1]);
            } else {
                $s->arrival()
                    ->address(preg_replace("/ - Ph:[\d \(\)\-\+]+$/", '', $rowNext));
            }

            if (($i + 1 == count($locations) - 1) && !empty($duration) && $startDate) {
                (int) (str_replace(',', '.', $duration) * 60.0) . ' minutes';
                $s->arrival()
                    ->date(strtotime("+" . ((int) (str_replace(',', '.', $duration) * 60.0)) . ' minutes', $startDate));
            } else {
                $s->arrival()
                    ->noDate();
            }
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
            //            // 10/28/2021 @ 03:30 PM
            '/^\s*(\d{2}\\/\d{2}\\/\d{4})\s+@\s+(\d{1,2}:\d{2}\s*([ap]m)?)\s*$/iu',
        ];
        $out = [
            '$1, $2',
        ];

        $date = preg_replace($in, $out, $date);

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
