<?php

namespace AwardWallet\Engine\pof\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "pof/it-29998970.eml, pof/it-30090317.eml, pof/it-30969804.eml, pof/it-30979856.eml";

    public $reFrom = ["@poferries.com"];
    public $reBody = [
        'en' => ['BOOKING CONFIRMATION', 'OUTBOUND'],
    ];
    public $reSubject = [
        '/Booking confirmation \d+/',
        '/Order Confirmation \d+/',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [],
    ];
    private $keywordProv = 'P&O Ferries';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!$this->parseEmail($email)) {
            return null;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.poferries.com')] | //img[contains(@src,'.poferries.com')]")->length > 0) {
            return $this->assignLang();
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
                if (($fromProv && (preg_match($reSubject, $headers["subject"]) > 0))
                    || stripos($headers["subject"], $this->keywordProv) !== false
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
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('YOUR BOOKING NUMBER'))}]/following::text()[normalize-space()!=''][1]"));
        $sum = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL'))}]/following::text()[normalize-space()!=''][1]"));

        if (!empty($sum['Total'])) {
            $email->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
        }
        $xpath = "//text()[{$this->starts($this->t('DEPART'))}]/ancestor::table[1]";
        $reservations = $this->http->XPath->query($xpath);

        if ($reservations->length > 2) {
            $this->logger->debug("other format resevations");

            return false;
        }

        foreach ($reservations as $i => $reservation) {
            $r = $email->add()->ferry();
            $r->general()
                ->noConfirmation();
            $num = $i + 1;
            $sum = $this->getTotalCurrency($this->http->FindSingleNode("(//text()[{$this->starts($this->t('TICKET TYPE'))}]/ancestor::table[1])[$num]/descendant::text()[normalize-space()!=''][3]"));

            if (!empty($sum['Total'])) {
                $r->price()
                    ->cost($sum['Total'])
                    ->currency($sum['Currency']);
            }
            $s = $r->addSegment();
            $s->extra()
                ->vessel($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t(' to '))}]/preceding::text()[normalize-space()!=''][1]",
                    $reservation));
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t(' to '))}]",
                $reservation);

            if (preg_match("/(.+){$this->opt($this->t(' to '))}(.+)/", $node, $m)) {
                $s->departure()
                    ->name($m[1]);
                $s->arrival()
                    ->name($m[2]);
            }
            $date = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t(' to '))}]/following::text()[normalize-space()!=''][1]",
                $reservation));
            $node = $this->http->FindSingleNode("./descendant::text()[({$this->starts($this->t('DEPART'))}) and ({$this->contains($this->t('ARRIVE'))})]",
                $reservation);

            if (preg_match("/{$this->opt($this->t('DEPART'))}[ :]+(\d+:\d+)\s+{$this->opt($this->t('ARRIVE'))}[ :]+(\d+:\d+)/",
                $node, $m)) {
                $s->departure()
                    ->date(strtotime($m[1], $date));
                $s->arrival()
                    ->date(strtotime($m[2], $date));
            }

            $node = implode("\n",
                $this->http->FindNodes("(//text()[{$this->starts($this->t('PASSENGER & VEHICLE DETAILS'))}]/ancestor::table[1])[{$num}]/descendant::text()[normalize-space()!='']"));
            $pax = array_map(function ($s) {
                return $this->re("/(.+?)\s*(?:\(|$)/", $s);
            }, explode("\n",
                $this->re("/{$this->opt($this->t('PASSENGERS:'))}\s+(.+?)\s+{$this->opt($this->t('VEHICLE:'))}/s",
                    $node)));
            $r->general()
                ->travellers($pax);

            $vehicle = preg_replace("/\s+/", ' ', $this->re("/{$this->opt($this->t('VEHICLE:'))}\s+(.+)/s", $node));

            if (preg_match("/(.*?{$this->opt($this->t('Car'))})\s*(.+?) h\s*\&\s*(.+) l(?:\))?$/", $vehicle, $m)) {
                $v = $s->addVehicle();
                $v->setType($m[1])
                    ->setHeight($m[2])
                    ->setLength($m[3]);
            }

            $node = implode("\n",
                $this->http->FindNodes("./following::table[({$this->contains($this->t('PASSENGERS:'))}) and ({$this->contains($this->t('VEHICLE:'))})][1]/descendant::text()[{$this->eq($this->t('PASSENGERS:'))}][{$num}]/ancestor::table[1]/descendant::text()[normalize-space()!='']",
                    $reservation));
            $adult = (int) $this->re("/(\d+)[x ]+{$this->opt($this->t('Adult'))}/", $node);

            if (!empty($adult)) {
                $s->booked()->adults($adult);
            }
            $kids = (int) $this->re("/(\d+)[x ]+{$this->opt($this->t('Child'))}/", $node);
            $infant = (int) $this->re("/(\d+)[x ]+{$this->opt($this->t('Infant'))}/", $node);
            $kids += $infant;

            if (!empty($kids)) {
                $s->booked()->kids($kids);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Sat 22 Sep 2018
            '#^\w+\s+(\d+)\s+(\w+)\s+(\d{4})$#u',
            //Thu Aug 25 15:40:00 BST 2016
            '#^[\w\-]+\s+(\w+)\s+(\d+)\s+(\d+:\d+):\d+\s+.+?\s+(\d{4})$#u',
        ];
        $out = [
            '$1 $2 $3',
            '$2 $1 $4, $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
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

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
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
