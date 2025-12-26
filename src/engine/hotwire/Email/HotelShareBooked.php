<?php

namespace AwardWallet\Engine\hotwire\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class HotelShareBooked extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "hotwire.com";
    public $reBody = [
        'en' => ['has shared with you', 'hotel itinerary'],
    ];
    public $reSubject = [
        'wants to share info about a hotel they booked on Hotwire',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];
    private $date;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'HotelShareBooked' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'hotwire.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'R'];
        $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
        $it['HotelName'] = $this->http->FindSingleNode("//a[contains(@href,'maps.google.com')]/ancestor::*[1]/preceding-sibling::*[string-length(normalize-space(.))>2][2]");
        $it['Address'] = $this->http->FindSingleNode("//a[contains(@href,'maps.google.com')]/ancestor::*[1]");
        $it['Phone'] = $this->http->FindSingleNode("//a[contains(@href,'maps.google.com')]/ancestor::*[1]/following-sibling::*[string-length(normalize-space(.))>2][1]");
        $text = $this->http->FindSingleNode("//a[contains(@href,'maps.google.com')]/ancestor::*[1]/preceding-sibling::*[string-length(normalize-space(.))>2][1]");

        if (preg_match("#(\w+,\s+\w+\s+\d+)\s*\-\s*(\w+,\s+\w+\s+\d+)#", $text, $m)) {
            $it['CheckInDate'] = $this->normalizeDate($m[1]);
            $it['CheckOutDate'] = $this->normalizeDate($m[2]);
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '#^(\w+),\s+(\w+)\s+(\d+)$#',
        ];
        $out = [
            '$3 $2 ' . $year,
        ];
        $outWeek = [
            '$1',
        ];
        $weeknum = WeekTranslate::number1(WeekTranslate::translate(preg_replace($in, $outWeek, $date), $this->lang));
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
        $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
