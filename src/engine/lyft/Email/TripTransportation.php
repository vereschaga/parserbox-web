<?php

namespace AwardWallet\Engine\lyft\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Transfer;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TripTransportation extends \TAccountChecker
{
    public $mailFiles = "lyft/it-11081027.eml, lyft/it-11084341.eml, lyft/it-11149286.eml, lyft/it-13166943.eml, lyft/it-3009093.eml, lyft/it-3905917.eml, lyft/it-3926796.eml, lyft/it-3926798.eml, lyft/it-4003948.eml, lyft/it-4003952.eml, lyft/it-43481952.eml, lyft/it-45750616.eml, lyft/it-45763459.eml, lyft/it-46043777.eml, lyft/it-46129651.eml, lyft/it-46156026.eml, lyft/it-46277307.eml, lyft/it-5077929.eml, lyft/it-59829737.eml, lyft/it-8558231.eml, lyft/it-8687153.eml, lyft/it-8838342.eml, lyft/it-8838461.eml, lyft/it-8842141.eml";

    protected $singleArrDate;

    protected $date;

    protected $text;

    private $lang = 'en';

    private static $dict = [
        'en' => [
            'Dropoff' => ['Dropoff', 'Drop-off'],
        ],
        'fr' => [
            'Receipt #' => 'Reçu n°',
            //            'Base fare' => '',
            'Pickup' => 'Départ',
            //            'Stop' => '',
            'Dropoff' => 'Arrivée',
            //            'Pickup:' => '',
            'Ride Details' => 'Détails de la course',
            //            'Ride ending' => '',
            'Thanks for riding with' => "Merci d'avoir voyagé avec",
            //            'Total charged' => '',
        ],
        'es' => [
            'Receipt #' => 'Recibo núm.',
            //            'Base fare' => '',
            'Pickup' => 'Origen',
            //            'Stop' => '',
            'Dropoff' => 'Destino',
            //            'Pickup:' => '',
            'Ride Details' => 'Detalles del viaje',
            //            'Ride ending' => '',
            'Thanks for riding with' => "¡Gracias por viajar con",
            //            'Total charged' => '',
        ],

        'pt' => [
            'Receipt #' => 'Recibo n.º',
            //            'Base fare' => '',
            'Pickup' => 'Embarque',
            //            'Stop' => '',
            'Dropoff' => 'Desembarque',
            //            'Pickup:' => '',
            //'Ride Details' => '',
            //            'Ride ending' => '',
            'Thanks for riding with' => "Obrigado por viajar com",
            //            'Total charged' => '',
        ],
    ];

    private $detectBody = [
        'en' => [
            'Pickup', 'Receipt #',
        ],
        'fr' => [
            'Départ', 'Reçu n°',
        ],
        'pt' => [
            'Embarque', 'Recibo n.º',
        ],
        'es' => [
            'Origen', 'Recibo núm.',
        ],
    ];

    private $year;
    private $region = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->year = date('Y', strtotime($parser->getDate()));
        $body = $parser->getHTMLBody();

        foreach ($this->detectBody as $lang => $detectBody) {
            if (false !== stripos($body, $detectBody[0]) && false !== stripos($body, $detectBody[1])) {
                $this->lang = $lang;

                break;
            }
        }

        // for google, to help find correct address
        // if ($this->lang === 'en' && $this->http->XPath->query("//node()[{$this->contains('San Francisco, CA')}]")->length > 0) {
        //     $this->region = ', US';
        // }

        $type = '';

