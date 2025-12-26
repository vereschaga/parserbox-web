<?php

namespace AwardWallet\Engine\nsinter\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3714703 extends \TAccountCheckerExtended
{
    public $mailFiles = "nsinter/it-61545320.eml, nsinter/it-7012241.eml";

    public $reBody = [
        "en"  => 'Thank you for choosing NS International',
        "en2" => 'Thank you for buying your train tickets at NS International',
        "en3" => 'We confirm that the following booking has been canceled as requested.',
        "nl"  => 'Bedankt voor uw boeking bij NS International',
        'nl2' => 'Hierbij bevestigen wij de annulering van uw treintickets bij NS International.',
    ];
    public $reFrom = "no-reply@mail.nsinternational.nl";

    public static $dictionary = [
        "en" => [
            'cancelledText' => 'We confirm that the following booking has been canceled as requested.',
        ],
        "nl" => [
            "Booking code" => "Boekingscode",
            "Total price"  => "Totaalprijs",
            "Departure:"   => "Vertrek:",
            "Trainnumber:" => "Treinnummer:",
            "By:"          => "Met:",
            "Arrival:"     => "Aankomst:",
            "Passengers:"  => "Reizigers:",
            "Tariff"       => "Tariefsoort",
            "with tariff"  => "met tarief",
            "Travel time:" => "Reistijd:",
            "Coach"        => "Rijtuig",
            "Seat"         => "Zitplaats",
            "Class:"       => "Comfortklasse:",

            // Cancelled
            'cancelledText'           => 'Hierbij bevestigen wij de annulering van uw treintickets bij NS International.',
            'Dear '                   => 'Beste ',
            'Your booking code (DNR)' => 'Uw boekingscode (DNR)',
            'Ticket ID'               => 'Ticket ID',
            'From'                    => 'Van', // From AMSTERDAM C. to ZONE BRUXELLES/BRUSSEL on 11/8/2020 with NRT ticket (BeNeLux)
            'to'                      => 'naar',
            'on'                      => 'op',
            'with'                    => 'met',
            'Total:'                  => 'Totaal:',

            'D'  => 'V',
            'A'  => 'A',
            'at' => 'om',
        ],
    ];

    public $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->reBody as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        if ($this->http->FindSingleNode("//text()[{$this->contains($this->t('cancelledText'))}]")) {
            $this->parseEmailCancelled($email);
        } else {
            $this->parseEmail($email);
        }

        $email->setType('TrainTrip' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["from"], $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function parseEmailCancelled(Email $email)
    {
        $r = $email->add()->train();
        $r->general()->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, false, "/{$this->opt($this->t('Dear '))}(.+?),/"));
        $r->general()->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking code (DNR)'))}]/following-sibling::*[1]"));
        $r->general()->cancelled();

        $segment = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Ticket ID'))}]/ancestor::tr[1]/preceding-sibling::tr[1]");
        // From AMSTERDAM C. to ZONE BRUXELLES/BRUSSEL on 11/8/2020 with NRT ticket (BeNeLux)
        if (preg_match("/{$this->opt($this->t('From'))} (.+?) {$this->opt($this->t('to'))} (.+?)\s*"
            . "{$this->opt($this->t('on'))} (.+?) {$this->opt($this->t('with'))}/", $segment, $m)) {
            $s = $r->addSegment();
            $s->departure()->name($m[1]);
            $s->arrival()->name($m[2]);
            $s->departure()->date2($this->ModifyDateFormat($m[3]));
        }

