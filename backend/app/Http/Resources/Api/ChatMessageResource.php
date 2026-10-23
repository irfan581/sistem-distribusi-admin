<?php

namespace App\Http\Resources\Api;

use App\Support\BerkasChat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'sender_type'  => $this->sender_type,
            'sender_id'    => $this->sender_id,
            'sender_name'  => $this->sender_name,
            'sender_initial' => $this->sender_initial,
            'type'         => $this->type,
            'group_id'     => $this->group_id,
            'receiver_type' => $this->receiver_type,
            'receiver_id'  => $this->receiver_id,
            'message_type' => $this->message_type,
            'message'      => $this->message,
            'file_url'     => BerkasChat::url($this->resource),
            // Gelembung chat lebarnya cuma 300px, jadi yang dimuat daftar
            // pesan cukup versi kecilnya. Yang tidak punya thumbnail jatuh
            // kembali ke berkas penuh.
            'thumb_url'    => BerkasChat::thumb($this->resource),
            'created_at'   => $this->created_at->toDateTimeString(),
            'is_deleted'   => $this->is_deleted,
            'is_edited'    => $this->is_edited,
            'edited_at'    => $this->edited_at,
        ];
    }
}