        if ($this->http->XPath->query("//img[contains(@src, 'lyft.zimride.com/images/emails/') or contains(@alt,'Photo of')]")->length > 0
            && 0 === $this->http->XPath->query("//text()[contains(normalize-space(.), 'Your cancellation receipt') or contains(normalize-space(.), 'Your missed ride receipt')]")->length
        ) {
            $this->parseEmail($parser, $email);
        } elseif (0 < $this->http->XPath->query("//text()[contains(normalize-space(.), 'Your cancellation receipt') or contains(normalize-space(.), 'Your missed ride receipt')]")->length) {
            $this->parseCancel($email);
            $type = 'Cancelled';
        } else {
            $this->parseEmailPlain($parser, $email);
            $type = 'Plain';
        }
        $email->setType('RideReceipt' . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $from = true;

        if (isset($headers['from']) && $this->detectEmailFromProvider($headers['from']) !== true) {
            $from = false;
        }

        return isset($headers['subject'])
            && ($from || preg_match("#\bLyft\b#", $headers['subject']) > 0)
            && (stripos($headers['subject'], 'Your ride with') !== false
                || stripos($headers['subject'], 'Lyft Ride Receipt - Charge Failed') !== false);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        if (empty($text)) {
            $text = $parser->getPlainBody();
        }

        return
            $this->http->XPath->query("//img[contains(@src,'lyft.zimride.com/images/emails/') or contains(@alt,'Photo of')]")->length > 0
            || (strpos($text, 'Thanks for riding with') !== false && (strpos($text, 'Photo of') !== false || strpos($text, 'Lyft Ride') !== false));
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Lyft Ride') !== false
            || stripos($from, '@lyftmail.com') !== false;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    private function parseEmail(PlancakeEmailParser $parser, Email $email)
    {
        $this->logger->debug(__METHOD__);
        $r = $email->add()->transfer();

        $r->general()->confirmation($this->http->FindSingleNode("//*[contains(normalize-space(text()), '{$this->t('Receipt #')}')]", null, true, "#(?:Receipt \#|Reçu n°|Recibo núm\.|Recibo n\.º)\s*(\d+)#u"), $this->t('Receipt #'));

        if ($sum = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Balance Due'))}]", null, false, "#^(.+)\s+{$this->opt($this->t('Balance Due'))}#")) {
            $r->price()
                ->total($this->amount($sum))
                ->currency($this->currency($sum));
        } elseif ($sum = $this->http->FindSingleNode("//img[contains(@alt, 'Ride Map')]/preceding::tr[normalize-space()][1]/td[2]", null, false, "#^(.[0-9\.\,]+)$#")) {
            $r->price()
                ->total($this->amount($sum))
                ->currency($this->currency($sum));
        } else {
            $r->price()
                ->total($this->amount($this->http->FindSingleNode("//text()[normalize-space(.)='{$this->t('Base fare')}']/ancestor::tr[2]/following-sibling::tr[last()]/descendant::text()[normalize-space(.)!=''][2]")))
                ->currency($this->currency($this->http->FindSingleNode("//text()[normalize-space(.)='{$this->t('Base fare')}']/ancestor::tr[2]/following-sibling::tr[last()]/descendant::text()[normalize-space(.)!=''][2]")), false, true);
        }

        if ($r->getPrice()->getTotal() == 0) {
            $total = $this->http->FindSingleNode("//*[contains(normalize-space(text()), '{$this->t('Ride Details')}')]/ancestor::tr[3]/following-sibling::tr[4]/descendant::strong[normalize-space(.)!='']");

            if (empty($total)) {
                $total = $this->http->FindSingleNode("//*[contains(text(), '{$this->t('Total charged')}')]/following::*[1][normalize-space(.)!='']");
            }

            if (empty($total)) {
                $total = $this->http->FindSingleNode("//td[@class='mobileBodySmall' and img[@height='23']]/following-sibling::td");
            }

            if (preg_match('/^([\d\.,]+)[ ]+(\D{1}[A-Z]{1,3})$/', $total, $m)) {
                $r->price()
                    ->total(str_replace(',', '.', $m[1]))
                    ->currency($this->currency($m[2]));
            } elseif (preg_match("#^\s*(\D+)\s*(\d[\d.,]*)#", $total, $m)) {
                $r->price()
                    ->total(str_replace(',', '.', $m[2]));

                $r->price()
                    ->currency($this->currency($m[1]));
            }
        }

        $this->parseDate($parser);

        $searchWords = array_merge((array) $this->t("Pickup"), (array) $this->t("Stop"), (array) $this->t("Dropoff"));
        $xpath = "//text()[" . $this->eq($searchWords) . "]/ancestor::tr[ ./following-sibling::tr ][1]/..";
        $segments = $this->http->XPath->query($xpath);
        //		if ($segments->length === 0) {
//            $xpath = "//text()[".$this->starts($searchWords)."]/ancestor::tr[ ./following-sibling::tr ][1]/..";
//            $segments = $this->http->XPath->query($xpath);
//        }

        if ($segments->length > 0) {
            $points = [];

            foreach ($segments as $key => $root) {
                $point = [];
                $time = $this->http->FindSingleNode("./tr[2]/descendant::text()[normalize-space(.)!=''][last()]", $root);

                if ($this->date && $time) {
                    $point['Date'] = strtotime($this->date . ', ' . $time);
                }

                // it-8558231.eml
                if (!empty($points[$key - 1]) && !empty($point['Date'])) {
                    $prevPoint = $points[$key - 1];

                    if ($prevPoint['Date'] > $point['Date']) {
                        $point['Date'] = strtotime('+1 days', $point['Date']);
                    }
                    // it-59829737.eml
                    // TODO: https://redmine.awardwallet.com/issues/11393#note-78
                    elseif ($prevPoint['Date'] == $point['Date']) {
                        $point['Date'] = strtotime('+1 minute', $point['Date']);
                    }
                }

                $nameTexts = $this->http->FindNodes("./tr[position()=3 or position()=4][normalize-space(.)!='']", $root);

                if (!empty($nameTexts[0])) {
                    $point['Name'] = trim(implode(' ', $nameTexts), ', ');
                }

                $points[] = $point;
            }

            if (!$this->convertSegments($points, $r)) {
                // FE: it-11149286.eml - no pickup location -> junk
                if (count($r->getSegments()) === 1) {
                    if ($dep = array_shift($points)) {
                        if (!isset($dep['Name'])
                            && $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup'))}]/following::text()[normalize-space()!=''][2][{$this->starts($this->t('Dropoff'))}]")
                        ) {
                            $email->removeItinerary($r);
                            $email->setIsJunk(true, 'No pickup location.');

                            return;
                        }
                    }
                }
            }
        } elseif (isset($this->singleArrDate)) {
            $xpath = "//text()[" . $this->eq([$this->t("Pickup:"), $this->t("Dropoff:")]) . "]/ancestor::tr[1]";
            $segments = $this->http->XPath->query($xpath);

            if ($segments->length > 0) {
                $points = [];

                foreach ($segments as $root) {
                    $point = [];

                    if ($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Pickup:")) . "]", $root)) {
                        $point['Date'] = MISSING_DATE;
                    } else {
                        $point['Date'] = $this->singleArrDate;
                    }
                    $point['Name'] = $this->http->FindSingleNode('./td[2]', $root);
                    $points[] = $point;
                }
                $this->convertSegments($points, $r);
            }
        }
    }

