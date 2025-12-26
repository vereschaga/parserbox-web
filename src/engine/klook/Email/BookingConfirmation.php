<?php

namespace AwardWallet\Engine\klook\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Common\Parser\Util\PriceHelper;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "klook/it-428009126.eml, klook/it-889090215.eml, klook/it-527030667-de.eml, klook/it-887898560-id.eml";

    public $subjects = [
        'Pesanan yang dikonfirmasi:', // id
        'Pembayaranmu di Klook:', // id
        'Booking confirmed:',
        'Your booking confirmation for',
    ];

    public $lang = '';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "id" => [
            // PDF
            'Package'     => 'Paket Package',
            'Date'        => 'Tanggal',
            'Voucher no.' => 'No. Voucher',
            'addressEnd' => ['Silakan lihat'],
            // 'openingHoursEnd' => [''],
            'cancellation' => 'Kebijakan Pembatalan',

            // HTML
            // '' => '',
        ],
        "de" => [
            // PDF
            'Package'     => 'Paket',
            'Date'        => 'Datum',
            'Voucher no.' => 'Voucher-Nr.',
            // 'addressEnd' => [''],
            // 'openingHoursEnd' => [''],
            'cancellation' => 'Stornierungsbedingungen',

            // HTML
            // '' => '',
        ],
        "en" => [
            // PDF
            'Package' => 'Package',
            'Date'    => 'Date',
            // 'Voucher no.' => '',
            'addressEnd' => ['Please refer to', 'How to get there', 'Need help with this booking'],
            'openingHoursEnd' => ['Voucher type', 'How to redeem', 'Address', 'How to get there', 'Need help with this booking'],
            'cancellation' => 'Cancellation Policy',

            // HTML
            // '' => '',
        ],
    ];

    private $patterns = [
        'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52  |  (+351) 21 342 09 07  |  713.680.2992
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || strpos($headers['subject'], '[Klook]') === false)
        ) {
            return false;
        }

        foreach ($this->subjects as $phrase) {
            if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false)
                return true;
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectProv = false;
        $href = ['.klook.com/', 'click.klook.com'];

        if ($this->detectEmailFromProvider($parser->getCleanFrom()) === true
            || $this->http->XPath->query("//a[{$this->contains($href, '@href')} or {$this->contains($href, '@originalsrc')}]")->length > 0
            || $this->http->XPath->query('//text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Klook")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Website: www.klook.com")]')->length > 0
        ) {
            $detectProv = true;
        }

        if ($detectProv && $this->findRoots()->length > 0) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }
    
            if (strpos($text, 'KLOOK') === false
                && stripos($text, 'Please contact Klook customer service') === false
                && !preg_match("/(?:Klook's\s+Terms\s+of\s+Use|KLOOK\s+Nutzungsbedingungen)/i", $text) // en + de
            ) {
                continue;
            }

            $this->assignLangPdf($text);

            if ($this->detectPdf($text)) {
                return true;
            }
        }

        return false;
    }

    private function assignLangPdf(?string $text): bool
    {
        if ( empty($text) || !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['Package']) || empty($phrases['Date']) ) {
                continue;
            }
            if ($this->containsText($text, $phrases['Package'])
                && $this->containsText($text, $phrases['Date'])
            ) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function detectPdf($text): bool
    {
        if ($this->containsText($text, $this->tPlusEn('Package'))
            && ($this->containsText($text, $this->tPlusEn('Voucher no.'))
                || $this->containsText($text, ['Itinerary', 'Tap on the links to redeem your vouchers:', 'Scan the QR codes below to redeem your units individually'])
            )
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]klook\.com$/', $from) > 0;
    }

    private function findRoots(): \DOMNodeList
    {
        return $this->http->XPath->query("//*[{$this->eq(['Participation Date', 'id1' => 'Tanggal Partisipasi'], "translate(.,':','')")}]");
    }

    private function parseHtml(Email $email, array &$bookingIsJunk): void
    {
        // examples: it-889090215.eml, it-887898560-id.eml
        $this->logger->debug(__FUNCTION__ . '()');

        $htmlIsJunk = [];

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts(['Hey', 'id1' => 'Hai'])}]", null, "/^{$this->opt(['Hey', 'id1' => 'Hai'])}[,\s]+({$this->patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }

        $address = $this->http->FindSingleNode("//li[ preceding::text()[normalize-space()][1][{$this->eq(['Address'], "translate(.,':','')")}] and (following::text()[normalize-space()][1][{$this->starts(['How to get there', 'Please refer to'])}] or count(following-sibling::li[normalize-space()])=0) ]");

        $bookingRoots = $this->findRoots();

        if ($bookingRoots->length === 0) {
            $this->logger->debug('Booking root-nodes not found!');
        }

        foreach ($bookingRoots as $root) {
            $activityName = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]/ancestor::*[1]", $root);

            $isJunk = preg_match("/(?:(?i)\bSIM[- ]+Card\b(?-i)|\beSIM\b)/", $activityName);
            $htmlIsJunk[] = $isJunk;
            
            if ($isJunk) {
                continue;
            }

            $t = $email->add()->event();
            $t->setEventType(EVENT_SHOW);

            if ($traveller) {
                $t->general()->traveller($traveller);
            }

            $confirmation = $this->http->FindSingleNode("preceding::text()[normalize-space()][position()<7][{$this->eq(['Booking reference ID', 'id1' => 'ID Pesanan'], "translate(.,':','')")}]/following::text()[normalize-space()][1]", $root, true, '/^[-A-Z\d]{4,40}$/');
            $confirmationTitle = $this->http->FindSingleNode("preceding::text()[normalize-space()][position()<7][{$this->eq(['Booking reference ID', 'id1' => 'ID Pesanan'], "translate(.,':','')")}]", $root, true, '/^(.+?)[\s:：]*$/u');

            if (!$confirmation
                && preg_match("/^({$this->opt(['Booking reference ID', 'id1' => 'ID Pesanan'])})[:\s]+([-A-Z\d]{4,40})$/", $this->http->FindSingleNode("preceding::text()[normalize-space()][position()<7][{$this->starts(['Booking reference ID', 'id1' => 'ID Pesanan'])}]", $root), $m)
            ) {
                $confirmation = $m[2];
                $confirmationTitle = $m[1];
            }

            $t->general()->confirmation($confirmation, $confirmationTitle);

            $bookedDateVal = $this->http->FindSingleNode("preceding::text()[normalize-space()][position()<5][{$this->eq(['Booked', 'id1' => 'Tanggal Pemesanan'], "translate(.,':','')")}]/following::text()[normalize-space()][1]", $root, true, "/^.*\b\d{4}\b.*$/")
            ?? $this->http->FindSingleNode("preceding::text()[normalize-space()][position()<5][{$this->starts(['Booked', 'id1' => 'Tanggal Pemesanan'])}]", $root, true, "/^{$this->opt(['Booked', 'id1' => 'Tanggal Pemesanan'])}[:\s]+(.*\b\d{4}\b.*)$/");
            $t->general()->date(strtotime($bookedDateVal));

            $eventName = $activityName;
            $t->place()->name($eventName)->address($address ?? $eventName);

            $dateStartVal = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $root, true, "/^.*\b\d{4}\b.*$/");
            $dateStart = strtotime($dateStartVal);

            if ($dateStart) {
                $t->booked()->start($dateStart)->noEnd();
            }

            $guestCount = null;
            $countValues = [];
            $texts = $this->http->FindNodes("following::text()[normalize-space()][position()<4][{$this->eq(['Current bookings', 'id1' => 'Pemesanan saat ini'], "translate(.,':','')")}]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]/descendant::text()[contains(translate(normalize-space(),'0123456789', '∆∆∆∆∆∆∆∆∆∆'),'X ∆')]", $root);

            foreach ($texts as $txt) {
                if (preg_match("/^.*\S\s+X\s+(\d{1,3})$/", $txt, $m)) {
                    // $labelExamples = ['Dewasa', 'Anak'];
                    $countValues[] = $m[1];
                }
            }

            if (count($countValues) > 0) {
                $guestCount = (string) array_sum($countValues);
            }

            if ($guestCount !== null) {
                $t->booked()->guests($guestCount);
            }
        }

        $bookingIsJunk = array_merge($bookingIsJunk, $htmlIsJunk);

        if (count(array_unique($htmlIsJunk)) === 1 && $htmlIsJunk[0] === true) {
            return;
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq(['id1' => 'Jumlah yang Dibayar'])}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.’‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // Rp 12,761,843
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function parsePdf(Email $email, string $text, string $headerText, ?string $eventName): void
    {
        $this->logger->debug(__FUNCTION__ . '()');

        $t = $email->add()->event();
        $t->setEventType(EVENT_SHOW);

        if (preg_match("/(Booking reference ID)[ :]*\n+.+[ ]{10}([-A-Z\d]{4,40})\n/", $text, $m)) {
            $t->general()->confirmation($m[2], $m[1]);
        }

        $address = $this->parseAddress($text, $eventName);
        $t->place()->name($eventName)->address($address);

        $dateVal = null;

        if (preg_match("/Lead participant.*{$this->opt($this->tPlusEn('Date'))}\n+(?<traveller>{$this->patterns['travellerName']})\s*(?<date>\d{1,2}\s*[[:alpha:]]+\s*\d{4}\s*(?:{$this->patterns['time']})?)\n/u", $headerText, $m)) {
            $t->general()->traveller($m['traveller'], true);

            $dateVal = $m['date'];
        }

        $guestCount = $this->parseGuestCount($text, $headerText);
        $t->booked()->guests($guestCount);

        if (preg_match("/^[ ]*Itinerary(?:[ ]{2}|[ ]*[-\[(]|$)/im", $text) ) {
            $this->parsePdfTicket2($t, $text, $dateVal);
        } else {
            $this->parsePdfTicket($t, $text, $dateVal);
        }

        if (preg_match("/\n[ ]*({$this->opt(['Last admission', 'id1' => 'Kunjungan terakhir'])}[ ]*[:]+.{2,}(?:\n.+)?)(?:\n{2}|\n[ ]*Please check)/i", $text, $m)) {
            $t->setNotes(preg_replace('/^(.+?)[,.;!? ]*\n+[ ]*(.+)$/', '$1; $2', $m[1]));
        }

        $cancellation = $this->parseCancellation($text);
        $t->general()->cancellation($cancellation, false, true);
    }

    private function parsePdfTicket(Event $t, $textPDF, ?string $dateVal): void
    {
        // examples: it-428009126.eml
        $this->logger->debug(__FUNCTION__ . '()');

        $date = strtotime($dateVal);
        $timeBegin = $timeEnd = '';

        /*
            Monday-Sunday: 07:00-23:00
        */
        $patternOH = "[-[:alpha:]]+ ?- ?[-[:alpha:]]+[ ]*[:]+\s*(?<beg>{$this->patterns['time']}) ?- ?(?<end>{$this->patterns['time']})";

        $openingHoursText = $this->re("/\n[ ]*Opening Hours\n+([\s\S]*?)\n+[ ]*{$this->opt($this->tPlusEn('openingHoursEnd'))}/i", $textPDF);

        if (preg_match_all("/^[ ]*({$patternOH})/mu", $openingHoursText ?? '', $ohMatches)
            && count(array_unique($ohMatches[1])) === 1
        ) {
            $timeBegin = $ohMatches['beg'][0];
            $timeEnd = $ohMatches['end'][0];
        } elseif (preg_match("/\n[ ]*Opening Hours\n+[ ]*{$patternOH}/iu", $textPDF, $m)) {
            $timeBegin = $m['beg'];
            $timeEnd = $m['end'];
        }

        if ($date && !empty($timeBegin) && !empty($timeEnd)) {
            $t->setStartDate(strtotime($timeBegin, $date));
            $t->setEndDate(strtotime($timeEnd, $date));
        } else {
            $t->setStartDate($date);
            $t->setNoEndDate(true);
        }
    }

    private function parsePdfTicket2(Event $t, $textPDF, ?string $dateVal): void
    {
        // examples: it-527030667-de.eml
        $this->logger->debug(__FUNCTION__ . '()');

        $date = strtotime($dateVal);

        $itineraryText = $this->re("/\n[ ]*Itinerary(?:[ ]{2}.+|[ ]*[-\[(].*)?\n+([\s\S]+)$/", $textPDF);

        $timeBegin = $this->re("/(?:^\s*|\n[ ]*)(?:{$this->opt(['id1' => 'Mulai'])}[: ]+)?({$this->patterns['time']})[ ]*{$this->opt(['Departure', 'id1' => 'Keberangkatan'])}(?:[ ]{2}|[ ]*\(|\n|\s*$)/i", $itineraryText)
            ?? $this->re("/(?:^\s*|\n[ ]*){$this->opt(['Departure', 'id1' => 'Keberangkatan'])}(?:[ ]{2}.+|[ ]*\(.*)?\n+[ ]*({$this->patterns['time']})[ ]*Pick up/i", $itineraryText);
        $timeEnd = $this->re("/\n[ ]*(?:{$this->opt(['id1' => 'Mulai'])}[: ]+)?({$this->patterns['time']})[ ]*{$this->opt(['Return', 'id1' => 'Kembali'])}(?:[ ]{2}|[ ]*\(|\n|\s*$)/i", $itineraryText)
            ?? $this->re("/(?:^\s*|\n[ ]*){$this->opt(['Return', 'id1' => 'Kembali'])}(?:[ ]{2}.+|[ ]*\(.*)?\n+[ ]*({$this->patterns['time']})[ ]*To (?:hotel|personal address)/i", $itineraryText);

        if ($date && !empty($timeBegin) && !empty($timeEnd)) {
            $t->setStartDate(strtotime($timeBegin, $date));
            $t->setEndDate(strtotime($timeEnd, $date));
        } else {
            $t->setStartDate($date);
            $t->setNoEndDate(true);
        }
    }

    private function parseGuestCount($textPDF, ?string $headerText): ?string
    {
        $guestCount = $this->re("/{$this->opt('No. of participants')}\n+(\d{1,3})\b/", $textPDF);

        if ($guestCount === null
            && preg_match_all("/^[ ]*{$this->opt('Participant')}[ ]*(\d{1,3})$/im", $textPDF, $guestsMatches)
        ) {
            rsort($guestsMatches[1]);
            $guestCount = array_shift($guestsMatches[1]);
        }

        if ($guestCount === null && preg_match("/Quantity.*Booking reference ID[ :]*\n+[ ]*(.+?)\s*[-A-Z\d]{4,40}$/m", $headerText, $matches)) {
            $countValues = [];

            $listValues = preg_split('/(\s*,\s*)+/', $matches[1]);

            foreach ($listValues as $value) {
                if (preg_match("/^\s*(\d{1,3})\s+[Xx]\s+[^\s\d]/", $value, $m)) {
                    // $labelExamples = ['Admission Ticket', 'Child Ticket', 'Senior'];
                    $countValues[] = $m[1];
                }
            }

            if (count($countValues) > 0) {
                $guestCount = (string) array_sum($countValues);
            }
        }

        return $guestCount;
    }

    private function parseAddress($textPDF, ?string &$eventName): ?string
    {
        $address = null;

        if (preg_match("/\n[ ]*Keberangkatan\n+[ ]*(?:[[:alpha:]]+[ ]+)?{$this->patterns['time']}.*((?:\n+.{2,}){1,2}?)\n+[ ]*Titik keberangkatan/u", $textPDF, $m) // id
        ) {
            $address = preg_replace(['/([ ]*\n+[ ]*)+/', '/([ ]*,[ ]*)+/', '/\s+/'], [', ', ', ', ' '], trim($m[1]));
        }

        if (empty($address)) {
            $address = $this->re("/\n[ ]*{$this->opt(['Hotel & Address', 'id1' => 'Lokasi keberangkatan'])}\n+(.{3,})/", $textPDF);
        }

        $addressText = $this->re("/\n[ ]*{$this->opt(['Address', 'id1' => 'Alamat'])}\n+[ ]*(\S[\s\S]+?\S)\n+[ ]*{$this->opt($this->tPlusEn('addressEnd'))}/", $textPDF);
        $addressRows = preg_split("/([ ]*\n+[ ]*)+/", $addressText);

        if (count($addressRows) > 0 && count($addressRows) < 3 && !empty($addressRows[0])) {
            $address = implode(', ', array_map(function ($item) {
                return rtrim($item, ', ');
            }, $addressRows));
            $address = preg_replace(['/^(.*?)(?:\s*[-.;!])+$/', '/(?:\s*,\s*)+/', '/\s+/'], ['$1', ', ', ' '], $address);
        }

        if (empty($address) && !empty($eventName)
            && preg_match("/^(.{2,}?)\s+in\s+(.+)$/", $eventName, $matches)
        ) {
            $eventName = $matches[1];
            $address = $matches[2];
        }

        if (empty($address)) {
            $address = $this->re("/Address[ ]*[:]+\s*(.{3,120})$/m", $textPDF);
        }

        if (empty($address) && !empty($eventName)) {
            $address = preg_replace([
                '/^(?:(?:\([^()]*\)|\[[^\[\]]*\]|【[^【】]*】)\s*)+(.{2,})$/',
                '/^(.{2,}?)(?:\s*(?:\([^()]*\)|\[[^\[\]]*\]|【[^【】]*】))+$/',
                '/^(.{2,}?)(?:\s+(?:eTicket|Ticket|eVoucher|Voucher|drink coupons?|Coupons?))+$/i'
            ], '$1', $eventName);
        }

        return $address;
    }

    private function parseCancellation($text): ?string
    {
        return preg_match("/\n[ ]*{$this->opt($this->t('cancellation'))}\n{0,1}((?:\n.+){1,3}?)(?:\n\n|\s*$)/iu", $text, $m) ? preg_replace('/\s+/', ' ', trim($m[1])) : null;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $bookingIsJunk = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            $this->assignLangPdf($text);

            if ($this->detectPdf($text) !== true) {
                continue;
            }

            $headerText = $this->re("/^\n*([\s\S]+?)\n+[\n\D]*{$this->opt($this->tPlusEn('Voucher no.'))}[\n\D]*\n/", $text) ?? $text;

            $activityName = preg_match("/^\s*((?:.{2,}\n){1,2})\n*[ ]*{$this->opt($this->tPlusEn('Package'))}/", $headerText, $m) ? preg_replace('/\s+/', ' ', trim($m[1])) : null;
            $isJunk = preg_match("/(?:(?i)\bSIM[- ]+Card\b(?-i)|\beSIM\b)/", $activityName) // it-466877913.eml
                && preg_match("/(?:\n[ ]*|[ ]{2})(?:Service Time|SIM Card Validity)(?:[ ]{2}|\n)/i", $text);
            $bookingIsJunk[] = $isJunk;
            
            if ($isJunk) {
                continue;
            }

            $this->parsePdf($email, $text, $headerText, $activityName);
        }

        if (count($email->getItineraries()) === 0) {
            $this->parseHtml($email, $bookingIsJunk);
        }

        $email->setType('BookingConfirmation' . ucfirst($this->lang));

        if (count(array_unique($bookingIsJunk)) === 1 && $bookingIsJunk[0] === true) {
            $email->setIsJunk(true, "It looks like it's a SIM card");
        }

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

    private function re(string $re, ?string $str, $c = 1): ?string
    {
        if (preg_match($re, $str ?? '', $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function tPlusEn(string $s): array
    {
        return array_unique(array_merge((array) $this->t($s), (array) $this->t($s, 'en')));
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
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

    /**
     * @param string $string Unformatted string with currency
     * @return string
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'IDR' => ['Rp'],
        ];
        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency)
                    return $currencyCode;
            }
        }
        return $string;
    }
}
