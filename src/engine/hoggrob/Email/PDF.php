<?php

namespace AwardWallet\Engine\hoggrob\Email;

use AwardWallet\Schema\Parser\Email\Email;

class PDF extends \TAccountChecker
{
    public $mailFiles = "hoggrob/it-10013141.eml, hoggrob/it-10013151.eml, hoggrob/it-53876267.eml";

    private $detects = [
        'en' => [
            'Please check HRGOnline for the Fare Rules',
            'ALL Hotel Cancellations are made direct with HRG',
        ],
    ];

    private $from = '@hrgworldwide.com';
    private $subject = 'Confirmed Itinerary for';

    private $provider = 'HRG';

    private $lang = 'en';
    private static $dict = [
        'en' => [
            'Itinerary Details'  => 'Itinerary Details',
            'Booking Reference:' => ['Booking Reference:', 'Booking Ref:'],
        ],
    ];

    private $otaPNR;
    private $otaRef;
    private $pax;
    private $dateRes;
    private $totalInfo;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                if (strpos($text, $this->provider) !== false && $this->assignLang($text)) {
                    $this->parseEmailPdf($email, $text);
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], $this->from) !== false
            && isset($headers['subject']) && stripos($headers['subject'], $this->subject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() ? $parser->getHTMLBody() : $parser->getPlainBody();
        $bodyProv = (strpos($body, $this->provider) !== false);
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$bodyProv && strpos($text, $this->provider) === false) {
                continue;
            }

            if ($this->assignLang($text) === false) {
                continue;
            }

            if ($this->detect($text)) {
                return true;
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
        $types = 2; // train | hotel
        $cnt = count(self::$dict) * $types;

        return $cnt;
    }

    private function parseEmailPdf(Email $email, string $text)
    {
        $info = $this->findCutSection($text, 'Itinerary Details', 'Itinerary');

        if (empty($info)) {
            $this->logger->debug('other format');

            return;
        }
        $body = substr(strstr($text, $info), strlen($info));

        if (!empty($str = strstr($body, 'Estimated Cost Summary', true))) {
            $body = $str;
        }

        $this->otaPNR = $this->re('/PNR Locator\s+([A-Z\d]{5,7})/', $info);
        $this->otaRef = $this->re('/Trip Reference\s+([A-Z\d]{5,})/', $info);
        $this->pax = $this->re('/Arranger Name\s+(.+)/', $info);
        $this->dateRes = strtotime($this->re('/Request Date\s+(.+)/', $info));
        $this->totalInfo = $this->findCutSection($text, 'Estimated Cost Summary', null);

        $segments = $this->splitter("/\n([ ]*S[ ]*\d+[ ]+[^\n]+\s+{$this->opt($this->t('Booking Reference:'))})/",
            $body);

        foreach ($segments as $segment) {
            if (preg_match("/^\s*S\s*\d+\s*Rail/", $segment)) {
                $rails[] = $segment;
            } elseif (preg_match("/^\s*S\s*\d+\s*Hotel/", $segment)) {
                $hotels[] = $segment;
            } else {
                $email->add()->flight(); // for broke
                $this->logger->alert("unknown format. check and parse");
            }
        }

        if (isset($rails)) {
            $this->parseTrain($rails, $email);
        }

        if (isset($hotels)) {
            $this->parseHotel($hotels, $email);
        }
    }

    private function parseTrain(array $segments, Email $email)
    {
        $rails = [];

        foreach ($segments as $segment) {
            $ref = $this->re("/{$this->opt($this->t('Booking Reference:'))}\s*([\w\-]+)/", $segment);
            $rails[$ref][] = $segment;
        }

        foreach ($rails as $rl => $segments) {
            $r = $email->add()->train();
            $r->general()
                ->confirmation($rl, trim(((array) $this->t('Booking Reference:'))[0], ":"))
                ->traveller($this->pax, true);

            if (!empty($this->dateRes)) {
                $r->general()->date($this->dateRes);
            }
            $r->ota()
                ->confirmation($this->otaPNR, 'PNR Locator')
                ->confirmation($this->otaRef, 'Trip Reference');

            $tickets = [];

            foreach ($segments as $segment) {
                $s = $r->addSegment();
                $s->departure()
                    ->name($this->re('/Departure Station:\s+(.+)/', $segment))
                    ->date(strtotime($this->re('/Date\/Time:\s+(.+)/', $segment)));
                $s->arrival()
                    ->name($this->re('/Arrival Station:\s+(.+)/', $segment))
                    ->date(strtotime($this->re('/Date\/Time\s+(.+)/', $segment)));

                $s->extra()
                    ->noNumber()
                    ->service($this->re('/Vendor:\s+(.+)/', $segment))
                    ->status($this->re('/Status:\s+(.+)/', $segment))
                    ->cabin($this->re('/Seat Class:\s+(.+)/', $segment))
                    ->seats(explode(",", $this->re('/Seat\(s\):\s+\b([A-Z\d]{1,4}.*)/', $segment)));
                $tickets[] = $this->re('/Ticket Ref Number:\s+(.+)/', $segment);
            }
            $tickets = array_unique($tickets);

            if (!empty($tickets)) {
                $r->setTicketNumbers($tickets, false);
            }
        }

        if (count($rails) === 1 && isset($r)) {
            $r->price()
                ->total($this->re('/Rail[ ]+[A-Z]{3}[ ]+\b([\d\.]+)\b/', $this->totalInfo))
                ->currency($this->re('/Rail[ ]+([A-Z]{3})[ ]+/', $this->totalInfo));
        }
    }

    private function parseHotel(array $segments, Email $email)
    {
        foreach ($segments as $segment) {
            $r = $email->add()->hotel();

            $r->general()
                ->confirmation(
                    $this->re("/{$this->opt($this->t('Booking Reference:'))}\s*([\w\-]+)/", $segment),
                    trim(((array) $this->t('Booking Reference:'))[0], ":")
                )
                ->traveller($this->pax, true)
                ->status($this->re('/Status:\s+(.+)/', $segment));

            if (!empty($this->dateRes)) {
                $r->general()->date($this->dateRes);
            }
            $r->ota()
                ->confirmation($this->otaPNR, 'PNR Locator')
                ->confirmation($this->otaRef, 'Trip Reference');

            $r->hotel()
                ->name($this->re('/Hotel Name:\s+(.+)/', $segment))
                ->address($this->normalizeText($this->re('/Address:\s+(.+)\s+Phone/s', $segment)))
                ->phone($this->re('/Phone:\s+(.+)/', $segment))
                ->fax($this->re('/Fax:\s+(.+)/', $segment), false, true);

            $r->booked()
                ->checkIn(strtotime($this->re('/Arrival Date:\s+(.+)/', $segment)))
                ->checkOut(strtotime($this->re('/Departure Date:\s+(.+)/', $segment)));

            $room = $r->addRoom();
            $room
                ->setType($this->normalizeText($this->re('/Room Type:\s+(.+)\s+Cost Details/si', $segment)));
            $costDetails = $this->normalizeText($this->re('/Cost Details:\s+(.+)\s+Cancellation Policy/s', $segment));

            if (preg_match('/([\d\.]+\s+([A-Z]{3})\s+PER\s+NIGHT\s+[\s\S]+)\s+\b([\d\.]+)\b\s+\w+\s+APPROX\s+TOTAL/',
                $costDetails, $m)) {
                $room->setRate($this->normalizeText($m[1]));
                $r->price()
                    ->currency($m[2])
                    ->total($m[3]);
            } elseif (preg_match('/([\d\.]+\s+([A-Z]{3})\s+PER\s+NIGHT)/',
                $this->re('/Room Type:\s+(.+)\s+Cost Details/si', $segment) . ' ' . $costDetails, $m)) {
                $room->setRate($this->normalizeText($m[1]));
                $r->price()
                    ->currency($m[2]);
            }
            $cancellation = $this->normalizeText($this->re('/Cancellation Policy:\s+([A-Z\s\d\-\.\,:]+?)(?:\s+Special Requests|\n\n)/si',
                $segment));

            if (empty($cancellation)) {
                $cancellation = $this->normalizeText($this->re('/Cancellation Policy:\s+([A-Z\s\d\-\.\,:]+?)(?:\s+Special Requests|\n+)/si',
                    $segment));
            }
            $r->general()
                ->cancellation($cancellation);

            $description = $this->re('/Special Requests:\s+(.+)/', $segment);

            if (!empty($description)) {
                $room->setDescription($description);
            }

            $this->detectDeadLine($r);
        }

        if (count($segments) === 1 && isset($r)) {
            $r->price()
                ->total($this->re('/Hotel[ ]+[A-Z]{3}[ ]+\b([\d\.]+)\b/', $this->totalInfo))
                ->currency($this->re('/Hotel[ ]+([A-Z]{3})[ ]+/', $this->totalInfo));
        }
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancel By (?<day>\d+)\s*(?<mnth>\w+?)\s*(?<year>\d{2}) (?<time>\d+:\d+(?:\s*[ap]\.?m)?)/ui",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime(str_replace(".", '', $m['time']),
                    strtotime($m['day'] . ' ' . $m['mnth'] . ' 20' . $m['year'])));

            return;
        }

        if (preg_match("/NO CANCELLATION CHARGE APPLIES PRIOR TO (?<time>\d+:\d+(?:\s*[ap]\.?m\.?)?)\s*LOCAL TIME ON THE DAY OF ARRIVAL\./ui",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative('0 day', str_replace(".", '', $m['time']));

            return;
        }
    }

    private function normalizeText($str)
    {
        return preg_replace(['/\s+/', '/\b[\d\.]+\b\s+[A-Z]{3}/'], [' ', ''], $str);
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function findCutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = strstr($input, $searchStart);

        if (!empty($left)) {
            $left = substr($left, strlen($searchStart));
        } else {
            return false;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            if (!empty($searchFinish)) {
                $inputResult = strstr($left, $searchFinish, true);
            } else {
                $inputResult = $left;
            }
        }

        return $inputResult;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function detect(string $text): bool
    {
        foreach ($this->detects as $lang => $detects) {
            foreach ($detects as $detect) {
                if (stripos($text, $detect) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Itinerary Details"], $words["Booking Reference:"])) {
                if ($this->stripos($body, $words["Itinerary Details"]) !== false && $this->stripos($body,
                        $words["Booking Reference:"]) !== false
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