        if ($total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total:'))}]/ancestor::td[1]")) {
            $total = $this->amount(preg_replace("/(\d+)\s+(\d{2})$/", "$1,$2", $total));
            $currency = $this->currency($total);

            if (!empty($total) && !empty($currency)) {
                $r->price()
                    ->total($total)
                    ->currency($currency);
            }
        }
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->train();
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking code'))}]/following::text()[normalize-space()!=''][1]"));
        $confNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('PNR(s)'))}]/following::text()[normalize-space()!=''][1]");

        if (empty($confNo) && $this->http->XPath->query("//text()[{$this->contains($this->t('PNR(s)'))}]")->length === 0) {
            $r->general()
                ->noConfirmation();
        } else {
            $r->general()->confirmation($confNo);
        }

        $pax = array_unique($this->http->FindNodes("//img[contains(@src, '/reiziger.gif')]/ancestor::td[1]/following-sibling::td[1]",
            null, "/(.*?)\s+-/"));

        if (!empty($pax)) {
            $r->general()
                ->travellers($pax);
        }

        $total = $this->amount(preg_replace("/(\d+)\s+(\d{2})$/", "$1,$2",
            $this->http->FindSingleNode("//*[normalize-space(text())='" . $this->t("Total price") . "']/ancestor::tr[1]/following-sibling::tr[2]")));
        $currency = $this->currency($this->http->FindSingleNode("//*[normalize-space(text())='" . $this->t("Total price") . "']/ancestor::tr[1]/following-sibling::tr[2]"));

        if (!empty($total) && !empty($currency)) {
            $r->price()
                ->total($total)
                ->currency($currency);
        }

        $this->segmentsRoutedetails($r);

        if (count($r->getSegments()) > 0) {
            return;
        }
        $xpath = "//*[{$this->eq($this->t("Departure:"))}]/ancestor::tr[1]";
        $segments = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH]: " . $xpath);

        foreach ($segments as $i => $root) {
            $s = $r->addSegment();
            $train = $this->http->FindSingleNode("./following-sibling::tr[" . $this->contains($this->t("Trainnumber:")) . "][1]/td[3]",
                $root);

            if (!empty($train)) {
                $s->extra()->number($train);
            } else {
                $s->extra()->noNumber();
            }

            $s->departure()
                ->name($this->http->FindSingleNode("./preceding-sibling::tr[2]", $root, true, "/^(.*?)\s+-\s+.*?$/"))
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("./td[3]", $root))));

            $s->arrival()
                ->name($this->http->FindSingleNode("./preceding-sibling::tr[2]", $root, true, "/^.*?\s+-\s+(.*?)$/"));

            $arrDate = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1][contains(., '" . $this->t("Arrival:") . "')]/td[3]",
                $root)));

            if (!empty($arrDate)) {
                $s->arrival()
                    ->date($arrDate);
            } elseif (empty($this->http->FindSingleNode("./following-sibling::tr[position()<4][not(" . $this->contains($this->t("Arrival:")) . ")]", $root))
                && !empty($this->http->FindSingleNode("./td[3]", $root, true, "/^[^:]+$/"))
                && !empty($s->getDepName())
            ) {
                $s->arrival()
                    ->noDate();
                $r->removeSegment($s);

                continue;
            }

            $passenger = $this->http->FindSingleNode("./following-sibling::tr[position() < 10][{$this->contains($this->t("Passengers:"))}][1]/following-sibling::tr[1]/td[3]",
                $root);

            if (preg_match("/" . $this->t("Coach") . " (\d+)/", $passenger, $m)) {
                $s->extra()
                    ->car($m[1]);
            }

            if (preg_match("/" . $this->t("Seat") . " (\w+)/", $passenger, $m)) {
                $s->extra()->seat($m[1]);
            }

            if (preg_match("/^\s*\d+\s+x\s+.+(?: - | Seat reservation )(.*\b(class|klas)\b.*)/u", $this->http->FindSingleNode("./following-sibling::tr[position() < 10][{$this->contains($this->t("Passengers:"))}][1]/td[3]",
                $root), $m)) {
                $s->extra()
                    ->cabin($m[1]);
            }

            $type = $this->http->FindSingleNode("./following-sibling::tr[{$this->starts($this->t("By:"))}][1]/td[3]",
                    $root);

            if (!empty($type)) {
                $s->extra()->type($type);
            }

            $s->extra()
                ->duration($this->http->FindSingleNode("./following-sibling::tr[position() < 5][{$this->contains($this->t("Travel time:"))}][1]/td[3]",
                    $root), true, true);
        }
    }

    private function segmentsRoutedetails(Train $r)
    {
        $xpath = "//tr[td[normalize-space(.)][1][{$this->eq($this->t("D"))}] and following-sibling::tr[1][td[normalize-space(.)][1][{$this->eq($this->t("A"))}]]]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length == 0) {
            return;
        }

        $dates = $this->http->FindNodes("//tr[td[normalize-space()][1][{$this->eq($this->t("Departure:"))}]]/td[normalize-space()][2]",
            null, "/(.+) {$this->opt($this->t('at'))} /u");
        $routedetails = $this->http->FindNodes("//text()[{$this->eq($this->t("Routedetails"))}]");

        if (count($dates) == 0 || count($dates) > 2 || count($dates) !== count($routedetails)) {
            return;
        }

        foreach ($segments as $root) {
            if (!empty($this->http->FindSingleNode("(./ancestor::*[not({$this->contains($this->t("Routedetails"))})]/preceding-sibling::*" . $xpath . ")[1]",
                $root))) {
                $columns[2][] = $root;
            } else {
                $columns[1][] = $root;
            }
        }

        if (count($dates) !== count($columns)) {
            return;
        }

        $detailinfo = [];

        foreach ($columns as $i => $roots) {
            $details = [];
            $tariff = $this->http->FindNodes("./following::text()[" . $this->eq($this->t('Tariff')) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space()][2]");
            $tariff = implode("\n", $this->http->FindNodes("//tr[.//text()[" . $this->eq($this->t('Tariff')) . "]]/following-sibling::tr[1]/td[normalize-space()][" . $i . "]/descendant::tr[1]/ancestor::*[1]/*"));
            $tariff = "\n" . $tariff . "\n";

            $segments = array_filter(preg_split("/\n([\p{Lu}\W\d ]+ - [\p{Lu}\W\d ]+)\n/u", $tariff, null, PREG_SPLIT_DELIM_CAPTURE));

            foreach ($segments as $row) {
                if (preg_match("/^\s*([\p{Lu}\W\d ]+ - [\p{Lu}\W\d ]+)\s*$/u", $row)) {
                    $name = $row;
                } else {
                    $details[$name][] = $row;
                }
            }

            if (count($roots) !== count($details)) {
                $detailinfo[$i] = [];
            } else {
                foreach ($details as $d) {
                    $detailinfo[$i][] = $d;
                }
            }
        }

