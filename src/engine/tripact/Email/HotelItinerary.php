<?php

namespace AwardWallet\Engine\tripact\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
//use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelItinerary extends \TAccountChecker
{
    public $mailFiles = "tripact/it-38248287.eml, tripact/it-38672884.eml, tripact/it-38679255.eml, tripact/it-39215625.eml, tripact/it-39301835.eml, tripact/it-54676055.eml, tripact/it-54985172.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            "Hotel Confirmation:" => ["Hotel Confirmation:", "Hotel Confi​rmation:"], // Caution! There are hidden characters.
            // PDF
            "CHECK IN"        => ["CHECK IN", "Check In"],
            "CHECK OUT"       => ["CHECK OUT", "Check Out"],
            "Room Cost/Night" => ["Room Cost/Night", "Room Cost per night"],
            "TOTAL PAID"      => ["TOTAL PAID", "Estimated Charge at Hotel checkout"],
            "Taxes"           => ["Taxes", "Taxes & Fees"],
        ],
    ];

    private $detectFrom = "@tripactions.com";
    private $detectSubject = [
        "en" => "Booking |", //Confirmed - Hyatt House Dallas Las Colinas Booking | Maria Aveledo Sosa (17927574286)
    ];

    private $detectCompany = 'tripactions.com';

    private $detectBody = [
        "en" => ["Hotel Confirmation", "Hotel Confi​rmation"], // Caution! There are hidden characters.
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        $body = $this->http->Response['body'];
//        foreach ($this->detectBody as $lang => $detectBody){
//            foreach ($detectBody as $dBody){
//                if (strpos($body, $dBody) !== false) {
//                    $this->lang = $lang;
//                    break;
//                }
//            }
//        }

        // Travel Agency
        $email->obtainTravelAgency();
        $conf = $this->http->FindSingleNode("//text()[" . $this->starts("Record locator") . "]", null, true, "#^\s*Record locator\s+([A-Z\d]{5,})\s*$#");

        if (empty($conf) && preg_match("#\s*\(\s?([A-Z\d]{5,})\s?\)\s*$#", $parser->getSubject(), $m)) {
            $conf = $m[1];
        }
        $email->ota()->confirmation($conf, "Record locator");

        $earned = $this->http->FindSingleNode("//td[not(.//td) and " . $this->starts("Score! You Earned") . "]", null, true, "#Score! You Earned (.+)#");

        if (!empty($earned)) {
            $email->ota()->earnedAwards($earned);
        }
        $h = $this->parseHotel($email);

        // parsing additional information from PDF-attachment
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs)
            && (empty($h->getCheckInDate()) || empty($h->getCheckOutDate()) || empty($h->getPrice()))
        ) {
            $confirmationNumbers = $h->getConfirmationNumbers();
            $confirmation = !empty($confirmationNumbers) && count($confirmationNumbers) === 1
                ? array_shift($confirmationNumbers)[0] : null;

            foreach ($pdfs as $pdf) {
                $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (empty($textPdf)) {
                    continue;
                }

                if (stripos($textPdf, '@tripactions.com') !== false
                    && (strpos($textPdf, 'Hotel Information') !== false || strpos($textPdf, 'CHECK OUT') !== false)
                    && strpos($textPdf, $confirmation) !== false
                ) {
                    $this->parsePdf($h, $textPdf);

                    break;
                }
            }
        }

        if (empty($h->getCheckInDate()) && empty($h->getCheckOutDate()) && $h->getCancelled()) {
            $h->booked()
                ->noCheckIn()
                ->noCheckOut();
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'{$this->detectCompany}')] | //*[contains(.,'{$this->detectCompany}')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(),"' . $dBody . '")]')->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHotel(Email $email): Hotel
    {
        $h = $email->add()->hotel();

        // General
        $conf = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Hotel Confirmation:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][2]", null, true, "#^\s*([A-Z\d]{4,})\s*$#");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Hotel Confirmation:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][1]", null, true, "#^\s*([A-Z\d]{4,})\s*$#");
        }

        if (!empty($conf)) {
            $h->general()->confirmation($conf, $this->t("Hotel Confirmation"));
        } elseif (empty($conf) && !empty($this->re("/^\s*(.+)\n{$this->opt($this->t("Guest:"))}/", implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Hotel Confirmation:")) . "]/ancestor::tr[1]/following::tr[normalize-space()][position()<4]"))))) {
            $h->general()->noConfirmation();
        }

        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Guest:")) . "]/following::text()[normalize-space()][1]"))
            ->cancellation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Cancellation Policy")) . "]/following::text()[normalize-space()][1][not(ancestor::strong)]"), true, true)
        ;

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("You're good to go!")) . "])[1]"))) {
            $h->general()->status("Confirmed");
        } elseif (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("You canceled your hotel")) . "])[1]"))) {
            $h->general()
                ->status("Canceled")
                ->cancelled();
        }

        // Program
        $account = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Loyalty Program:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space()][2]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        if (!empty($account)) {
            $h->program()->account($account, false);
        }

        // Price
        $total = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Total Net Charge:")) . "][1]/following::text()[normalize-space()][1]/ancestor::td[1]//text()[normalize-space()]"));

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }
        $tax = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Taxes:")) . "][1]/following::text()[normalize-space()][1]/ancestor::td[1]//text()[normalize-space()]"));

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d., ]*)\s*$#", $tax, $m)
                || preg_match("#^\s*(?<amount>\d[\d., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $tax, $m)) {
            $currency = $this->currency($m['curr']);

            if ((!empty($h->getPrice()) && $h->getPrice()->getCurrencyCode() === $currency) || (empty($h->getPrice()))) {
                $h->price()
                    ->currency($this->currency($m['curr']))
                    ->fee("Taxes", $this->amount($m['amount']));
            }
        }
        $fXpath = "//text()[" . $this->eq(["Trip fee:", "Resort fee:", "Total Taxes/Fees:"]) . "][1]";
        $feeNodes = $this->http->XPath->query($fXpath);

        foreach ($feeNodes as $fRoot) {
            $feeName = $this->http->FindSingleNode(".", $fRoot);
            $fee = implode("\n", $this->http->FindNodes("./following::text()[normalize-space()][1]/ancestor::td[1]//text()[normalize-space()]", $fRoot));

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d., ]*)\s*$#", $fee, $m)
                    || preg_match("#^\s*(?<amount>\d[\d., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $fee, $m)) {
                $currency = $this->currency($m['curr']);

                if ((!empty($h->getPrice()) && $h->getPrice()->getCurrencyCode() === $currency) || (empty($h->getPrice()))) {
                    $h->price()
                        ->currency($this->currency($m['curr']))
                        ->fee(trim($feeName, ': '), $this->amount($m['amount']));
                }
            }
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Hotel Name")) . "]/following::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Hotel Name")) . "]/following::img[contains(@src, 'BookingCarPin')])[1]/ancestor::tr[1]"))
            ->phone($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Hotel Name")) . "]/following::img[contains(@src, 'hotels/phone')])[1]/ancestor::tr[1]", null, true, "#^\s*([\d \(\)\-\+\.]{5,})\s*$#"), true, true)
        ;

        if ($this->http->XPath->query("//text()[{$this->eq($this->t("Check In:"))}]")->length > 0 && empty($h->getCancelled())) {
            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check In:")) . "]/following::text()[normalize-space()][1]")))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check Out:")) . "]/following::text()[normalize-space()][1]")))
                ->rooms($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Rooms:")) . "][1]/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, "#^\s*x\s*(\d+)\s*$#i"))
            ;
        }

        // Rooms
        $h->addRoom()
            ->setType($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Room Type")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]"))
            ->setDescription($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Room Type")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space()][2]"))
            ->setRate($this->http->FindSingleNode("//text()[{$this->eq($this->t("Price per room/night:"))}][1]/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, '/^.*\d.*$/'), false, true);

        $this->detectDeadLine($h);

        return $h;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        // Relative date
        if (
               preg_match("#^\s*Any cancellation received within (?<days>\d{1,2} days?) prior to the arrival date will (?:incur the first night's charge|be charged for the entire stay)\.#ui", $cancellationText, $m)
            || preg_match("#You may cancel free of charge until (?<days>\d{1,2} days?) before arrival\.#ui", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['days'], '00:00');

            return true;
        }

        // Absolute date without year
        if (
            preg_match("#If cancelled before (?<month>\d{1,2})/(?<day>\d{1,2}) (?<time>\d{1,2}:\d{1,2}(?: [APap][Mm])?), no fee will be charged\.#ui", $cancellationText, $m)
        ) {
            if (!empty($h->getCheckInDate())) {
                $h->booked()->deadline(EmailDateHelper::parseDateRelative($m['day'] . '.' . $m['month'] . '.' . date('Y', $h->getCheckInDate()) . ', ' . $m['time'], $h->getCheckInDate(), false));
            }

            return true;
        }

        // Absolute date with year
        if (
            preg_match("#This reservation qualifies for free cancellation up until (?<time>\d{1,2}:\d{1,2}(?: [APap][Mm])?) local hotel time on (?<date>[^\.]*?\b\d{4}\b[^\.]*?)\.#ui", $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime($m['date'] . ', ' . $m['time']));

            return true;
        }

        // Nonrefundable
        if (
               preg_match("#Please note, if cancelled, modified or in case of no-show, the total price of the reservation will be charged\.#ui", $cancellationText, $m)
            || preg_match("#This rate is non-refundable\.#ui", $cancellationText, $m)
            || preg_match("#^\s*Non Refundable\s*$#ui", $cancellationText, $m)
            || preg_match("#you are not allowed to change or cancel your reservation\.#ui", $cancellationText, $m)
        ) {
            $h->booked()->nonRefundable();

            return true;
        }

        return false;
    }

    private function parsePdf(Hotel $h, $text): void
    {
        if (empty($h->getCheckInDate())) {
            if (preg_match("/^[ ]*{$this->opt($this->t('CHECK IN'))}[ ]*[:]+[ ]*(.{6,}?)(?:[ ]{2}|$)/m", $text, $m)) {
                // it-54676055.eml
                $h->booked()->checkIn($this->normalizeDate($m[1]));
            } elseif (preg_match("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('CHECK IN'))}\n+.*[ ]{2}(.{6,})$/m", $text, $m)) {
                // it-54985172.eml
                $h->booked()->checkIn($this->normalizeDate($m[1]));
            }
        }

        if (empty($h->getCheckOutDate())) {
            if (preg_match("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('CHECK OUT'))}[ ]*[:]+[ ]*(.{6,})$/m", $text, $m)) {
                // it-54676055.eml
                $h->booked()->checkOut($this->normalizeDate($m[1]));
            } elseif (preg_match("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('CHECK OUT'))}\n+.*[ ]{2}(.{6,})$/m", $text, $m)) {
                // it-54985172.eml
                $h->booked()->checkOut($this->normalizeDate($m[1]));
            }
        }

        if (!empty($h->getPrice())) {
            return;
        }

        $priceText = preg_match("/{$this->opt($this->t('PRICE SUMMARY'))}([\s\S]+)/", $text, $m) ? $m[1] : null;

        $rooms = $h->getRooms();

        if (!empty($rooms) && count($rooms) === 1
            && preg_match("/(?:^|[ ]{2})[ ]*{$this->opt($this->t('Room Cost/Night'))}[ ]{2,}(.*\d.*)$/m", $priceText, $m)
        ) {
            $room = array_shift($rooms);
            $room->setRate($m[1]);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('TOTAL PAID'))}[ ]{2,}(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d]*)$/m", $priceText, $m)) {
            // USD 1,041.11
            $h->price()
                ->currency($m['currency'])
                ->total($this->amount($m['amount']));

            if (preg_match('/^[ ]*' . $this->opt($this->t('Subtotal')) . '[ ]+(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d]*)$/m', $priceText, $matches)) {
                $h->price()->cost($this->amount($matches['amount']));
            }

            if (preg_match('/^[ ]*' . $this->opt($this->t('Taxes')) . '[ ]+(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d]*)$/m', $priceText, $matches)) {
                $h->price()->tax($this->amount($matches['amount']));
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
//        $in = [
//            "#^[^\s\d]+,\s*([^\s\d]+)\s*(\d{1,2}),\s*(\d{4})\s*at\s*(\d+:\d+(\s*[ap]m)?)\s*$#iu",// Thu, Feb 14, 2019 at 12:00 pm
//        ];
//        $out = [
//            "$2 $1 $3, $4",
//        ];
//        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price): ?float
    {
        if (preg_match("#^\s*\d{1,3}(,\d{1,3})?\.\d{2}\s*$#", $price)) {
            $price = str_replace([',', ' '], '', $price);
        }

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
