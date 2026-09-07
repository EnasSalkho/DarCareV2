<?php

namespace App\Http\Middleware;

use App\Modules\Providers\Models\Provider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks providers whose account is not approved.
 *
 * Checking only at login is not enough: a token issued while the account was
 * approved keeps working after an admin rejects it, so the check has to run on
 * every authenticated request too.
 */
class EnsureProviderVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        if ($actor instanceof Provider) {
            $status = $actor->verification_status instanceof \BackedEnum
                ? $actor->verification_status->value
                : (string) $actor->verification_status;

            if ($status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => $status === 'rejected'
                        ? 'تم رفض حسابك من قبل الإدارة. يرجى مراجعة سبب الرفض وإعادة التقديم.'
                        : 'لم يتم تفعيل حسابك بعد. حسابك قيد المراجعة من قبل الإدارة، وسيصلك بريد إلكتروني فور الموافقة عليه.',
                    'data' => null,
                    'errors' => [
                        'verification_status' => $status,
                        'rejection_reason' => $actor->rejection_reason,
                    ],
                ], 400);
            }
        }

        return $next($request);
    }
}
