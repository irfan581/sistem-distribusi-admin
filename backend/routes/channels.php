<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Admin dashboard listens for realtime target updates
Broadcast::channel('visit-targets', function ($user) {
    return $user instanceof \App\Models\User
        && app(\App\Policies\VisitTargetPolicy::class)->viewAny($user);
});

// Sales Flutter app listens for their own target changes
Broadcast::channel('sales.{salesId}.target', function ($user, $salesId) {
    if ($user instanceof \App\Models\Sales) {
        return (int) $user->id === (int) $salesId;
    }
    // Admin can also subscribe (for monitoring)
    return $user instanceof \App\Models\User && $user->isAdminUser();
});

// Sales Flutter app listens for their own DM/chat events
Broadcast::channel('chat.sales.{id}', function ($user, $id) {
    return $user instanceof \App\Models\Sales && (int) $user->id === (int) $id;
});

Broadcast::channel('chat.group.{groupId}', function ($user, $groupId) {
    return \App\Models\ChatGroupMember::where('group_id', $groupId)
        ->where('member_type', get_class($user))
        ->where('member_id', $user->id)
        ->exists();
});
