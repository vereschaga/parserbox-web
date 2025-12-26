<?php

namespace AwardWallet\Engine\kayak\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class PlanTrip extends \TAccountChecker
{
    public $mailFiles = "kayak/it-19318567.eml, kayak/it-39004208.eml, kayak/it-39143262.eml, kayak/it-5517461.eml, kayak/it-5576383.eml, kayak/it-88347764.eml";

    public $reBody = [
        'en'  => ['Thanks for using KAYAK to book your hotel', 'Confirmation'],
        'en2' => ['Your booking', 'Confirmation'],
        'ru'  => ['Мы создали поездку для вашей брони отеля', 'Забронировано на'],
        'ru2' => ['Мы создали поездку для вашей брони перелета', 'Забронировано на'],
    ];
    public $reSubject = [
        'en' => 'Plan your trip to',
        'ru' => 'Составьте удобный план поездки в',
    ];

    public $lang = '';
    public $date;
    public static $dict = [
        'en' => [
            //            "Confirmation:" => "",
            //            "Booked on" => "",
            //            "Phone" => "",
            //            "Show details" => "",
            //            "to" => "",
            //            "review" => "",
            //            "Pick-Up/Drop-Off" => "",
            //            "Pick-up:" => "",
            //            "Drop-off:" => "",
        ],
        'ru' => [
            "Confirmation:" => "Подтверждение:",
            "Booked on"     => "Забронировано на",
            "Phone"         => "Телефон",
            "Show details"  => "Подробнее",
            "to"            => "-",
            //            "review" => "",
            //            "Pick-Up/Drop-Off" => "",
            //            "Pick-up:" => "",
            //            "Drop-off:" => "",
        ],
    ];
    private $keywords = [
        'booking' => [
            'Booking.com',
        ],
        'dollar' => [
            'Dollar',
        ],
        'payless' => [
            'Payless',
        ],
        'marriott' => [
            'Residence Inn By Marriott',
        ],
        'norwegian' => [
            'Norwegian.com',
        ],
        'priceline' => [
            'Priceline.com',
        ],
        'amadeus' => [
            'amadeus',
        ],
        'hotels' => [
            'Hotels.com',
        ],
        'htonight' => [
            'HotelTonight',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $body = $this->http->Response['body'];

        foreach ($this->reBody as $lang => $reBody) {
            if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $email->ota()->code('kayak');

        if (!$this->parseEmail($email)) {
            return null;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'kayak.com')]")->length > 0) {
            $body = $parser->getHTMLBody();

            foreach ($this->reBody as $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "kayak.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 3; // flight | hotel | car
        $cnt = count(self::$dict) * $types;

        return $cnt;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email)
    {
        if (!$this->parseFlight($email)) {
            return false;
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Pick-Up')]")->length === 0) {
            if (!$this->parseHotel($email)) {
                return false;
            }
        }

        if (!$this->parseCar($email)) {
            return false;
        }

        if (!$this->parseCar2($email)) {
            return false;
        }

        return true;
    }

    private function parseFlight(Email $email)
    {
        $xpath = "//img[contains(@src,'oneway')]/ancestor::tr[1]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $f = $email->add()->flight();
            $keyword = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::text()[contains(.,'" . $this->t('Booked on') . "')]/ancestor-or-self::*[1]",
                $root, true, "#:\s*(.+)#");

            if (!empty($keyword) && !empty($code = $this->getProviderByKeyword($keyword))) {
                $f->program()
                    ->code($code);
            }
            $phone = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::text()[contains(.,'" . $this->t('Phone') . "')]/ancestor-or-self::*[1]",
                $root, true, "#:\s*(.+)#");

            if (!empty($phone)) {
                $f->program()
                    ->phone($phone);
            }
            $confNo = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::text()[contains(.,'" . $this->t('Confirmation:') . "')]/ancestor-or-self::*[1]",
                $root, true, "#:\s*([A-Z\d]+)#");

