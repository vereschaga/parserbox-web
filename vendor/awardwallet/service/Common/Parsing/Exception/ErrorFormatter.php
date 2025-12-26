<?php

namespace AwardWallet\Common\Parsing\Exception;

class ErrorFormatter
{

    private string $displayName;
    private string $shortName;

    public function __construct(string $displayName, string $shortName)
    {
        $this->displayName = $displayName;
        $this->shortName = $shortName;
    }

    public function format(?string $error) : ?string
    {
        if ($error === null) {
            return $error;
        }

        return str_ireplace(
            [
                '%DISPLAY_NAME%',
                '%SHORT_NAME%',
            ],
            [
                $this->displayName,
                $this->shortName,
            ],
            $error
        );
    }

}