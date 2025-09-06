<?php

use Illuminate\Support\Facades\Broadcast;
use Cmgmyr\Messenger\Models\Thread;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


// Маршрут авторизации приватных каналов (middleware под свои нужды)
Broadcast::routes(['middleware' => ['web', 'auth']]);

// Доступ к приватному каналу треда
Broadcast::channel('thread.{threadId}', function ($user, $threadId) {
    return Thread::where('id', $threadId)
        ->whereHas('participants', fn($q) => $q->where('user_id', $user->id))
        ->exists()
        ? ['id' => $user->id, 'name' => $user->name]
        : false;
});

// Персональный канал пользователя
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int)$user->id === (int)$userId
        ? ['id' => $user->id, 'name' => $user->name]
        : false;
});

Broadcast::channel('inbox.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});