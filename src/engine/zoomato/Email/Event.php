<?php

namespace AwardWallet\Engine\zoomato\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Event extends \TAccountChecker
{
	public $mailFiles = "zoomato/it-814152694.eml, zoomato/it-823157512.eml, zoomato/it-824101713.eml, zoomato/it-826562719.eml, zoomato/it-828631825.eml, zoomato/it-828796444.eml";

    public $subjects = [
        '/Your reservation at .+ has been modified/',
        '/Your booking at .+ has been confirmed/',
        '/Reservation reminder at .+/',
        '/Thank you for confirming your booking at .+/',
        '/Your booking at .+ has been cancelled/',
        '/A tua reserva no .+ foi cancelada!/'
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['ABOUT THE VENUE'],
        'pt' => ['SOBRE O RESTAURANTE'],
    ];

    public static $dictionary = [
        'en' => [
            'detectPhrase' => ['Booking Confirmed!', 'Reservation Modified!', 'Reservation Reminder', 'Thank you for confirming!', 'Booking Cancelled'],
            'YOUR BOOKING ID IS' => ['YOUR BOOKING ID IS', 'YOUR BOOKING ID WAS'],
            'ABOUT THE VENUE' => 'ABOUT THE VENUE',
            'BOOKING DETAILS' => ['BOOKING DETAILS', 'RESERVATION DETAILS'],
            'guests' => ['guests', 'guest']
        ],
        'pt' => [
            'detectPhrase' => ['Reserva Cancelada!'],
            'BOOKING ID' => 'ID DE RESERVA',
            'ABOUT THE VENUE' => 'SOBRE O RESTAURANTE',
            'YOUR BOOKING ID IS' => ['ID DE RESERVA'],
            'BOOKING DETAILS' => 'DETALHES DA RESERVA',
            'Booking Cancelled' => 'Reserva Cancelada!',
            'guests' => ['pessoas'],
            'at' => 'às',
            'Cancelled' => 'Cancelada'
        ],

    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'zomatobook.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a/@href[{$this->contains(['zomatobook.com'])}]")->length === 0
        ) {
            return false;
        }
        foreach (self::$dictionary as $dict) {
            if (!empty($dict['detectPhrase']) && $this->http->XPath->query("//*[{$this->contains($dict['detectPhrase'])}]")->length > 0
                && !empty($dict['YOUR BOOKING ID IS']) && $this->http->XPath->query("//*[{$this->contains($dict['YOUR BOOKING ID IS'])}]")->length > 0
                && !empty($dict['ABOUT THE VENUE']) && $this->http->XPath->query("//*[{$this->contains($dict['ABOUT THE VENUE'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]zomatobook\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->assignLang();

        $this->Event($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Event(Email $email)
    {
        $e = $email->add()->event();

        if ($this->http->XPath->query("//*[{$this->contains($this->t('Booking Cancelled'))}]")->length > 0){
            $e->general()
                ->status($this->t("Cancelled"))
                ->cancelled();
        }

        $e->type()
            ->restaurant();

        $e->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('YOUR BOOKING ID IS'))}]", null, false, "/^{$this->opt($this->t('YOUR BOOKING ID IS'))} \#([\d\D\-]+)$/"), $this->t('BOOKING ID'));

        $e->place()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('ABOUT THE VENUE'))}]/following::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('ABOUT THE VENUE'))}]/following::text()[normalize-space()][2]"));

        $phoneInfo = $this->http->FindSingleNode("//tr[./descendant::text()[{$this->starts($this->t('ABOUT THE VENUE'))}]]/following-sibling::tr[./td][1]/descendant::text()[normalize-space()][1]", null, false, '/^[\+\(\)\-\s\d]+$/');

        if ($phoneInfo !== null) {
            $e->place()
                ->phone($phoneInfo);
        }

        $e->addTraveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('BOOKING DETAILS'))}]/following::text()[normalize-space()][1]", null, false, '/^[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]]$/'), true);

        $startTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('BOOKING DETAILS'))}]/following::text()[normalize-space()][3]", null, false, "/^\d+\/\d+\/\d{4}\s*{$this->t('at')}\s*\d+\:\d+\s*[AP]?M?$/u");
        if (preg_match("/^(?<date>\d+\/\d+\/\d{4})\s*{$this->t('at')}\s*(?<time>\d+\:\d+\s*[AP]?M?)$/u", $startTime, $m)){
            $e->booked()
                ->start(strtotime($this->normalizeDate($m['date']) . ' ' . $m['time']));
        }

        $e->booked()
            ->noEnd()
            ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('BOOKING DETAILS'))}]/following::text()[normalize-space()][2]", null, false, "/^(\d+)\s*{$this->opt($this->t('guests'))}$/"));
    }

    private function normalizeDate($str)
    {
        if (preg_match("/^(\d+)\/(\d+)\/(\d{4})$/u", $str, $m)) {
            $in = "/^(\d+)\/(\d+)\/(\d{4})$/u";

            $out = "$1/$2/$3";
            
            switch ($this->lang) {
                case 'pt':
                    $out = "$2/$1/$3";
                    break;
                case 'en':
                    $out = "$1/$2/$3";
                    break;
            }

            if((int) $m[1] > 12){
                $out = "$2/$1/$3";
            } elseif((int) $m[2] > 12){
                $out = "$1/$2/$3";
            }

            $str = preg_replace($in, $out, $str);
        }

        return $str;
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $dBody) {
            foreach ($dBody as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s, '/'));
            }, $field)) . ')';
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
}