    private function parseEmailPlain(PlancakeEmailParser $parser, Email $email)
    {
        $this->logger->debug(__METHOD__);

        if (empty($this->http->Response['body'])) {
            $text = $this->text = text($parser->getPlainBody());
        } else {
            $text = $this->text = text($this->http->Response['body']);
        }

        $r = $email->add()->transfer();
        $r->general()->confirmation($this->re("#Receipt \#\s*(\d+)#", $text), 'Receipt #');
        $r->price()
            ->total($this->amount($this->re('#fare.+(\$\d+\.\d+).*?Pickup#s', $text)))
            ->currency($this->currency($this->re('#fare.+(\$\d+\.\d+).*?Pickup#s', $text)), false, true);

        $this->parseDate($parser);

        $s = $r->addSegment();

        if (preg_match("#\n\s*Pickup\s+(\d+:\d+.*)\s+(.+)#", $text, $m)) {
            $depDate = $m[1];
            $s->departure()
                ->name($m[2]);
        }

        if (preg_match("#\n\s*{$this->opt($this->t('Dropoff'))}\s+(\d+:\d+.*)\s+(.+)#", $text, $m)) {
            $arrDate = $m[1];
            $s->arrival()
                ->name($m[2]);
        }

        if (isset($this->singleArrDate)) {
            $s->departure()
                ->noDate();
            $s->arrival()
                ->date($this->singleArrDate);
        } elseif (isset($this->date) && isset($depDate) && isset($arrDate)) {
            $s->departure()->date(strtotime($this->date . ', ' . $depDate));
            $s->arrival()->date(strtotime($this->date . ', ' . $arrDate));
        }
    }

