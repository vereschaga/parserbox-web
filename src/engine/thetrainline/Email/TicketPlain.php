<?php

namespace AwardWallet\Engine\thetrainline\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TicketPlain extends \TAccountChecker
{
    public $mailFiles = "thetrainline/it-53084864.eml, thetrainline/it-53234312.eml, thetrainline/it-53538151.eml";

    public $reFrom = ["trainline.eu"];
    public $reBody = [
        'en' => ['Your ticket purchased on the'],
    ];
    public $reSubject = [
        'Your ticket for',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Passenger'         => 'Passenger',
            'Carriage'          => 'Carriage',
            'Booking reference' => 'Booking reference',
        ],
    ];
    private $keywordProv = 'Trainline';
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        if (!$this->assignLang($this->http->Response['body'])) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.trainline.eu')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang($this->http->Response['body']);
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers["subject"]) > 0)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $dateRes = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your ticket purchased on the'))}]",
            null, false,
            "/{$this->opt($this->t('Your ticket purchased on the'))}\s*(.+)\s+{$this->t('is confirmed')}/"));

        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your ticket purchased on the'))}]",
            null, false,
            "/{$this->opt($this->t('Your ticket purchased on the'))}\s*.+\s+({$this->t('is confirmed')})/");

        $r = $email->add()->train();
        // general
        $r->general()
            ->date($dateRes)
            ->status($status)
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference:'))}]",
                null, false, "/{$this->opt($this->t('Booking reference:'))}\s*(.+)/"));

        $traveller = [];

        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";
        $xpath = "//text()[{$ruleTime}][./following::text()[normalize-space()!=''][1][{$ruleTime}]]";
        $roots = $this->http->XPath->query($xpath);
        $this->logger->debug($xpath);
        $prevDate = null;

        foreach ($roots as $root) {
            $s = $r->addSegment();

            $traveller[] = $this->http->FindSingleNode("./following::text()[normalize-space()!=''][position()=3][{$this->contains($this->t('Passenger:'))}]",
                $root, false, "/{$this->opt($this->t('Passenger:'))}\s+(.+)/");

            $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::text()[normalize-space()!=''][1]",
                $root));

            if ($date) {
                $prevDate = $date;
            } elseif ($prevDate) {
                $date = $prevDate;
            }

            // departure
            $dep = $this->http->FindSingleNode(".", $root);

            if (preg_match("/\b(\d+:\d+)\s+(.+)$/", $dep, $m)) {
                $s->departure()
                    ->date(strtotime($m[1], $date))
                    ->name($m[2]);
            }
            // arrival
            $arr = $this->http->FindSingleNode("./following::text()[normalize-space()!=''][1]", $root);

            if (preg_match("/\b(\d+:\d+)\s+(.+)$/", $arr, $m)) {
                $s->arrival()
                    ->date(strtotime($m[1], $date))
                    ->name($m[2]);
            }

            // extra
            $trainNum = $this->http->FindSingleNode("./following::text()[normalize-space()!=''][2]", $root);

            if (preg_match("/^•?\s*(.+)\s+(\d+)$/", $trainNum, $m)) {
                $s->extra()
                    ->type($m[1])
                    ->number($m[2]);
            }
            $seat = $this->http->FindSingleNode("./following::text()[normalize-space()!=''][4][{$this->contains($this->t('Carriage'))}]",
                $root);

            if (preg_match("/{$this->t('Carriage')}\s+(\d+),\s*{$this->t('seat')}\s+(\d+)/i", $seat, $m)) {
                $s->extra()
                    ->car($m[1])
                    ->seat($m[2]);
            }
            $cabin = $this->http->FindSingleNode("//text()[contains(.,'→') and {$this->starts($s->getDepName())}]/following::text()[normalize-space()!=''][1][{$this->starts($this->t('Fare:'))}]",
                null, false, "/{$this->t('Fare:')}\s+(.+)/");

            if (empty($cabin)) {// it-
                $cabin = $this->http->FindSingleNode("//text()[contains(.,'→') and {$this->contains($s->getArrName())}]/following::text()[normalize-space()!=''][1][{$this->starts($this->t('Fare:'))}]",
                    null, false, "/{$this->t('Fare:')}\s+(.+)/");
            }

            if (!empty($cabin)) {
                $s->extra()->cabin($cabin);
            }
        }
        $prices = $this->http->FindNodes("//text()[{$this->starts($this->t('Price:'))}]",
            null, "/{$this->opt($this->t('Price:'))}\s*(.+)/");
        $total = 0.0;
        $currency = null;

        foreach ($prices as $price) {
            $sum = $this->getTotalCurrency($price);
            $total += $sum['Total'];
            $currency = $sum['Currency'];
        }
        $r->price()
            ->total($total)
            ->currency($currency);

        $traveller = array_unique($traveller);

        if (!empty($traveller)) {
            $r->general()->travellers($traveller, false);
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //27 January at 17:27
            '#^(\d+)\s+(\w+)\s+at\s+(\d+:\d+)$#u',
            //Friday, 31 January 2020:
            '#^\w+, (\d+)\s+(\w+)\s+(\d{4}):?$#u',
        ];
        $out = [
            '$1 $2 ' . $year . ', $3',
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
        $str = EmailDateHelper::parseDateRelative($str, $this->date);

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Passenger"], $words["Booking reference"])) {
                if ($this->stripos($body, $words["Passenger"]) && $this->stripos($body, $words["Booking reference"])) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "₹"], ["EUR", "GBP", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#^(?<c>[^\s\d])\s*(?<t>\d[\.\d\,\s]*\d*)$#", trim($node), $m)
            || preg_match("#^(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[^\s\d])$#", trim($node), $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
