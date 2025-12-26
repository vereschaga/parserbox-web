<?php

namespace AwardWallet\Engine\greyhound\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "greyhound/it-238656904.eml";
    public $subjects = [
        'Greyhound Ticket Purchase Confirmation and Itinerary',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@greyhound.com') !== false) {
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
        $body = $parser->getBody();

        if (stripos($body, 'Greyhound') !== false) {
            if (
                stripos($body, 'TRAVEL INFORMATION') !== false
             && stripos($body, 'Trip to') !== false
             && stripos($body, 'PAYMENT') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]greyhound\.com$/', $from) > 0;
    }

    public function ParseTrain(Email $email, $text)
    {
        $b = $email->add()->bus();

        $passengerText = $this->re("/PASSENGERS(.+)PAYMENT/s", $text);

        if (preg_match_all("/\n(\D+)\s\D[\d\.\,]+\s*[A-Z]{2,3}\n/u", $passengerText, $m)) {
            $b->general()
                ->travellers($m[1], true);
        }

        $b->general()
            ->confirmation($this->re("/{$this->opt($this->t('Your reference number is'))}\s*(\d+)/", $text));

        $totalText = $this->re("/{$this->opt($this->t('Total:'))}\s*(?<priceText>\D[\d\.\,]+)\n*/u", $text);

        if (preg_match("/^(?<currency>\D)(?<total>[\d\.\,]+)$/", $totalText, $m)) {
            $b->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $segmentsText = $this->re("/\n+^[\-\s]+Trip\s*to\D*[\-]{5,}\n+(.+Address.*)\n+Note\:/msu", $text);

        if (empty($segmentsText)) {
            $segmentsText = $this->re("/\n+^[\-\s]+Trip\s*to\D*[\-]{5,}\n*(.+)\n*Note\:/msu", $text);
        }

        if (preg_match_all("/([\d\/]+\s*[\d\:]+A?P?M\s*[A-Z\d]*\-\d{1,4}[\s\*]+(?:Depart|Arrive)\s*.*)/", $segmentsText, $m)) {
            foreach ($m[1] as $key => $segment) {
                if ($key === 0) {
                    $s = $b->addSegment();

                    $depAddress = str_replace('   *   *  ', ', ', $this->re("/^.+\nAddress\:\s*(.+)/", $segmentsText));

                    if (!empty($depAddress)) {
                        $s->departure()
                            ->address($depAddress);
                    }
                }

                if (preg_match("/(?<dateTime>[\d\/]+\s*[\d\:]+A?P?M)\s*[A-Z\d]*\-(?<number>\d{1,4})[\s\*]+(?:Depart)\s*(?<depName>.*)/", $segment, $match)) {
                    $s->setNumber($match['number']);

                    $s->departure()
                        ->name($match['depName'])
                        ->date(strtotime($match['dateTime']));
                } elseif (preg_match("/(?<dateTime>[\d\/]+\s*[\d\:]+A?P?M)\s*[A-Z\d]*\-(?<number>\d{1,4})[\s\*]+(?:Arrive)\s*(?<arrName>.*)/", $segment, $match)) {
                    $s->arrival()
                        ->name($match['arrName'])
                        ->date(strtotime($match['dateTime']));

                    if ($key > 0 && $key < count($m[1]) - 1) {
                        $s = $b->addSegment();
                    }

                    $arrAddress = str_replace('   *   *  ', ', ', $this->re("/Address\:\s*(.+)$/", $segmentsText));

                    if (!empty($arrAddress)) {
                        if ($key == count($m[1]) - 1) {
                            $s->arrival()
                                ->address($arrAddress);
                        }
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = $parser->getBody();

        $this->ParseTrain($email, $text);

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
