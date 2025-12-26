<?php

namespace AwardWallet\Engine\triprewards\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// parsers with similar formats: triprewards/Vacation(object)

class WorldMarkConfirmation extends \TAccountChecker
{
    public $mailFiles = "triprewards/it-2599780.eml, triprewards/it-43951403.eml, triprewards/it-44059081.eml, triprewards/it-65859807.eml, triprewards/it-812929592.eml";

    public $reFrom = ["@wyndhamvo.com"];
    public $reBody = [
        'en' => ['Dear WorldMark Owner/Guest', 'Your reservation details:'],
    ];
    public $reSubject = [
        'Confirmation #',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Reserved For:'        => ['Reserved For:', 'Reserved for'],
            'Check-In'             => ['Check-In', 'Check in'],
            'Check-Out'            => ['Check-Out', 'Checkout'],
            'Reservation Details'  => ['Reservation Details', 'Your reservation details:'],
            'Confirmation Number:' => ['Confirmation Number:', 'Confirmation number'],
            'Owner Number:'        => ['Owner Number:', 'Owner number'],
            'Max occupancy'        => ['Max occupancy', 'Max Occupancy:'],
            'Unit Description:'    => ['Unit Description:', 'Suite type'],
            'Payment Type:'        => ['Payment Type:', 'Credits used'],
            'Amount Charged:'      => ['Amount Charged:', 'Amount charged'],
            'Cancel by date'       => ['Cancel by date', 'To avoid penalty you must cancel by:'],
            'statusVariants'       => ['confirmed'],
        ],
    ];
    private $keywordProv = ['WorldMark', 'wyndham'];
    private $patterns = [
        'time' => '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?',
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'WorldMark') or contains(@src,'.worldmarktheclub.com')] | //a[contains(@href,'.worldmarktheclub.com')] | //text()[contains(normalize-space(), 'Wyndham Resort Development Corporation')]")->length > 0) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
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
                    && strpos($headers['subject'], $reSubject) !== false
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

    private function parseEmail(Email $email): void
    {
        $this->http->SetEmailBody(str_replace(["•", "&bull;"], '', $this->http->Response['body']));
        $r = $email->add()->hotel();

        $detailsHtml = $this->http->FindHTMLByXpath("//text()[{$this->eq($this->t('Reservation Details'))}]/ancestor::tr[1]/following-sibling::tr[{$this->contains($this->t('Check-In'))}]");
        $details = $this->htmlToText($detailsHtml);

        if (!$details) {
            // it-65859807.eml
            $detailsHtml = $this->http->FindHTMLByXpath("//text()[{$this->eq($this->t('Reservation Details'))}]/ancestor::tr[1][{$this->contains($this->t('Check-In'))}]");
            $details = $this->htmlToText($detailsHtml);
        }

        $travellerValue = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reserved For:'))}]/following::text()[normalize-space()][1]");
        $travellers = preg_split("/\s+(?:&|and)\s+/i", $travellerValue);

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation Number:'))}]/following::text()[normalize-space()!=''][1]"))
            ->travellers(array_filter($travellers, function ($item) {
                return !preg_match('/\.$/', $item);
            }))
            ->cancellation($this->http->FindPreg("#({$this->opt($this->t('Cancel by date'))}[:\s]+.+)#s",
                false, $details), true, true);

        $account = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Owner Number:'))}]/following::text()[normalize-space()!=''][1]");

        if (empty($account)) {
            $account = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Member#'))}]", null, true, "/{$this->opt($this->t('Member#'))}\s*(\d+)/");
        }

        if (!empty($account) && count($travellers) == 1) {
            $r->program()
                ->account($account, false, $travellers[0]);
        } elseif (!empty($account)) {
            $r->program()
                ->account($account, false);
        }

        $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Sincerely,'))}]/following::text()[normalize-space()][1]",
            null, false, "#^{$this->opt($this->t('Your'))}\s*(.+?)\s*(?:\bStaff|$)#");

        if (!$hotelName) {
            // it-65859807.eml
            $hotelName_temp = $this->http->FindSingleNode("//text()[{$this->contains($this->t('reservation at'))}]", null, true, "/{$this->opt($this->t('reservation at'))}\s+(.{3,}?)\s+{$this->opt($this->t('is confirmed'))}/");
            $hotelName_temp = str_replace("WorldMark WorldMark", "WorldMark", $hotelName_temp);
            $hotelName_temp = str_replace("WorldMark Club", "Club", $hotelName_temp);

            if ($this->http->XPath->query("//text()[{$this->contains([$hotelName_temp, strtoupper($hotelName_temp)])}]")->length > 1) {
                $hotelName = $hotelName_temp;
            }
        }

        $address = $this->http->FindPreg("#{$this->opt($this->t('Resort Check-in Address:'))}\s+(.+?)\s*{$this->opt($this->t('Owner Number:'))}#s", false, $details);
        $phone = null;

        if (!$address && $hotelName) {
            // it-65859807.eml
            $contactsHtml = $this->http->FindHTMLByXpath("//*[count(tr)=3 and tr[1][normalize-space()=''] and tr[2][{$this->starts([$hotelName, strtoupper($hotelName)])}] and tr[3][normalize-space()='']]/tr[2]");
            $contacts = $this->htmlToText($contactsHtml);

            if (preg_match("/^\s*(?<name>{$this->opt($hotelName)})[ ]*(?<address>(?:\n+.+){1,3}?)\s+(?<phone>[+(\d][-. \d)(]{5,}[\d)])(?:$|\n)/i", $contacts, $m)
            || preg_match("/^\s*(?<name>{$this->opt($hotelName)})[ ]*(?<address>(?:\n+.+){1,3}?[A-z])\s*(?<phone>[+(\d][-. \d)(]{5,}[\d)])(?:$|\n)/i", $contacts, $m)) {
                $address = preg_replace('/\s+/', ' ', trim($m['address']));
                $phone = $m['phone'];
            }
        }

        if (!$phone) {
            $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Resort Phone Number:'))}]/following::text()[normalize-space()][1]");
        }

        $r->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone);

        /*
            Check-In at 4:00 PM:
            Friday, Sep 6, 2019
        */
        $patterns['timeDate'] = "{$this->opt($this->t('at'))}[ ]+(?<time>{$this->patterns['time']})[: ]*\n+[ ]*(?<date>.{6,}?)";

        /*
            Check in
            Monday, Jan 25, 2021 at 4 p.m.
        */
        $patterns['dateTime'] = "(?<date>.{6,}?)[ ]+{$this->opt($this->t('at'))}[ ]+(?<time>{$this->patterns['time']})";

        if (preg_match("/^[ ]*{$this->opt($this->t('Check-In'))}[ ]+{$patterns['timeDate']}[ ]*$/mu", $details, $m)
            || preg_match("/^[ ]*{$this->opt($this->t('Check-In'))}[: ]*\n+[ ]*{$patterns['dateTime']}[ ]*$/mu", $details, $m)
        ) {
            $r->booked()->checkIn2($this->normalizeDate($m['date']) . ' ' . $m['time']);
        } elseif (preg_match("/{$this->opt($this->t('Check-In'))}\n*(?<date>\d+\/\d+\/\d{4})\s*at\s*\n*(?<time>\d+\s*(?:a\.|p\.)m\.)/", $details, $match)) {
            $r->booked()->checkIn2($this->normalizeDate($match['date']) . ' ' . $match['time']);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Check-Out'))}[ ]+{$patterns['timeDate']}[ ]*$/mu", $details, $m)
            || preg_match("/^[ ]*{$this->opt($this->t('Check-Out'))}[: ]*\n+[ ]*{$patterns['dateTime']}[ ]*$/mu", $details, $m)
        ) {
            $r->booked()->checkOut2($this->normalizeDate($m['date']) . ' ' . $m['time']);
        } elseif (preg_match("/{$this->opt($this->t('Check-Out'))}\n*(?<date>\d+\/\d+\/\d{4})\s*at\s*\n*(?<time>\d+\s*(?:a\.|p\.)m\.)/", $details, $match)) {
            $r->booked()->checkOut2($this->normalizeDate($match['date']) . ' ' . $match['time']);
        }

        $guests = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Max occupancy'))}]/following::text()[normalize-space()][1]");

        if (!empty($guests)) {
            $r->booked()->guests($guests);
        }

        $room = $r->addRoom();

        $unitDescription = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Unit Description:'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^([^:]+?)\s*[:]+\s*(.+)$/", $unitDescription, $m)) {
            $room
                ->setType($m[1])
                ->setDescription($m[2]);
        } elseif ($unitDescription) {
            // it-65859807.eml
            $room->setType($unitDescription);
        } else {
            $r->removeRoom($room);
        }

        $spent = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Payment Type:'))}]/following::text()[normalize-space()][1]", null, true, "/.*{$this->opt($this->t('credits'))}.*/i");

        if (empty($spent)) {
            $spent = $this->http->FindSingleNode("//text()[normalize-space()='Credits used']/following::text()[normalize-space()][1]", null, true, "/^([\d\.\,]+)$/");
        }

        if ($spent !== null) {
            $r->price()->spentAwards($spent);
        }

        $sum = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Amount Charged:'))}]/following::text()[normalize-space()!=''][1]");

        if ($sum !== null) {
            $sum = $this->getTotalCurrency($sum);
            $r->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Booking date'))}[: ]*\n+[ ]*(.{6,})$/m", $details, $m)) {
            $r->general()->date2($m[1]);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('your reservation at'))}]", null, true, "/{$this->opt($this->t('is'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[.,:;!]|$)/i");

        if ($status) {
            $r->general()->status($status);
        }

        $this->detectDeadLine($r);

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Available Points:')]")->length > 0 && !empty($account)) {
            $st = $email->add()->statement();

            if (count($travellers) == 1) {
                $st->addProperty('Name', $travellers[0]);
            }

            $st->setBalance($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Available Points:')]", null, true, "/{$this->opt($this->t('Available Points:'))}\s*(\d+)/"));
            $st->setNumber($account);
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            // Wednesday, Jun 24, 2015
            '/^[-[:alpha:]]+\s*,\s*([[:alpha:]]{3,})\s+(\d{1,2})\s*,\s*(\d{4})$/u',
        ];
        $out = [
            '$2 $1 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/{$this->opt($this->t('Cancel by date'))}[:\s]+(.+?(?: |\/)\d{4})/i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline2($this->normalizeDate($m[1]));
        }

        $h->booked()
            ->parseNonRefundable("#cancel by: Not Cancelable$#");
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Reserved For:'], $words['Check-In'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Reserved For:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Check-In'])}]")->length > 0
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

    private function getTotalCurrency($node): array
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = null;
        $cur = null;

        if (preg_match("#^(?<c>\D{1,3})\s*(?<t>[\d\.\']+)\,\s*if\s*applicable#u", $node, $m)
            || preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::parse($m['t'], $cur);
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
