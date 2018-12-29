<?php
namespace MSCL\FileInfo;

use Exception;

// TODO: Remove this class to make code flow easier to understand
/**
 * This "notification" is thrown by any media info class, if the specified file hasn't been modified and
 * its cached counterpart is still valid.
 */
class NotModifiedNotification extends Exception
{
    // This class is a "hack" but it's the easiest way to handle this situation.
}
