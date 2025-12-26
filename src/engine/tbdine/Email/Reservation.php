<?php

namespace AwardWallet\Engine\tbdine\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "tbdine/it-260671751.eml, tbdine/it-261613985.eml, tbdine/it-269727788.eml, tbdine/it-270242236.eml";

    public $date;

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'CancelledText' => ['Your reservation has been canceled', 'your reservation has been successfully canceled'],
            //            'View map' => '',
            //            'Confirmation #' => '',
            //            'of reservation for' => '',
            //            'Reserved by' => '', // hide after guest name
            //            'Date' => '',
            //            'Time' => '',
            //            'Party Size' => '',
        ],
        'fr' => [
            'CancelledText'      => ['Votre réservation a été annulée.', 'votre réservation a été annulée avec succès'],
            'View map'           => 'Voir carte',
            'Confirmation #'     => 'Confirmation #',
            'of reservation for' => 'pour la réservation de',
            'Reserved by'        => 'Réservé par', // hide after guest name
            'Date'               => 'Date',
            'Time'               => 'Heure',
            'Party Size'         => 'Table pour',
            'Guest'              => 'Pers',
        ],
    ];

    private $detectFrom = "@tbdine.com";
    private $detectSubject = [
        // en
        'Your reservation at',
        'Your upcoming reservation at',
        // fr
        'Votre réservation chez',
        'Demande de confirmation de réservation de',
    ];
    private $detectBody = [
        'en' => [
            'Your reservation is confirmed.',
            'Your reservation has been modified.',
            'Your reservation has been canceled.',
            'Please confirm your reservation.',
        ],
        'fr' => [
            'Votre réservation est confirmée.',
            'Votre réservation a été annulée.',
            'Demande de confirmation de présence.',
            'Votre réservation a été modifiée.',
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
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation #'))}]",
                null, true, "/{$this->opt($this->t('Confirmation #'))}\s*(\d{5,})\s*(?:{$this->opt($this->t('of reservation for'))}|$)/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->contains($this->t('of reservation for'))}]/following::text()[normalize-space()][1][following::text()[normalize-space()][not({$this->starts($this->t('Reserved by'))}) and not({$this->contains('@impersonationName')})][1][{$this->eq($this->t('Date'))}]]"))
        ;

        if (!empty($this->http->FindSingleNode("(//*[{$this->contains($this->t('CancelledText'))}])[1]"))) {
            $d->general()
                ->cancelled()
                ->status('Cancelled');
        }

        // Place
        $d->place()
            ->type(Event::TYPE_RESTAURANT)
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('View map'))}]/ancestor::*[not({$this->eq($this->t('View map'))})][1]/preceding::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('View map'))}]/ancestor::*[not({$this->eq($this->t('View map'))})][1]",
                null, true, "/^(.+?)\s*{$this->opt($this->t('View map'))}\s*$/"))
        ;

        // Booked
        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Date'))}]/following::text()[normalize-space()][1]"));
        $time = $this->normalizeTime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Time'))}]/following::text()[normalize-space()][1]"));
        $d->booked()
            ->start((!empty($date) && !empty($time)) ? strtotime($time, $date) : null)
            ->noEnd()
            ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('Party Size'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d+)\s*{$this->opt($this->t('Guest'))}/"))
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
        $year = date("Y", $this->date);
        $in = [
            //Tue, Jan 24
            "/^\s*([^\d\s]+?)[\s.,]+([[:alpha:]]+)\s+(\d+)\s*$/u",
            // mer., 28 déc.
            "/^\s*([^\d\s]+?)[\s.,]+\s*(\d+)\s+([[:alpha:]]+)[.]?\s*$/u",
        ];
        $out = [
            "$1, $3 $2 $year",
            "$1, $2 $3 $year",
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
