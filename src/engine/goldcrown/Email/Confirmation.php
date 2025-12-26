<?php

namespace AwardWallet\Engine\goldcrown\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "goldcrown/it-162451053.eml, goldcrown/it-166787417.eml";
    public $subjects = [
        'Confirmation of your reservation – Best Western',
        'Confirmation de votre réservation – Best Western',
        "Avis d'annulation de votre réservation - Best Western",
    ];

    public $lang = 'en';

    public $detectLang = [
        'en' => ['Reservation status'],
        'fr' => ['Statut de votre réservation'],
    ];

    public static $dictionary = [
        "en" => [
        ],

        'fr' => [
            'Please find a summary of your reservation below' => ['Nous vous remercions pour votre confiance', 'a bien été annulée'],
            'Reservation status'                              => 'Statut de votre réservation',
            'Your stay at'                                    => 'Votre séjour au',

            'Hello'          => 'Bonjour',
            'reservation no' => ['réservation chambre', 'N° réservation', 'N° Réservation :'],
            //'Tel.' => '',
            'Arrival date'   => 'Arrivée le',
            'Departure date' => 'Départ le',
            'people(s)'      => ['adulte(s)', 'adultes', 'adulte'],
            'room'           => ['chambre', 'Chambre'],
            'View hotel'     => "Revoir l'hôtel",
            'Room '          => 'CHAMBRE ',
            'Your stay'      => 'Votre séjour',
            //'Room holding policy' => '',
            'TOTAL'               => 'MONTANT TOTAL',
            'Cancellation policy' => "Politique d'annulation",
            'Cancelled'           => 'ANNULÉE',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@bestwestern.') !== false) {
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
        $this->detectLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Best Western'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Please find a summary of your reservation below'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation status'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your stay at'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]bestwestern\./', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();
        $h->general()
            ->status($this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation status'))}]/following::text()[normalize-space()][1]"))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('reservation no'))}]/following::text()[normalize-space()][1]", null, true, "/^\d+$/"))
            ->traveller(trim($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/{$this->opt($this->t('Hello'))}\s*(.+)/"), ','), true);

        $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation policy'))}]", null, true, "/\:\s*(.+)/");

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        if ($this->t('Cancelled') == $h->getStatus()) {
            $h->general()
                ->cancelled();
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your stay at'))}]/following::text()[normalize-space()][1]"));

        $addressText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your stay at'))}]/following::text()[normalize-space()][2]/ancestor::*[1]");

        if (preg_match("/^(.+)\s*{$this->opt($this->t('Tel.'))}\s*([+][\d\s]+)/", $addressText, $m)) {
            $h->hotel()
                ->address($m[1])
                ->phone($m[2]);
        }

        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrival date'))}]/ancestor::tr[1]/descendant::td[2]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Departure date'))}]/ancestor::tr[1]/descendant::td[2]")));

        $roomsInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrival date'))}]/preceding::text()[{$this->starts($this->t('Your stay'))}]/ancestor::tr[1]/descendant::td[2]");
        $this->logger->debug($roomsInfo);

        if (preg_match("/(\d+)\s*{$this->opt($this->t('people(s)'))}[\s\–]+(\d+)\s*{$this->opt($this->t('room'))}/u", $roomsInfo, $m)) {
            $h->booked()
                ->guests($m[1])
                ->rooms($m[2]);
        }

        $roomDescription = $this->http->FindSingleNode("//text()[{$this->starts($this->t('View hotel'))}]/following::text()[{$this->starts($this->t('Room '))}][not({$this->contains($this->t('Room holding policy'))})]/following::text()[normalize-space()][1]/ancestor::p[1]");

        if (!empty($roomDescription)) {
            $room = $h->addRoom();
            $room->setDescription($roomDescription);
        }

        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('TOTAL'))}]");

        if (preg_match("/\:\s*(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})/", $total, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $account = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Membre ID'))}]", null, true, "/\:\s*(.+)/");

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectLang();

        $this->ParseHotel($email);

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

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Free cancellation up to\s*(?<hours>\d+)h\s*on the day of the booking on\s*(?<day>\d+\/\d+\/\d+)/u", $cancellationText, $m)
        || preg_match("/Annulation sans frais jusqu\'à (?<hours>\d+)h \(heure locale\) le (?<day>\d+\/\d+\/\d+)\.?/u", $cancellationText, $m)
        || preg_match("/Annulation gratuite jusqu\'à (?<hours>\d+)h le (?<day>\d+\/\d+\/\d+)/u", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m['day'] . ', ' . $m['hours'] . ':00'));
        }
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = '.print_r( $str,true));

        $in = [
            "#^(\d+)\/(\d+)\/(\d{4})\s*\D+(\d+)\D(\d+)$#u", //17/06/2022 check-in 15h00
            "#^(\d+)\/(\d+)\/(\d{2})\,\s*([\d\:]+)$#", //16/06/22, 16:00
        ];
        $out = [
            "$1.$2.$3 $4:$5",
            "$1.$2.20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function detectLang()
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectLang as $lang => $detects) {
            foreach ($detects as $word) {
                if (stripos($body, $word) !== false) {
                    $this->lang = $lang;
                    $this->logger->warning($lang);

                    return true;
                }
            }
        }

        return false;
    }
}
