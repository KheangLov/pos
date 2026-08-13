<?php

namespace App\Services;

use KHQR\Api\Transaction;
use KHQR\BakongKHQR;
use KHQR\Helpers\KHQRData;
use KHQR\Models\IndividualInfo;
use KHQR\Models\MerchantInfo;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class KhqrService
{
    /**
     * Generates a KHQR payload for a Bakong account.
     *
     * Uses a formal merchant QR (KHQR\Models\MerchantInfo) only when both
     * $merchantId and $acquiringBank are supplied - that's Bakong-registered
     * merchant data most small shops don't have. Otherwise falls back to an
     * individual-account QR (KHQR\Models\IndividualInfo), which only needs
     * the Bakong account ID/phone number.
     *
     * @return array{qr: string, md5: string}|null null if the vendor package
     *                                             isn't installed or generation fails.
     */
    public function generateKhqr(
        string $bakongId,
        float $amount,
        string $currency,
        string $merchantName,
        string $city = 'Phnom Penh',
        ?string $merchantId = null,
        ?string $acquiringBank = null,
    ): ?array {
        if (! class_exists('\KHQR\BakongKHQR')) {
            return null;
        }

        $currencyCode = $currency === 'KHR' ? KHQRData::CURRENCY_KHR : KHQRData::CURRENCY_USD;
        $merchantName = substr($merchantName, 0, 25);

        try {
            if ($merchantId && $acquiringBank) {
                $info = new MerchantInfo(
                    bakongAccountID: $bakongId,
                    merchantName: $merchantName,
                    merchantCity: $city,
                    merchantID: $merchantId,
                    acquiringBank: $acquiringBank,
                    currency: $currencyCode,
                    amount: $amount,
                    storeLabel: 'POS Terminal',
                    terminalLabel: 'POS-01',
                );

                $response = BakongKHQR::generateMerchant($info);
            } else {
                $info = new IndividualInfo(
                    bakongAccountID: $bakongId,
                    merchantName: $merchantName,
                    merchantCity: $city,
                    currency: $currencyCode,
                    amount: $amount,
                    storeLabel: 'POS Terminal',
                    terminalLabel: 'POS-01',
                );

                $response = BakongKHQR::generateIndividual($info);
            }
        } catch (\Throwable) {
            return null;
        }

        // generateMerchant()/generateIndividual() both build $data as a plain
        // ['qr' => ..., 'md5' => ...] array, not an object - see
        // KHQR\BakongKHQR::generateMerchant()/generateIndividual(). Both also
        // always construct their KHQRResponse with a hardcoded null error
        // object, so response->status['code'] is unconditionally 0 here and
        // isn't worth checking - real failures throw KHQRException instead
        // (caught above), they don't come back as a non-zero status code.
        if (! isset($response->data['qr'])) {
            return null;
        }

        return [
            'qr' => $response->data['qr'],
            'md5' => $response->data['md5'],
        ];
    }

    /**
     * Generate an SVG representation of the QR code string.
     */
    public function generateQrImage(string $qrString, int $size = 300): string
    {
        // returns an SVG string
        return (string) QrCode::size($size)->margin(1)->generate($qrString);
    }

    /**
     * Asks Bakong whether a generated KHQR was actually paid.
     *
     * Requires a live bearer token (KHQR\BakongKHQR::renewToken(), stored on
     * PaymentMethod::bakong_token). Deliberately never returns a hard "no" -
     * the vendor README only documents this response envelope
     * (responseCode/responseMessage/errorCode/data) for renewToken(), not
     * for check_transaction_by_md5 specifically, and this hasn't been run
     * against a real Bakong account. A caller should treat anything short of
     * true as "still unknown, keep the payment pending" - never as
     * confirmation the payment failed.
     *
     * @return bool true only when Bakong's response envelope reports success
     *              (responseCode 0) with data present; false in every other
     *              case (no token, network/API error, still unmatched).
     */
    public function checkTransaction(string $token, string $md5, bool $isTest = false): bool
    {
        if (! class_exists('\KHQR\Api\Transaction') || $token === '' || $md5 === '') {
            return false;
        }

        try {
            $result = Transaction::checkTransactionByMD5($token, $md5, $isTest);
        } catch (\Throwable) {
            return false;
        }

        return isset($result['responseCode']) && (int) $result['responseCode'] === 0 && ! empty($result['data']);
    }
}
