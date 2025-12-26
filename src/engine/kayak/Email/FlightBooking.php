<?php

namespace AwardWallet\Engine\kayak\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightBooking extends \TAccountChecker
{
    public $mailFiles = "kayak/it-631944858.eml, kayak/it-70626703.eml, kayak/it-85217958.eml, kayak/it-89297857.eml";
    public static $dictionary = [
        "en" => [
            'tripNumber'         => ['KAYAK Booking Reference', 'PRICELINE Trip Number', 'OVAGO Order number'],
            'statusVariants'     => ['confirmed', 'processing'],
            'overnight'          => ['Lands', 'Arrives'],
            ' Record Locator'    => [' Record Locator', ' Confirmation Code'],
            'Booking Date'       => ['Booking Date', 'Booking Date:'],
            'Total Price'        => ['Total Price', 'Total price'],
            'Booking reference:' => ['Booking reference:', 'Confirmation number:', 'Order number:'], // Subject
        ],
    ];
    private $detectFrom = "no-reply@support1.kayak.com";
    private $lastDate;
    private $detectSubject = [
        "en" => "Your KAYAK flight booking to",
        'Your OVAGO flight booking to Santiago has been received',
    ];
    private $detectBody = [
        "en" => [
            "Your booking is confirmed and issued",
            "We have received your order and we are now processing",
            "We have received your confirmation for the new schedule",
        ],
    ];

    private $lang = "en";
    private $emailSubject;

    private $providerCode = '';

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
//        if (stripos($headers["from"], $this->detectFrom)===false)
//            return false;

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Format
        $body = $parser->getHTMLBody();

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Language
        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($this->http->Response["body"], $dBody) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $this->emailSubject = $parser->getSubject();

