<?php

namespace AwardWallet\Engine\goldpassport\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parsers omnihotels/FolioForPDF, carlson/FolioPDF (in favor of goldpassport/InvoicePDF)

// parsers with similar formats: fseasons/FolioPdf, aceh/YourReservationPDF

class InvoicePDF extends \TAccountChecker
{
    public $mailFiles = "goldpassport/it-121336190.eml, goldpassport/it-156523941.eml, goldpassport/it-168311173.eml, goldpassport/it-187696490.eml, goldpassport/it-52820857.eml, goldpassport/it-52925586.eml, goldpassport/it-53386834.eml, goldpassport/it-621072474.eml, goldpassport/it-62624922.eml, goldpassport/it-62746130.eml, goldpassport/it-644110739.eml, goldpassport/it-64850457.eml, goldpassport/it-6590393.eml, goldpassport/it-6615206.eml, goldpassport/it-66330600.eml, goldpassport/it-793119727.eml, goldpassport/it-870340640.eml, goldpassport/it-870917947.eml, goldpassport/it-88385198.eml, goldpassport/it-97866261.eml";

    private static $detectors = [
        'ko' => ["입실일"],
        'ja' => ["お客様名"],
        'de' => ["RECHNUNG"],
        'fr' => ["No. chambre"],
        'en' => ["INVOICE", "Receipt for ", "Stay Charges:", "World of Hyatt Stay Summary", "Folio:", "Membership", "www.marriottrewards.com", "www.ihgrewardsclub.com", "As a Marrio", "Marriott", 'Thank you for choosing Hilton', 'United States 805-900-8388', 'under license of IHG Hotels Limited', 'Four Seasons', 'www.fourseasons.com/', '@fourseasons.com',
            'www.loewshotels.com', "Thank you for staying with us at 70 Park Avenue Hotel !", "www.ibisba.com.au", "I agree my liability for this bill is not waived and agree to be held personally l", ],
    ];
    private static $dictionary = [
        'ko' => [
            //"Booking #"  => "",
            "Arrival"   => ["입실일"],
            "Departure" => "퇴실일",
            "Tel:"      => "Tel 전화 :",
            "Fax:"      => "팩스 :",
            // "Folio No:" => "",
            "Room No:"  => "객실 번호",
            //"Guest Name" => "お客様名",
            "Mr/Mrs" => ["Mr"],
            //"Membership" => "",
            //"Total"      => [""],
            //"Balance"    => [""],
            //            "Cost" => "",
            //            "Tax" => "",
            //            "hotel name detect" => "",
            //            "managerName" => "Generaldirektor/General Manager",
        ],
        'ja' => [
            "Booking #"  => "発行番号",
            "Arrival"    => ["到着日"],
            "Departure"  => "出発日",
            "Tel:"       => "〒",
            "Fax:"       => "Fax :",
            // "Folio No:" => "",
            "Room No:"   => "部屋番号",
            "Guest Name" => "お客様名",
            "Mr/Mrs"     => ["Mr"],
            "Membership" => "会員番号",
            "Total"      => ["合計金額"],
            "Balance"    => ["残高"],
            //            "Cost" => "",
            //            "Tax" => "",
            //            "hotel name detect" => "",
            //            "managerName" => "Generaldirektor/General Manager",
        ],
        'de' => [
            "Booking #" => "ResNr.",
            "Arrival"   => ["Anreise"],
            "Departure" => "Abreise",
            "Tel:"      => ["Tel:", "Tel.:"],
            //            "Fax:" => "",
            // "Folio No:" => "",
            "Room No:"   => ["Zimmer Nr.", "Zimmernr."],
            "Guest Name" => "Gastname",
            "Mr/Mrs"     => ["Herr"],
            //            "Membership" => "",
            "Total" => ["Total", "TOTAL:"],
            // "Balance"    => "",
            //            "Cost" => "",
            //            "Tax" => "",
            //            "hotel name detect" => "",
            "managerName" => "Generaldirektor/General Manager",
        ],
        'fr' => [
            "Booking #"  => "No. De confirmation",
            "Arrival"    => ["Arrivée"],
            "Departure"  => "Depart",
            "Tel:"       => ["Tel"],
            "Fax:"       => "Fax",
            "Folio No:"  => "No. de folio",
            "Room No:"   => ["No. chambre"],
            "Guest Name" => "Nom Client",
            "Mr/Mrs"     => ["Mr."],
            "Membership" => "No. Membership",
            "Total"      => ["Total"],
            // "Balance"    => "",
            //            "Cost" => "",
            //            "Tax" => "",
            //            "hotel name detect" => "",
            //"managerName" => "Generaldirektor/General Manager",
        ],
        'en' => [
            "Booking #"         => ["Booking #", "Confirmation #", "Conf No.", "Confirmation No.", "CRS Number", "Conf No", "Conf. No.", "Conf.", "Confirmation Number", "Conf. Number", 'REFERENCE', 'Reg. No.', 'Reservation No.', 'Conf.No.'],
            "Arrival"           => ["Arrival", "ARRIVAL DATE"],
            "Departure"         => ["Departure", "DEPARTURE DATE"],
            "Tel:"              => ["Tel:", "Tel.", "tel.", "T:", "Telephone:", "Telephone :", "Telephone", "Phone:", "Phone", "T ", "T.", "Tel :", 'ph ', 'TEL:', 'TEL.', 'CEP:'],
            "Fax:"              => ["Fax:", "F:", "fax"],
            "Folio No:"         => ["Folio No:", "Folio No", "Folio No.:", "Folio No.", "Folio:", "Folio Number:", "FOLIO NO:"],
            "Room No:"          => ["Room No:", "Room No", "Room No.:", "Room No.", "Room:", "Room Number:", 'ROOM NO:', 'ROOM'],
            "Guest Name"        => ["Guest Name", "Invoice For", "Receipt for"],
            'No of Guests'      => ['No of Guests', 'No. of Adults', 'Adult/Child', 'Number of guest(s)', 'Persons per room', 'Guests', 'Number of Adults'],
            "Mr/Mrs"            => ["Mr. and Mrs.", "Miss", "MISS", "Mrs", "MRS", "Mr", "MR", "Ms", "MS"],
            "Membership"        => ["Membership Number", "Membership", "Marriott Bonvoy No.", "Loyalty No.", "Loyalty Number", "Membership No.", "WyndhamRewards #:", "Rewards No.", "Membership No:MR #", "Rewards No:", "Bonvoy No."],
            "Total"             => ["Total", "TOTAL:"],
            "Cost"              => "Vatable Amount",
            "Tax"               => ["VAT Amount", "VAT"],
            "hotel name detect" => ["WE LOOK FORWARD TO WELCOMING YOU BACK TO"],
            "No. of rooms"      => ["No. of rooms", "Number of room(s)"],
            //            "managerName" => "",
            'Room Category:' => ['Room Category:', 'Room Type'],
            'Tax Invoice'    => ['Tax Invoice', 'Information Invoice'],
        ],
    ];

