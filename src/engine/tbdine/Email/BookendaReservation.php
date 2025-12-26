<?php

namespace AwardWallet\Engine\tbdine\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class BookendaReservation extends \TAccountChecker
{
    public $mailFiles = "tbdine/it-272693789.eml";

    public $detectFrom = "@tbdine.com";
    public $detectSubject = [
        'Votre réservation est confirmée',
        'Votre réservation a été annulée',
    ];
    public $detectBody = [
        'fr' => [
            'La réservation suivante est confirmée',
            'confirmer votre annulation à votre récente réservation',
            'Pouvez-vous nous confirmer votre présence?',
        ],
    ];

    public $date;

    public $lang = 'en';
    public static $dictionary = [
        'fr' => [
            'CancelledText'      => ['Votre réservation a été annulée', 'plaisir de confirmer votre annulation à votre récente réservation'],
            'Confirmation'       => 'No. confirmation:',
            'For'                => 'Pour :',
            'Date'               => 'Date :',
            'Table'              => 'Table :',
            'Time'               => 'Heure :',
            'adult'              => 'adultes',
            'child'              => 'enfants',
            'View map'           => 'Consultez notre page',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if ($this->http->XPath->query("//a[{$this->contains(['.tbdine.com'], '@href')}] | //img[{$this->contains(['/tbdine/images'], '@src')}]")->length === 0) {
            return false;
        }

        // detect Format
        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->date = strtotime($parser->getDate());

        $this->parseEmailHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function parseEmailHtml(Email $email)
    {
        $d = $email->add()->event();

        // General
        $d->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation'))}]",
                null, true, "/{$this->opt($this->t('Confirmation'))}\s*(\d{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('For'))}]/following::text()[normalize-space()][1]"))
        ;

        if (!empty($this->http->FindSingleNode("(//*[{$this->contains($this->t('CancelledText'))}])[1]"))) {
            $d->general()
                ->cancelled()
                ->status('Cancelled');
        }

        // Place
        $d->place()
            ->type(Event::TYPE_RESTAURANT)
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('View map'))}]/ancestor::td[1][.//img]/descendant::text()[normalize-space()][1]"))
        ;
        $address = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('View map'))}]/ancestor::td[1][.//img]/descendant::text()[normalize-space()][position() > 1]"));
        $address = preg_replace("/\s*{$this->opt($this->t('View map'))}\s*$/", '', $address);

        if (preg_match("/^(?<address>.+?)(?<phone>\n[\d \-\+\(\)\. ]{5,})(?: #\d+)?\s*$/s", $address, $m)) {
            $d->place()
                ->address(preg_replace("/\s+/", ' ', $m['address']))
                ->phone(trim($m['phone']));
        } else {
            $d->place()
                ->address(preg_replace("/\s+/", ' ', $m['address']));
        }

        // Booked
        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Date'))}]/following::text()[normalize-space()][1]"));
        $time = $this->normalizeTime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Time'))}]/following::text()[normalize-space()][1]"));
        $d->booked()
            ->start((!empty($date) && !empty($time)) ? strtotime($time, $date) : null)
            ->noEnd()
            ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('Table'))}]/following::text()[normalize-space()][1]",
                null, true, "/(\d+)\s*{$this->opt($this->t('adult'))}/")
                + $this->http->FindSingleNode("//text()[{$this->eq($this->t('Table'))}]/following::text()[normalize-space()][1]",
                null, true, "/(\d+)\s*{$this->opt($this->t('child'))}/"))
        ;

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $year = date("Y", $this->date);
        $in = [
            // vendredi 27 janvier 2023
            "/^\s*[^\d\s]+?[\s.,]+(\d+)\s+([[:alpha:]]+)\s+(\d{4})\s*$/u",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^(?<week>[\w\-]+), (?<date>\d+ \w+ .+)#u", $str, $m)) {
            $weekT = WeekTranslate::translate($m['week'], $this->lang);
            $weeknum = WeekTranslate::number1($weekT);
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function normalizeTime(?string $time)
    {
        $in = [
            '#^\s*(\d{1,2}) *h *(\d{2})\s*$#ui', //12h25
        ];
        $out = [
            '$1:$2',
        ];
        $time = preg_replace($in, $out, $time);

        return $time;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
