<?php

namespace AwardWallet\Engine\hostme\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Event extends \TAccountChecker
{
	public $mailFiles = "hostme/it-767390197.eml, hostme/it-775277827.eml, hostme/it-776260138.eml";

    public $subjects = [
        'Your reservation at',
        'has been confirmed!',
        'Reservation confirmed!',
        'You have Upcoming reservation in',
        'Your reservation request at',
        'has been received.'
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Reservation Confirmed' => ['Reservation Confirmed', 'Upcoming Reservation', 'Waiting for approval'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'hostmeapp.com') !== false) {
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
        if ($this->http->XPath->query("//img[contains(@src, 'hostmeprod.')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Reservation Confirmed'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Manage your reservation'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]hostmeapp\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->Event($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Event(Email $email)
    {
        $e = $email->add()->event();

        $e->type()
            ->restaurant();

        $e->general()
            ->noConfirmation();

        $placeName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation Confirmed'))}]/preceding::table[1]", null, false, "/^(.*)$/");

        $e->place()
            ->name($placeName);

        $placeAddress = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation Confirmed'))}]/ancestor::table[1]/following-sibling::table[3]", null, false, "/^$placeName\s*(.*)\s*\+\d*/");

        if (!empty($placeAddress)){
            $e->place()
                ->address($placeAddress);
        } else {
            $link = $this->http->FindSingleNode("//text()[normalize-space()='Manage your reservation']/ancestor::a/@href"); #- получаешь ссылку на сайт провайдера
            $http2 = clone $this->http;
            $http2->GetURL($link);

            if (preg_match("/reserve\/(\d+)\//", $http2->currentUrl(), $m)){
                $http2->GetURL("https://api.hostmeapp.com/api/core/mb/restaurants/$m[1]");
                $jsonArray = $http2->JsonLog(null, 0, true);
                $e->place()
                    ->address($jsonArray['address']);
            }
        }

        $startTime = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation Confirmed'))}]/ancestor::table[1]/following-sibling::table[2]/descendant::table[2]", null, false, "/^([\d\w]+\s*[\d\w]+\s*[\d\:]+\s*A?P?M?)\s+/");

        $e->booked()
            ->start(strtotime($this->normalizeDate($startTime)));

        $durationInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Duration'))}]/ancestor::td[1]", null, false, "/^Duration\s*(.*)$/");

        if (preg_match("/^(\d+)\s*\w+\s*(\d+)\s*\w+$/", $durationInfo, $matches)){
            $durationSeconds = ($matches[1] * 3600) + ($matches[2] * 60);

            $e->booked()
                ->end((strtotime($this->normalizeDate($startTime)) + $durationSeconds));
        }

        $guestsInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation Confirmed'))}]/ancestor::table[1]/following-sibling::table[2]/descendant::table[2]");

        if (preg_match("/\s*of\s*(?<guestCount>\d+)\s*for\s*(?<guestName>[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*/", $guestsInfo,$m)) {
            $e->booked()
                ->guests($m['guestCount']);

            $e->addTraveller($m['guestName']);
        }
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
        $year = date("Y", $this->date);

        $in = [
            // October 20 12:22
            "/^\s*(\w+)\s*(\d+)\s*([\d\:]+\s*A?P?M?)\s*$/",
            // 20 October 12:22
            "/^\s*(\d+)\s*(\w+)\s*([\d\:]+\s*A?P?M?)\s*$/",
        ];
        $out = [
            "$1 $2 " . $year . " $3",
            "$2 $1 " . $year . " $3",
        ];

        $date = preg_replace($in, $out, $str);

        return $date;
    }
}
