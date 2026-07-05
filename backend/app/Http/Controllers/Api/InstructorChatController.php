<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\InstructorChat;
use App\Models\InstructorChatMember;
use App\Models\InstructorChatMessage;
use App\Models\InstructorChatRead;
use App\Services\FirebasePushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Czaty grupowe instruktora.
 *
 * Instruktor tworzy NAZWANY czat, zaznaczając dowolny podzbiór uczestników
 * swoich grup (np. „zaznaczam 5 osób z zespołu"). Do czatu dołączani są też
 * rodzice tych uczestników (mogą czytać/odpisywać w imieniu dziecka).
 *
 * Dostęp do czatu mają: właściciel (instruktor) oraz członkowie
 * (uczestnicy + ich rodzice). Wysłanie wiadomości robi fan-out push do
 * wszystkich pozostałych członków.
 */
class InstructorChatController extends Controller
{
    /** @var FirebasePushService */
    private $push;

    public function __construct(FirebasePushService $push)
    {
        $this->push = $push;
    }

    /**
     * GET /api/instructor/chats
     * Czaty, w których uczestniczy zalogowany (jako instruktor lub członek).
     */
    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        $chatIds = $this->visibleChatIds($userId);
        if (empty($chatIds)) {
            return response()->json(['status' => '200', 'body' => [], 'recordCount' => 0]);
        }

        $chats = InstructorChat::whereIn('id', $chatIds)->orderByDesc('updated_at')->get();

        // Ostatnia wiadomość per czat.
        $lastByChat = InstructorChatMessage::whereIn('chat_id', $chatIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('chat_id')
            ->map(fn ($g) => $g->first());

        $reads = InstructorChatRead::where('user_id', $userId)
            ->whereIn('chat_id', $chatIds)
            ->pluck('last_read_at', 'chat_id');

        $unreadByChat = $this->unreadCounts($chatIds, $userId, $reads->all());

        $body = $chats->map(function ($c) use ($lastByChat, $unreadByChat, $userId) {
            $last = $lastByChat[$c->id] ?? null;
            return [
                'id'          => (int) $c->id,
                'name'        => $c->name,
                'memberCount' => (int) $c->member_count,
                'isOwner'     => (int) $c->instructor_user_id === $userId,
                'lastMessage' => $last ? $last->body : null,
                'lastAt'      => $last ? optional($last->created_at)->toIso8601String() : null,
                'unread'      => (int) ($unreadByChat[$c->id] ?? 0),
            ];
        })->values();

        return response()->json(['status' => '200', 'body' => $body, 'recordCount' => $body->count()]);
    }

    /**
     * POST /api/instructor/chats
     * Body: name, participantUserIds[] (uczestnicy z grup instruktora)
     */
    public function store(Request $request): JsonResponse
    {
        $employeeIds = $this->employeeIds($userId = (int) $request->user()->getKey());
        if (empty($employeeIds)) {
            return response()->json(['message' => 'Konto nie jest powiązane z instruktorem.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name'                 => ['required', 'string', 'max:120'],
            'participantUserIds'   => ['required', 'array', 'min:1'],
            'participantUserIds.*' => ['integer', 'min:1'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Nieprawidłowe dane.', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $requested = array_values(array_unique(array_map('intval', $data['participantUserIds'])));

        // Tylko uczestnicy z grup instruktora.
        $allowed = $this->instructorParticipantIds($employeeIds);
        $participants = array_values(array_intersect($requested, $allowed));
        if (empty($participants)) {
            return response()->json(['message' => 'Żaden z wybranych uczestników nie należy do Twoich grup.'], 422);
        }

        $chat = InstructorChat::create([
            'instructor_user_id' => $userId,
            'name'               => $data['name'],
            'member_count'       => 0,
        ]);

        // Członkowie: instruktor + uczestnicy + rodzice uczestników.
        $members = [$userId => 'instructor'];
        foreach ($participants as $pid) {
            $members[$pid] = 'participant';
            foreach ($this->parentsOf($pid) as $parentId) {
                if (!isset($members[$parentId])) {
                    $members[$parentId] = 'parent';
                }
            }
        }

        foreach ($members as $uid => $role) {
            InstructorChatMember::create([
                'chat_id' => $chat->id,
                'user_id' => $uid,
                'role'    => $role,
            ]);
        }
        $chat->update(['member_count' => count($members)]);

        return response()->json([
            'status'  => '200',
            'message' => 'Czat utworzony.',
            'data'    => [
                'id'          => (int) $chat->id,
                'name'        => $chat->name,
                'memberCount' => count($members),
            ],
        ], 201);
    }

    /**
     * GET /api/instructor/chats/{chatId}/messages
     * Wiadomości czatu + oznaczenie jako przeczytane.
     */
    public function messages(Request $request, int $chatId): JsonResponse
    {
        $userId = (int) $request->user()->getKey();
        if (!$this->canAccess($userId, $chatId)) {
            return response()->json(['message' => 'Brak dostępu do tego czatu.'], 403);
        }

        $messages = InstructorChatMessage::where('chat_id', $chatId)
            ->orderBy('created_at')
            ->get();

        $names = $this->userNames($messages->pluck('sender_user_id')->all());

        InstructorChatRead::updateOrCreate(
            ['chat_id' => $chatId, 'user_id' => $userId],
            ['last_read_at' => now()]
        );

        $body = $messages->map(fn ($m) => [
            'id'         => (int) $m->id,
            'body'       => $m->body,
            'mine'       => (int) $m->sender_user_id === $userId,
            'senderRole' => $m->sender_role,
            'senderName' => $names[(int) $m->sender_user_id] ?? 'Użytkownik',
            'createdAt'  => optional($m->created_at)->toIso8601String(),
        ])->values();

        return response()->json(['status' => '200', 'body' => $body, 'recordCount' => $body->count()]);
    }

    /**
     * POST /api/instructor/chats/{chatId}/messages
     * Body: body
     */
    public function send(Request $request, int $chatId): JsonResponse
    {
        $userId = (int) $request->user()->getKey();
        if (!$this->canAccess($userId, $chatId)) {
            return response()->json(['message' => 'Brak dostępu do tego czatu.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'body' => ['required', 'string', 'max:2000'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Nieprawidłowe dane.', 'errors' => $validator->errors()], 422);
        }

        $chat = InstructorChat::find($chatId);
        $text = trim((string) $request->input('body'));

        $role = $this->memberRole($userId, $chatId);

        $message = InstructorChatMessage::create([
            'chat_id'        => $chatId,
            'sender_user_id' => $userId,
            'sender_role'    => $role,
            'body'           => $text,
        ]);
        $chat->touch();

        // Fan-out push do pozostałych członków.
        $recipientIds = InstructorChatMember::where('chat_id', $chatId)
            ->where('user_id', '!=', $userId)
            ->pluck('user_id')->map(fn ($v) => (int) $v)->all();

        $senderName = $this->userName($userId);
        $title = $chat->name;
        $deepLink = 'edschat://chat?c=' . $chatId . '&n=' . rawurlencode($chat->name);
        $pushBody = $senderName . ': ' . mb_strimwidth($text, 0, 120, '…');

        $sent = 0;
        foreach ($recipientIds as $uid) {
            try {
                $this->push->sendToUser($uid, $title, $pushBody, 'instructor', $deepLink);
                $sent++;
            } catch (\Throwable $e) {
                // pojedynczy błąd nie blokuje reszty
            }
        }

        return response()->json([
            'status'         => '200',
            'message'        => 'Wysłano.',
            'recipientCount' => $sent,
            'data'           => [
                'id'        => (int) $message->id,
                'createdAt' => optional($message->created_at)->toIso8601String(),
            ],
        ], 201);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function employeeIds(int $usersId): array
    {
        return Employee::where('UsersID', $usersId)
            ->where(function ($q) {
                $q->whereNull('Cancelled')->orWhere('Cancelled', 0);
            })
            ->pluck('EmployeesID')->map(fn ($v) => (int) $v)->all();
    }

    /** Grupy instruktora z courses.instructorEmployeesIDList. */
    private function instructorGroupIds(array $employeeIds): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        $query = DB::table('courses')
            ->where('cancelled', 0)
            ->where('instructorEmployeesIDList', '<>', '');
        $query->where(function ($q) use ($employeeIds) {
            foreach ($employeeIds as $employeeId) {
                $q->orWhereRaw(
                    'FIND_IN_SET(?, REPLACE(instructorEmployeesIDList, " ", ""))',
                    [(int) $employeeId]
                );
            }
        });

        return $query
            ->pluck('coursesHeadingsID')
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->values()
            ->all();
    }

    /** Uczestnicy wszystkich grup instruktora. */
    private function instructorParticipantIds(array $employeeIds): array
    {
        $groupIds = $this->instructorGroupIds($employeeIds);
        if (empty($groupIds)) {
            return [];
        }
        return DB::table('contracts')
            ->whereIn('coursesHeadingsID', $groupIds)
            ->where('cancelled', 0)
            ->where('usersID', '>', 0)
            ->distinct()
            ->pluck('usersID')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();
    }

    private function parentsOf(int $userId): array
    {
        try {
            return DB::table('usersrelations')
                ->where('UsersID', $userId)->where('Cancelled', 0)
                ->pluck('Parent_UsersID')->map(fn ($v) => (int) $v)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function canAccess(int $userId, int $chatId): bool
    {
        return InstructorChatMember::where('chat_id', $chatId)->where('user_id', $userId)->exists();
    }

    private function memberRole(int $userId, int $chatId): string
    {
        $role = InstructorChatMember::where('chat_id', $chatId)->where('user_id', $userId)->value('role');
        return $role ?: 'participant';
    }

    private function visibleChatIds(int $userId): array
    {
        return InstructorChatMember::where('user_id', $userId)
            ->pluck('chat_id')->map(fn ($v) => (int) $v)->all();
    }

    /** Liczby nieprzeczytanych per czat dla użytkownika. */
    private function unreadCounts(array $chatIds, int $userId, array $lastReadByChat): array
    {
        $messages = InstructorChatMessage::whereIn('chat_id', $chatIds)
            ->where('sender_user_id', '!=', $userId)
            ->get(['chat_id', 'sender_user_id', 'created_at']);

        $result = [];
        foreach ($messages as $m) {
            $lastRead = $lastReadByChat[$m->chat_id] ?? null;
            if ($lastRead === null || $m->created_at > $lastRead) {
                $result[$m->chat_id] = ($result[$m->chat_id] ?? 0) + 1;
            }
        }
        return $result;
    }

    private function userName(int $userId): string
    {
        $u = DB::table('users')->where('UsersID', $userId)->first(['FirstName', 'LastName']);
        if (!$u) {
            return 'Użytkownik';
        }
        $name = trim(($u->FirstName ?? '') . ' ' . ($u->LastName ?? ''));
        return $name !== '' ? $name : 'Użytkownik';
    }

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