//        $this->logger->debug('$detailinfo = ' . print_r($detailinfo, true));

        foreach ($columns as $i => $roots) {
            foreach ($roots as $j => $root) {
                $s = $r->addSegment();

                $s->extra()->noNumber();

                $date = $this->normalizeDate($dates[$i - 1]);

                $time = $this->http->FindSingleNode("./td[normalize-space()][2]", $root);
                $datetime = null;

                if (!empty($date) && !empty($time)) {
                    $datetime = strtotime($this->normalizeDate($date . " " . $time));
                }

                $s->departure()
                    ->name($this->http->FindSingleNode("./td[normalize-space()][3]", $root))
                    ->date($datetime);

                $time = $this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space()][2]", $root);
                $datetime = null;

                if (!empty($date) && !empty($time)) {
                    $datetime = strtotime($this->normalizeDate($date . " " . $time));
                }
                $s->arrival()
                    ->name($this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space()][3]", $root))
                    ->date($datetime)
                ;

                $type = null;
                $typeSrt = $this->http->FindSingleNode("./td[.//img][last()]//img/@src", $root, false, "/(\w+)\.png\s*$/");

                switch ($typeSrt) {
                    case 'tha':
                        $type = 'Thalys';

                        break;

                    case 'ic':
                        $type = 'IC';

                        break;
                }

                if (!empty($type)) {
                    $s->extra()->type($type);
                }

                $info = implode("\n", $detailinfo[$i][$j] ?? []);

                if (preg_match("/(?:^|\n)\s*\d+\s+x\s+.+(?: - | Seat reservation | with tariff )(.*\b(class|klas)\b.*)/u", $info, $m)) {
                    $s->extra()
                        ->cabin($m[1], true, true);
                }

                if (preg_match("/{$this->opt($this->t('Coach'))}\s+(\d+),?\s+{$this->opt($this->t('Seat'))}\s+(\w+)/", $info, $m)) {
                    $s->extra()
                        ->car($m[1])
                        ->seat($m[2]);
                }
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "/^\w+\s+(\d+\s+\w+\s+\d{4})(?:\s+at|\s+om)?\s+(\d+:\d+)$/",
            "/^\w+\s+(\d+\s+\w+\s+\d{4})$/",
        ];
        $out = [
            "$1, $2",
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("/\d+\s+([^\d\s]+)\s+\d{4}/", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("/[.,](\d{3})/", "$1", $this->re("/([\d\,\.]+)/", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            //'$' => 'USD',
            '£' => 'GBP',
        ];

        if ($code = $this->re("/(?:^|\s)([A-Z]{3})(?:$|\s)/", $s)) {
            return $code;
        }
        $s = $this->re("/([^\d\,\.]+)/", $s);

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field));
    }
}
