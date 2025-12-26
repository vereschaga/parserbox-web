<?php

namespace AwardWallet\Common\Parsing\Web\HAR;

use JMS\Serializer\GraphNavigatorInterface;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\JsonDeserializationVisitor;

class ArrayOrStringHandler implements SubscribingHandlerInterface
{
    public static function getSubscribingMethods()
    {
        return [
            [
                'type'      => 'ArrayOrString',
                'format'    => 'json',
                'direction' => GraphNavigatorInterface::DIRECTION_DESERIALIZATION,
                'method'    => 'deserializeArrayOrString',
            ],
        ];
    }

    public function deserializeArrayOrString(
        JsonDeserializationVisitor $visitor,
                                   $data,
        array $type
    ) {
        if (is_scalar($data)) {
            return $data; // Convert single string into an array with one element
        }

        if (is_array($data)) {
            return $data;
        }

        throw new \InvalidArgumentException('Invalid type, expected array or string.');
    }
}