        $this->parseHtml($parser, $email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

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

    public static function getEmailProviders()
    {
        return ['priceline', 'kayak', 'ovago'];
    }

    private function parseHtml(PlancakeEmailParser $parser, Email $email): void
    {
        $otaConfirmation = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('tripNumber'))}]/following-sibling::*[normalize-space()]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($otaConfirmation) {
            $otaConfirmationTitle = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('tripNumber'))} and following-sibling::*[normalize-space()]]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        } elseif (preg_match("/({$this->preg_implode($this->t('Booking reference:'))}) *([A-Z\d]{5,})(?: |$)/", $this->emailSubject, $m)) {
            $email->ota()->confirmation($m[2], trim($m[1], ':'));
        }

        $recordLocatorsTexts = $this->http->FindNodes("//text()[{$this->eq($this->t('tripNumber'))}]/following::td[not(.//td)][{$this->contains($this->t(' Record Locator'))}]/ancestor::tr[1]");

        if (count($recordLocatorsTexts) === 0) {
            $recordLocatorsTexts = $this->http->FindNodes("//text()[{$this->eq($this->t('Booking Date'))}]/preceding::td[not(.//td)][{$this->contains($this->t(' Record Locator'))}]/ancestor::tr[1]");
        }

        $rls = [];

        foreach ($recordLocatorsTexts as $rlt) {
            if (preg_match("/(.+)" . $this->preg_implode($this->t(" Record Locator")) . "\s*([A-Z\d]{5,7})\s*$/", $rlt, $m)) {
                $rls[trim($m[1])] = $m[2];
            }
        }

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger(s) details")) . "]/following::tr[not(./ancestor::thead)][1]/ancestor::*[1]/*/*[normalize-space()][1]", null,
                "/^\s*\d+\.\s*([[:alpha:]\- ]+?)\s*\(/u")), true)
        ;

        $status = $this->http->FindSingleNode("//h1/following::text()[{$this->starts($this->t('Booking'))}][1]", null, true, "/^{$this->preg_implode($this->t('Booking'))}\s+({$this->preg_implode($this->t('statusVariants'))})$/i");

        if ($status) {
            $f->general()->status($status);
        }

        // Issued
        $f->issued()
            ->tickets(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger(s) details")) . "]/following::tr[not(./ancestor::thead)][1]/ancestor::*[1]/*/*[normalize-space()][last()]", null,
                "/^\s*(\d{13})\s*$/u")), false)
        ;

        // Price
        $total = $this->nextText($this->t("Total Price"));

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$/", $total, $m)
        ) {
            // $1,347.72
            $f->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;

            $cost = $this->nextText($this->t("Fare"));

            if (preg_match("#^\s*" . preg_quote($m['curr']) . "\s*(?<amount>\d[\d\., ]*)\s*$#", $cost, $mat)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*" . preg_quote($m['curr']) . "\s*$#", $cost, $mat)) {
                $f->price()
                    ->cost($this->amount($mat['amount']))
                ;
            }
            $taxes = $this->nextText($this->t("Taxes & Fees"));

            if (preg_match("#^\s*" . preg_quote($m['curr']) . "\s*(?<amount>\d[\d\., ]*)\s*$#", $taxes, $mat)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*" . preg_quote($m['curr']) . "\s*$#", $taxes, $mat)) {
                $f->price()
                    ->tax($this->amount($mat['amount']))
                ;
            }
        }

        $bookingDate = strtotime($this->normalizeDate($this->nextText($this->t('Booking Date'))));
        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd") or starts-with(translate(normalize-space(),"0123456789：","dddddddddd:"),"dd:dd"))';

        $xpath = "//tr[ {$xpathTime} and following-sibling::tr[normalize-space()][1][{$xpathTime}] ]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $key => $root) {
            $dateRow = $this->http->FindSingleNode("ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][last()]/descendant-or-self::tr/*[not(.//tr) and normalize-space()][1][contains(normalize-space(), ',')]", $root, true, "/^[^,]+,\s*(.{3,})$/");

            if (preg_match("/^(.{3,}?)\s*\,?\s*{$this->preg_implode($this->t("overnight"))}\s+(.+)$/", $dateRow, $m)) {
                $dateValue = $m[1];
                $overnight = $m[2];
            } else {
                $dateValue = $dateRow;
                $overnight = null;
            }

            $dateNormal = $this->normalizeDate($dateValue);

            if ($dateNormal && $bookingDate) {
                $date = EmailDateHelper::parseDateRelative($dateNormal, $bookingDate, true, '%D% %Y%');
            } elseif ($dateNormal) {
                $date = EmailDateHelper::calculateDateRelative($dateNormal, $this, $parser, '%D% %Y%');
            } else {
                $date = 0;
            }

            $realDateOvernight = '';

            if (!empty($overnight)) {
                $dateOvernight = $this->normalizeDate($overnight);

                if ($dateOvernight && $bookingDate) {
                    $realDateOvernight = EmailDateHelper::parseDateRelative($dateOvernight, $bookingDate, true, '%D% %Y%');
                } elseif ($dateOvernight) {
                    $realDateOvernight = EmailDateHelper::calculateDateRelative($dateOvernight, $this, $parser, '%D% %Y%');
                } else {
                    $realDateOvernight = 0;
                }
            }

            // Segments
            $s = $f->addSegment();

            $xpathFlight = "ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]/descendant-or-self::tr[count(*)=3][1]";

            // British Airways • BA116 • Economy
            $flightRow = $this->http->FindSingleNode($xpathFlight . '/*[2]', $root);
            $flightRowParts = preg_split('/\s*•\s*/', $flightRow);

            if (count($flightRowParts) === 3) {
                if (!empty($flightRowParts[0]) && !empty($rls[$flightRowParts[0]])) {
                    $s->airline()->confirmation($rls[$flightRowParts[0]]);
                }

                if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flightRowParts[1], $m)) {
                    $s->airline()
                        ->name($m['name'])
                        ->number($m['number']);
                }

                if (!empty($flightRowParts[2])) {
                    $s->extra()->cabin($flightRowParts[2]);
                }
            }

            $duration = $this->http->FindSingleNode($xpathFlight . '/*[3]', $root, true, "/^{$this->preg_implode($this->t("Duration:"))}\s*(\d[\w ]+)$/");
            $s->extra()->duration($duration);

            // 8:15 PM    JFK    John F. Kennedy International Airport
            $pattern = "/^\s*(?<time>\d{1,2}:\d{2}(?:\s*[apAP][mM])?)\s*(?<code>[A-Z]{3})\s*(?<name>.+)/";

            $departure = $this->http->FindSingleNode('.', $root);

            // Departure
            $changeMoreThenDay = $this->http->FindSingleNode("//text()[{$this->contains($s->getAirlineName() . $s->getFlightNumber())}]/preceding::text()[normalize-space()][1]/ancestor::tr[1][starts-with(normalize-space(), 'Change planes in')]/descendant::td[normalize-space()][2]", null, true, "/^\s*(\d+\s*d)/");

            if (preg_match($pattern, $departure, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name']);

                if (!empty($changeMoreThenDay)) {
                    $s->departure()
                        ->noDate();
                } else {
                    $s->departure()
                        ->date((!empty($date)) ? strtotime($m['time'], $date) : null);
                }
            }

            $arrival = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]", $root);

            // Arrival
            if (preg_match($pattern, $arrival, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name']);

                if (!empty($changeMoreThenDay)) {
                    $s->arrival()
                        ->date(strtotime($m['time'], $realDateOvernight));
                } else {
                    $s->arrival()
                        ->date((!empty($date)) ? strtotime($m['time'], $date) : null);
                }
            }
        }
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@priceline.center') !== false
            || $this->http->XPath->query('//a[contains(@href,".priceline.com/") or contains(@href,"www.priceline.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"This is a transactional email from priceline") or contains(.,"@priceline.center")]')->length > 0
        ) {
            // it-89297857.eml
            $this->providerCode = 'priceline';

            return true;
        }

        if (stripos($headers['from'], '@ovago.com') !== false
            || $this->http->XPath->query('//*[contains(.,"customercare@ovago.com")]')->length > 0
        ) {
            // it-631944858.eml
            $this->providerCode = 'ovago';

            return true;
        }

        if (stripos($headers['from'], '@support1.kayak.com') !== false
            || $this->http->XPath->query('//a[contains(@href,".kayak.com/") or contains(@href,"www.kayak.com") or contains(@href,"//ovago.com/")]')->length > 0
            || $this->http->XPath->query('//*[contains(.,"@support1.kayak.com")]')->length > 0
        ) {
            // it-70626703.eml, it-85217958.eml
            $this->providerCode = 'kayak';

            return true;
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^([[:alpha:]]{3,})\s+(\d{1,2})\s*,\s*(\d{4})$/u', $text, $m)) {
            // Nov 10, 2020
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{1,2})\s+([[:alpha:]]{3,})$/u', $text, $m)) {
            // 26 Dec
            $day = $m[1];
            $month = $m[2];
            $year = '';
        } elseif (preg_match('/^\w*\,?\s*([[:alpha:]]{3,})\s+(\d{1,2})$/u', $text, $m)) {
            // Jan 18
            $month = $m[1];
            $day = $m[2];
            $year = '';
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) use ($text) { return "contains(" . $text . ", \"{$s}\")"; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v); }, $field)) . ')';
    }
}
