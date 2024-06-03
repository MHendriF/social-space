<?php

namespace App\Http\Controllers;

use App\Models\Follower;
use App\Models\User;
use App\Notifications\FollowUser;
use App\Notifications\SimpleNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function follow(Request $request, User $user)
    {
        $authUser = Auth::user();
        $data = $request->validate([
            'follow' => ['boolean']
        ]);
        if ($data['follow']) {
            $message = 'You followed user "'.$user->name.'"';
            Follower::create([
                'user_id' => $user->id,
                'follower_id' => Auth::id()
            ]);
        } else {
            $message = 'You unfollowed user "'.$user->name.'"';
            Follower::query()
                ->where('user_id', $user->id)
                ->where('follower_id', $authUser->id)
                ->delete();
        }

        //$user->notify(new FollowUser($authUser, $data['follow']));

        if ($data['follow']) {
            $subject = "New Follower";
            $content = 'User "' . $authUser->username . '" has followed you';
        } else {
            $subject = "Un Following You";
            $content = 'User "' . $authUser->username . '" is no more following you';
        }
        $actionText = "View Profile";
        $url = url(route('profile', $authUser));
        $user->notify(new SimpleNotification($subject, $content, $actionText, $url));

        return back()->with('success', $message);
    }
}