    private function parseCancel(Email $email)
    {
        // so for now it's better junk then cancelled
        $email->setIsJunk(true, 'Need information about arrival place.');

        return;
        $t = $email->add()->transfer();
        $t->general()->cancelled();

        if ($conf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Receipt #')][1]", null, true, '/\#[ ]*(\d+)/')) {
            $t->general()
                ->confirmation($conf);
        }

        if (($segInfo = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Request on')][1]"))
            && preg_match('/Request on[ ]+(\w+) (\d{1,2}) at (\d{1,2}:\d{2} [AP]M) at (.+)/', $segInfo, $m)
        ) {
            $s = $t->addSegment();
            $s->departure()
                ->date(strtotime($m[2] . ' ' . $m[1] . ' ' . $this->year . ' ' . $m[3]))
                ->address($m[4]);
            $s->arrival()
                ->noDate();
        }
    }

    private function parseDate(PlancakeEmailParser $parser)
    {
//        $date = $this->http->FindSingleNode("//tr[td/span[contains(text(), 'Thanks for riding with')]]/preceding-sibling::tr[1]");
        $date = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thanks for riding with'))}]/ancestor::tr[1]/preceding-sibling::tr[normalize-space()!=''][not(contains(normalize-space(), 'Provider Code:'))][1]");

        if (!isset($date)) {
            $date = $this->http->FindSingleNode("//*[text()[{$this->contains($this->t('Thanks for riding with'))}]]/following::*[normalize-space(.)!=''][1]");
        }

        if (empty($date) && isset($this->text) && !empty($this->text)) {//for Plain format
            $date = $this->re("#{$this->t('Thanks for riding with')}.+\s+(.+?\b\d{4}\b.+?)\n#", $this->text);
        }

        if (empty($date) && isset($this->text) && !empty($this->text)) {//for Plain format
            $date = $this->re("#\n\s*(.+?\b\d{4}\b.+?)\s+{$this->t('Thanks for riding with')}#", $this->text);
        }

        if (isset($date)) {
            if (preg_match("/{$this->t('Ride ending')} (\w+ \d{1,2}) at (\d{1,2}:\d{2} [AaPp][Mm])/", $date, $m)) {
                $relative = strtotime($parser->getDate());

                if ($relative && $relative > strtotime('01/01/2000')) {
                    $year = date('Y', $relative);
                    $arr = strtotime($m[1] . ' ' . $year . ' ' . $m[2]);
                } else {
                    $arr = strtotime($m[1] . ' ' . $m[2]);
                }

                if ($arr) {
                    $this->singleArrDate = $arr;
                }
            } elseif (preg_match('/^(\w+ \d{1,2},\s*\d{4}) at /iu', $date, $m)) {
                $this->date = $m[1];
            } elseif (preg_match('/^(\w+) (\d{1,2},\s*\d{4}) à /iu', $date, $m)) {
                $this->date = MonthTranslate::translate($m[1], $this->lang) . ' ' . $m[2];
            } elseif (preg_match('/^(\d{1,2}) de (\w+) de[ ]*(\d{4}) a las /', $date, $m)) {
                $this->date = $m[1] . ' ' . MonthTranslate::translate($m[2], $this->lang) . ' ' . $m[3];
            } elseif (preg_match('/(\d{1,2})\s+DE\s+(.+)\s+DE\s+(\d{4})\s+.+\s+(\d{1,2}[:]\d{1,2})/', $date, $m)) {
                $this->date = $m[1] . ' ' . MonthTranslate::translate($m[2], $this->lang) . ' ' . $m[3];
            }
        }
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $this->logger->debug('$s = ' . print_r($s, true));

        $sym = [
            '€'   => 'EUR',
            '$'   => '$',
            '£'   => 'GBP',
            'US$' => 'USD',
            '$US' => 'USD',
            'CA$' => 'CAD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = trim($this->re("#([^\d\,\.]+)#", $s));

        foreach ($sym as $f=> $r) {
            if ($s === $f) {
                return $r;
            }
        }

        return null;
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

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }

    private function convertSegments($points, Transfer $r): bool
    {
        $result = true;

        foreach ($points as $point) {
            if ($next = next($points)) {
                $s = $r->addSegment();

                if (isset($point["Name"])) {
                    $s->departure()->name($this->normalizeLocation($point["Name"]));
                } else {
                    $result = false;
                }

                if (isset($point["Date"])) {
                    if ($point["Date"] === MISSING_DATE) {
                        $s->departure()->noDate();
                    } else {
                        $s->departure()->date($point["Date"]);
                    }
                }

                if (isset($next["Name"])) {
                    $s->arrival()->name($this->normalizeLocation($next["Name"]));
                } else {
                    $result = false;
                }

                if (isset($next["Date"])) {
                    if ($next["Date"] === MISSING_DATE) {
                        $s->arrival()->noDate();
                    } else {
                        $s->arrival()->date($next["Date"]);
                    }
                }
            }
        }

        return $result;
    }

    private function normalizeLocation(?string $s): ?string
    {
        if (empty($s) || !isset($this->region)) {
            return $s;
        }
        $s = preg_replace('/[, ]*,[, ]*/', ', ', $s);
        $s = preg_match("/.{2,},\s*USA?$/", $s) ? $s : $s . $this->region;

        return $s;
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }
}
