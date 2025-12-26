<?php

namespace AwardWallet\Engine\qantas\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PrepareChanged extends \TAccountChecker
{
    public $mailFiles = "qantas/it-10174166.eml, qantas/it-10195145.eml, qantas/it-5334509.eml, qantas/it-5413530.eml, qantas/it-562869151.eml, qantas/it-5913954.eml";
    public $reFrom = "@yourbooking.qantas.com.au";
    public $reBody = [
        "enImgAlt"  => "Prepare for your flight",
        "enImgAlt1" => "Your Flight Has Changed",
        "enImgAlt2" => "had to make some changes to your flight",
        "enImgAlt3" => "Your flight has changed",
        "enImgAlt4" => "Your flight time has changed",
        "jaImgAlt"  => "ご搭乗便に関する変更のお知らせ",
    ];
    public $reSubject = [
        "en"  => "Your Flight has been changed",
        "en1" => "Prepare for your flight",
        "en2" => "We've had to make some changes to your flight",
        "en3" => "We've had to make some changes to your booking",
        'Your flight time has changed for', // + ja
    ];

    public static $dictionary = [
        "en" => [
            'reservation' => ['Your booking reference is', 'Your Qantas booking reference is', 'Booking reference:'],
            'Details'     => ['Flight Details', 'New Flight'],
            // 'Passengers' => '',
            // 'Date' => '',
            // 'Flight' => '',
            // 'From' => '',
            // 'To' => '',
            // 'Terminal' => '',
            // 'Travel Class' => '',
        ],
        "ja" => [
            'reservation' => ['お客様の予約番号：'],
            'Details'     => ['変更後のフライト'],
            'Passengers'  => '搭乗者',
            'Date'        => '日付',
            'Flight'      => 'フライト',
            'From'        => '出発地',
            'To'          => '到着地',
            // 'Terminal' => '',
            // 'Travel Class' => '',
        ],
    ];

    public $lang = "en";

    public function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        $travellers = array_filter(array_unique($this->http->FindNodes("(.//td[not(.//td)][{$this->eq($this->t('Passengers'))}])[1]/following-sibling::td[normalize-space(.)][1]//table[count(descendant::table)=0]//tr[1]")));

        if (count($travellers) == 0) {
            $travellers = array_filter(array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::table[1]/descendant::a[normalize-space()='Add']/preceding::text()[normalize-space()][1]")));
        }

        if (count($travellers) == 0) {
            $travellers[] = $this->http->FindSingleNode("(.//td[not(.//td)][{$this->eq($this->t('Passengers'))}])[1]/following-sibling::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)][1]");
        }

        $f->general()
            ->travellers(preg_replace("/^(MS|MR|MRS|MISS|Dr|Prof|Mstr) /i", '', $travellers));

        $accounts = array_filter(array_unique($this->http->FindNodes("(.//td[not(.//td)][{$this->eq($this->t('Passengers'))}])[1]/following-sibling::td[normalize-space(.)][1]//table[count(descendant::table)=0]//tr[2]")));

        if (count($accounts) == 0) {
            $accounts[] = $this->http->FindSingleNode("(.//td[not(.//td)][{$this->eq($this->t('Passengers'))}])[1]/following-sibling::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)][2]");
        }

        if (count(array_filter($accounts)) > 0) {
            $f->setAccountNumbers($accounts, false);
        }

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('reservation'))}]/following::text()[string-length(normalize-space(.))>4][1]"));

        $xpath = "//text()[{$this->contains($this->t('Details'))}]/ancestor::table[1]/descendant::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->getField($this->t("Date"), $root));

            if (preg_match("#([A-Z\d]{2})\s*(\d{1,4})#", $this->getField($this->t("Flight"), $root), $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (preg_match("#^\s*(.+?)\s*(\d+:\d+\s*[AP]M|$)#i", $this->getField($this->t("From"), $root), $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m[1]);

                if (!empty($m[2])) {
                    $s->departure()
                        ->date(strtotime($m[2], $date));
                } else {
                    $s->departure()
                        ->noDate();
                }
            }

            if (preg_match("#^\s*(.+?)\s*(\d+:\d+\s*[AP]M|$)#i", $this->getField($this->t("To"), $root), $m)) {
                $s->arrival()
                    ->noCode()
                    ->name($m[1]);

                if (!empty($m[2])) {
                    $s->arrival()
                        ->date(strtotime($m[2], $date));

                    if (preg_match("#\+(\d+)$#", $this->getField($this->t("To"), $root), $m)) {
                        $dayCount = $m[1];
                        $s->arrival()
                            ->date(strtotime("+$dayCount day", $s->getArrDate()));
                    }
                } else {
                    $s->arrival()
                        ->noDate();
                }
            }

            if ($s->getNoDepDate() == true && $s->getNoArrDate() == true) {
                $this->logger->error("DepDate && ArrDate missing");

                return $email;
            }

            $depTerminal = $this->getField($this->t("Terminal"), $root);

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $cabin = $this->getField($this->t("Travel Class"), $root);

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@src='http://www.qantas.com.au/img/email/pdc/red-corner.png' or @src='http://www.qantas.com/img/email/pdc/red-corner.png' or @alt='Qantas']")->length > 0) {
            foreach ($this->reBody as $re) {
                if ($this->http->XPath->query("//img[@alt='{$re}'] | //text()[contains(.,'{$re}')]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*e-ticket.*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            return null; //exclude intersection at parsing emails like qantas/it-3232794.eml
        }

        $this->http->FilterHTML = false;

        $this->lang = "";

        foreach (self::$dictionary as $lang => $re) {
            if (!is_array($re['reservation'])) {
                if (strpos($this->http->Response["body"], $re['reservation']) !== false) {
                    $this->lang = $lang;
                }
            } else {
                foreach ($re['reservation'] as $r) {
                    if (strpos($this->http->Response["body"], $r) !== false) {
                        $this->lang = $lang;

                        break;
                    }
                }
            }

            if (!empty($this->lang)) {
                break;
            }
        }

        $this->parseHtml($email);

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

    private function normalizeDate($str)
    {
        // $this->logger->debug('$date = '. $str);
        $in = [
            // 10月25日2023
            "#^\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*(\d{4})\s*$#",
        ];
        $out = [
            "$3-$1-$2",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str = '. $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function getField($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td)][{$this->eq($field)}])[{$n}]/following-sibling::td[1]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
}
