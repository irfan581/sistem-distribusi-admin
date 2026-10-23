<?php

namespace App\Http\Controllers\Api\Admin;

use App\Events\ChatMessagePinned;
use App\Events\ChatMessageReacted;
use App\Events\ChatMessageSent;
use App\Events\ChatMessagesRead;
use App\Events\TypingIndicator;
use App\Http\Controllers\Controller;
use App\Jobs\SendChatNotification;
use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\ChatMessage;
use App\Models\ChatMessageRead;
use App\Models\ChatMessageStar;
use App\Models\ChatMessageReaction;
use App\Models\ChatPinnedMessage;
use App\Models\Sales;
use App\Models\User;
use App\Traits\ApiResponse;
use App\Traits\UploadGambar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminChatController extends Controller
{
    use ApiResponse, UploadGambar;

    // Returns all contacts: other admins + all active sales
    public function contacts(Request $request): JsonResponse
    {
        $me = $request->user(); // User model

        // Other admin users
        $admins = User::where('id', '!=', $me->id)
            ->where('is_admin', true)
            ->get()
            ->map(fn($u) => $this->buildContact($me, $u, 'admin'));

        // All active sales
        $salesList = Sales::where('is_active', true)
            ->get()
            ->map(fn($s) => $this->buildContact($me, $s, 'sales'));

        return $this->success([
            'admins' => $admins,
            'sales'  => $salesList,
        ]);
    }

    private function buildContact(User $me, $contact, string $type): array
    {
        $contactClass = get_class($contact);
        $meClass      = get_class($me);

        $lastMsg = ChatMessage::where('type', 'dm')->where('is_deleted', false)
            ->where(function ($q) use ($me, $meClass, $contact, $contactClass) {
                $q->where(fn($q2) => $q2
                    ->where('sender_type', $meClass)->where('sender_id', $me->id)
                    ->where('receiver_type', $contactClass)->where('receiver_id', $contact->id))
                  ->orWhere(fn($q2) => $q2
                    ->where('sender_type', $contactClass)->where('sender_id', $contact->id)
                    ->where('receiver_type', $meClass)->where('receiver_id', $me->id));
            })->latest()->first();

        $unread = ChatMessage::where('type', 'dm')->where('is_deleted', false)
            ->where('sender_type', $contactClass)->where('sender_id', $contact->id)
            ->where('receiver_type', $meClass)->where('receiver_id', $me->id)
            ->whereNotIn('id', function ($q) use ($me, $meClass) {
                $q->select('message_id')->from('chat_message_reads')
                  ->where('reader_type', $meClass)->where('reader_id', $me->id);
            })->count();

        $name            = $type === 'sales' ? $contact->nama : $contact->name;
        $onlineThreshold = now()->subMinutes(2);
        $lastSeen        = $contact->last_seen_at;

        return [
            'id'           => $contact->id,
            'type'         => $type,
            'model_class'  => $contactClass,
            'nama'         => $name,
            'initial'      => strtoupper(substr($name, 0, 1)),
            'area'         => $type === 'sales' ? ($contact->area ?? '') : ($contact->role?->label ?? 'Admin'),
            'last_message' => $lastMsg?->message,
            'last_time'    => $lastMsg?->created_at?->format('H:i'),
            'unread'       => $unread,
            'is_online'    => $lastSeen && $lastSeen->gt($onlineThreshold),
            'last_seen_at' => $lastSeen?->toISOString(),
        ];
    }

    public function dmMessages(Request $request, int $userId): JsonResponse
    {
        $me          = $request->user();
        $meClass     = get_class($me);
        $isAdmin     = $request->query('type') === 'admin';
        $contactClass = $isAdmin ? User::class : Sales::class;
        $beforeId    = $request->query('before_id');   // cursor pagination
        $limit       = 40;

        $query = ChatMessage::where('type', 'dm')
            ->where(function ($q) use ($me, $meClass, $userId, $contactClass) {
                $q->where(fn($q2) => $q2
                    ->where('sender_type', $meClass)->where('sender_id', $me->id)
                    ->where('receiver_type', $contactClass)->where('receiver_id', $userId))
                  ->orWhere(fn($q2) => $q2
                    ->where('sender_type', $contactClass)->where('sender_id', $userId)
                    ->where('receiver_type', $meClass)->where('receiver_id', $me->id));
            });

        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        $raw     = $query->orderBy('id', 'desc')->limit($limit + 1)->get();
        $hasMore = $raw->count() > $limit;
        $msgs    = $raw->take($limit)->reverse()->values(); // take oldest 40, then chronological order

        // Which messages sent by the contact have been read by me?
        $readByContactIds = ChatMessageRead::where('reader_type', $meClass)
            ->where('reader_id', $me->id)
            ->whereIn('message_id', $msgs->pluck('id'))
            ->pluck('message_id')
            ->flip()
            ->all();

        // Which messages I sent have been read by the contact?
        $readByContactMap = ChatMessageRead::where('reader_type', $contactClass)
            ->where('reader_id', $userId)
            ->whereIn('message_id', $msgs->pluck('id'))
            ->pluck('message_id')
            ->flip()
            ->all();

        // Which messages I've starred?
        $starredIds = ChatMessageStar::where('user_type', $meClass)
            ->where('user_id', $me->id)
            ->whereIn('message_id', $msgs->pluck('id'))
            ->pluck('message_id')->flip()->all();

        // Reactions — grouped by message_id
        $msgIds      = $msgs->pluck('id')->all();
        $reactionsRaw = ChatMessageReaction::whereIn('message_id', $msgIds)->get();
        $reactionsMap = [];
        foreach ($reactionsRaw->groupBy('message_id') as $mid => $rows) {
            foreach ($rows->groupBy('emoji') as $emoji => $group) {
                $reactionsMap[$mid][] = [
                    'emoji'         => $emoji,
                    'count'         => $group->count(),
                    'reacted_by_me' => $group->contains(fn($r) => $r->user_type === $meClass && $r->user_id === $me->id),
                ];
            }
        }

        $maps = $this->buildSenderMaps($msgs);
        $messages = $msgs->map(fn($m) => $this->formatMessage($m, $maps, $me, $meClass, $readByContactMap, $starredIds, $reactionsMap));

        // Mark incoming as read
        $unreadIds = ChatMessage::where('type', 'dm')
            ->where('sender_type', $contactClass)->where('sender_id', $userId)
            ->where('receiver_type', $meClass)->where('receiver_id', $me->id)
            ->whereNotIn('id', function ($q) use ($me, $meClass) {
                $q->select('message_id')->from('chat_message_reads')
                  ->where('reader_type', $meClass)->where('reader_id', $me->id);
            })->pluck('id');

        if ($unreadIds->isNotEmpty()) {
            $now = now()->toDateTimeString();
            ChatMessageRead::upsert(
                $unreadIds->map(fn($id) => [
                    'message_id'  => $id,
                    'reader_type' => $meClass,
                    'reader_id'   => $me->id,
                    'read_at'     => $now,
                ])->all(),
                ['message_id', 'reader_type', 'reader_id'],
                ['read_at']
            );

            // Notify the contact (sender) that their messages have been read
            try {
                \App\Support\SiaranChat::coba(fn () => broadcast(new ChatMessagesRead(
                        readerId:   $me->id,
                        readerType: $meClass,
                        senderId:   $userId,
                        senderType: $contactClass,
                    )), 'chat admin');
            } catch (\Throwable) {}
        }

        return $this->success([
            'messages' => $messages,
            'has_more' => $hasMore,
            'oldest_id' => $msgs->first()?->id,
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'message'       => 'nullable|string|max:2000',
            // File: izinkan image + dokumen umum saja — blokir executable/script
            'file'          => 'nullable|file|max:10240|mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,application/zip,application/x-zip-compressed',
            'audio'         => 'nullable|file|max:10240|mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/wav',
            'type'          => 'required|in:dm,group',
            'receiver_id'   => 'required_if:type,dm|nullable|integer',
            'receiver_type' => 'required_if:type,dm|nullable|in:admin,sales',
            'group_id'      => 'required_if:type,group|nullable|integer',
            'reply_to_id'   => 'nullable|integer|exists:chat_messages,id',
            'mentions'      => 'nullable',   // array (JSON body) atau JSON string (FormData)
        ]);

        // Must have message, file, or audio
        if (!$request->filled('message') && !$request->hasFile('file') && !$request->hasFile('audio')) {
            return response()->json(['message' => 'Pesan atau file wajib diisi.'], 422);
        }

        $me = $request->user();

        if ($request->type === 'group') {
            $group = ChatGroup::find($request->group_id);
            if ($group && $group->is_broadcast && !$me->canBroadcast()) {
                return $this->error('Hanya Developer IT, HRD, atau Owner yang boleh mengirim pesan di grup pengumuman ini.', 403);
            }
        }

        $receiverClass = null;
        if ($request->type === 'dm') {
            $receiverClass = $request->receiver_type === 'admin' ? User::class : Sales::class;
        }

        // Handle file upload with compression
        //
        // `file_url` sengaja dibiarkan kosong untuk pesan baru. Alamat berkas
        // sekarang berupa tautan bertanda tangan yang berlaku 12 jam, jadi
        // kalau disimpan ke kolom ia sudah mati besok paginya. `file_path`
        // yang jadi patokan, dan BerkasChat yang membentuk alamatnya setiap
        // kali pesannya dikirim ke client.
        $fileUrl     = null;
        $fileName    = null;
        $storedPath  = null;
        $messageType = 'text';

        if ($request->hasFile('audio')) {
            // Voice message — store webm/ogg as-is
            $audioFile   = $request->file('audio');
            $ext         = $audioFile->getClientOriginalExtension() ?: 'webm';
            $messageType = 'audio';
            $fileName    = 'voice_' . now()->format('Ymd_His') . '.' . $ext;
            $storedPath  = $audioFile->storeAs('chat-audio', Str::uuid() . '.' . $ext, 'local');
        } elseif ($request->hasFile('file')) {
            $file    = $request->file('file');
            $mime    = $file->getMimeType();
            $isImage = str_starts_with($mime, 'image/');

            if ($isImage) {
                // Compress image untuk chat — 1280px width, quality 82 (jernih, bisa dizoom)
                $messageType = 'image';
                $fileName    = Str::uuid() . '.jpg';
                $storedPath  = $this->uploadChatImage($file, 'chat-files');
            } else {
                // Non-image: store as-is (PDF, DOC, XLS are already compressed formats)
                $messageType = 'file';
                $fileName    = $file->getClientOriginalName();
                $ext         = $file->getClientOriginalExtension();
                $storedPath  = $file->storeAs('chat-files', Str::uuid() . '.' . $ext, 'local');
            }
        }

        $msg = ChatMessage::create([
            'sender_type'   => get_class($me),
            'sender_id'     => $me->id,
            'receiver_type' => $receiverClass,
            'receiver_id'   => $request->type === 'dm' ? $request->receiver_id : null,
            'group_id'      => $request->type === 'group' ? $request->group_id : null,
            'type'          => $request->type,
            'message_type'  => $messageType,
            'message'       => $request->message ?? ($messageType !== 'text' ? $fileName : '') ?? '',
            'file_url'      => $fileUrl,
            // Sampai sekarang hanya file_url yang diisi di sini, sedangkan
            // aplikasi sopir dan sales membaca file_path — foto kiriman admin
            // tidak pernah muncul di HP. Dua-duanya diisi: file_path jadi
            // patokan, file_url tetap ada demi pesan lama.
            'file_path'     => $storedPath,
            'file_name'     => $fileName,
            'reply_to_id'   => $request->reply_to_id,
            'mentions'      => $this->parseMentionsInput($request->mentions),
        ]);

        \App\Support\SiaranChat::coba(fn () => broadcast(new ChatMessageSent($msg)), 'chat admin');
        SendChatNotification::dispatch($msg);

        // Send mention notifications (group only) — FCM to each mentioned user
        $mentionsArr = is_string($request->mentions) ? json_decode($request->mentions, true) : $request->mentions;
        if ($request->type === 'group' && !empty($mentionsArr)) {
            $this->dispatchMentionNotifs($msg, $mentionsArr);
        }

        // Build minimal maps dari sender yang baru saja kirim (hindari N+1)
        $senderMaps = $this->buildSenderMaps(collect([$msg]));
        return $this->success($this->formatMessage($msg, $senderMaps, $me, get_class($me)), 'Pesan terkirim');
    }

    /**
     * Send FCM notification to each @mentioned user (skip sender).
     */
    private function dispatchMentionNotifs(ChatMessage $msg, array $mentions): void
    {
        // Bulk fetch sender name (1 query, bukan per-mention)
        $senderMaps = $this->buildSenderMaps(collect([$msg]));
        $senderName = $msg->sender_type === User::class
            ? ($senderMaps['users'][$msg->sender_id] ?? 'Admin')
            : ($senderMaps['sales'][$msg->sender_id] ?? 'Sales');

        $body = "@{$senderName} menyebut Anda: {$msg->message}";
        $data = ['type' => 'mention', 'message_id' => (string) $msg->id, 'group_id' => (string) $msg->group_id];

        // Pisahkan admin ids dan sales ids
        $adminIds = collect($mentions)->where('type', 'admin')->pluck('id');
        $salesIds = collect($mentions)->where('type', 'sales')->pluck('id');

        // Bulk fetch tokens — 2 query total, bukan N query
        $adminTokens = $adminIds->isNotEmpty()
            ? User::whereIn('id', $adminIds)->whereNotNull('fcm_token')->pluck('fcm_token', 'id')
            : collect();
        $salesTokens = $salesIds->isNotEmpty()
            ? Sales::whereIn('id', $salesIds)->whereNotNull('fcm_token')->pluck('fcm_token', 'id')
            : collect();

        foreach ($mentions as $m) {
            $modelClass = $m['type'] === 'admin' ? User::class : Sales::class;
            if ($modelClass === $msg->sender_type && (int)$m['id'] === $msg->sender_id) continue;

            $token = $m['type'] === 'admin'
                ? ($adminTokens[(int)$m['id']] ?? null)
                : ($salesTokens[(int)$m['id']] ?? null);

            if ($token) \App\Services\FcmService::sendNotification($token, 'Disebutkan di grup', $body, $data);
        }
    }

    /**
     * mentions bisa berupa array (JSON body) atau JSON string (FormData).
     */
    private function parseMentionsInput(mixed $mentions): ?string
    {
        if (!$mentions) return null;
        if (is_array($mentions)) return json_encode($mentions);
        if (is_string($mentions)) {
            $decoded = json_decode($mentions, true);
            return is_array($decoded) ? $mentions : null;
        }
        return null;
    }

    public function editMessage(Request $request, int $id): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:2000']);

        $me  = $request->user();
        $msg = ChatMessage::find($id);

        if (!$msg || $msg->is_deleted) {
            return $this->error('Pesan tidak ditemukan', 404);
        }

        // Only the sender can edit their own message
        if ($msg->sender_id !== $me->id || $msg->sender_type !== get_class($me)) {
            return $this->error('Tidak bisa mengedit pesan orang lain', 403);
        }

        // Only text messages can be edited
        if ($msg->message_type !== 'text') {
            return $this->error('Hanya pesan teks yang bisa diedit', 422);
        }

        $msg->update([
            'message'   => $request->message,
            'is_edited' => true,
        ]);

        // Broadcast update ke semua channel yang relevan
        try {
            \App\Support\SiaranChat::coba(fn () => broadcast(new ChatMessageSent($msg)), 'chat admin');
        } catch (\Throwable) {}

        $maps = $this->buildSenderMaps(collect([$msg]));
        return $this->success($this->formatMessage($msg, $maps, $me, get_class($me)), 'Pesan berhasil diedit');
    }

    public function deleteMessage(Request $request, int $id): JsonResponse
    {
        $me  = $request->user();
        $msg = ChatMessage::find($id);

        if (!$msg || $msg->is_deleted) {
            return $this->error('Pesan tidak ditemukan', 404);
        }

        // Only the sender can delete their own message
        if ($msg->sender_id !== $me->id || $msg->sender_type !== get_class($me)) {
            return $this->error('Tidak bisa menghapus pesan orang lain', 403);
        }

        $msg->update([
            'is_deleted' => true,
            'message'    => '',
        ]);

        // Broadcast deletion — reuse ChatMessageSent so frontend updates the bubble
        try {
            \App\Support\SiaranChat::coba(fn () => broadcast(new ChatMessageSent($msg)), 'chat admin');
        } catch (\Throwable) {}

        return $this->success(null, 'Pesan berhasil dihapus');
    }

    public function createGroup(Request $request): JsonResponse
    {
        $request->validate([
            'nama'         => 'required|string|max:100',
            'is_broadcast' => 'nullable|boolean',
            'member_ids'   => 'nullable|array',
            'member_ids.*' => 'integer',
        ]);

        $me = $request->user(); // App\Models\User (admin)

        $isBroadcast = (bool) $request->boolean('is_broadcast');
        if ($isBroadcast && !$me->canBroadcast()) {
            return $this->error('Hanya Developer IT, HRD, atau Owner yang boleh membuat grup pengumuman (broadcast).', 403);
        }

        $group = ChatGroup::create([
            'nama'         => trim($request->nama),
            'creator_type' => get_class($me),
            'creator_id'   => $me->id,
            'is_broadcast' => $isBroadcast,
        ]);

        // Always insert creator (admin) as group admin member
        ChatGroupMember::create([
            'group_id'    => $group->id,
            'member_type' => get_class($me),
            'member_id'   => $me->id,
            'is_admin'    => true,
        ]);

        // Insert additional sales members if provided
        foreach (($request->member_ids ?? []) as $salesId) {
            ChatGroupMember::create([
                'group_id'    => $group->id,
                'member_type' => Sales::class,
                'member_id'   => $salesId,
                'is_admin'    => false,
            ]);
        }

        return $this->success([
            'id'   => $group->id,
            'nama' => $group->nama,
        ], 'Grup berhasil dibuat');
    }

    public function groups(Request $request): JsonResponse
    {
        $me      = $request->user();
        $meClass = get_class($me);

        $groupIds = ChatGroupMember::where('member_type', $meClass)
            ->where('member_id', $me->id)->pluck('group_id');

        $groupList = ChatGroup::whereIn('id', $groupIds)->get();

        // Batch: last message per group
        $lastMsgs = ChatMessage::where('type', 'group')
            ->whereIn('group_id', $groupIds)
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('group_id')
            ->keyBy('group_id');

        // Batch: member count per group
        $memberCounts = ChatGroupMember::whereIn('group_id', $groupIds)
            ->selectRaw('group_id, count(*) as cnt')
            ->groupBy('group_id')
            ->pluck('cnt', 'group_id');

        // Batch: read ids
        $readIds = ChatMessageRead::where('reader_type', $meClass)
            ->where('reader_id', $me->id)
            ->pluck('message_id');

        // Batch: unread count per group
        $unreadByGroup = ChatMessage::where('type', 'group')
            ->whereIn('group_id', $groupIds)
            ->where('is_deleted', false)
            ->where(fn($q) => $q->where('sender_type', '!=', $meClass)->orWhere('sender_id', '!=', $me->id))
            ->whereNotIn('id', $readIds)
            ->selectRaw('group_id, count(*) as cnt')
            ->groupBy('group_id')
            ->pluck('cnt', 'group_id');

        $groups = $groupList->map(fn($g) => [
            'id'           => $g->id,
            'nama'         => $g->nama,
            'is_broadcast' => $g->is_broadcast,
            'member_count' => $memberCounts[$g->id] ?? 0,
            'last_message' => $lastMsgs->get($g->id)?->message,
            'last_time'    => $lastMsgs->get($g->id)?->created_at?->format('H:i'),
            'unread'       => $unreadByGroup[$g->id] ?? 0,
        ]);

        return $this->success($groups);
    }

    public function groupMessages(Request $request, int $groupId): JsonResponse
    {
        $me       = $request->user();
        $meClass  = get_class($me);
        $beforeId = $request->query('before_id');
        $limit    = 40;

        // Pastikan user adalah member grup — cegah akses grup lain
        $isMember = ChatGroupMember::where('group_id', $groupId)
            ->where('member_type', $meClass)->where('member_id', $me->id)->exists();
        if (!$isMember) return $this->error('Akses ditolak', 403);

        $query = ChatMessage::where('type', 'group')->where('group_id', $groupId)
            ->where('is_deleted', false);

        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        $rawCollection = $query->orderBy('id', 'desc')->limit($limit + 1)->get();
        $hasMore       = $rawCollection->count() > $limit;
        $msgCollection = $rawCollection->take($limit)->reverse()->values();

        $starredIds = ChatMessageStar::where('user_type', $meClass)
            ->where('user_id', $me->id)
            ->whereIn('message_id', $msgCollection->pluck('id'))
            ->pluck('message_id')->flip()->all();

        // Reactions for group messages
        $grpMsgIds      = $msgCollection->pluck('id')->all();
        $grpReactRaw    = ChatMessageReaction::whereIn('message_id', $grpMsgIds)->get();
        $grpReactionsMap = [];
        foreach ($grpReactRaw->groupBy('message_id') as $mid => $rows) {
            foreach ($rows->groupBy('emoji') as $emoji => $group) {
                $grpReactionsMap[$mid][] = [
                    'emoji'         => $emoji,
                    'count'         => $group->count(),
                    'reacted_by_me' => $group->contains(fn($r) => $r->user_type === $meClass && $r->user_id === $me->id),
                ];
            }
        }

        $maps     = $this->buildSenderMaps($msgCollection);
        $messages = $msgCollection->map(fn($m) => $this->formatMessage($m, $maps, $me, $meClass, [], $starredIds, $grpReactionsMap));

        $unreadIds = ChatMessage::where('type', 'group')->where('group_id', $groupId)
            ->where(fn($q) => $q->where('sender_type', '!=', $meClass)->orWhere('sender_id', '!=', $me->id))
            ->whereNotIn('id', function ($q) use ($me, $meClass) {
                $q->select('message_id')->from('chat_message_reads')
                  ->where('reader_type', $meClass)->where('reader_id', $me->id);
            })->pluck('id');

        if ($unreadIds->isNotEmpty()) {
            $now = now()->toDateTimeString();
            ChatMessageRead::upsert(
                $unreadIds->map(fn($id) => [
                    'message_id'  => $id,
                    'reader_type' => $meClass,
                    'reader_id'   => $me->id,
                    'read_at'     => $now,
                ])->all(),
                ['message_id', 'reader_type', 'reader_id'],
                ['read_at']
            );
        }

        return $this->success([
            'messages' => $messages,
            'has_more' => $hasMore,
            'oldest_id' => $msgCollection->first()?->id,
        ]);
    }

    public function groupInfo(Request $request, int $groupId): JsonResponse
    {
        $me      = $request->user();
        $meClass = get_class($me);

        $group = ChatGroup::find($groupId);
        if (!$group) return $this->error('Grup tidak ditemukan', 404);

        // Pastikan user adalah anggota grup
        $isMember = ChatGroupMember::where('group_id', $groupId)
            ->where('member_type', $meClass)->where('member_id', $me->id)->exists();
        if (!$isMember) return $this->error('Akses ditolak', 403);

        $members = ChatGroupMember::where('group_id', $groupId)->get();

        // Grup laporan kiriman juga berisi sopir, jadi daftar anggotanya tidak
        // boleh menganggap "bukan admin berarti sales".
        $peta = \App\Support\PengirimChat::peta($members, 'member_type', 'member_id');

        $memberList = $members->map(function ($m) use ($peta, $me, $meClass) {
            $name = \App\Support\PengirimChat::dariPeta($peta, $m->member_type, $m->member_id);
            $role = \App\Support\PengirimChat::peran($m->member_type);
            return [
                'id'         => $m->member_id,
                'type'       => $role,
                'nama'       => $name,
                'initial'    => strtoupper(substr($name, 0, 1)),
                'is_admin'   => $m->is_admin,
                'is_me'      => $m->member_type === $meClass && $m->member_id === $me->id,
            ];
        })->values();

        $amAdmin = $members->where('member_type', $meClass)
            ->where('member_id', $me->id)->first()?->is_admin ?? false;

        return $this->success([
            'id'         => $group->id,
            'nama'       => $group->nama,
            'is_broadcast' => $group->is_broadcast,
            'created_at' => $group->created_at?->format('d M Y'),
            'am_admin'   => $amAdmin,
            'members'    => $memberList,
        ]);
    }

    public function addMember(Request $request, int $groupId): JsonResponse
    {
        $me      = $request->user();
        $meClass = get_class($me);

        $request->validate([
            'sales_id' => 'required|integer|exists:sales,id',
        ]);

        // Hanya group admin yang boleh tambah anggota
        $amAdmin = ChatGroupMember::where('group_id', $groupId)
            ->where('member_type', $meClass)->where('member_id', $me->id)
            ->where('is_admin', true)->exists();
        if (!$amAdmin) return $this->error('Hanya admin grup yang bisa menambah anggota', 403);

        // Cek sudah jadi anggota
        $alreadyMember = ChatGroupMember::where('group_id', $groupId)
            ->where('member_type', Sales::class)->where('member_id', $request->sales_id)->exists();
        if ($alreadyMember) return $this->error('Sales sudah menjadi anggota grup ini', 422);

        ChatGroupMember::create([
            'group_id'    => $groupId,
            'member_type' => Sales::class,
            'member_id'   => $request->sales_id,
            'is_admin'    => false,
        ]);

        return $this->success(null, 'Anggota berhasil ditambahkan');
    }

    public function removeMember(Request $request, int $groupId, int $memberId): JsonResponse
    {
        $me      = $request->user();
        $meClass = get_class($me);

        // Hanya admin grup yang boleh keluarkan anggota, atau keluar sendiri
        $myMembership = ChatGroupMember::where('group_id', $groupId)
            ->where('member_type', $meClass)->where('member_id', $me->id)->first();

        $isSelf = ($meClass === Sales::class && $me->id === $memberId)
               || ($meClass === User::class && $me->id === $memberId);

        if (!$isSelf && !$myMembership?->is_admin) {
            return $this->error('Hanya admin grup yang bisa mengeluarkan anggota', 403);
        }

        // Cari member yang akan dikeluarkan (bisa Sales atau User)
        $member = ChatGroupMember::where('group_id', $groupId)
            ->where('member_id', $memberId)
            ->first();
        if (!$member) return $this->error('Anggota tidak ditemukan', 404);

        // Jangan biarkan satu-satunya admin dihapus
        if ($member->is_admin) {
            $adminCount = ChatGroupMember::where('group_id', $groupId)->where('is_admin', true)->count();
            if ($adminCount <= 1) return $this->error('Tidak bisa menghapus satu-satunya admin grup', 422);
        }

        $member->delete();
        return $this->success(null, 'Anggota berhasil dikeluarkan');
    }

    public function renameGroup(Request $request, int $groupId): JsonResponse
    {
        $me      = $request->user();
        $meClass = get_class($me);

        $request->validate(['nama' => 'required|string|max:100']);

        $amAdmin = ChatGroupMember::where('group_id', $groupId)
            ->where('member_type', $meClass)->where('member_id', $me->id)
            ->where('is_admin', true)->exists();
        if (!$amAdmin) return $this->error('Hanya admin grup yang bisa mengganti nama', 403);

        $group = ChatGroup::find($groupId);
        if (!$group) return $this->error('Grup tidak ditemukan', 404);

        $group->update(['nama' => trim($request->nama)]);
        return $this->success(['nama' => $group->nama], 'Nama grup berhasil diubah');
    }

    public function searchMessages(Request $request): JsonResponse
    {
        $request->validate([
            'q'            => 'required|string|min:2|max:100',
            'contact_id'   => 'nullable|integer',
            'contact_type' => 'nullable|in:admin,sales,group',
        ]);

        $me      = $request->user();
        $meClass = get_class($me);
        $q       = trim($request->q);

        $query = ChatMessage::where('is_deleted', false)
            ->where('message_type', 'text')
            ->where('message', 'like', '%' . $q . '%');

        $contactId   = $request->integer('contact_id');
        $contactType = $request->query('contact_type');

        if ($contactType === 'group') {
            // Pastikan user anggota grup tersebut
            $isMember = ChatGroupMember::where('group_id', $contactId)
                ->where('member_type', $meClass)->where('member_id', $me->id)->exists();
            if (!$isMember) return $this->success([]);
            $query->where('type', 'group')->where('group_id', $contactId);
        } elseif ($contactId && $contactType) {
            $contactClass = $contactType === 'admin' ? User::class : Sales::class;
            $query->where('type', 'dm')
                ->where(function ($q2) use ($me, $meClass, $contactId, $contactClass) {
                    $q2->where(fn($q3) => $q3
                        ->where('sender_type', $meClass)->where('sender_id', $me->id)
                        ->where('receiver_type', $contactClass)->where('receiver_id', $contactId))
                      ->orWhere(fn($q3) => $q3
                        ->where('sender_type', $contactClass)->where('sender_id', $contactId)
                        ->where('receiver_type', $meClass)->where('receiver_id', $me->id));
                });
        } else {
            // Global search — hanya pesan yang melibatkan user ini
            $groupIds = ChatGroupMember::where('member_type', $meClass)
                ->where('member_id', $me->id)->pluck('group_id');
            $query->where(function ($q2) use ($me, $meClass, $groupIds) {
                $q2->where(fn($q3) => $q3->where('type', 'group')->whereIn('group_id', $groupIds))
                  ->orWhere(fn($q3) => $q3->where('type', 'dm')
                    ->where(fn($q4) => $q4
                        ->where('sender_type', $meClass)->where('sender_id', $me->id)
                        ->orWhere('receiver_type', $meClass)->where('receiver_id', $me->id)));
            });
        }

        $msgs = $query->orderBy('created_at', 'desc')->limit(30)->get();
        $maps = $this->buildSenderMaps($msgs);

        $results = $msgs->map(fn($m) => [
            'id'           => $m->id,
            'message'      => $m->message,
            'sender_name'  => $m->sender_type === User::class
                ? ($maps['users'][$m->sender_id] ?? 'Admin')
                : ($maps['sales'][$m->sender_id] ?? 'Sales'),
            'time'         => $m->created_at?->format('H:i'),
            'date'         => $m->created_at?->format('d M Y'),
            'created_at'   => $m->created_at?->toISOString(),
            'type'         => $m->type,
            'group_id'     => $m->group_id,
            'sender_id'    => $m->sender_id,
            'sender_type'  => $m->sender_type,
            'receiver_id'  => $m->receiver_id,
            'receiver_type'=> $m->receiver_type,
        ])->values();

        return $this->success($results);
    }

    public function starMessage(Request $request, int $id): JsonResponse
    {
        $me      = $request->user();
        $meClass = get_class($me);

        $msg = ChatMessage::find($id);
        if (!$msg || $msg->is_deleted) return $this->error('Pesan tidak ditemukan', 404);

        ChatMessageStar::firstOrCreate([
            'message_id' => $id,
            'user_type'  => $meClass,
            'user_id'    => $me->id,
        ], ['starred_at' => now()]);

        return $this->success(null, 'Pesan dibintangi');
    }

    public function unstarMessage(Request $request, int $id): JsonResponse
    {
        $me      = $request->user();
        $meClass = get_class($me);

        ChatMessageStar::where('message_id', $id)
            ->where('user_type', $meClass)
            ->where('user_id', $me->id)
            ->delete();

        return $this->success(null, 'Bintang dihapus');
    }

    public function starredMessages(Request $request): JsonResponse
    {
        $me      = $request->user();
        $meClass = get_class($me);

        $stars = ChatMessageStar::where('user_type', $meClass)
            ->where('user_id', $me->id)
            ->with('message')
            ->orderBy('starred_at', 'desc')
            ->limit(100)
            ->get();

        // Filter out deleted messages
        $msgs = $stars->filter(fn($s) => $s->message && !$s->message->is_deleted)
            ->map(fn($s) => $s->message);

        $maps    = $this->buildSenderMaps($msgs);

        // Bulk-fetch receiver names dan grup names untuk context — hindari N+1
        $dmMsgs      = $msgs->where('type', 'dm');
        $groupMsgs   = $msgs->where('type', 'group');
        $recvUserIds = $dmMsgs->where('receiver_type', User::class)->pluck('receiver_id')->unique();
        $recvSalesIds= $dmMsgs->where('receiver_type', Sales::class)->pluck('receiver_id')->unique();
        $groupIds    = $groupMsgs->pluck('group_id')->unique();
        $recvUsers   = $recvUserIds->isNotEmpty()  ? User::whereIn('id', $recvUserIds)->pluck('name', 'id')           : collect();
        $recvSales   = $recvSalesIds->isNotEmpty() ? Sales::whereIn('id', $recvSalesIds)->pluck('nama', 'id')          : collect();
        $groupNames  = $groupIds->isNotEmpty()     ? \App\Models\ChatGroup::whereIn('id', $groupIds)->pluck('nama', 'id') : collect();

        $results = $msgs->map(function ($m) use ($maps, $stars, $recvUsers, $recvSales, $groupNames) {
            $senderName = $m->sender_type === User::class
                ? ($maps['users'][$m->sender_id] ?? 'Admin')
                : ($maps['sales'][$m->sender_id] ?? 'Sales');

            // Konteks percakapan — nama kontak/grup (dari bulk-fetched maps)
            $contextName = null;
            if ($m->type === 'group') {
                $contextName = $groupNames[$m->group_id] ?? 'Grup';
            } else {
                $other = $m->receiver_type === User::class
                    ? ($recvUsers[$m->receiver_id] ?? null)
                    : ($recvSales[$m->receiver_id] ?? null);
                $contextName = $other ?? $senderName;
            }

            return [
                'id'           => $m->id,
                'message'      => $m->message,
                'message_type' => $m->message_type,
                'file_url'     => \App\Support\BerkasChat::url($m),
                'file_name'    => $m->file_name,
                'sender_name'  => $senderName,
                'sender_initial' => strtoupper(substr($senderName, 0, 1)),
                'time'         => $m->created_at?->format('H:i'),
                'date'         => $m->created_at?->format('d M Y'),
                'created_at'   => $m->created_at?->toISOString(),
                'type'         => $m->type,
                'group_id'     => $m->group_id,
                'receiver_id'  => $m->receiver_id,
                'receiver_type'=> $m->receiver_type,
                'sender_id'    => $m->sender_id,
                'sender_type'  => $m->sender_type,
                'context_name' => $contextName,
                'starred_at'   => $stars->firstWhere('message_id', $m->id)?->starred_at?->toISOString(),
            ];
        })->values();

        return $this->success($results);
    }

    // ── Link Preview ───────────────────────────────────────────────────────

    /**
     * GET /admin/chat/link-preview?url=https://...
     * Fetch OG/meta tags dari URL eksternal, return title/desc/image/favicon.
     * Cache 1 jam per URL.
     */
    public function linkPreview(Request $request): JsonResponse
    {
        $request->validate(['url' => 'required|url|max:500']);
        $url = $request->url;

        // ── SSRF Guard — tolak URL ke internal network / loopback ──────────
        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'])) {
            return $this->error('URL tidak diizinkan', 422);
        }
        $host = $parsed['host'] ?? '';
        // Blokir IP literal (e.g. http://192.168.x.x) langsung
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->error('URL tidak diizinkan', 422);
        }
        // Resolve hostname dan blokir IP privat / loopback
        $resolvedIp = @gethostbyname($host);
        if ($resolvedIp && filter_var($resolvedIp, FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return $this->error('URL tidak diizinkan', 422);
        }
        // ───────────────────────────────────────────────────────────────────

        $cacheKey = 'link_preview_' . md5($url);

        $result = \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () use ($url) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(5)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; TMKBot/1.0)'])
                    ->get($url);

                if (!$response->ok()) return null;

                $html   = $response->body();
                $parsed = parse_url($url);
                $origin = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

                // Extract OG / meta tags
                $title       = $this->extractMeta($html, ['og:title', 'twitter:title', 'title']);
                $description = $this->extractMeta($html, ['og:description', 'twitter:description', 'description']);
                $image       = $this->extractMeta($html, ['og:image', 'twitter:image']);
                $siteName    = $this->extractMeta($html, ['og:site_name']);

                // Make image URL absolute
                if ($image && !str_starts_with($image, 'http')) {
                    $image = $origin . '/' . ltrim($image, '/');
                }

                return [
                    'url'         => $url,
                    'title'       => $title       ? mb_substr($title, 0, 120)       : null,
                    'description' => $description ? mb_substr($description, 0, 200) : null,
                    'image'       => $image,
                    'site_name'   => $siteName ?? $parsed['host'] ?? '',
                    'favicon'     => $origin . '/favicon.ico',
                ];
            } catch (\Throwable) {
                return null;
            }
        });

        return $this->success($result);
    }

    private function extractMeta(string $html, array $names): ?string
    {
        foreach ($names as $name) {
            if ($name === 'title') {
                // <title> tag
                if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
                    return trim(html_entity_decode(strip_tags($m[1])));
                }
                continue;
            }
            // og:* / twitter:* / name= tags
            $patterns = [
                // <meta property="og:title" content="...">
                '/<meta[^>]+property=["\']' . preg_quote($name, '/') . '["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/si',
                '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+property=["\']' . preg_quote($name, '/') . '["\'][^>]*>/si',
                // <meta name="description" content="...">
                '/<meta[^>]+name=["\']' . preg_quote($name, '/') . '["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/si',
                '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']' . preg_quote($name, '/') . '["\'][^>]*>/si',
            ];
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html, $m)) {
                    $val = trim(html_entity_decode($m[1]));
                    if ($val) return $val;
                }
            }
        }
        return null;
    }

    /** Save FCM token dari browser Vue admin */
    public function saveFcmToken(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string|max:500']);
        $request->user()->update(['fcm_token' => $request->token]);
        return $this->success(null);
    }

    /** Update last_seen_at — dipanggil tiap 30 detik dari client */
    public function ping(Request $request): JsonResponse
    {
        $request->user()->update(['last_seen_at' => now()]);
        return $this->success(null);
    }

    /** Batch cek status online beberapa kontak sekaligus */
    public function onlineStatus(Request $request): JsonResponse
    {
        $request->validate([
            'contacts' => 'required|array|max:50',
            'contacts.*.id'   => 'required|integer',
            'contacts.*.type' => 'required|in:admin,sales',
        ]);

        $onlineThreshold = now()->subMinutes(2);
        $result = [];

        $adminIds = collect($request->contacts)->where('type', 'admin')->pluck('id');
        $salesIds = collect($request->contacts)->where('type', 'sales')->pluck('id');

        if ($adminIds->isNotEmpty()) {
            User::whereIn('id', $adminIds)->get(['id', 'last_seen_at'])
                ->each(function ($u) use (&$result, $onlineThreshold) {
                    $result["admin-{$u->id}"] = [
                        'is_online'     => $u->last_seen_at && $u->last_seen_at->gt($onlineThreshold),
                        'last_seen_at'  => $u->last_seen_at?->toISOString(),
                    ];
                });
        }

        if ($salesIds->isNotEmpty()) {
            Sales::whereIn('id', $salesIds)->get(['id', 'last_seen_at'])
                ->each(function ($s) use (&$result, $onlineThreshold) {
                    $result["sales-{$s->id}"] = [
                        'is_online'    => $s->last_seen_at && $s->last_seen_at->gt($onlineThreshold),
                        'last_seen_at' => $s->last_seen_at?->toISOString(),
                    ];
                });
        }

        return $this->success($result);
    }

    public function typing(Request $request): JsonResponse
    {
        $me = $request->user();
        try {
            \App\Support\SiaranChat::coba(fn () => broadcast(new TypingIndicator(
                    $me->id,
                    get_class($me),
                    $me->name,
                    $request->chat_room_id,
                    $request->is_typing ?? true
                ))->toOthers(), 'chat admin');
        } catch (\Throwable) {
        }
        return $this->success(null);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $me      = $request->user();
        $meClass = get_class($me);
        $readIds = ChatMessageRead::where('reader_type', $meClass)
            ->where('reader_id', $me->id)->pluck('message_id');

        $count = ChatMessage::where('receiver_type', $meClass)
            ->where('receiver_id', $me->id)
            ->where('is_deleted', false)
            ->whereNotIn('id', $readIds)->count();

        return $this->success(['count' => $count]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $me      = $request->user();
        $meClass = get_class($me);
        ChatMessageRead::upsert(
            [['message_id' => $id, 'reader_type' => $meClass, 'reader_id' => $me->id, 'read_at' => now()->toDateTimeString()]],
            ['message_id', 'reader_type', 'reader_id'],
            ['read_at']
        );
        return $this->success(null);
    }

    // ── Pin Pesan ──────────────────────────────────────────────────────────

    /** Build a deterministic room key for a DM conversation */
    private function dmRoomKey($me, $contact, string $contactType): string
    {
        $meClass      = get_class($me);
        $contactClass = $contactType === 'sales' ? Sales::class : User::class;
        // Ensure consistent ordering: smaller id first, with type prefix
        $a = ['id' => $me->id, 'type' => $meClass === User::class ? 'user' : 'sales'];
        $b = ['id' => $contact->id, 'type' => $contactClass === User::class ? 'user' : 'sales'];
        if ($a['id'] > $b['id'] || ($a['id'] === $b['id'] && $a['type'] > $b['type'])) {
            [$a, $b] = [$b, $a];
        }
        return "dm-{$a['type']}-{$a['id']}-{$b['type']}-{$b['id']}";
    }

    /** Format a pin record for API response */
    private function formatPin(ChatPinnedMessage $pin): array
    {
        $msg        = $pin->message;
        $maps       = $this->buildSenderMaps(collect([$msg]));
        $senderName = $msg->sender_type === User::class
            ? ($maps['users'][$msg->sender_id] ?? 'Admin')
            : ($maps['sales'][$msg->sender_id] ?? 'Sales');
        return [
            'id'             => $pin->id,
            'message_id'     => $pin->message_id,
            'room_key'       => $pin->room_key,
            'message'        => $msg->message,
            'message_type'   => $msg->message_type,
            'file_url'       => \App\Support\BerkasChat::url($msg),
            'file_name'      => $msg->file_name,
            'sender_name'    => $senderName,
            'pinned_at'      => $pin->created_at?->toISOString(),
        ];
    }

    /** GET /admin/chat/pinned?contact_id=&contact_type=  OR  ?group_id= */
    public function getPinnedMessage(Request $request): JsonResponse
    {
        $me = $request->user();

        if ($request->has('group_id')) {
            $roomKey = "group-{$request->group_id}";
        } else {
            $contactType  = $request->input('contact_type', 'sales');
            $contactClass = $contactType === 'sales' ? Sales::class : User::class;
            $contact      = $contactClass::findOrFail($request->contact_id);
            $roomKey      = $this->dmRoomKey($me, $contact, $contactType);
        }

        $pin = ChatPinnedMessage::where('room_key', $roomKey)->with('message')->first();
        return $this->success($pin ? $this->formatPin($pin) : null);
    }

    /** POST /admin/chat/messages/{id}/pin */
    public function pinMessage(Request $request, int $id): JsonResponse
    {
        $me  = $request->user();
        $msg = ChatMessage::findOrFail($id);

        // Derive room key
        if ($msg->type === 'group') {
            $roomType = 'group';
            $roomKey  = "group-{$msg->group_id}";
        } else {
            $roomType = 'dm';
            $contactType  = $msg->sender_type === User::class && $msg->sender_id === $me->id
                ? ($msg->receiver_type === Sales::class ? 'sales' : 'admin')
                : ($msg->sender_type === Sales::class ? 'sales' : 'admin');
            $contactId   = $msg->sender_type === User::class && $msg->sender_id === $me->id
                ? $msg->receiver_id
                : $msg->sender_id;
            $contactClass = $msg->sender_type === User::class && $msg->sender_id === $me->id
                ? $msg->receiver_type
                : $msg->sender_type;
            $contact = $contactClass::findOrFail($contactId);
            $roomKey = $this->dmRoomKey($me, $contact, $contactType);
        }

        $pin = ChatPinnedMessage::updateOrCreate(
            ['room_type' => $roomType, 'room_key' => $roomKey],
            ['message_id' => $id, 'pinned_by_type' => get_class($me), 'pinned_by_id' => $me->id]
        );
        $pin->load('message');
        $formatted = $this->formatPin($pin);

        // Broadcast ke semua channel yg relevan
        $channels = $this->pinChannels($me, $msg, $roomKey);
        try {
            \App\Support\SiaranChat::coba(fn () => broadcast(new ChatMessagePinned($channels, $formatted)), 'chat admin');
        } catch (\Throwable) {}

        return $this->success($formatted);
    }

    /** DELETE /admin/chat/messages/{id}/pin */
    public function unpinMessage(Request $request, int $id): JsonResponse
    {
        $me  = $request->user();
        $msg = ChatMessage::findOrFail($id);

        if ($msg->type === 'group') {
            $roomKey = "group-{$msg->group_id}";
        } else {
            $contactType  = $msg->sender_type === User::class && $msg->sender_id === $me->id
                ? ($msg->receiver_type === Sales::class ? 'sales' : 'admin')
                : ($msg->sender_type === Sales::class ? 'sales' : 'admin');
            $contactId   = $msg->sender_type === User::class && $msg->sender_id === $me->id
                ? $msg->receiver_id
                : $msg->sender_id;
            $contactClass = $msg->sender_type === User::class && $msg->sender_id === $me->id
                ? $msg->receiver_type
                : $msg->sender_type;
            $contact = $contactClass::findOrFail($contactId);
            $roomKey = $this->dmRoomKey($me, $contact, $contactType);
        }

        ChatPinnedMessage::where('room_key', $roomKey)->delete();

        $channels = $this->pinChannels($me, $msg, $roomKey);
        try {
            \App\Support\SiaranChat::coba(fn () => broadcast(new ChatMessagePinned($channels, null)), 'chat admin');
        } catch (\Throwable) {}

        return $this->success(null);
    }

    /** Derive broadcast channels for a pin event */
    private function pinChannels($me, ChatMessage $msg, string $roomKey): array
    {
        if ($msg->type === 'group') {
            // Broadcast to all members of the group
            $memberChannels = ChatGroupMember::where('group_id', $msg->group_id)
                ->get()
                ->map(function ($m) {
                    $prefix = $m->member_type === User::class ? 'chat.user' : 'chat.sales';
                    return "{$prefix}.{$m->member_id}";
                })->toArray();
            return $memberChannels;
        }

        // DM — both participants
        $meChannel = get_class($me) === User::class
            ? "chat.user.{$me->id}"
            : "chat.sales.{$me->id}";
        $otherChannel = $msg->sender_type === User::class && $msg->sender_id === $me->id
            ? ($msg->receiver_type === Sales::class ? "chat.sales.{$msg->receiver_id}" : "chat.user.{$msg->receiver_id}")
            : ($msg->sender_type === Sales::class   ? "chat.sales.{$msg->sender_id}"   : "chat.user.{$msg->sender_id}");

        return array_unique([$meChannel, $otherChannel]);
    }

    // ── Forward Pesan ──────────────────────────────────────────────────────

    /**
     * POST /admin/chat/messages/{id}/forward
     * body: [{ type: 'sales'|'admin'|'group', id: int }]  (bisa banyak tujuan)
     */
    public function forwardMessage(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'targets'            => 'required|array|min:1|max:10',
            'targets.*.type'     => 'required|in:sales,admin,group',
            'targets.*.id'       => 'required|integer',
        ]);

        $me      = $request->user();
        $meClass = get_class($me);
        $source  = ChatMessage::findOrFail($id);

        if ($source->is_deleted) {
            return $this->error('Tidak dapat meneruskan pesan yang sudah dihapus', 422);
        }

        $sent = [];
        foreach ($request->targets as $target) {
            if ($target['type'] === 'group') {
                $group = ChatGroup::findOrFail($target['id']);
                // Must be a member
                $isMember = ChatGroupMember::where('group_id', $group->id)
                    ->where('member_type', $meClass)->where('member_id', $me->id)->exists();
                if (!$isMember) continue;

                $msg = ChatMessage::create([
                    'sender_type'        => $meClass,
                    'sender_id'          => $me->id,
                    'receiver_type'      => null,
                    'receiver_id'        => null,
                    'group_id'           => $group->id,
                    'type'               => 'group',
                    'message_type'       => $source->message_type,
                    'message'            => $source->message,
                    'file_url'           => $source->file_url,
                    'file_name'          => $source->file_name,
                    'file_path'          => $source->file_path,
                    'forwarded_from_id'  => $source->id,
                ]);
            } else {
                $targetClass = $target['type'] === 'sales' ? Sales::class : User::class;
                $targetUser  = $targetClass::findOrFail($target['id']);

                $msg = ChatMessage::create([
                    'sender_type'        => $meClass,
                    'sender_id'          => $me->id,
                    'receiver_type'      => $targetClass,
                    'receiver_id'        => $targetUser->id,
                    'group_id'           => null,
                    'type'               => 'dm',
                    'message_type'       => $source->message_type,
                    'message'            => $source->message,
                    'file_url'           => $source->file_url,
                    'file_name'          => $source->file_name,
                    'file_path'          => $source->file_path,
                    'forwarded_from_id'  => $source->id,
                ]);
            }

            // Build minimal maps — sender selalu $me, tidak perlu N+1
            $fwdMaps = $this->buildSenderMaps(collect([$msg]));
            $formatted = $this->formatMessage($msg, $fwdMaps, $me, $meClass);
            try {
                \App\Support\SiaranChat::coba(fn () => broadcast(new ChatMessageSent($msg)), 'chat admin');
            } catch (\Throwable) {}

            $sent[] = $formatted;
        }

        return $this->success(['forwarded' => count($sent), 'messages' => $sent]);
    }

    // ── Reaction Emoji ─────────────────────────────────────────────────────

    private const ALLOWED_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '😡'];

    /** Build reaction summary for a message, relative to current user */
    private function buildReactions(int $messageId, string $meType, int $meId): array
    {
        $rows = ChatMessageReaction::where('message_id', $messageId)->get();
        $grouped = $rows->groupBy('emoji');
        $result  = [];
        foreach ($grouped as $emoji => $group) {
            $result[] = [
                'emoji'        => $emoji,
                'count'        => $group->count(),
                'reacted_by_me' => $group->contains(fn($r) => $r->user_type === $meType && $r->user_id === $meId),
            ];
        }
        return $result;
    }

    /** POST /admin/chat/messages/{id}/react  body: { emoji } */
    public function addReaction(Request $request, int $id): JsonResponse
    {
        $request->validate(['emoji' => 'required|string']);
        $emoji = $request->emoji;
        if (!in_array($emoji, self::ALLOWED_EMOJIS)) {
            return $this->error('Emoji tidak diizinkan', 422);
        }

        $me      = $request->user();
        $meType  = get_class($me);
        $meId    = $me->id;
        $msg     = ChatMessage::findOrFail($id);

        // Upsert — toggle: if already reacted, remove; else add
        $exists = ChatMessageReaction::where([
            'message_id' => $id, 'emoji' => $emoji,
            'user_type'  => $meType, 'user_id' => $meId,
        ])->first();

        if ($exists) {
            $exists->delete();
        } else {
            ChatMessageReaction::create([
                'message_id' => $id, 'emoji' => $emoji,
                'user_type'  => $meType, 'user_id' => $meId,
                'reacted_at' => now(),
            ]);
        }

        $reactions = $this->buildReactions($id, $meType, $meId);
        $channels  = $this->reactionChannels($me, $msg);

        try {
            \App\Support\SiaranChat::coba(fn () => broadcast(new ChatMessageReacted($channels, $id, $reactions)), 'chat admin');
        } catch (\Throwable) {}

        return $this->success(['message_id' => $id, 'reactions' => $reactions]);
    }

    /** Determine broadcast channels for a reaction event */
    private function reactionChannels($me, ChatMessage $msg): array
    {
        if ($msg->type === 'group') {
            return ChatGroupMember::where('group_id', $msg->group_id)
                ->get()
                ->map(fn($m) => ($m->member_type === User::class ? 'chat.user' : 'chat.sales') . ".{$m->member_id}")
                ->toArray();
        }
        $meChannel    = get_class($me) === User::class ? "chat.user.{$me->id}" : "chat.sales.{$me->id}";
        $otherChannel = $msg->sender_type === User::class && $msg->sender_id === $me->id
            ? ($msg->receiver_type === Sales::class ? "chat.sales.{$msg->receiver_id}" : "chat.user.{$msg->receiver_id}")
            : ($msg->sender_type === Sales::class   ? "chat.sales.{$msg->sender_id}"   : "chat.user.{$msg->sender_id}");
        return array_unique([$meChannel, $otherChannel]);
    }

    private function formatReply(?ChatMessage $m): ?array
    {
        if (!$m) return null;
        $maps       = $this->buildSenderMaps(collect([$m]));
        $senderName = $m->sender_type === User::class
            ? ($maps['users'][$m->sender_id] ?? 'Admin')
            : ($maps['sales'][$m->sender_id] ?? 'Sales');
        return [
            'sender_name'  => $senderName,
            'message'      => $m->message,
            'message_type' => $m->message_type,
            'file_name'    => $m->file_name,
            'is_deleted'   => $m->is_deleted,
        ];
    }

    private function buildSenderMaps(\Illuminate\Support\Collection $msgs): array
    {
        $userIds  = $msgs->where('sender_type', User::class)->pluck('sender_id')->unique();
        $salesIds = $msgs->where('sender_type', Sales::class)->pluck('sender_id')->unique();

        $users = User::whereIn('id', $userIds)->get(['id', 'name', 'avatar_url']);
        $sales = Sales::whereIn('id', $salesIds)->get(['id', 'nama', 'foto_profil']);

        return [
            'users'        => $users->pluck('name', 'id'),
            'sales'        => $sales->pluck('nama', 'id'),
            // Lewat accessor, bukan Storage::url: keduanya mengembalikan
            // tautan bertanda tangan yang habis masa berlakunya, dan itu satu
            // -satunya cara `<img src>` bisa memuat foto dari route berpenjaga.
            'user_avatars' => $users->mapWithKeys(fn($u) => [$u->id => $u->avatar_url_full]),
            'sales_avatars' => $sales->mapWithKeys(fn($s) => [$s->id => $s->foto_profil_url]),
        ];
    }

    private function formatMessage(ChatMessage $m, array $maps = [], $me = null, string $meClass = '', array $readByContactMap = [], array $starredIds = [], array $reactionsMap = []): array
    {
        // Jika maps tidak disediakan, build otomatis (1 query) daripada N+1
        if (empty($maps)) {
            $maps = $this->buildSenderMaps(collect([$m]));
        }
        $senderName   = $m->sender_type === User::class
            ? ($maps['users'][$m->sender_id] ?? 'Admin')
            : ($maps['sales'][$m->sender_id] ?? 'Sales');
        $senderAvatar = $m->sender_type === User::class
            ? ($maps['user_avatars'][$m->sender_id] ?? null)
            : ($maps['sales_avatars'][$m->sender_id] ?? null);
        $isMyMsg   = $me && $m->sender_id === $me->id && $m->sender_type === $meClass;
        $isRead    = $isMyMsg && !empty($readByContactMap) && isset($readByContactMap[$m->id]);
        $isStarred = !empty($starredIds) && isset($starredIds[$m->id]);
        $reactions = $reactionsMap[$m->id] ?? [];

        return [
            'id'             => $m->id,
            'sender_id'      => $m->sender_id,
            'sender_type'    => $m->sender_type,
            'sender_name'    => $senderName,
            'sender_avatar'  => $senderAvatar,
            'sender_initial' => strtoupper(substr($senderName, 0, 1)),
            'message'        => $m->message,
            'message_type'   => $m->message_type,
            'file_url'       => \App\Support\BerkasChat::url($m),
            'thumb_url'      => \App\Support\BerkasChat::thumb($m),
            'file_name'      => $m->file_name,
            'type'           => $m->type,
            'group_id'       => $m->group_id,
            'receiver_id'    => $m->receiver_id,
            'receiver_type'  => $m->receiver_type,
            'reply_to_id'    => $m->reply_to_id,
            'reply_to'       => $m->reply_to_id ? $this->formatReply($m->replyTo) : null,
            'is_deleted'     => $m->is_deleted,
            'is_edited'      => $m->is_edited,
            'is_read'           => $isRead,
            'is_starred'        => $isStarred,
            'reactions'         => $reactions,
            'is_forwarded'      => !is_null($m->forwarded_from_id),
            'forwarded_from_id' => $m->forwarded_from_id,
            'mentions'          => $m->mentions ?? [],
            'created_at'        => $m->created_at?->toISOString(),
            'time'              => $m->created_at?->format('H:i'),
        ];
    }
}