    private $from = "@hyatt.com";
    private $subject = ["Invoice for your stay from", "Folio from", "Guest Folio"];
    private $providerCode = '';
    private $lang = '';
    private $pdfNamePattern = ".*pdf";
    private $enDatesInverted = false;
    private $subjectEmail;
    private $fromEmail;

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['subject'], 'Armenia Marriott Hotel Yerevan') !== false
            || stripos($headers['subject'], 'Your Folio from the TWA Hotel') !== false
        ) {
            return true;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true
            && stripos($headers['subject'], 'The Ritz-Carlton') === false
            && stripos($headers['from'], '@marriott.com') === false && stripos($headers['from'], '@marriotthotels.com') === false
            && stripos($headers['from'], '@rosewoodhotels.com') === false && strpos($headers['subject'], 'Rosewood') === false
            && stripos($headers['from'], '@fourseasons.com') === false && strpos($headers['subject'], '@fourseasons.com') === false
            && stripos($headers['from'], '@loewshotels.com') === false && strpos($headers['from'], '@universalorlando.com') === false
            && stripos($headers['from'], 'wynnlasvegas.com') === false
        ) {
            return false;
        }

        foreach ($this->subject as $sub) {
            if (stripos($headers["subject"], $sub) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs)) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

            if (empty($text)) {
                return false;
            }

            if ($this->detectBody($text) !== true) {
                return false;
            }

            if ($this->assignLang($text)) {
                return $this->assignProvider($parser->getHeaders(), $text);
            }
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return ['marriott', 'loews', 'goldpassport', 'rosewood', 'ichotelsgroup', 'omnihotels',
            'triprewards', 'aplus', 'twa', 'wynnlv', 'hhonors', 'hoxton', 'sonesta', 'fseasons', 'shangrila',
            'uniorlres', 'dorchester', 'montage', 'yotel', 'iberostar', ];
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subjectEmail = $parser->getSubject();
        $this->fromEmail = implode('', $parser->getFrom());

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!empty($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug("Can't determine a language");

                        return $email;
                    } else {
                        $this->assignProvider($parser->getHeaders(), $text);
                        $this->parseEmailPdf($email, $text);
                    }
                }
            }
        }

        $email->setType('InvoicePDF' . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public function addQ($text)
    {
        return preg_replace("#(\w+\s)#u", "(?:$1)?", $text);
    }

    private function detectBody($body): bool
    {
        foreach (self::$detectors as $phrases) {
            foreach ($phrases as $word) {
                if (stripos($body, $word) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body): bool
    {
        foreach (self::$dictionary as $lang => $words) {
            foreach ($words["Arrival"] as $word) {
                if (stripos($body, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignProvider($headers, string $text): bool
    {
        if (empty($headers['subject'])) {
            $headers['subject'] = 'no subject';
        }

        $this->subjectEmail = $headers['subject'];

        if (stripos($headers['from'], '@marriott.com') !== false || stripos($headers['from'], '@marriotthotels.com') !== false
            || strpos($headers['subject'], 'Marriott') !== false
            || stripos($text, '@marriott.com') !== false || stripos($text, '@marriotthotels.com') !== false
            || stripos($text, 'marriott.com/') !== false || stripos($text, '.marriott.com') !== false
            || stripos($text, 'marriotthotels.com/') !== false || stripos($text, '.marriotthotels.com') !== false

            || stripos($headers['from'], '@ritzcarlton.com') !== false
            || strpos($headers['subject'], 'Ritz-Carlton') !== false
            || stripos($text, 'RITZCARLTON.COM') !== false
        ) {
            $this->providerCode = 'marriott';

            return true;
        }

        if (stripos($headers['from'], '@universalorlando.com') !== false
            || stripos($text, 'www.universalorlando.com') !== false
        ) {
            // before loews (universalorlando emails contains text 'www.loewshotels.com')
            $this->providerCode = 'uniorlres';

            return true;
        }

        if (stripos($headers['from'], '@loewshotels.com') !== false
            || stripos($text, 'www.loewshotels.com') !== false
        ) {
            $this->providerCode = 'loews';

            return true;
        }

        if (stripos($headers['from'], '@hyatt.com') !== false || strpos($headers['subject'], 'Hyatt') !== false
            || stripos($text, '@hyatt.com') !== false || stripos($text, 'Visit worldofhyatt.com') !== false
            || stripos($text, '.hyatt.com') !== false || strpos($text, 'Hyatt') !== false
        ) {
            $this->providerCode = 'goldpassport';

            return true;
        }

        if (stripos($headers['from'], '@rosewoodhotels.com') !== false
            || strpos($headers['subject'], 'Rosewood') !== false
            || stripos($text, 'rosewoodhotels.com') !== false
        ) {
            $this->providerCode = 'rosewood';

            return true;
        }

        if (stripos($headers['from'], '@ihg.com') !== false
            || strpos($headers['subject'], 'InterContinental') !== false
            || stripos($text, 'www.ihg.com') !== false
            || stripos($text, 'www.hiexpress.com') !== false
            || stripos($text, 'www.ihgrewardsclub.com') !== false
            || stripos($text, 'IHG Hotels Limited') !== false
            || preg_match('/^[ ]*(?:Holiday Inn|Staybridge Suites) .{2}/im', $text) > 0
        ) {
            $this->providerCode = 'ichotelsgroup';

            return true;
        }

        if (stripos($headers['from'], '@omnihotels.com') !== false
            || stripos($headers['from'], 'www.omnihotels.com') !== false
            || stripos($headers['subject'], 'Omni Hotel Stay') !== false
            || stripos($headers['subject'], 'folio from the Omni') !== false
            || stripos($text, 'www.omnihotels.com') !== false
            || preg_match("/^[ ]+Omni .+ (?-i){$this->opt($this->t("Room No:"))}/im", $text) > 0
        ) {
            $this->providerCode = 'omnihotels';

            return true;
        }

        if (stripos($headers['from'], '@WYNHG.COM') !== false
            || strpos($headers['subject'], 'AMERICINN') !== false
            || stripos($text, 'www.wyndhamrewards.com') !== false
            || stripos($text, 'Wyndham Rewards') !== false
        ) {
            $this->providerCode = 'triprewards';

            return true;
        }

        if (stripos($headers['from'], '@accor.com') !== false
            || strpos($headers['subject'], 'Invoice from Hotel Grand Windsor') !== false
            || stripos($text, '@accor.com') !== false
            || stripos($text, '@hotelgrandwindsor.com') !== false
        ) {
            $this->providerCode = 'aplus';

            return true;
        }

        if (stripos($headers['from'], '@twahotel.com') !== false
            || stripos($headers['subject'], 'Your Folio from the TWA Hotel') !== false
        ) {
            $this->providerCode = 'twa';

            return true;
        }

        if (stripos($headers['from'], '@wynnlasvegas.com') !== false
            || stripos($headers['from'], '.wynnlasvegas.com') !== false
            || strpos($headers['subject'], 'at Wynn Las Vegas') !== false
        ) {
            $this->providerCode = 'wynnlv';

            return true;
        }

        if (strpos($text, 'choosing Hilton Garden') !== false
        ) {
            $this->providerCode = 'hhonors';

            return true;
        }

        if (strpos($text, 'The Hoxton') !== false
        ) {
            $this->providerCode = 'hoxton';

            return true;
        }

        if (strpos($text, 'Shangri-La') !== false
            || strpos($text, 'Shangri - La') !== false
            || strpos($text, 'Shangri-la') !== false
        ) {
            $this->providerCode = 'shangrila';

            return true;
        }

        if (strpos($text, 'dorchestercollection.com') !== false) {
            $this->providerCode = 'dorchester';

            return true;
        }

        if (strpos($text, 'www.fourseasons.com') !== false
            || strpos($text, 'Four Seasons Resort') !== false
            || strpos($text, '@fourseasons.com') !== false
            || strpos($text, ' Thank you for staying at the Four Seasons') !== false
        ) {
            $this->providerCode = 'fseasons';

            return true;
        }

        if (strpos($text, 'Sonesta') !== false
        ) {
            $this->providerCode = 'sonesta';

            return true;
        }

        if (strpos($headers['subject'], 'stay at Montage') !== false
        ) {
            $this->providerCode = 'montage';

            return true;
        }

        if (strpos($headers['subject'], ' YOTEL ') !== false
            || strpos($text, ' YOTEL ') !== false
        ) {
            $this->providerCode = 'yotel';

            return true;
        }

        if (stripos($headers['from'], 'iberostar.com') !== false) {
            $this->providerCode = 'iberostar';

            return true;
        }
        // don't forget added provider code to function getEmailProviders

        return false;
    }

    private function parseEmailPdf(Email $email, $text): void
    {
        $r = $email->add()->hotel();

        // remove garbage
        // $text = preg_replace("/\n[ ]*This Hotel is Owned and Operated by .{2,}\n/i", "\n\n", $text);

        //Confirmation number
        if (preg_match("/(?:^[ ]*|[ ]{2})({$this->opt($this->t("Booking #"))})[: ]*\b(?-i)([\dA-Z]+\d+[\dA-Z\-]*)(?:[ ]{2}| *\(|$)/im", $text, $m)) {
            $r->general()->confirmation($m[2], $m[1]);
        } else {
            $r->general()->noConfirmation();
        }

        // Invoice Date
        if (preg_match("/{$this->opt($this->t("Invoice Date"))}\s+:\s+(.+?\d+)\s*\n/im", $text, $m)) {
            $r->general()->date2($this->normalizeDate($m[1]));
        }

        //Travellers
        $guestName = null;
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        if (preg_match("/^\s*{$this->opt($this->t('Guest Name'))}\s+({$patterns['travellerName']})[ ]*(?:Reference|TA Locator|Ref. agence)/m", $text, $m)
            || preg_match("/[ ]{0,10}(?:MR |Mr |Miss |Mrs |MRS )([[:upper:]][[:lower:]]+(?: [[:upper:]][[:lower:]]+){1,2})[ ]{5,}{$this->opt($this->t('Tax Invoice'))}/i", $text, $m)
            || preg_match("/{$this->opt($this->t("Guest Name"))}\s*[:]*\s*(['A-z&,\s\-]+?)(?:[ ]{2}|\n)/i", $text, $m) && stripos($m[1], 'GUESTS') === false
            || preg_match("/({$this->opt($this->t("Mr/Mrs"))}[.]? .+?)(?:[ ]{2}|\n)/", $text, $m) && preg_match("/^{$patterns['travellerName']}$/u", $m[1])
            || preg_match("/(?:^|\n{3}|\b\d{2}-\d{2}-\d{2}\n{1,3})[ ]{0,10}(?:MR |Mr |Miss |Mrs |MRS )?([[:upper:]][[:lower:]]+(?: [[:upper:]][[:lower:]]+){1,2})[ ]{5,}(?:{$this->opt($this->t('Folio No:'))}|{$this->opt($this->t('Room No:'))}).*\n[ ]{0,10}\S/u", $text, $m)
            || preg_match("/^{$this->opt($this->t('Dear'))}\s+({$patterns['travellerName']})\s*[,:;!?]/", $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Dear')]"), $m)
            || preg_match("/Guest Bill for\s+({$patterns['travellerName']})\s+at/iu", $this->subjectEmail, $m) // it-97866261.eml
            || preg_match("/Name\s*[:]*\s*(['A-z,\s]+?)(?:[ ]{2}|\n)/i", $text, $m) && stripos($m[1], 'GUESTS') === false
        ) {
            $guestName = $m[1];
        }

        if (!empty($guestName)) {
            if (stripos($guestName, '&') !== false) {
                $r->general()->travellers(explode("&", $guestName));
            } else {
                $r->general()->traveller(preg_replace("/{$this->opt($this->t('Mr/Mrs'))}\.*/", "", $guestName), true);
            }
        }

        if (preg_match("/Cancellation & No-Show Policy:\s*(.+hours prior to arrival.+)\n/", $text, $m)) {
            $r->general()
                ->cancellation($m[1]);
        }

        //Accounts
        if (preg_match("/(?:^|[ ]{2}){$this->opt($this->t("Membership"))}[: ]+(?:LP|PC|SG)?\s*([A-Z\d]{4,12})(?:[ ]{2}|$)/im", $text, $m)) {
            if (stripos($m[1], 'Number') === false && stripos($m[1], 'Page') === false) {
                $r->program()->account($m[1], preg_match("/^XXXX/", $m[1]) > 0);
            }
        }

        //CheckIn
        if (preg_match("/" . $this->opt($this->t("Arrival")) . "\s+(\d{4}[\s\/.-](?:[A-z]{3}|\d{1,2})[\s\/.-]\d{2,4})\b/ims", $text, $m)
            || preg_match("/(?: {3,}|\n\s*)" . $this->opt($this->t("Arrival")) . " *:? *((?:\S ?)*\d{2}(?: ?\S)*)(?: {2,}|\n)/i", $text, $m)
            || preg_match("/" . $this->opt($this->t("Arrival")) . "\s*Folio.+?\n(\d{1,2}[\s\/.-](?:[A-z]{3}|\d{1,2})[\s\/.-]\d{2,4})\b/im", $text, $m)
            /*                                                                       :   01-19-25
                AJR Number                                                  Arrival
                146 Juniper Way
                Ocala 34480          Group Code                             Departure : 01-24-25 */
            || preg_match("/\n.{70,} {3,}\W? *(\d.{5,10})\n.{70,} {3,}" . $this->opt($this->t("Arrival")) . "\n(?:.{0,70}\n)?.{70,} {3,}" . $this->opt($this->t("Departure")) . " *\W? *\d.+/iu", $text, $m)
            || preg_match("/" . $this->opt($this->t("Arrival")) . "\D+?(\d{1,2}[\s\/.-](?:[A-z]{3}|\d{1,2})[\s\/.-]\d{2,4})\b/ims", $text, $m)
        ) {
            if (preg_match("/\s" . $this->opt($this->t("Departure")) . ".*/is", $m[0])
                && !preg_match("/" . preg_quote($m[1], '/') . ".+\s" . $this->opt($this->t("Departure")) . ".*/is", $m[0])
                && !preg_match("/^\s*" . $this->opt($this->t("Arrival")) . " {2,}" . $this->opt($this->t("Departure")) . "/i", $m[0])
            ) {
                $m[1] = null;
            }

            if ((stripos($m[1], '-') !== false || stripos($m[1], '/') !== false)
                && preg_match_all('/^ {0,15}(\d{1,2})\s*[-\\/]\s*(\d{1,2})\s*[-\\/]\s*\d{2,4} {3,}/m', $text, $dateMatches)) {
                if (max($dateMatches[1]) > 12) {
                    $this->enDatesInverted = true;
                } elseif (max($dateMatches[2]) > 12) {
                    $this->enDatesInverted = false;
                } elseif (count(array_unique($dateMatches[1])) > count(array_unique($dateMatches[2]))) {
                    $this->enDatesInverted = true;
                }
            }

            $r->booked()->checkIn2($this->normalizeDate($m[1]));
        }

        //CheckOut
        if (
            preg_match("/\n *" . $this->opt($this->t("Arrival")) . " {2,}" . $this->opt($this->t("Departure")) . "(?: .+)\s*"
                . "\n *(?:\d{1,2}[\- ]{1,3}(?:[A-z]{3}|\d{1,2})[\- ]{1,3}\d{2,4}) {2,}(\d{1,2}[\- ]{1,3}(?:[A-z]{3}|\d{1,2})[\- ]{1,3}\d{2,4})/ims", $text, $m)
            || preg_match("/(?: {3,}|\n\s*)" . $this->opt($this->t("Departure")) . " *\W? *((?:\S ?)*\d{2}(?: ?\S)*)(?: {2,}|\n)/iu", $text, $m)
            || preg_match("/" . $this->opt($this->t("Departure")) . "\s+(\d{4}[\s\/.\-](?:[A-z]{3}|\d{1,2})[\s\/.\-]\d{2,4})/ims", $text, $m)
            || preg_match("/" . $this->opt($this->t("Departure")) . "(?:[^\d\n]* +|(?:\n.* {2,}))(\d{1,2}[ \/.\-](?:[A-z]{3}|\d{1,2})[ \/.\-]\d{2,4})/im", $text, $m)
        ) {
            $r->booked()->checkOut2($this->normalizeDate($m[1]));
        }

        if (empty($r->getCheckOutDate())) {
            if (preg_match("/" . $this->opt($this->t("Departure")) . ".+?(?:\s+[X\s\d]+)?(\d{1,2}[\s\/.-](?:[A-z]{3}|\d{1,2})[\s\/.-]\d{2,4})/im", $text,
                $m)) {
                $r->booked()->checkOut2($this->normalizeDate($m[1]));
            }
        }

        if ($r->getCheckOutDate() < $r->getCheckInDate()) {
            $this->enDatesInverted = true;
            $r->booked()->checkOut2($this->normalizeDate($m[1]));
        }

        $timeText = $this->re("/(Our rooms are available from.+)/u", $text);

        if (preg_match("/from\s*(?<timeIn>\d+\s*[a\.p]+m\.).+to (?<timeOut>\d+\s*[a\.p]+m\.)/u", $timeText, $m)
            || preg_match("/ETA\:\s*(?<timeIn>[\d\.]+\s*[ap]+m).*\n*.*ETD\:\s*(?<timeOut>[\d\.]+\s*[ap]+m?)/u", $text, $m)
        ) {
            $r->booked()
                ->checkIn(strtotime($m[1], $r->getCheckInDate()))
                ->checkOut(strtotime($m[2], $r->getCheckOutDate()));
        }

        //Phone
        $patterns['phone'] = '[+(\d][-+. \d)(]{5,}[\d)]'; // (+374) 10 599 000    |    +61 2 8099 1234

        if (preg_match("/\b{$this->opt($this->t("Tel:"))}[: ]*({$patterns['phone']})(?:[ ]{2}| ?\b{$this->opt($this->t("Fax:"))}|$)/im", $text, $m)
            || preg_match("/\b{$this->opt($this->t("Tel:"))}(?:[: ]*|\s?)({$patterns['phone']})\s\|/i", $text, $m)
            || preg_match("/\s{$this->opt($this->t("Tel:"))}(?:[: ]*|\s+)({$patterns['phone']})\s/", $text, $m)
        ) {
            if (strlen($m[1]) < 30) {
                $r->hotel()->phone($m[1]);
            }
        }

        //Fax
        if (preg_match("/{$this->opt($this->t("Fax:"))}[: ]*({$patterns['phone']})(?:[ ]{2}|$|[ ]*Website:| www\.| ?{$this->opt($this->t("Toll-Free:"))})/im", $text, $m)) {
            $r->hotel()->fax($m[1]);
        }

        //Hotel Name | Hotel Address

        $hotelName = $address = null;

        $patterns['hotelBrands'] = "(?i)(?:Holiday Inn|IBIS FEIRA DE SANTANA|Hotel Ibis Amsterdam Airport|Staybridge Suites|Days Inn|Day's Inn|Las Ventanas|The Ritz|Ritz-Carlton|Hilton|Rosewood|Hyatt|Grand Hyatt|Mercure Hotel|TWA Hotel|\bWynn\b|Hotel Grand|Hotel Indigo|ibis Brisbane Airport|InterContinental|One Nob Hill|\bOmni\b|70 Park Avenue Hotel|ibis Styles Brisbane Elizabeth Street|Ibis Brussels Centre Gare Midi|ibis Genève Aéroport|Hôtel Investissements & Management SA)(?-i)";

        // Ritz-Carlton
        if (stripos($this->subjectEmail, 'Ritz-Carlton') !== false || stripos($this->subjectEmail, 'Intercontinental') !== false) {
            if (preg_match("/(\b[^:\d]*Ritz-Carlton[^:\d]*?)\s+-\s+[[:alpha:]][-.'[:alpha:] ]*[[:alpha:]]\s+Recent\s+Stays/iu", $this->subjectEmail, $m)
                || preg_match("/(\b[^:\d]*Ritz-Carlton[^:\d]*?)(?:\s+-)?\s+(?:Hotel|Guest|Stay)\s+Folio(?:\s+From| for [\d\.]+\s*$|$)/i", $this->subjectEmail, $m)
                || preg_match("/Folio\s+from\s+(\D{3,}?)\s+for/", $this->subjectEmail, $m)
                || preg_match("/Folio\s+from\s+(\D{3,})/u", $this->subjectEmail, $m)
            ) {
                $hotelName = preg_replace("/^\s*<\s*(.+?)\s*>\s*/", '$1', $m[1]);
            }

            if ($hotelName && preg_match("/{$this->addQ($hotelName)}\.?\,?\s+((?:.+\n+){1,4}?.*?)[ ]+(?-i){$this->opt($this->t('Tel:'))}/i", $text, $m)) {
                $address = $m[1];
            }

            if (empty($address)) {
                if (preg_match("/\n{3,}(?<address>\D+.+?)\s(?<phone>\d{3}\.\d{3}\.\d{3,})?[ ]*(?:WWW\.)?RITZCARLTON\.COM/i", $text, $m)) {
                    $address = $m['address'];

                    if (empty($r->getPhone()) && !empty($m['phone'])) {
                        $r->hotel()->phone($m['phone']);
                    }
                }
            }

            if (empty($address)) {
                $address = $this->re("/\n{3,}[ ]+([A-Z]\D+.+)\s(?i){$this->opt($this->t('Tel:'))}/", $text);
            }

            if (empty($address)) {
                $address = $this->re("/\n{3,}[ ]+(\d+\D+.+)\s+(?i){$this->opt($this->t('Tel:'))}/", $text);
            }
        }

        if (stripos($this->subjectEmail, 'Four Seasons') !== false) {
            if (preg_match("/^(.+)\,\s*A\s*Four Seasons Hotel\s*(?:\-|\–)/u", $this->subjectEmail, $m)
                || preg_match("/Four\s+Seasons\s+(\D{3,})\s+(?:\-|\–)/u", $this->subjectEmail, $m)
            ) {
                $hotelName = $m[1];
                $r->setChainName('Four Seasons');
            }

            if (preg_match("/\s+(?<address>\d.+)\s+Tax ID\:.+\n\s+Tel\.\s+(?<phone>[+\d\s]+)\s+Fax\.\s*(?<fax>[+\d\s]+)\s+Email\:/", $text, $m)) {
                $address = $m['address'];
                $r->setPhone($m['phone']);
                $r->setFax($m['fax']);
            }

            if (preg_match("/\s+(?<address>.+)\n\s+TEL\:\s+(?<phone>[+\d\s\(\)\-]+)\s+www\.fourseasons.com/", $text, $m)) {
                $address = $m['address'];
                $r->setPhone($m['phone']);
            }

            if (preg_match("/\s+(?<address>\d.+)\s+-\s+TEL\.\s*\:\s*(?<phone>[\s\d]+)\s+\-\s*FAX\s*\:\s*(?<fax>[\d\s]+)\n/", $text, $m)) {
                $address = $m['address'];
                $r->setPhone($m['phone']);
                $r->setFax($m['fax']);
            }

            if (preg_match("#fourseasons.com/boston#", $text, $m)) {
                if (preg_match("/\n+\s+(?<address>\d.+U\.S\.A\.)\n+\s+Tel\:\s+(?<phone>[\(\)\s\-\d]+)\s+Fax\:\s*(?<fax>[\(\)\s\-\d]+)/", $text, $m)) {
                    $address = $m['address'];
                    $r->setPhone($m['phone']);
                    $r->setFax($m['fax']);
                }
            }

            if (preg_match("#fourseasons.com/guangzhou#", $text, $m)) {
                if (preg_match("/\n+\s+(?<address>\d.+\d{4,})\n+\s+.*\n\s*TEL\s*\D{1,2}[：](?<phone>[\(\)\s\d\-]+)\s+FAX\s*\D{1,2}[：](?<fax>[\(\)\s\d\-]+)\n/u", $text, $m)) {
                    $address = $m['address'];
                    $r->setPhone($m['phone']);
                    $r->setFax($m['fax']);
                }
            }

            if (preg_match("#http://www.fourseasons.com/seychelles#", $text, $m)) {
                if (preg_match("/\n+\s+(?<address>.+SEYCHELLES)\n+\s*Tel\:\s*(?<phone>[+\d\-]+)\s*Fax\:\s+(?<fax>[+\d\-]+)/", $text, $m)) {
                    $address = $m['address'];
                    $r->setPhone($m['phone']);
                    $r->setFax($m['fax']);
                }
            }

            if (preg_match("#CZECH REPUBLIC#", $text, $m)) {
                if (preg_match("/\n+\s+(?<address>.+)\n+\s+.*\n\s*TEL\:(?<phone>[\(\)\s\d\-\+]+)\s+FAX:\s+(?<fax>[\(\)\s\d\-\+]+)/", $text, $m)) {
                    $address = $m['address'];
                    $r->setPhone($m['phone']);
                    $r->setFax($m['fax']);
                }
            }

            if (preg_match("#www.fourseasons.com/manelebay#", $text, $m)) {
                if (preg_match("/\n+\s+(?<address>.+)\n\s*Tel\.?(?<phone>[\(\)\s\d\-\+]+)\D+Fax\s+(?<fax>[\(\)\s\d\-\+]+)/u", $text, $m)) {
                    $address = $m['address'];
                    $r->setPhone($m['phone']);
                    $r->setFax($m['fax']);
                }
            }

            if (preg_match("/Resort Bora Bora\,?\s*(?<address>.+)\n\s+www\.fourseasons\.com/", $text, $m)) {
                $address = $m['address'];
            }
        }
        // Sonesta
        if (preg_match("/\n\n +(.*\bSonesta\b.+)\n(?<address>.+?) *\| *Telephone: *(?<phone>.+)/iu", $text, $m)
        ) {
            $hotelName = $m[1];
            $address = trim($m['address']);
            $r->hotel()
                ->phone($m['phone']);
        }

        // uniorlres (Universal Orlando Resort)
        if (preg_match("/\n\n *(?<address>.+?) +T: *(?<phone>[\d \(\)\-\+]+?) +F: *(?<fax>[\d \(\)\-\+]+)(?: .*)?\n *www\.loewshotels\.com *www\.universalorlando\.com\s*$/iu", $text, $m)
        ) {
            $hotelName = 'Universal Orlando Resort';
            $address = trim($m['address']);
            $r->hotel()
                ->phone($m['phone']);
        }

        if (preg_match("/TCC\sHotel\sCollection\sCo\.,\sLtd\.\s+([A-Za-z,\s\d]+)\s+Tel:/s", $text, $match)) {
            $address = trim($match[1]);

            if (preg_match("/GUEST\sFOLIO\s+(\D+)\s+{$address}/s", $text, $match)) {
                $hotelName = $match[1];
            }
        }

        if (preg_match("/Thank you for choosing\s*(?<name>The Ritz-Carlton)\s*(?<address>[A-z\-\s\,]+)\n/", $text, $match)) {
            $hotelName = $match['name'];
            $address = $match['address'];
        }

        // it-187696490.eml
        if ((empty($hotelName) || empty($address))
            && preg_match("/^[ ]*Generate PDF to Print\n+([\s\S]+?)\n+[ ]*Stay Charges:(?:[ ]{2}|$)/im", $text, $m)
        ) {
            $table = $this->splitCols($m[1]);

            if (count($table) > 1 && preg_match("/^{$this->opt($this->t("Room No:"))}.+/s", $table[1])
                && preg_match("/^(?<name>.*{$patterns['hotelBrands']}.*)(?<address>(?:\n.{2,}){1,3}?)(?:\n[ ]*{$this->opt($this->t('Tel:'))}|\n[ ]*{$this->opt($this->t('Fax:'))}|\n\n|$)/", $table[0], $m2)
            ) {
                $hotelName = $m2['name'];
                $address = preg_replace('/[ ]*\n+[ ]*/', ', ', trim($m2['address']));

                if (empty($r->getPhone()) && preg_match("/\n[ ]*{$this->opt($this->t('Tel:'))}[: ]*({$patterns['phone']})$/m", $table[0], $m3)) {
                    $r->hotel()->phone($m3[1]);
                }

                if (empty($r->getFax()) && preg_match("/[\n,;][ ]*{$this->opt($this->t('Fax:'))}[: ]*({$patterns['phone']})$/m", $table[0], $m3)) {
                    $r->hotel()->fax($m3[1]);
                }
            }
        }

        if (empty($hotelName) && empty($address)) {
            if (preg_match("/\n+\s+(?<address>.+Los Cabos.+)\n\s+TEL\:\s+(?<phone>[+\d]+\s+[\(\)\d]+\s+[\d\-]+)/", $text, $m)) {
                $hotelName = 'Costa Palmas';
                $address = $m['address'];
                $r->hotel()
                    ->phone($m['phone']);
            }
        }

        // it-66330600.eml
        if (empty($hotelName) && in_array($this->subjectEmail, ['Las Ventanas al Paraiso Final Folio'])) {
            $hotelName = trim(str_ireplace('Final Folio', '', $this->subjectEmail));

            if (empty($address)) {
                $address = $this->re("/-\s+(\D+.+?)\s+{$this->opt($this->t('Tel:'))}/", $text);
            }
        }

        if (empty($hotelName) && preg_match("/Requested folio for your recent stay at (Montage .+) from /", $this->subjectEmail, $m)) {
            $hotelName = $m[1];

            if (empty($address)) {
                $address = $this->re("/\n *(\S.+?) +\\/ +{$this->opt($this->t('Tel:'))}/", $text);
            }
        }

        if (empty($hotelName)
            && preg_match("/^\s*Invoice\s*\n {0,3}(?<name>YOTEL (\S ?)+)\n(?<address>( {0,3}(\S ?)+\n){1,6}?)\n/", $text, $m)) {
            $hotelName = $m['name'];
            $address = $m['address'];
        }

        if (empty($hotelName)
            && preg_match("/Registered\s+Office:.*?(?:\n.*)? (YOTEL .+?) \| (.+?(?:\n.*)?)VAT No:/", $text, $m)) {
            $hotelName = $m[1];
            $address = $m[2];
        }

        if (empty($hotelName) && preg_match("/\n\n\n *(?<address>.+) - t\. *(?<phone>\d[\d ]{5,}) - f\. *(?<fax>\d[\d ]{5,}) - thehoxton\.com\n/", $text, $m)) {
            // 81 Great Eastern Street, London, EC2A 3HU - t. 020 7550 1000 - f. 020 7550 1090 - thehoxton.com
            // The Hoxton is a trading name of The Hoxton (Shoreditch) Limited (Registered in England and Wales under Company Number 03850699)
            // VAT 152 089 613
            $hotelName = 'The Hoxton';
            $address = $m['address'];

            $r->hotel()
                ->phone($m['phone'])
                ->fax($m['fax']);
        }

        if (empty($hotelName) && preg_match("/\n\n *(?<name>The Hoxton, .+?) \\| (?<address>.+ \d{5}) \\| thehoxton\.com\n/", $text, $m)) {
            // The Hoxton, Downtown LA | 1060 S Broadway | Los Angeles, CA 90015 | thehoxton.com
            $hotelName = $m['name'];
            $address = $m['address'];
        }

        if (empty($hotelName) && empty($address)
            && (preg_match("/^(?:\s*Invoice[ ]*\n+)?((?:.+\n+){1,5}?)(?:[ ]+{$this->opt($this->t("Tel:"))}|[ ]+\+\d[-\d() ]+?(?:[ ]{2}|\n)|\n\n.*\b{$this->opt($this->t("Arrival"))}.*\d)/i", $text, $m) // document top
                || preg_match("/\n{4,}((?:.+\n+){1,4}?)[ ]+(?:{$this->opt($this->t("Tel:"))}|\+\d+[\d()\-\s]+?)[ :].*(?i)(?:\n{1,2}.*(?:Owned by|Operated by|Owned and Operated by|\bwww\.|@).*)?$/", $text, $m) // document bottom
            ) && preg_match("/^\s*(?<name>.{3,})(?:\n+[ ]*(?<address>[\s\S]{3,}))?/", $m[1], $matches)
        ) {
            if (!empty($matches['address'])) {
                if (preg_match("/{$patterns['hotelBrands']}/", $matches['name']) > 0) {
                    $hotelName = $matches['name'];
                }
                $address = preg_replace('/\s+/', ' ', trim($matches['address']));
            } elseif (stripos($matches['name'], '1 Amiryan Street, 0010 Yerevan, Armenia') !== false
                || stripos($matches['name'], '1 Amiryan street, Yerevan 0010, Armenia') !== false
            ) {
                $hotelName = 'Armenia Marriott Hotel Yerevan';
                $address = $matches['name'];
            }
        }

        if (empty($hotelName)) {
            if (preg_match("/" . $this->opt($this->t("hotel name detect")) . "\s*(.+)\s{2,}/i", $text, $m)) {
                if (stripos($m[1], "Hyatt") !== false) {
                    $hotelName = $m[1];
                }
            }
        }

        if (preg_match("#www.fourseasons.com/boston#", $text, $m)) {
            $hotelName = 'Hotel Boston';
        }

        if (empty($hotelName)) {
            if (preg_match("/{$this->opt($this->t('Thank you for your reservation in our'))}\s*(\D+)\.\s/", $text, $m)) {
                $hotelName = $m[1];
            }
        }

        if (empty($hotelName)) {
            if (preg_match("/^([A-Z\s&]+)\n[ ]{20,}\d+\s*[A-Z]+/", $text, $m)) {
                $hotelName = $m[1];
            }
        }
        //AMERICINN
        if (stripos($this->subjectEmail, 'AMERICINN') !== false) {
            $hotelName = $this->re("/^(?:Fwd:\s+)?Reservation\s+Folio\s+From\s+(\D+)\s?-?$/", $this->subjectEmail);
        }

        if (preg_match("/Shangri[\s\-]*La/iu", $this->subjectEmail)) {
            $hotelName = $this->re("#\n+\s*(.+)\s+\d+\/\d+\/\d{2}\s+\d+\:\d+\n+\s*DATE#ui", $text);

            if (stripos($hotelName, 'The Shard, London') !== false) {
                $address = '31 St Thomas Street, London SE1 9QU United Kingdom';
            }

            if (stripos($hotelName, 'Pudong Shangri-la, East Shanghai') !== false) {
                $address = '33 Fu Cheng Road, Pudong, Shanghai 200120 China';
            }

            if (empty($address) && stripos($text, 'www.shangri-la.com') !== false) {
                if (preg_match("/\n\n\n.\s*(?<address>.+)\s*Tel\s*(?<phone>[\(\)\s\d]+)\s+Fax\s*(?<fax>[\(\)\d\s]+)\s+www\.shangri\-la\.com/", $text, $m)) {
                    $address = $m['address'];
                    $r->setPhone($m['phone'])
                        ->setFax($m['fax']);
                }
            }
        }

        if (empty($hotelName) || stripos($hotelName, "Mr.") !== false) {
            if (preg_match("/^(\D+)\sStay\s+Folio\s+from\s+\d{2}-\d{2}-\d{2}$/", $this->subjectEmail, $m)
                || preg_match("/^(?:Fwd:\s)?Your\sHotel\sFolio\sfrom\s(\D+)$/", $this->subjectEmail, $m)
                || preg_match("/Guest Bill for\s+{$patterns['travellerName']}\s+at\s+(\D+)$/iu", $this->subjectEmail, $m)
                // Fwd: Sheraton Munich Arabellapark Hotel Copy of Stay Folio for 06.02.19
                || preg_match("/Fwd:\s+(.+?)\s+Hotel Copy/iu", $this->subjectEmail, $m)
                || preg_match("/Folio\s*from\s*(\D+)\s+for/iu", $this->subjectEmail, $m)
                // Rosewood Hotel Georgia Guest Folio
                || preg_match("/(\b[^:\d]*Rosewood[^:\d]*?)(?:\s+-)?\s+Guest Folio/i", $this->subjectEmail, $m)
            ) {
                $hotelName = $m[1];
            }
        }

        if (empty($address) && !empty($hotelName)) {
            if (preg_match("/{$this->addQ($hotelName)}\D+\s{4,}(.+)\s(?-i){$this->opt($this->t('Tel:'))}/i", $text, $m)
                || preg_match("/{$this->addQ($hotelName)}\.?\,?\s+((?:.+\n+){1,4}?.*?)[ ]+(?-i){$this->opt($this->t('Tel:'))}/i", $text, $m)
                || preg_match("/[ ]{10,}(.+)\s+[\d\-]+\n+\s+rosewoodhotels\.com/i", $text, $m)
            ) {
                $address = trim(preg_replace('/\s+/', ' ', $m[1]));
            }
        }

        if (!empty($address) && strlen($address) > 50) {
            if (preg_match("/\n\s+(?<Name>\D+)\|\s?(?<Adress>.+)\s?\|\s?{$this->opt($this->t('Tel:'))}\:/", $text, $m)) {
                $hotelName = $m['Name'];
                $address = $m['Adress'];
            }

            if (!empty($hotelName)
                && preg_match("/{$this->addQ($hotelName)}\D+\s{4,}(.+)\s{$this->opt($this->t('Tel:'))}/", $text, $m)
            ) {
                $address = $m[1];
            }

            if (preg_match("/\n{3,}\s+(\d+.+)\n?\s+{$this->opt($this->t('Tel:'))}/", $text, $m) && strlen($address) > 50) {
                $address = $m[1];
            }
        }

        if (empty($address) && empty($hotelName)) {
            if (preg_match("/\n[ ]*This Hotel is Owned and Operated by .{2,}\n[ ]*(?<name>\S.+)(?<address>(?:\n[ ]*[^+:\n]{3,}){1,2})$/i", $text, $m)
                || preg_match("/\n\s+(?<name>\D+)\|\s?(?<address>.+)\s?\|\s?{$this->opt($this->t('Tel:'))}\:/", $text, $m)
                || preg_match("/\n\s+(?<name>\D+)\|\s?(?<address>.+\|.+)\n+\s*T\s+/", $text, $m)
                || preg_match("/\n[ ]+(?<name>{$patterns['hotelBrands']})\s*\|\s*(?<address>.+)\s?\n\s*{$this->opt($this->t('Tel:'))}\:/", $text, $m)
            ) {
                $hotelName = $m['name'];
                $address = preg_replace('/\s+/', ' ', trim($m['address']));
            }
        }

        if (empty($hotelName) && empty($address)) {
            if (preg_match("/^(?<hotelName>The Dorchester|45 Park Lane|Coworth Park|Le Meurice|Hôtel Plaza Athénée|Hotel Principe di Savoia|Hotel Eden|The Beverly Hills Hotel|Hotel Bel-Air|The Lana)\n\s*(?<address>(?:.+\n){1,5})\s*Tel\s*(?<phone>[+][\d\s\-]+)\n\s*Fax\s*(?<fax>[+]\s*[\d\-\s]+)\n/iu", $text, $m)) {
                $hotelName = $m['hotelName'];
                $address = $m['address'];
                $r->hotel()
                    ->phone($m['phone'])
                    ->fax($m['fax']);
            }
        }

        if ($address) {
            // it-62624922.eml
            $address = preg_replace("/\s*{$this->opt($this->t('managerName'))}.+/", '', $address);
        }

        if (empty($hotelName)) {
            $hotelName = $this->re("/{$this->opt($this->t('Bill from your recent stay at'))}\s+(\D+)/", $this->subjectEmail);

            if (!empty($hotelName) && preg_match("/{$hotelName}\s*(?<address>.+)\b(?:Tel|ph )\s*\:?(?<phone>[\d\s\(\)\-\.]+)/us", $text, $m)) {
                $address = str_replace("\n", " ", preg_replace("/\s+/", " ", $m['address']));
                $r->hotel()
                    ->phone($m['phone']);
            }
        }

        if (empty($address) && !empty($hotelName)) {
            $address = $this->re("/{$hotelName}\s*\n(.+)\s*$/", $text);
        }

        if (empty($address) && !empty($hotelName)) {
            $tempHotelName = trim(str_replace(['–'], '', $hotelName));
            $address = $this->re("/{$tempHotelName}\s*\n(.+)\s*$/", $text);
        }

        if (empty($hotelName) && empty($address)) {
            if (preg_match("/^[ ]+(?<name>[A-z\s]+)\s+\-\s+(?<address>.+\d)\n+\s+{$this->opt($this->t('Tel:'))}\s*(?<phone>.+)\s+{$this->opt($this->t('Fax:'))}\s+(?<fax>.+\d)\s/um", $text, $m)
            || preg_match("/^[ ]+(?<name>[A-Z\s]+)\s+\–\s+(?<address>.+)\n+\s+{$this->opt($this->t('Tel:'))}\s*(?<phone>.+)\s\|/um", $text, $m)) {
                $hotelName = $m['name'];
                $address = $m['address'];
            }
        }

        if (empty($address)) {
            $address = $this->re("/\n\n(.+\n) +\b(?:TEL|tel\.|ph\b) ?\W+.* +(?:FAX|fax)\W+/", $text);
        }

        if (empty($address)) {
            $address = $this->re("/(.+\n)\s*TEL\:.+FAX\:.+/", $text);
        }

        if (empty($address)) {
            // bottom single row
            $address = $this->re("/\n{5,}\s*([A-Z]+.+[ ,]+\d{5})\s*$/", $text);
        }

        if (stripos($address, 'ICO:') !== false) {
            $address = '';
        }

        if (empty($hotelName)) {
            if (preg_match("/\bwynnlasvegas.com\b/i", $this->fromEmail, $m)) {
                $hotelName = 'Wynn Las Vegas';
                $address = null;
            }
        }

        if ((empty($hotelName) || empty($address))
            && preg_match("/\s+(?<hotelName>[A-Z].+)\n(?<address>(?:.+\n){1,2})\s*(?:Telephone|TELEPHONE|Tel|telephone no.|T)\s*\:?\s*(?<phone>[+\(\)\s\-\d]+)(?:\s+(?:Fax|FAX|fax no.)\:?\s*(?<fax>[\(\)\s\-\d]+))?/", $text, $m)) {
            $hotelName = $m['hotelName'];
            $address = $m['address'];
            $r->hotel()
                ->phone($m['phone']);

            if (isset($m['fax']) && !empty($m['fax'])) {
                $r->hotel()
                    ->fax($m['fax']);
            }
        }
        $r->hotel()->name($hotelName);

        if ($address) {
            $r->hotel()->address(preg_replace('/\s+/', ' ', trim($address)));
        } elseif (!preg_match("/\b(tel|phone|\bph\b)/i", $text)
            && !preg_match("/.*\b\d{5}\s*\n/i", $this->re("/^((\s*\S.+\n+){7})/", $text))
            && !preg_match("/.*\b\d{5}\s*(?:\n|$)/i", $this->re("/((\n+\S.+\s*){7})$/", $text))
        ) {
            $r->hotel()->noAddress();
        } else {
            $textRows = explode("\n", $text);

            if (preg_match("/{$this->opt($this->t("Arrival"))}/i", $textRows[0])
                && preg_match("/{$this->opt($this->t("Balance"))}/i", array_pop($textRows))
            ) {
                // it-97866261.eml
                $r->hotel()->noAddress();
            }
        }

        //Guests
        $guests = $this->re("/{$this->opt($this->t('No of Guests'))}[ ]*(?:[:]+[ ]+)?(.*\d.*)$/m", $text);

        if (preg_match("/^(\d{1,3})\s*\/\s*(\d{1,3})$/", $guests, $m)) {
            $r->booked()->guests($m[1])->kids($m[2]);
        } elseif (preg_match("/^\d{1,3}$/", $guests)) {
            $r->booked()->guests($guests);
        } elseif (preg_match("/^(\d+)\s+\D+$/", $guests, $m)) {
            $r->booked()->guests($m[1]);
        } elseif (preg_match("/{$this->opt($this->t('No of Guests'))}\s*(\d+)\s*Adults\D+(\d+)\s*Children/u", $text, $m)) {
            $r->booked()
                ->guests($m[1])
                ->kids($m[2]);
        }

        //ROOM INFO
        if (!empty($roomNumber = $this->re("/({$this->opt($this->t('Room No:'))}\s*(?:\:\s+)?\d+)/", $text))) {
            $room = $r->addRoom();
            $room->setDescription(preg_replace("/\s{2,}/", " ", trim($roomNumber, ':')));

            if (!empty($roomType = $this->re("/{$this->opt($this->t('Room Type:'))}\s+([\d\/\sA-Z\,]+)(?:\s{4,}|\s*Nights)/", $text))) {
                $room->setType($roomType);
            }

            if (preg_match("/Number of Rooms\s*(?:\:\s+)?(\d+)\s*(.+)[ ]{4,}/u", $text, $m)) {
                $room->setType($m[2]);
            }

            if (preg_match("/Rate per room\/night\:\s*([\d\.]+\s*[A-Z]{3})/", $text, $m)
                || preg_match("/Price per room \/ night\s*([A-Z]{3}\s*[\d\.]+)/", $text, $m)
                || preg_match("/Daily Rate\:\s*(.+)\s+GTD/", $text, $m)) {
                $room->setRate($m[1] . ' / night');
            }
        } else {
            if (preg_match("/(?:Number of Rooms)\s*(?:\:\s+)?(\d+)\s*(.+)[ ]{4,}/u", $text, $m)) {
                $r->booked()->rooms($m[1]);

                $room = $r->addRoom();
                $room->setType($m[2]);

                if (preg_match("/Rate per room\/night\:\s*([\d\.]+\s*[A-Z]{3})/", $text, $m)) {
                    $room->setRate($m[1] . ' / night');
                }
            }

            if (preg_match("/{$this->opt($this->t('No. of rooms'))}\s*(\d+)/u", $text, $m)) {
                $r->booked()->rooms($m[1]);

                if (preg_match("/Price per room[\/\s]+night\s*([A-Z]{3}\s*[\d\.]+)/", $text, $m)
                    || preg_match("/Room Rate\: \(Per Night\)\n*.*\n*^(\D\d[\d\,]+)[ ]{10,}/m", $text, $m)
                ) {
                    $room = $r->addRoom();
                    $room->setRate($m[1] . ' / night');
                }
            }

            if (preg_match("/{$this->opt($this->t('Room Category:'))}\s*(\D+)\n/", $text, $m)) {
                if (isset($room)) {
                    $room->setType($m[1]);
                } else {
                    $room = $r->addRoom();
                    $room->setType($m[1]);
                }
            }
        }

        //Price
        $cost = $this->re("/{$this->opt($this->t('Cost'))}\s+([\d\.\,]+)/s", $text);

        if (!empty($cost)) {
            $r->price()->cost(str_replace(",", "", $cost));
        }

        $tax = $this->re("/{$this->opt($this->t('Tax'))}\s+([\d\.\,]+)/s", $text);

        if (!empty($tax)) {
            $r->price()->tax(str_replace(",", "", $tax));
        }

        if (preg_match("/{$this->opt($this->t("Total"))}.+?\/\s*(?<currencyCode>[A-Z]{3})\s+(?<amount>\d[,.\'\d ]*?)\s*/im", $text, $m)
            || preg_match("/{$this->opt($this->t("Total"))}.+?\b(?<amount>\d[,.\'\d ]*?)[ ]*(?:(?<currencyCode>[A-Z]{3}\b)|[ ]{2}|$)/im", $text, $m)) {
            $r->price()->total(PriceHelper::parse($m['amount']));

            if (!empty($m['currencyCode'])) {
                $r->price()->currency($m['currencyCode']);
            } elseif (preg_match("/(?:{$this->opt($this->t("Balance"))}|Total Invoice)[ ]*(?:\d[,.\'\d ]{1,16})?(?<currencyCode>[A-Z]{3}\b)/", $text, $m)
                || preg_match("/(?:^|[ ]{2}){$this->opt($this->t("Invoice Currency"))}[: ]+(?<currencyCode>[A-Z]{3})(?:[ ]{2}|$)/m", $text, $m)
                || preg_match("/{$this->opt($this->t('Total Rates'))}\s+(?<currencyCode>[A-Z]{3})(?:[ ]{1}|$)/m", $text, $m)
            ) {
                $r->price()->currency($m['currencyCode']);
            }
        }

        if (preg_match("/\*Total\s+([A-Z]{3})\s+[$].+?([\d.,]+)(\s[A-Z]{3}|)/im", $text, $m)) {
            $r->price()
                ->currency($m[1])
                ->total(str_replace(",", "", $m[2]));
        }

        if (preg_match("/(?:\n| {5,})TOTAL IN ([A-Z]{3}) *(\d[\d.,]*)(?: +|\n)/im", $text, $m)) {
            $r->price()
                ->currency($m[1])
                ->total(str_replace(",", "", $m[2]));
        }

        if (empty($this->providerCode) && !empty($r->getHotelName())) {
            if (preg_match('/^(?:Holiday Inn|Staybridge Suites)/i', $r->getHotelName())) {
                $this->providerCode = 'ichotelsgroup';
            } elseif (preg_match('/^Omni\b.{2}/i', $r->getHotelName())) {
                $this->providerCode = 'omnihotels';
            } elseif (preg_match('/^TWA Hotel/i', $r->getHotelName())) {
                $this->providerCode = 'twa';
            }
        }

        $this->detectDeadLine($r);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate(?string $text)
    {
        if (empty($text) || !is_string($text)) {
            return '';
        }
        // $this->logger->debug('$text = '.print_r( $text,true));
        $in = [
            // 13.11.19
            '/^(\d{1,2})[.](\d{1,2})[.](\d{2})$/u',
            // 27/02/20
            '/^\s*(\d{1,2})[ ]*\/[ ]*(\d{1,2})[ ]*\/[ ]*(\d{2})\s*$/u',
            // 13-11-2020; 5/10/2022 (Tue)
            '/^(\d{1,2})[^\.\w](\d{1,2})[^\.\w](\d{4})(?:\s*\([[:alpha:]]+\))?\s*$/u', // always last!
            // 13-11-19
            '/^(\d{1,2})[^\.\w](\d{1,2})[^\.\w](\d{2})$/u', // always last!
        ];
        $out[0] = '$1.$2.20$3';
        $out[1] = $this->enDatesInverted ? '$1.$2.20$3' : '$1/$2/20$3';
        $out[2] = $this->enDatesInverted ? '$1.$2.$3' : '$2.$1.$3';
        $out[3] = $this->enDatesInverted ? '$1.$2.20$3' : '$2.$1.20$3';

        $str = preg_replace($in, $out, $text);

        if (!preg_match("/^\d{1,2}([.\/])\d{1,2}\1\d{4}$/", $str)) {
            // 02.02-25
            $str = preg_replace('/^(\d{1,2})[^\w](\d{1,2})[^\w](\d{2})$/u', $this->enDatesInverted ? '$1.$2.20$3' : '$2.$1.20$3', $str);
        }

        if (preg_match("#^(\d+)\.(\d+).(\d{2})$#", $str, $m)) {
            if (checkdate($m[2], $m[1], $m[3]) === false) {
                $out[2] = '$1.$2.20$3';
                $str = preg_replace($in, $out, $text);
            }
        }

        if (preg_match("#^(\d+)\.(\d+).(\d{4})$#", $str, $m)) {
            if ($m[2] > 12) {
                $str = $m[2] . '.' . $m[1] . '.' . $m[3];
            }
        }

        return $str;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/Reservations must be canceled no later than\s*(\d+)\s*hours prior to arrival/u', $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m[1] . ' hours');
        }
    }
}
