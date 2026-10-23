<?php

namespace App\Services;

use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\ChatMessage;
use App\Models\ChatMessageRead;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ChatContactService
{
    public function loadGroups(): array
    {
        $user = Auth::user();
        $groupIds = ChatGroupMember::where('member_type', get_class($user))
            ->where('member_id', $user->id)
            ->pluck('group_id');

        return ChatGroup::whereIn('id', $groupIds)->with('members')->get()
            ->map(function ($group) use ($user) {
                $lastMsg = ChatMessage::where('type', 'group')
                    ->where('group_id', $group->id)
                    ->where('is_deleted', false)
                    ->latest()->first();

                $unread = ChatMessage::where('type', 'group')
                    ->where('group_id', $group->id)
                    ->where('is_deleted', false)
                    ->where(fn($q) => $q->where('sender_type', '!=', get_class($user))->orWhere('sender_id', '!=', $user->id))
                    ->whereNotIn('id', function ($q) use ($user) {
                        $q->select('message_id')->from('chat_message_reads')
                          ->where('reader_type', get_class($user))->where('reader_id', $user->id);
                    })->count();

                return [
                    'id' => $group->id,
                    'nama' => $group->nama,
                    'member_count' => $group->members->count(),
                    'last_message' => $lastMsg?->message ?? '',
                    'last_time' => $lastMsg?->created_at->format('H:i') ?? '',
                    'unread' => $unread,
                ];
            })->sortByDesc(function($g) {
                return ChatMessage::where('group_id', $g['id'])
                    ->latest()->first()?->created_at ?? now();
            })->values()->all();
    }

    public function loadContacts(string $search = ''): array
    {
        $user = Auth::user();
        $query = Sales::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->get()->map(function ($contact) use ($user) {
            $lastMsg = ChatMessage::where('type', 'dm')
                ->where([
                    ['sender_id', $contact->id],
                    ['receiver_id', $user->id],
                ])
                ->orWhere([
                    ['sender_id', $user->id],
                    ['receiver_id', $contact->id],
                ])
                ->where('is_deleted', false)
                ->latest()
                ->first();

            $unread = ChatMessage::where('type', 'dm')
                ->where('sender_id', $contact->id)
                ->where('receiver_id', $user->id)
                ->where('is_deleted', false)
                ->whereNotIn('id', function ($q) use ($user) {
                    $q->select('message_id')->from('chat_message_reads')
                      ->where('reader_type', get_class($user))->where('reader_id', $user->id);
                })->count();

            return [
                'id' => $contact->id,
                'nama' => $contact->name,
                'area' => $contact->area ?? '',
                'initial' => strtoupper(substr($contact->name, 0, 1)),
                'last_message' => $lastMsg?->message ?? '',
                'last_time' => $lastMsg?->created_at->format('H:i') ?? '',
                'unread' => $unread,
            ];
        })->sortByDesc(function($c) {
            return ChatMessage::where('type', 'dm')
                ->where(function($q) use ($c) {
                    $q->where(['sender_id' => $c['id']])
                      ->orWhere(['receiver_id' => $c['id']]);
                })
                ->latest()
                ->first()?->created_at ?? now();
        })->values()->all();
    }

    public function loadAdmins(string $search = ''): array
    {
        $user = Auth::user();
        $query = User::where('id', '!=', $user->id);

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->get()->map(function ($admin) use ($user) {
            $lastMsg = ChatMessage::where('type', 'dm')
                ->where([
                    ['sender_id', $admin->id],
                    ['receiver_id', $user->id],
                ])
                ->orWhere([
                    ['sender_id', $user->id],
                    ['receiver_id', $admin->id],
                ])
                ->where('is_deleted', false)
                ->latest()
                ->first();

            $unread = ChatMessage::where('type', 'dm')
                ->where('sender_id', $admin->id)
                ->where('receiver_id', $user->id)
                ->where('is_deleted', false)
                ->whereNotIn('id', function ($q) use ($user) {
                    $q->select('message_id')->from('chat_message_reads')
                      ->where('reader_type', get_class($user))->where('reader_id', $user->id);
                })->count();

            return [
                'id' => $admin->id,
                'nama' => $admin->name,
                'area' => '',
                'initial' => strtoupper(substr($admin->name, 0, 1)),
                'last_message' => $lastMsg?->message ?? '',
                'unread' => $unread,
            ];
        })->sortByDesc(function($a) {
            return ChatMessage::where('type', 'dm')
                ->where(function($q) use ($a) {
                    $q->where(['sender_id' => $a['id']])
                      ->orWhere(['receiver_id' => $a['id']]);
                })
                ->latest()
                ->first()?->created_at ?? now();
        })->values()->all();
    }

    public function createGroup(string $groupName, array $memberIds): ChatGroup
    {
        $group = ChatGroup::create(['nama' => $groupName]);

        $user = Auth::user();
        ChatGroupMember::create([
            'group_id' => $group->id,
            'member_type' => get_class($user),
            'member_id' => $user->id,
        ]);

        foreach ($memberIds as $memberId) {
            ChatGroupMember::create([
                'group_id' => $group->id,
                'member_type' => Sales::class,
                'member_id' => $memberId,
            ]);
        }

        return $group;
    }

    public function getGroupInfo(int $groupId): array
    {
        $group = ChatGroup::find($groupId);
        if (!$group) return [];

        return [
            'id' => $group->id,
            'nama' => $group->nama,
            'dibuat' => $group->created_at->translatedFormat('d F Y H:i'),
            'members' => $group->members->map(function($member) {
                $name = $member->memberObject?->name ?? 'Unknown';
                return [
                    'id' => $member->member_id,
                    'nama' => $name,
                    'initial' => strtoupper(substr($name, 0, 1)),
                    'role' => $member->is_admin ? 'Admin' : 'Member',
                    'is_admin' => $member->is_admin,
                ];
            })->values()->all(),
        ];
    }

    public function singkatNama(string $nama): string
    {
        $kata = explode(' ', $nama);
        $hasil = strtoupper(substr($kata[0], 0, 1));

        for ($i = 1; $i < count($kata); $i++) {
            $hasil .= ' ' . strtoupper(substr($kata[$i], 0, 1)) . '.';
        }

        return $hasil;
    }
}
