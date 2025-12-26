<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicketPDF extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "iberia/it-5147432.eml, iberia/it-828502029.eml";
    public $reSubject = [
        'es' => ["Iberia Billete Electrónico - Electronic Ticket",
            'Boliviana de Aviación Billete Electrónico - Electronic Ticket',
        ],
    ];
    public $lang = 'es';
    public $date;
    public $providerCode;
    public static $dictionary = [
        'es' => [
            'ISSUED'             => 'EMITIDO/ISSUED',
            'NAME'               => 'NOMBRE/NAME',
            'TICKET NUMBER'      => 'NRO. BILLETE/TICKET NUMBER',
            'LOCATOR'            => 'LOCALIZADOR/RECORD LOCATOR',
            'ITINERARIO'         => 'ITINERARIO',
            'BASIC FARE'         => 'TARIFA AÉREA',
            'EQUIVALENT FARE'    => 'TARIFA EQUIVALENTE',
            'TAXES'              => 'TASAS',
            'SERVICE CHARGE'     => 'GASTOS DE GESTIÓN',
            'TOTAL'              => 'TOTAL',
            'From'               => ['Desde / From', 'desde / from'],
            'To'                 => ['A / To', 'a / to'],
            'Open'               => 'Abierto',
            'segmentEND'         => 'No Válido Antes de / Invalid Before',
        ],
    ];
    public static $providerDetect = [
        'iberia' => [
            'from'                => 'LOCALIZADOR/RECORD LOCATOR',
            'uniqueNameInSubject' => 'Iberia',
            'body'                => 'IBERIA.COM',
        ],
        'boliviana' => [
            'from'                => 'reservas@boa.bo',
            'uniqueNameInSubject' => 'Boliviana de Aviación',
            'body'                => ['BOA VENTAS WEB', 'reservas@boa.bo'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*");

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                if ($this->detectPdf($text, $parser->getCleanFrom()) === true) {
                    $this->parsePdf($email, $text);
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public function detectPdf($text, $from)
    {
        $detectedProvider = false;

        foreach (self::$providerDetect as $code => $dProvider) {
            if (!empty($dProvider['body'])
                && ($this->containsText($text, $dProvider['body']) !== false
                || $this->http->XPath->query("//node()[{$this->contains($dProvider['body'])}]")->length > 0
                )
            ) {
                $detectedProvider = true;
                $this->providerCode = $code;

                break;
            }

            if (!empty($dProvider['from']) && $this->containsText($from, $dProvider['from']) !== false) {
                $detectedProvider = true;
                $this->providerCode = $code;

                break;
            }
        }

        if ($detectedProvider === false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $reBody) {
            if (stripos($text, $reBody['LOCATOR']) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                if ($this->detectPdf($text, $parser->getCleanFrom()) === true) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject[0]) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "iberia.com") !== false || stripos($from, "iberia.es") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providerDetect);
    }

    private function parsePdf(Email $email, $text)
    {
        $tickets = $this->split("/(\n *{$this->opt($this->t('ISSUED'))})/u", $text);

        foreach ($tickets as $tText) {
            $conf = $this->re("/" . $this->opt($this->t('LOCATOR')) . " +([A-Z\d]{5,7})\n/", $tText);
            unset($f);

            foreach ($email->getItineraries() as $it) {
                if (in_array($conf, array_column($it->getConfirmationNumbers(), 0))) {
                    $f = $it;
                }
            }

            if (!isset($f)) {
                $f = $email->add()->flight();

                $f->general()
                    ->confirmation($conf);
            }

            $traveller = $this->re("/" . $this->opt($this->t('NAME')) . " *(.+)/u", $tText);
            $f->general()
                ->traveller($traveller, true);
            $date = null;

            if (preg_match("#\n\s*" . $this->t('ISSUED') . " *(\d+ *\S+ *\d+)\s+#", $tText, $m)) {
                $date = strtotime($m[1]);

                if (!empty($date)) {
                    $f->general()
                        ->date($date);
                }
            }

            if (preg_match("/" . $this->opt($this->t('TICKET NUMBER')) . " *(.+)/u", $tText,
                $m)) {
                $f->issued()
                    ->ticket($m[1], false, $traveller);
            }

            if (preg_match("/\n\s*" . $this->opt($this->t('TOTAL')) . " +([A-Z]{3})\s+([\d.,]+)\s+/", $tText, $m)) {
                if ($f->getPrice() && $f->getPrice()->getTotal()) {
                    if ($f->getPrice()->getCurrencyCode() === $m[1]) {
                        $f->price()
                            ->total($f->getPrice()->getTotal() + PriceHelper::parse($m[2], $m[1]))
                            ->currency($m[1]);
                    } else {
                        $f->price()
                            ->total(null);
                    }
                } else {
                    $f->price()
                        ->total(PriceHelper::parse($m[2], $m[1]))
                        ->currency($m[1]);
                }
            }

            if (
                preg_match("/\n\s*" . $this->opt($this->t('EQUIVALENT FARE')) . " +([A-Z]{3})\s+([\d.,]+)\s+/", $tText, $m)
                || preg_match("/\n\s*" . $this->opt($this->t('BASIC FARE')) . " +([A-Z]{3})\s+([\d.,]+)\s+/", $tText, $m)
            ) {
                if ($f->getPrice() && $f->getPrice()->getCost()) {
                    if ($f->getPrice()->getCurrencyCode() === $m[1]) {
                        $f->price()
                            ->cost($f->getPrice()->getCost() + PriceHelper::parse($m[2], $m[1]))
                            ->currency($m[1]);
                    } else {
                        $f->price()
                            ->cost(null);
                    }
                } else {
                    $f->price()
                        ->cost(PriceHelper::parse($m[2], $m[1]))
                        ->currency($m[1]);
                }
            }

            if (preg_match("/\n\s*(" . $this->opt($this->t('TAXES')) . ") +([A-Z]{3})\s+([\d.,]+)\s+/", $tText, $m)) {
                $currency = ($f->getPrice()) ? $f->getPrice()->getCurrencyCode() : null;

                if ($currency === $m[2] || empty($currency)) {
                    $f->price()
                        ->fee($m[1], PriceHelper::parse($m[3], $m[2]))
                        ->currency($m[2]);
                }
            }

            if (preg_match("/\n\s*(" . $this->opt($this->t('SERVICE CHARGE')) . ") +([A-Z]{3})\s+([\d.,]+)\s+/", $tText, $m)) {
                $currency = ($f->getPrice()) ? $f->getPrice()->getCurrencyCode() : null;

                if ($currency === $m[2] || empty($currency)) {
                    $f->price()
                        ->fee($m[1], PriceHelper::parse($m[3], $m[2]))
                        ->currency($m[2]);
                }
            }

            $segments = $this->split("/(\n\s*" . $this->opt($this->t('From')) . ")/u", $tText);
            $openSegmentsCount = 0;
            $allSegmentsCount = 0;

            foreach ($segments as $stext) {
                $stext = preg_replace("/\s+{$this->opt($this->t('segmentEND'))}[\S\s]+/u", '', $stext);
                $s = $f->addSegment();
                $allSegmentsCount++;

                // Desde / From
                // TOULOUSE (TLS)      BA0377     X   04 DEC     04 DEC   XBAFF 1PCOK
                //                                    07:35      08:40
                // A / To
                // MADRID (MAD)
                $re1 = "/" . $this->opt($this->t('From')) . "\s+(?<dName>.+?)\s*\((?<dCode>[A-Z]{3})\)\s*(?<al>[A-Z\d]{2})\s*(?<fn>\d+|OPEN)\s*(?<class>[A-Z]{1,2})\s*(?<dDate>(?:\d+\s*\S+|" . $this->t('Open') . "))\s*(?<aDate>(?:\d+\s*\S+|" . $this->t('Open') . "))\s*\s+\S+\s+\S+\s+(?<status>.*?)"
                    . "\s+(?<dTime>[\d:]{5}|Open|)\s+(?<aTime>[\d:]{5}|Open|)\s*" . $this->opt($this->t('To')) . "\s+(?<aName>.+?)\s*\((?<aCode>[A-Z]{3})\)/msi";
                // Desde / From      OB961     V     26DEC24     26DEC24              HK
                // SANTA CRUZ (VVI)                  0550        0655
                // A / To
                // LA PAZ (LPB)
                $re2 = "/" . $this->opt($this->t('From')) . " +(?<al>[A-Z\d]{2}) ?(?<fn>\d+|OPEN) +(?<class>[A-Z]{1,2})? *(?<dDate>(?:\d+ ?\S+|" . $this->t('Open') . ")) +(?<aDate>(?:\d+ ?\S+|" . $this->t('Open') . "))(?: +.*)*(?<status> +[A-Z]{2,})?"
                    . "\n\s*(?<dName>.+?) *\((?<dCode>[A-Z]{3})\) *(?<dTime>[\d:]{4,5}|Open) +(?<aTime>[\d:]{4,5}|Open)\s*\n\s*" . $this->opt($this->t('To')) . "\s+(?<aName>.+?) *\((?<aCode>[A-Z]{3})\)\s*(?: {3,}|\n|$)/u";

                if (preg_match($re1, $stext, $m) || preg_match($re2, $stext, $m)) {
                    // Airline
                    $s->airline()
                        ->name($m['al']);

                    if ($m['fn'] === 'OPEN') {
                        $openSegmentsCount++;
                        unset($s);

                        continue;
                    } else {
                        $s->airline()
                            ->number($m['fn']);
                    }

                    // Departure
                    $s->departure()
                        ->name($m['dName'])
                        ->code($m['dCode']);

                    if (mb_strtolower($m['dTime']) === 'open') {
                        $openSegmentsCount++;
                        unset($s);

                        continue;
                    } elseif (empty($m['dTime'])) {
                        $s->departure()
                            ->noDate();
                    } else {
                        $s->departure()
                            ->date($this->normalizeDate($m['dDate'] . ' ' . $m['dTime'], $date));
                    }

                    // Arrival
                    $s->arrival()
                        ->name($m['aName'])
                        ->code($m['aCode']);

                    if (mb_strtolower($m['aTime']) === 'open') {
                        $openSegmentsCount++;
                        unset($s);

                        continue;
                    } elseif (empty($m['aTime'])) {
                        $s->arrival()
                            ->noDate();
                    } else {
                        $s->arrival()
                            ->date($this->normalizeDate($m['aDate'] . ' ' . $m['aTime'], $date));
                    }

                    // Extra
                    $s->extra()
                        ->cabin($m['class'], true, true)
                        ->status(trim($m['status'] ?? ''), true, true);

                    $segments = $f->getSegments();

                    foreach ($segments as $segment) {
                        if ($segment->getId() !== $s->getId()) {
                            if (serialize($segment->toArray()) === serialize($s->toArray())) {
                                $f->removeSegment($s);

                                break;
                            }
                        }
                    }
                }
            }

            if (!empty($openSegmentsCount) && $openSegmentsCount === $allSegmentsCount) {
                $email->removeItinerary($f);
            }
        }

        if ($email->getItineraries() === 0 && !empty($openSegmentsCount)) {
            $email->setIsJunk(true, 'only open flights');
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
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

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function normalizeDate($str, $date)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 26DEC24 0550
            '/^\s*(\d{1,2}) ?([[:alpha:]]+) ?(\d{2})\s+(\d{1,2})(\d{2})\s*$/u',
            // 04 DEC 08:40
            '/^\s*(\d{1,2}) ?([[:alpha:]]+)\s+(\d{1,2}:\d{2})\s*$/u',
        ];
        $out = [
            "$1 $2 20$3, $4:$5",
            "$1 $2 %year%, $3",
        ];

        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str 2 = '.print_r( $str,true));

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+(?:\d{4}|%year%).*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (!empty($date) && $date > strtotime('01.01.2000') && strpos($str, '%year%') !== false
            && preg_match('/^\s*(?<date>\d+ \w+) %year%(?:\s*,\s(?<time>\d{1,2}:\d{2}.*))?$/', $str, $m)) {
            // $this->logger->debug('$date (no week, no year) = '.print_r( $m['date'],true));
            $str = EmailDateHelper::parseDateRelative($m['date'], $date);

            if (!empty($str) && !empty($m['time'])) {
                return strtotime($m['time'], $str);
            }

            return $str;
        } elseif (preg_match("/\b20\d{2}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field, $delimiter = '/'): string
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            // return str_replace(' ', '\s+', preg_quote($s, $delimiter));
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
    }
}
