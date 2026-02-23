<?php

namespace Atendwa\SmsReader\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array   getMessages(array $options = [])
 * @method static array   getMessagesForSenders(array $senders, ?int $sinceMs = null, int $limitEach = 500)
 * @method static ?string getRawResponse(array $options = [])
 *
 * @see \Atendwa\SmsReader\SmsReader
 */
class SmsReader extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'sms-reader';
    }
}
