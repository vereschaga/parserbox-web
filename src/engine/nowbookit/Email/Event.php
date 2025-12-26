<?php

namespace AwardWallet\Engine\nowbookit\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Event extends \TAccountChecker
{
	public $mailFiles = "nowbookit/it-810714533.eml, nowbookit/it-828080643.eml, nowbookit/it-828561998.eml, nowbookit/it-829350441.eml, nowbookit/it-829882461.eml, nowbookit/it-829928001.eml, nowbookit/it-835760043.eml, nowbookit/it-840284120.eml, nowbookit/it-843476609.eml, nowbookit/it-846799626.eml";

    public $subjects = [
        'Booking confirmation',
        'Booking reminder',
        'Booking cancellation',
        'Cancelled booking confirmation at',
        'RESERVATION CONFIRMATION @',
        'Your booking confirmation',
        'Your Reservation at'
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Number of Guests' => 'Number of Guests',
            'Booking Reference' => 'Booking Reference',
            'ABN' => ['ABN', 'NZBN'],
            'Your booking has been cancelled.' => ['Your booking has been cancelled.', 'Your reservation has been cancelled at']
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'nowbookit.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (stripos($parser->getHeader('from'), 'nowbookit.com') === 0
            && $this->http->XPath->query("//img/@src[{$this->contains(['nowbookit.com'])}]")->length === 0
            && $this->http->XPath->query("//a/@href[{$this->contains(['nowbookit.com'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Number of Guests']) && $this->http->XPath->query("//*[{$this->contains($dict['Number of Guests'])}]")->length > 0
                && !empty($dict['Booking Reference']) && $this->http->XPath->query("//*[{$this->contains($dict['Booking Reference'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]nowbookit\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->Event($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Event(Email $email)
    {
        $e = $email->add()->event();

        $e->type()->event();

        if ($this->http->XPath->query("//*[{$this->contains($this->t('Your booking has been cancelled.'))}]")->length > 0){
            $e->general()
                ->status($this->t('Cancelled'))
                ->cancelled();
        }

        $confNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference'))}]", null, false, "/^{$this->t('Booking Reference')}\:\s*([\d\D\-]+)$/");

        if ($confNumber !== null) {
            $e->general()
                ->confirmation($confNumber, $this->t('Booking Reference'));
        } else if ($this->http->XPath->query("//text()[{$this->starts($this->t('Booking Reference'))}]")->length === 0) {
            $e->general()
                ->noConfirmation();
        }

        $guestName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Name:'))}]/ancestor::*[.//text()[{$this->starts($this->t('Date:'))}]][1]", null, false, "/^{$this->opt($this->t('Name:'))}\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*{$this->opt($this->t('Date:'))}/");

        if ($guestName !== null){
            $e->addTraveller($guestName, true);
        } else if ($guestName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Date'))}]/preceding::text()[normalize-space()][1]", null, false, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/")) {
            $e->addTraveller($guestName, true);
        }

        $dateInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Date'))}]", null, false, "/^{$this->t('Date')}\:\s*(\w+\,\s*\w+\s*\d+\,\s*\d{4})$/u");
        $timeInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Time'))}]");

        if ($dateInfo !== null && preg_match("/^{$this->t('Time')}\:\s*(\d{1,2}\:\d{2}\s*[AaPp]?[Mm]?)\$/u", $timeInfo, $m)){
            $startDate = $m[1];

            if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp](?:\.[ ]*)?[Mm]\.?$/', $startDate, $m) && (int) $m[2] > 12) {
                // 23:00 PM -> 23:00
                $startDate = $m[1];
            }

            $e->booked()
                ->noEnd()
                ->start(strtotime($dateInfo . ' ' . $startDate));
        } elseif ($dateInfo !== null && preg_match("/^{$this->t('Time')}\:\s*(\d{1,2}\:\d{2}\s*[AaPp]?[Mm]?)\s*\-\s*(\d{1,2}\:\d{2}\s*[AaPp]?[Mm]?)\$/u", $timeInfo, $m)) {
            $startDate = $m[1];
            $endDate = $m[2];

            if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp](?:\.[ ]*)?[Mm]\.?$/', $startDate, $m) && (int) $m[2] > 12) {
                // 23:00 PM -> 23:00
                $startDate = $m[1];
            }

            if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp](?:\.[ ]*)?[Mm]\.?$/', $endDate, $m) && (int) $m[2] > 12) {
                // 23:00 PM -> 23:00
                $endDate = $m[1];
            }

            $e->booked()
                ->start(strtotime($dateInfo . ' ' . $startDate))
                ->end(strtotime($dateInfo . ' ' . $endDate));
        }

        $eventService = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Service'))}]", null, false, "/^{$this->t('Service')}\:\s*\S.+$/");
        $eventSection = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Section'))}]", null, false, "/^{$this->t('Section')}\:\s*\S.+$/");

        $notes = array_filter([$eventService, $eventSection]);
        if (!empty($notes)) {
            $e->general()->notes(implode(". ", $notes));
        }

        $e->booked()
            ->guests($this->http->FindSingleNode("//text()[{$this->starts($this->t('Number of Guests'))}]", null, false,"/^{$this->t('Number of Guests')}\:\s*(\d+)$/"));

        $placeName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Venue'))}]", null, false, "/^{$this->t('Venue')}\:\s*(.+)$/");

        if ($placeName !== null){
            $e->place()
                ->name($placeName);
        } else if ($placeName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Phone'))}]/preceding-sibling::text()[last()]")){
            $e->place()
                ->name($placeName);
        } else if ($placeName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Phone'))}]/ancestor::p[1]/descendant::text()[normalize-space()][1]")){
            $e->place()
                ->name($placeName);
        }
        
        $phoneNumber = $this->http->FindSingleNode("//text()[{$this->eq($placeName)}]/following-sibling::text()[normalize-space()][{$this->starts('Phone')}]", null, false, "/^{$this->t('Phone')}\:\s*([\d\s\+\-\(\)]+)$/");

        if ($phoneNumber !== null && strlen(preg_replace("/\D/", '', $phoneNumber)) > 5) {
            $e->place()
                ->phone($phoneNumber);
        }

        $e->place()
            ->address(implode(" ", $this->http->FindNodes("//text()[{$this->eq($placeName)}][./following::text()[1][not({$this->contains($this->t('Your booking'))})]]/following::text()[normalize-space()][not({$this->starts($this->t('ABN'))})][./following::text()[{$this->starts($this->t('Phone'))} and not({$this->starts($this->t('Event'))})]]")));
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
                return "normalize-space(.)=\"{$s}\"";
            }, $field)) . ')';
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
        $in = [
            // 11-30-2024 12:22 AM
            "/^(\d+)\-(\d+)\-(\d{4})\s*([\d\:]+\s*A?P?M?)$/",
        ];
        $out = [
            "$2.$1.$3 $4",
        ];

        $date = preg_replace($in, $out, $str);

        return strtotime($date);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s, '/'));
            }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }
}
