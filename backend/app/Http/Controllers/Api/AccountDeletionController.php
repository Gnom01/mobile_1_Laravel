<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use App\Models\DeviceToken;
use App\Models\OtpRequest;
use App\Services\SerwerSmsClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Usuwanie Konta z aplikacji (część V dokumentu prawnego):
 * żądanie → weryfikacja kodem SMS → blokada logowania, unieważnienie
 * sesji/tokenów i przekazanie żądania do realizacji.
 */
class AccountDeletionController extends Controller
{
    /**
     * Krok 1: wysyłka kodu SMS na numer powiązany z Kontem.
     */
    public function request(Request $request, SerwerSmsClient $sms)
    {
        $user = $request->user();
        $phone = $this->normalizePhone((string) $user->Phone);

        if ($phone === '') {
            return response()->json([
                'success' => false,
                'message' => 'Brak numeru telefonu przypisanego do konta. '
                    . 'Skontaktuj się ze wsparciem: studio@egurrola.com.',
            ], 422);
        }

        // Rate limit: max 3 kody na numer w 15 minut (jak przy logowaniu).
        $recentCount = OtpRequest::where('phone', $phone)
            ->where('created_at', '>', now()->subMinutes(15))
            ->count();
        if ($recentCount >= 3) {
            return response()->json([
                'success' => false,
                'error'   => 'TOO_MANY_REQUESTS',
                'message' => 'Zbyt wiele prób. Odczekaj kilka minut.',
            ], 429);
        }

        $code = (string) random_int(100000, 999999);
        OtpRequest::create([
            'phone'      => $phone,
            'code_hash'  => bcrypt($code),
            'expires_at' => now()->addMinutes(5),
            'attempts'   => 0,
        ]);

        $sms->sendOtp(
            $phone,
            "Kod potwierdzenia usuniecia konta EDS: {$code}. Wazny 5 min.",
            (bool) config('services.sms.test_mode', false),
        );

        Log::info('Account deletion code sent', [
            'user_id'      => $user->UsersID,
            'phone_suffix' => substr($phone, -3),
        ]);

        return response()->json([
            'success'     => true,
            'maskedPhone' => $this->maskPhone($phone),
        ]);
    }

    /**
     * Krok 2: weryfikacja kodu i przyjęcie żądania — blokada logowania,
     * unieważnienie sesji i tokenów push.
     */
    public function confirm(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        $phone = $this->normalizePhone((string) $user->Phone);

        if (!$this->verifyOtpForPhone($phone, $validated['code'])) {
            return response()->json([
                'success' => false,
                'error'   => 'OTP_INVALID',
                'message' => 'Kod jest nieprawidłowy lub wygasł.',
            ], 422);
        }

        AccountDeletionRequest::create([
            'UsersID'      => $user->UsersID,
            'status'       => 'confirmed',
            'requested_at' => now(),
            'confirmed_at' => now(),
        ]);

        // Dezaktywacja tokenów push tego użytkownika.
        try {
            DeviceToken::where('user_id', $user->UsersID)
                ->update(['is_active' => false]);
        } catch (\Throwable $e) {
            Log::warning('Account deletion: push token deactivation failed', [
                'user_id' => $user->UsersID,
                'error'   => $e->getMessage(),
            ]);
        }

        Log::notice('ACCOUNT DELETION CONFIRMED — do realizacji przez administrację', [
            'user_id' => $user->UsersID,
        ]);

        // Unieważnienie wszystkich sesji — od tej chwili logowanie jest
        // blokowane do czasu realizacji żądania.
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Żądanie usunięcia konta zostało przyjęte.',
            'retainedDataInfo' =>
                'Dane niezbędne do wykonania aktywnych umów, rozliczeń '
                . 'podatkowych i rachunkowych, obsługi reklamacji, ochrony '
                . 'małoletnich, zapobiegania nadużyciom oraz ustalenia, '
                . 'dochodzenia lub obrony roszczeń mogą zostać zachowane '
                . 'przez okres wymagany prawem. O zakresie działań '
                . 'poinformujemy na wskazany kanał kontaktu.',
        ]);
    }

    private function verifyOtpForPhone(string $normalizedPhone, string $code): bool
    {
        $otp = OtpRequest::where('phone', $normalizedPhone)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp || $otp->attempts >= 5) {
            return false;
        }

        $otp->increment('attempts');

        if (!Hash::check($code, $otp->code_hash)) {
            return false;
        }

        // Kod jednorazowy — unieważnij po użyciu.
        $otp->update(['expires_at' => now()]);

        return true;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-\(\)]+/', '', $phone) ?? '';
        if (str_starts_with($phone, '+48')) {
            $phone = substr($phone, 3);
        } elseif (str_starts_with($phone, '48') && strlen($phone) === 11) {
            $phone = substr($phone, 2);
        }
        return $phone;
    }

    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 3) {
            return '•••';
        }
        return '••• ••• ' . substr($phone, -3);
    }
}
