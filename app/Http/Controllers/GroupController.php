<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateGroupRequest;
use App\Http\Resources\GroupUserResource;
use App\Http\Resources\PostAttachmentResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\Post;
use App\Models\PostAttachment;
use App\Models\User;
use App\Notifications\InvitationApproved;
use App\Notifications\InvitationGroup;
use App\Notifications\RequestApproved;
use App\Notifications\RequestToJoinGroup;
use App\Notifications\RoleChanged;
use App\Notifications\SimpleNotification;
use App\Notifications\UserRemovedFromGroup;
use Carbon\Carbon;
use App\Http\Enums\GroupUserRole;
use App\Http\Enums\GroupUserStatus;
use App\Http\Requests\InviteUserRequest;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Models\GroupUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class GroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function profile(Request $request, Group $group)
    {
        $group->load('currentUserGroup');
        $userId = Auth::id();

        if ($group->hasApprovedUser($userId)) {
            $posts = Post::postsForTimeline($userId, false)
                ->leftJoin('groups AS g', 'g.pinned_post_id', 'posts.id')
                ->where('group_id', $group->id)
                ->orderBy('g.pinned_post_id', 'desc')
                ->orderBy('posts.created_at', 'desc')
                ->paginate(10);
            $posts = PostResource::collection($posts);
        } else {
            return Inertia::render('Group/View', [
                'success' => session('success'),
                'group' => new GroupResource($group),
                'posts' => null,
                'users' => [],
                'requests' => []
            ]);
        }

        if ($request->wantsJson()) {
            return $posts;
        }

        $users = User::query()
            ->select(['users.*', 'gu.role', 'gu.status', 'gu.group_id'])
            ->join('group_users AS gu', 'gu.user_id', 'users.id')
            ->orderBy('users.name')
            ->where('group_id', $group->id)
            ->get();
        $requests = $group->pendingUsers()->orderBy('name')->get();
        $photos = PostAttachment::query()
        ->select('post_attachments.*')
        ->join('posts AS p', 'p.id', 'post_attachments.post_id')
        ->where('p.group_id', $group->id)
        ->where('mime', 'like', 'image/%')
        ->latest()
        ->get();

        return Inertia::render('Group/View', [
            'success' => session('success'),
            'posts' => $posts,
            'group' => new GroupResource($group),
            'users' => GroupUserResource::collection($users),
            'requests' => UserResource::collection($requests),
            'photos' => PostAttachmentResource::collection($photos)
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGroupRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();
        $group = Group::create($data);

        $groupUserData = [
            'status' => GroupUserStatus::APPROVED->value,
            'role' => GroupUserRole::ADMIN->value,
            'user_id' => Auth::id(),
            'group_id' => $group->id,
            'created_by' => Auth::id()
        ];

        GroupUser::create($groupUserData);
        $group->status = $groupUserData['status'];
        $group->role = $groupUserData['role'];

        return response(new GroupResource($group), 201);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGroupRequest $request, Group $group)
    {
        $group->update($request->validated());
        return back()->with('success', "Group was updated");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function updateImage(Request $request, Group $group)
    {
        if (!$group->isAdmin(Auth::id())) {
            return response("You don't have permission to perform this action", 403);
        }
        $data = $request->validate([
            'cover' => ['nullable', 'image'],
            'thumbnail' => ['nullable', 'image']
        ]);

        $thumbnail = $data['thumbnail'] ?? null;
        $cover = $data['cover'] ?? null;

        $success = '';
        if ($cover) {
            if ($group->cover_path) {
                Storage::disk('public')->delete($group->cover_path);
            }
            $path = $cover->store('group-'.$group->id, 'public');
            $group->update(['cover_path' => $path]);
            $success = 'Your cover image was updated';
        }

        if ($thumbnail) {
            if ($group->thumbnail_path) {
                Storage::disk('public')->delete($group->thumbnail_path);
            }
            $path = $thumbnail->store('group-'.$group->id, 'public');
            $group->update(['thumbnail_path' => $path]);
            $success = 'Your thumbnail image was updated';
        }
        return back()->with('success', $success);
    }

    public function inviteUsers(InviteUserRequest $request, Group $group)
    {
        $data = $request->validated();
        $user = $request->user;
        $groupUser = $request->groupUser;

        if ($groupUser) {
            $groupUser->delete();
        }

        $hours = 24;
        $token = Str::random(256);

        GroupUser::create([
            'status' => GroupUserStatus::PENDING->value,
            'role' => GroupUserRole::USER->value,
            'token' => $token,
            'token_expire_date' => Carbon::now()->addHours($hours),
            'user_id' => $user->id,
            'group_id' => $group->id,
            'created_by' => Auth::id(),
        ]);

        //$user->notify(new InvitationGroup($group, $hours, $token));

        $subject = "You are invited";
        $content = 'You have been invited to join to group "' . $group->name . '"';
        $contentBottom = 'The link will be valid for next ' . $hours . ' hours';
        $actionText = "Join the Group";
        $url = url(route('group.approveInvitation', $token));
        $user->notify(new SimpleNotification($subject, $content, $actionText, $url, $contentBottom));

        return back()->with('success', 'User was invited to join to group');
    }

    public function approveInvitation(string $token)
    {
        $groupUser = GroupUser::query()
            ->where('token', $token)
            ->first();

        $errorTitle = '';
        if (!$groupUser) {
            $errorTitle = 'The link is not valid';
        } else if ($groupUser->token_used || $groupUser->status === GroupUserStatus::APPROVED->value) {
            $errorTitle = 'The link is already used';
        } else if ($groupUser->token_expire_date < Carbon::now()) {
            $errorTitle = 'The link is expired';
        }

        if ($errorTitle) {
            return \inertia('Error', compact('errorTitle'));
        }

        $groupUser->status = GroupUserStatus::APPROVED->value;
        $groupUser->token_used = Carbon::now();
        $groupUser->save();

        $adminUser = $groupUser->adminUser;

        //$adminUser->notify(new InvitationApproved($groupUser->group, $groupUser->user));
        $subject = "Request was approved";
        $content = 'User "'.$groupUser->user->name.'" has join to group "'.$groupUser->group->name.'"';
        $actionText = "Open Group";
        $url = url(route('group.profile', $groupUser->group));
        $adminUser->notify(new SimpleNotification($subject, $content, $actionText, $url));

        return redirect(route('group.profile', $groupUser->group))->with('success', 'You accepted to join to group "'.$groupUser->group->name.'"');
    }

    public function join(Group $group)
    {
        $user = \request()->user();

        $status = GroupUserStatus::APPROVED->value;
        $successMessage = 'You have joined to group "' . $group->name . '"';
        if (!$group->auto_approval) {
            $status = GroupUserStatus::PENDING->value;

            $subject = "Request to join group";
            $content = 'User "'.$user->name.'" requested to join to group "'.$group->name.'"';
            $actionText = "Approve Request";
            $url = url(route('group.profile', $group));

            //Notification::send($group->adminUsers, new RequestToJoinGroup($group, $user));
            Notification::send($group->adminUsers, new SimpleNotification($subject, $content, $actionText, $url));
            $successMessage = 'Your request has been accepted. You will be notified once you will be approved';
        }

        GroupUser::create([
            'status' => $status,
            'role' => GroupUserRole::USER->value,
            'user_id' => $user->id,
            'group_id' => $group->id,
            'created_by' => $user->id,
        ]);

        return back()->with('success', $successMessage);
    }

    public function approveRequest(Request $request, Group $group)
    {
        if (!$group->isAdmin(Auth::id())) {
            return response("You don't have permission to perform this action", 403);
        }

        $data = $request->validate([
            'user_id' => ['required'],
            'action' => ['required']
        ]);

        $groupUser = GroupUser::where('user_id', $data['user_id'])
            ->where('group_id', $group->id)
            ->where('status', GroupUserStatus::PENDING->value)
            ->first();

        if ($groupUser) {
            $approved = false;
            if ($data['action'] === 'approve') {
                $approved = true;
                $groupUser->status = GroupUserStatus::APPROVED->value;
            } else {
                $groupUser->status = GroupUserStatus::REJECTED->value;
            }
            $groupUser->save();

            $user = $groupUser->user;
            //$user->notify(new RequestApproved($groupUser->group, $user, $approved));

            $status = ($approved ? 'approved' : 'rejected');
            $subject = 'Request was ' . $status;
            $content = 'Your request to join to group "' . $groupUser->group->name . '" has been ' . $status;
            $actionText = "Open Group";
            $url = url(route('group.profile', $group));
            $user->notify(new SimpleNotification($subject, $content, $actionText, $url));

            return back()->with('success', 'User "'.$user->name.'" was '.($approved ? 'approved' : 'rejected'));
        }

        return back();
    }

    public function changeRole(Request $request, Group $group)
    {
        if (!$group->isAdmin(Auth::id())) {
            return response("You don't have permission to perform this action", 403);
        }

        $data = $request->validate([
            'user_id' => ['required'],
            'role' => ['required', Rule::enum(GroupUserRole::class)]
        ]);

        $user_id = $data['user_id'];
        if ($group->isOwner($user_id)) {
            return response("You can't change role of the owner of the group", 403);
        }

        $groupUser = GroupUser::where('user_id', $user_id)
            ->where('group_id', $group->id)
            ->first();

        if ($groupUser) {
            $groupUser->role = $data['role'];
            $groupUser->save();
            //$groupUser->user->notify(new RoleChanged($group, $data['role']));

            $subject = "Role was changed";
            $content = 'Your role was changed into "'.$groupUser->role.'" for group "'.$group->name.'".';
            $actionText = "Open Group";
            $url = url(route('group.profile', $group));
            $groupUser->user->notify(new SimpleNotification($subject, $content, $actionText, $url));
        }
        return back();
    }

    public function removeUser(Request $request, Group $group)
    {
        if (!$group->isAdmin(Auth::id())) {
            return response("You don't have permission to perform this action", 403);
        }

        $data = $request->validate([
            'user_id' => ['required'],
        ]);

        $user_id = $data['user_id'];
        if ($group->isOwner($user_id)) {
            return response("The owner of the group cannot be removed", 403);
        }

        $groupUser = GroupUser::where('user_id', $user_id)
            ->where('group_id', $group->id)
            ->first();

        if ($groupUser) {
            $user = $groupUser->user;
            $groupUser->delete();

            $user->notify(new UserRemovedFromGroup($group));
        }

        return back();
    }
}
