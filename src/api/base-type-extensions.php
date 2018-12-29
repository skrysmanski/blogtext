<?php
namespace MSCL;

/**
 * Whether $str starts with $with.
 *
 * @param string $str  the string to check
 * @param string $with  the start string
 * @param int $offset  offset within the string to start the comparison at
 *
 * @return bool
 */
function string_starts_with($str, $with, $offset = 0)
{
    $withLen = strlen($with);
    if ($withLen === 1)
    {
        return $str[$offset] === $with[0];
    }
    else
    {
        return substr($str, $offset, strlen($with)) === $with;
    }
}

/**
 * Whether $str ends with $with.
 *
 * @param string $str  the string to check
 * @param string $with  the start string
 *
 * @return bool
 */
function string_ends_with($str, $with)
{
    $withLen = strlen($with);
    if ($withLen === 1)
    {
        return $str[strlen($str) - 1] === $with[0];
    }
    else
    {
        return substr($str, -strlen($with)) === $with;
    }
}
