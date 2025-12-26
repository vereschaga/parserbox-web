<?php

namespace AwardWallet\Engine\tapportugal\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BPassTicket extends \TAccountChecker
{
    public $mailFiles = "tapportugal/it-6170210.eml, tapportugal/it-7061209.eml, tapportugal/it-8725208.eml, tapportugal/it-8759531.eml, tapportugal/it-8769924.eml, tapportugal/it-8772263.eml, tapportugal/it-27913963.eml";

    public $lang = '';
    public static $dictionary = [
        "en" => [
            "Booking reference" => ["Booking reference", "Booking Reference"],
            "Dear Mr. /Mrs."    => ["Dear Mr. /Mrs.", "Dear Mr./Mrs."],
            "reDear"            => "Dear Mr\.\s*\/Mrs.",
            //			"Electronic Ticket Number(s)" => "",
            "Flight details" => ["Flight details", "Itinerary updated"],
        ],
        "pt" => [
            "Booking reference"           => ["Código de Reserva", "Código de reserva"],
            "Dear Mr. /Mrs."              => "Exmo.(a) Sr.(a)",
            "reDear"                      => "Exmo\.\(a\) Sr.\(a\)",
            "Electronic Ticket Number(s)" => "Número(s) de Bilhete(s) Eletrónico(s)",
            "Flight details"              => ["Detalhes do Voo", "Itinerário Atualizado"],
        ],
    ];

    private $provider = 'TAP Portugal';

    private $subjects = [
        'en' => ['Check-in here', 'Schedule Change', 'Reservation Change'],
        'pt' => ['Faça aqui o seu Check-in', 'Alteração de Reserva'],
    ];

    private $detectBody = [
        "pt"  => 'Check-in aberto',
        "pt2" => 'TAP Air Portugal',
        "en"  => 'Flight Schedule Change',
        "en2" => 'Check-in open',
        "en3" => 'Flight Reservation Change',
        "en4" => 'TAP informs you that',
        'en5' => 'We wish you a pleasant flight',
        'en6' => 'Flight Reservation Change',
        'en7' => 'TAP informs you that one or',
    ];

    /*private function parseEmail()
    {
        // @var \AwardWallet\ItineraryArrays\AirTrip $it
        $it = ['Kind' => 'T'];
//		$it['RecordLocator'] = $this->http->FindSingleNode("(//text()[contains(., '".$this->t('Booking reference')."')]/following::text()[normalize-space(.)][1])[1]", null, true, '/^\s*([A-Z\d]{5,})\s*$/');
        $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[".$this->contains($this->t('Booking reference'))."]/following::text()[normalize-space(.)][1])[1]", null, true, '/^\s*([A-Z\d]{5,})\s*$/');

        $psng = $this->http->FindSingleNode("(//text()[". $this->contains($this->t('Dear Mr. /Mrs.'))."]/ancestor-or-self::td[1])[1]", null, true, "#".$this->t('reDear')."\s*(.+),$#");
        $psng = preg_replace("#^(.+)MRS?\s+(.+)$#", "\\1 \\2", $psng);
        $it['Passengers'] = [$psng];

        $ticketNumbers = $this->http->FindSingleNode("(//*[contains(text(), '".$this->t('Electronic Ticket Number(s)')."')]/ancestor-or-self::td[1])[1]", null, true, '/:\s*(\d[-\d]{5,}\d)/');
        if (!empty($ticketNumbers)){
            $it['TicketNumbers'][] = $ticketNumbers;
        }
//        $xpathFragmentRow = "self::tr or self::div";

        $xpathFragmentAirport = "[not(contains(.,'='))]/descendant::text()[normalize-space(.)][1][string-length(normalize-space(.))=3]";
        $xpath = "//text()[{$this->eq($this->t('Flight details'))}]/following::*[ ./*[1][normalize-space(.)] and ./*[string-length(normalize-space(.))>2 or descendant::img][2]{$xpathFragmentAirport} and ./*[string-length(normalize-space(.))>2 or descendant::img][3]//img and ./*[string-length(normalize-space(.))>2 or descendant::img][4]{$xpathFragmentAirport} ]";
        $segments = $this->http->XPath->query($xpath);
        if ($segments->length === 0) {
            $this->logger->alert('Segments not found by: ' . $xpath);
            return false;
        }
        foreach ($segments as $root) {
           //** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg
            $seg = [];
            $flight = $this->http->FindSingleNode('./*[1]', $root);
            if (preg_match('/([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d+)/', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $dep = implode("\n", $this->http->FindNodes('./*[string-length(normalize-space(.))>2 or descendant::img][2]/descendant::text()[normalize-space(.)]', $root));
            $arr = implode("\n", $this->http->FindNodes('./*[string-length(normalize-space(.))>2 or descendant::img][4]/descendant::text()[normalize-space(.)]', $root));
            $depArr = ['Dep' => $dep, 'Arr' => $arr];
            $re = '/([A-Z]{3})\s+(.+)\s+(\d{1,2})\s+(\w+)\s+(\d{2})\s+(\d{1,2}:\d{2})/';
            $re2 = '/([A-Z]{3})\s+(\d{1,2}\s+\w+\s+\d{2})\s+(\d{1,2}:\d{2})/';
            array_walk($depArr, function ($s, $key) use (&$seg, $re, $re2) {
                if (preg_match($re, $s, $m)) {
                    $seg[$key . 'Code'] = $m[1];
                    $seg[$key . 'Name'] = $m[2];
                    $seg[$key . 'Date'] = strtotime($m[3] . ' ' . $this->dateStringToEnglish($m[4]) . ' 20' . $m[5] . ', ' . $m[6]);
                } elseif ( preg_match($re2, $s, $m) ){
                    $seg[$key . 'Code'] = $m[1];
                    $seg[$key . 'Date'] = strtotime($this->dateStringToEnglish($m[2]).', '.$m[3]);
                }
            });
            $it['TripSegments'][] = $seg;
        }
        return [$it];
    }*/

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $texts = implode("\n", $parser->getRawBody());

