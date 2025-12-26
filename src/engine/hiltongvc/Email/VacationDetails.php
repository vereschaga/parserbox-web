<?php

namespace AwardWallet\Engine\hiltongvc\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class VacationDetails extends \TAccountChecker
{
    public $mailFiles = "hiltongvc/it-51685037.eml";

    public $reFrom = ["@hgv.com", "@hgvc.com"];
    public $reBody = [
        'en' => ['VACATION DETAILS'],
    ];
    public $reSubject = [
        '/Your Vacation Details$/',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Arrival Date'   => 'Arrival Date',
            'Departure Date' => 'Departure Date',
        ],
    ];
    private $keywordProv = 'Hilton Grand Vacations';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'hgv.my.salesforce.com')] | //a[contains(@href,'.hgv.com') or contains(@href,'.hgvc.com')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
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
                    && preg_match($reSubject, $headers["subject"]) > 0
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

    private function parseEmail(Email $email)
    {
        // prepare
        $textInfo = implode("\n",
            $this->http->FindNodes("//text()[{$this->eq($this->t('YOUR INFORMATION'))}]/ancestor::td[1][{$this->contains($this->t('YOUR DESTINATION'))}]//text()"));
        $textPax = $this->re("/{$this->opt($this->t('Confirmation #'))}\s*[^\n]+\s+(.+?)\s+{$this->opt($this->t('YOUR DESTINATION'))}/s",
            $textInfo);
        $pax = array_filter(array_map("trim", explode("\n", $textPax)));

        $confNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation #'))}]/following::text()[normalize-space()!=''][1]");
        $phoneText = $this->re("/{$this->opt($this->t('Customer Care'))}\s*([^\n]+)/", $textInfo);
        $phones = [];
        $this->logger->critical($textInfo);

        if (preg_match("/T ([()\d+\- ]+), Ext\.[ ]*([^\|]*?)[ ]*(?:\|[ ]*([()\d+\- ]+))?[ ]*$/", $phoneText, $m)) {
            if (!empty(trim($m[2]))) {
                $phones[] = trim($m[1]) . ' ext.' . trim($m[2]);
            } else {
                $phones[] = trim($m[1]);
            }

            if (isset($m[3]) && !empty(trim($m[3]))) {
                $phones[] = trim($m[3]);
            }
        }

        /////////
        // hotel
        $r = $email->add()->hotel();

        $r->general()
            ->noConfirmation()
            ->travellers($pax);

        $r->ota()->confirmation($confNo, $this->t('Confirmation #'));

        foreach ($phones as $phone) {
            $r->ota()->phone($phone, $this->t('Customer Care'));
        }

        $r->hotel()
            ->name($this->re("/{$this->opt($this->t('YOUR DESTINATION'))}\s+(.+)/", $textInfo))
            ->address($this->re("/{$this->opt($this->t('YOUR DESTINATION'))}\s+.+\s+(.+)/", $textInfo));

        $r->booked()
            ->checkIn2($this->re("/{$this->opt($this->t('Arrival Date'))}:\s+(.+)/", $textInfo))
            ->checkOut2($this->re("/{$this->opt($this->t('Departure Date'))}:\s+(.+)/", $textInfo));
        $room = $r->addRoom();
        $room->setType($this->re("/{$this->opt($this->t('Room Type'))}:\s+(.+)/", $textInfo));

        ////////
        // tour
        $r = $email->add()->event();
        $r->general()
            ->noConfirmation()
            ->travellers($pax);
        $r->ota()->confirmation($confNo, $this->t('Confirmation #'));

        foreach ($phones as $phone) {
            $r->ota()->phone($phone, $this->t('Customer Care'));
        }

        $r->booked()
            ->start($this->normalizeDate($this->re("/{$this->opt($this->t('Tour Date/Time'))}:\s+(.+)/", $textInfo)))
            ->noEnd();

        if (preg_match("/{$this->t('Tour Address')}\:\s+(.+?)[ ].[ ](.+)/u", $textInfo, $m)) {
            $r->place()
                ->name($m[1])
                ->address($m[2])
                ->type(EVENT_EVENT);
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $this->logger->debug($date);
        $in = [
            //1/31/2020 1:30 PM
            '#^(\d+)\/(\d+)\/(\d{4}) (\d+:\d+(?:\s*[ap]m)?)\D*?$#ui',
            //1/31/2020 1:30 PM
            '#^(\d+)\/(\d+)\/(\d{4})\D+$#ui',
        ];
        $out = [
            '$3-$1-$2, $4',
            '$3-$1-$2',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Arrival Date'], $words['Departure Date'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Arrival Date'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Departure Date'])}]")->length > 0
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
