<?php

namespace AwardWallet\Engine\libroreserve\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Event extends \TAccountChecker
{
    public $mailFiles = "libroreserve/it-794455706.eml, libroreserve/it-798826002.eml, libroreserve/it-800041720.eml, libroreserve/it-800272996.eml, libroreserve/it-800810061.eml";

    public $subjects = [
        'Booking Confirmation for',
        'Online Booking Cancelation for',
        'Confirmation de votre réservation chez',
        'Rappel pour votre réservation chez',
        'Annulation de votre réservation en ligne pour',
        'Booking Reminder for',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => 'Hello',
        'fr' => 'Bonjour',
    ];

    public static $dictionary = [
        'en' => [
            'detectPhrase'       => ['Your reservation was received and approved.', 'Your reservation was canceled.', 'This is a friendly reminder for your reservation.'],
            'cancellationPhrase' => 'Your reservation was canceled.',
            'View reservation'   => ['View reservation', 'Reserve another table', 'Confirm Booking'],
        ],
        'fr' => [
            'detectPhrase'       => ['Ceci est un rappel pour votre réservation.', 'Votre réservation a été reçue et approuvée.', 'Votre réservation a été annulée.'],
            'View reservation'   => ['Voir la réservation', 'Confirmer la réservation', 'Réserver une autre table'],
            'cancellationPhrase' => 'Votre réservation a été annulée.',
            'Address'            => 'Adresse',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'libroreserve.com') !== false) {
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
        $this->assignLang();

        if ($this->http->XPath->query("//a[contains(@href, 'libroreserve.')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('detectPhrase'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('View reservation'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]libroreserve\.com$/', $from) > 0;
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

        $e->type()
            ->restaurant();

        $e->general()
            ->noConfirmation();

        $e->place()
            ->name($this->http->FindSingleNode("//text()[{$this->contains($this->t('Address'))}]/ancestor::div[2]/descendant::text()[2]", null, false, "/^(.*)$/"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/ancestor::div[1]/following-sibling::address[1]"));

        $startTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/ancestor::div[2]/descendant::div[1]/descendant::div[1]", null, false, "/^(\d{4}\-\d+\-\d+\s*[\d\:]+\s*a?A?p?P?m?M?)\s*/");

        $e->booked()
            ->start(strtotime($startTime))
            ->noEnd()
            ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/ancestor::div[2]/descendant::div[1]/descendant::div[2]", null, false, "/(\d+)/u"));

        $phoneNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/ancestor::div[1]/following-sibling::a[1]", null, false, "/^[\+\(\-\)\d\s]+$/");

        if ($phoneNumber !== null) {
            $e->place()
                ->phone($phoneNumber);
        }

        $guestName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/ancestor::div[2]/descendant::div[1]/descendant::div[3]", null, false, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/u ");

        if ($guestName !== null) {
            $e->addTraveller($guestName);
        }

        $statusInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('cancellationPhrase'))}]");

        if (preg_match("/{$this->t('cancellationPhrase')}/", $statusInfo)) {
            $e->general()
                ->cancelled();
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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $dBody) {
            if ($this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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