        if (substr_count($texts, 'Content-Type: text/html') > 1) {
            $texts = preg_replace("#^(--[\w\-=+]{25,60})\s*$#m", "\n", $texts);
            $posBegin1 = stripos($texts, "Content-Type: text/html");
            $body = '';
            $i = 0;

            while ($posBegin1 !== false && $i < 30) {
                $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                $posEnd = stripos($texts, "\n\nContent", $posBegin);

                if ($posEnd) {
                    $length = $posEnd - $posBegin;
                } else {
                    $length = strlen(substr($texts, $posBegin));
                }
                $header = substr($texts, $posBegin1, $posBegin - $posBegin1);

                if (preg_match("#name=.*\.htm.*base64#s", $header)) {
                    $t = substr($texts, $posBegin, $length);
                    $body .= base64_decode($t);
                } elseif (preg_match("#Encoding: quoted-printable#s", $header)) {
                    $t = substr($texts, $posBegin, $length);
                    $body .= quoted_printable_decode($t);
                } else {
                    $t = substr($texts, $posBegin, $length);
                    $body .= $t;
                }
                $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
                $i++;
            }
            $this->http->SetEmailBody($body);
        }

        $body = $this->http->Response['body'];

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        foreach ($this->detectBody as $lang => $detect) {
            if (stripos($body, $detect) !== false) {
                $this->lang = substr($lang, 0, 2);
            }
        }
        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $texts = implode("\n", $parser->getRawBody());

        if (substr_count($texts, 'Content-Type: text/html') > 1) {
            $texts = preg_replace("#^(--[\w\-=+]{25,60})\s*$#m", "\n", $texts);
            $posBegin1 = stripos($texts, "Content-Type: text/html");
            $body = '';
            $i = 0;

            while ($posBegin1 !== false && $i < 30) {
                $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                $posEnd = stripos($texts, "\n\nContent", $posBegin);

                if ($posEnd) {
                    $length = $posEnd - $posBegin;
                } else {
                    $length = -1;
                }
                $header = substr($texts, $posBegin1, $posBegin - $posBegin1);

                if (preg_match("#name=.*\.htm.*base64#s", $header)) {
                    $t = substr($texts, $posBegin, $length);
                    $body .= base64_decode($t);
                } elseif (preg_match("#Encoding: quoted-printable#s", $header)) {
                    $t = substr($texts, $posBegin, $length);
                    $body .= quoted_printable_decode($t);
                } else {
                    $t = substr($texts, $posBegin, $length);
                    $body .= $t;
                }
                $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
                $i++;
            }
            $this->http->SetEmailBody($body);
        }

