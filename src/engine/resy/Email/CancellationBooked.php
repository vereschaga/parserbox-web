<?php

namespace AwardWallet\Engine\resy\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CancellationBooked extends \TAccountChecker
{
    public $mailFiles = "resy/it-53731850.eml";
    public $reFrom = '@resy.com';
    public $reSubject = [
        'has Been Canceled',
        'has Been Cancelled',
        'has been cancelled',
        'a été annulée',
        'foi cancelada'
    ];
    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'cancelled' => ['cancelled', 'canceled', 'annulée', 'cancelada'],
            'Your reservation' => ['Your reservation has been', 'Your booking has been', 'Votre réservation a été', 'Sua reserva'],
            'detectPhrase' => ['Votre réservation a été annulée', 'Sua reserva foi cancelada.', 'Your reservation has been cancel', 'Your booking has been cancelled'],
            'If you have any' => ['If you have any', 'If you have questions, please reply to this email or call', 'hesitate to contact us at', 'Si vous avez des questions,'],
            'cancel' => ['annulée', 'cancel', 'cancelada'],
            'has been' => ['a été', 'has been', 'foi'],
            'Party of' => ['Party of', 'Grupo de']
        ],
    ];

    public $date;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->date = strtotime($parser->getHeader('date'));
        $event = $email->add()->event();

        $status = $this->http->FindSingleNode("//td[preceding-sibling::td[descendant::img]][{$this->contains(['Party of', 'Tickets,', 'Guest', 'Grupo de'])}]/preceding::td[{$this->starts($this->t('Your reservation'))} and {$this->contains($this->t('cancel'))}]", null, true, "/{$this->opt($this->t('has been'))}[ ]+({$this->opt($this->t('cancelled'))})\s*[.:;!]/i");

        $event->general()
            ->status($status)
            ->cancelled();

        $eventName = $this->http->FindSingleNode("//td[preceding-sibling::td[descendant::img]][{$this->contains(['Party of', 'Tickets,', 'Guest', 'Grupo de'])}]/preceding::text()[normalize-space()][2]");

        if (!empty($eventName)) {
            $event->setName($eventName);
            $event->setEventType(1);
        }

        $event->booked()
            ->start($this->normalizeDate($this->http->FindSingleNode("//td[preceding-sibling::td[descendant::img]][{$this->contains(['Party of', 'Tickets,', 'Guest', 'Grupo de'])}]/preceding::text()[normalize-space()][1]")))
            ->noEnd();

        $guests = $this->http->FindSingleNode("//td[preceding-sibling::td[descendant::img]][{$this->contains($this->t('Party of'))}]", null, true, "/{$this->opt($this->t('Party of'))}\s+(\d+)/");

        if (empty($guests)) {
            $guests = $this->http->FindSingleNode("//td[preceding-sibling::td[descendant::img]][contains(normalize-space(), 'Tickets,')]", null, true, "/^\s*(\d+)\s+Tickets,/");
        }

        if (empty($guests)) {
            $guests = $this->http->FindSingleNode("//td[preceding-sibling::td[descendant::img]][contains(normalize-space(), 'Guest')]", null, true, "/^\s*(\d+)\s+Guests?/");
        }

        $event->booked()->guests($guests);

        $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('If you have any'))}]/following::text()[1]", null, true, "/([+][\d\s\-\s]+)$/");

        if (!empty($phone)) {
            $event->place()
                ->phone($phone);
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/{$this->reFrom}/", $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//node()[{$this->contains(['is a member of Resy', 'est membre de Resy', 'é membro do Resy', 'est un membre de Resy', 'é membro de Resy'])}]")->length > 0
            && $this->http->XPath->query("//a[contains(@href,'resy.com')]")->length > 0
        ) {
            if ($this->http->XPath->query("//node()[{$this->contains($this->t('detectPhrase'))}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false
                && preg_match("/{$this->reFrom}/", $headers["from"]) > 0) {
                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\w+)[,]\s*(\w+)\.?\s*(\d+)\,?\s*(\d{4})\s*at\s*(\d+:\d+\s*(?:am|pm))$#", // Wed, May 22, 2024 at 6:00pm
            "#^(\w+)[,]\s*(\w+)\.?\s*(\d+)\s*at\s*(\d+:\d+\s*(?:am|pm))$#", // Friday, Feb 7 at 7:30pm
            "#^(\w+)[,]\s*(\d+)\s*(\w+)\s*\s*at\s*(\d+:\d+\s*(?:am|pm))$#", // Saturday, 23 Sep at 6:15pm
            "#^(\w+)\.?[,]\s*(\d+)\s*(\w+)\.?\s*\s*(\d+:\d+\s*[Aa]?[Pp]?[Mm]?)$#u", // sam., 23 mars 6:15
            "#^(\d+)\s*de\s*(\w+)\s*\s*(\d+:\d+\s*[Aa]?[Pp]?[Mm]?)$#u", // 23 de Mai 6:15
        ];
        $out = [
            '$1, $3 $2 $4, $5',
            '$1, $3 $2 ' . $year . ' $4',
            '$1, $2 $3 ' . $year . ' $4',
            '$1, $2 $3 ' . $year . ' $4',
            '$1 $2 ' . $year . ' $3',
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#\b\d{1,2}\s+([^\d\s]+)\s*\d{4}\b#u", $str, $m)) {
            foreach (['fr', 'pt', 'en'] as $lang){
                if ($en = MonthTranslate::translate($m[1], $lang)) {
                    $str = str_replace($m[1], $en, $str);

                    break;
                }
            }
        }

        if (preg_match("#^([^\d\s]+),\s+(\d+\s+[^\d\s]+\s*\d{4}.*)#u", $str, $m)) {
            foreach (['fr', 'pt', 'en'] as $lang){
                if ($weeknum = WeekTranslate::number1(WeekTranslate::translate($m[1], $lang))){
                    $str = EmailDateHelper::parseDateUsingWeekDay($m[2], $weeknum);

                    break;
                }
            }
        } else {
            $str = strtotime($str);
        }

        return $str;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
