<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Employee;
use App\Services\FirebasePushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Czat uczestnik (rodzic/dziecko) ↔ instruktor.
 *
 * Uczestnik widzi instruktorów grup, do których chodzi (lub jego dzieci),
 * może napisać do wybranego instruktora; instruktor dostaje push i może
 * odpowiedzieć. Bezpieczeństwo: pisać można tylko między osobami, które
 * łączy wspólna grupa (instruktor ↔ uczestnik).
 */
class ContactController extends Controller
{
    /** @var FirebasePushService */
    private $push;

    public function __construct(FirebasePushService $push)
    {
        $this->push = $push;
    }

    /**
     * GET /api/contact/instructors
     * Instruktorzy grup, do których chodzi zalogowany użytkownik.
     */
    public function instructors(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->getKey();
        $list = $this->instructorsForUser($userId);

        return response()->json([
            'status'      => '200',
            'body'        => array_values($list),
            'recordCount' => count($list),
        ]);
    }

    /**
     * GET /api/contact/conversation/{instructorUserId}
     * Wątek wiadomości między zalogowanym użytkownikiem a instruktorem.
     */
    public function conversation(Request $request, int $instructorUserId): JsonResponse
    {
        $userId = (int) $request->user()->getKey();
        $key = ContactMessage::conversationKey($userId, $instructorUserId);

        // Oznacz jako przeczytane wiadomości przychodzące do mnie.
        ContactMessage::where('conversation_key', $key)
            ->where('recipient_user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = ContactMessage::where('conversation_key', $key)
            ->orderBy('created_at')
            ->get(['id', 'sender_user_id', 'recipient_user_id', 'sender_role', 'body', 'read_at', 'created_at']);

        $body = $messages->map(fn ($m) => [
            'id'        => $m->id,
            'body'      => $m->body,
            'mine'      => (int) $m->sender_user_id === $userId,
            'senderRole'=> $m->sender_role,
            'createdAt' => optional($m->created_at)->toIso8601String(),
            'read'      => $m->read_at !== null,
        ]);

        return response()->json(['status' => '200', 'body' => $body, 'recordCount' => $body->count()]);
    }

    /**
     * POST /api/contact/messages
     * Wysyła wiadomość do drugiej osoby (instruktor↔uczestnik) + push.
     * Body: toUserId, body
     */
    public function send(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'toUserId' => ['required', 'integer', 'min:1'],
            'body'     => ['required', 'string', 'max:2000'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Nieprawidłowe dane.', 'errors' => $validator->errors()], 422);
        }

        $senderId = (int) $request->user()->getKey();
        $toUserId = (int) $request->input('toUserId');
        $text     = trim((string) $request->input('body'));

        if ($senderId === $toUserId) {
            return response()->json(['message' => 'Nieprawidłowy odbiorca.'], 422);
        }

        // Autoryzacja: nadawca i odbiorca muszą mieć wspólną grupę (instruktor↔uczestnik).
        $allowed = in_array($toUserId, $this->instructorUserIds($senderId), true)
            || in_array($senderId, $this->instructorUserIds($toUserId), true);
        if (!$allowed) {
            return response()->json(['message' => 'Brak wspólnej grupy z tym odbiorcą.'], 403);
        }

        $senderIsInstructor = $this->isInstructor($senderId);
        $senderRole = $senderIsInstructor ? 'instructor' : 'participant';

        $message = ContactMessage::create([
            'conversation_key'  => ContactMessage::conversationKey($senderId, $toUserId),
            'sender_user_id'    => $senderId,
            'recipient_user_id' => $toUserId,
            'sender_role'       => $senderRole,
            'body'              => $text,
        ]);

        // Push do odbiorcy. Kategoria 'instructor' gdy pisze instruktor → pojawi
        // się w sekcji „Wiadomości od instruktora i szkoły"; w drugą stronę 'message'.
        try {
            $senderName = $this->userName($senderId);
            $title = $senderIsInstructor
                ? "Wiadomość od instruktora"
                : "Nowa wiadomość: {$senderName}";
            $this->push->sendToUser(
                $toUserId,
                $title,
                mb_strimwidth($text, 0, 120, '…'),
                $senderIsInstructor ? 'instructor' : 'message'
            );
        } catch (\Throwable $e) {
            // wysyłka push nie jest krytyczna dla zapisu wiadomości
        }

        return response()->json([
            'status'  => '200',
            'message' => 'Wysłano.',
            'data'    => [
                'id'        => $message->id,
                'createdAt' => optional($message->created_at)->toIso8601String(),
            ],
        ], 201);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    /** ID grup, do których chodzi użytkownik. */
    private function userGroupIds(int $userId): array
    {
        $a = DB::table('usersproducts')->where('usersid', $userId)->where('cancelled', 0)
            ->distinct()->pluck('coursesheadingsid')->all();
        $b = DB::table('usersschedules')->where('usersid', $userId)->where('cancelled', 0)
            ->distinct()->pluck('coursesheadingsid')->all();
        return array_values(array_unique(array_map('intval', array_merge($a, $b))));
    }

    /**
     * Bogata lista instruktorów grup użytkownika (do ekranu kontaktu).
     * @return array<int, array>
     */
    private function instructorsForUser(int $userId): array
    {
        $groupIds = $this->userGroupIds($userId);
        if (empty($groupIds)) {
            return [];
        }

        $courses = DB::table('courses')
            ->whereIn('coursesHeadingsID', $groupIds)
            ->get(['coursesHeadingsID', 'courseHeadingName', 'instructorEmployeesIDList', 'instructorsList']);

        // employeeId => ['groups' => [nazwy]]
        $byEmployee = [];
        foreach ($courses as $c) {
            $eids = array_filter(array_map('trim', explode(',', (string) $c->instructorEmployeesIDList)));
            foreach ($eids as $eid) {
                if (!is_numeric($eid)) {
                    continue;
                }
                $eid = (int) $eid;
                $byEmployee[$eid]['groups'][] = $c->courseHeadingName;
            }
        }
        if (empty($byEmployee)) {
            return [];
        }

        $employees = DB::table('employees')
            ->whereIn('EmployeesID', array_keys($byEmployee))
            ->get(['EmployeesID', 'UsersID', 'FirstName', 'LastName']);

        $result = [];
        foreach ($employees as $emp) {
            $uid = (int) $emp->UsersID;
            if ($uid <= 0) {
                continue; // instruktor bez konta — nie da się napisać
            }
            $fullName = trim(($emp->FirstName ?? '') . ' ' . ($emp->LastName ?? ''));
            $result[$uid] = [
                'instructorUserId' => $uid,
                'employeesID'      => (int) $emp->EmployeesID,
                'name'             => $fullName !== '' ? $fullName : 'Instruktor',
                'groups'           => array_values(array_unique($byEmployee[$emp->EmployeesID]['groups'] ?? [])),
                'unread'           => $this->unreadFrom($uid, $userId),
            ];
        }

        return $result;
    }

    /** Same UsersID instruktorów (do autoryzacji). */
    private function instructorUserIds(int $userId): array
    {
        return array_map('intval', array_keys($this->instructorsForUser($userId)));
    }

    private function unreadFrom(int $fromUserId, int $toUserId): int
    {
        $key = ContactMessage::conversationKey($fromUserId, $toUserId);
        return ContactMessage::where('conversation_key', $key)
            ->where('sender_user_id', $fromUserId)
            ->where('recipient_user_id', $toUserId)
            ->whereNull('read_at')
            ->count();
    }

    private function isInstructor(int $userId): bool
    {
        try {
            return Employee::where('UsersID', $userId)
                ->where(function ($q) {
                    $q->whereNull('Cancelled')->orWhere('Cancelled', 0);
                })
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function userName(int $userId): string
    {
        $u = DB::table('users')->where('UsersID', $userId)->first(['FirstName', 'LastName']);
        if (!$u) {
            return 'Użytkownik';
        }
        $n = trim(($u->FirstName ?? '') . ' ' . ($u->LastName ?? ''));
        return $n !== '' ? $n : 'Użytkownik';
    }
}
