<?php

namespace AwardWallet\Engine\hotels\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class SentMessage extends \TAccountChecker
{
    public $mailFiles = "hotels/it-183561580.eml, hotels/it-60804074-junk.eml";

    public $lang = '';

    public static $dictionary = [
        'nl' => [
            'headerText' => [' heeft je een nieuw bericht gestuurd:'],
            'buttonText' => ['Accommodatie beantwoorden'],
            // 'checkInDate' => '',
            // 'checkOutDate' => '',
            // 'checkInTime' => '',
            // 'checkOutTime' => '',
            // 'Hi' => '',
            // 'Guest Name' => '',
            // 'phoneLeftText' => '',
            // 'Cancellation Policy' => '',
        ],
        'fr' => [
            'headerText' => [' vous a envoyé un nouveau message:'],
            'buttonText' => ['Répondre à l’établissement'],
            // 'checkInDate' => '',
            // 'checkOutDate' => '',
            // 'checkInTime' => '',
            // 'checkOutTime' => '',
            // 'Hi' => '',
            // 'Guest Name' => '',
            // 'phoneLeftText' => '',
            // 'Cancellation Policy' => '',
        ],
        'de' => [
            'headerText' => [' hat Ihnen eine Nachricht gesendet:'],
            'buttonText' => ['Unterkunft antworten'],
            // 'checkInDate' => '',
            // 'checkOutDate' => '',
            // 'checkInTime' => '',
            // 'checkOutTime' => '',
            // 'Hi' => '',
            // 'Guest Name' => '',
            // 'phoneLeftText' => '',
            // 'Cancellation Policy' => '',
        ],
        'es' => [
            'headerText' => [' te ha enviado un mensaje:'],
            'buttonText' => ['Responder al establecimiento'],
            // 'checkInDate' => '',
            // 'checkOutDate' => '',
            // 'checkInTime' => '',
            // 'checkOutTime' => '',
            // 'Hi' => '',
            // 'Guest Name' => '',
            // 'phoneLeftText' => '',
            // 'Cancellation Policy' => '',
        ],
        'pt' => [
            'headerText' => ['enviou uma mensagem para você:'],
            'buttonText' => ['Responder ao estabelecimento'],
            // 'checkInDate' => '',
            // 'checkOutDate' => '',
            // 'checkInTime' => '',
            // 'checkOutTime' => '',
            // 'Hi' => '',
            // 'Guest Name' => '',
            // 'phoneLeftText' => '',
            // 'Cancellation Policy' => '',
        ],
        'en' => [
            'headerText'   => [' sent you a message:', ' sent you a new message:', ' sent you a new message with an attachment:'],
            'buttonText'   => ['Reply', 'Reply To Property', 'Reply to Property'],
            'checkInDate'  => ['Check in date', 'Check-In Date', 'Check-In date', 'Arrival Date', 'Arrival Due', 'Check-in:', 'checking in'],
            'checkOutDate' => ['Check out date', 'Check-Out Date', 'Check-Out date', 'Departure Date', 'Departure Due', 'Check-out:'],
            'checkInTime'  => ['Check-In Time', 'Check In time is'],
            'checkOutTime' => ['Check-Out Time', 'Check Out time is'],
            // 'Hi' => '',
            // 'Guest Name' => '',
            'phoneLeftText' => 'If you have any questions about our property, simply call us at',
            // 'Cancellation Policy' => '',
        ],
    ];

    private $detectFrom = ['donotreply@hotels.com', 'donotreply@hoteis.com'];
    private $detectSubject = [
        // en
        'sent you a message', // Marina Torres, Grand Fiesta Americana Coral Beach Cancun sent you a message
        // nl
        'heeft je een bericht gestuurd', // Marcel Nogarede, Van der Valk Theaterhotel Almelo heeft je een bericht gestuurd
        // fr
        'vous a envoyé un message', // Pedro Viegas, l’établissement NH Lyon Airport vous a envoyé un message
        // de
        'hat Ihnen eine Nachricht gesendet', // Philipp Müller, KING's HOTEL Center hat Ihnen eine Nachricht gesendet
        // es
        'te ha enviado un mensaje', // Tanya Girdwood, Hampton Inn & Suites Houston North IAH, TX te ha enviado un mensaje
        // pt
        'enviou uma mensagem para você', // Fabio Rigat, Al Cappello Rosso enviou uma mensagem para você
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $xpathButton = "a[{$this->eq($this->t('buttonText'))}]";

        $messageBody = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ preceding-sibling::tr[{$this->contains($this->t('headerText'))}] and following::tr/descendant::{$xpathButton} and not(descendant::{$xpathButton}) ]"));

