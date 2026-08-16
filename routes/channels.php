<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\ChatThread;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('thread.{threadId}', function ($user, $threadId) {
    return ChatThread::query()
        ->where('id', $threadId)
        ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
        ->exists()
        ? ['id' => $user->id, 'name' => $user->name]
        : false;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId
        ? ['id' => $user->id, 'name' => $user->name]
        : false;
});

Broadcast::channel('inbox.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
