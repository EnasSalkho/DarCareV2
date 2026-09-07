<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a provider authenticates successfully but their account has not
 * been approved by an admin yet, so no token may be issued.
 */
class ProviderNotVerifiedException extends Exception
{
    public function __construct(
        public readonly string $verificationStatus,
        public readonly ?string $rejectionReason = null,
    ) {
        parent::__construct(self::messageFor($verificationStatus));
    }

    private static function messageFor(string $status): string
    {
        return $status === 'rejected'
            ? 'تم رفض حسابك من قبل الإدارة. يرجى مراجعة سبب الرفض وإعادة التقديم.'
            : 'لم يتم تفعيل حسابك بعد. حسابك قيد المراجعة من قبل الإدارة، وسيصلك بريد إلكتروني فور الموافقة عليه.';
    }
}