        if ($this->detectEmailByHeaders($parser->getHeaders()) && !empty($messageBody)
            && !preg_match("/(?:{$this->opt($this->t('checkInDate'))}|{$this->opt($this->t('checkOutDate'))}|\bTour\b|Booking Reference|Your reservation number is)/i", $messageBody)
        ) {
            $email->setType('SentMessageJunk' . ucfirst($this->lang));
            $email->setIsJunk(true);

            return $email;
        }

        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $email->setType('SentMessage' . ucfirst($this->lang));
        $h = $email->add()->hotel();

        $hotelName = $this->http->FindSingleNode("descendant::tr[not(.//tr) and {$this->contains($this->t('headerText'))}][last()]", null, true, "/^(.{2,}?)\s*{$this->opt($this->t('headerText'))}/");
        $phone = $this->re("/{$this->opt($this->t('phoneLeftText'))}\s*({$patterns['phone']})/", $messageBody);
        $h->hotel()->name($hotelName)->phone($phone, false, true);

        if (preg_match("/{$this->opt($this->t('Guest Name'))}[: ]*\n+[ ]*({$patterns['travellerName']})[ ]*(?:\n|$)/u", $messageBody, $m)) {
            $h->general()->traveller($m[1], true);
        } else {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null, "/^{$this->opt($this->t('Hi'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
                $h->general()->traveller($traveller);
            }
        }

        $dateCheckIn = strtotime($this->re("/{$this->opt($this->t('checkInDate'))}[: ]*\s+(.*\d.*?)[ ]*(?:\n|$)/", $messageBody));
        $dateCheckOut = strtotime($this->re("/{$this->opt($this->t('checkOutDate'))}[: ]*\s+(.*\d.*?)[ ]*(?:\n|$)/", $messageBody));

        $h->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

        $timeCheckIn = $this->re("/{$this->opt($this->t('checkInTime'))}[: ]*\s+({$patterns['time']})/", $messageBody);
        $timeCheckOut = $this->re("/{$this->opt($this->t('checkOutTime'))}[: ]*\s+({$patterns['time']})/", $messageBody);

        if ($timeCheckIn) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $dateCheckIn));
        }

        if ($timeCheckOut) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $dateCheckOut));
        }

        $cancellation = $this->re("/{$this->opt($this->t('Cancellation Policy'))}[: ]*\s+([\s\S]{1,195}?)(?:[ ]*\n|\s*$)/", $messageBody);
        $h->general()->cancellation($cancellation, false, true);

        if (preg_match("/Should\s+(?i)your\s+plans\s+change,\s+please\s+notify\s+Hotels.com\s+at\s+least\s+(?<prior>\d{1,3}\s+hours?)\s+in\s+advance\s+of\s+the\s+arrival\s+date\s+to\s+avoid\s+a\s+cancell?ation\s+fee\s+of\s+first\s+night/", $messageBody, $m) // en
        ) {
            $h->booked()->deadlineRelative($m['prior'], '00:00');
        } elseif (preg_match("/Reservation (?i)is non-refundable and a deposit will not be issued\./", $messageBody) > 0 // en
        ) {
            $h->booked()->nonRefundable();
        }

        if (!empty($h->getHotelName()) && !empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
            $h->general()->noConfirmation();
            $h->hotel()->noAddress();
        }

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        /*
        if ($this->detectEmailFromProvider( $parser->getHeader('from') ) !== true
            && $this->http->XPath->query('//a[contains(@href,".hotels.com/") or contains(@href,"mg.hotels.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Please contact Hotels.com") or contains(normalize-space(),"Report it to Hotels.com")]')->length === 0
        ) {
            return false;
        }
        */
        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match("/^.{2,}{$this->opt($this->detectSubject)}$/", $headers['subject']) > 0) {
            return true;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        if (empty($headers["subject"])) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function striposAll($text, $needle): bool
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['headerText']) || empty($phrases['buttonText'])) {
                continue;
            }

            if ($this->http->XPath->query("//tr[not(.//tr) and {$this->contains($phrases['headerText'])}]")->length > 0
                && $this->http->XPath->query("//a[{$this->eq($phrases['buttonText'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
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