        $body = $this->http->Response['body'];

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        foreach ($this->detectBody as $detect) {
            if (stripos($body, $detect) !== false && (stripos($body, $this->provider) !== false || stripos($body, 'TAP Air Portugal') !== false)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'TAP Air Portugal') !== false
            || stripos($from, '@info.flytap.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 2 * count(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("(//text()[" . $this->contains($this->t('Booking reference')) . "]/following::text()[normalize-space(.)][1])[1]", null, true, '/^\s*([A-Z\d]{5,})\s*$/'));

        $psng = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t('Dear Mr. /Mrs.')) . "]/ancestor-or-self::td[1])[1]", null, true, "#" . $this->t('reDear') . "\s*(.+),$#");
        $psng = preg_replace("#^(.+)MRS?\s+(.+)$#", "\\1 \\2", $psng);
        $f->general()
            ->travellers([$psng]);

        $ticketNumbers = $this->http->FindSingleNode("(//*[contains(text(), '" . $this->t('Electronic Ticket Number(s)') . "')]/ancestor-or-self::td[1])[1]", null, true, '/:\s*(\d[-\d]{5,}\d)/');

        if (!empty($ticketNumbers)) {
            $f->issued()->ticket($ticketNumbers, false);
        }
//        $xpathFragmentRow = "self::tr or self::div";

        $xpathFragmentAirport = "[not(contains(.,'='))]/descendant::text()[normalize-space(.)][1][string-length(normalize-space(.))=3]";
        $xpath = "//text()[{$this->eq($this->t('Flight details'))}]/following::*[ ./*[1][normalize-space(.)] and ./*[string-length(normalize-space(.))>2 or descendant::img][2]{$xpathFragmentAirport} and ./*[string-length(normalize-space(.))>2 or descendant::img][3]//img and ./*[string-length(normalize-space(.))>2 or descendant::img][4]{$xpathFragmentAirport} ]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->alert('Segments not found by: ' . $xpath);

            return false;
        }

        foreach ($segments as $root) {
            $seg = $f->addSegment();
            $flight = $this->http->FindSingleNode('./*[1]', $root);

            if (preg_match('/([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d+)/', $flight, $m)) {
                $seg->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $dep = implode("\n", $this->http->FindNodes('./*[string-length(normalize-space(.))>2 or descendant::img][2]/descendant::text()[normalize-space(.)]', $root));
            $arr = implode("\n", $this->http->FindNodes('./*[string-length(normalize-space(.))>2 or descendant::img][4]/descendant::text()[normalize-space(.)]', $root));
            $depArr = ['Dep' => $dep, 'Arr' => $arr];
            $re = '/([A-Z]{3})\s+(.+)\s+(\d{1,2})\s+(\w+)\s+(\d{2})\s+(\d{1,2}:\d{2})/';
            $re2 = '/([A-Z]{3})\s+(\d{1,2}\s+\w+\s+\d{2})\s+(\d{1,2}:\d{2})/';
            array_walk($depArr, function ($s, $key) use (&$seg, $re, $re2) {
                if (preg_match($re, $s, $m)) {
                    $this->logger->debug(var_export($m, true));

                    if ($key == 'Dep') {
                        $seg->departure()
                            ->name($m[2])
                            ->code($m[1])
                            ->date(strtotime($m[3] . ' ' . $this->dateStringToEnglish($m[4]) . ' 20' . $m[5] . ', ' . $m[6]));
                    }

                    if ($key == 'Arr') {
                        $seg->arrival()
                            ->name($m[2])
                            ->code($m[1])
                            ->date(strtotime($m[3] . ' ' . $this->dateStringToEnglish($m[4]) . ' 20' . $m[5] . ', ' . $m[6]));
                    }
                } elseif (preg_match($re2, $s, $m)) {
                    if ($key == 'Dep') {
                        $seg->departure()
                            ->code($m[1])
                            ->date(strtotime($this->dateStringToEnglish($m[2]) . ', ' . $m[3]));
                    }

                    if ($key == 'Arr') {
                        $seg->arrival()
                            ->code($m[1])
                            ->date(strtotime($this->dateStringToEnglish($m[2]) . ', ' . $m[3]));
                    }
                }
            });
        }

        return true;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
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
}
