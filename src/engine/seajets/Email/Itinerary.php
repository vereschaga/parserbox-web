<?php

namespace AwardWallet\Engine\seajets\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "seajets/it-107419390.eml, seajets/it-378842104.eml";

    public $pdfNamePattern = ".*\.pdf";
    public $pdfInfo;
    public $subject;

    public $lang;
    public static $dictionary = [
        'en' => [
            'Passenger Name' => 'Passenger Name',
            'Child'          => ['Child', 'Infant'],
            'Order Id:'      => ['Order Id:', 'orderId:'],
            'Type'           => 'Type',
        ],
        'el' => [
            'Passenger Name'                            => 'Passenger Name',
            'Child'                                     => ['Child', 'Infant'],
            'Order Id:'                                 => ['Order Id:', 'orderId:'],
            'Type'                                      => 'Tύπος',
            "Booking Reference:"                        => 'Αριθμός Κράτησης:',
            "Reservation Date:"                         => 'Ημερομηνία Κράτησης:',
            "Departure"                                 => 'Αναχώρηση',
            "Vessel"                                    => 'Πλοίο',
            "TOTAL AMOUNT CHARGED TO YOUR CREDIT CARD:" => 'ΣΥΝΟΛΙΚΟ ΠΟΣΟ ΧΡΕΩΣΗΣ ΣΤΗΝ ΚΑΡΤΑ ΣΑΣ:',
        ],
    ];

    private $detectFrom = "reservations@seajets.gr";
    private $detectSubject = [
        // en
        // if array - contains all phrases
        ['Dear', 'orderId:'], // Dear KIM KIRK orderId: 691248 Booking Reference: 1112TO8VJ
    ];
    private $detectBody = [
        'en' => [
            'See you on board ',
        ],
        'el' => [
            'Θα σε δούμε στο πλοίο ',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (is_string($dSubject) && stripos($headers["subject"], $dSubject) !== false) {
                return true;
            } elseif (is_array($dSubject)) {
                $detected = true;

                foreach ($dSubject as $ds) {
                    if (stripos($headers["subject"], $ds) === false) {
                        $detected = false;

                        continue 2;
                    }
                }

                if ($detected == true) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains(['www.seajets.gr', 'www.seajets.com'], '@href')}]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $this->subject = $parser->getSubject();

        $this->pdfInfo = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $orderId = $this->re("/^(\d{5,})\W*/", $this->getAttachmentName($parser, $pdf));

            if (!empty($orderId) && stripos($text, '@seajets.gr') !== false) {
                $this->pdfInfo[$orderId] = [];
                $segments = $this->split("/(?:^|\n)(.+ \d{1,2}:\d{2} )/", $text);

                foreach ($segments as $segment) {
                    if (preg_match("/(\S.+) {3,}(?<date>\S.+) {3,}(?<arrival>\S.+)\n\s*.+@.+\s*\n *(?<name>[[:alpha:] \-]+) +(?<accommodation>\w+ +[A-Z\d]{1,5})\s*\n/", $segment, $m)) {
                        $date = $this->normalizeDate($m['date']);
                        $foundSeg = false;

                        foreach ($this->pdfInfo[$orderId] as $i => $st) {
                            if ($st['date'] === $date && $st['arrival'] === $m['arrival']) {
                                $this->pdfInfo[$orderId][$i]['name'][] = $m['name'];
                                $this->pdfInfo[$orderId][$i]['accommodation'][] = preg_replace("/\s+/", ', ', trim($m['accommodation']));
                                $foundSeg = true;

                                break;
                            }
                        }

                        if ($foundSeg === false) {
                            $this->pdfInfo[$orderId][] = [
                                'date'          => $date,
                                'arrival'       => $m['arrival'],
                                'name'          => [$m['name']],
                                'accommodation' => [preg_replace("/\s+/", ', ', trim($m['accommodation']))],
                            ];
                        }
                    }
                }
            }
        }

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
        $this->logger->debug(__METHOD__);
        $ferry = $email->add()->ferry();

        // General
        $order = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Order Id:")) . "]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{5,})\s*$/");

        if (empty($order)) {
            $order = $this->re("/{$this->opt($this->t('Order Id:'))}\s*(\d{5,})/", $this->subject);
        }

        $ferry->general()
            ->confirmation($order, "Order Id")
            ->confirmation($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Booking Reference:")) . "])[1]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,})\s*$/"),
                trim($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Booking Reference:")) . "])[1]"), ':'))
            ->travellers(array_unique($this->http->FindNodes("//tr[td[1][" . $this->eq($this->t("Passenger Name")) . "]]/following-sibling::tr/td[1]")))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservation Date:")) . "]/following::text()[normalize-space()][1]")))
        ;

        $xpath = "//tr[td[" . $this->eq($this->t("Departure")) . "] and td[" . $this->eq($this->t("Vessel")) . "]]/following-sibling::tr[normalize-space()]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $i => $root) {
            // Segments
            $s = $ferry->addSegment();
            $s->departure()
                ->name($this->http->FindSingleNode("*[1]", $root, null, "/^\s*(\S.+)? - \S.+/"))
                ->date(strtotime($this->http->FindSingleNode("*[2]", $root)))
            ;
            $s->arrival()
                ->name($this->http->FindSingleNode("*[1]", $root, null, "/^\s*\S.+ - (\S.+)/"))
                ->date(strtotime($this->http->FindSingleNode("*[3]", $root)))
            ;
            $s->extra()
                ->vessel($this->http->FindSingleNode("*[4]", $root))
            ;

            $followingCount = $nodes->length - $i - 1;
            $travellerXpath = "following::text()[" . $this->eq($this->t("Passenger Name")) . "]/following::tr[1][count(following::tr[td[" . $this->eq($this->t("Departure")) . "] and td[" . $this->eq($this->t("Vessel")) . "]]) = $followingCount]";

            if (!empty($this->pdfInfo[$order]) && !empty($s->getDepDate()) && !empty($s->getArrName())) {
                foreach ($this->pdfInfo[$order] as $pInfo) {
                    if ($pInfo['date'] === $s->getDepDate() && $pInfo['arrival'] === $s->getArrName()) {
                        $s->booked()
                            ->accommodations($pInfo['accommodation']);
                    }
                }
            }

            if (empty($s->getAccommodations())) {
                $accommodations = $this->http->FindNodes($travellerXpath . '/td[5]', $root);
                $s->booked()
                    ->accommodations($accommodations)
                ;
            }
            $adult = count(array_filter($this->http->FindNodes($travellerXpath . '/td[3]', $root, "/(" . $this->opt($this->t("Adult")) . ")/")));
            $child = count(array_filter($this->http->FindNodes($travellerXpath . '/td[3]', $root, "/(" . $this->opt($this->t("Child")) . ")/")));

            if ($adult + $child === count($ferry->getTravellers())) {
                $s->booked()
                    ->adults($adult)
                    ->kids($child);
            }
        }

        // Price
        $total = $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("TOTAL AMOUNT CHARGED TO YOUR CREDIT CARD:")) . "]/following-sibling::td[normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $ferry->price()
                ->total(PriceHelper::cost($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        return true;
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words['Type'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Type'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
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

    // additional methods
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
            // 20/06/2021
            '/^\s*(\d{1,2})\/(\d{2})\/(\d{4})\s*$/iu',
            // 20/06/2021 11:10
            '/^\s*(\d{1,2})\/(\d{2})\/(\d{4})\s+(\d{1,2}:\d{2})\s*$/iu',
        ];
        $out = [
            '$1.$2.$3',
            '$1.$2.$3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $date = $this->dateTranslate($date);

        return strtotime($date);
    }

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function getAttachmentName(\PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Disposition');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $matches)) {
            return $matches[1];
        }

        return false;
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
