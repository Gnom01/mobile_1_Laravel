<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\ContactThreadRead;
use App\Models\Employee;
use App\Models\UsersRelation;
use App\Services\FirebasePushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Czat uczestnik (dziecko/dorosły) ↔ instruktor, z dostępem rodzica.
 *
 * Wątek zakotwiczony na (participant, instructor). Dostęp ma:
 *  - sam uczestnik,
 *  - rodzic uczestnika (widzi wątki wszystkich swoich dzieci, może odpisać),
 *  - instruktor.
 * Po wysłaniu wiadomości push leci do wszystkich stron oprócz nadawcy
 * (instruktor + dziecko + rodzice dziecka), z dźwiękiem (kanał eds_high_importance).
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
     * GET /api/contact/participants
     * Uczestnicy, w imieniu których mogę pisać (ja + moje dzieci) wraz z ich instruktorami.
     * Służy do rozpoczęcia nowej rozmowy: wybierz dziecko → wybierz instruktora.
     */
    public function participants(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->getKey();
        $ids = $this->accessibleParticipants($userId);

        $names = $this->userNames($ids);
        $body = [];
        foreach ($ids as $pid) {
            $instructors = $this->instructorsForParticipant($pid);
            $body[] = [
                'userId'      => $pid,
                'name'        => $names[$pid] ?? 'Uczestnik',
                'isSelf'      => $pid === $userId,
                'instructors' => array_values($instructors),
            ];
        }

        return response()->json(['status' => '200', 'body' => $body, 'recordCount' => count($body)]);
    }

    /**
     * GET /api/contact/me
     * Numeryczne UsersID zalogowanego — potrzebne Flutterowi, by jako instruktor
     * otworzyć wątek (participant, instructor=ja) bez wyboru instruktora.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'status' => '200',
            'userId' => (int) $request->user()->getKey(),
        ]);
    }

    /**
     * GET /api/contact/threads
     * Wszystkie wątki widoczne dla zalogowanego (moje + moich dzieci + jako instruktor).
     */
    public function threads(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->getKey();
        $accessible = $this->accessibleParticipants($userId);

        $rows = ContactMessage::query()
            ->where(function ($q) use ($accessible, $userId) {
                $q->whereIn('participant_user_id', $accessible)
                  ->orWhere('instructor_user_id', $userId);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Ostatnia wiadomość per wątek + zbiór wiadomości do liczenia nieprzeczytanych.
        $latest = [];
        $byThread = [];
        foreach ($rows as $m) {
            $byThread[$m->thread_key][] = $m;
            if (!isset($latest[$m->thread_key])) {
                $latest[$m->thread_key] = $m;
            }
        }

        // Nazwy uczestników i instruktorów.
        $userIdsToName = [];
        foreach ($latest as $m) {
            $userIdsToName[$m->participant_user_id] = true;
            $userIdsToName[$m->instructor_user_id] = true;
        }
        $names = $this->userNames(array_keys($userIdsToName));

        // Znaczniki przeczytania.
        $reads = ContactThreadRead::where('user_id', $userId)
            ->whereIn('thread_key', array_keys($latest))
            ->pluck('last_read_at', 'thread_key');

        $body = [];
        foreach ($latest as $key => $m) {
            $lastRead = $reads[$key] ?? null;
            $unread = 0;
            foreach ($byThread[$key] as $msg) {
                if ((int) $msg->sender_user_id !== $userId
                    && ($lastRead === null || $msg->created_at > $lastRead)) {
                    $unread++;
                }
            }
            $body[] = [
                'participantUserId' => (int) $m->participant_user_id,
                'instructorUserId'  => (int) $m->instructor_user_id,
                'participantName'   => $names[$m->participant_user_id] ?? 'Uczestnik',
                'instructorName'    => $names[$m->instructor_user_id] ?? 'Instruktor',
                'lastMessage'       => $m->body,
                'lastAt'            => optional($m->created_at)->toIso8601String(),
                'unread'            => $unread,
                'aboutMe'           => (int) $m->participant_user_id === $userId,
            ];
        }

        return response()->json(['status' => '200', 'body' => $body, 'recordCount' => count($body)]);
    }

    /**
     * GET /api/contact/conversation?participantUserId=&instructorUserId=
     * Wątek + oznaczenie jako przeczytany przez bieżącego użytkownika.
     */
    public function conversation(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->getKey();
        $participantId = (int) $request->query('participantUserId', 0);
        $instructorId = (int) $request->query('instructorUserId', 0);

        if (!$this->canAccessThread($userId, $participantId, $instructorId)) {
            return response()->json(['message' => 'Brak dostępu do tej rozmowy.'], 403);
        }

        $key = ContactMessage::threadKey($participantId, $instructorId);

        $messages = ContactMessage::where('thread_key', $key)
            ->orderBy('created_at')
            ->get();

        // Oznacz przeczytane.
        ContactThreadRead::updateOrCreate(
            ['user_id' => $userId, 'thread_key' => $key],
            ['last_read_at' => now()]
        );

        $body = $messages->map(fn ($m) => [
            'id'         => $m->id,
            'body'       => $m->body,
            'mine'       => (int) $m->sender_user_id === $userId,
            'senderRole' => $m->sender_role,
            'createdAt'  => optional($m->created_at)->toIso8601String(),
        ]);

        return response()->json(['status' => '200', 'body' => $body, 'recordCount' => $body->count()]);
    }

    /**
     * POST /api/contact/messages
     * Body: participantUserId, instructorUserId, body
     */
    public function send(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'participantUserId' => ['required', 'integer', 'min:1'],
            'instructorUserId'  => ['required', 'integer', 'min:1'],
            'body'              => ['required', 'string', 'max:2000'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Nieprawidłowe dane.', 'errors' => $validator->errors()], 422);
        }

        $senderId = (int) $request->user()->getKey();
        $participantId = (int) $request->input('participantUserId');
        $instructorId = (int) $request->input('instructorUserId');
        $text = trim((string) $request->input('body'));

        if (!$this->canAccessThread($senderId, $participantId, $instructorId)) {
            return response()->json(['message' => 'Brak dostępu do tej rozmowy.'], 403);
        }

        // Rola nadawcy w wątku.
        if ($senderId === $instructorId) {
            $senderRole = 'instructor';
        } elseif ($senderId === $participantId) {
            $senderRole = 'participant';
        } else {
            $senderRole = 'parent';
        }

        $message = ContactMessage::create([
            'thread_key'          => ContactMessage::threadKey($participantId, $instructorId),
            'participant_user_id' => $participantId,
            'instructor_user_id'  => $instructorId,
            'sender_user_id'      => $senderId,
            'sender_role'         => $senderRole,
            'body'                => $text,
        ]);

        // Fan-out push: instruktor + dziecko + rodzice dziecka — oprócz nadawcy.
        $recipients = array_merge(
            [$instructorId, $participantId],
            $this->parentsOf($participantId)
        );
        $recipients = array_values(array_unique(array_filter(
            $recipients,
            fn ($id) => (int) $id !== $senderId && (int) $id > 0
        )));

        $names = $this->userNames([$senderId, $participantId, $instructorId]);
        $senderName = $names[$senderId] ?? 'Użytkownik';
        $isInstructorSender = $senderRole === 'instructor';
        $title = $isInstructorSender ? 'Wiadomość od instruktora' : "Nowa wiadomość: {$senderName}";
        $category = $isInstructorSender ? 'instructor' : 'message';

        // Deep-link do wątku — pozwala otworzyć rozmowę (z polem pisania)
        // prosto z powiadomienia. Format obsługuje aplikacja Flutter.
        $deepLink = 'edschat://open?p=' . $participantId
            . '&i=' . $instructorId
            . '&pn=' . rawurlencode($names[$participantId] ?? '')
            . '&in=' . rawurlencode($names[$instructorId] ?? '');

        foreach ($recipients as $uid) {
            try {
                $this->push->sendToUser((int) $uid, $title, mb_strimwidth($text, 0, 120, '…'), $category, $deepLink);
            } catch (\Throwable $e) {
                // pojedyncza wysyłka nie blokuje reszty
            }
        }

        return response()->json([
            'status'         => '200',
            'message'        => 'Wysłano.',
            'recipientCount' => count($recipients),
            'data'           => ['id' => $message->id, 'createdAt' => optional($message->created_at)->toIso8601String()],
        ], 201);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    /** Dzieci użytkownika (UsersID). */
    private function childrenOf(int $userId): array
    {
        try {
            return UsersRelation::where('Parent_UsersID', $userId)->where('Cancelled', 0)
                ->pluck('UsersID')->map(fn ($v) => (int) $v)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Rodzice użytkownika (UsersID). */
    private function parentsOf(int $userId): array
    {
        try {
            return UsersRelation::where('UsersID', $userId)->where('Cancelled', 0)
                ->pluck('Parent_UsersID')->map(fn ($v) => (int) $v)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Uczestnicy, których wątki widzę: ja + moje dzieci. */
    private function accessibleParticipants(int $userId): array
    {
        return array_values(array_unique(array_merge([$userId], $this->childrenOf($userId))));
    }

    /** Czy user ma dostęp do wątku (participant, instructor). */
    private function canAccessThread(int $userId, int $participantId, int $instructorId): bool
    {
        if ($participantId <= 0 || $instructorId <= 0) {
            return false;
        }
        // Instruktor musi faktycznie prowadzić grupę uczestnika.
        if (!in_array($instructorId, array_keys($this->instructorsForParticipant($participantId)), true)) {
            // Pozwól też, gdy wątek już istnieje (dane historyczne).
            $exists = ContactMessage::where('thread_key', ContactMessage::threadKey($participantId, $instructorId))->exists();
            if (!$exists) {
                return false;
            }
        }
        if ($userId === $instructorId) {
            return true;
        }
        // Uczestnik lub jego rodzic.
        return in_array($participantId, $this->accessibleParticipants($userId), true);
    }

    /** ID grup, do których chodzi uczestnik. */
    private function userGroupIds(int $userId): array
    {
        $a = DB::table('usersproducts')->where('usersid', $userId)->where('cancelled', 0)
            ->distinct()->pluck('coursesheadingsid')->all();
        $b = DB::table('usersschedules')->where('usersid', $userId)->where('cancelled', 0)
            ->distinct()->pluck('coursesheadingsid')->all();
        return array_values(array_unique(array_map('intval', array_merge($a, $b))));
    }

    /**
     * Instruktorzy grup uczestnika: [instructorUserId => ['instructorUserId','name','groups']].
     */
    private function instructorsForParticipant(int $participantId): array
    {
        $groupIds = $this->userGroupIds($participantId);
        if (empty($groupIds)) {
            return [];
        }

        // Nazwy grup z coursesheadings (pełny zbiór, nie z wąskiej tabeli courses).
        $groupNames = DB::table('coursesheadings')
            ->whereIn('CoursesHeadingsID', $groupIds)
            ->pluck('CourseHeadingName', 'CoursesHeadingsID');

        // Instruktorzy grup wyznaczani z harmonogramu (scheduleseventssettlements.instructorsIDList),
        // bo `courses` zawiera tylko podzbiór „oferty WWW".
        $scheduleRows = DB::table('scheduleseventssettlements')
            ->where('cancelled', 0)
            ->whereIn('coursesHeadingsID', $groupIds)
            ->where('instructorsIDList', '<>', '')
            ->select('coursesHeadingsID', 'instructorsIDList')
            ->distinct()
            ->get();

        $byEmployee = [];
        foreach ($scheduleRows as $r) {
            $eids = array_filter(array_map('trim', explode(',', (string) $r->instructorsIDList)));
            $gname = $groupNames[$r->coursesHeadingsID] ?? '';
            foreach ($eids as $eid) {
                if (is_numeric($eid)) {
                    $byEmployee[(int) $eid]['groups'][$gname] = true;
                }
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
                continue;
            }
            $name = trim(($emp->FirstName ?? '') . ' ' . ($emp->LastName ?? ''));
            $result[$uid] = [
                'instructorUserId' => $uid,
                'name'             => $name !== '' ? $name : 'Instruktor',
                'groups'           => array_values(array_filter(array_keys($byEmployee[$emp->EmployeesID]['groups'] ?? []))),
            ];
        }

        return $result;
    }

    private function isInstructor(int $userId): bool
    {
        try {
            return Employee::where('UsersID', $userId)
                ->where(function ($q) {
                    $q->whereNull('Cancelled')->orWhere('Cancelled', 0);
                })->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Mapa UsersID => pełna nazwa. */
    private function userNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return [];
        }
        $rows = DB::table('users')->whereIn('UsersID', $ids)->get(['UsersID', 'FirstName', 'LastName']);
        $map = [];
        foreach ($rows as $r) {
            $n = trim(($r->FirstName ?? '') . ' ' . ($r->LastName ?? ''));
            $map[(int) $r->UsersID] = $n !== '' ? $n : 'Użytkownik';
        }
        return $map;
    }
}
