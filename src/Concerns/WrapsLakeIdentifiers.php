<?php

namespace StringKe\LakePhp\Laravel\Concerns;

trait WrapsLakeIdentifiers
{
    protected function wrapValue($value)
    {
        return $value === '*' ? $value : '`'.str_replace('`', '``', $value).'`';
    }

    public function getDateFormat()
    {
        return 'Y-m-d H:i:s.u';
    }

    protected function quoteLiteral(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "''"], $value)."'";
    }
}
