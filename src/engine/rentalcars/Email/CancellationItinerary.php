<?php

namespace AwardWallet\Engine\rentalcars\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CancellationItinerary extends \TAccountChecker
{
    public $mailFiles = "rentalcars/it-110009038.eml, rentalcars/it-144385244.eml, rentalcars/it-144940683.eml, rentalcars/it-146476282.eml";

    public $lang = '';
    public $subject;

    public static $dictionary = [
        'en' => [
            'confNumber'          => ['Itinerary Number is'],
            'statusPhrases'       => 'has been successfully',
            'statusVariants'      => ['cancelled', 'canceled'],
            'cancellationPhrases' => [
                'Thank you for your cancellation request',
                'Thank you for your cancelation request',
            ],
        ],

        'fr' => [
            //'confNumber'          => [''],
            'statusPhrases'       => 'Votre réservation a été',
            'statusVariants'      => ['annulée'],
            'cancellationPhrases' => [
                'Votre réservation a été annulée',
            ],
            'Dear' => 'Cher/Chère',
        ],

        'pt' => [
            //'confNumber'          => [''],
            'statusPhrases'       => 'A sua reserva foi',
            'statusVariants'      => ['cancelada'],
            'cancellationPhrases' => [
                'A sua reserva foi cancelada',
            ],
            'Dear' => ['Caro (a)', 'Estimado(a)'],
        ],

        'es' => [
            //'confNumber'          => [''],
            'statusPhrases'       => 'Hemos',
            'statusVariants'      => ['cancelado'],
            'cancellationPhrases' => [
                'Hemos cancelado',
            ],
            'Dear' => ['Estimado(a)'],
        ],

        'no' => [
            //'confNumber'          => [''],
            'statusPhrases'       => 'Din booking er nå',
            'statusVariants'      => ['avbestilt'],
            'cancellationPhrases' => [
                'Din booking er nå avbestilt',
            ],
            'Dear' => ['Hei'],
        ],
    ];

    private $detectors = [
        'no' => [''],
        'es' => ['Gastos de cancelación'],
        'pt' => ['A sua reserva foi cancelada'],
        'fr' => ['Votre réservation a été annulée'],
        'en' => ['Thank you for your cancellation request'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@reservations.rentalcars.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Cancellation of your Rentalcars.com Itinerary for') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".rentalcars.com/") or contains(@href,"reservations.rentalcars.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"Rentalcars.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('CancellationItinerary' . ucfirst($this->lang));

        $this->parseCar($email);

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

    private function parseCar(Email $email): void
    {
        $car = $email->add()->rental();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]");

        if (preg_match("/({$this->opt($this->t('confNumber'))})\s+([-A-Z\d]{5,})$/", $confirmation, $m)) {
            $car->general()->confirmation($m[2], preg_replace("/\s+is$/i", '', $m[1]));
        } elseif (empty($confirmation)) {
            if (preg_match("/Rentalcars\.com(?:\s*\w+)?[\s\-\–]+\w+\s*\:\s*(\d+)/u", $this->subject, $m)) {
                $car->general()->confirmation($m[1]);
            }
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u");
        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}\s+({$this->opt($this->t('statusVariants'))})/");
        $car->general()->traveller($traveller)->status($status);

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancellationPhrases'))}]")->length > 0) {
            $car->general()->cancelled();
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['statusPhrases'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['statusPhrases'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
