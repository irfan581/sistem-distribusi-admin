<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendChatNotification;
use App\Models\ChatPoll;
use App\Models\ChatPollVote;
use App\Models\ChatMessage;
use App\Events\ChatMessageSent;
use App\Http\Requests\Api\ChatPollRequest;
use App\Http\Resources\Api\ChatPollResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ChatPollController extends Controller
{
    // Buat polling baru
    public function store(ChatPollRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            $poll = ChatPoll::create([
                'creator_type'    => get_class($user),
                'creator_id'      => $user->id,
                'pertanyaan'      => $request->pertanyaan,
                'pilihan'         => $request->pilihan,
                'multiple_choice' => $request->multiple_choice ?? false,
                'tutup_pada'      => $request->tutup_pada,
            ]);

            // Buat chat message untuk polling
            $data = [
                'sender_type'  => get_class($user),
                'sender_id'    => $user->id,
                'type'         => $request->type,
                'message_type' => 'poll',
                'message'      => $request->pertanyaan,
                'poll_id'      => $poll->id,
            ];

            if ($request->type === 'group') {
                $data['group_id'] = $request->group_id;
            } else {
                $data['receiver_type'] = $request->receiver_type === 'user'
                    ? 'App\\Models\\User' : 'App\\Models\\Sales';
                $data['receiver_id'] = $request->receiver_id;
            }

            $message = ChatMessage::create($data);

            \App\Support\SiaranChat::coba(fn () => broadcast(new ChatMessageSent($message))->toOthers(), 'polling chat');
            SendChatNotification::dispatch($message);

                        return response()->json([
                            'success' => true,
                            'message' => 'Polling berhasil dibuat',
                            'data'    => new ChatPollResource($poll),
                        ], 201);
                    } catch (\Exception $e) {
                        Log::error('ChatPoll store error', ['error' => $e->getMessage()]);
                        return response()->json([
                            'success' => false,
                            'message' => 'Gagal membuat polling.',
                        ], 500);
                    }
                }

    // Vote polling
    public function vote(Request $request, int $pollId): JsonResponse
    {
        try {
            $request->validate([
                'pilihan_index' => ['required', 'array', 'min:1'],
                'pilihan_index.*' => ['integer', 'min:0'],
            ]);

            $user = $request->user();
            $poll = ChatPoll::findOrFail($pollId);

            if ($poll->is_closed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Polling sudah ditutup.',
                ], 400);
            }

            // Jika tidak multiple choice, hapus vote lama dulu
            if (!$poll->multiple_choice) {
                ChatPollVote::where('poll_id', $pollId)
                    ->where('voter_type', get_class($user))
                    ->where('voter_id', $user->id)
                    ->delete();
            }

            foreach ($request->pilihan_index as $index) {
                ChatPollVote::firstOrCreate([
                    'poll_id'       => $pollId,
                    'voter_type'    => get_class($user),
                    'voter_id'      => $user->id,
                    'pilihan_index' => $index,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Vote berhasil',
                'data'    => new ChatPollResource($poll->fresh()),
            ]);
        } catch (\Exception $e) {
            Log::error('ChatPoll vote error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan vote.',
            ], 500);
        }
    }

    // Tutup polling
    public function close(Request $request, int $pollId): JsonResponse
    {
        try {
            $user = $request->user();
            $poll = ChatPoll::findOrFail($pollId);

            if ($poll->creator_type !== get_class($user) || $poll->creator_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya pembuat polling yang bisa menutup.',
                ], 403);
            }

            $poll->update(['is_closed' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Polling ditutup',
                'data'    => new ChatPollResource($poll->fresh()),
            ]);
        } catch (\Exception $e) {
            Log::error('ChatPoll close error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menutup polling.',
            ], 500);
        }
    }

    // Detail polling
    public function show(Request $request, int $pollId): JsonResponse
    {
        try {
            $poll = ChatPoll::findOrFail($pollId);
            return response()->json([
                'success' => true,
                'data'    => new ChatPollResource($poll),
            ]);
        } catch (\Exception $e) {
            Log::error('ChatPoll show error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data polling.',
            ], 500);
        }
    }
}
