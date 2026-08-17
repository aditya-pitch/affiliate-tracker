<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The sign-in code was generated but could not be delivered.
 *
 * Distinguished from any other failure because it has a specific remedy: the
 * account is fine, the password was right, and retrying may well work. What is
 * broken is the mail transport, which is an operational problem on our side and
 * should never be shown to a creator as though they did something wrong.
 */
class CouldNotSendLoginCode extends RuntimeException
{
}
