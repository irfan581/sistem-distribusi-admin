<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Jobs\SendChatNotification;
use App\Models\ChatMessage;
use App\Models\ChatMessageRead;
use App\Models\ChatPoll;
use App\Models\ChatPollVote;
use App\Models\Sales;
use App\Support\PengirimChat;
use App\Models\User;
use App\Events\ChatMessageSent;
use Illuminate\Http\Request;
use App\Events\TypingIndicator;

class ChatController extends Controller
{
    // ── KONTAK ────────────────────────────────────────

    public function contacts(Request $request)
    {
        try {
            $sales      = auth()->user();
            $salesClass = get_class($sales);
            $userClass  = 'App\\Models\\User';

            $onlineThreshold = now()->subMinutes(5);
            $readIds = \App\Models\ChatMessageRead::where('reader_type', $salesClass)
                ->where('reader_id', $sales->id)
                ->pluck('message_id');

            $buildContact = function ($contact, string $type) use ($sales, $salesClass, $readIds, $onlineThreshold) {
                $contactClass = get_class($contact);
                $lastMsg = ChatMessage::where('type', 'dm')->where('is_deleted', false)
                    ->where(function ($q) use ($sales, $salesClass, $contact, $contactClass) {
                        $q->where(fn($q2) => $q2->where('sender_type', $salesClass)->where('sender_id', $sales->id)->where('receiver_type', $contactClass)->where('receiver_id', $contact->id))
                          ->orWhere(fn($q2) => $q2->where('sender_type', $contactClass)->where('sender_id', $contact->id)->where('receiver_type', $salesClass)->where('receiver_id', $sales->id));
                    })->latest()->first();

                $unread = ChatMessage::where('type', 'dm')
                    ->where('sender_type', $contactClass)->where('sender_id', $contact->id)
                    ->where('receiver_type', $salesClass)->where('receiver_id', $sales->id)
                    ->where('is_deleted', false)
                    ->whereNotIn('id', $readIds)
                    ->count();

                $name = $type === 'sales' ? $contact->nama : $contact->name;
                $lastSeen = $contact->last_seen_at ?? null;
                return [
                    'id'           => $contact->id,
                    'type'         => $type,
                    'nama'         => $name,
                    'initial'      => strtoupper(substr($name, 0, 1)),
                    'area'         => $type === 'sales' ? ($contact->area ?? null) : null,
                    'last_message' => $lastMsg?->message ?? null,
                    'last_time'    => $lastMsg?->created_at?->setTimezone('Asia/Jakarta')->format('H:i') ?? null,
                    'unread'       => $unread,
                    'is_online'    => $lastSeen && $lastSeen->gt($onlineThreshold),
                    'last_seen_at' => $lastSeen?->toISOString(),
                ];
            };

            $admins    = User::all()->map(fn($u) => $buildContact($u, 'user'));
            $salesList = Sales::where('id', '!=', $sales->id)->where('is_active', true)
                ->get()->map(fn($s) => $buildContact($s, 'sales'));

            return response()->json([
                'status' => 'success',
                'data'   => ['admins' => $admins, 'sales' => $salesList],
            ]);
        } catch (\Exception $e) {
            \Log::error('ChatController@contacts: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal memuat kontak'], 500);
        }
    }

    // ── GRUP ──────────────────────────────────────────

    public function myGroups(Request $request)
    {
        try {
            $sales    = auth()->user();
            $groupIds = ChatGroupMember::where('member_type', get_class($sales))
                ->where('member_id', $sales->id)
                ->pluck('group_id');

            $salesClass = get_class($sales);

            $lastMsgs = ChatMessage::where('type', 'group')
                ->whereIn('group_id', $groupIds)
                ->where('is_deleted', false)
                ->orderBy('created_at', 'desc')
                ->get()->unique('group_id')->keyBy('group_id');

            $memberCounts = ChatGroupMember::whereIn('group_id', $groupIds)
                ->selectRaw('group_id, count(*) as cnt')
                ->groupBy('group_id')
                ->pluck('cnt', 'group_id');

            $readIds = \App\Models\ChatMessageRead::where('reader_type', $salesClass)
                ->where('reader_id', $sales->id)->pluck('message_id');

            $unreadByGroup = ChatMessage::where('type', 'group')
                ->whereIn('group_id', $groupIds)
                ->where('is_deleted', false)
                ->where(fn($q) => $q->where('sender_type', '!=', $salesClass)->orWhere('sender_id', '!=', $sales->id))
                ->whereNotIn('id', $readIds)
                ->selectRaw('group_id, count(*) as cnt')
                ->groupBy('group_id')
                ->pluck('cnt', 'group_id');

            // ->get() bukan [$g->id]: grup yang belum punya satu pun pesan —
            // seperti grup laporan yang baru dibuat — membuat akses larik
            // langsung melempar "Undefined array key" dan seluruh daftar grup
            // gagal dimuat.
            $groups = ChatGroup::whereIn('id', $groupIds)->get()->map(fn($g) => [
                'id'           => $g->id,
                'nama'         => $g->nama,
                'member_count' => $memberCounts[$g->id] ?? 0,
                'last_message' => $lastMsgs->get($g->id)?->message ?? null,
                'last_time'    => $lastMsgs->get($g->id)?->created_at?->setTimezone('Asia/Jakarta')->format('H:i') ?? null,
                'unread'       => $unreadByGroup[$g->id] ?? 0,
            ]);

            return response()->json(['status' => 'success', 'data' => $groups]);
        } catch (\Exception $e) {
            \Log::error('ChatController@myGroups: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal memuat grup'], 500);
        }
    }

    public function createGroup(Request $request)
    {
        try {
            $request->validate([
                'nama'       => 'required|string|max:100',
                'member_ids' => 'required|array|min:1',
            ]);

            $sales = auth()->user();
            $group = ChatGroup::create([
                'nama'         => trim($request->nama),
                'creator_type' => get_class($sales),
                'creator_id'   => $sales->id,
                'is_broadcast' => false,
            ]);

            ChatGroupMember::create([
                'group_id'    => $group->id,
                'member_type' => get_class($sales),
                'member_id'   => $sales->id,
                'is_admin'    => true,
            ]);

            foreach ($request->member_ids as $userId) {
                ChatGroupMember::create([
                    'group_id'    => $group->id,
                    'member_type' => 'App\\Models\\User',
                    'member_id'   => $userId,
                    'is_admin'    => false,
                ]);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Grup berhasil dibuat',
                'data'    => ['id' => $group->id, 'nama' => $group->nama],
            ]);
        } catch (\Exception $e) {
            \Log::error('ChatController@createGroup: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal membuat grup'], 500);
        }
    }

    public function groupMessages(Request $request, int $groupId)
    {
        try {
            $sales = auth()->user();
            $page  = $request->input('page', 1);
            $limit = 50;

            // Cek apakah member grup
            $isMember = ChatGroupMember::where('group_id', $groupId)
                ->where('member_type', get_class($sales))
                ->where('member_id', $sales->id)
                ->exists();

            if (!$isMember) {
                return response()->json(['status' => 'error', 'message' => 'Tidak diizinkan'], 403);
            }

            // Mark as read
            $unreadIds = ChatMessage::where('type', 'group')
                ->where('group_id', $groupId)
                ->where(fn($q) => $q->where('sender_type', '!=', get_class($sales))->orWhere('sender_id', '!=', $sales->id))
                ->whereNotIn('id', function ($q) use ($sales) {
                    $q->select('message_id')->from('chat_message_reads')
                      ->where('reader_type', get_class($sales))
                      ->where('reader_id', $sales->id);
                })->pluck('id');

            if ($unreadIds->isNotEmpty()) {
                $now = now()->toDateTimeString();
                ChatMessageRead::upsert(
                    $unreadIds->map(fn($id) => [
                        'message_id'  => $id,
                        'reader_type' => get_class($sales),
                        'reader_id'   => $sales->id,
                        'read_at'     => $now,
                    ])->all(),
                    ['message_id', 'reader_type', 'reader_id'],
                    ['read_at']
                );
            }

            $msgs = ChatMessage::where('type', 'group')
                ->where('group_id', $groupId)
                ->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get()
                ->reverse()
                ->values();

            $maps = $this->buildSenderMaps($msgs);
            $messages = $msgs->map(fn($msg) => $this->formatMessage($msg, $sales, $maps));

            return response()->json(['status' => 'success', 'data' => $messages, 'page' => $page]);
        } catch (\Exception $e) {
            \Log::error('ChatController@groupMessages: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal memuat pesan'], 500);
        }
    }

    // ── DM ────────────────────────────────────────────

    public function dmHistory(Request $request)
    {
        try {
            $request->validate([
                'contact_id'   => 'required|integer',
                'contact_type' => 'nullable|in:user,sales',
            ]);

            $sales        = auth()->user();
            $contactId    = $request->contact_id;
            $contactType  = $request->input('contact_type', 'user');
            $receiverType = $contactType === 'user' ? 'App\\Models\\User' : 'App\\Models\\Sales';
            $page         = $request->input('page', 1);
            $limit        = 50;

            // Mark as read
            $unreadIds = ChatMessage::where('type', 'dm')
                ->where('sender_type', $receiverType)
                ->where('sender_id', $contactId)
                ->where('receiver_type', get_class($sales))
                ->where('receiver_id', $sales->id)
                ->whereNotIn('id', function ($q) use ($sales) {
                    $q->select('message_id')->from('chat_message_reads')
                      ->where('reader_type', get_class($sales))
                      ->where('reader_id', $sales->id);
                })->pluck('id');

            if ($unreadIds->isNotEmpty()) {
                $now = now()->toDateTimeString();
                ChatMessageRead::upsert(
                    $unreadIds->map(fn($id) => [
                        'message_id'  => $id,
                        'reader_type' => get_class($sales),
                        'reader_id'   => $sales->id,
                        'read_at'     => $now,
                    ])->all(),
                    ['message_id', 'reader_type', 'reader_id'],
                    ['read_at']
                );

                try {
                    \App\Support\SiaranChat::coba(fn () => broadcast(new \App\Events\ChatMessagesRead(
                            readerId: $sales->id,
                            readerType: get_class($sales),
                            senderId: $contactId,
                            senderType: $receiverType,
                            chatRoomId: $request->input('contact_type', 'user') === 'sales'
                                ? "sales:{$contactId}"
                                : "user:{$contactId}",
                        )), 'chat sales');
                } catch (\Throwable) {
                }
            }

            $msgs = ChatMessage::where('type', 'dm')
                ->where(function ($q) use ($sales, $contactId, $receiverType) {
                    $q->where(fn($q2) => $q2->where('sender_type', get_class($sales))->where('sender_id', $sales->id)->where('receiver_type', $receiverType)->where('receiver_id', $contactId))
                      ->orWhere(fn($q2) => $q2->where('sender_type', $receiverType)->where('sender_id', $contactId)->where('receiver_type', get_class($sales))->where('receiver_id', $sales->id));
                })
                ->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get()
                ->reverse()
                ->values();

            $maps = $this->buildSenderMaps($msgs);
            $messages = $msgs->map(fn($msg) => $this->formatMessage($msg, $sales, $maps));

            return response()->json(['status' => 'success', 'data' => $messages, 'page' => $page]);
        } catch (\Exception $e) {
            \Log::error('ChatController@dmHistory: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal memuat pesan'], 500);
        }
    }

    // ── KIRIM PESAN ───────────────────────────────────

    public function send(Request $request)
    {
        try {
            $request->validate([
                'type'         => 'required|in:dm,group',
                'contact_id'   => 'required|integer',
                'contact_type' => 'nullable|in:user,sales',
                'message'      => 'nullable|string|max:5000',
                'file'         => 'nullable|file|max:10240',
                'message_type' => 'nullable|in:text,image,file',
                'reply_to_id'  => 'nullable|integer|exists:chat_messages,id',
            ]);

            $sales    = auth()->user();
            $filePath = null;
            $msgType  = $request->input('message_type', 'text');
            $msgText  = $request->input('message', '');

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $mime = $file->getMimeType();
                if (str_starts_with($mime, 'image/')) {
                    $filePath = $file->store('chat-images', 'local');
                    $msgType  = 'image';
                    $msgText  = $msgText ?: basename($filePath);
                } else {
                    $filePath = $file->store('chat-files', 'local');
                    $msgType  = 'file';
                    $msgText  = $msgText ?: $file->getClientOriginalName();
                }
            }

            if (empty($msgText) && !$filePath) {
                return response()->json(['status' => 'error', 'message' => 'Pesan tidak boleh kosong'], 422);
            }

            $contactType  = $request->input('contact_type', 'user');
            $receiverType = $contactType === 'user' ? 'App\\Models\\User' : 'App\\Models\\Sales';

            // Sopir hanya berkepentingan di grup laporan; percakapan pribadi
            // butuh daftar kontak yang tidak ada di aplikasinya.
            if ($sales instanceof \App\Models\drivers && $request->type !== 'group') {
                return response()->json(['status' => 'error', 'message' => 'Tidak diizinkan'], 403);
            }

            // Tanpa ini siapa pun yang punya token bisa menulis ke grup mana
            // pun cuma dengan menebak nomornya — grup laporan kiriman memuat
            // bukti bongkar, jadi pengirimnya harus benar-benar anggota.
            if ($request->type === 'group') {
                $anggota = ChatGroupMember::where('group_id', $request->contact_id)
                    ->where('member_type', get_class($sales))
                    ->where('member_id', $sales->id)
                    ->exists();

                if (! $anggota) {
                    return response()->json(['status' => 'error', 'message' => 'Tidak diizinkan'], 403);
                }
            }

            $data = [
                'sender_type'  => get_class($sales),
                'sender_id'    => $sales->id,
                'type'         => $request->type,
                'message_type' => $msgType,
                'message'      => $msgText,
                'file_path'    => $filePath,
            ];

            if ($request->type === 'group') {
                $data['group_id'] = $request->contact_id;
            } else {
                $data['receiver_type'] = $receiverType;
                $data['receiver_id']   = $request->contact_id;
            }

            if ($request->filled('reply_to_id')) {
                $data['reply_to_id'] = $request->reply_to_id;
            }

            $chat = ChatMessage::create($data);
            \App\Support\SiaranChat::coba(fn () => broadcast(new ChatMessageSent($chat)), 'chat sales');
            SendChatNotification::dispatch($chat);

            return response()->json([
                'status'  => 'success',
                'message' => 'Pesan terkirim',
                'data'    => [
                    'id'           => $chat->id,
                    'message'      => $chat->message,
                    'message_type' => $chat->message_type,
                    'file_url'     => \App\Support\BerkasChat::url($chat),
                    'time'         => $chat->created_at->setTimezone('Asia/Jakarta')->format('H:i'),
                    'created_at'   => $chat->created_at->setTimezone('Asia/Jakarta')->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('ChatController@send: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal mengirim pesan'], 500);
        }
    }

    // ── UNREAD COUNT ──────────────────────────────────

    public function unreadCount(Request $request)
    {
        try {
            $sales = auth()->user();

            $readerType = get_class($sales);
            $readIds = ChatMessageRead::where('reader_type', $readerType)
                ->where('reader_id', $sales->id)
                ->pluck('message_id');

            $dmUnread = ChatMessage::where('type', 'dm')
                ->where('receiver_type', $readerType)
                ->where('receiver_id', $sales->id)
                ->where('is_deleted', false)
                ->whereNotIn('id', $readIds)
                ->count();

            $groupIds = ChatGroupMember::where('member_type', $readerType)
                ->where('member_id', $sales->id)
                ->pluck('group_id');

            $groupUnread = ChatMessage::where('type', 'group')
                ->whereIn('group_id', $groupIds)
                ->where('is_deleted', false)
                ->where(fn($q) => $q->where('sender_type', '!=', $readerType)->orWhere('sender_id', '!=', $sales->id))
                ->whereNotIn('id', $readIds)
                ->count();

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'dm'    => $dmUnread,
                    'group' => $groupUnread,
                    'total' => $dmUnread + $groupUnread,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('ChatController@unreadCount: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal memuat unread'], 500);
        }
    }

    // ── EDIT PESAN ────────────────────────────────────

    public function editPesan(Request $request, int $messageId)
    {
        try {
            $request->validate(['message' => 'required|string|max:5000']);

            $sales = auth()->user();
            $msg   = ChatMessage::findOrFail($messageId);

            if ($msg->sender_type !== get_class($sales) || $msg->sender_id !== $sales->id) {
                return response()->json(['status' => 'error', 'message' => 'Tidak diizinkan'], 403);
            }

            if ($msg->message_type !== 'text') {
                return response()->json(['status' => 'error', 'message' => 'Hanya pesan teks yang bisa diedit'], 422);
            }

            $msg->update([
                'message'   => trim($request->message),
                'is_edited' => true,
                'edited_at' => now(),
            ]);

            return response()->json(['status' => 'success', 'message' => 'Pesan berhasil diedit']);
        } catch (\Exception $e) {
            \Log::error('ChatController@editPesan: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal edit pesan'], 500);
        }
    }

    // ── HAPUS PESAN ───────────────────────────────────

    public function hapusPesan(Request $request, int $messageId)
    {
        try {
            $sales = auth()->user();
            $msg   = ChatMessage::findOrFail($messageId);

            if ($msg->sender_type !== get_class($sales) || $msg->sender_id !== $sales->id) {
                return response()->json(['status' => 'error', 'message' => 'Tidak diizinkan'], 403);
            }

            $msg->update(['is_deleted' => true]);

            return response()->json(['status' => 'success', 'message' => 'Pesan dihapus']);
        } catch (\Exception $e) {
            \Log::error('ChatController@hapusPesan: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal menghapus pesan'], 500);
        }
    }

    // ── HELPER FORMAT PESAN ───────────────────────────

    private function buildSenderMaps(\Illuminate\Support\Collection $msgs): array
    {
        // Sopir ikut mengirim ke grup laporan, jadi petanya tidak boleh cuma
        // mengenal admin dan sales — lihat PengirimChat.
        return PengirimChat::peta($msgs);
    }

    private function formatMessage(ChatMessage $msg, $sales, array $senderMaps = []): array
    {
        $isMe = $msg->sender_type === get_class($sales) && $msg->sender_id === $sales->id;

        $name = !empty($senderMaps)
            ? PengirimChat::dariPeta($senderMaps, $msg->sender_type, $msg->sender_id)
            : PengirimChat::nama($msg->sender_type, $msg->sender_id);

        $pollData = null;
        if ($msg->message_type === 'poll' && $msg->poll_id) {
            $poll = ChatPoll::find($msg->poll_id);
            if ($poll) {
                $pollData = [
                    'id'          => $poll->id,
                    'pertanyaan'  => $poll->pertanyaan,
                    'pilihan'     => $poll->pilihan,
                    'is_closed'   => $poll->is_closed,
                    'total_votes' => $poll->votes()->count(),
                    'hasil'       => $poll->hasil,
                    'sudah_vote'  => $poll->sudahVote(get_class($sales), $sales->id),
                    'votes_saya'  => $poll->votes()->where('voter_type', get_class($sales))->where('voter_id', $sales->id)->pluck('pilihan_index')->toArray(),
                ];
            }
        }

        // Build reply_to data if this message is a reply
        $replyToData = null;
        if ($msg->reply_to_id) {
            $replyMsg = ChatMessage::find($msg->reply_to_id);
            if ($replyMsg) {
                $replyName = PengirimChat::nama($replyMsg->sender_type, $replyMsg->sender_id);
                $replyToData = [
                    'id'           => $replyMsg->id,
                    'sender_name'  => $replyName,
                    'message'      => $replyMsg->is_deleted ? null : $replyMsg->message,
                    'message_type' => $replyMsg->message_type,
                ];
            }
        }

        return [
            'id'             => $msg->id,
            'sender_id'      => $msg->sender_id,
            'sender_type'    => $msg->sender_type,
            'sender_name'    => $name,
            // Peran pengirim supaya aplikasi bisa membedakan laporan bongkar
            // dari obrolan biasa tanpa menebak-nebak dari nama kelas model.
            'sender_role'    => PengirimChat::peran($msg->sender_type),
            'sender_initial' => strtoupper(substr($name, 0, 1)),
            'is_me'          => $isMe,
            'message'        => $msg->is_deleted ? null : $msg->message,
            'message_type'   => $msg->message_type,
            'file_url'       => $msg->is_deleted ? null : \App\Support\BerkasChat::url($msg),
            // Gelembung chat lebarnya cuma 300px. Foto laporan bongkar punya
            // versi 320px; sisanya jatuh kembali ke berkas penuh.
            'thumb_url'      => $msg->is_deleted ? null : \App\Support\BerkasChat::thumb($msg),
            'file_name'      => $msg->file_path ? basename($msg->file_path) : $msg->file_name,
            'poll'           => $pollData,
            'reply_to'       => $replyToData,
            'is_deleted'     => $msg->is_deleted,
            'is_edited'      => $msg->is_edited,
            'is_read'        => $msg->isReadBy(get_class($sales), $sales->id),
            'time'           => $msg->created_at->setTimezone('Asia/Jakarta')->format('H:i'),
            'date'           => $msg->created_at->setTimezone('Asia/Jakarta')->toDateString(),
            'created_at'     => $msg->created_at->setTimezone('Asia/Jakarta')->toISOString(),
        ];
    }

    // ── typing chat ─────────────────────────────────────


    public function typing(Request $request) {
        try {
            $validated = $request->validate([
                'chat_room_id' => [
                    'required',
                    'string',
                    'regex:/^(user|sales|group):\d+$/',
                ],
                'is_typing' => 'required|boolean',
            ]);

            $user = auth()->user();

            // Sales model pakai field 'nama', User (admin) pakai 'name'
            $displayName = $user->nama ?? $user->name ?? 'Unknown';

            \App\Support\SiaranChat::coba(fn () => broadcast(new TypingIndicator(
                    $user->id,
                    get_class($user),
                    $displayName,
                    $validated['chat_room_id'],
                    $validated['is_typing']
                ))->toOthers(), 'chat sales');

            return response()->json([
                'status' => 'success',
                'message' => 'Typing status sent',
            ]);
        } catch (\Exception $e) {
            \Log::error('Typing indicator error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send typing status',
            ], 500);
        }
    }

    public function heartbeat(Request $request)
    {
        try {
            $validated = $request->validate([
                'is_online' => 'nullable|boolean',
            ]);

            $sales = auth()->user();
            $sales->update([
                'last_seen_at' => now(),
                'is_online'    => $validated['is_online'] ?? true,
            ]);

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            \Log::error('Heartbeat error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal mengirim heartbeat'], 500);
        }
    }
}