            if (empty($confNo)
                && (($this->http->XPath->query("./following-sibling::table[1]", $root)->length > 0)
                    || ($this->http->XPath->query("./following::text()[normalize-space(.)!=''][1][" . $this->contains($this->t("Show details")) . "]",
                            $root)->length > 0))
            ) {
                $f->general()->noConfirmation();
            } elseif (!empty($confNo)) {
                $f->general()->confirmation($confNo);
            }
            $nodeSeg = $this->http->XPath->query(".//tr", $root);

            foreach ($nodeSeg as $i => $rootSeg) {
                $s = $f->addSegment();

                $node = $this->http->FindSingleNode("./preceding::table[1]/descendant::td[string-length(normalize-space(.))>3 and not(contains(.,' " . $this->t('to') . " '))]",
                    $rootSeg);

                if (($i & 1) && preg_match("#^\s*.+?\s*-\s*(.+?)\s*$#", $node, $m)) {//odd
                    $date = strtotime($this->normalizeDate($m[1]));
                } elseif (preg_match("#^\s*(.+?)\s*-\s*.+?\s*$#", $node, $m)) {
                    $date = strtotime($this->normalizeDate($m[1]));
                } else {
                    $date = strtotime($this->normalizeDate($node));
                }
                $node = $this->http->FindSingleNode("(./td[string-length(normalize-space(.))>2])[1]", $rootSeg);

                if (isset($date) && preg_match("#(\d+:\d+[ap])#", $node, $m)) {
                    $s->departure()
                        ->date(strtotime($m[1] . 'm', $date));
                } else {
                    $s->departure()
                        ->date(strtotime($node, $date));
                }
                $node = $this->http->FindSingleNode("(./td[string-length(normalize-space(.))>2])[4]", $rootSeg);

                if (isset($date) && preg_match("#(\d+:\d+[ap])#", $node, $m)) {
                    $s->arrival()
                        ->date(strtotime($m[1] . 'm', $date));
                } else {
                    $s->arrival()
                        ->date(strtotime($node, $date));
                }
                $s->departure()
                    ->code($this->http->FindSingleNode("(./td[string-length(normalize-space(.))>2])[2]", $rootSeg, true,
                        "#^\s*([A-Z]{3})\s*$#"));
                $s->arrival()
                    ->code($this->http->FindSingleNode("(./td[string-length(normalize-space(.))>2])[3]", $rootSeg, true,
                        "#^\s*([A-Z]{3})\s*$#"));
                $s->extra()
                    ->duration($this->http->FindSingleNode("(./td[string-length(normalize-space(.))>2])[5]", $rootSeg));
                $node = $this->http->FindSingleNode("(./td[string-length(normalize-space(.))>2])[6]", $rootSeg);

                if (preg_match("#(\d+)\s+stop#", $node, $m)) {
                    $s->extra()->stops($m[1]);
                } elseif (preg_match("#Non[-\s]*stop#i", $node, $m)) {
                    $s->extra()->stops(0);
                }
                $s->airline()
                    ->name($this->http->FindSingleNode("(./td[string-length(normalize-space(.))>2])[7]", $rootSeg))
                    ->noNumber();
            }
        }

        return true;
    }

    private function parseHotel(Email $email)
    {
        $xpath = "//img[contains(@src,'/hotels/') and (starts-with(@alt,'star') or starts-with(@alt,'Stars'))]/ancestor::table[1][contains(.,'review')]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = "//text()[" . $this->starts($this->t("Confirmation:")) . " or " . $this->starts($this->t("Booked on")) . "]/ancestor::table[position()<3][preceding-sibling::table]/preceding-sibling::table[1][not(//img[contains(@src,'oneway')]) and not(" . $this->contains($this->t("Pick-Up/Drop-Off")) . ")]";
            $this->logger->error($xpath);
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length === 0) {
            $xpath = "//text()[" . $this->starts($this->t("Confirmation:")) . "]/ancestor::table[position()<3][preceding-sibling::table]/preceding-sibling::table[1][not(//img[contains(@src,'oneway')]) and not(" . $this->contains($this->t("Pick-Up/Drop-Off")) . ")]";
            $this->logger->error($xpath);
            $nodes = $this->http->XPath->query($xpath);
        }

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();
            $keyword = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::text()[contains(.,'" . $this->t('Booked on') . "')]/ancestor-or-self::*[1]",
                $root, true, "#:\s*(.+)#");

            if (!empty($keyword) && !empty($code = $this->getProviderByKeyword($keyword))) {
                $h->program()
                    ->code($code);
            }
            $phone = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::text()[contains(.,'" . $this->t('Phone') . "')]/ancestor-or-self::*[1]",
                $root, true, "#:\s*(.+)#");

            if (!empty($phone)) {
                $h->program()
                    ->phone($phone);
            }
            $confNo = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::text()[contains(.,'" . $this->t('Confirmation:') . "')]/ancestor-or-self::*[1]",
                $root, true, "#:\s*([A-Z\d\-]+)#");

            if (empty($confNo) && ($this->http->XPath->query("./following-sibling::table[1]", $root)->length > 0)) {
                $h->general()->noConfirmation();
            } elseif (!empty($confNo)) {
                $h->general()->confirmation($confNo);
            }

            if (($root1 = $this->http->XPath->query("./preceding-sibling::table[1]", $root))->length > 0) {
                $root1 = $root1->item(0);
            } else {
                $root1 = $root;
            }
            $h->hotel()
                ->name($this->http->FindSingleNode("(./descendant::text()[normalize-space(.)!=''])[1]", $root1));

            $node = $this->http->FindSingleNode("(./descendant::text()[normalize-space(.)!=''])[last()][not(" . $this->contains($this->t("review")) . ")]",
                $root);

            if (preg_match("#^(.+),\s+([A-Z]{2})(?:,\s+(.+))?$#", $node, $m)) {
                $h->hotel()
                    ->address($node);
                $da = $h->hotel()->detailed();
                $da
                    ->city($m[1])
                    ->state($m[2]);

                if (isset($m[3])) {
                    $da->country($m[3]);
                }
            } elseif (!empty($node)) {
                $h->hotel()
                    ->address($node);
            } else {
                $h->hotel()
                    ->noAddress();
            }
            $node = array_map("trim",
                explode("-", $this->http->FindSingleNode("(./descendant::text()[normalize-space(.)!=''])[2]", $root1)));

            if (count($node) == 2) {
                $h->booked()
                    ->checkIn(strtotime($this->normalizeDate($node[0])))
                    ->checkOut(strtotime($this->normalizeDate($node[1])));
            }
        }

        return true;
    }

    private function parseCar(Email $email)
    {
        $xpath = "//text()[" . $this->starts($this->t("Pick-Up/Drop-Off")) . "]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->rental();
            $keyword = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::text()[contains(.,'" . $this->t('Booked on') . "')]/ancestor-or-self::*[1]",
                $root, true, "#:\s*(.+)#");

            if (!empty($keyword) && !empty($code = $this->getProviderByKeyword($keyword))) {
                $r->program()
                    ->code($code);
            }
            $phone = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::text()[contains(.,'" . $this->t('Phone') . "')]/ancestor-or-self::*[1]",
                $root, true, "#:\s*(.+)#");

            if (!empty($phone)) {
                $r->program()
                    ->phone($phone);
            }
            $confNo = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::text()[contains(.,'" . $this->t('Confirmation:') . "')]/ancestor-or-self::*[1]",
                $root, true, "#:\s*([A-Z\d]+)#");

            if (empty($confNo) && ($this->http->XPath->query("./following-sibling::table[1]", $root)->length > 0)) {
                $r->general()->noConfirmation();
            } elseif (!empty($confNo)) {
                $r->general()->confirmation($confNo);
            }

            if (($root1 = $this->http->XPath->query("./preceding-sibling::table[1]", $root))->length > 0) {
                $root1 = $root1->item(0);
            } else {
                $root1 = $root;
            }
            $r->car()
                ->type($this->http->FindSingleNode("(./descendant::text()[normalize-space(.)!=''])[1]", $root1, false,
                    "#(.+)\s*\-#"));

            $node = array_map("trim",
                explode("-", $this->http->FindSingleNode("(./descendant::text()[normalize-space(.)!=''])[2]", $root1)));

            if (count($node) == 2) {
                $r->pickup()
                    ->date(strtotime($this->normalizeDate($node[0])));
                $r->dropoff()
                    ->date(strtotime($this->normalizeDate($node[1])));

                if ($nodes->length === 1) {
                    $puTime = strtotime($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Pick-up:")) . "]/ancestor::td[1]/following-sibling::td[1]"));
                    $doTime = strtotime($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Drop-off:")) . "]/ancestor::td[1]/following-sibling::td[1]"));

                    if (!empty($puTime)) {
                        $r->pickup()
                            ->date($puTime);
                    }

                    if (!empty($doTime)) {
                        $r->dropoff()
                            ->date($doTime);
                    }
                }
            }
            $r->pickup()
                ->location($this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Pick-Up/Drop-Off")) . "]",
                    $root, false, "#:\s*(.+)#"));
            $r->dropoff()
                ->location($this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Pick-Up/Drop-Off")) . "]",
                    $root, false, "#:\s*(.+)#"));
        }

        return true;
    }

    private function parseCar2(Email $email)
    {
        $xpath = "//text()[normalize-space()='Need a Rental Car?']/preceding::text()[starts-with(normalize-space(), 'Pick-Up:')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->rental();
            $keyword = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::text()[contains(.,'" . $this->t('Booked on') . "')]/ancestor-or-self::*[1]",
                $root, true, "#:\s*(.+)#");

            if (!empty($keyword) && !empty($code = $this->getProviderByKeyword($keyword))) {
                $r->program()
                    ->code($code);
            }
            $phone = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::text()[contains(.,'" . $this->t('Phone') . "')]/ancestor-or-self::*[1]",
                $root, true, "#:\s*(.+)#");

            if (!empty($phone)) {
                $r->program()
                    ->phone($phone);
            }
            $confNo = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::text()[contains(.,'" . $this->t('Confirmation:') . "')]/ancestor-or-self::*[1]",
                $root, true, "#:\s*([A-Z\d]+)#");

            if (empty($confNo) && ($this->http->XPath->query("./following-sibling::table[1]", $root)->length > 0)) {
                $r->general()->noConfirmation();
            } elseif (!empty($confNo)) {
                $r->general()->confirmation($confNo);
            }

            if (($root1 = $this->http->XPath->query("./preceding-sibling::table[1]", $root))->length > 0) {
                $root1 = $root1->item(0);
            } else {
                $root1 = $root;
            }
            $r->car()
                ->type($this->http->FindSingleNode("(./descendant::text()[normalize-space(.)!=''])[1]", $root1, false,
                    "#(.+)\s*\-#"));

            $node = array_map("trim",
                explode("-", $this->http->FindSingleNode("(./descendant::text()[normalize-space(.)!=''])[2]", $root1)));

            if (count($node) == 2) {
                $r->pickup()
                    ->date(strtotime($this->normalizeDate($node[0])));
                $r->dropoff()
                    ->date(strtotime($this->normalizeDate($node[1])));

                if ($nodes->length === 1) {
                    $puTime = strtotime($this->http->FindSingleNode("./following::text()[" . $this->starts($this->t("Pick-up:")) . "][1]/following::text()[normalize-space()][1]", $root));
                    $doTime = strtotime($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Drop-off:")) . "]/following::text()[normalize-space()][1]", $root));

                    if (!empty($puTime)) {
                        $r->pickup()
                            ->date($puTime);
                    }

                    if (!empty($doTime)) {
                        $r->dropoff()
                            ->date($doTime);
                    }
                }
            }
            $r->pickup()
                ->location($this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Pick-Up")) . "]",
                    $root, false, "#:\s*(.+)#"));
            $r->dropoff()
                ->location($this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Drop-Off")) . "]",
                    $root, false, "#:\s*(.+)#"));
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '#^\s*\S+\s+(\S+)\s+(\d+)\s*$#',
            '#^\s*\S+\s+(\d+)\s+(\S+)\s*$#',
            '#^\s*(\S+)\s+(\d+)\s*$#',
            '#^\s*(\d+)\s+(\S+)\s*$#',
        ];
        $out = [
            '$2 $1 ' . $year,
            '$1 $2 ' . $year,
            '$2 $1 ' . $year,
            '$1 $2 ' . $year,
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $date));
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function getProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                }
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

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }
}
